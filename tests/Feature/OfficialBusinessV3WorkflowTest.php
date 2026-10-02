<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\OfficialBusinessTrip;
use App\Models\User;
use App\Services\OfficialBusinessAuthorizationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    /*
     * These tests intentionally exercise migration up/down behavior.
     * Never allow them to run against a persisted database.
     */
    if (
        DB::connection()->getDriverName() !== 'sqlite'
        || DB::connection()->getDatabaseName() !== ':memory:'
    ) {
        throw new RuntimeException(
            'O.B. V3 workflow tests require SQLite :memory:.'
        );
    }

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password')->nullable();
        $table->string('role');
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });


    Schema::create('notifications', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('type');
        $table->morphs('notifiable');
        $table->text('data');
        $table->timestamp('read_at')->nullable();
        $table->timestamps();
    });
Schema::create('departments', function (Blueprint $table) {
        $table->id();
        $table->string('department_name');
        $table->timestamps();
    });

    Schema::create('employees', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->nullable();
        $table->foreignId('department_id')->nullable();
        $table->string('employee_code')->nullable();
        $table->string('first_name')->nullable();
        $table->string('middle_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('position')->nullable();
        $table->string('immediate_supervisor')->nullable();
        $table->string('reporting_to')->nullable();
        $table->string('work_email')->nullable();
        $table->string('email')->nullable();
        $table->timestamps();
    });

    /*
     * Minimal contacts fixture required by the real Human Capital O.B.
     * index endpoint exercised by the OBV3-005 visibility regression.
     * This is test infrastructure only; production migrations are untouched.
     */
    Schema::create('contacts', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->nullable();
        $table->string('first_name')->nullable();
        $table->string('middle_initial')->nullable();
        $table->string('last_name')->nullable();
        $table->string('name_extension')->nullable();
        $table->string('company_name')->nullable();
        $table->string('email')->nullable();
        $table->string('phone')->nullable();
        $table->string('cif_no')->nullable();
        $table->string('ownership_flag')->nullable();
        $table->string('foreign_business_nature')->nullable();
        $table->string('organization_type')->nullable();
        $table->string('tin')->nullable();
        $table->timestamps();
    });
    Schema::create('companies', function (Blueprint $table) {
        $table->id();
        $table->string('company_name');
        $table->timestamps();
    });

    Schema::create('user_permissions', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id');
        $table->boolean('access_hc_obf')->default(false);
        $table->boolean('create_hc_obf')->default(false);
        $table->boolean('approve_hc_obf')->default(false);
        $table->boolean('control_hc_obf_travel')->default(false);
        $table->boolean('manage_hc_obf')->default(false);
        $table->boolean('access_hc_obf_finance')->default(false);
        $table->boolean('admin_hc_obf')->default(false);
    });

    Schema::create('role_permissions', function (Blueprint $table) {
        $table->id();
        $table->string('role');
        $table->boolean('access_hc_obf')->default(false);
        $table->boolean('create_hc_obf')->default(false);
        $table->boolean('approve_hc_obf')->default(false);
        $table->boolean('control_hc_obf_travel')->default(false);
        $table->boolean('manage_hc_obf')->default(false);
        $table->boolean('access_hc_obf_finance')->default(false);
        $table->boolean('admin_hc_obf')->default(false);
    });

    Schema::create('activity_attachments', function (Blueprint $table): void {
        $table->id();
        $table->string('activity_type', 40);
        $table->unsignedBigInteger('activity_id');
        $table->string('original_name');
        $table->string('stored_name')->nullable();
        $table->text('path')->nullable();
        $table->text('external_url')->nullable();
        $table->string('mime_type')->nullable();
        $table->unsignedBigInteger('size_bytes')->nullable();
        $table->string('category', 60)->nullable();
        $table->foreignId('uploaded_by_user_id')->nullable();
        $table->timestamp('uploaded_at')->nullable();
        $table->json('metadata')->nullable();
        $table->timestamps();

        $table->index(['activity_type', 'activity_id']);
    });

    Schema::create('activity_audit_logs', function (Blueprint $table): void {
        $table->id();
        $table->string('activity_type', 40);
        $table->unsignedBigInteger('activity_id')->nullable();
        $table->string('action', 80);
        $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
        $table->json('changes')->nullable();
        $table->json('metadata')->nullable();
        $table->timestamps();
    });

    /*
     * Use the actual O.B. schema migrations so Draft nullability,
     * workflow_version, timestamps and soft deletes match production.
     */
    (require database_path(
        'migrations/2026_05_11_160306_create_official_business_trips_table.php'
    ))->up();

    /*
     * Production received these O.B. master columns from the broader
     * 2026-08-24 Activities V2 migration. Loading that entire migration
     * here would require unrelated Tasks, Messages and Meetings schemas,
     * so reproduce only its established O.B. table additions.
     */
    Schema::table('official_business_trips', function (Blueprint $table) {
        $table->unsignedBigInteger('company_id')->nullable();
        $table->unsignedBigInteger('contact_id')->nullable();
        $table->unsignedBigInteger('deal_id')->nullable();
        $table->unsignedBigInteger('project_id')->nullable();
        $table->unsignedInteger('duration_minutes')->nullable();

        // Existing Activities V2 core fields used by the O.B. controller.
        $table->unsignedBigInteger('prepared_by_user_id')->nullable();
        $table->text('client_report_description')->nullable();
        $table->boolean('include_in_client_report')->default(false);
    });

    (require database_path(
        'migrations/2026_08_26_112720_add_actual_travel_timestamps_to_official_business_trips_table.php'
    ))->up();

    (require database_path(
        'migrations/2026_08_26_130000_add_soft_deletes_to_official_business_trips_table.php'
    ))->up();

    (require database_path(
        'migrations/2026_09_16_160000_add_official_business_v3_domain_foundation.php'
    ))->up();

    (require database_path(
        'migrations/2026_09_16_162000_allow_incomplete_official_business_v3_drafts.php'
    ))->up();

    /*
     * Client-removal / Internal-conversion regression coverage exercises
     * the production Trip Ticket archive-safety relations. Load the same
     * real ticket-detail, completion, and archival migrations used by the
     * dedicated V3 Trip Ticket test suite.
     */
    (require database_path(
        'migrations/2026_09_17_110100_add_official_business_v3_ticket_details.php'
    ))->up();

    (require database_path(
        'migrations/2026_09_17_120000_add_official_business_v3_ticket_completion_data.php'
    ))->up();

    (require database_path(
        'migrations/2026_09_21_000001_add_archival_metadata_to_official_business_trip_tickets.php'
    ))->up();

    (require database_path(
        'migrations/2026_09_28_235900_add_movement_status_to_official_business_trips.php'
    ))->up();

    $this->manager = User::create([
        'name' => 'O.B. Test Manager',
        'email' => 'ob-manager@example.test',
        'role' => 'Admin',
        'is_active' => true,
    ]);

    DB::table('user_permissions')->insert([
        'user_id' => $this->manager->id,
        'access_hc_obf' => true,
    ]);
});

test('v3 workflow test environment uses isolated in-memory database', function () {
    expect(DB::connection()->getDriverName())->toBe('sqlite')
        ->and(DB::connection()->getDatabaseName())->toBe(':memory:')
        ->and(Schema::hasColumn(
            'official_business_trips',
            'workflow_version'
        ))->toBeTrue()
        ->and(Schema::hasColumn(
            'official_business_trips',
            'actual_departure'
        ))->toBeTrue()
        ->and(
            Schema::getColumnListing('official_business_trips')
        )->toContain('deleted_at');
});

