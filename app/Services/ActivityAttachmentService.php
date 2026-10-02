<?php

namespace App\Services;

use App\Models\ActivityAttachment;
use App\Models\Meeting;
use App\Models\OfficialBusinessTrip;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ActivityAttachmentService
{
    public function __construct(private readonly ActivityTypeRegistry $types) {}

    public function forActivity(string $type, Model $record): array
    {
        $type = $this->types->normalize($type);

        $stored = ! Schema::hasTable('activity_attachments')
            ? []
            : ActivityAttachment::query()
            ->with('uploadedBy:id,name,email')
            ->where('activity_type', $type)
            ->where('activity_id', $record->getKey())
            ->orderBy('uploaded_at')
            ->orderBy('id')
            ->get()
            ->map(fn (ActivityAttachment $attachment): array => $this->serialize($attachment))
            ->all();

        return collect($stored)
            ->merge($this->legacyForActivity($type, $record))
            ->unique(fn (array $attachment): string => implode('|', [
                $attachment['storage_path'] ?? '',
                $attachment['safe_url'] ?? '',
                $attachment['category'] ?? '',
            ]))
            ->values()
            ->all();
    }

    public function serialize(ActivityAttachment $attachment): array
    {
        $privateEmail = $attachment->activity_type === ActivityTypeRegistry::EMAIL && $attachment->storage_path;
        $url = $attachment->external_url;

        if ($privateEmail) {
            $url = url('/api/email-activities/'.$attachment->activity_id.'/files/'.$attachment->id);
        } elseif (! $url && $attachment->storage_disk && $attachment->storage_path) {
            $url = Storage::disk($attachment->storage_disk)->url($attachment->storage_path);
        }

        return [
            'id' => $attachment->id,
            'source_activity_type' => $attachment->activity_type,
            'source_activity_id' => $attachment->activity_id,
            'filename' => $attachment->filename,
            'original_filename' => $attachment->original_filename,
            'storage_path' => $privateEmail ? null : $attachment->storage_path,
            'safe_url' => $url,
            'mime_type' => $attachment->mime_type,
            'size_bytes' => $attachment->size_bytes,
            'uploaded_by_user_id' => $attachment->uploaded_by_user_id,
            'uploaded_by' => $attachment->uploadedBy,
            'uploaded_at' => $attachment->uploaded_at?->toIso8601String(),
            'category' => $attachment->category,
            'metadata' => $attachment->metadata,
            'is_legacy' => false,
        ];
    }

    public function createFromStoredFile(
        string $type,
        int $activityId,
        string $path,
        string $originalName,
        ?string $mimeType,
        ?int $size,
        ?string $category,
        ?int $userId,
        array $metadata = []
    ): ActivityAttachment {
        return ActivityAttachment::query()->create([
            'activity_type' => $this->types->normalize($type),
            'activity_id' => $activityId,
            'filename' => basename($path),
            'original_filename' => $this->types->normalize($type) === ActivityTypeRegistry::EMAIL ? self::safeFilename($originalName) : $originalName,
            'storage_disk' => $this->types->normalize($type) === ActivityTypeRegistry::EMAIL ? 'local' : 'public',
            'storage_path' => $path,
            'mime_type' => $mimeType,
            'size_bytes' => $size,
            'category' => $category,
            'uploaded_by_user_id' => $userId,
            'uploaded_at' => now(),
            'metadata' => $metadata ?: null,
        ]);
    }

    public static function safeFilename(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1f\x7f]/u', '', $name) ?? '';
        $name = trim(str_replace(['/', '\\', '%'], '_', $name), '. ');
        return Str::limit($name ?: 'attachment', 180, '');
    }

    public static function safeMime(?string $mime): string
    {
        return preg_match('~^[a-zA-Z0-9!#$&^_.+-]+/[a-zA-Z0-9!#$&^_.+-]+$~D', $mime ?? '')
            ? $mime : 'application/octet-stream';
    }

    public function createExternalReference(
        string $type,
        int $activityId,
        string $url,
        string $name,
        ?string $category,
        ?int $userId,
        array $metadata = []
    ): ActivityAttachment {
        return ActivityAttachment::query()->create([
            'activity_type' => $this->types->normalize($type),
            'activity_id' => $activityId,
            'filename' => $name,
            'original_filename' => $name,
            'external_url' => $url,
            'category' => $category,
            'uploaded_by_user_id' => $userId,
            'uploaded_at' => now(),
            'metadata' => $metadata ?: null,
        ]);
    }

    private function legacyForActivity(string $type, Model $record): array
    {
        return match ($type) {
            ActivityTypeRegistry::CALL => $this->legacyPath(
                $type,
                $record->id,
                $record->audio_path,
                'audio',
                'audio/*'
            ),
            ActivityTypeRegistry::MEETING => $this->meetingLegacyAttachments($record),
            ActivityTypeRegistry::OFFICIAL_BUSINESS_TRAVEL => $this->officialBusinessAttachments($record),
            default => [],
        };
    }

    private function meetingLegacyAttachments(Meeting $meeting): array
    {
        return collect()
            ->merge($this->legacyPath(
                ActivityTypeRegistry::MEETING,
                $meeting->id,
                $meeting->video_path,
                'recording',
                'video/*'
            ))
            ->merge($this->legacyPath(
                ActivityTypeRegistry::MEETING,
                $meeting->id,
                $meeting->audio_path,
                'recording',
                'audio/*'
            ))
            ->merge($meeting->notes
                ->filter(fn ($note): bool => str_starts_with((string) $note->content, 'TRANSCRIPT UPLOADED:')
                    || str_starts_with((string) $note->content, 'MEETING MINUTES UPLOADED:'))
                ->map(function ($note) use ($meeting): array {
                    $lines = preg_split('/\R/', (string) $note->content) ?: [];
                    $category = str_starts_with((string) $note->content, 'TRANSCRIPT')
                        ? 'transcript'
                        : 'minutes';

                    return $this->legacyMetadata(
                        ActivityTypeRegistry::MEETING,
                        $meeting->id,
                        $lines[1] ?? $category,
                        null,
                        $lines[2] ?? null,
                        $category,
                        null,
                        $note->created_at?->toIso8601String()
                    );
                }))
            ->values()
            ->all();
    }

    private function officialBusinessAttachments(OfficialBusinessTrip $trip): array
    {
        $types = collect($trip->attachment_types ?? [])->values();

        return collect($trip->attachment_paths ?? [])
            ->values()
            ->map(function ($attachment, int $index) use ($trip, $types): array {
                $path = is_array($attachment) ? ($attachment['path'] ?? null) : $attachment;
                $originalName = is_array($attachment)
                    ? ($attachment['original_name'] ?? basename((string) $path))
                    : basename((string) $path);
                $category = is_array($attachment)
                    ? ($attachment['type'] ?? $types->get($index))
                    : $types->get($index);

                return $this->legacyMetadata(
                    ActivityTypeRegistry::OFFICIAL_BUSINESS_TRAVEL,
                    $trip->id,
                    $originalName,
                    $path,
                    $path ? Storage::disk('public')->url($path) : null,
                    $category,
                    null,
                    $trip->created_at?->toIso8601String()
                );
            })
            ->all();
    }

    private function legacyPath(
        string $type,
        int $id,
        ?string $path,
        string $category,
        ?string $mimeType
    ): array {
        if (blank($path)) {
            return [];
        }

        $storagePath = preg_replace('#^(?:public/|storage/)#', '', ltrim($path, '/'));
        $url = Str::startsWith($path, ['http://', 'https://'])
            ? $path
            : Storage::disk('public')->url($storagePath);

        return [$this->legacyMetadata(
            $type,
            $id,
            basename($storagePath),
            $storagePath,
            $url,
            $category,
            $mimeType
        )];
    }

    private function legacyMetadata(
        string $type,
        int $id,
        string $name,
        ?string $path,
        ?string $url,
        ?string $category,
        ?string $mimeType,
        ?string $uploadedAt = null
    ): array {
        return [
            'id' => null,
            'source_activity_type' => $type,
            'source_activity_id' => $id,
            'filename' => basename($name),
            'original_filename' => $name,
            'storage_path' => $path,
            'safe_url' => $url,
            'mime_type' => $mimeType,
            'size_bytes' => null,
            'uploaded_by_user_id' => null,
            'uploaded_by' => null,
            'uploaded_at' => $uploadedAt,
            'category' => $category,
            'metadata' => null,
            'is_legacy' => true,
        ];
    }
}
