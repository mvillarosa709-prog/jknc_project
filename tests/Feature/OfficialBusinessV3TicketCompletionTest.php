<?php

use App\Models\ActivityAttachment;
use App\Models\Company;
use App\Models\Employee;
use App\Models\OfficialBusinessTrip;
use App\Models\OfficialBusinessTripTicket;
use App\Models\OfficialBusinessTripTicketCost;
use App\Models\User;
use App\Services\ActivityTypeRegistry;
use App\Services\CompanyTravelCreditService;
use App\Services\OfficialBusinessCompletionService;
use App\Services\OfficialBusinessTripTicketService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
        throw new RuntimeException('O.B. V3 ticket completion tests require SQLite :memory:.');
    }

    $this->withoutMiddleware([
        \Laravel\Boost\Middleware\InjectBoost::class,
        \App\Http\Middleware\PreventBackHistory::class,
        \App\Http\Middleware\EnsurePasswordChanged::class,
        \App\Http\Middleware\HumanCapitalModuleAccess::class,
    ]);
    Storage::fake('public');

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password')->nullable();
        $table->string('role');
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
    (require database_path('migrations/2026_05_28_165518_create_notifications_table.php'))->up();
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
        $table->timestamps();
    });
    Schema::create('user_permissions', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('user_id');
        $table->boolean('access_activities')->default(false);
        $table->boolean('access_hc_obf')->default(false);
        $table->boolean('create_hc_obf')->default(false);
        $table->boolean('approve_hc_obf')->default(false);
        $table->boolean('control_hc_obf_travel')->default(false);
        $table->boolean('manage_hc_obf')->default(false);
        $table->boolean('access_hc_obf_finance')->default(false);
        $table->boolean('admin_hc_obf')->default(false);
    });
    Schema::create('role_permissions', function (Blueprint $table): void {
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
        $table->index(['activity_type', 'activity_id']);
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

    (require database_path('migrations/2026_05_11_160306_create_official_business_trips_table.php'))->up();
    Schema::table('official_business_trips', function (Blueprint $table): void {
        $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
    });
    (require database_path('migrations/2026_08_26_112720_add_actual_travel_timestamps_to_official_business_trips_table.php'))->up();
    (require database_path('migrations/2026_08_26_130000_add_soft_deletes_to_official_business_trips_table.php'))->up();
    (require database_path('migrations/2026_09_16_160000_add_official_business_v3_domain_foundation.php'))->up();
    (require database_path('migrations/2026_09_17_110100_add_official_business_v3_ticket_details.php'))->up();
    (require database_path('migrations/2026_09_17_120000_add_official_business_v3_ticket_completion_data.php'))->up();
    (require database_path('migrations/2026_09_21_000002_create_company_travel_credit_ledger.php'))->up();

    $departmentId = DB::table('departments')->insertGetId([
        'department_name' => 'Operations',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $this->owner = User::create([
        'name' => 'Completion Owner',
        'email' => 'completion-owner@example.test',
        'role' => 'Employee',
    ]);
    DB::table('user_permissions')->insert([
        'user_id' => $this->owner->id,
        'access_activities' => true,
        'access_hc_obf' => false,
        'access_hc_obf_finance' => true,
    ]);
    $this->employee = Employee::create([
        'user_id' => $this->owner->id,
        'department_id' => $departmentId,
        'employee_code' => 'EMP-OBTT-C',
        'first_name' => 'Completion',
        'last_name' => 'Owner',
        'position' => 'Associate',
    ]);
    $this->clientA = Company::create(['company_name' => 'Client A']);
    $this->clientB = Company::create(['company_name' => 'Client B']);
    $this->master = OfficialBusinessTrip::create([
        'ob_reference_no' => 'OBF-V3-COMPLETION',
        'employee_id' => $this->employee->id,
        'employee_name' => $this->employee->full_name,
        'destination' => 'BIR Cebu',
        'purpose' => 'Client filing',
        'date_from' => '2026-09-17',
        'actual_departure' => '2026-09-17 08:00:00',
        'actual_return' => '2026-09-17 17:00:00',
        'is_client_travel' => true,
        'company_id' => $this->clientA->id,
        'workflow_version' => 3,
        'status' => 'For Completion',
        'created_by' => $this->owner->id,
    ]);
    $this->service = app(OfficialBusinessTripTicketService::class);
});

function phase3cResolveTicketDetails($test, OfficialBusinessTripTicket $ticket): void
{
    $test->service->updateDetails($test->owner, $ticket, [[
        'sequence' => 1,
        'destination' => 'BIR Cebu',
        'activity' => 'Filing',
        'resolution_status' => 'Resolved',
        'resolved_at' => '2026-09-17 15:00:00',
    ]], [[
        'employee_id' => $test->employee->id,
        'role' => 'Filer',
        'participation_status' => 'Confirmed',
    ]]);
}

function phase3cResolveFinancials($test, OfficialBusinessTripTicket $ticket, bool $evidenceRequired = true): void
{
    $test->service->updateFinancialTreatment($test->owner, $ticket, [
        'billing_treatment' => 'Included in Retainer',
        'travel_credit_units' => null,
        'travel_credit_value' => null,
        'evidence_required' => $evidenceRequired,
        'evidence_requirement_notes' => $evidenceRequired ? null : 'No client document was produced for this meeting.',
        'additional_costs_status' => 'Not Applicable',
        'notes' => null,
    ]);
}

function phase3cAddEvidence($test, OfficialBusinessTripTicket $ticket, string $name = 'evidence.pdf'): ActivityAttachment
{
    return ActivityAttachment::create([
        'activity_type' => ActivityTypeRegistry::OFFICIAL_BUSINESS_TRIP_TICKET,
        'activity_id' => $ticket->id,
        'filename' => $name,
        'original_filename' => $name,
        'external_url' => 'https://evidence.example.test/'.$name,
        'category' => 'Filing Confirmation',
        'uploaded_by_user_id' => $test->owner->id,
        'uploaded_at' => now(),
    ]);
}

function phase3cMakeReady($test, OfficialBusinessTripTicket $ticket): void
{
    phase3cResolveTicketDetails($test, $ticket);
    phase3cResolveFinancials($test, $ticket);
    phase3cAddEvidence($test, $ticket);
}

test('evidence belongs only to its client ticket', function () {
    [$ticketA, $ticketB] = $this->service
        ->syncClients($this->owner, $this->master, [$this->clientA->id, $this->clientB->id])
        ->all();

    $this->actingAs($this->owner)
        ->postJson(route('activities.official-business.trip-tickets.evidence.store', $ticketA), [
            'external_url' => 'https://evidence.example.test/client-a.pdf',
            'evidence_type' => 'Filing Confirmation',
            'description' => 'Client A only',
        ])
        ->assertCreated();

    expect($ticketA->evidence()->count())->toBe(1)
        ->and($ticketB->evidence()->count())->toBe(0);

    $this->getJson(route('activities.official-business.trip-tickets.show', $ticketB))
        ->assertOk()
        ->assertJsonCount(0, 'ticket.evidence');
});

test('unauthorized users cannot mutate ticket evidence', function () {
    $ticket = $this->service->forClient($this->owner, $this->master, $this->clientA);
    $stranger = User::create(['name' => 'Stranger', 'email' => 'c-stranger@example.test', 'role' => 'Employee']);

    $this->actingAs($stranger)
        ->postJson(route('activities.official-business.trip-tickets.evidence.store', $ticket), [
            'external_url' => 'https://evidence.example.test/blocked.pdf',
            'evidence_type' => 'Official Receipt',
        ])
        ->assertForbidden();

    expect($ticket->evidence()->count())->toBe(0);
});

test('finance permission is required for ticket financial mutations', function () {
    $ticket = $this->service->forClient($this->owner, $this->master, $this->clientA);
    $stranger = User::create([
        'name' => 'Non Finance User',
        'email' => 'non-finance@example.test',
        'role' => 'Employee',
    ]);
    DB::table('user_permissions')->insert([
        'user_id' => $stranger->id,
        'access_activities' => true,
        'access_hc_obf' => false,
        'access_hc_obf_finance' => false,
    ]);

    $this->actingAs($stranger)
        ->putJson(route('activities.official-business.trip-tickets.financial-treatment.update', $ticket), [
            'billing_treatment' => 'Included in Retainer',
            'evidence_required' => false,
            'additional_costs_status' => 'Not Applicable',
        ])
        ->assertForbidden();

    expect($ticket->financialTreatment()->exists())->toBeFalse();
});

test('ticket cannot complete with unresolved stops', function () {
    $ticket = $this->service->forClient($this->owner, $this->master, $this->clientA);
    $this->service->updateDetails($this->owner, $ticket, [[
        'sequence' => 1, 'destination' => 'BIR Cebu', 'resolution_status' => 'Planned',
    ]], [[
        'employee_id' => $this->employee->id, 'participation_status' => 'Confirmed',
    ]]);
    phase3cResolveFinancials($this, $ticket);
    phase3cAddEvidence($this, $ticket);

    $this->actingAs($this->owner)
        ->postJson(route('activities.official-business.trip-tickets.complete', $ticket))
        ->assertUnprocessable()
        ->assertJsonPath('completion_ready', false)
        ->assertJsonFragment(['key' => 'stops']);
});

test('ticket cannot complete with unresolved personnel', function () {
    $ticket = $this->service->forClient($this->owner, $this->master, $this->clientA);
    $this->service->updateDetails($this->owner, $ticket, [[
        'sequence' => 1, 'destination' => 'BIR Cebu', 'resolution_status' => 'Resolved',
    ]], [[
        'employee_id' => $this->employee->id, 'participation_status' => 'Assigned',
    ]]);
    phase3cResolveFinancials($this, $ticket);
    phase3cAddEvidence($this, $ticket);

    $this->actingAs($this->owner)
        ->postJson(route('activities.official-business.trip-tickets.complete', $ticket))
        ->assertUnprocessable()
        ->assertJsonFragment(['key' => 'personnel']);
});

test('ticket cannot complete when required evidence is missing', function () {
    $ticket = $this->service->forClient($this->owner, $this->master, $this->clientA);
    phase3cResolveTicketDetails($this, $ticket);
    phase3cResolveFinancials($this, $ticket);

    $this->actingAs($this->owner)
        ->postJson(route('activities.official-business.trip-tickets.complete', $ticket))
        ->assertUnprocessable()
        ->assertJsonFragment(['key' => 'evidence']);
});

test('ticket cannot complete without financial treatment', function () {
    $ticket = $this->service->forClient($this->owner, $this->master, $this->clientA);
    phase3cResolveTicketDetails($this, $ticket);
    phase3cAddEvidence($this, $ticket);

    $this->actingAs($this->owner)
        ->postJson(route('activities.official-business.trip-tickets.complete', $ticket))
        ->assertUnprocessable()
        ->assertJsonFragment(['key' => 'financial_treatment']);
});

test('ticket exposes its parent master identity and blocks until master is for completion', function () {
    $this->master->update(['status' => 'Approved']);
    $ticket = $this->service->forClient($this->owner, $this->master, $this->clientA);

    $this->actingAs($this->owner)
        ->getJson(route('activities.official-business.trip-tickets.show', $ticket))
        ->assertOk()
        ->assertJsonPath('ticket.official_business_trip_id', $this->master->id)
        ->assertJsonPath('ticket.trip.id', $this->master->id)
        ->assertJsonPath('ticket.trip.reference_no', $this->master->ob_reference_no)
        ->assertJsonPath('ticket.trip.status', 'Approved')
        ->assertJsonPath('ticket.trip.movement_status', 'Returned');

    $this->actingAs($this->owner)
        ->getJson(route('activities.official-business.trip-tickets.completion-checklist', $ticket))
        ->assertOk()
        ->assertJsonPath('completion_ready', false)
        ->assertJsonFragment([
            'key' => 'master_for_completion',
            'label' => 'Master O.B. must be For Completion',
        ])
        ->assertJsonFragment([
            'detail' => 'The parent Official Business must be returned and moved to For Completion before this Trip Ticket can be completed.',
        ]);

    $this->master->update(['status' => 'For Completion']);

    $checklist = app(\App\Services\OfficialBusinessTripTicketCompletionService::class)
        ->checklist($ticket->fresh());

    expect(collect($checklist)->firstWhere('key', 'master_for_completion')['complete'])->toBeTrue()
        ->and(OfficialBusinessTrip::query()->count())->toBe(1);

    $ticket->forceFill(['status' => 'Completed'])->saveQuietly();
    $this->master->update(['status' => 'Completed']);

    $completedChecklist = app(\App\Services\OfficialBusinessTripTicketCompletionService::class)
        ->checklist($ticket->fresh());

    expect(collect($completedChecklist)->firstWhere('key', 'master_for_completion'))
        ->toMatchArray([
            'complete' => true,
            'label' => 'Master O.B. is Completed',
            'detail' => 'The parent Official Business is Completed.',
        ]);
});

test('ticket completes when every applicable requirement passes', function () {
    $ticket = $this->service->forClient($this->owner, $this->master, $this->clientA);
    phase3cMakeReady($this, $ticket);

    $this->actingAs($this->owner)
        ->postJson(route('activities.official-business.trip-tickets.complete', $ticket))
        ->assertOk()
        ->assertJsonPath('completion_ready', true)
        ->assertJsonPath('ticket.status', 'Completed');

    expect($ticket->fresh()->status)->toBe('Completed');
});

test('one completed ticket does not complete a multi-client master', function () {
    [$ticketA, $ticketB] = $this->service
        ->syncClients($this->owner, $this->master, [$this->clientA->id, $this->clientB->id])
        ->all();
    phase3cMakeReady($this, $ticketA);

    $this->actingAs($this->owner)
        ->postJson(route('activities.official-business.trip-tickets.complete', $ticketA))
        ->assertOk();

    $checklist = app(OfficialBusinessCompletionService::class)->checklist($this->master->fresh());
    expect(collect($checklist)->firstWhere('key', 'client_trip_tickets')['complete'])->toBeFalse()
        ->and($ticketB->fresh()->status)->toBe('Draft')
        ->and($this->master->fresh()->status)->toBe('For Completion');
});

test('all active completed tickets allow phase 3a master completion', function () {
    $tickets = $this->service
        ->syncClients($this->owner, $this->master, [$this->clientA->id, $this->clientB->id]);

    foreach ($tickets as $ticket) {
        phase3cMakeReady($this, $ticket);
        $this->actingAs($this->owner)
            ->postJson(route('activities.official-business.trip-tickets.complete', $ticket))
            ->assertOk();
    }

    $completion = app(OfficialBusinessCompletionService::class);
    $checklist = $completion->checklist($this->master->fresh());
    expect($completion->isReady($checklist))->toBeTrue();

    DB::table('user_permissions')
        ->where('user_id', $this->owner->id)
        ->update(['manage_hc_obf' => true]);
    $this->owner->unsetRelation('userPermission');

    $this->actingAs($this->owner)
        ->postJson(route('activities.official-business.complete', $this->master))
        ->assertOk()
        ->assertJsonPath('record.status', 'Completed');

    expect($this->master->fresh()->status)->toBe('Completed');
});

test('three clients keep independent ticket data and master completion waits for all', function () {
    (require database_path('migrations/2026_09_28_000002_create_official_business_master_personnel.php'))->up();
    $clientC = Company::create(['company_name' => 'Client C']);
    $people = collect(['Alpha', 'Beta', 'Gamma'])->map(fn (string $label) => Employee::create([
        'first_name' => $label, 'last_name' => 'Traveler', 'position' => 'Traveler',
    ]))->all();
    app(\App\Services\OfficialBusinessMasterPersonnelService::class)
        ->sync($this->master, collect($people)->pluck('id')->all());

    $clients = [$this->clientA, $this->clientB, $clientC];
    $ids = array_map(fn (Company $client): int => $client->id, $clients);
    $tickets = $this->service->syncClients($this->owner, $this->master, $ids);
    $this->service->syncClients($this->owner, $this->master, array_reverse($ids));
    expect($tickets)->toHaveCount(3)
        ->and($this->master->tripTickets()->count())->toBe(3)
        ->and(OfficialBusinessTrip::query()->count())->toBe(1);

    foreach ($tickets->values() as $index => $ticket) {
        $label = ['ALPHA', 'BETA', 'GAMMA'][$index];
        $person = $people[$index];
        $this->service->updateDetails($this->owner, $ticket, [[
            'sequence' => 1,
            'destination' => $label.' DESTINATION ONLY',
            'activity' => $label.' ACTIVITY ONLY',
            'resolution_status' => 'Resolved',
        ]], [[
            'employee_id' => $person->id,
            'participation_status' => 'Confirmed',
        ]]);

        if ($index === 2) {
            app(CompanyTravelCreditService::class)->adjust($this->owner, $clientC, 2, 500, 'Opening balance', 'Added');
        }
        $treatment = ['Included in Retainer', 'Billable to Client', 'Travel Credit'][$index];
        $this->service->updateFinancialTreatment($this->owner, $ticket, [
            'billing_treatment' => $treatment,
            'travel_credit_units' => $index === 2 ? 1 : null,
            'travel_credit_value' => $index === 2 ? 500 : null,
            'evidence_required' => true,
            'additional_costs_status' => $index === 1 ? 'Resolved' : 'Not Applicable',
        ]);
        if ($index === 1) {
            $this->service->addCost($this->owner, $ticket, [
                'category' => 'Parking', 'description' => 'BETA COST ONLY',
                'amount' => '123.45', 'billing_treatment' => 'Billable to Client',
            ]);
        }
        phase3cAddEvidence($this, $ticket, strtolower($label).'-evidence.pdf');
        expect($ticket->stops()->sole()->destination)->toBe($label.' DESTINATION ONLY')
            ->and($ticket->personnel()->sole()->employee_id)->toBe($person->id)
            ->and($ticket->financialTreatment()->sole()->billing_treatment)->toBe($treatment)
            ->and($ticket->evidence()->count())->toBe(1);
    }

    foreach ($tickets->values() as $index => $ticket) {
        $this->actingAs($this->owner)
            ->postJson(route('activities.official-business.trip-tickets.complete', $ticket))
            ->assertOk();
        expect($ticket->fresh()->status)->toBe('Completed')
            ->and($this->master->fresh()->status)->toBe('For Completion');
        $ready = app(OfficialBusinessCompletionService::class)
            ->isReady(app(OfficialBusinessCompletionService::class)->checklist($this->master->fresh()));
        expect($ready)->toBe($index === 2);
    }

    $pdftotext = (new \Symfony\Component\Process\ExecutableFinder)->find('pdftotext');
    if ($pdftotext) {
        foreach ($tickets->values() as $index => $ticket) {
            $label = ['ALPHA', 'BETA', 'GAMMA'][$index];
            $response = $this->actingAs($this->owner)
                ->get(route('activities.official-business.trip-tickets.pdf', $ticket))
                ->assertOk();
            $process = new \Symfony\Component\Process\Process([$pdftotext, '-layout', '-enc', 'UTF-8', '-', '-']);
            $process->setInput($response->getContent());
            $process->run();
            expect($process->isSuccessful())->toBeTrue();
            $text = $process->getOutput();
            expect($text)->toContain($label.' DESTINATION ONLY')
                ->toContain($label.' ACTIVITY ONLY')
                ->toContain(ucfirst(strtolower($label)).' Traveler')
                ->toContain(strtolower($label).'-evidence.pdf');
            foreach (array_diff(['ALPHA', 'BETA', 'GAMMA'], [$label]) as $other) {
                expect($text)->not->toContain($other.' DESTINATION ONLY')
                    ->not->toContain($other.' ACTIVITY ONLY')
                    ->not->toContain(ucfirst(strtolower($other)).' Traveler')
                    ->not->toContain(strtolower($other).'-evidence.pdf');
            }
        }
    }
});

test('finance feed exposes only completed canonical tickets to finance users', function () {
    [$completed, $draft] = $this->service
        ->syncClients($this->owner, $this->master, [$this->clientA->id, $this->clientB->id])
        ->all();
    phase3cMakeReady($this, $completed);
    $this->actingAs($this->owner)
        ->postJson(route('activities.official-business.trip-tickets.complete', $completed))
        ->assertOk();

    $this->getJson(route('activities.official-business.trip-tickets.finance-ready'))
        ->assertOk()
        ->assertJsonCount(1, 'tickets')
        ->assertJsonPath('tickets.0.trip_ticket_no', $completed->reference_no)
        ->assertJsonPath('tickets.0.ob_reference_no', $this->master->ob_reference_no)
        ->assertJsonPath('tickets.0.company_id', $this->clientA->id)
        ->assertJsonPath('tickets.0.billing_treatment', 'Included in Retainer')
        ->assertJsonPath('tickets.0.evidence_count', 1)
        ->assertJsonPath('pagination.total', 1);

    $this->getJson(route('activities.official-business.trip-tickets.finance-ready', [
        'company_id' => $this->clientB->id,
    ]))->assertOk()->assertJsonCount(0, 'tickets');

    $employee = User::create([
        'name' => 'No Finance', 'email' => 'no-finance-feed@example.test', 'role' => 'Employee',
    ]);
    $this->actingAs($employee)
        ->getJson(route('activities.official-business.trip-tickets.finance-ready'))
        ->assertForbidden();
});

test('legacy v1 financial and attachment behavior remains unchanged', function () {
    $legacy = OfficialBusinessTrip::create([
        'employee_id' => $this->employee->id,
        'employee_name' => $this->employee->full_name,
        'destination' => 'Legacy destination',
        'purpose' => 'Legacy purpose',
        'date_from' => '2026-09-17',
        'is_client_travel' => true,
        'workflow_version' => 1,
        'travel_billability' => 'Legacy Billable',
        'amount_client_will_pay' => '123.45',
        'attachment_paths' => ['legacy/file.pdf'],
        'status' => 'Pending',
        'created_by' => $this->owner->id,
    ]);

    expect($legacy->tripTickets()->count())->toBe(0)
        ->and($legacy->fresh()->travel_billability)->toBe('Legacy Billable')
        ->and($legacy->fresh()->amount_client_will_pay)->toBe('123.45')
        ->and($legacy->fresh()->attachment_paths)->toBe(['legacy/file.pdf']);
});

test('archived ticket identity remains preserved with completion data', function () {
    $ticket = $this->service->forClient($this->owner, $this->master, $this->clientA);
    phase3cResolveFinancials($this, $ticket, false);
    $reference = $ticket->reference_no;
    $ticket->delete();

    expect(fn () => $this->service->syncClients($this->owner, $this->master, [$this->clientA->id]))
        ->toThrow(ValidationException::class)
        ->and(OfficialBusinessTripTicket::withTrashed()->find($ticket->id)?->reference_no)->toBe($reference)
        ->and(OfficialBusinessTripTicket::withTrashed()->find($ticket->id)?->trashed())->toBeTrue();
});

test('money fields retain decimal precision', function () {
    $ticket = $this->service->forClient($this->owner, $this->master, $this->clientA);
    app(CompanyTravelCreditService::class)->adjust(
        $this->owner,
        $this->clientA,
        2,
        500.10,
        'Opening test balance',
        'Added'
    );
    $this->service->updateFinancialTreatment($this->owner, $ticket, [
        'billing_treatment' => 'Travel Credit + Additional Cost',
        'travel_credit_units' => '1.25',
        'travel_credit_value' => '500.10',
        'evidence_required' => false,
        'evidence_requirement_notes' => 'Receipt is represented by the cost reference.',
        'additional_costs_status' => 'Resolved',
        'notes' => null,
    ]);
    $cost = $this->service->addCost($this->owner, $ticket, [
        'category' => 'Parking',
        'description' => 'Client site parking',
        'amount' => '123.45',
        'incurred_on' => '2026-09-17',
        'billing_treatment' => 'Billable to Client',
        'evidence_reference' => 'OR-1001',
    ]);

    expect($ticket->fresh()->financialTreatment->travel_credit_units)->toBe('1.25')
        ->and($ticket->fresh()->financialTreatment->travel_credit_value)->toBe('500.10')
        ->and($cost->fresh()->amount)->toBe('123.45');
});

test('adding an additional cost resolves the ticket additional cost status', function () {
    $ticket = $this->service->forClient(
        $this->owner,
        $this->master,
        $this->clientA
    );

    $this->service->updateFinancialTreatment($this->owner, $ticket, [
        'billing_treatment' => 'Included in Retainer',
        'travel_credit_units' => null,
        'travel_credit_value' => null,
        'evidence_required' => false,
        'evidence_requirement_notes' => 'No evidence required for cost-status regression.',
        'additional_costs_status' => 'Pending',
        'notes' => null,
    ]);

    expect(
        $ticket->fresh()->financialTreatment->additional_costs_status
    )->toBe('Pending');

    $this->service->addCost($this->owner, $ticket, [
        'category' => 'Parking',
        'description' => 'Client site parking',
        'amount' => '150.00',
        'billing_treatment' => 'Billable to Client',
    ]);

    $ticket = $ticket->fresh([
        'financialTreatment',
        'costs',
    ]);

    expect($ticket->costs)->toHaveCount(1)
        ->and($ticket->financialTreatment->additional_costs_status)
        ->toBe('Resolved');

    $checklist = app(
        \App\Services\OfficialBusinessTripTicketCompletionService::class
    )->checklist($ticket);

    $additionalCosts = collect($checklist)->firstWhere(
        'key',
        'additional_costs'
    );

    expect($additionalCosts)->not->toBeNull()
        ->and($additionalCosts['complete'])->toBeTrue()
        ->and($additionalCosts['detail'])->toBe('Additional costs are resolved.');
});

test('travel credit is deducted once per ticket and reversal restores the client balance', function () {
    $ledger = app(CompanyTravelCreditService::class);
    $ledger->adjust($this->owner, $this->clientA, 3, 625, 'Opening allocation', 'Added');
    $ticket = $this->service->forClient($this->owner, $this->master, $this->clientA);
    $creditTreatment = [
        'billing_treatment' => 'Travel Credit',
        'travel_credit_units' => '1.00',
        'travel_credit_value' => null,
        'evidence_required' => false,
        'evidence_requirement_notes' => 'No evidence required for ledger regression.',
        'additional_costs_status' => 'Not Applicable',
        'notes' => null,
    ];

    $this->service->updateFinancialTreatment($this->owner, $ticket, $creditTreatment);
    $this->service->updateFinancialTreatment($this->owner, $ticket, $creditTreatment);

    expect($this->clientA->travelCreditAccount()->value('available_units'))->toBe('2.00')
        ->and($ticket->travelCreditEntries()->where('entry_type', 'Used')->count())->toBe(1)
        ->and($ticket->fresh()->financialTreatment->travel_credit_value)->toBe('625.00');

    $this->service->updateFinancialTreatment($this->owner, $ticket, [
        'billing_treatment' => 'Included in Retainer',
        'travel_credit_units' => null,
        'travel_credit_value' => null,
        'evidence_required' => false,
        'evidence_requirement_notes' => 'No evidence required for ledger regression.',
        'additional_costs_status' => 'Not Applicable',
        'notes' => null,
    ]);

    expect($this->clientA->travelCreditAccount()->value('available_units'))->toBe('3.00')
        ->and($ticket->travelCreditEntries()->where('entry_type', 'Reversal')->count())->toBe(1)
        ->and($ticket->fresh()->financialTreatment->travel_credit_entry_id)->toBeNull();
});

test('travel credit deduction rejects insufficient client balance', function () {
    $ticket = $this->service->forClient($this->owner, $this->master, $this->clientA);

    expect(fn () => $this->service->updateFinancialTreatment($this->owner, $ticket, [
        'billing_treatment' => 'Travel Credit',
        'travel_credit_units' => '1.00',
        'travel_credit_value' => null,
        'evidence_required' => false,
        'evidence_requirement_notes' => 'No evidence required for ledger regression.',
        'additional_costs_status' => 'Not Applicable',
        'notes' => null,
    ]))->toThrow(ValidationException::class)
        ->and($ticket->financialTreatment()->exists())->toBeFalse()
        ->and($this->clientA->travelCreditAccount()->exists())->toBeFalse();
});

test('cross-client evidence and cost leakage is rejected', function () {
    [$ticketA, $ticketB] = $this->service
        ->syncClients($this->owner, $this->master, [$this->clientA->id, $this->clientB->id])
        ->all();
    $evidence = phase3cAddEvidence($this, $ticketA, 'client-a-only.pdf');
    $cost = $this->service->addCost($this->owner, $ticketA, [
        'category' => 'Toll',
        'description' => 'Client A toll',
        'amount' => '75.00',
        'billing_treatment' => 'Billable to Client',
    ]);

    $this->actingAs($this->owner)
        ->deleteJson(route('activities.official-business.trip-tickets.evidence.destroy', [$ticketB, $evidence]))
        ->assertNotFound();
    $this->deleteJson(route('activities.official-business.trip-tickets.costs.destroy', [$ticketB, $cost]))
        ->assertNotFound();

    expect($ticketA->evidence()->whereKey($evidence->id)->exists())->toBeTrue()
        ->and(OfficialBusinessTripTicketCost::find($cost->id))->not->toBeNull()
        ->and($ticketB->evidence()->count())->toBe(0)
        ->and($ticketB->costs()->count())->toBe(0);
});

test('finance user can view client travel credit summary over json', function () {
    $ledger = app(CompanyTravelCreditService::class);

    $ledger->adjust(
        $this->owner,
        $this->clientA,
        4,
        625,
        'Opening HTTP test allocation',
        'Added'
    );

    $this->actingAs($this->owner)
        ->getJson(route('activities.official-business.travel-credits.show', $this->clientA))
        ->assertOk()
        ->assertJsonPath('travel_credit.available_units', '4.00')
        ->assertJsonPath('travel_credit.credit_value', '625.00');
});

test('non finance user cannot view or adjust client travel credits', function () {
    $stranger = User::create([
        'name' => 'Travel Credit Non Finance',
        'email' => 'travel-credit-non-finance@example.test',
        'role' => 'Employee',
    ]);

    DB::table('user_permissions')->insert([
        'user_id' => $stranger->id,
        'access_activities' => true,
        'access_hc_obf' => false,
        'access_hc_obf_finance' => false,
    ]);

    $this->actingAs($stranger)
        ->getJson(route('activities.official-business.travel-credits.show', $this->clientA))
        ->assertForbidden();

    $this->actingAs($stranger)
        ->postJson(route('activities.official-business.travel-credits.adjust', $this->clientA), [
            'entry_type' => 'Added',
            'units_delta' => '2.00',
            'credit_value' => '500.00',
            'reason' => 'This request must be rejected.',
        ])
        ->assertForbidden();

    expect($this->clientA->travelCreditAccount()->exists())->toBeFalse();
});

test('finance user can add client travel credits through http endpoint', function () {
    $this->actingAs($this->owner)
        ->postJson(route('activities.official-business.travel-credits.adjust', $this->clientA), [
            'entry_type' => 'Added',
            'units_delta' => '3.00',
            'credit_value' => '750.00',
            'reason' => 'Opening client travel credit allocation',
        ])
        ->assertCreated()
        ->assertJsonPath('message', 'Client travel-credit ledger updated successfully.')
        ->assertJsonPath('travel_credit.available_units', '3.00')
        ->assertJsonPath('travel_credit.credit_value', '750.00');

    expect($this->clientA->travelCreditAccount()->value('available_units'))->toBe('3.00')
        ->and($this->clientA->travelCreditAccount()->value('credit_value'))->toBe('750.00')
        ->and(
            DB::table('company_travel_credit_entries')
                ->where('entry_type', 'Added')
                ->where('reason', 'Opening client travel credit allocation')
                ->count()
        )->toBe(1);
});

test('travel credit adjustment endpoint rejects invalid added balance', function () {
    $this->actingAs($this->owner)
        ->postJson(route('activities.official-business.travel-credits.adjust', $this->clientA), [
            'entry_type' => 'Added',
            'units_delta' => '-1.00',
            'credit_value' => '500.00',
            'reason' => 'Invalid negative addition',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('units_delta');

    expect($this->clientA->travelCreditAccount()->exists())->toBeFalse()
        ->and(DB::table('company_travel_credit_entries')->count())->toBe(0);
});

test('finance user can render the travel credit blade page', function () {
    app(CompanyTravelCreditService::class)->adjust(
        $this->owner,
        $this->clientA,
        2,
        625,
        'Browser rendering allocation',
        'Added'
    );

    $this->actingAs($this->owner)
        ->get(route('activities.official-business.travel-credits.show', $this->clientA))
        ->assertOk()
        ->assertViewIs('company.travel-credits')
        ->assertViewHas('company')
        ->assertViewHas('summary')
        ->assertSee('Travel Credits')
        ->assertSee('Available Credits')
        ->assertSee('Credit Value')
        ->assertSee('Available Value')
        ->assertSee('Add / Adjust Credits')
        ->assertSee('Credit History')
        ->assertSee('Browser rendering allocation');
});

test('non finance user cannot render the travel credit blade page', function () {
    $stranger = User::create([
        'name' => 'Browser Non Finance',
        'email' => 'browser-non-finance@example.test',
        'role' => 'Employee',
    ]);

    DB::table('user_permissions')->insert([
        'user_id' => $stranger->id,
        'access_activities' => true,
        'access_hc_obf' => false,
        'access_hc_obf_finance' => false,
    ]);

    $this->actingAs($stranger)
        ->get(route('activities.official-business.travel-credits.show', $this->clientA))
        ->assertForbidden();
});

test('browser travel credit adjustment redirects back to the ledger page', function () {
    $response = $this
        ->actingAs($this->owner)
        ->post(route('activities.official-business.travel-credits.adjust', $this->clientA), [
            'entry_type' => 'Added',
            'units_delta' => '5.00',
            'credit_value' => '700.00',
            'reason' => 'Browser form allocation',
        ]);

    $response
        ->assertRedirect(
            route('activities.official-business.travel-credits.show', $this->clientA)
        )
        ->assertSessionHas(
            'success',
            'Client travel-credit ledger updated successfully.'
        );

    expect($this->clientA->travelCreditAccount()->value('available_units'))->toBe('5.00')
        ->and($this->clientA->travelCreditAccount()->value('credit_value'))->toBe('700.00')
        ->and(
            DB::table('company_travel_credit_entries')
                ->where('reason', 'Browser form allocation')
                ->where('entry_type', 'Added')
                ->count()
        )->toBe(1);
});
