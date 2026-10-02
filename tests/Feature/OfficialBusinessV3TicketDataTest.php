<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\ActivityAuditLog;
use App\Models\OfficialBusinessTrip;
use App\Models\OfficialBusinessTripTicket;
use App\Models\User;
use App\Services\OfficialBusinessCompletionService;
use App\Services\OfficialBusinessTripTicketService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
        throw new RuntimeException('O.B. V3 ticket data tests require SQLite :memory:.');
    }

    $this->withoutMiddleware([
        \Laravel\Boost\Middleware\InjectBoost::class,
        \App\Http\Middleware\PreventBackHistory::class,
        \App\Http\Middleware\EnsurePasswordChanged::class,
        \App\Http\Middleware\HumanCapitalModuleAccess::class,
    ]);

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password')->nullable();
        $table->string('role');
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
    Schema::create('departments', function (Blueprint $table): void {
        $table->id();
        $table->string('department_name');
        $table->timestamps();
    });
    Schema::create('employees', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('user_id')->nullable();
        $table->foreignId('department_id')->nullable();
        $table->string('employee_code')->nullable();
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('position')->nullable();
        $table->uuid('digital_id_token')->nullable();
        $table->string('verification_reference')->nullable();
        $table->string('employment_status')->nullable();
        $table->date('digital_id_issued_at')->nullable();
        $table->string('payroll_type')->nullable();
        $table->timestamps();
    });
    Schema::create('companies', function (Blueprint $table): void {
        $table->id();
        $table->string('company_name');
        $table->foreignId('primary_contact_id')->nullable();
        $table->timestamps();
    });
    Schema::create('contacts', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('user_id')->nullable();
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('email')->nullable();
        $table->timestamps();
    });
    Schema::create('company_contact', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('company_id');
        $table->foreignId('contact_id');
        $table->timestamps();
        $table->unique(['company_id', 'contact_id']);
    });
    Schema::create('user_permissions', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('user_id');
        $table->boolean('access_activities')->default(false);
        $table->boolean('access_hc_obf')->default(false);
    });
    Schema::create('role_permissions', function (Blueprint $table): void {
        $table->id();
        $table->string('role');
        $table->boolean('access_hc_obf')->default(false);
    });
    Schema::create('activity_attachments', function (Blueprint $table): void {
        $table->id();
        $table->string('activity_type', 40);
        $table->unsignedBigInteger('activity_id');
        $table->string('filename');
        $table->string('original_filename')->nullable();
        $table->string('storage_disk', 40)->nullable();
        $table->string('storage_path')->nullable();
        $table->text('external_url')->nullable();
        $table->string('mime_type')->nullable();
        $table->unsignedBigInteger('size_bytes')->nullable();
        $table->string('category', 60)->nullable();
        $table->foreignId('uploaded_by_user_id')->nullable();
        $table->timestamp('uploaded_at')->nullable();
        $table->json('metadata')->nullable();
        $table->timestamps();
    });
    Schema::create('activity_audit_logs', function (Blueprint $table): void {
        $table->id();
        $table->string('activity_type', 40);
        $table->unsignedBigInteger('activity_id')->nullable();
        $table->string('action', 80);
        $table->foreignId('user_id')->nullable();
        $table->json('changes')->nullable();
        $table->json('metadata')->nullable();
        $table->timestamps();
    });

    (require database_path('migrations/2026_05_28_165518_create_notifications_table.php'))->up();

    (require database_path('migrations/2026_05_11_160306_create_official_business_trips_table.php'))->up();
    Schema::table('official_business_trips', function (Blueprint $table): void {
        $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
    });
    (require database_path('migrations/2026_08_26_112720_add_actual_travel_timestamps_to_official_business_trips_table.php'))->up();
    (require database_path('migrations/2026_08_26_130000_add_soft_deletes_to_official_business_trips_table.php'))->up();
    (require database_path('migrations/2026_09_16_160000_add_official_business_v3_domain_foundation.php'))->up();
    (require database_path('migrations/2026_09_17_110100_add_official_business_v3_ticket_details.php'))->up();
    (require database_path('migrations/2026_09_17_120000_add_official_business_v3_ticket_completion_data.php'))->up();
    (require database_path('migrations/2026_09_21_000001_add_archival_metadata_to_official_business_trip_tickets.php'))->up();
    (require database_path('migrations/2026_09_21_000002_create_company_travel_credit_ledger.php'))->up();

    $departmentId = DB::table('departments')->insertGetId([
        'department_name' => 'Operations',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $this->owner = User::create([
        'name' => 'Ticket Owner',
        'email' => 'ticket-owner@example.test',
        'role' => 'Employee',
    ]);
    DB::table('user_permissions')->insert([
        'user_id' => $this->owner->id,
        'access_activities' => true,
        'access_hc_obf' => false,
    ]);
    $this->employee = Employee::create([
        'user_id' => $this->owner->id,
        'department_id' => $departmentId,
        'employee_code' => 'EMP-OBTT-1',
        'first_name' => 'Ticket',
        'last_name' => 'Owner',
        'position' => 'Associate',
    ]);
    $this->clientA = Company::create(['company_name' => 'Client A']);
    $this->clientB = Company::create(['company_name' => 'Client B']);
    $this->master = OfficialBusinessTrip::create([
        'ob_reference_no' => 'OBF-V3-TICKET-DATA',
        'employee_id' => $this->employee->id,
        'employee_name' => $this->employee->full_name,
        'destination' => 'BIR Cebu',
        'purpose' => 'Client filing',
        'trip_type' => 'Roundtrip',
        'date_from' => '2026-09-17',
        'is_client_travel' => true,
        'company_id' => $this->clientA->id,
        'workflow_version' => 3,
        'status' => 'Draft',
        'created_by' => $this->owner->id,
    ]);
    $this->service = app(OfficialBusinessTripTicketService::class);
});

