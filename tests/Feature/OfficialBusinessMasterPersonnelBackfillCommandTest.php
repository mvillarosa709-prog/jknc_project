<?php

use App\Models\Employee;
use App\Models\OfficialBusinessTrip;
use App\Models\OfficialBusinessTripPersonnel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
        throw new RuntimeException('Master personnel backfill tests require SQLite :memory:.');
    }

    foreach (['official_business_trip_personnel', 'official_business_trips', 'employees', 'departments'] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('departments', function ($table): void {
        $table->id();
        $table->string('department_name');
        $table->timestamps();
    });

    Schema::create('employees', function ($table): void {
        $table->id();
        $table->string('employee_code')->nullable();
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('email')->nullable();
        $table->unsignedBigInteger('department_id')->nullable();
        $table->string('position')->nullable();
        $table->uuid('digital_id_token')->nullable();
        $table->string('verification_reference')->nullable();
        $table->string('employment_status')->nullable();
        $table->date('digital_id_issued_at')->nullable();
        $table->string('payroll_type')->nullable();
        $table->timestamps();
    });

    Schema::create('official_business_trips', function ($table): void {
        $table->id();
        $table->unsignedBigInteger('employee_id')->nullable();
        $table->string('ob_reference_no')->nullable();
        $table->string('employee_name')->nullable();
        $table->string('position')->nullable();
        $table->string('department')->nullable();
        $table->string('destination')->nullable();
        $table->text('purpose')->nullable();
        $table->date('date_from')->nullable();
        $table->json('team_members')->nullable();
        $table->boolean('is_client_travel')->default(false);
        $table->unsignedInteger('workflow_version')->default(3);
        $table->string('status')->default('Approved');
        $table->string('movement_status')->nullable();
        $table->dateTime('actual_departure')->nullable();
        $table->dateTime('actual_return')->nullable();
        $table->unsignedInteger('duration_minutes')->nullable();
        $table->dateTime('deleted_at')->nullable();
        $table->timestamps();
    });
});

function createMasterPersonnelTables(): void
{
    (require database_path('migrations/2026_09_28_000002_create_official_business_master_personnel.php'))->up();
}

function createBackfillEmployee(array $overrides = []): Employee
{
    return Employee::query()->create(array_merge([
        'employee_code' => 'EMP-BACKFILL-'.str()->random(6),
        'first_name' => 'Current',
        'last_name' => 'Employee',
        'email' => str()->uuid().'@example.test',
        'position' => 'Current Position',
    ], $overrides));
}

function createBackfillMaster(Employee $employee, array $overrides = []): OfficialBusinessTrip
{
    $master = OfficialBusinessTrip::query()->create(array_merge([
        'employee_id' => $employee->id,
        'ob_reference_no' => 'OBF-BACKFILL-'.str()->random(6),
        'employee_name' => 'Historical Employee',
        'position' => 'Historical Position',
        'department' => 'Historical Department',
        'destination' => 'Test Destination',
        'purpose' => 'Test Purpose',
        'date_from' => '2026-09-29',
        'is_client_travel' => true,
        'workflow_version' => 3,
        'status' => 'Approved',
    ], $overrides));

    if (array_key_exists('deleted_at', $overrides)) {
        $master->forceFill(['deleted_at' => $overrides['deleted_at']])->saveQuietly();
    }

    return $master->fresh();
}

test('dry run makes no database changes', function (): void {
    createBackfillMaster(createBackfillEmployee());
    createBackfillMaster(createBackfillEmployee(), ['deleted_at' => '2026-09-28 10:00:00']);
    createBackfillMaster(createBackfillEmployee(), ['employee_id' => 999999]);
    createMasterPersonnelTables();

    $this->artisan('ob:v3-backfill-master-personnel', ['--dry-run' => true])
        ->expectsOutput('DRY RUN - NO DATABASE CHANGES')
        ->expectsOutput('ACTIVE_MASTER_INSERTS=1')
        ->expectsOutput('SOFT_DELETED_MASTER_INSERTS=1')
        ->expectsOutput('CONFLICTS=1')
        ->expectsOutput('TOTAL_PROCESSED=3')
        ->assertExitCode(Command::FAILURE);

    expect(Schema::hasTable('official_business_trip_personnel'))->toBeTrue()
        ->and(OfficialBusinessTripPersonnel::withTrashed()->count())->toBe(0);
});

