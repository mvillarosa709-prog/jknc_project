<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\HumanCapitalChangeRequest;
use App\Models\OfficialBusinessTrip;
use App\Models\User;
use App\Services\OfficialBusinessActivityService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->withoutMiddleware();

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password')->nullable();
        $table->string('role')->nullable();
        $table->timestamps();
    });

    Schema::create('user_permissions', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id');
        $table->boolean('access_hc_obf')->default(false);
        $table->boolean('access_hc_my_hc')->default(false);
        $table->boolean('access_activities')->default(false);
        $table->timestamps();
    });

    Schema::create('role_permissions', function (Blueprint $table) {
        $table->id();
        $table->string('role')->unique();
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
        $table->string('first_name');
        $table->string('last_name');
        $table->string('email')->nullable();
        $table->string('work_email')->nullable();
        $table->string('company_email')->nullable();
        $table->string('personal_email')->nullable();
        $table->string('position')->nullable();
        $table->uuid('digital_id_token')->nullable();
        $table->string('verification_reference')->nullable();
        $table->string('employment_status')->nullable();
        $table->date('digital_id_issued_at')->nullable();
        $table->string('payroll_type')->nullable();
        $table->timestamps();
    });

    Schema::create('companies', function (Blueprint $table) {
        $table->id();
        $table->string('company_name');
        $table->timestamps();
    });

    Schema::create('official_business_trips', function (Blueprint $table) {
        $table->id();
        $table->string('ob_reference_no')->nullable();
        $table->foreignId('employee_id');
        $table->string('employee_name');
        $table->string('destination');
        $table->text('additional_stops')->nullable();
        $table->date('date_from');
        $table->date('date_to')->nullable();
        $table->time('departure_time')->nullable();
        $table->time('return_time')->nullable();
        $table->dateTime('actual_departure')->nullable();
        $table->dateTime('actual_return')->nullable();
        $table->unsignedInteger('duration_minutes')->nullable();
        $table->boolean('is_client_travel')->default(false);
        $table->string('client_name')->nullable();
        $table->json('team_members')->nullable();
        $table->string('status')->default('Pending');

        // Legacy fixture records intentionally remain V1 unless a test
        // explicitly creates a V3 record.
        $table->unsignedTinyInteger('workflow_version')->default(1);

        $table->foreignId('created_by')->nullable();
        $table->foreignId('prepared_by_user_id')->nullable();
        $table->text('client_report_description')->nullable();
        $table->boolean('include_in_client_report')->default(false);
        $table->timestamps();
        $table->softDeletes();
    });

    // Minimal V3 relationship schema required by OfficialBusinessActivityService.
    // These tables intentionally model only the columns needed by this legacy
    // Activities test; production migrations remain authoritative.
    Schema::create('official_business_trip_tickets', function (Blueprint $table) {
        $table->id();
        $table->foreignId('official_business_trip_id');
        $table->foreignId('company_id');
        $table->string('reference_no')->nullable();
        $table->string('client_name_snapshot')->nullable();
        $table->string('status')->default('Draft');
        $table->foreignId('created_by_user_id')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('official_business_trip_ticket_personnel', function (Blueprint $table) {
        $table->id();
        $table->foreignId('official_business_trip_ticket_id');
        $table->foreignId('employee_id')->nullable();
        $table->string('employee_name_snapshot')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('human_capital_change_requests', function (Blueprint $table) {
        $table->id();
        $table->string('module')->index();
        $table->string('action')->index();
        $table->string('subject_type')->index();
        $table->unsignedBigInteger('subject_id')->nullable()->index();
        $table->string('subject_name')->nullable();
        $table->json('old_values')->nullable();
        $table->json('new_values')->nullable();
        $table->string('status')->default('Pending Approval')->index();
        $table->unsignedBigInteger('requested_by')->nullable()->index();
        $table->string('requested_by_name')->nullable();
        $table->timestamp('requested_at')->nullable();
        $table->unsignedBigInteger('reviewed_by')->nullable()->index();
        $table->timestamp('reviewed_at')->nullable();
        $table->text('review_note')->nullable();
        $table->timestamps();
    });

    Schema::create('human_capital_logs', function (Blueprint $table) {
        $table->id();
        $table->string('module')->index();
        $table->string('action')->index();
        $table->string('subject_type')->nullable()->index();
        $table->unsignedBigInteger('subject_id')->nullable()->index();
        $table->string('subject_name')->nullable();
        $table->text('description')->nullable();
        $table->json('old_values')->nullable();
        $table->json('new_values')->nullable();
        $table->unsignedBigInteger('user_id')->nullable()->index();
        $table->string('user_name')->nullable();
        $table->string('ip_address', 45)->nullable();
        $table->text('user_agent')->nullable();
        $table->timestamp('logged_at')->nullable()->index();
        $table->timestamps();
    });
});

function createActivitiesObEmployee(User $user, string $firstName, string $lastName): Employee
{
    return Employee::create([
        'user_id' => $user->id,
        'employee_code' => 'EMP-'.$user->id,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'work_email' => $user->email,
        'position' => 'Consultant',
    ]);
}

function createActivitiesObTrip(Employee $employee, array $attributes = []): OfficialBusinessTrip
{
    $preparedByUserId = $attributes['prepared_by_user_id'] ?? null;
    unset($attributes['prepared_by_user_id']);

    $trip = OfficialBusinessTrip::create(array_merge([
        'ob_reference_no' => 'OBF-'.$employee->id,
        'employee_id' => $employee->id,
        'employee_name' => $employee->full_name,
        'destination' => 'Cebu City Hall',
        'date_from' => '2026-08-20',
        'date_to' => '2026-08-20',
        'departure_time' => '08:30:00',
        'return_time' => '17:00:00',
        'is_client_travel' => false,
        'status' => 'Pending',
        'created_by' => $employee->user_id,
        'client_report_description' => null,
        'include_in_client_report' => false,
    ], $attributes));

    $trip->prepared_by_user_id = $preparedByUserId;
    $trip->save();

    return $trip;
}

test('the activities official business adapter requires authentication', function () {
    $this->withMiddleware()
        ->getJson('/api/official-business')
        ->assertUnauthorized();
});

test('an authenticated manager receives persisted official business records in the activities shape', function () {
    $admin = User::create([
        'name' => 'Operations Admin',
        'email' => 'admin@example.test',
        'role' => 'Admin',
    ]);
    $employee = createActivitiesObEmployee($admin, 'Operations', 'Admin');

    createActivitiesObTrip($employee, [
        'ob_reference_no' => 'OBF-20260820-0001',
        'additional_stops' => 'Client Office',
        'is_client_travel' => true,
        'client_name' => 'Acme Corporation',
        'status' => 'Approved',
        'prepared_by_user_id' => $admin->id,
        'client_report_description' => 'Client travel completed.',
        'include_in_client_report' => true,
    ]);

    $this->actingAs($admin)
        ->getJson('/api/official-business')
        ->assertOk()
        ->assertJsonCount(1, 'records')
        ->assertJsonPath('records.0.ref', 'OBF-20260820-0001')
        ->assertJsonPath('records.0.employee', 'Operations Admin')
        ->assertJsonPath('records.0.type', 'One Client')
        ->assertJsonPath('records.0.clients', 'Acme Corporation')
        ->assertJsonPath('records.0.destination', 'Cebu City Hall / Client Office')
        ->assertJsonPath('records.0.destination_primary', 'Cebu City Hall')
        ->assertJsonPath('records.0.additional_stops', 'Client Office')
        ->assertJsonPath('records.0.ob_reference_no', 'OBF-20260820-0001')
        ->assertJsonPath('records.0.departure', 'Aug 20, 2026, 8:30 AM')
        ->assertJsonPath('records.0.expectedReturn', 'Aug 20, 2026, 5:00 PM')
        ->assertJsonPath('records.0.dateFrom', '2026-08-20')
        ->assertJsonPath('records.0.dateTo', '2026-08-20')
        ->assertJsonPath('records.0.status', 'Approved')
        ->assertJsonPath('records.0.isMine', true)
        ->assertJsonPath('records.0.prepared_by_user_id', $admin->id)
        ->assertJsonPath('records.0.prepared_by.id', $admin->id)
        ->assertJsonPath('records.0.client_report_description', 'Client travel completed.')
        ->assertJsonPath('records.0.include_in_client_report', true)
        ->assertJsonPath('can_manage', true)
        ->assertJsonPath('capabilities.travel_movement', true)
        ->assertJsonPath('capabilities.soft_delete', true)
        ->assertJsonPath('capabilities.multiple_clients', true)
        ->assertJsonPath('supported_statuses', [
            'Draft',
            'Submitted',
            'Pending',
            'Approved',
            'Ready for Travel',
            'In Progress',
            'Ongoing',
            'Returned',
            'For Completion',
            'Completed',
            'Reverted',
            'Cancelled',
            'Rejected',
        ]);

    expect(OfficialBusinessTrip::count())->toBe(1);
});

test('official business activity service reuses scoped source records in the unified ledger shape', function () {
    $admin = User::create([
        'name' => 'Operations Admin',
        'email' => 'unified-admin@example.test',
        'role' => 'Admin',
    ]);
    $employee = createActivitiesObEmployee($admin, 'Operations', 'Admin');
    $trip = createActivitiesObTrip($employee, [
        'ob_reference_no' => 'OBF-UNIFIED-1',
        'is_client_travel' => true,
        'client_name' => 'Acme Corporation',
        'status' => 'Approved',
        'prepared_by_user_id' => $admin->id,
        'client_report_description' => 'Unified travel summary.',
        'include_in_client_report' => true,
    ]);

    $service = app(OfficialBusinessActivityService::class);
    $context = $service->context($admin);
    $activity = $service->toUnifiedActivity($context['records']->sole());

    expect($context['records']->sole()->is($trip))->toBeTrue()
        ->and($activity['id'])->toBe('official-business-travel-'.$trip->id)
        ->and($activity['source_id'])->toBe($trip->id)
        ->and($activity['type'])->toBe('official_business_travel')
        ->and($activity['account'])->toBe('Acme Corporation')
        ->and($activity['source_status'])->toBe('Approved')
        ->and($activity['duration_minutes'])->toBeNull()
        ->and($activity['prepared_by_user_id'])->toBe($admin->id)
        ->and($activity['client_report_description'])->toBe('Unified travel summary.')
        ->and($activity['include_in_client_report'])->toBeTrue()
        ->and($activity['attachments'])->toBe([]);

    expect(OfficialBusinessTrip::count())->toBe(1);
});

test('a regular employee can read all active official business records while mine stays owner-scoped', function () {
    $user = User::create([
        'name' => 'Juan Dela Cruz',
        'email' => 'juan@example.test',
        'role' => 'Employee',
    ]);
    \Illuminate\Support\Facades\DB::table('user_permissions')->insert([
        'user_id' => $user->id,
        'access_hc_obf' => false,
        'access_hc_my_hc' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $employee = createActivitiesObEmployee($user, 'Juan', 'Dela Cruz');
    $otherUser = User::create([
        'name' => 'Other Employee',
        'email' => 'other@example.test',
        'role' => 'Employee',
    ]);
    $otherEmployee = createActivitiesObEmployee($otherUser, 'Other', 'Employee');

    createActivitiesObTrip($employee, ['ob_reference_no' => 'OBF-OWN']);
    createActivitiesObTrip($otherEmployee, [
        'ob_reference_no' => 'OBF-TEAM',
        'created_by' => $otherUser->id,
        'team_members' => ['Juan Dela Cruz'],
    ]);
    createActivitiesObTrip($otherEmployee, [
        'ob_reference_no' => 'OBF-HIDDEN',
        'created_by' => $otherUser->id,
    ]);

    $response = $this->actingAs($user)->getJson('/api/official-business')->assertOk();

    expect(collect($response->json('records'))->pluck('ref')->all())
        ->toEqualCanonicalizing(['OBF-OWN', 'OBF-TEAM', 'OBF-HIDDEN']);

    $response->assertJsonPath('can_manage', false);
    $response->assertJsonPath('can_view_all', true);
    expect(collect($response->json('records'))->firstWhere('ref', 'OBF-OWN')['isMine'])->toBeTrue()
        ->and(collect($response->json('records'))->firstWhere('ref', 'OBF-HIDDEN')['isMine'])->toBeFalse()
        ->and(collect($response->json('records'))->firstWhere('ref', 'OBF-HIDDEN')['can_view'])->toBeTrue()
        ->and(collect($response->json('records'))->firstWhere('ref', 'OBF-HIDDEN')['can_edit'])->toBeFalse();
});

test('a regular employee can read another employee trip ticket but cannot mutate or finance it', function () {
    $viewer = User::create([
        'name' => 'Read Only Viewer',
        'email' => 'read-only-viewer@example.test',
        'role' => 'Employee',
    ]);
    $owner = User::create([
        'name' => 'Trip Ticket Owner',
        'email' => 'trip-ticket-owner@example.test',
        'role' => 'Employee',
    ]);

    $ownerEmployee = createActivitiesObEmployee($owner, 'Trip', 'Ticket Owner');
    $company = Company::create(['company_name' => 'Read Only Client']);
    $trip = createActivitiesObTrip($ownerEmployee, [
        'workflow_version' => 3,
        'is_client_travel' => true,
    ]);
    $ticket = $trip->tripTickets()->create(['company_id' => $company->id]);
    $policy = app(\App\Policies\OfficialBusinessTripTicketPolicy::class);

    expect($policy->viewAny($viewer, $trip))->toBeTrue()
        ->and($policy->view($viewer, $ticket->fresh()))->toBeTrue()
        ->and($policy->update($viewer, $ticket->fresh()))->toBeFalse()
        ->and($policy->finance($viewer, $ticket->fresh()))->toBeFalse();
});

test('approved employee deletion soft deletes official business and preserves restore and force delete controls', function () {
    Notification::fake();

    $employeeUser = User::create([
        'name' => 'Employee Requester',
        'email' => 'employee-requester@example.test',
        'role' => 'Employee',
    ]);
    $admin = User::create([
        'name' => 'HC Administrator',
        'email' => 'hc-administrator@example.test',
        'role' => 'Admin',
    ]);

    $employee = createActivitiesObEmployee($employeeUser, 'Employee', 'Requester');
    $trip = createActivitiesObTrip($employee, [
        'ob_reference_no' => 'OBF-DELETE-APPROVAL',
        'status' => 'Pending',
    ]);

    $this->actingAs($employeeUser);

    $deleteRequest = \Illuminate\Http\Request::create(
        route('human-capital.obf.destroy', $trip),
        'DELETE'
    );
    $deleteRequest->setUserResolver(fn () => $employeeUser);

    $deleteResponse = app(\App\Http\Controllers\OfficialBusinessTripController::class)
        ->destroy($deleteRequest, $trip->fresh());

    expect($deleteResponse->getTargetUrl())->toBe(route('human-capital.obf'));

    $changeRequest = HumanCapitalChangeRequest::query()->sole();

    expect($changeRequest->action)->toBe('delete')
        ->and($changeRequest->status)->toBe('Pending Approval')
        ->and(OfficialBusinessTrip::query()->whereKey($trip->id)->exists())->toBeTrue();

    $this->actingAs($admin);

    $approvalResponse = app(\App\Http\Controllers\AdminHumanCapitalDashboardController::class)
        ->approveChangeRequest($changeRequest->fresh());

    expect($approvalResponse->getTargetUrl())->toBe(route('admin.human-capital.dashboard'));

    $this->assertSoftDeleted('official_business_trips', ['id' => $trip->id]);
    $this->assertDatabaseHas('human_capital_change_requests', [
        'id' => $changeRequest->id,
        'status' => 'Approved',
        'reviewed_by' => $admin->id,
    ]);
    $this->assertDatabaseHas('human_capital_logs', [
        'module' => 'Official Business Trip Form',
        'action' => 'delete_approved',
        'subject_type' => OfficialBusinessTrip::class,
        'subject_id' => $trip->id,
    ]);

    expect(OfficialBusinessTrip::query()->whereKey($trip->id)->exists())->toBeFalse()
        ->and(OfficialBusinessTrip::onlyTrashed()->whereKey($trip->id)->exists())->toBeTrue();

    $this->actingAs($employeeUser)
        ->getJson('/api/official-business')
        ->assertOk()
        ->assertJsonCount(0, 'records');

    $this->actingAs($admin)
        ->getJson('/api/official-business')
        ->assertOk()
        ->assertJsonCount(0, 'records');

    $this->getJson('/api/official-business/deleted')
        ->assertOk()
        ->assertJsonCount(1, 'records')
        ->assertJsonPath('records.0.id', $trip->id)
        ->assertJsonPath('records.0.ref', 'OBF-DELETE-APPROVAL');

    $this->postJson("/api/official-business/{$trip->id}/restore")
        ->assertOk()
        ->assertJsonPath('record.id', $trip->id);

    expect(OfficialBusinessTrip::query()->whereKey($trip->id)->exists())->toBeTrue()
        ->and(OfficialBusinessTrip::onlyTrashed()->whereKey($trip->id)->exists())->toBeFalse();

    OfficialBusinessTrip::query()->findOrFail($trip->id)->delete();

    $this->deleteJson("/api/official-business/{$trip->id}/force")
        ->assertOk();

    expect(OfficialBusinessTrip::withTrashed()->whereKey($trip->id)->exists())->toBeFalse();
});

test('human capital change request preserves an explicit subject id', function () {
    $user = User::create([
        'name' => 'Subject ID Probe',
        'email' => 'subject-id-probe@example.test',
        'role' => 'Admin',
    ]);

    $employee = createActivitiesObEmployee($user, 'Subject', 'Probe');
    $trip = createActivitiesObTrip($employee, [
        'ob_reference_no' => 'OBF-SUBJECT-ID-PROBE',
        'status' => 'Draft',
    ]);

    expect($trip->id)->not->toBeNull()
        ->and($trip->getKey())->toBe($trip->id);

    $changeRequest = HumanCapitalChangeRequest::create([
        'module' => 'Official Business Trip Form',
        'action' => 'delete',
        'subject_type' => OfficialBusinessTrip::class,
        'subject_id' => $trip->getKey(),
        'subject_name' => $trip->ob_reference_no,
        'status' => 'Pending Approval',
        'requested_by' => $user->id,
        'requested_by_name' => $user->name,
        'requested_at' => now(),
    ]);

    expect($changeRequest->subject_id)->toBe($trip->id)
        ->and($changeRequest->fresh()->subject_id)->toBe($trip->id);

    $this->assertDatabaseHas('human_capital_change_requests', [
        'id' => $changeRequest->id,
        'subject_type' => OfficialBusinessTrip::class,
        'subject_id' => $trip->id,
    ]);
});

test('activities delete action submits the existing approval workflow as json', function () {
    Notification::fake();

    $employeeUser = User::create([
        'name' => 'Activities Delete Requester',
        'email' => 'activities-delete@example.test',
        'role' => 'Employee',
    ]);

    \Illuminate\Support\Facades\DB::table('user_permissions')->insert([
        'user_id' => $employeeUser->id,
        'access_hc_obf' => true,
        'access_hc_my_hc' => true,
        'access_activities' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $employee = createActivitiesObEmployee($employeeUser, 'Activities', 'Requester');
    $trip = createActivitiesObTrip($employee, [
        'ob_reference_no' => 'OBF-ACTIVITIES-DELETE',
        'workflow_version' => 3,
        'status' => 'Draft',
    ]);

    expect($trip->id)->not->toBeNull()
        ->and($trip->getKey())->toBe($trip->id)
        ->and($trip->fresh()?->getKey())->toBe($trip->id);

    $this->withMiddleware();

    $this->actingAs($employeeUser)
        ->deleteJson(route('activities.official-business.destroy', $trip))
        ->assertStatus(202)
        ->assertJsonPath('message', 'Official Business deletion submitted for admin approval.')
        ->assertJsonPath('change_request_id', 1);

    $this->assertDatabaseHas('human_capital_change_requests', [
        'subject_type' => OfficialBusinessTrip::class,
        'subject_id' => $trip->id,
        'action' => 'delete',
        'status' => 'Pending Approval',
    ]);
    expect($trip->fresh())->not->toBeNull();
});



test('approved official business records can depart and return idempotently using actual travel time', function () {
    \Carbon\Carbon::setTestNow('2026-08-26 08:00:00');

    $user = User::create([
        'name' => 'Travel User',
        'email' => 'travel-user@example.test',
        'role' => 'Employee',
    ]);

    \Illuminate\Support\Facades\DB::table('user_permissions')->insert([
        'user_id' => $user->id,
        'access_hc_obf' => false,
        'access_hc_my_hc' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $employee = createActivitiesObEmployee($user, 'Travel', 'User');

    $trip = createActivitiesObTrip($employee, [
        'status' => 'Approved',
    ]);

    $resolvedEmployee = Employee::with('department')
        ->where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->orWhere('email', $user->email)
                ->orWhere('work_email', $user->email)
                ->orWhere('company_email', $user->email);
        })
        ->first();

    expect($employee->user_id)->toBe($user->id)
        ->and($employee->work_email)->toBe($user->email)
        ->and($trip->employee_id)->toBe($employee->id)
        ->and($trip->created_by)->toBe($user->id)
        ->and($resolvedEmployee)->not->toBeNull()
        ->and($resolvedEmployee?->id)->toBe($employee->id);

    $this->actingAs($user);

    $this->assertAuthenticatedAs($user);

    expect(\Illuminate\Support\Facades\Auth::id())->toBe($user->id);

    $controller = app(\App\Http\Controllers\OfficialBusinessTripController::class);

    $departResponse = $controller->depart($trip->fresh());

    expect($departResponse->getStatusCode())->toBe(200)
        ->and($departResponse->getData(true)['record']['status'])->toBe('In Progress');

    $trip->refresh();

    expect($trip->actual_departure?->format('Y-m-d H:i:s'))
        ->toBe('2026-08-26 08:00:00')
        ->and($trip->actual_return)->toBeNull()
        ->and($trip->duration_minutes)->toBeNull();

    \Carbon\Carbon::setTestNow('2026-08-26 08:30:00');

    $resolvedEmployee = Employee::with('department')
        ->where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->orWhere('email', $user->email)
                ->orWhere('work_email', $user->email)
                ->orWhere('company_email', $user->email);
        })
        ->first();

    expect($employee->user_id)->toBe($user->id)
        ->and($employee->work_email)->toBe($user->email)
        ->and($trip->employee_id)->toBe($employee->id)
        ->and($trip->created_by)->toBe($user->id)
        ->and($resolvedEmployee)->not->toBeNull()
        ->and($resolvedEmployee?->id)->toBe($employee->id);

    $repeatDepartResponse = $controller->depart($trip->fresh());

    expect($repeatDepartResponse->getStatusCode())->toBe(200);

    $trip->refresh();

    expect($trip->actual_departure?->format('Y-m-d H:i:s'))
        ->toBe('2026-08-26 08:00:00');

    \Carbon\Carbon::setTestNow('2026-08-26 09:20:00');

    $returnResponse = $controller->returnTrip($trip->fresh());

    expect($returnResponse->getStatusCode())->toBe(200)
        ->and($returnResponse->getData(true)['record']['status'])->toBe('Completed');

    $trip->refresh();

    expect($trip->actual_return?->format('Y-m-d H:i:s'))
        ->toBe('2026-08-26 09:20:00')
        ->and($trip->duration_minutes)->toBe(80);

    \Carbon\Carbon::setTestNow('2026-08-26 10:00:00');

    $repeatReturnResponse = $controller->returnTrip($trip->fresh());

    expect($repeatReturnResponse->getStatusCode())->toBe(200);

    $trip->refresh();

    expect($trip->actual_return?->format('Y-m-d H:i:s'))
        ->toBe('2026-08-26 09:20:00')
        ->and($trip->duration_minutes)->toBe(80);

    $activity = app(\App\Services\OfficialBusinessActivityService::class)
        ->toUnifiedActivity($trip);

    expect($activity['id'])->toBe('official-business-travel-'.$trip->id)
        ->and($activity['duration_minutes'])->toBe(80)
        ->and($activity['source_status'])->toBe('Completed');

    \Carbon\Carbon::setTestNow();
});

test('admin can restore a soft deleted official business record', function () {
    $admin = User::create([
        'name' => 'Restore Administrator',
        'email' => 'restore-admin@example.test',
        'role' => 'Admin',
    ]);

    $employee = createActivitiesObEmployee(
        $admin,
        'Restore',
        'Administrator'
    );

    $trip = createActivitiesObTrip($employee, [
        'ob_reference_no' => 'OBF-RESTORE-TEST',
        'status' => 'Draft',
    ]);

    $trip->delete();

    $this->assertSoftDeleted('official_business_trips', [
        'id' => $trip->id,
    ]);

    $response = $this
        ->actingAs($admin)
        ->post(route('activities.official-business.restore', [
            'id' => $trip->id,
        ]));

    $response->assertSuccessful();

    expect(
        OfficialBusinessTrip::query()->find($trip->id)
    )->not->toBeNull();
});

test('admin can permanently delete a soft deleted official business without trip tickets', function () {
    $admin = User::create([
        'name' => 'Permanent Delete Administrator',
        'email' => 'permanent-delete-admin@example.test',
        'role' => 'Admin',
    ]);

    $employee = createActivitiesObEmployee(
        $admin,
        'Permanent',
        'Administrator'
    );

    $trip = createActivitiesObTrip($employee, [
        'ob_reference_no' => 'OBF-FORCE-DELETE-TEST',
        'status' => 'Draft',
    ]);

    $trip->delete();

    $response = $this
        ->actingAs($admin)
        ->delete(route('activities.official-business.force-delete', [
            'id' => $trip->id,
        ]));

    $response->assertSuccessful();

    expect(
        OfficialBusinessTrip::withTrashed()->find($trip->id)
    )->toBeNull();
});

test('non admin cannot restore or permanently delete a soft deleted official business', function () {
    $employeeUser = User::create([
        'name' => 'Deleted Record Employee',
        'email' => 'deleted-record-employee@example.test',
        'role' => 'Employee',
    ]);

    $employee = createActivitiesObEmployee(
        $employeeUser,
        'Deleted',
        'Employee'
    );

    $trip = createActivitiesObTrip($employee, [
        'ob_reference_no' => 'OBF-DELETED-AUTH-TEST',
        'status' => 'Draft',
    ]);

    $trip->delete();

    $restore = $this
        ->actingAs($employeeUser)
        ->post(route('activities.official-business.restore', [
            'id' => $trip->id,
        ]));

    $forceDelete = $this
        ->actingAs($employeeUser)
        ->delete(route('activities.official-business.force-delete', [
            'id' => $trip->id,
        ]));

    $restore->assertForbidden();
    $forceDelete->assertForbidden();

    expect(
        OfficialBusinessTrip::withTrashed()->find($trip->id)
    )->not->toBeNull();
});
