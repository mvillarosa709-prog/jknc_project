<?php

namespace App\Services;

use App\Models\ActivityLink;

class ActivityLinkService
{
    public function __construct(private readonly ActivityTypeRegistry $types) {}

    public function canonicalPair(string $firstType, int $firstId, string $secondType, int $secondId): array
    {
        $firstType = $this->types->normalize($firstType);
        $secondType = $this->types->normalize($secondType);
        $firstKey = $firstType.':'.str_pad((string) $firstId, 20, '0', STR_PAD_LEFT);
        $secondKey = $secondType.':'.str_pad((string) $secondId, 20, '0', STR_PAD_LEFT);

        if ($firstKey <= $secondKey) {
            return [$firstType, $firstId, $secondType, $secondId];
        }

        return [$secondType, $secondId, $firstType, $firstId];
    }

    public function create(
        string $firstType,
        int $firstId,
        string $secondType,
        int $secondId,
        ?int $userId
    ): array {
        [$sourceType, $sourceId, $relatedType, $relatedId] = $this->canonicalPair(
            $firstType,
            $firstId,
            $secondType,
            $secondId
        );

        $link = ActivityLink::query()->firstOrCreate([
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
        ], [
            'created_by_user_id' => $userId,
        ]);

        return [$link, $link->wasRecentlyCreated];
    }

    public function find(string $firstType, int $firstId, string $secondType, int $secondId): ?ActivityLink
    {
        [$sourceType, $sourceId, $relatedType, $relatedId] = $this->canonicalPair(
            $firstType,
            $firstId,
            $secondType,
            $secondId
        );

        return ActivityLink::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('related_type', $relatedType)
            ->where('related_id', $relatedId)
            ->first();
    }
}