test('active and soft deleted masters create owner rows with historical snapshot priority', function (): void {
    $department = DB::table('departments')->insertGetId([
        'department_name' => 'Current Department',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $employee = createBackfillEmployee(['department_id' => $department]);
    $active = createBackfillMaster($employee);
    $deleted = createBackfillMaster($employee, [
        'employee_name' => null,
        'position' => null,
        'department' => null,
        'deleted_at' => '2026-09-28 10:00:00',
    ]);
    createMasterPersonnelTables();

    $this->artisan('ob:v3-backfill-master-personnel')
        ->expectsOutput('ACTIVE_MASTER_INSERTS=1')
        ->expectsOutput('SOFT_DELETED_MASTER_INSERTS=1')
        ->expectsOutput('CONFLICTS=0')
        ->expectsOutput('TOTAL_PROCESSED=2')
        ->assertExitCode(Command::SUCCESS);

    $activeMember = OfficialBusinessTripPersonnel::query()->where('official_business_trip_id', $active->id)->sole();
    $deletedMember = OfficialBusinessTripPersonnel::withTrashed()->where('official_business_trip_id', $deleted->id)->sole();

    expect($activeMember->employee_name_snapshot)->toBe('Historical Employee')
        ->and($activeMember->position_snapshot)->toBe('Historical Position')
        ->and($activeMember->department_snapshot)->toBe('Historical Department')
        ->and($activeMember->deleted_at)->toBeNull()
        ->and($deletedMember->employee_name_snapshot)->toBe('Current Employee')
        ->and($deletedMember->position_snapshot)->toBe('Current Position')
        ->and($deletedMember->department_snapshot)->toBe('Current Department')
        ->and($deletedMember->deleted_at->format('Y-m-d H:i:s'))->toBe('2026-09-28 10:00:00');
});

test('missing master snapshots fall back to current employee and department data', function (): void {
    $department = DB::table('departments')->insertGetId([
        'department_name' => 'Fallback Department',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $employee = createBackfillEmployee([
        'first_name' => 'Fallback',
        'last_name' => 'Person',
        'position' => 'Fallback Position',
        'department_id' => $department,
    ]);
    $master = createBackfillMaster($employee, [
        'employee_name' => null,
        'position' => null,
        'department' => null,
    ]);
    createMasterPersonnelTables();

    $this->artisan('ob:v3-backfill-master-personnel')->assertExitCode(Command::SUCCESS);

    $member = OfficialBusinessTripPersonnel::query()->where('official_business_trip_id', $master->id)->sole();
    expect($member->employee_name_snapshot)->toBe('Fallback Person')
        ->and($member->position_snapshot)->toBe('Fallback Position')
        ->and($member->department_snapshot)->toBe('Fallback Department');
});

test('rerun is idempotent and preserves existing snapshots while restoring or deleting as required', function (): void {
    $employee = createBackfillEmployee();
    $active = createBackfillMaster($employee);
    $deleted = createBackfillMaster($employee, ['deleted_at' => '2026-09-28 10:00:00']);
    $preexisting = createBackfillMaster($employee, ['ob_reference_no' => 'OBF-BACKFILL-PREEXISTING']);
    createBackfillTablesForExistingRows($active, $deleted, $preexisting);

    $existing = OfficialBusinessTripPersonnel::withTrashed()
        ->where('official_business_trip_id', $active->id)
        ->firstOrFail();
    $existing->forceFill([
        'employee_name_snapshot' => 'Do Not Rewrite',
        'position_snapshot' => 'Old Position',
        'department_snapshot' => 'Old Department',
        'deleted_at' => now(),
    ])->saveQuietly();

    $deletedExisting = OfficialBusinessTripPersonnel::withTrashed()
        ->where('official_business_trip_id', $deleted->id)
        ->firstOrFail();
    $deletedExisting->forceFill(['deleted_at' => null])->saveQuietly();

    $this->artisan('ob:v3-backfill-master-personnel')
        ->expectsOutput('RESTORES=1')
        ->expectsOutput('SOFT_DELETES=1')
        ->assertExitCode(Command::SUCCESS);

    $this->artisan('ob:v3-backfill-master-personnel')
        ->expectsOutput('SKIPS=3')
        ->assertExitCode(Command::SUCCESS);

    expect(OfficialBusinessTripPersonnel::withTrashed()->count())->toBe(3)
        ->and(OfficialBusinessTripPersonnel::query()->where('official_business_trip_id', $active->id)->sole()->employee_name_snapshot)->toBe('Do Not Rewrite')
        ->and(OfficialBusinessTripPersonnel::query()->where('official_business_trip_id', $active->id)->exists())->toBeTrue()
        ->and(OfficialBusinessTripPersonnel::query()->where('official_business_trip_id', $deleted->id)->exists())->toBeFalse()
        ->and(OfficialBusinessTripPersonnel::withTrashed()->where('official_business_trip_id', $deleted->id)->sole()->deleted_at)->not->toBeNull();
});

function createBackfillTablesForExistingRows(
    OfficialBusinessTrip $active,
    OfficialBusinessTrip $deleted,
    OfficialBusinessTrip $preexisting
): void
{
    createMasterPersonnelTables();
    createBackfillPersonnelRow($active, false);
    createBackfillPersonnelRow($deleted, true);
    createBackfillPersonnelRow($preexisting, false);
}

function createBackfillPersonnelRow(OfficialBusinessTrip $master, bool $deleted): void
{
    $member = new OfficialBusinessTripPersonnel();
    $member->forceFill([
        'official_business_trip_id' => $master->id,
        'employee_id' => $master->employee_id,
        'employee_name_snapshot' => 'Existing Snapshot',
        'position_snapshot' => 'Existing Position',
        'department_snapshot' => 'Existing Department',
        'deleted_at' => $deleted ? $master->deleted_at : null,
    ])->saveQuietly();
}

test('missing personnel table fails safely', function (): void {
    createBackfillMaster(createBackfillEmployee());

    $this->artisan('ob:v3-backfill-master-personnel')
        ->expectsOutput('The structured Master O.B. personnel table does not exist. Apply migration 2026_09_28_000002 before running this command.')
        ->assertExitCode(Command::FAILURE);
});

test('invalid employee is reported as a conflict without an invalid insert', function (): void {
    createBackfillMaster(createBackfillEmployee(), ['employee_id' => 999999]);
    createMasterPersonnelTables();

    $this->artisan('ob:v3-backfill-master-personnel')
        ->expectsOutputToContain('CONFLICT')
        ->expectsOutput('CONFLICTS=1')
        ->assertExitCode(Command::FAILURE);

    expect(OfficialBusinessTripPersonnel::withTrashed()->count())->toBe(0);
});

test('backfill leaves O.B. workflow fields and unrelated Trip Ticket personnel unchanged', function (): void {
    $employee = createBackfillEmployee();
    $master = createBackfillMaster($employee, [
        'status' => 'Draft',
        'movement_status' => 'Returned',
        'actual_departure' => '2026-09-29 08:00:00',
        'actual_return' => '2026-09-29 09:30:00',
        'duration_minutes' => 90,
    ]);
    createMasterPersonnelTables();

    Schema::create('official_business_trip_ticket_personnel', function ($table): void {
        $table->id();
        $table->unsignedBigInteger('official_business_trip_ticket_id');
        $table->unsignedBigInteger('employee_id');
        $table->string('employee_name_snapshot')->nullable();
        $table->timestamps();
    });
    DB::table('official_business_trip_ticket_personnel')->insert([
        'official_business_trip_ticket_id' => 777,
        'employee_id' => $employee->id,
        'employee_name_snapshot' => 'Ticket Snapshot',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $before = $master->fresh()->only([
        'status', 'movement_status', 'actual_departure', 'actual_return', 'duration_minutes',
    ]);

    $this->artisan('ob:v3-backfill-master-personnel')->assertExitCode(Command::SUCCESS);

    expect($master->fresh()->only(array_keys($before)))->toEqual($before)
        ->and(DB::table('official_business_trip_ticket_personnel')->sole()->employee_name_snapshot)
        ->toBe('Ticket Snapshot')
        ->and(OfficialBusinessTripPersonnel::withTrashed()->count())->toBe(1);
});
