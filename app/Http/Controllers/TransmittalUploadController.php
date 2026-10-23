<?php

namespace App\Http\Controllers;

use App\Exceptions\OcrUnavailableException;
use App\Models\Transmittal;
use App\Models\TransmittalDocument;
use App\Services\ResumableTransmittalUploadService;
use App\Services\TransmittalOcrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class TransmittalUploadController extends Controller
{
    public function start(Request $request, ResumableTransmittalUploadService $uploads): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1', 'max:'.config('ordo.upload.max_bytes')],
            'type' => ['nullable', 'string', 'max:127'],
        ]);
        $token = Str::random(64);
        $upload = $uploads->start(
            $this->ownerHash($request),
            $token,
            $data['name'],
            (int) $data['size'],
            $data['type'] ?? 'application/octet-stream'
        );
        $tokens = $request->session()->get('transmittal_upload_tokens', []);
        $tokens[$upload->upload_id] = $token;
        $request->session()->put('transmittal_upload_tokens', $tokens);

        return response()->json([
            'upload_id' => $upload->upload_id,
            'chunk_size' => $upload->chunk_size,
            'chunk_count' => $upload->chunk_count,
            'expires_at' => $upload->expires_at->toIso8601String(),
        ], 201);
    }

    public function putChunk(
        Request $request,
        string $uploadId,
        int $index,
        ResumableTransmittalUploadService $uploads
    ): JsonResponse {
        $request->validate([
            'chunk' => ['required', 'file', 'max:'.(int) ceil(config('ordo.upload.chunk_bytes') / 1024)],
        ]);
        $result = $uploads->putChunk(
            $this->ownerHash($request),
            $uploadId,
            $this->uploadToken($request, $uploadId),
            $index,
            $request->file('chunk')
        );

        return response()->json($result);
    }

    public function complete(Request $request, string $uploadId, ResumableTransmittalUploadService $uploads): JsonResponse
    {
        $upload = $uploads->complete($this->ownerHash($request), $uploadId, $this->uploadToken($request, $uploadId));

        return response()->json([
            'upload_id' => $upload->upload_id,
            'name' => $upload->original_name,
            'size' => $upload->expected_size,
            'sha256' => $upload->sha256,
            'status' => $upload->status,
        ]);
    }

    public function analyze(
        Request $request,
        string $uploadId,
        ResumableTransmittalUploadService $uploads,
        TransmittalOcrService $ocr
    ): JsonResponse {
        $upload = $uploads->ownedUpload($this->ownerHash($request), $uploadId, $this->uploadToken($request, $uploadId));
        $file = $uploads->temporaryFile($upload);
        validator(['document' => $file], [
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,tif,tiff', 'max:'.(int) ceil(config('ordo.upload.max_bytes') / 1024)],
        ])->validate();

        try {
            $analysis = $ocr->analyze($file);
            $path = $file->getRealPath();
            abort_unless(is_string($path), 422, 'The uploaded file could not be verified.');
            $sha256 = hash_file('sha256', $path);

            $existingDoc = TransmittalDocument::query()
                ->with('transmittal:id,tracking_number,subject')
                ->where('sha256', $sha256)
                ->orWhere('original_name', $upload->original_name)
                ->first();

            $refMatch = ! empty($analysis['fields']['reference'])
                ? Transmittal::query()->where('external_reference', $analysis['fields']['reference'])->first(['id', 'tracking_number', 'subject'])
                : null;

            $matchedRecord = null;
            if ($existingDoc) {
                $matchedRecord = [
                    'type' => $existingDoc->sha256 === $sha256 ? 'duplicate_file' : 'similar_name',
                    'label' => $existingDoc->sha256 === $sha256 ? 'Duplicate File' : 'Matched Document',
                    'tracking_number' => $existingDoc->transmittal?->tracking_number,
                    'subject' => $existingDoc->transmittal?->subject,
                    'document_name' => $existingDoc->original_name,
                ];
            } elseif ($refMatch) {
                $matchedRecord = [
                    'type' => 'reference_match',
                    'label' => 'Matched Reference',
                    'tracking_number' => $refMatch->tracking_number,
                    'subject' => $refMatch->subject,
                ];
            }

            return response()->json([
                'name' => $upload->original_name,
                'category' => $analysis['category'],
                'fields' => $analysis['fields'],
                'field_metadata' => $analysis['field_metadata'],
                'matched_record' => $matchedRecord,
                'sha256' => $sha256,
                'text_preview' => Str::limit($analysis['text'], 500),
                'analysis_token' => Crypt::encryptString(json_encode([
                    'name' => basename($upload->original_name),
                    'sha256' => $sha256,
                    'category' => $analysis['category'],
                    'text' => $analysis['text'],
                    'fields' => $analysis['fields'],
                    'field_metadata' => $analysis['field_metadata'],
                ], JSON_THROW_ON_ERROR)),
            ]);
        } catch (OcrUnavailableException $exception) {
            return response()->json(['message' => $exception->getMessage()], 503);
        }
    }

    private function ownerHash(Request $request): string
    {
        return hash('sha256', $request->session()->getId());
    }

    private function uploadToken(Request $request, string $uploadId): string
    {
        $token = $request->session()->get("transmittal_upload_tokens.{$uploadId}");
        abort_unless(is_string($token), 404);

        return $token;
    }
}