test('ticket stores structured client stops and stable personnel snapshots', function () {
    $ticket = $this->service->forClient($this->owner, $this->master, $this->clientA);

    $this->service->updateDetails($this->owner, $ticket, [
        ['sequence' => 1, 'destination' => 'BIR Cebu', 'activity' => 'Filing', 'resolution_status' => 'Resolved'],
        ['sequence' => 2, 'destination' => 'Client Office', 'activity' => 'Acknowledgment', 'resolution_status' => 'Planned'],
    ], [
        ['employee_id' => $this->employee->id, 'role' => 'Filer', 'participation_status' => 'Confirmed'],
    ]);

    $ticket->load(['stops', 'personnel']);

    expect($ticket->stops)->toHaveCount(2)
        ->and($ticket->stops->pluck('sequence')->all())->toBe([1, 2])
        ->and($ticket->stops->first()->destination)->toBe('BIR Cebu')
        ->and($ticket->personnel)->toHaveCount(1)
        ->and($ticket->personnel->first()->employee_id)->toBe($this->employee->id)
        ->and($ticket->personnel->first()->employee_name_snapshot)->toBe('Ticket Owner')
        ->and($ticket->personnel->first()->position_snapshot)->toBe('Associate')
        ->and($ticket->personnel->first()->department_snapshot)->toBe('Operations');
});

test('client ticket personnel must belong to the structured master team', function () {
    (require database_path('migrations/2026_09_28_000002_create_official_business_master_personnel.php'))->up();
    app(\App\Services\OfficialBusinessMasterPersonnelService::class)->sync($this->master, []);
    $outsider = Employee::create(['first_name' => 'Outside', 'last_name' => 'Traveler']);
    $ticket = $this->service->forClient($this->owner, $this->master, $this->clientA);

    expect(fn () => $this->service->updateDetails($this->owner, $ticket, null, [
        ['employee_id' => $outsider->id],
    ]))->toThrow(ValidationException::class);

    $this->service->updateDetails($this->owner, $ticket, null, [
        ['employee_id' => $this->employee->id],
    ]);
    expect($ticket->personnel()->pluck('employee_id')->all())->toBe([$this->employee->id]);

    app(\App\Services\OfficialBusinessMasterPersonnelService::class)
        ->sync($this->master, [$outsider->id]);
    $this->service->updateDetails($this->owner, $ticket, null, [
        ['employee_id' => $outsider->id],
    ]);
    expect(fn () => app(\App\Services\OfficialBusinessMasterPersonnelService::class)
        ->sync($this->master, []))->toThrow(ValidationException::class);
});

test('two clients create distinct tickets and repeated synchronization is idempotent', function () {
    $first = $this->service->syncClients($this->owner, $this->master, [$this->clientA->id, $this->clientB->id]);
    $second = $this->service->syncClients($this->owner, $this->master, [$this->clientB->id, $this->clientA->id, $this->clientA->id]);

    expect($first)->toHaveCount(2)
        ->and($second)->toHaveCount(2)
        ->and($this->master->tripTickets()->count())->toBe(2)
        ->and($first->pluck('reference_no')->sort()->values()->all())
        ->toBe($second->pluck('reference_no')->sort()->values()->all());
});