test('draft correction persists on the same master without changing movement', function ($actorRole, $initialStatus, $intent, $movement) {
    $owner = User::create(['name' => 'Draft Owner', 'email' => 'draft-owner@example.test', 'role' => 'Employee', 'is_active' => true]);
    $employee = Employee::find(DB::table('employees')->insertGetId(['user_id' => $owner->id, 'first_name' => 'Draft', 'last_name' => 'Owner']));
    $actor = $actorRole === 'Admin' ? $this->manager : $owner;
    $trip = OfficialBusinessTrip::create([
        'ob_reference_no' => 'OB-DRAFT-EDIT', 'workflow_version' => 3,
        'employee_id' => $employee->id, 'created_by' => $owner->id,
        'status' => $initialStatus, 'movement_status' => $movement,
        'actual_departure' => '2026-09-20 08:00:00', 'actual_return' => '2026-09-20 09:00:00',
        'duration_minutes' => 60,
    ]);
    $before = $trip->fresh()->only(['movement_status', 'actual_departure', 'actual_return', 'duration_minutes']);
    $this->actingAs($actor);
    expect(app(OfficialBusinessAuthorizationService::class)->canEdit($actor, $trip))->toBeTrue();
    $page = $this->get(route('human-capital.obf', ['edit' => $trip->id]))->assertOk();
    $editableRecord = collect($page->viewData('trips'))->firstWhere('id', $trip->id);
    expect($editableRecord['id'])->toBe($trip->id)->and($editableRecord['can_edit'])->toBeTrue();
    $this->put(route('human-capital.obf.update', $trip), [
        'intent' => $intent, 'employee_id' => $employee->id, 'destination' => 'Updated destination',
        'purpose' => 'Updated purpose', 'date_from' => '2026-09-20', 'date_to' => '2026-09-20',
        'is_client_travel' => 0,
        // Movement inputs must not be accepted by the edit endpoint.
        'movement_status' => 'Not Started', 'actual_departure' => null, 'actual_return' => null, 'duration_minutes' => 0,
    ])->assertRedirect(route('human-capital.obf'))->assertSessionHasNoErrors();
    $trip->refresh();
    expect($trip->status)->toBe($intent === 'save_draft' ? 'Draft' : 'Submitted')
        ->and($trip->destination)->toBe('Updated destination')
        ->and($trip->purpose)->toBe('Updated purpose')
        ->and($trip->only(array_keys($before)))->toEqual($before)
        ->and(OfficialBusinessTrip::count())->toBe(1);
    $formatter = new ReflectionMethod(\App\Http\Controllers\OfficialBusinessTripController::class, 'formatTrip');
    $record = $formatter->invoke(app(\App\Http\Controllers\OfficialBusinessTripController::class), $trip);
    expect($record['id'])->toBe($trip->id)
        ->and($record['status'])->toBe($trip->status)
        ->and($record['movement_status'])->toBe($movement)
        ->and($record['can_edit'])->toBe($intent === 'save_draft');
    $activity = app(\App\Services\OfficialBusinessActivityService::class)->toUnifiedActivity($trip);
    expect($activity['source_id'])->toBe($trip->id)
        ->and($activity['status'])->toBe($trip->status)
        ->and($activity['source_status'])->toBe($trip->status)
        ->and($activity['movement_status'])->toBe($movement);
})->with([
    ['Employee', 'Draft', 'save_draft', 'Departed'],
    ['Employee', 'Draft', 'submit', 'Departed'],
    ['Admin', 'Draft', 'submit', 'Departed'],
    ['Employee', 'Draft', 'submit', 'Returned'],
    ['Employee', 'Reverted', 'submit', 'Returned'],
]);

test('draft edit authorization supports creator string ids and authorized managers only', function () {
    $user = User::create(['name' => 'Creator', 'email' => 'creator@example.test', 'role' => 'Employee', 'is_active' => true]);
    $trip = OfficialBusinessTrip::create(['ob_reference_no' => 'OB-CREATOR', 'workflow_version' => 3, 'status' => 'Draft', 'created_by' => $user->id]);
    $trip->created_by = (string) $user->id;
    $authorization = app(OfficialBusinessAuthorizationService::class);
    expect($authorization->canEdit($user, $trip))->toBeTrue();
    $manager = User::create(['name' => 'Manager', 'email' => 'manager-capability@example.test', 'role' => 'Manager', 'is_active' => true]);
    expect($authorization->canEdit($manager, $trip))->toBeFalse();
    DB::table('user_permissions')->insert(['user_id' => $manager->id, 'manage_hc_obf' => true]);
    expect($authorization->canEdit($manager->fresh(), $trip))->toBeTrue();
});

test('other employees cannot edit or submit drafts and submitted masters remain protected', function ($status, $own, $intent) {
    $user = User::create(['name' => 'Employee', 'email' => 'denied@example.test', 'role' => 'Employee', 'is_active' => true]);
    $employee = Employee::find(DB::table('employees')->insertGetId(['user_id' => $user->id, 'first_name' => 'Other', 'last_name' => 'Employee']));
    $trip = OfficialBusinessTrip::create([
        'ob_reference_no' => 'OB-DENIED', 'workflow_version' => 3, 'status' => $status,
        'employee_id' => $own ? $employee->id : null, 'created_by' => $own ? $user->id : $this->manager->id,
    ]);
    expect(app(OfficialBusinessAuthorizationService::class)->canEdit($user, $trip))->toBeFalse();
    $this->actingAs($user)->putJson(route('human-capital.obf.update', $trip), [
        'intent' => $intent, 'destination' => 'Forbidden edit',
    ])->assertForbidden();
    expect($trip->fresh()->status)->toBe($status)
        ->and($trip->fresh()->destination)->toBeNull()
        ->and(OfficialBusinessTrip::count())->toBe(1);
})->with([
    ['Draft', false, 'save_draft'], ['Draft', false, 'submit'],
    ['Submitted', true, 'submit'], ['Approved', true, 'save_draft'],
]);

test('master reference index protects deleted records and ordinary edits retain their reference', function () {
    (require database_path('migrations/2026_09_28_000001_unique_official_business_master_reference.php'))->up();

    $this->actingAs($this->manager)->post(route('human-capital.obf.store'), [
        'intent' => 'save_draft',
        'ob_reference_no' => 'OBF-20260928-ABCDE',
    ])->assertSessionHas('success');

    $trip = OfficialBusinessTrip::query()->sole();
    expect($trip->ob_reference_no)->toBe('OBF-20260928-ABCDE');

    $this->actingAs($this->manager)->put(route('human-capital.obf.update', $trip), [
        'intent' => 'save_draft',
        'ob_reference_no' => 'OBF-20260928-ZZZZZ',
    ])->assertSessionHas('success');
    expect($trip->fresh()->ob_reference_no)->toBe('OBF-20260928-ABCDE');

    $trip->delete();
    $this->expectException(\Illuminate\Database\QueryException::class);
    DB::table('official_business_trips')->insert([
        'ob_reference_no' => 'OBF-20260928-ABCDE',
        'workflow_version' => 3,
        'status' => 'Draft',
    ]);
});

