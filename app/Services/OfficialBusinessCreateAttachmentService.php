<?php

namespace App\Services;

use App\Models\ActivityAttachment;
use App\Models\OfficialBusinessTrip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OfficialBusinessCreateAttachmentService
{
    private array $storedPaths = [];

    public const TYPES = [
        'Rental Contract', 'Flight Ticket', 'Transmittal Form', 'Memo',
        'Client Contract', 'Client Approval', 'Picture of Meter', 'Others',
    ];

    public function decode(?string $json): array
    {
        $rows = json_decode($json ?: '[]', true);
        if (! is_array($rows) || ! array_is_list($rows)) {
            throw ValidationException::withMessages(['attachment_rows_json' => 'Attachments must be a list.']);
        }

        return $rows;
    }

    public function validate(Request $request, ?OfficialBusinessTrip $trip, array $detailRows, bool $isDraft): array
    {
        $rows = $this->decode($request->input('attachment_rows_json'));
        $request->validate([
            'attachment_files' => ['nullable', 'array', 'max:30'],
            'attachment_files.*' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,csv,txt,jpg,jpeg,png,webp,mp3,wav,m4a,mp4,mov', 'max:20480'],
        ]);

        foreach (array_keys($request->file('attachment_files', [])) as $fileIndex) {
            if (! array_key_exists($fileIndex, $rows)) {
                throw ValidationException::withMessages([
                    'attachment_files.'.$fileIndex => 'Add a Type and Related Client / Trip for this file.',
                ]);
            }
        }

        $validKeys = collect($detailRows)->flatMap(fn ($row) => array_filter([
            filled($row['client_key'] ?? null) ? (string) $row['client_key'] : null,
            filled($row['id'] ?? null) ? 'detail:'.(int) $row['id'] : null,
        ]))->values()->all();
        $errors = [];
        $seenIds = [];
        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                $errors["attachment_rows_json.{$index}"] = 'Attachment entry is invalid.';
                continue;
            }
            $type = $row['type'] ?? null;
            $detailKey = (string) ($row['detail_key'] ?? '');
            $id = $row['id'] ?? null;
            $remove = filter_var($row['remove'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $file = $request->file("attachment_files.{$index}");

            if ($id && (! $trip || ! is_numeric($id) || ! ActivityAttachment::query()
                ->whereKey($id)->where('activity_type', ActivityTypeRegistry::OFFICIAL_BUSINESS_TRAVEL)
                ->where('activity_id', $trip->id)->exists())) {
                $errors["attachment_rows_json.{$index}"] = 'Attachment does not belong to this O.B.';
                continue;
            }
            if ($id && in_array((int) $id, $seenIds, true)) {
                $errors["attachment_rows_json.{$index}"] = 'An attachment may only appear once.';
            }
            if ($id) $seenIds[] = (int) $id;
            if (! $isDraft && ! $remove && ! in_array($type, self::TYPES, true)) {
                $errors["attachment_rows_json.{$index}"] = 'Attachment '.($index + 1).' Type is required or invalid.';
            }
            if (! $isDraft && ! $remove && ! $id && ! $file) {
                $errors["attachment_rows_json.{$index}"] = 'Attachment '.($index + 1).' File is required.';
            }
            if (! $remove && $detailKey !== '' && ! in_array($detailKey, $validKeys, true)) {
                $errors["attachment_rows_json.{$index}"] = 'Attachment '.($index + 1).' Related Client / Trip must match a current O.B. Detail.';
            }
            if (isset($row['description']) && (! is_string($row['description']) || mb_strlen($row['description']) > 1000)) {
                $errors["attachment_rows_json.{$index}"] = 'Attachment '.($index + 1).' Description must not exceed 1000 characters.';
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $rows;
    }

    /** Delete/relink existing evidence before detail removal is synchronized. */
    public function prepareExisting(OfficialBusinessTrip $trip, array $rows, array $detailRows): void
    {
        $keptIds = collect($detailRows)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if (! $id) {
                continue;
            }
            $attachment = ActivityAttachment::query()
                ->whereKey($id)
                ->where('activity_type', ActivityTypeRegistry::OFFICIAL_BUSINESS_TRAVEL)
                ->where('activity_id', $trip->id)
                ->firstOrFail();

            if (filter_var($row['remove'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                app(ActivityAuditService::class)->record(
                    ActivityTypeRegistry::OFFICIAL_BUSINESS_TRAVEL,
                    $trip->id,
                    'attachment_removed',
                    [],
                    ['attachment_id' => $attachment->id]
                );
                $this->delete($attachment);
                continue;
            }

            $targetId = array_key_exists('detail_key', $row)
                ? $this->persistedTargetId((string) $row['detail_key'])
                : $attachment->official_business_trip_detail_id;
            if ($attachment->official_business_trip_detail_id
                && ! in_array((int) $attachment->official_business_trip_detail_id, $keptIds, true)
                && $targetId === null) {
                throw ValidationException::withMessages([
                    'attachment_rows_json' => 'Choose General O.B., relink the attachment, or remove it before removing its linked O.B. Detail.',
                ]);
            }
            if ($targetId !== null && ! in_array($targetId, $keptIds, true)) {
                throw ValidationException::withMessages([
                    'attachment_rows_json' => 'Relink this attachment to a detail that remains in the O.B.',
                ]);
            }

            $attachment->update([
                'official_business_trip_detail_id' => $targetId,
                'category' => in_array($row['type'] ?? null, self::TYPES, true) ? $row['type'] : null,
                'metadata' => ['description' => trim((string) ($row['description'] ?? '')) ?: null],
            ]);
        }
    }

    public function persist(OfficialBusinessTrip $trip, array $rows, array $detailRows, Request $request): void
    {
        $detailsByKey = $trip->details()->get()->keyBy('id');
        foreach ($rows as $index => $row) {
            if (filter_var($row['remove'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            $file = $request->file("attachment_files.{$index}");
            if (! $id && ! $file) {
                continue;
            }

            $detailKey = (string) ($row['detail_key'] ?? '');
            $detailId = $id && ! array_key_exists('detail_key', $row)
                ? (int) ActivityAttachment::query()->whereKey($id)->value('official_business_trip_detail_id') ?: null
                : $this->resolveDetailId($detailKey, $detailRows, $detailsByKey);
            $attributes = [
                'official_business_trip_detail_id' => $detailId,
                'category' => in_array($row['type'] ?? null, self::TYPES, true) ? $row['type'] : null,
                'metadata' => ['description' => trim((string) ($row['description'] ?? '')) ?: null],
            ];

            if ($id) {
                ActivityAttachment::query()->whereKey($id)
                    ->where('activity_type', ActivityTypeRegistry::OFFICIAL_BUSINESS_TRAVEL)
                    ->where('activity_id', $trip->id)->update($attributes);
                continue;
            }

            $path = $file->store('activities/official_business_travel/'.$trip->id, 'public');
            $this->storedPaths[] = $path;
            try {
                $attachment = app(ActivityAttachmentService::class)->createFromStoredFile(
                    ActivityTypeRegistry::OFFICIAL_BUSINESS_TRAVEL,
                    $trip->id,
                    $path,
                    ActivityAttachmentService::safeFilename($file->getClientOriginalName()),
                    ActivityAttachmentService::safeMime($file->getMimeType()),
                    $file->getSize(),
                    $attributes['category'],
                    request()->user()?->id,
                    $attributes['metadata']
                );
                $attachment->update(['official_business_trip_detail_id' => $detailId]);
                app(ActivityAuditService::class)->record(
                    ActivityTypeRegistry::OFFICIAL_BUSINESS_TRAVEL,
                    $trip->id,
                    'attachment_uploaded',
                    [],
                    ['attachment_id' => $attachment->id, 'category' => $attachment->category]
                );
            } catch (\Throwable $exception) {
                Storage::disk('public')->delete($path);
                throw $exception;
            }
        }
    }

    public function cleanupUncommittedUploads(): void
    {
        foreach ($this->storedPaths as $path) {
            Storage::disk('public')->delete($path);
        }
        $this->storedPaths = [];
    }

    private function resolveDetailId(string $key, array $detailRows, $detailsByKey): ?int
    {
        if ($key === '') {
            return null;
        }
        foreach ($detailRows as $index => $row) {
            if ((string) ($row['client_key'] ?? '') === $key) {
                return (int) ($detailsByKey->values()->get($index)?->id ?? 0) ?: null;
            }
            if ($key === 'detail:'.($row['id'] ?? '')) {
                return (int) ($row['id'] ?? 0) ?: null;
            }
        }

        return null;
    }

    private function persistedTargetId(string $key): ?int
    {
        return str_starts_with($key, 'detail:') && ctype_digit(substr($key, 7))
            ? (int) substr($key, 7)
            : null;
    }

    private function delete(ActivityAttachment $attachment): void
    {
        $disk = $attachment->storage_disk;
        $path = $attachment->storage_path;
        $attachment->delete();

        if ($disk && $path && DB::transactionLevel() > 0) {
            DB::afterCommit(fn () => Storage::disk($disk)->delete($path));
        } elseif ($disk && $path) {
            Storage::disk($disk)->delete($path);
        }
    }
}