test('removing an unworked client archives its ticket and readding restores the same identity', function () {
    [$ticketA, $ticketB] = $this->service
        ->syncClients($this->owner, $this->master, [$this->clientA->id, $this->clientB->id])
        ->all();

    $this->service->syncClients($this->owner, $this->master, [$this->clientA->id]);
    $archived = OfficialBusinessTripTicket::withTrashed()->findOrFail($ticketB->id);

    expect($this->master->tripTickets()->pluck('id')->all())->toBe([$ticketA->id])
        ->and($archived->trashed())->toBeTrue()
        ->and($archived->status)->toBe('Cancelled')
        ->and($archived->archived_reason)->toBe('client_removed_from_master');

    $restored = $this->service
        ->syncClients($this->owner, $this->master, [$this->clientA->id, $this->clientB->id])
        ->last();

    expect($restored->id)->toBe($ticketB->id)
        ->and($restored->reference_no)->toBe($ticketB->reference_no)
        ->and($restored->status)->toBe('Draft')
        ->and($restored->trashed())->toBeFalse()
        ->and(OfficialBusinessTripTicket::withTrashed()->count())->toBe(2);
});

test('client removal is rejected after meaningful ticket work', function () {
    [, $ticketB] = $this->service
        ->syncClients($this->owner, $this->master, [$this->clientA->id, $this->clientB->id])
        ->all();
    $this->service->updateDetails($this->owner, $ticketB, [[
        'sequence' => 1,
        'destination' => 'SEC Cebu',
        'activity' => 'Submission',
        'resolution_status' => 'Resolved',
        'resolved_at' => now(),
    ]], null);

    expect(fn () => $this->service->syncClients($this->owner, $this->master, [$this->clientA->id]))
        ->toThrow(ValidationException::class)
        ->and(OfficialBusinessTripTicket::find($ticketB->id))->not->toBeNull();
});

test('archived ticket identity is not recreated by synchronization', function () {
    $ticket = $this->service->forClient($this->owner, $this->master, $this->clientA);
    $ticket->delete();

    expect(fn () => $this->service->syncClients($this->owner, $this->master, [$this->clientA->id]))
        ->toThrow(ValidationException::class)
        ->and(OfficialBusinessTripTicket::withTrashed()->count())->toBe(1);
});

test('duplicate master client pairs remain rejected by the database', function () {
    $this->service->forClient($this->owner, $this->master, $this->clientA);

    expect(fn () => DB::table('official_business_trip_tickets')->insert([
        'official_business_trip_id' => $this->master->id,
        'company_id' => $this->clientA->id,
        'reference_no' => 'OBTT-DUPLICATE',
        'client_name_snapshot' => 'Duplicate',
        'status' => 'Draft',
    ]))->toThrow(QueryException::class);
});

test('company and employee renames preserve ticket identity snapshots', function () {
    $ticket = $this->service->forClient($this->owner, $this->master, $this->clientA);
    $this->service->updateDetails($this->owner, $ticket, [], [
        ['employee_id' => $this->employee->id, 'role' => 'Traveler', 'participation_status' => 'Assigned'],
    ]);
    $reference = $ticket->reference_no;

    $this->clientA->update(['company_name' => 'Renamed Client']);
    $this->employee->update(['first_name' => 'Renamed']);

    expect($ticket->fresh()->reference_no)->toBe($reference)
        ->and($ticket->fresh()->client_name_snapshot)->toBe('Client A')
        ->and($ticket->personnel()->first()->employee_name_snapshot)->toBe('Ticket Owner');
});

test('unauthorized and client users cannot mutate ticket details', function () {
    $ticket = $this->service->forClient($this->owner, $this->master, $this->clientA);
    $stranger = User::create(['name' => 'Stranger', 'email' => 'stranger-obtt@example.test', 'role' => 'Employee']);
    $clientUser = User::create(['name' => 'Client User', 'email' => 'client-obtt@example.test', 'role' => 'Client']);

    foreach ([$stranger, $clientUser] as $actor) {
        expect(fn () => $this->service->updateDetails($actor, $ticket, [
            ['sequence' => 1, 'destination' => 'Unauthorized'],
        ], []))->toThrow(AuthorizationException::class);
    }

    expect($ticket->stops()->count())->toBe(1)
        ->and($ticket->stops()->sole()->destination)->toBe('BIR Cebu');
});