test('master travelers use stable employee identities and name snapshots', function () {
    (require database_path('migrations/2026_09_28_000002_create_official_business_master_personnel.php'))->up();
    DB::table('employees')->insert(['id' => 51, 'first_name' => 'Master', 'last_name' => 'Filer']);
    DB::table('employees')->insert(['id' => 52, 'first_name' => 'Master', 'last_name' => 'Traveler']);

    $this->actingAs($this->manager)->post(route('human-capital.obf.store'), [
        'intent' => 'save_draft', 'employee_id' => 51,
        'team_member_employee_ids' => [52], 'team_members' => ['Spoofed Name'],
    ])->assertSessionHas('success');

    $trip = OfficialBusinessTrip::query()->sole();
    expect($trip->personnel()->orderBy('employee_id')->pluck('employee_id')->all())->toBe([51, 52])
        ->and($trip->team_members)->toBe(['Master Traveler']);

    DB::table('employees')->where('id', 52)->update(['last_name' => 'Renamed']);
    expect($trip->personnel()->where('employee_id', 52)->sole()->employee_name_snapshot)->toBe('Master Traveler');
});

test('v3 workflow schema permits incomplete draft fields', function () {
    $trip = OfficialBusinessTrip::create([
        'ob_reference_no' => 'OBF-V3-DRAFT-SCHEMA',
        'workflow_version' => 3,
        'status' => 'Draft',
        'created_by' => $this->manager->id,
    ]);

    expect($trip->workflow_version)->toBe(3)
        ->and($trip->status)->toBe('Draft')
        ->and($trip->employee_id)->toBeNull()
        ->and($trip->employee_name)->toBeNull()
        ->and($trip->destination)->toBeNull()
        ->and($trip->purpose)->toBeNull()
        ->and($trip->date_from)->toBeNull();
});

test('manager can save an incomplete v3 official business draft', function () {
    $response = $this
        ->actingAs($this->manager)
        ->post(route('human-capital.obf.store'), [
            'intent' => 'save_draft',
        ]);

    $response
        ->assertRedirect(route('human-capital.obf'))
        ->assertSessionHas(
            'success',
            'Official Business draft saved successfully.'
        );

    $trip = OfficialBusinessTrip::query()->sole();

    expect($trip->workflow_version)->toBe(3)
        ->and($trip->status)->toBe('Draft')
        ->and($trip->employee_id)->toBeNull()
        ->and($trip->employee_name)->toBeNull()
        ->and($trip->destination)->toBeNull()
        ->and($trip->purpose)->toBeNull()
        ->and($trip->date_from)->toBeNull()
        ->and($trip->created_by)->toBe($this->manager->id);
});

test('changing transportation mode clears details from the previous mode', function () {
    $this->actingAs($this->manager)->post(route('human-capital.obf.store'), [
        'intent' => 'save_draft',
        'transportation_mode' => 'Company Vehicle',
        'company_vehicle' => 'Van',
        'plate_number' => 'ABC-123',
    ])->assertSessionHas('success');

    $trip = OfficialBusinessTrip::query()->sole();
    expect($trip->transportation_details['company_vehicle'])->toBe('Van');

    $this->put(route('human-capital.obf.update', $trip), [
        'intent' => 'save_draft',
        'transportation_mode' => 'Public Transportation',
        'public_transport_mode' => 'Bus',
        'transportation_cost' => 50,
        'company_vehicle' => 'Van',
        'plate_number' => 'ABC-123',
    ])->assertSessionHas('success');

    $details = $trip->fresh()->transportation_details;
    expect($details['public_transport_mode'])->toBe('Bus')
        ->and((float) $details['transportation_cost'])->toBe(50.0)
        ->and($details['company_vehicle'])->toBeNull()
        ->and($details['plate_number'])->toBeNull();
});

test('employee selection snapshots Human Capital routing through draft edits and submission', function () {
    DB::table('departments')->insert(['id' => 11, 'department_name' => 'Consulting']);
    DB::table('departments')->insert(['id' => 12, 'department_name' => 'Operations']);
    DB::table('employees')->insert(['id' => 41, 'employee_code' => 'HC-41', 'first_name' => 'First', 'last_name' => 'Traveler', 'position' => 'Consultant', 'department_id' => 11, 'immediate_supervisor' => 'First Superior']);
    DB::table('employees')->insert(['id' => 42, 'employee_code' => 'HC-42', 'first_name' => 'Second', 'last_name' => 'Traveler', 'position' => 'Manager', 'department_id' => 12, 'immediate_supervisor' => 'Second Superior']);
    DB::table('employees')->insert(['id' => 43, 'first_name' => 'First', 'last_name' => 'Superior', 'work_email' => 'first.superior@example.test']);
    DB::table('employees')->insert(['id' => 44, 'first_name' => 'Second', 'last_name' => 'Superior', 'work_email' => 'second.superior@example.test']);

    $this->actingAs($this->manager)->post(route('human-capital.obf.store'), [
        'intent' => 'save_draft', 'employee_id' => 41,
        'immediate_superior' => 'Forged Superior', 'superior_email' => 'forged@example.test',
    ])->assertSessionHas('success');

    $trip = OfficialBusinessTrip::query()->sole();
    expect($trip->employee_name)->toBe('First Traveler')
        ->and($trip->employee_code)->toBe('HC-41')
        ->and($trip->position)->toBe('Consultant')
        ->and($trip->department)->toBe('Consulting')
        ->and($trip->immediate_superior)->toBe('First Superior')
        ->and($trip->superior_email)->toBe('first.superior@example.test');

    DB::table('employees')->where('id', 41)->update(['immediate_supervisor' => 'Changed Later']);
    $this->actingAs($this->manager)->put(route('human-capital.obf.update', $trip), [
        'intent' => 'save_draft', 'employee_id' => 41,
    ])->assertSessionHas('success');
    expect($trip->fresh()->immediate_superior)->toBe('First Superior');

    $this->actingAs($this->manager)->put(route('human-capital.obf.update', $trip), [
        'intent' => 'save_draft', 'employee_id' => 42,
    ])->assertSessionHas('success');
    $trip->refresh();
    expect($trip->employee_name)->toBe('Second Traveler')
        ->and($trip->position)->toBe('Manager')
        ->and($trip->department)->toBe('Operations')
        ->and($trip->immediate_superior)->toBe('Second Superior')
        ->and($trip->superior_email)->toBe('second.superior@example.test');

    $this->actingAs($this->manager)->put(route('human-capital.obf.update', $trip), [
        'intent' => 'submit', 'employee_id' => 42,
        'destination' => 'Cebu City Hall', 'purpose' => 'Client coordination meeting',
        'date_from' => '2026-09-20', 'date_to' => '2026-09-20', 'is_client_travel' => 0,
    ])->assertSessionHas('success');
    expect($trip->fresh()->status)->toBe('Submitted')
        ->and($trip->superior_email)->toBe('second.superior@example.test');
});

test('saving an existing v3 draft keeps it in draft status', function () {
    $trip = OfficialBusinessTrip::create([
        'ob_reference_no' => 'OBF-V3-CONTINUE',
        'workflow_version' => 3,
        'status' => 'Draft',
        'created_by' => $this->manager->id,
    ]);

    $response = $this
        ->actingAs($this->manager)
        ->put(route('human-capital.obf.update', ['officialBusinessTrip' => $trip]), [
            'intent' => 'save_draft',
            'destination' => 'Cebu City Hall',
        ]);

    $response
        ->assertRedirect(route('human-capital.obf'))
        ->assertSessionHas(
            'success',
            'Official Business draft saved successfully.'
        );

    $trip->refresh();

    expect($trip->workflow_version)->toBe(3)
        ->and($trip->status)->toBe('Draft')
        ->and($trip->destination)->toBe('Cebu City Hall');
});

