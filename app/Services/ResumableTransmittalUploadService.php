<?php

namespace App\Services;

use App\Models\TransmittalUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ResumableTransmittalUploadService
{
    public function start(string $ownerHash, string $token, string $name, int $size, string $mime): TransmittalUpload
    {
        $maxBytes = (int) config('ordo.upload.max_bytes');
        abort_if($maxBytes < 1, 500, 'The resumable upload maximum file size is not configured correctly.');
        abort_if($size < 1 || $size > $maxBytes, 422, 'The file size is outside the allowed range.');
        abort_unless(in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), ['pdf', 'jpg', 'jpeg', 'png', 'tif', 'tiff', 'doc', 'docx'], true), 422, 'This file type is not allowed.');

        $chunkSize = (int) config('ordo.upload.chunk_bytes');
        abort_if($chunkSize < 1, 500, 'The resumable upload chunk size is not configured correctly.');
        $chunkCount = (int) ceil($size / $chunkSize);
        $maxChunks = (int) config('ordo.upload.max_chunks', 4096);
        abort_if($maxChunks < 1, 500, 'The resumable upload maximum chunk count is not configured correctly.');
        abort_if($chunkCount > $maxChunks, 422, 'The file requires more upload chunks than the server allows.');
        abort_if((int) config('ordo.upload.expiry_hours') < 1, 500, 'The resumable upload expiry is not configured correctly.');
        $this->completionLockSeconds();
        $upload = TransmittalUpload::query()->create([
            'upload_id' => (string) Str::uuid(),
            'owner_session_hash' => $ownerHash,
            'upload_token_hash' => hash('sha256', $token),
            'original_name' => basename($name),
            'mime_type' => mb_substr($mime, 0, 127),
            'expected_size' => $size,
            'chunk_size' => $chunkSize,
            'chunk_count' => $chunkCount,
            'chunk_hashes' => [],
            'expires_at' => now()->addHours((int) config('ordo.upload.expiry_hours')),
        ]);

        return $upload;
    }

    public function putChunk(string $ownerHash, string $uploadId, string $token, int $index, UploadedFile $chunk): array
    {
        $lockSeconds = max(300, $this->completionLockSeconds());

        return Cache::lock('ordo-upload:'.$uploadId, $lockSeconds)->block(10, function () use ($ownerHash, $uploadId, $token, $index, $chunk): array {
            $upload = $this->ownedUpload($ownerHash, $uploadId, $token);
            abort_unless($upload->status === 'uploading', 409, 'This upload is no longer accepting chunks.');
            abort_if($index < 0 || $index >= $upload->chunk_count, 404);
            $expectedBytes = min($upload->chunk_size, $upload->expected_size - ($index * $upload->chunk_size));
            abort_unless($chunk->getSize() === $expectedBytes, 422, 'The chunk size is invalid.');
            $path = 'transmittal-upload-chunks/'.$uploadId.'/'.$index.'.part';
            $hash = hash_file('sha256', $chunk->getRealPath());
            abort_unless(is_string($hash), 500, 'The upload chunk could not be integrity checked.');
            $chunks = $upload->chunk_hashes ?? [];
            $disk = Storage::disk('local');

            if (isset($chunks[$index])) {
                abort_unless(hash_equals($chunks[$index]['sha256'], $hash), 409, 'A different chunk already exists at this position.');
                if ($disk->exists($path)) {
                    $storedHash = hash_file('sha256', $disk->path($path));
                    if (is_string($storedHash) && hash_equals($chunks[$index]['sha256'], $storedHash)) {
                        $upload->forceFill(['expires_at' => now()->addHours((int) config('ordo.upload.expiry_hours'))])->save();

                        return ['index' => $index, 'received' => true, 'retry' => true];
                    }
                }
            }

            $stored = $disk->putFileAs(
                'transmittal-upload-chunks/'.$uploadId,
                $chunk,
                $index.'.part'
            );
            abort_unless(is_string($stored) && $stored !== '', 500, 'The upload chunk could not be stored.');
            $chunks[$index] = ['path' => $path, 'sha256' => $hash, 'bytes' => $expectedBytes];
            $upload->forceFill([
                'chunk_hashes' => $chunks,
                'expires_at' => now()->addHours((int) config('ordo.upload.expiry_hours')),
            ])->save();

            return ['index' => $index, 'received' => true, 'retry' => false];
        });
    }

    public function complete(string $ownerHash, string $uploadId, string $token): TransmittalUpload
    {
        return Cache::lock('ordo-upload:'.$uploadId, $this->completionLockSeconds())->block(10, function () use ($ownerHash, $uploadId, $token): TransmittalUpload {
            $upload = $this->ownedUpload($ownerHash, $uploadId, $token);
            if ($upload->status === 'completed') {
                $this->verifyCompletedFile($upload);
                $this->deleteChunkDirectory($uploadId);

                return $upload;
            }
            abort_unless($upload->status === 'uploading', 409, 'This upload cannot be completed.');

            $directory = 'transmittal-uploads/completed';
            $path = $directory.'/'.$uploadId.'.upload';
            try {
                $completed = DB::transaction(function () use ($ownerHash, $uploadId, $token, $path, $directory): TransmittalUpload {
                    $claimed = DB::table('transmittal_upload_sessions')
                        ->where('upload_id', $uploadId)
                        ->where('owner_session_hash', $ownerHash)
                        ->where('upload_token_hash', hash('sha256', $token))
                        ->where('status', 'uploading')
                        ->where('expires_at', '>', now())
                        ->update([
                            'status' => 'assembling',
                            'expires_at' => now()->addHours((int) config('ordo.upload.expiry_hours')),
                            'updated_at' => now(),
                        ]);
                    abort_unless($claimed === 1, 409, 'This upload has changed or is no longer available.');
                    $upload = $this->ownedUpload($ownerHash, $uploadId, $token);
                    $chunks = $upload->chunk_hashes ?? [];
                    abort_unless(count($chunks) === $upload->chunk_count, 409, 'Some upload chunks are missing.');
                    $disk = Storage::disk('local');
                    abort_unless($disk->makeDirectory($directory), 500, 'The private upload directory could not be created.');
                    $output = fopen($disk->path($path), 'wb');
                    abort_if($output === false, 500, 'The completed file could not be created.');
                    $hash = hash_init('sha256');
                    $bytes = 0;

                    try {
                        for ($index = 0; $index < $upload->chunk_count; $index++) {
                            $chunkInfo = $chunks[$index] ?? null;
                            abort_unless(is_array($chunkInfo) && $disk->exists($chunkInfo['path']), 409, 'An upload chunk is missing.');
                            $chunkPath = $disk->path($chunkInfo['path']);
                            $chunkBytes = min($upload->chunk_size, $upload->expected_size - ($index * $upload->chunk_size));
                            abort_unless(filesize($chunkPath) === $chunkBytes, 409, 'An upload chunk failed size verification.');
                            $chunkHash = hash_file('sha256', $chunkPath);
                            abort_unless(is_string($chunkHash) && hash_equals($chunkInfo['sha256'], $chunkHash), 409, 'An upload chunk failed integrity verification.');
                            $input = fopen($chunkPath, 'rb');
                            abort_if($input === false, 500, 'An upload chunk could not be read.');
                            try {
                                while (! feof($input)) {
                                    $buffer = fread($input, 1024 * 1024);
                                    abort_if($buffer === false, 500, 'An upload chunk could not be read.');
                                    if ($buffer === '') {
                                        continue;
                                    }
                                    abort_unless(fwrite($output, $buffer) === strlen($buffer), 500, 'The completed file could not be written.');
                                    hash_update($hash, $buffer);
                                    $bytes += strlen($buffer);
                                }
                            } finally {
                                fclose($input);
                            }
                        }
                    } finally {
                        fclose($output);
                    }
                    abort_unless($bytes === $upload->expected_size, 409, 'The completed file size does not match its declaration.');
                    $sha256 = hash_final($hash);
                    $upload->forceFill([
                        'status' => 'completed',
                        'storage_path' => $path,
                        'sha256' => $sha256,
                        'expires_at' => now()->addHours((int) config('ordo.upload.expiry_hours')),
                    ])->save();

                    return $upload->fresh();
                });
            } catch (\Throwable $exception) {
                $disk = Storage::disk('local');
                if ($disk->exists($path) && ! $disk->delete($path)) {
                    throw new \RuntimeException("Unable to remove failed assembly for {$uploadId}.", previous: $exception);
                }
                throw $exception;
            }

            $this->deleteChunkDirectory($uploadId);

            return $completed;
        });
    }

    public function ownedUpload(string $ownerHash, string $uploadId, string $token): TransmittalUpload
    {
        $upload = TransmittalUpload::query()
            ->where('upload_id', $uploadId)
            ->where('expires_at', '>', now())
            ->first();
        abort_unless(
            $upload !== null
                && hash_equals($upload->owner_session_hash, $ownerHash)
                && hash_equals($upload->upload_token_hash, hash('sha256', $token)),
            404
        );

        return $upload;
    }

    public function verifyCompletedFile(TransmittalUpload $upload): string
    {
        abort_unless($upload->status === 'completed' && $upload->storage_path, 409, 'This upload has not been completed.');
        $disk = Storage::disk('local');
        abort_unless($disk->exists($upload->storage_path), 410, 'The temporary upload has expired or is unavailable.');
        $path = $disk->path($upload->storage_path);
        abort_unless(filesize($path) === $upload->expected_size, 409, 'The uploaded file failed size verification.');
        $sha256 = hash_file('sha256', $path);
        abort_unless(is_string($upload->sha256) && is_string($sha256) && hash_equals($upload->sha256, $sha256), 409, 'The uploaded file failed integrity verification.');

        return $path;
    }

    public function temporaryFile(TransmittalUpload $upload): UploadedFile
    {
        $path = $this->verifyCompletedFile($upload);

        return new UploadedFile($path, $upload->original_name, $upload->mime_type, UPLOAD_ERR_OK, true);
    }

    public function cleanupExpired(): int
    {
        $count = 0;
        TransmittalUpload::query()->where('expires_at', '<=', now())->chunkById(100, function ($uploads) use (&$count): void {
            foreach ($uploads as $upload) {
                $lock = Cache::lock('ordo-upload:'.$upload->upload_id, $this->completionLockSeconds());
                if (! $lock->get()) {
                    continue;
                }
                try {
                    DB::transaction(function () use ($upload, &$count): void {
                        $current = TransmittalUpload::query()->whereKey($upload->id)->lockForUpdate()->first();
                        if (! $current || $current->expires_at->isFuture()) {
                            return;
                        }
                        $disk = Storage::disk('local');
                        $this->deleteChunkDirectory($current->upload_id);
                        $paths = array_filter([
                            $current->storage_path,
                            'transmittal-uploads/completed/'.$current->upload_id.'.upload',
                        ]);
                        foreach (array_unique($paths) as $path) {
                            if ($disk->exists($path) && ! $disk->delete($path)) {
                                throw new \RuntimeException("Unable to remove expired upload {$current->upload_id}.");
                            }
                        }
                        $current->delete();
                        $count++;
                    });
                } finally {
                    $lock->release();
                }
            }
        });

        return $count;
    }

    private function deleteChunkDirectory(string $uploadId): void
    {
        $disk = Storage::disk('local');
        $path = 'transmittal-upload-chunks/'.$uploadId;
        if ($disk->directoryExists($path) && ! $disk->deleteDirectory($path)) {
            throw new \RuntimeException("Unable to remove upload chunks for {$uploadId}.");
        }
    }

    private function completionLockSeconds(): int
    {
        $seconds = (int) config('ordo.upload.completion_lock_seconds');
        abort_if($seconds < 300, 500, 'The resumable upload completion lock must be at least 300 seconds.');

        return $seconds;
    }
}
