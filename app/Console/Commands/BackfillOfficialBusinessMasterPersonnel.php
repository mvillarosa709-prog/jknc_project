<?php

namespace App\Console\Commands;

use App\Models\OfficialBusinessTrip;
use App\Models\OfficialBusinessTripPersonnel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillOfficialBusinessMasterPersonnel extends Command
{
    protected $signature = 'ob:v3-backfill-master-personnel
        {--dry-run : Show the planned actions without changing the database}';

    protected $description = 'Backfill structured personnel rows for existing Master O.B. records.';

    /** @var array<string, int> */
    private array $counters = [
        'ACTIVE_MASTER_INSERTS' => 0,
        'SOFT_DELETED_MASTER_INSERTS' => 0,
        'RESTORES' => 0,
        'SOFT_DELETES' => 0,
        'SKIPS' => 0,
        'CONFLICTS' => 0,
        'ERRORS' => 0,
        'TOTAL_PROCESSED' => 0,
    ];

    public function handle(): int
    {
        $this->counters = [
            'ACTIVE_MASTER_INSERTS' => 0,
            'SOFT_DELETED_MASTER_INSERTS' => 0,
            'RESTORES' => 0,
            'SOFT_DELETES' => 0,
            'SKIPS' => 0,
            'CONFLICTS' => 0,
            'ERRORS' => 0,
            'TOTAL_PROCESSED' => 0,
        ];

        if (! Schema::hasTable('official_business_trip_personnel')) {
            $this->error(
                'The structured Master O.B. personnel table does not exist. '
                .'Apply migration 2026_09_28_000002 before running this command.'
            );

            return self::FAILURE;
        }

        if (! Schema::hasTable('official_business_trips') || ! Schema::hasTable('employees')) {
            $this->error('Required Master O.B. source tables are missing. Apply the prerequisite migrations first.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->line('DRY RUN - NO DATABASE CHANGES');
        }

        OfficialBusinessTrip::query()
            ->withTrashed()
            ->with('employee.department')
            ->orderBy('id')
            ->chunkById(100, function ($trips) use ($dryRun): void {
                foreach ($trips as $trip) {
                    $this->processTrip($trip, $dryRun);
                }
            });

        foreach ($this->counters as $name => $count) {
            $this->line($name.'='.$count);
        }

        return ($this->counters['CONFLICTS'] > 0 || $this->counters['ERRORS'] > 0)
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function processTrip(OfficialBusinessTrip $trip, bool $dryRun): void
    {
        $this->counters['TOTAL_PROCESSED']++;

        if (! $trip->employee_id || ! $trip->employee) {
            $this->counters['CONFLICTS']++;
            $this->reportAction($trip, 'CONFLICT', 'missing or invalid employee_id');

            return;
        }

        $targetDeletedAt = $trip->deleted_at;
        $employee = $trip->employee;
        $snapshots = [
            'employee_name_snapshot' => filled($trip->employee_name)
                ? $trip->employee_name
                : $employee->full_name,
            'position_snapshot' => filled($trip->position)
                ? $trip->position
                : $employee->position,
            'department_snapshot' => filled($trip->department)
                ? $trip->department
                : $employee->department?->department_name,
        ];

        try {
            $member = OfficialBusinessTripPersonnel::withTrashed()
                ->where('official_business_trip_id', $trip->id)
                ->where('employee_id', $trip->employee_id)
                ->first();

            if (! $member) {
                $action = $targetDeletedAt
                    ? 'SOFT_DELETED_MASTER_INSERT'
                    : 'ACTIVE_MASTER_INSERT';

                if (! $dryRun) {
                    DB::transaction(function () use ($trip, $snapshots, $targetDeletedAt): void {
                        $member = new OfficialBusinessTripPersonnel();
                        $member->forceFill([
                            'official_business_trip_id' => $trip->id,
                            'employee_id' => $trip->employee_id,
                            ...$snapshots,
                            'deleted_at' => $targetDeletedAt,
                        ]);
                        $member->saveQuietly();
                    });
                }

                $this->counters[$targetDeletedAt ? 'SOFT_DELETED_MASTER_INSERTS' : 'ACTIVE_MASTER_INSERTS']++;
                $this->reportAction($trip, $action);

                return;
            }

            $needsDeletedAtSync = $this->deletedAtDiffers($member->deleted_at, $targetDeletedAt);

            if (! $targetDeletedAt && $member->trashed()) {
                if (! $dryRun) {
                    DB::transaction(fn (): bool => $member->forceFill(['deleted_at' => null])->saveQuietly());
                }

                $this->counters['RESTORES']++;
                $this->reportAction($trip, 'RESTORE');

                return;
            }

            if ($targetDeletedAt && (! $member->trashed() || $needsDeletedAtSync)) {
                if (! $dryRun) {
                    DB::transaction(fn (): bool => $member->forceFill(['deleted_at' => $targetDeletedAt])->saveQuietly());
                }

                $this->counters['SOFT_DELETES']++;
                $this->reportAction($trip, 'SOFT_DELETE');

                return;
            }

            $this->counters['SKIPS']++;
            $this->reportAction($trip, 'SKIP');
        } catch (\Throwable $exception) {
            $this->counters['ERRORS']++;
            $this->reportAction($trip, 'ERROR', $exception->getMessage());
        }
    }

    private function deletedAtDiffers($existing, $target): bool
    {
        if (blank($existing) || blank($target)) {
            return filled($existing) !== filled($target);
        }

        return (string) $existing !== (string) $target;
    }

    private function reportAction(OfficialBusinessTrip $trip, string $action, ?string $detail = null): void
    {
        $reference = $trip->ob_reference_no ?: 'O.B. #'.$trip->id;
        $suffix = $detail ? ' - '.$detail : '';
        $this->line(sprintf(
            '%s | %s | employee_id=%s | master_deleted_at=%s%s',
            $action,
            $reference,
            $trip->employee_id ?: 'NULL',
            $trip->deleted_at ?: 'NULL',
            $suffix
        ));
    }
}