test('submitting an incomplete v3 draft fails required validation', function () {
    $trip = OfficialBusinessTrip::create([
        'ob_reference_no' => 'OBF-V3-INCOMPLETE',
        'workflow_version' => 3,
        'status' => 'Draft',
        'created_by' => $this->manager->id,
    ]);

    $response = $this
        ->actingAs($this->manager)
        ->from(route('human-capital.obf'))
        ->put(route('human-capital.obf.update', ['officialBusinessTrip' => $trip]), [
            'intent' => 'submit',
        ]);

    $response
        ->assertRedirect(route('human-capital.obf'))
        ->assertSessionHasErrors([
            'purpose',
            'date_from',
        ]);

    expect($trip->fresh()->status)->toBe('Draft');
});

test('a completed v3 draft can be submitted', function () {
    $employeeId = DB::table('employees')->insertGetId([
        'user_id' => $this->manager->id,
        'employee_code' => 'EMP-V3-001',
        'first_name' => 'O.B.',
        'last_name' => 'Manager',
        'position' => 'Consultant',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $trip = OfficialBusinessTrip::create([
        'ob_reference_no' => 'OBF-V3-SUBMIT',
        'workflow_version' => 3,
        'status' => 'Draft',
        'created_by' => $this->manager->id,
    ]);

    $response = $this
        ->actingAs($this->manager)
        ->put(route('human-capital.obf.update', ['officialBusinessTrip' => $trip]), [
            'intent' => 'submit',
            'employee_id' => $employeeId,
            'destination' => 'Cebu City Hall',
            'purpose' => 'Client coordination meeting',
            'date_from' => '2026-09-20',
            'date_to' => '2026-09-20',
            'is_client_travel' => 0,
        ]);

    $response
        ->assertRedirect(route('human-capital.obf'))
        ->assertSessionHas(
            'success',
            'Official Business request submitted successfully.'
        );

    $trip->refresh();

    expect($trip->workflow_version)->toBe(3)
        ->and($trip->status)->toBe('Submitted')
        ->and($trip->employee_id)->toBe($employeeId)
        ->and($trip->employee_name)->toBe('O.B. Manager')
        ->and($trip->destination)->toBe('Cebu City Hall')
        ->and($trip->purpose)->toBe('Client coordination meeting')
        ->and($trip->date_from?->format('Y-m-d'))->toBe('2026-09-20');
});

test('v3 draft update transitions record exactly one semantic audit event', function () {
    $employeeId = DB::table('employees')->insertGetId([
        'user_id' => $this->manager->id,
        'employee_code' => 'EMP-V3-AUDIT',
        'first_name' => 'Audit',
        'last_name' => 'Manager',
        'position' => 'Consultant',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $draft = OfficialBusinessTrip::create([
        'ob_reference_no' => 'OBF-V3-AUDIT-DRAFT',
        'workflow_version' => 3,
        'status' => 'Draft',
        'created_by' => $this->manager->id,
    ]);

    $reverted = OfficialBusinessTrip::create([
        'ob_reference_no' => 'OBF-V3-AUDIT-REVERTED',
        'workflow_version' => 3,
        'status' => 'Reverted',
        'created_by' => $this->manager->id,
    ]);

    $submitted = OfficialBusinessTrip::create([
        'ob_reference_no' => 'OBF-V3-AUDIT-SUBMITTED',
        'workflow_version' => 3,
        'status' => 'Draft',
        'created_by' => $this->manager->id,
    ]);

    $this->actingAs($this->manager)
        ->put(route('human-capital.obf.update', ['officialBusinessTrip' => $draft]), [
            'intent' => 'save_draft',
            'destination' => 'Draft destination',
        ])
        ->assertRedirect(route('human-capital.obf'));

    $this->put(route('human-capital.obf.update', ['officialBusinessTrip' => $reverted]), [
        'intent' => 'save_draft',
        'destination' => 'Corrected destination',
    ])->assertRedirect(route('human-capital.obf'));

    $this->put(route('human-capital.obf.update', ['officialBusinessTrip' => $submitted]), [
        'intent' => 'submit',
        'employee_id' => $employeeId,
        'destination' => 'Submitted destination',
        'purpose' => 'Audit regression proof',
        'date_from' => '2026-09-20',
        'date_to' => '2026-09-20',
        'is_client_travel' => 0,
    ])->assertRedirect(route('human-capital.obf'));

    expect(DB::table('activity_audit_logs')
        ->where('activity_type', 'OFFICIAL_BUSINESS_TRAVEL')
        ->where('activity_id', $draft->id)
        ->where('action', 'draft_saved')
        ->count())->toBe(1)
        ->and(DB::table('activity_audit_logs')
            ->where('activity_type', 'OFFICIAL_BUSINESS_TRAVEL')
            ->where('activity_id', $reverted->id)
            ->where('action', 'draft_saved')
            ->count())->toBe(1)
        ->and(DB::table('activity_audit_logs')
            ->where('activity_type', 'OFFICIAL_BUSINESS_TRAVEL')
            ->where('activity_id', $submitted->id)
            ->where('action', 'submitted')
            ->count())->toBe(1);
});

test('submitted v3 official business can be approved', function () {
    $trip = OfficialBusinessTrip::create([
        'ob_reference_no' => 'OBF-V3-APPROVE',
        'workflow_version' => 3,
        'status' => 'Submitted',
        'created_by' => $this->manager->id,
    ]);

    $response = $this
        ->actingAs($this->manager)
        ->post(route('human-capital.obf.approve', [
            'officialBusinessTrip' => $trip,
        ]));

    $response->assertRedirect();

    expect($trip->fresh()->status)->toBe('Approved');
});

test('submitted v3 official business can be explicitly reverted for correction', function () {
    $trip = OfficialBusinessTrip::create([
        'ob_reference_no' => 'OBF-V3-REVERT',
        'workflow_version' => 3,
        'status' => 'Submitted',
        'created_by' => $this->manager->id,
    ]);

    $this->actingAs($this->manager)
        ->post(route('human-capital.obf.revert', ['officialBusinessTrip' => $trip]), [
            'remarks' => 'Correct the travel dates.',
        ])
        ->assertRedirect();

    expect($trip->fresh()->status)->toBe('Reverted')
        ->and($trip->fresh()->remarks)->toBe('Correct the travel dates.')
        ->and(DB::table('activity_audit_logs')
            ->where('activity_id', $trip->id)
            ->where('action', 'reverted')
            ->count())->toBe(1);
});

test('v3 json approval and revert preserve independent movement state', function () {
    $trip = OfficialBusinessTrip::create([
        'ob_reference_no' => 'OBF-V3-APPROVAL-MOVEMENT',
        'workflow_version' => 3,
        'status' => 'Submitted',
        'movement_status' => 'Returned',
        'actual_departure' => '2026-09-20 08:00:00',
        'actual_return' => '2026-09-20 09:30:00',
        'duration_minutes' => 90,
        'created_by' => $this->manager->id,
    ]);

    $this->actingAs($this->manager)
        ->postJson(route('human-capital.obf.approve', $trip))
        ->assertOk()
        ->assertJsonPath('record.status', 'Approved')
        ->assertJsonPath('record.movement_status', 'Returned');

    expect($trip->fresh()->only([
        'status', 'movement_status', 'actual_departure', 'actual_return', 'duration_minutes',
    ]))->toMatchArray([
        'status' => 'Approved',
        'movement_status' => 'Returned',
        'actual_departure' => '2026-09-20 08:00:00',
        'actual_return' => '2026-09-20 09:30:00',
        'duration_minutes' => 90,
    ]);

    $trip->refresh()->update(['status' => 'Submitted']);

    $this->actingAs($this->manager)
        ->postJson(route('human-capital.obf.revert', $trip), ['remarks' => 'Please correct the dates.'])
        ->assertOk()
        ->assertJsonPath('record.status', 'Reverted')
        ->assertJsonPath('record.movement_status', 'Returned');

    expect($trip->fresh()->only([
        'status', 'movement_status', 'actual_departure', 'actual_return', 'duration_minutes', 'remarks',
    ]))->toMatchArray([
        'status' => 'Reverted',
        'movement_status' => 'Returned',
        'actual_departure' => '2026-09-20 08:00:00',
        'actual_return' => '2026-09-20 09:30:00',
        'duration_minutes' => 90,
        'remarks' => 'Please correct the dates.',
    ]);
});

test('v3 revert preserves every independent movement state', function ($movement) {
    $trip = OfficialBusinessTrip::create([
        'ob_reference_no' => 'OBF-V3-REVERT-'.$movement,
        'workflow_version' => 3,
        'status' => 'Submitted',
        'movement_status' => $movement,
        'actual_departure' => in_array($movement, ['Departed', 'Returned'], true)
            ? '2026-09-20 08:00:00'
            : null,
        'actual_return' => $movement === 'Returned' ? '2026-09-20 09:30:00' : null,
        'duration_minutes' => $movement === 'Returned' ? 90 : null,
        'created_by' => $this->manager->id,
    ]);

    $this->actingAs($this->manager)
        ->postJson(route('human-capital.obf.revert', $trip), ['remarks' => 'Please correct the dates.'])
        ->assertOk()
        ->assertJsonPath('record.status', 'Reverted')
        ->assertJsonPath('record.movement_status', $movement);

    expect($trip->fresh()->only([
        'status', 'movement_status', 'actual_departure', 'actual_return', 'duration_minutes',
    ]))->toMatchArray([
        'status' => 'Reverted',
        'movement_status' => $movement,
        'actual_departure' => in_array($movement, ['Departed', 'Returned'], true)
            ? '2026-09-20 08:00:00'
            : null,
        'actual_return' => $movement === 'Returned' ? '2026-09-20 09:30:00' : null,
        'duration_minutes' => $movement === 'Returned' ? 90 : null,
    ]);
})->with(['Not Started', 'Ready', 'Departed', 'Returned']);

test('v3 approval controls retain authorization and require revert remarks', function () {
    $trip = OfficialBusinessTrip::create([
        'ob_reference_no' => 'OBF-V3-APPROVAL-AUTH',
        'workflow_version' => 3,
        'status' => 'Submitted',
        'movement_status' => 'Departed',
        'actual_departure' => '2026-09-20 08:00:00',
        'created_by' => $this->manager->id,
    ]);

    $this->actingAs($this->manager)
        ->postJson(route('human-capital.obf.revert', $trip), [])
        ->assertUnprocessable();

    $unauthorized = User::create([
        'name' => 'O.B. Approval Unauthorized',
        'email' => 'ob-approval-unauthorized@example.test',
        'role' => 'Employee',
        'is_active' => true,
    ]);

    $this->actingAs($unauthorized)
        ->postJson(route('human-capital.obf.approve', $trip))
        ->assertForbidden();

    expect($trip->fresh()->status)->toBe('Submitted')
        ->and($trip->fresh()->movement_status)->toBe('Departed');
});

test('v3 approval and rejection reject invalid states', function () {
    $approved = OfficialBusinessTrip::create([
        'ob_reference_no' => 'OBF-V3-INVALID-APPROVE',
        'workflow_version' => 3,
        'status' => 'Approved',
        'created_by' => $this->manager->id,
    ]);

    $this
        ->actingAs($this->manager)
        ->post(route('human-capital.obf.approve', [
            'officialBusinessTrip' => $approved,
        ]))
        ->assertStatus(422);

    $draft = OfficialBusinessTrip::create([
        'ob_reference_no' => 'OBF-V3-INVALID-REJECT',
        'workflow_version' => 3,
        'status' => 'Draft',
        'created_by' => $this->manager->id,
    ]);

    $this
        ->actingAs($this->manager)
        ->post(route('human-capital.obf.reject', [
            'officialBusinessTrip' => $draft,
        ]))
        ->assertStatus(422);

    expect($approved->fresh()->status)->toBe('Approved')
        ->and($draft->fresh()->status)->toBe('Draft');
});

test('v3 human capital listing ignores legacy team member names and uses ticket personnel identity', function () {
    $viewer = User::create([
        'name' => 'Canonical Visibility Employee',
        'email' => 'canonical-visibility@example.test',
        'role' => 'Employee',
        'is_active' => true,
    ]);

    $viewerEmployeeId = DB::table('employees')->insertGetId([
        'user_id' => $viewer->id,
        'employee_code' => 'EMP-OBV3-VIS',
        'first_name' => 'Canonical Visibility',
        'last_name' => 'Employee',
        'position' => 'Associate',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $owner = User::create([
        'name' => 'Different O.B. Owner',
        'email' => 'different-ob-owner@example.test',
        'role' => 'Employee',
        'is_active' => true,
    ]);

    $ownerEmployeeId = DB::table('employees')->insertGetId([
        'user_id' => $owner->id,
        'employee_code' => 'EMP-OBV3-OWNER',
        'first_name' => 'Different O.B.',
        'last_name' => 'Owner',
        'position' => 'Associate',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $legacy = OfficialBusinessTrip::create([
        'ob_reference_no' => 'OBF-V1-LEGACY-NAME-VISIBLE',
        'workflow_version' => 1,
        'status' => 'Pending',
        'employee_id' => $ownerEmployeeId,
        'employee_name' => $owner->name,
        'team_members' => [$viewer->name],
        'created_by' => $owner->id,
    ]);

    $v3NameOnly = OfficialBusinessTrip::create([
        'ob_reference_no' => 'OBF-V3-NAME-ONLY-HIDDEN',
        'workflow_version' => 3,
        'status' => 'Draft',
        'employee_id' => $ownerEmployeeId,
        'employee_name' => $owner->name,
        'team_members' => [$viewer->name],
        'created_by' => $owner->id,
    ]);

    $client = \App\Models\Company::create([
        'company_name' => 'OBV3 Visibility Client',
    ]);

    $v3Canonical = OfficialBusinessTrip::create([
        'ob_reference_no' => 'OBF-V3-CANONICAL-PERSONNEL',
        'workflow_version' => 3,
        'status' => 'Draft',
        'is_client_travel' => true,
        'company_id' => $client->id,
        'employee_id' => $ownerEmployeeId,
        'employee_name' => $owner->name,
        'team_members' => [],
        'created_by' => $owner->id,
    ]);

    $ticket = $v3Canonical->tripTickets()->create([
        'company_id' => $client->id,
        'client_name_snapshot' => $client->company_name,
        'status' => 'Draft',
        'created_by_user_id' => $owner->id,
    ]);

    $ticket->personnel()->create([
        'employee_id' => $viewerEmployeeId,
        'role' => 'Participant',
        'participation_status' => 'Assigned',
    ]);

    $response = $this
        ->actingAs($viewer)
        ->get(route('human-capital.obf'))
        ->assertOk();

    $references = collect($response->viewData('trips'))
        ->pluck('ob_reference_no')
        ->values()
        ->all();

    expect($references)
        ->toContain($legacy->ob_reference_no)
        ->toContain($v3Canonical->ob_reference_no)
        ->toContain($v3NameOnly->ob_reference_no);

    expect(collect($response->viewData('trips'))->firstWhere('id', $v3Canonical->id)['can_edit'])->toBeFalse();
    expect(collect($response->viewData('trips'))->firstWhere('id', $v3NameOnly->id)['can_view'])->toBeTrue()
        ->and(collect($response->viewData('trips'))->firstWhere('id', $v3NameOnly->id)['can_edit'])->toBeFalse();
    foreach (['save_draft', 'submit'] as $intent) {
        $this->putJson(route('human-capital.obf.update', $v3Canonical), ['intent' => $intent])
            ->assertForbidden();
    }
});
test('master serializer hides finance fields from a non finance viewer', function () {
    $viewer = User::create([
        'name' => 'OBV3 Non Finance Viewer',
        'email' => 'obv3-non-finance-viewer@example.test',
        'role' => 'Employee',
        'is_active' => true,
    ]);

    DB::table('user_permissions')->insert([
        'user_id' => $viewer->id,
        'access_hc_obf' => true,
        'access_hc_obf_finance' => false,
    ]);

    $trip = OfficialBusinessTrip::create([
        'ob_reference_no' => 'OBF-V3-FINANCE-REDACTION',
        'workflow_version' => 3,
        'status' => 'Draft',
        'created_by' => $viewer->id,
        'client_payment_status' => 'Billable',
        'client_payment_items' => ['Transportation'],
        'client_payment_other' => 'Special billing item',
        'amount_client_will_pay' => 12500,
        'travel_credit_details' => [
            'monthly_limit' => 50000,
            'used_this_month' => 10000,
            'needed_for_trip' => 5000,
            'remaining_after_trip' => 35000,
        ],
        'estimated_expenses' => 15000,
    ]);

    $this->actingAs($viewer);

    $controller = app(\App\Http\Controllers\OfficialBusinessTripController::class);
    $method = new \ReflectionMethod($controller, 'formatTrip');
    $method->setAccessible(true);

    $payload = $method->invoke($controller, $trip->fresh());

    expect($payload)
        ->toBeArray()
        ->toHaveKey('id', $trip->id)
        ->toHaveKey('status', 'Draft')
        ->not->toHaveKey('client_payment_status')
        ->not->toHaveKey('client_payment_items')
        ->not->toHaveKey('client_payment_other')
        ->not->toHaveKey('amount_client_will_pay')
        ->not->toHaveKey('travel_credit_details')
        ->not->toHaveKey('estimated_expenses');
});

test('master serializer preserves finance fields for an authorized finance viewer', function () {
    $viewer = User::create([
        'name' => 'OBV3 Finance Viewer',
        'email' => 'obv3-finance-viewer@example.test',
        'role' => 'Employee',
        'is_active' => true,
    ]);

    DB::table('user_permissions')->insert([
        'user_id' => $viewer->id,
        'access_hc_obf' => true,
        'access_hc_obf_finance' => true,
    ]);

    // Permission was inserted directly through the query builder.
    // Refresh the model before exercising Eloquent permission resolution.
    $viewer = $viewer->fresh();

    expect(
        app(\App\Services\OfficialBusinessAuthorizationService::class)
            ->canReadFinance($viewer)
    )->toBeTrue();

    $trip = OfficialBusinessTrip::create([
        'ob_reference_no' => 'OBF-V3-FINANCE-VISIBLE',
        'workflow_version' => 3,
        'status' => 'Draft',
        'created_by' => $viewer->id,
        'client_payment_status' => 'Billable',
        'client_payment_items' => ['Transportation'],
        'client_payment_other' => 'Special billing item',
        'amount_client_will_pay' => 12500,
        'travel_credit_details' => [
            'monthly_limit' => 50000,
            'used_this_month' => 10000,
            'needed_for_trip' => 5000,
            'remaining_after_trip' => 35000,
        ],
        'estimated_expenses' => 15000,
    ]);

    $this->actingAs($viewer);

    $controller = app(\App\Http\Controllers\OfficialBusinessTripController::class);
    $method = new \ReflectionMethod($controller, 'formatTrip');
    $method->setAccessible(true);

    $payload = $method->invoke($controller, $trip->fresh());

    expect($payload)
        ->toBeArray()
        ->toHaveKey('client_payment_status', 'Billable')
        ->toHaveKey('client_payment_items')
        ->toHaveKey('client_payment_other', 'Special billing item')
        ->toHaveKey('amount_client_will_pay')
        ->toHaveKey('travel_credit_details')
        ->toHaveKey('estimated_expenses');
});
test('admin dashboard can approve and reject submitted v3 official business', function () {
    $admin = User::create([
        'name' => 'OBV3 Admin Transition Tester',
        'email' => 'obv3-admin-transition@example.test',
        'role' => 'Admin',
    ]);

    $approveTrip = OfficialBusinessTrip::create([
        'ob_reference_no' => 'OBF-V3-ADMIN-APPROVE',
        'workflow_version' => 3,
        'status' => 'Submitted',
        'created_by' => $this->manager->id,
    ]);

    $rejectTrip = OfficialBusinessTrip::create([
        'ob_reference_no' => 'OBF-V3-ADMIN-REJECT',
        'workflow_version' => 3,
        'status' => 'Submitted',
        'created_by' => $this->manager->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.human-capital.obf.approve', [
            'officialBusinessTrip' => $approveTrip,
        ]))
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('admin.human-capital.obf.reject', [
            'officialBusinessTrip' => $rejectTrip,
        ]), [
            'remarks' => 'Rejected through admin dashboard.',
        ])
        ->assertRedirect();

    expect($approveTrip->fresh()->status)->toBe('Approved')
        ->and($rejectTrip->fresh()->status)->toBe('Rejected')
        ->and($rejectTrip->fresh()->remarks)->toBe('Rejected through admin dashboard.');
});

test('admin dashboard cannot approve or reject non submitted v3 official business', function () {
    $admin = User::create([
        'name' => 'OBV3 Admin Guard Tester',
        'email' => 'obv3-admin-guard@example.test',
        'role' => 'Admin',
    ]);

    $draft = OfficialBusinessTrip::create([
        'ob_reference_no' => 'OBF-V3-ADMIN-DRAFT-GUARD',
        'workflow_version' => 3,
        'status' => 'Draft',
        'created_by' => $this->manager->id,
    ]);

    $completed = OfficialBusinessTrip::create([
        'ob_reference_no' => 'OBF-V3-ADMIN-COMPLETED-GUARD',
        'workflow_version' => 3,
        'status' => 'Completed',
        'created_by' => $this->manager->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.human-capital.obf.approve', [
            'officialBusinessTrip' => $draft,
        ]))
        ->assertStatus(422);

    $this->actingAs($admin)
        ->post(route('admin.human-capital.obf.reject', [
            'officialBusinessTrip' => $completed,
        ]), [
            'remarks' => 'This must not overwrite completion.',
        ])
        ->assertStatus(422);

    expect($draft->fresh()->status)->toBe('Draft')
        ->and($completed->fresh()->status)->toBe('Completed')
        ->and($completed->fresh()->remarks)->not->toBe(
            'This must not overwrite completion.'
        );
});
test('approved v3 official business readiness and departure preserve approval status', function () {
    $trip = OfficialBusinessTrip::create([
        'ob_reference_no' => 'OBF-V3-DEPART',
        'workflow_version' => 3,
        'status' => 'Approved',
        'created_by' => $this->manager->id,
    ]);

    $this->actingAs($this->manager)
        ->postJson(route('activities.official-business.ready', [
            'officialBusinessTrip' => $trip,
        ]))
        ->assertOk()
        ->assertJsonPath('record.status', 'Approved')
        ->assertJsonPath('record.movement_status', 'Ready');

    $this->actingAs($this->manager)
        ->postJson(route('activities.official-business.depart', [
            'officialBusinessTrip' => $trip,
        ]))
        ->assertOk();

    $trip->refresh();

    expect($trip->status)->toBe('Approved')
        ->and($trip->movement_status)->toBe('Departed')
        ->and($trip->actual_departure)->not->toBeNull()
        ->and($trip->actual_return)->toBeNull()
        ->and(DB::table('activity_audit_logs')
            ->where('activity_id', $trip->id)
            ->where('action', 'departed')
            ->count())->toBe(1);
});

test('ongoing v3 official business returns before management starts completion', function () {
    $trip = OfficialBusinessTrip::create([
        'ob_reference_no' => 'OBF-V3-RETURN',
        'workflow_version' => 3,
        'status' => 'Ongoing',
        'actual_departure' => now()->subMinutes(30),
        'created_by' => $this->manager->id,
    ]);

    $response = $this
        ->actingAs($this->manager)
        ->postJson(route('activities.official-business.return', [
            'officialBusinessTrip' => $trip,
        ]));

    $response->assertOk();

    $trip->refresh();

    expect($trip->status)->toBe('Approved')
        ->and($trip->movement_status)->toBe('Returned')
        ->and($trip->actual_return)->not->toBeNull()
        ->and($trip->duration_minutes)->toBeGreaterThanOrEqual(0)
        ->and(DB::table('activity_audit_logs')
            ->where('activity_id', $trip->id)
            ->where('action', 'returned')
            ->count())->toBe(1);

    $this->actingAs($this->manager)
        ->postJson(route('activities.official-business.for-completion', [
            'officialBusinessTrip' => $trip,
        ]))
        ->assertOk()
        ->assertJsonPath('record.status', 'For Completion');

    expect(DB::table('activity_audit_logs')
        ->where('activity_id', $trip->id)
        ->where('action', 'for_completion')
        ->count())->toBe(1);
});

test('start completion uses returned movement and preserves movement data', function () {
    $trip = OfficialBusinessTrip::create([
        'ob_reference_no' => 'OBF-V3-START-COMPLETION',
        'workflow_version' => 3,
        'status' => 'Approved',
        'movement_status' => 'Returned',
        'actual_departure' => '2026-09-20 08:00:00',
        'actual_return' => '2026-09-20 09:30:00',
        'duration_minutes' => 90,
        'created_by' => $this->manager->id,
    ]);

    $this->actingAs($this->manager)
        ->postJson(route('activities.official-business.for-completion', $trip))
        ->assertOk()
        ->assertJsonPath('record.status', 'For Completion')
        ->assertJsonPath('record.movement_status', 'Returned');

    expect($trip->fresh()->only([
        'status', 'movement_status', 'actual_departure', 'actual_return', 'duration_minutes',
    ]))->toMatchArray([
        'status' => 'For Completion',
        'movement_status' => 'Returned',
        'actual_departure' => '2026-09-20 08:00:00',
        'actual_return' => '2026-09-20 09:30:00',
        'duration_minutes' => 90,
    ]);

    foreach (['Departed', 'Ready'] as $movement) {
        $blocked = OfficialBusinessTrip::create([
            'ob_reference_no' => 'OBF-V3-BLOCK-'.$movement,
            'workflow_version' => 3,
            'status' => 'Approved',
            'movement_status' => $movement,
            'actual_departure' => '2026-09-20 08:00:00',
            'created_by' => $this->manager->id,
        ]);

        $this->actingAs($this->manager)
            ->postJson(route('activities.official-business.for-completion', $blocked))
            ->assertUnprocessable();
    }

    foreach (['Completed', 'Cancelled'] as $status) {
        $blocked = OfficialBusinessTrip::create([
            'ob_reference_no' => 'OBF-V3-BLOCK-'.$status,
            'workflow_version' => 3,
            'status' => $status,
            'movement_status' => 'Returned',
            'actual_return' => '2026-09-20 09:30:00',
            'created_by' => $this->manager->id,
        ]);

        $this->actingAs($this->manager)
            ->postJson(route('activities.official-business.for-completion', $blocked))
            ->assertUnprocessable();
    }

    $unauthorized = User::create([
        'name' => 'O.B. Unauthorized',
        'email' => 'ob-unauthorized@example.test',
        'role' => 'Employee',
        'is_active' => true,
    ]);

    $this->actingAs($unauthorized)
        ->postJson(route('activities.official-business.for-completion', $trip))
        ->assertForbidden();
});

test('legacy v1 movement remains in progress then completed', function () {
    $trip = OfficialBusinessTrip::create([
        'ob_reference_no' => 'OBF-V1-MOVEMENT',
        'workflow_version' => 1,
        'status' => 'Approved',
        'created_by' => $this->manager->id,
    ]);

    $depart = $this
        ->actingAs($this->manager)
        ->postJson(route('activities.official-business.depart', [
            'officialBusinessTrip' => $trip,
        ]));

    $depart->assertOk();

    $trip->refresh();

    expect($trip->status)->toBe('In Progress')
        ->and($trip->actual_departure)->not->toBeNull();

    $return = $this
        ->actingAs($this->manager)
        ->postJson(route('activities.official-business.return', [
            'officialBusinessTrip' => $trip,
        ]));

    $return->assertOk();

    $trip->refresh();

    expect($trip->status)->toBe('Completed')
        ->and($trip->actual_return)->not->toBeNull();
});
test('for completion v3 official business can be completed', function () {
    $trip = OfficialBusinessTrip::create([
        'ob_reference_no' => 'OBF-V3-COMPLETE',
        'workflow_version' => 3,
        'status' => 'For Completion',
        'actual_departure' => now()->subHour(),
        'actual_return' => now()->subMinutes(15),
        'created_by' => $this->manager->id,
    ]);

    $response = $this
        ->actingAs($this->manager)
        ->postJson(route('activities.official-business.complete', [
            'officialBusinessTrip' => $trip,
        ]));

    $response
        ->assertOk()
        ->assertJsonPath('record.status', 'Completed');

    expect($trip->fresh()->status)->toBe('Completed');
});

test('v3 completion returns a clear checklist when travel timestamps are incomplete', function () {
    $trip = OfficialBusinessTrip::create([
        'ob_reference_no' => 'OBF-V3-COMPLETE-CHECKLIST',
        'workflow_version' => 3,
        'status' => 'For Completion',
        'actual_return' => now()->subMinutes(15),
        'created_by' => $this->manager->id,
    ]);

    $response = $this
        ->actingAs($this->manager)
        ->postJson(route('activities.official-business.complete', [
            'officialBusinessTrip' => $trip,
        ]));

    $response
        ->assertStatus(422)
        ->assertJsonPath('completion_ready', false)
        ->assertJsonPath('missing_requirements.0.key', 'departure')
        ->assertJsonFragment([
            'key' => 'departure',
            'label' => 'Departure recorded',
            'complete' => false,
        ]);

    expect($trip->fresh()->status)->toBe('For Completion');
});

test('client v3 completion waits for every trip ticket to be resolved', function () {
    $trip = OfficialBusinessTrip::create([
        'ob_reference_no' => 'OBF-V3-CLIENT-CHECKLIST',
        'workflow_version' => 3,
        'status' => 'For Completion',
        'is_client_travel' => true,
        'actual_departure' => now()->subHour(),
        'actual_return' => now()->subMinutes(15),
        'created_by' => $this->manager->id,
    ]);

    $withoutTicket = $this
        ->actingAs($this->manager)
        ->postJson(route('activities.official-business.complete', [
            'officialBusinessTrip' => $trip,
        ]));

    $withoutTicket
        ->assertStatus(422)
        ->assertJsonPath('missing_requirements.0.key', 'client_trip_tickets');

    $client = Company::create(['company_name' => 'Completion Client']);
    $ticket = $trip->tripTickets()->create([
        'company_id' => $client->id,
        'created_by_user_id' => $this->manager->id,
    ]);

    $draftTicket = $this->postJson(route('activities.official-business.complete', [
        'officialBusinessTrip' => $trip,
    ]));

    $draftTicket
        ->assertStatus(422)
        ->assertJsonPath('missing_requirements.0.key', 'client_trip_tickets');

    $ticket->forceFill(['status' => 'Completed'])->save();

    $this->postJson(route('activities.official-business.complete', [
        'officialBusinessTrip' => $trip,
    ]))
        ->assertOk()
        ->assertJsonPath('record.status', 'Completed');

    expect($trip->fresh()->status)->toBe('Completed');
});

test('v3 completion rejects records outside for completion status', function () {
    $trip = OfficialBusinessTrip::create([
        'ob_reference_no' => 'OBF-V3-COMPLETE-GUARD',
        'workflow_version' => 3,
        'status' => 'Ongoing',
        'actual_departure' => now()->subHour(),
        'created_by' => $this->manager->id,
    ]);

    $response = $this
        ->actingAs($this->manager)
        ->postJson(route('activities.official-business.complete', [
            'officialBusinessTrip' => $trip,
        ]));

    $response->assertStatus(422);

    expect($trip->fresh()->status)->toBe('Ongoing');
});

test('legacy v1 official business cannot use the v3 completion action', function () {
    $trip = OfficialBusinessTrip::create([
        'ob_reference_no' => 'OBF-V1-COMPLETE-GUARD',
        'workflow_version' => 1,
        'status' => 'Completed',
        'actual_departure' => now()->subHour(),
        'actual_return' => now()->subMinutes(15),
        'created_by' => $this->manager->id,
    ]);

    $response = $this
        ->actingAs($this->manager)
        ->postJson(route('activities.official-business.complete', [
            'officialBusinessTrip' => $trip,
        ]));

    $response->assertStatus(422);

    expect($trip->fresh()->status)->toBe('Completed');
});

test('draft client ob can convert to internal and archives untouched client tickets', function () {
    $employeeId = DB::table('employees')->insertGetId([
        'user_id' => $this->manager->id,
        'employee_code' => 'EMP-V3-INTERNAL-SAFE',
        'first_name' => 'O.B.',
        'last_name' => 'Manager',
        'position' => 'Consultant',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $client = Company::create([
        'company_name' => 'Internal Conversion Client',
    ]);

    $trip = OfficialBusinessTrip::create([
        'ob_reference_no' => 'OBF-V3-INTERNAL-CONVERSION-SAFE',
        'workflow_version' => 3,
        'status' => 'Draft',
        'is_client_travel' => true,
        'company_id' => $client->id,
        'employee_id' => $employeeId,
        'employee_name' => $this->manager->name,
        'destination' => 'Client Office',
        'purpose' => 'Client coordination',
        'date_from' => '2026-09-21',
        'date_to' => '2026-09-21',
        'created_by' => $this->manager->id,
    ]);

    $ticket = $trip->tripTickets()->create([
        'company_id' => $client->id,
        'client_name_snapshot' => $client->company_name,
        'status' => 'Draft',
        'created_by_user_id' => $this->manager->id,
    ]);

    $response = $this
        ->actingAs($this->manager)
        ->put(route('human-capital.obf.update', $trip), [
            'intent' => 'save_draft',
            'employee_id' => $employeeId,
            'destination' => 'JK&C Office',
            'purpose' => 'Internal coordination',
            'date_from' => '2026-09-21',
            'date_to' => '2026-09-21',
            'is_client_travel' => 0,
        ]);

    $response->assertRedirect(route('human-capital.obf'));

    $trip->refresh();

    expect((bool) $trip->is_client_travel)->toBeFalse()
        ->and($trip->company_id)->toBeNull();

    $archivedTicket = \App\Models\OfficialBusinessTripTicket::withTrashed()->findOrFail($ticket->id);

    expect($archivedTicket->deleted_at)->not->toBeNull()
        ->and($archivedTicket->archived_at)->not->toBeNull()
        ->and($archivedTicket->archived_reason)->toBe('client_removed_from_master')
        ->and($archivedTicket->status)->toBe('Cancelled')
        ->and($trip->tripTickets()->count())->toBe(0);
});

test('client ob with meaningful ticket work cannot convert to internal', function () {
    $employeeId = DB::table('employees')->insertGetId([
        'user_id' => $this->manager->id,
        'employee_code' => 'EMP-V3-INTERNAL-PROTECTED',
        'first_name' => 'O.B.',
        'last_name' => 'Manager',
        'position' => 'Consultant',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $client = Company::create([
        'company_name' => 'Protected Conversion Client',
    ]);

    $trip = OfficialBusinessTrip::create([
        'ob_reference_no' => 'OBF-V3-INTERNAL-CONVERSION-PROTECTED',
        'workflow_version' => 3,
        'status' => 'Draft',
        'is_client_travel' => true,
        'company_id' => $client->id,
        'employee_id' => $employeeId,
        'employee_name' => $this->manager->name,
        'destination' => 'Protected Client Office',
        'purpose' => 'Client coordination',
        'date_from' => '2026-09-21',
        'date_to' => '2026-09-21',
        'created_by' => $this->manager->id,
    ]);

    $ticket = $trip->tripTickets()->create([
        'company_id' => $client->id,
        'client_name_snapshot' => $client->company_name,
        'status' => 'Draft',
        'created_by_user_id' => $this->manager->id,
    ]);

    DB::table('activity_audit_logs')->insert([
        'activity_type' => 'OFFICIAL_BUSINESS_TRIP_TICKET',
        'activity_id' => $ticket->id,
        'action' => 'evidence_uploaded',
        'user_id' => $this->manager->id,
        'changes' => null,
        'metadata' => json_encode(['source' => 'protected-conversion-test']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this
        ->actingAs($this->manager)
        ->put(route('human-capital.obf.update', $trip), [
            'intent' => 'save_draft',
            'employee_id' => $employeeId,
            'destination' => 'JK&C Office',
            'purpose' => 'Internal coordination',
            'date_from' => '2026-09-21',
            'date_to' => '2026-09-21',
            'is_client_travel' => 0,
        ]);

    $response
        ->assertRedirect()
        ->assertSessionHasErrors('company_ids');

    $trip->refresh();
    $ticket->refresh();

    expect((bool) $trip->is_client_travel)->toBeTrue()
        ->and((int) $trip->company_id)->toBe((int) $client->id)
        ->and($ticket->deleted_at)->toBeNull()
        ->and($ticket->archived_at)->toBeNull()
        ->and($ticket->archived_reason)->toBeNull();
});
