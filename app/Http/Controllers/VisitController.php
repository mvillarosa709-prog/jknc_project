<?php

namespace App\Http\Controllers;

use App\Models\Call;
use App\Models\Event;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\Visit;
use App\Models\Visitor;
use App\Models\VisitMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VisitController extends Controller
{
    private const ACTIVITY_TYPES = [
        'Task' => Task::class,
        'Event' => Event::class,
        'Call' => Call::class,
        'Meeting' => Meeting::class,
    ];

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in([
                Visit::STATUS_CHECKED_IN,
                Visit::STATUS_COMPLETED,
                'Pending Link',
            ])],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $status = $validated['status'] ?? null;

        $query = Visit::query()
            ->with($this->visitRelations())
            ->when($search !== '', function ($query) use ($search): void {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $query->where(function ($visitQuery) use ($term): void {
                    $visitQuery
                        ->where('visit_reference', 'like', $term)
                        ->orWhereHas('visitor', function ($visitorQuery) use ($term): void {
                            $visitorQuery
                                ->where('visitor_reference', 'like', $term)
                                ->orWhere('name', 'like', $term)
                                ->orWhere('mobile', 'like', $term)
                                ->orWhere('email', 'like', $term)
                                ->orWhere('company_name', 'like', $term);
                        });
                });
            })
            ->when($status === 'Pending Link', fn ($query) => $query->whereNull('activity_id'))
            ->when(in_array($status, [Visit::STATUS_CHECKED_IN, Visit::STATUS_COMPLETED], true), fn ($query) => $query->where('status', $status))
            ->latest('checked_in_at');

        $visits = $query->get()
            ->map(fn (Visit $visit): array => $this->serializeVisit($visit))
            ->values();

        return response()->json([
            'visits' => $visits,
            'current_visits' => $visits->where('status', Visit::STATUS_CHECKED_IN)->values(),
        ]);
    }

    public function show(int|string $visit): JsonResponse
    {
        $visitRecord = Visit::query()->findOrFail($visit);

        return response()->json($this->serializeVisit($visitRecord->load($this->visitRelations())));
    }

    public function checkIn(Request $request): JsonResponse
    {
        $validated = $this->validateVisitRequest($request, true);
        $activity = $this->resolveActivity($validated);

        $visit = DB::transaction(function () use ($validated, $activity): Visit {
            $visitor = Visitor::query()->lockForUpdate()->findOrFail($validated['visitor_id']);

            if ($visitor->visits()->where('status', Visit::STATUS_CHECKED_IN)->exists()) {
                throw ValidationException::withMessages([
                    'visitor_id' => 'This visitor already has an active Checked In visit.',
                ]);
            }

            $occurredAt = now();
            $visit = $visitor->visits()->create([
                'status' => Visit::STATUS_CHECKED_IN,
                'checked_in_at' => $occurredAt,
                'checked_out_at' => null,
            ]);

            if ($activity) {
                $visit->activity()->associate($activity);
                $visit->save();
            }

            $visit->movements()->create($this->movementAttributes(
                $validated,
                VisitMovement::TYPE_CHECK_IN,
                $occurredAt
            ));

            return $visit;
        });

        return response()->json($this->serializeVisit($visit->load($this->visitRelations())), 201);
    }

    public function checkOut(Request $request, int|string $visit): JsonResponse
    {
        $validated = $this->validateVisitRequest($request, false);

        $visit = DB::transaction(function () use ($visit, $validated): Visit {
            $lockedVisit = Visit::query()->lockForUpdate()->findOrFail($visit);

            if ($lockedVisit->status !== Visit::STATUS_CHECKED_IN || $lockedVisit->checked_out_at !== null) {
                throw ValidationException::withMessages([
                    'visit' => 'Only an active Checked In visit can be checked out.',
                ]);
            }

            $occurredAt = now();
            $lockedVisit->movements()->create($this->movementAttributes(
                $validated,
                VisitMovement::TYPE_CHECK_OUT,
                $occurredAt
            ));
            $lockedVisit->update([
                'status' => Visit::STATUS_COMPLETED,
                'checked_out_at' => $occurredAt,
            ]);

            return $lockedVisit;
        });

        return response()->json($this->serializeVisit($visit->load($this->visitRelations())));
    }

    private function validateVisitRequest(Request $request, bool $checkIn): array
    {
        $rules = [
            'status' => ['prohibited'],
            'movement_type' => ['prohibited'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy_metres' => ['nullable', 'numeric', 'min:0'],
            'captured_at' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'max:100'],
            'location_label' => ['nullable', 'string', 'max:255'],
        ];

        if ($checkIn) {
            $rules += [
                'visitor_id' => ['required', 'integer', 'exists:visitors,id'],
                'activity_type' => ['nullable', 'required_with:activity_id', Rule::in(array_keys(self::ACTIVITY_TYPES))],
                'activity_id' => ['nullable', 'required_with:activity_type', 'integer'],
            ];
        }

        return $request->validate($rules);
    }

    private function resolveActivity(array $validated): ?Model
    {
        if (! isset($validated['activity_type'], $validated['activity_id'])) {
            return null;
        }

        $modelClass = self::ACTIVITY_TYPES[$validated['activity_type']];
        $activity = $modelClass::query()->find($validated['activity_id']);

        if (! $activity) {
            throw ValidationException::withMessages([
                'activity_id' => 'The selected activity does not exist.',
            ]);
        }

        return $activity;
    }

    private function movementAttributes(array $validated, string $movementType, $occurredAt): array
    {
        return [
            'movement_type' => $movementType,
            'occurred_at' => $occurredAt,
            'branch_id' => $validated['branch_id'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'accuracy_metres' => $validated['accuracy_metres'] ?? null,
            'captured_at' => $validated['captured_at'] ?? null,
            'source' => $validated['source'] ?? null,
            'location_label' => $validated['location_label'] ?? null,
        ];
    }

    private function visitRelations(): array
    {
        return ['visitor.contact', 'movements.branch', 'activity'];
    }

    private function serializeVisit(Visit $visit): array
    {
        $visitor = $visit->visitor;
        $activity = $visit->activity;
        $movements = $visit->movements->keyBy('movement_type');

        return [
            'id' => $visit->id,
            'visit_reference' => $visit->visit_reference,
            'visitor' => app(VisitorController::class)->serializeVisitor($visitor),
            'company' => $visitor->company_name,
            'checked_in_at' => $visit->checked_in_at?->toISOString(),
            'checked_out_at' => $visit->checked_out_at?->toISOString(),
            'status' => $visit->status,
            'display_status' => $activity ? $visit->status : 'Pending Link',
            'activity' => $activity ? [
                'type' => class_basename($activity),
                'id' => $activity->getKey(),
                'label' => $this->activityLabel($activity),
            ] : null,
            'check_in_movement' => $this->serializeMovement($movements->get(VisitMovement::TYPE_CHECK_IN)),
            'check_out_movement' => $this->serializeMovement($movements->get(VisitMovement::TYPE_CHECK_OUT)),
        ];
    }

    private function serializeMovement(?VisitMovement $movement): ?array
    {
        if (! $movement) {
            return null;
        }

        return [
            'id' => $movement->id,
            'movement_type' => $movement->movement_type,
            'occurred_at' => $movement->occurred_at?->toISOString(),
            'branch_id' => $movement->branch_id,
            'branch' => $movement->branch ? [
                'id' => $movement->branch->id,
                'name' => $movement->branch->branch_name,
            ] : null,
            'latitude' => $movement->latitude,
            'longitude' => $movement->longitude,
            'accuracy_metres' => $movement->accuracy_metres,
            'captured_at' => $movement->captured_at?->toISOString(),
            'source' => $movement->source,
            'location_label' => $movement->location_label,
        ];
    }

    private function activityLabel(Model $activity): string
    {
        return (string) match (true) {
            $activity instanceof Task => $activity->name,
            $activity instanceof Event => $activity->title,
            $activity instanceof Call => $activity->contact ?: $activity->purpose,
            $activity instanceof Meeting => $activity->title,
            default => '',
        };
    }
}