test('automatic master client synchronization seeds only stable v3 data', function () {
    $ticket = $this->service->syncCurrentMasterClient($this->owner, $this->master);

    expect(Gate::forUser($this->owner)->allows(
        'create',
        [OfficialBusinessTripTicket::class, $this->master]
    ))->toBeTrue();

    expect($ticket)->not->toBeNull()
        ->and($ticket->company_id)->toBe($this->clientA->id)
        ->and($ticket->stops()->sole()->destination)->toBe('BIR Cebu')
        ->and($ticket->personnel()->sole()->employee_id)->toBe($this->employee->id);

    $this->service->syncCurrentMasterClient($this->owner, $this->master);
    expect($this->master->tripTickets()->count())->toBe(1)
        ->and($ticket->stops()->count())->toBe(1)
        ->and($ticket->personnel()->count())->toBe(1);
});

test('legacy v1 masters are not changed or automatically ticketed', function () {
    $legacy = OfficialBusinessTrip::create([
        'ob_reference_no' => 'OBF-V1-UNCHANGED',
        'employee_id' => $this->employee->id,
        'employee_name' => $this->employee->full_name,
        'destination' => 'Legacy Stop',
        'purpose' => 'Legacy Purpose',
        'date_from' => '2026-09-17',
        'is_client_travel' => true,
        'company_id' => $this->clientA->id,
        'workflow_version' => 1,
        'status' => 'Pending',
        'created_by' => $this->owner->id,
    ]);

    expect($this->service->syncCurrentMasterClient($this->owner, $legacy))->toBeNull()
        ->and($legacy->tripTickets()->count())->toBe(0)
        ->and($legacy->fresh()->destination)->toBe('Legacy Stop')
        ->and($legacy->fresh()->status)->toBe('Pending');
});

test('phase 3a completion remains blocked until active structured tickets are resolved', function () {
    $this->master->forceFill([
        'status' => 'For Completion',
        'actual_departure' => now()->subHour(),
        'actual_return' => now()->subMinutes(10),
    ])->save();
    $ticket = $this->service->syncCurrentMasterClient($this->owner, $this->master);
    $completion = app(OfficialBusinessCompletionService::class);

    expect($completion->isReady($completion->checklist($this->master->fresh())))->toBeFalse();

    $ticket->forceFill(['status' => 'Completed'])->save();

    expect($completion->isReady($completion->checklist($this->master->fresh())))->toBeTrue();
});

test('ticket api lists views updates and soft deletes with policy enforcement', function () {
    $ticket = $this->service->syncCurrentMasterClient($this->owner, $this->master);

    $indexResponse = $this->actingAs($this->owner)
        ->getJson(route('activities.official-business.trip-tickets.index', $this->master));

    if ($indexResponse->status() !== 200) {
        throw new RuntimeException(json_encode($indexResponse->json(), JSON_PRETTY_PRINT));
    }

    $indexResponse
        ->assertOk()
        ->assertJsonPath('tickets.0.id', $ticket->id)
        ->assertJsonPath('tickets.0.permissions.update', true)
        ->assertJsonPath('tickets.0.permissions.finance', false)
        ->assertJsonPath('tickets.0.permissions.delete', true);

    $this->getJson(route('activities.official-business.trip-tickets.show', $ticket))
        ->assertOk()
        ->assertJsonPath('ticket.reference_no', $ticket->reference_no)
        ->assertJsonPath('ticket.permissions.update', true)
        ->assertJsonPath('ticket.permissions.finance', false);

    $this->putJson(route('activities.official-business.trip-tickets.update', $ticket), [
        'stops' => [
            ['sequence' => 1, 'destination' => 'Updated Client Stop', 'activity' => 'Submission'],
        ],
        'personnel' => [
            ['employee_id' => $this->employee->id, 'role' => 'Filer', 'participation_status' => 'Confirmed'],
        ],
    ])->assertOk()->assertJsonPath('ticket.stops.0.destination', 'Updated Client Stop');

    $stranger = User::create(['name' => 'API Stranger', 'email' => 'api-stranger@example.test', 'role' => 'Employee']);
    $this->actingAs($stranger)
        ->putJson(route('activities.official-business.trip-tickets.update', $ticket), [
            'stops' => [['sequence' => 1, 'destination' => 'Blocked']],
            'personnel' => [],
        ])
        ->assertForbidden();

    $this->actingAs($this->owner)
        ->deleteJson(route('activities.official-business.trip-tickets.destroy', $ticket))
        ->assertOk();

    $archivedTicket = OfficialBusinessTripTicket::withTrashed()
        ->find($ticket->id);

    expect(OfficialBusinessTripTicket::find($ticket->id))->toBeNull()
        ->and($archivedTicket)->not->toBeNull()
        ->and($archivedTicket->status)->toBe('Cancelled')
        ->and($archivedTicket->trashed())->toBeTrue();
});

