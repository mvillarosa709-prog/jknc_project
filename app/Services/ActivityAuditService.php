<?php

namespace App\Services;

use App\Models\ActivityAuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;

class ActivityAuditService
{
    public function recordSemantic(
        string $activityType,
        ?int $activityId,
        string $action,
        ?string $reference = null,
        array $details = [],
        array $before = [],
        array $after = [],
        ?int $actorId = null
    ): ?ActivityAuditLog {
        return $this->record(
            $activityType,
            $activityId,
            $action,
            ($before !== [] || $after !== []) ? ['before' => $before, 'after' => $after] : [],
            [
                'entity_type' => strtoupper($activityType),
                'entity_reference' => $reference,
                'details' => $details ?: null,
                'semantic' => true,
            ],
            $actorId
        );
    }

    public function referenceFor(Model $model): string
    {
        return (string) ($model->reference_no
            ?? $model->ob_reference_no
            ?? class_basename($model).' #'.$model->getKey());
    }

    public function serialize(ActivityAuditLog $log): array
    {
        $metadata = $log->metadata ?? [];

        return [
            'id' => $log->id,
            'timestamp' => $log->created_at?->toIso8601String(),
            'actor' => $log->user ? [
                'id' => $log->user->id,
                'name' => $log->user->name,
                'email' => $log->user->email,
            ] : null,
            'event' => $log->action,
            'action' => $log->action,
            'entity_type' => $metadata['entity_type'] ?? $log->activity_type,
            'entity_id' => $log->activity_id,
            'entity_reference' => $metadata['entity_reference'] ?? null,
            'details' => $metadata['details'] ?? null,
            'changes' => $log->changes,
            'metadata' => $metadata,
        ];
    }

    public function record(
        string $activityType,
        ?int $activityId,
        string $action,
        array $changes = [],
        array $metadata = [],
        ?int $actorId = null
    ): ?ActivityAuditLog {
        if (! Schema::hasTable('activity_audit_logs')) {
            return null;
        }

        return ActivityAuditLog::query()->create([
            'activity_type' => strtoupper($activityType),
            'activity_id' => $activityId,
            'action' => $action,
            'user_id' => $actorId ?? Auth::id(),
            'changes' => $changes ?: null,
            'metadata' => $metadata ?: null,
        ]);
    }
}
