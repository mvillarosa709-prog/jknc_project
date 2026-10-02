<?php

namespace App\Observers;

use App\Models\Call;
use App\Models\Event;
use App\Models\Meeting;
use App\Models\MessageConversation;
use App\Models\OfficialBusinessTrip;
use App\Models\Task;
use App\Services\ActivityAuditService;
use App\Services\ActivityTypeRegistry;
use Illuminate\Database\Eloquent\Model;

class ActivityAuditObserver
{
    public function created(Model $model): void
    {
        if ($model instanceof OfficialBusinessTrip) {
            $this->semantic($model, 'created', [
                'status' => $model->status,
                'workflow_version' => $model->workflow_version,
            ]);

            return;
        }

        $this->audit()->record($this->type($model), $model->getKey(), 'created');
    }

    public function updated(Model $model): void
    {
        $changes = collect($model->getChanges())->except('updated_at')->all();

        if ($changes === []) {
            return;
        }

        $before = collect($changes)
            ->keys()
            ->mapWithKeys(fn (string $key): array => [$key => $model->getOriginal($key)])
            ->all();

        if ($model instanceof OfficialBusinessTrip) {
            $this->audit()->recordSemantic(
                $this->type($model),
                $model->getKey(),
                $this->updatedAction($model, $changes),
                $this->audit()->referenceFor($model),
                $this->meaningfulDetails($changes),
                $before,
                $changes
            );

            return;
        }

        $this->audit()->record($this->type($model), $model->getKey(), $this->updatedAction($model, $changes), [
            'before' => $before,
            'after' => $changes,
        ]);
    }

    public function deleted(Model $model): void
    {
        if ($model instanceof OfficialBusinessTrip) {
            $action = method_exists($model, 'isForceDeleting') && $model->isForceDeleting()
                ? 'permanently_deleted'
                : 'deleted';
            $this->semantic($model, $action, ['status' => $model->status]);

            return;
        }

        $action = method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()
            ? 'archived'
            : 'deleted';

        $this->audit()->record($this->type($model), $model->getKey(), $action);
    }

    public function restored(Model $model): void
    {
        if ($model instanceof OfficialBusinessTrip) {
            $this->semantic($model, 'restored', ['status' => $model->status]);
        }
    }

    private function updatedAction(Model $model, array $changes): string
    {
        if ($model instanceof OfficialBusinessTrip) {
            if (array_key_exists('actual_departure', $changes) && $changes['actual_departure']) {
                return 'departed';
            }

            if (array_key_exists('actual_return', $changes) && $changes['actual_return']) {
                return 'returned';
            }

            if (array_key_exists('status', $changes)) {
                return match (strtolower((string) $changes['status'])) {
                    'draft' => 'draft_saved',
                    'submitted' => 'submitted',
                    'approved' => 'approved',
                    'ready for travel' => 'ready_for_travel',
                    'ongoing', 'in progress' => 'departed',
                    'returned' => 'returned',
                    'for completion' => 'for_completion',
                    'reverted' => 'reverted',
                    'rejected' => 'rejected',
                    'completed' => 'completed',
                    'cancelled', 'canceled' => 'cancelled',
                    default => 'status_changed',
                };
            }
        }

        if (array_key_exists('status', $changes) || array_key_exists('completed', $changes)) {
            $value = strtolower((string) ($changes['status'] ?? ($changes['completed'] ? 'completed' : 'open')));

            if (in_array($value, ['completed', 'complete', 'done', 'true', '1'], true)) {
                return 'completed';
            }

            if (in_array($value, ['open', 'upcoming', 'pending', 'false', '0'], true)) {
                return 'reopened';
            }

            return 'status_changed';
        }

        if (array_intersect(['assigned_to_user_id', 'assigned_user_id', 'owner'], array_keys($changes))) {
            return 'assignment_changed';
        }

        if (array_intersect(['due_date', 'date_to'], array_keys($changes))) {
            return 'due_date_changed';
        }

        return 'updated';
    }

    private function meaningfulDetails(array $changes): array
    {
        return collect($changes)
            ->only(['status', 'actual_departure', 'actual_return', 'duration_minutes', 'remarks'])
            ->all();
    }

    private function semantic(OfficialBusinessTrip $trip, string $action, array $details = []): void
    {
        $this->audit()->recordSemantic(
            ActivityTypeRegistry::OFFICIAL_BUSINESS_TRAVEL,
            $trip->getKey(),
            $action,
            $this->audit()->referenceFor($trip),
            $details
        );
    }

    private function type(Model $model): string
    {
        return match (true) {
            $model instanceof Task => ActivityTypeRegistry::TASK,
            $model instanceof Event => ActivityTypeRegistry::EVENT,
            $model instanceof Call => ActivityTypeRegistry::CALL,
            $model instanceof Meeting => ActivityTypeRegistry::MEETING,
            $model instanceof MessageConversation => ActivityTypeRegistry::MESSAGE,
            $model instanceof OfficialBusinessTrip => ActivityTypeRegistry::OFFICIAL_BUSINESS_TRAVEL,
        };
    }

    private function audit(): ActivityAuditService
    {
        return app(ActivityAuditService::class);
    }
}