test('ticket personnel visibility is isolated to each ticket while active employees retain read access', function () {
    [$ticketA, $ticketB] = $this->service
        ->syncClients($this->owner, $this->master, [$this->clientA->id, $this->clientB->id])
        ->all();
    $participant = User::create([
        'name' => 'Client A Personnel',
        'email' => 'client-a-personnel@example.test',
        'role' => 'Employee',
    ]);
    $participantEmployee = Employee::create([
        'user_id' => $participant->id,
        'employee_code' => 'EMP-OBTT-P',
        'first_name' => 'Client A',
        'last_name' => 'Personnel',
    ]);
    $this->service->updateDetails($this->owner, $ticketA, null, [[
        'employee_id' => $participantEmployee->id,
        'role' => 'Runner',
        'participation_status' => 'Confirmed',
    ]]);

    $this->actingAs($participant)
        ->getJson(route('activities.official-business.trip-tickets.index', $this->master))
        ->assertOk()
        ->assertJsonCount(2, 'tickets')
        ->assertJsonPath('tickets.0.id', $ticketA->id)
        ->assertJsonMissingPath('tickets.0.financial_treatment')
        ->assertJsonMissingPath('tickets.0.costs');
    $this->getJson(route('activities.official-business.index'))
        ->assertOk()
        ->assertJsonCount(1, 'records')
        ->assertJsonPath('records.0.type', 'Multiple Clients')
        ->assertJsonPath('records.0.trip_type', 'Roundtrip')
        ->assertJsonCount(2, 'records.0.trip_tickets')
        ->assertJsonPath('records.0.trip_tickets.0.id', $ticketA->id)
        ->assertJsonPath('records.0.trip_tickets.1.id', $ticketB->id);
    $this->getJson(route('activities.official-business.trip-tickets.show', $ticketB))
        ->assertOk()
        ->assertJsonMissingPath('financial_treatment')
        ->assertJsonMissingPath('costs');
    $this->putJson(route('activities.official-business.trip-tickets.update', $ticketA), [
        'stops' => [],
    ])->assertForbidden();
});

test('client account sees only its completed trip ticket and cannot mutate it', function () {
    [$ticketA, $ticketB] = $this->service
        ->syncClients($this->owner, $this->master, [$this->clientA->id, $this->clientB->id])
        ->all();
    $ticketA->forceFill(['status' => 'Completed'])->save();
    $ticketB->forceFill(['status' => 'Completed'])->save();
    $clientUser = User::create([
        'name' => 'Client A Account',
        'email' => 'client-a-account@example.test',
        'role' => 'Client',
    ]);
    $contactId = DB::table('contacts')->insertGetId([
        'user_id' => $clientUser->id,
        'first_name' => 'Client A',
        'last_name' => 'Account',
        'email' => $clientUser->email,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('company_contact')->insert([
        'company_id' => $this->clientA->id,
        'contact_id' => $contactId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($clientUser)
        ->getJson(route('activities.official-business.trip-tickets.index', $this->master))
        ->assertOk()
        ->assertJsonCount(1, 'tickets')
        ->assertJsonPath('tickets.0.id', $ticketA->id);
    $this->getJson(route('activities.official-business.trip-tickets.show', $ticketA))
        ->assertOk();
    $this->getJson(route('activities.official-business.trip-tickets.show', $ticketB))
        ->assertForbidden();
    $this->putJson(route('activities.official-business.trip-tickets.update', $ticketA), [
        'stops' => [],
    ])->assertForbidden();

    $clientResponse = $this->getJson(route('client.official-business.trip-tickets.index'))
        ->assertOk()
        ->assertJsonCount(1, 'tickets')
        ->assertJsonPath('tickets.0.id', $ticketA->id)
        ->assertJsonPath('tickets.0.client_name_snapshot', 'Client A');
    expect($clientResponse->json('tickets.0'))->not->toHaveKeys(['financial_treatment', 'costs']);
    expect($clientResponse->json('tickets.0.trip'))->not->toHaveKeys(['purpose', 'destination']);

    $this->get(route('client.official-business.trip-tickets.page'))
        ->assertOk()
        ->assertSee($ticketA->reference_no)
        ->assertDontSee($ticketB->reference_no)
        ->assertDontSee('Billing treatment');

    $this->getJson(route('client.official-business.trip-tickets.show', $ticketB))
        ->assertForbidden();
    $this->get(route('client.official-business.trip-tickets.pdf', $ticketB))
        ->assertForbidden();
    $this->get(route('official-business-trip-tickets.verify', $ticketB->reference_no))
        ->assertForbidden();
    $this->get(route('client.official-business.trip-tickets.pdf', $ticketA))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('ticket audit hides finance events and raw changes from non finance viewers', function () {
    $ticket = $this->service->syncClients($this->owner, $this->master, [$this->clientA->id])->sole();
    ActivityAuditLog::create([
        'activity_type' => 'OFFICIAL_BUSINESS_TRIP_TICKET',
        'activity_id' => $ticket->id,
        'action' => 'billing_treatment_updated',
        'user_id' => $this->owner->id,
        'metadata' => ['details' => ['billing_treatment' => 'Billable']],
    ]);
    ActivityAuditLog::create([
        'activity_type' => 'OFFICIAL_BUSINESS_TRIP_TICKET',
        'activity_id' => $ticket->id,
        'action' => 'stops_updated',
        'user_id' => $this->owner->id,
        'changes' => ['after' => ['amount' => 125]],
        'metadata' => ['details' => ['amount' => 125]],
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson("/api/activities/official-business-trip-ticket/{$ticket->id}/audit-log")
        ->assertOk()
        ->assertJsonPath('audit_log.0.action', 'stops_updated')
        ->assertJsonPath('audit_log.0.changes', null)
        ->assertJsonPath('audit_log.0.details', null);
    expect($response->getContent())->not->toContain('Billable', '125');
});

test('ticket pdf history and public verification stay scoped to one completed ticket', function () {
    [$ticketA, $ticketB] = $this->service
        ->syncClients($this->owner, $this->master, [$this->clientA->id, $this->clientB->id])
        ->all();
    $ticketA->forceFill(['status' => 'Completed'])->save();
    ActivityAuditLog::create([
        'activity_type' => 'OFFICIAL_BUSINESS_TRIP_TICKET',
        'activity_id' => $ticketA->id,
        'action' => 'completed',
        'user_id' => $this->owner->id,
    ]);
    ActivityAuditLog::create([
        'activity_type' => 'OFFICIAL_BUSINESS_TRIP_TICKET',
        'activity_id' => $ticketB->id,
        'action' => 'other_client_event',
        'user_id' => $this->owner->id,
    ]);

    $this->actingAs($this->owner)
        ->get(route('activities.official-business.trip-tickets.pdf', $ticketA))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
    $this->get(route('activities.official-business.trip-tickets.history-pdf', $ticketA))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $this->get(route('official-business-trip-tickets.verify', $ticketA->reference_no))
        ->assertOk()
        ->assertViewIs('public.official-business-trip-ticket-verification')
        ->assertSee('Official Business Trip Ticket Verification')
        ->assertSee('Verified')
        ->assertSee($ticketA->reference_no)
        ->assertSee('Client A')
        ->assertDontSee('Client B');

    $this->get(route('official-business-trip-tickets.verify', $ticketB->reference_no))
        ->assertNotFound();

    auth()->logout();
    $this->get(route('official-business-trip-tickets.verify', $ticketA->reference_no))
        ->assertOk()
        ->assertSee('Verified')
        ->assertDontSee('Client A')
        ->assertDontSee('Personnel');
});

test('rendered client ticket PDFs isolate stops personnel finance and evidence', function () {
    $pdftotext = (new ExecutableFinder)->find('pdftotext');
    expect($pdftotext)->not->toBeNull();

    $this->clientA->update(['company_name' => 'PDF ISOLATION CLIENT ALPHA']);
    $this->clientB->update(['company_name' => 'PDF ISOLATION CLIENT BETA']);
    $this->master->update([
        'destination' => 'ALPHA DESTINATION ONLY',
        'purpose' => 'ALPHA ACTIVITY ONLY',
        'team_members' => ['ALPHA PERSON ONLY', 'BETA PERSON ONLY'],
    ]);
    [$ticketA, $ticketB] = $this->service
        ->syncClients($this->owner, $this->master, [$this->clientA->id, $this->clientB->id])
        ->all();

    foreach ([
        [$ticketA, 'ALPHA', '111.11', '1.00'],
        [$ticketB, 'BETA', '222.22', '2.00'],
    ] as [$ticket, $label, $cost, $credit]) {
        $employee = Employee::create([
            'first_name' => $label,
            'last_name' => 'PERSON ONLY',
            'position' => 'Traveler',
        ]);
        $this->service->updateDetails($this->owner, $ticket, [[
            'sequence' => 1,
            'destination' => $label.' DESTINATION ONLY',
            'activity' => $label.' ACTIVITY ONLY',
            'resolution_status' => 'Resolved',
        ]], [[
            'employee_id' => $employee->id,
            'role' => 'Traveler',
            'participation_status' => 'Confirmed',
        ]]);
        $ticket->financialTreatment()->create([
            'billing_treatment' => 'Travel Credit + Additional Cost',
            'travel_credit_units' => $credit,
            'travel_credit_value' => $cost,
            'additional_costs_status' => 'Resolved',
            'notes' => $label.' FINANCE SECRET',
        ]);
        $ticket->costs()->create([
            'category' => 'Parking',
            'description' => $label.' COST ONLY',
            'amount' => $cost,
            'billing_treatment' => 'Billable to Client',
        ]);
        \App\Models\ActivityAttachment::create([
            'activity_type' => 'OFFICIAL_BUSINESS_TRIP_TICKET',
            'activity_id' => $ticket->id,
            'filename' => strtolower($label).'-evidence.pdf',
            'original_filename' => $label.' EVIDENCE SECRET.pdf',
            'category' => 'Official Receipt',
            'uploaded_at' => now(),
        ]);
        $ticket->forceFill(['status' => 'Completed'])->save();
    }

    $financeUser = User::create([
        'name' => 'PDF Finance Admin',
        'email' => 'pdf-finance-admin@example.test',
        'role' => 'Admin',
    ]);
    $renderText = function (User $viewer, OfficialBusinessTripTicket $ticket, bool $history = false) use ($pdftotext): string {
        $response = $this->actingAs($viewer)
            ->get(route($history
                ? 'activities.official-business.trip-tickets.history-pdf'
                : 'activities.official-business.trip-tickets.pdf', $ticket))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $process = new Process([$pdftotext, '-layout', '-enc', 'UTF-8', '-', '-']);
        $process->setInput($response->getContent());
        $process->run();
        expect($process->isSuccessful())->toBeTrue();

        return $process->getOutput();
    };

    foreach ([
        [$ticketA, 'ALPHA', 'BETA', '111.11', '222.22'],
        [$ticketB, 'BETA', 'ALPHA', '222.22', '111.11'],
    ] as [$ticket, $own, $other, $ownCost, $otherCost]) {
        $financeText = $renderText($financeUser, $ticket);
        expect($financeText)
            ->toContain('PDF ISOLATION CLIENT '.$own)
            ->toContain($own.' DESTINATION ONLY')
            ->toContain($own.' ACTIVITY ONLY')
            ->toContain($own.' PERSON ONLY')
            ->toContain($own.' EVIDENCE SECRET.pdf')
            ->toContain($own.' COST ONLY')
            ->toContain($ownCost)
            ->not->toContain('PDF ISOLATION CLIENT '.$other)
            ->not->toContain($other.' DESTINATION ONLY')
            ->not->toContain($other.' ACTIVITY ONLY')
            ->not->toContain($other.' PERSON ONLY')
            ->not->toContain($other.' EVIDENCE SECRET')
            ->not->toContain($other.' COST ONLY')
            ->not->toContain($otherCost);

        $employeeText = $renderText($this->owner, $ticket);
        expect($employeeText)
            ->toContain($own.' DESTINATION ONLY')
            ->toContain($own.' PERSON ONLY')
            ->not->toContain('Billing treatment')
            ->not->toContain('Travel credits')
            ->not->toContain('Additional costs status')
            ->not->toContain($ownCost)
            ->not->toContain($otherCost)
            ->not->toContain($other.' EVIDENCE SECRET');

        $historyText = $renderText($financeUser, $ticket, true);
        expect($historyText)
            ->toContain('PDF ISOLATION CLIENT '.$own)
            ->toContain($own.' DESTINATION ONLY')
            ->toContain($own.' ACTIVITY ONLY')
            ->toContain($own.' PERSON ONLY')
            ->toContain($own.' EVIDENCE SECRET.pdf')
            ->toContain($ownCost)
            ->not->toContain('PDF ISOLATION CLIENT '.$other)
            ->not->toContain($other.' DESTINATION ONLY')
            ->not->toContain($other.' ACTIVITY ONLY')
            ->not->toContain($other.' PERSON ONLY')
            ->not->toContain($other.' EVIDENCE SECRET')
            ->not->toContain($otherCost);

        $employeeHistoryText = $renderText($this->owner, $ticket, true);
        expect($employeeHistoryText)
            ->toContain($own.' PERSON ONLY')
            ->not->toContain('Billing and Travel Credit')
            ->not->toContain('Travel Credit Ledger Events')
            ->not->toContain('Additional Costs')
            ->not->toContain($ownCost)
            ->not->toContain($otherCost);
    }
});

test('master official business history pdf renders for authorized user', function () {
    \App\Models\ActivityAuditLog::create([
        'activity_type' => \App\Services\ActivityTypeRegistry::OFFICIAL_BUSINESS_TRAVEL,
        'activity_id' => $this->master->id,
        'action' => 'created',
        'user_id' => $this->owner->id,
    ]);

    $this->actingAs($this->owner)
        ->get(route('activities.official-business.history-pdf', $this->master))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('trip ticket history pdf hides finance sections when finance access is denied', function () {
    $ticket = $this->service->forClient(
        $this->owner,
        $this->master,
        $this->clientA
    );

    $ticket->load([
        'officialBusinessTrip',
        'company',
        'personnel',
        'evidence.uploadedBy',
        'financialTreatment.travelCreditEntry',
        'costs.recordedBy',
        'travelCreditEntries.recordedBy',
    ]);

    $html = view(
        'human-capital.official-business-trip-ticket-history-pdf',
        [
            'ticket' => $ticket,
            'history' => collect(),
            'includeFinance' => false,
            'verificationUrl' => 'https://example.test/verify',
            'generatedAt' => now(),
        ]
    )->render();

    expect($html)
        ->toContain('Official Business Trip Ticket History')
        ->toContain('Personnel')
        ->toContain('Evidence References')
        ->toContain('Chronological History')
        ->not->toContain('Billing and Travel Credit')
        ->not->toContain('Travel Credit Ledger Events')
        ->not->toContain('Additional Costs');
});
test('history pdf views expose required audit sections', function () {
    $ticket = $this->service->forClient(
        $this->owner,
        $this->master,
        $this->clientA
    );

    $ticket->load([
        'officialBusinessTrip',
        'company',
        'personnel',
        'evidence.uploadedBy',
        'financialTreatment.travelCreditEntry',
        'costs.recordedBy',
        'travelCreditEntries.recordedBy',
    ]);

    $ticketHtml = view(
        'human-capital.official-business-trip-ticket-history-pdf',
        [
            'ticket' => $ticket,
            'history' => collect(),
            'includeFinance' => true,
            'verificationUrl' => 'https://example.test/verify',
            'generatedAt' => now(),
        ]
    )->render();

    expect($ticketHtml)
        ->toContain('Official Business Trip Ticket History')
        ->toContain('Personnel')
        ->toContain('Evidence References')
        ->toContain('Billing and Travel Credit')
        ->toContain('Travel Credit Ledger Events')
        ->toContain('Additional Costs')
        ->toContain('Chronological History')
        ->toContain('Generated');

    $master = $this->master->fresh()->load([
        'employee',
        'preparedBy',
        'company',
        'tripTickets.company',
        'tripTickets.personnel',
    ]);

    $masterHtml = view(
        'human-capital.official-business-history-pdf',
        [
            'trip' => $master,
            'history' => collect(),
            'generatedAt' => now(),
        ]
    )->render();

    expect($masterHtml)
        ->toContain('Official Business History')
        ->toContain('O.B. Reference')
        ->toContain('Employee')
        ->toContain('Client Trip Tickets')
        ->toContain('Chronological History')
        ->toContain('Generated');
});
