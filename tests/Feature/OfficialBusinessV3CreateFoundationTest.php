<?php

use App\Models\ActivityAttachment;
use App\Models\Company;
use App\Models\OfficialBusinessTrip;
use App\Models\OfficialBusinessTripDetailTravel;
use App\Models\OfficialBusinessTripDetailTransportation;
use App\Services\OfficialBusinessDetailService;
use App\Services\OfficialBusinessCreateDetailService;
use App\Services\OfficialBusinessCreateAttachmentService;
use App\Services\OfficialBusinessSuggestionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
        throw new RuntimeException('O.B. create foundation tests require SQLite :memory:.');
    }

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email');
        $table->string('role');
        $table->timestamps();
    });
    Schema::create('employees', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('user_id')->nullable();
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
    });
    Schema::create('companies', function (Blueprint $table): void {
        $table->id();
        $table->string('company_name');
        $table->timestamps();
    });
    Schema::create('contacts', function (Blueprint $table): void {
        $table->id();
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->timestamps();
    });
    Schema::create('deals', function (Blueprint $table): void {
        $table->id();
        $table->string('deal_name')->nullable();
        $table->timestamps();
    });
    Schema::create('projects', function (Blueprint $table): void {
        $table->id();
        $table->string('project_code')->nullable();
        $table->foreignId('deal_id')->nullable();
        $table->foreignId('company_id')->nullable();
        $table->string('name')->nullable();
        $table->string('engagement_type')->nullable();
        $table->string('status')->nullable();
        $table->json('metadata')->nullable();
        $table->timestamps();
    });
    Schema::create('departments', function (Blueprint $table): void {
        $table->id();
        $table->string('department_name');
        $table->timestamps();
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

    (require database_path('migrations/2026_05_11_160306_create_official_business_trips_table.php'))->up();
    (require database_path('migrations/2026_08_26_130000_add_soft_deletes_to_official_business_trips_table.php'))->up();
    (require database_path('migrations/2026_09_16_160000_add_official_business_v3_domain_foundation.php'))->up();
    (require database_path('migrations/2026_09_28_235900_add_movement_status_to_official_business_trips.php'))->up();

    foreach ([
        '2026_10_07_120000_add_classification_to_official_business_trips.php',
        '2026_10_07_120100_create_official_business_trip_details_table.php',
        '2026_10_07_120200_create_official_business_trip_detail_travel_table.php',
        '2026_10_07_120300_create_official_business_trip_detail_transportation_table.php',
        '2026_10_07_120400_add_official_business_trip_detail_id_to_activity_attachments_table.php',
        '2026_10_07_120500_create_official_business_suggestions_table.php',
    ] as $migration) {
        (require database_path('migrations/'.$migration))->up();
    }

    $this->ownerId = DB::table('users')->insertGetId([
        'name' => 'O.B. Owner',
        'email' => 'owner@example.test',
        'role' => 'Employee',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $this->employeeId = DB::table('employees')->insertGetId([
        'user_id' => $this->ownerId,
        'first_name' => 'O.B.',
        'last_name' => 'Owner',
    ]);
});

function createObV3CreateFoundationTrip(object $test, array $attributes = []): OfficialBusinessTrip
{
    return OfficialBusinessTrip::create($attributes + [
        'ob_reference_no' => 'OBF-CREATE-'.strtoupper(Str::random(8)),
        'employee_id' => $test->employeeId,
        'employee_name' => 'O.B. Owner',
        'destination' => 'Legacy Destination',
        'purpose' => 'Legacy Purpose',
        'date_from' => '2026-10-07',
        'is_client_travel' => false,
        'workflow_version' => 1,
        'status' => 'Pending',
        'movement_status' => 'Not Started',
        'created_by' => $test->ownerId,
    ]);
}

function createObV3ClientEngagement(int $companyId, string $type, ?int $dealId = null): array
{
    $dealId ??= DB::table('deals')->insertGetId([
        'deal_name' => $type.' Engagement',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $projectId = DB::table('projects')->insertGetId([
        'deal_id' => $dealId,
        'company_id' => $companyId,
        'project_code' => strtoupper($type).'-'.Str::random(6),
        'name' => $type.' Engagement',
        'engagement_type' => $type,
        'status' => 'In Progress',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return ['deal_id' => $dealId, 'project_id' => $projectId];
}

function validObV3ClientDetailRow(int $companyId, string $type, array $engagement = []): array
{
    return array_merge([
        'company_id' => $companyId,
        'engagement_type' => $type,
        'from_location' => 'Office',
        'destination' => 'Client',
        'purpose' => 'Meeting',
        'travel' => [
            'departure_date' => '2026-10-08',
            'departure_time' => '08:00',
            'expected_arrival_date' => '2026-10-08',
            'expected_arrival_time' => '09:00',
            'estimated_expense' => 0,
        ],
        'transportation' => ['mode' => 'Walking'],
    ], $engagement);
}

test('existing workspace records drive the client endpoint without provisioning data', function (): void {
    $client = Company::create(['company_name' => 'Target Client']);
    $other = Company::create(['company_name' => 'Other Client']);
    foreach (['Regular', 'Project'] as $type) {
        createObV3ClientEngagement($client->id, $type);
        createObV3ClientEngagement($other->id, $type);
    }
    $firstIds = DB::table('projects')->pluck('id')->all();
    $dealCount = DB::table('deals')->count();

    $this->mock(\App\Services\OfficialBusinessAuthorizationService::class, function ($mock): void {
        $mock->shouldReceive('canView')->andReturn(true);
        $mock->shouldReceive('canReadFinance')->andReturn(false);
    });
    $controller = app(\App\Http\Controllers\OfficialBusinessTripController::class);
    $service = app(OfficialBusinessCreateDetailService::class);
    $trip = createObV3CreateFoundationTrip($this, ['classification' => 'client']);
    $rows = [];
    foreach (['Regular', 'Project'] as $type) {
        $payload = $controller->engagements(Request::create('/obf/engagements', 'GET', [
            'company_id' => $client->id, 'type' => $type,
        ]))->getData(true);
        expect($payload['items'])->toHaveCount(1);
        foreach ($payload['items'] as $item) {
            $project = \App\Models\Project::where('company_id', $client->id)->where('name', $item['name'])->firstOrFail();
            expect($item['id'])->toBe($project->id);
            $field = 'project_id';
            $row = validObV3ClientDetailRow($client->id, $type, [$field => $item['id']]);
            $validated = $service->validateRows([$row], 'client', false)[0];
            expect($validated[$field])->toBe($item['id'])
                ->and($validated['deal_id'])->toBe($type === 'Regular' ? $project->deal_id : null);
            $rows[] = $validated;
        }
    }
    $service->sync($trip, 'client', $rows);
    expect($trip->details()->count())->toBe(2)
        ->and(DB::table('projects')->pluck('id')->all())->toBe($firstIds)
        ->and(DB::table('deals')->count())->toBe($dealCount);
    foreach ($trip->details as $index => $detail) {
        expect($detail->deal_id)->toBe($rows[$index]['deal_id'])
            ->and($detail->project_id)->toBe($rows[$index]['project_id']);
    }
});

test('engagement endpoint excludes other clients inactive unknown and shell records and accepts active case variants', function (): void {
    $client = Company::create(['company_name' => 'Target Client']);
    $other = Company::create(['company_name' => 'Other Client']);
    $this->mock(\App\Services\OfficialBusinessAuthorizationService::class, function ($mock): void {
        $mock->shouldReceive('canView')->andReturn(true);
        $mock->shouldReceive('canReadFinance')->andReturn(false);
    });
    foreach (['Regular', 'Project'] as $type) {
        $active = createObV3ClientEngagement($client->id, $type);
        DB::table('projects')->where('id', $active['project_id'])->update(['engagement_type' => strtolower($type), 'status' => ' in progress ']);
        createObV3ClientEngagement($other->id, $type);
        foreach (['Completed', 'Cancelled', 'Archived', 'Rejected', 'shell', 'Inactive', 'On Hold', null, 'unknown'] as $status) {
            $excluded = createObV3ClientEngagement($client->id, $type);
            DB::table('projects')->where('id', $excluded['project_id'])->update([
                'status' => $status, 'metadata' => json_encode(['is_shell' => $status === 'shell']),
            ]);
            $field = 'project_id';
            expect(fn () => app(OfficialBusinessCreateDetailService::class)->validateRows([
                validObV3ClientDetailRow($client->id, $type, [$field => $excluded[$field]]),
            ], 'client', false))->toThrow(\Illuminate\Validation\ValidationException::class);
        }
        $payload = app(\App\Http\Controllers\OfficialBusinessTripController::class)->engagements(
            Request::create('/obf/engagements', 'GET', ['company_id' => $client->id, 'type' => $type])
        )->getData(true);
        expect(array_column($payload['items'], 'id'))->toBe([$active['project_id']]);
    }
});

test('manual Regular workspace without a Deal is selectable and persists through draft and submit validation', function (): void {
    $client = Company::create(['company_name' => 'Manual Regular Client']);
    $workspaceId = DB::table('projects')->insertGetId([
        'company_id' => $client->id, 'name' => 'Test workspace',
        'engagement_type' => 'Regular Retainer', 'status' => 'RSAT',
    ]);
    $this->mock(\App\Services\OfficialBusinessAuthorizationService::class, function ($mock): void {
        $mock->shouldReceive('canView')->andReturn(true);
        $mock->shouldReceive('canReadFinance')->andReturn(false);
    });
    $payload = app(\App\Http\Controllers\OfficialBusinessTripController::class)->engagements(
        Request::create('/obf/engagements', 'GET', ['company_id' => $client->id, 'type' => 'Regular'])
    )->getData(true);
    expect($payload['items'])->toBe([['id' => $workspaceId, 'name' => 'Test workspace', 'deal_id' => null]]);
    $trip = createObV3CreateFoundationTrip($this, ['workflow_version' => 3, 'classification' => 'client']);
    $service = app(OfficialBusinessCreateDetailService::class);
    $row = validObV3ClientDetailRow($client->id, 'Regular', ['project_id' => $workspaceId]);
    $draft = $service->validateRows([$row], 'client', true);
    $service->sync($trip, 'client', $draft);
    $detail = $trip->fresh()->details()->sole();
    expect($detail->project_id)->toBe($workspaceId)
        ->and($detail->deal_id)->toBeNull()
        ->and($detail->project->name)->toBe('Test workspace');
    $reloaded = array_replace($row, $detail->only(['id', 'project_id', 'deal_id']));
    $submitted = $service->validateRows([$reloaded], 'client', false);
    $service->sync($trip, 'client', $submitted);
    expect($trip->fresh()->details()->sole()->project_id)->toBe($workspaceId);
});

test('legacy deal-only selection cannot guess between multiple Regular workspaces', function (): void {
    $client = Company::create(['company_name' => 'Ambiguous Regular Client']);
    $regular = createObV3ClientEngagement($client->id, 'Regular');
    DB::table('projects')->insert([
        'company_id' => $client->id, 'deal_id' => $regular['deal_id'],
        'name' => 'Second test workspace', 'engagement_type' => 'Hybrid Regular', 'status' => 'RSAT',
    ]);
    expect(fn () => app(OfficialBusinessCreateDetailService::class)->validateRows([
        validObV3ClientDetailRow($client->id, 'Regular', ['deal_id' => $regular['deal_id']]),
    ], 'client', false))->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('malformed workspace IDs cannot be coerced to another existing engagement', function (): void {
    $client = Company::create(['company_name' => 'ID Validation Client']);
    $regular = createObV3ClientEngagement($client->id, 'Regular');
    foreach ([$regular['project_id'].'invalid', [$regular['project_id']], 0] as $id) {
        expect(fn () => app(OfficialBusinessCreateDetailService::class)->validateRows([
            validObV3ClientDetailRow($client->id, 'Regular', ['project_id' => $id]),
        ], 'client', false))->toThrow(\Illuminate\Validation\ValidationException::class);
    }
});

test('Regular canonical selection derives its Deal and rejects cross-client workspace IDs in drafts too', function (): void {
    $client = Company::create(['company_name' => 'Selected Workspace Client']);
    $other = Company::create(['company_name' => 'Other Workspace Client']);
    $regular = createObV3ClientEngagement($client->id, 'Regular');
    $foreign = createObV3ClientEngagement($other->id, 'Regular');
    $service = app(OfficialBusinessCreateDetailService::class);
    $valid = $service->validateRows([
        validObV3ClientDetailRow($client->id, 'Regular', [
            'project_id' => $regular['project_id'], 'deal_id' => $foreign['deal_id'],
        ]),
    ], 'client', false);
    expect($valid[0]['deal_id'])->toBe($regular['deal_id']);
    foreach ([true, false] as $draft) {
        expect(fn () => $service->validateRows([
            validObV3ClientDetailRow($client->id, 'Regular', ['project_id' => $foreign['project_id']]),
        ], 'client', $draft))->toThrow(\Illuminate\Validation\ValidationException::class);
    }
});

test('parent preserves nullable classification and zero details for legacy records', function (): void {
    $legacy = createObV3CreateFoundationTrip($this);

    expect($legacy->classification)->toBeNull()
        ->and($legacy->details)->toHaveCount(0)
        ->and($legacy->workflow_version)->toBe(1)
        ->and($legacy->status)->toBe('Pending')
        ->and($legacy->movement_status)->toBe('Not Started')
        ->and($legacy->remarks)->toBeNull();
});

test('a parent has ordered independent detail rows with one child record of each type', function (): void {
    $trip = createObV3CreateFoundationTrip($this, ['workflow_version' => 3]);
    $service = app(OfficialBusinessDetailService::class);

    $later = $service->create($trip, [
        'classification' => OfficialBusinessTrip::CLASSIFICATION_CLIENT,
        'sort_order' => 20,
        'from_location' => 'JK&C Office',
        'destination' => 'Client Office',
        'purpose' => 'Client meeting',
    ], ['departure_date' => '2026-10-10'], ['mode' => 'Taxi']);
    $first = $service->create($trip, [
        'classification' => OfficialBusinessTrip::CLASSIFICATION_INTERNAL,
        'sort_order' => 10,
        'destination' => 'BIR',
        'purpose' => 'Filing',
    ], ['departure_date' => '2026-10-08'], ['mode' => 'Walking']);

    $trip->refresh();

    expect($trip->details->pluck('id')->all())->toBe([$first->id, $later->id])
        ->and($first->officialBusinessTrip->id)->toBe($trip->id)
        ->and($first->travelDetail->departure_date->toDateString())->toBe('2026-10-08')
        ->and($first->transportationDetail->mode)->toBe('Walking')
        ->and($later->travelDetail->departure_date->toDateString())->toBe('2026-10-10')
        ->and($later->transportationDetail->mode)->toBe('Taxi');
});

test('detail classification and client regular or project references persist through canonical relationships', function (): void {
    $trip = createObV3CreateFoundationTrip($this, ['workflow_version' => 3]);
    $company = Company::create(['company_name' => 'Client Company']);
    $dealId = DB::table('deals')->insertGetId(['deal_name' => 'Annual Retainer', 'created_at' => now(), 'updated_at' => now()]);
    $regularId = DB::table('projects')->insertGetId([
        'deal_id' => $dealId,
        'company_id' => $company->id,
        'project_code' => 'REG-001',
        'name' => 'Regular Engagement',
        'engagement_type' => 'Regular',
        'status' => 'In Progress',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $projectId = DB::table('projects')->insertGetId([
        'deal_id' => $dealId,
        'company_id' => $company->id,
        'project_code' => 'PROJ-001',
        'name' => 'Project Engagement',
        'engagement_type' => 'Project',
        'status' => 'In Progress',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $service = app(OfficialBusinessDetailService::class);

    $regular = $service->create($trip, [
        'classification' => OfficialBusinessTrip::CLASSIFICATION_CLIENT,
        'company_id' => $company->id,
        'deal_id' => $dealId,
        'engagement_type' => 'Regular',
    ]);
    $project = $service->create($trip, [
        'classification' => OfficialBusinessTrip::CLASSIFICATION_CLIENT,
        'company_id' => $company->id,
        'deal_id' => $dealId,
        'project_id' => $projectId,
        'engagement_type' => 'Project',
    ]);

    expect($regular->client->id)->toBe($company->id)
        ->and($regular->deal->id)->toBe($dealId)
        ->and($regular->project_id)->toBeNull()
        ->and($project->project->engagement_type)->toBe('Project');
});

test('legacy deal-only Regular submission resolves its canonical workspace', function (): void {
    $company = Company::create(['company_name' => 'Regular Client']);
    $engagement = createObV3ClientEngagement($company->id, 'Regular');
    $row = validObV3ClientDetailRow($company->id, 'Regular', ['deal_id' => $engagement['deal_id']]);

    $validated = app(OfficialBusinessCreateDetailService::class)
        ->validateRows([$row], OfficialBusinessTrip::CLASSIFICATION_CLIENT, false);

    expect($validated[0]['deal_id'])->toBe($engagement['deal_id'])
        ->and($validated[0]['project_id'])->toBe($engagement['project_id']);
});

test('client Regular submission reports a friendly row error when engagement is missing', function (): void {
    $company = Company::create(['company_name' => 'Missing Regular Client']);
    $row = validObV3ClientDetailRow($company->id, 'Regular');

    try {
        app(OfficialBusinessCreateDetailService::class)
            ->validateRows([$row], OfficialBusinessTrip::CLASSIFICATION_CLIENT, false);
        test()->fail('Regular engagement is required for submission.');
    } catch (\Illuminate\Validation\ValidationException $exception) {
        expect($exception->errors()['details_json'])
            ->toContain('O.B. Detail 1 Related Regular Engagement is required.')
            ->and(array_keys($exception->errors()))->not->toContain('rows.0.project_id');
    }
});

test('client Project submission requires project_id and keeps deal_id null', function (): void {
    $company = Company::create(['company_name' => 'Project Client']);
    $engagement = createObV3ClientEngagement($company->id, 'Project');
    $row = validObV3ClientDetailRow($company->id, 'Project', ['project_id' => $engagement['project_id']]);

    $validated = app(OfficialBusinessCreateDetailService::class)
        ->validateRows([$row], OfficialBusinessTrip::CLASSIFICATION_CLIENT, false);

    expect($validated[0]['project_id'])->toBe($engagement['project_id'])
        ->and($validated[0]['deal_id'])->toBeNull();
});

test('type-specific client engagement IDs survive JSON decode validation and detail persistence', function (): void {
    $company = Company::create(['company_name' => 'Relationship Client']);
    $regular = createObV3ClientEngagement($company->id, 'Regular');
    $project = createObV3ClientEngagement($company->id, 'Project');
    $trip = createObV3CreateFoundationTrip($this, ['workflow_version' => 3]);
    $service = app(OfficialBusinessCreateDetailService::class);
    $requestRows = [
        validObV3ClientDetailRow($company->id, 'Regular', [
            'deal_id' => (string) $regular['deal_id'],
            'project_id' => null,
        ]),
        validObV3ClientDetailRow($company->id, 'Project', [
            'deal_id' => null,
            'project_id' => (string) $project['project_id'],
        ]),
    ];

    $validated = $service->validateRows(
        $service->decode(json_encode($requestRows)),
        OfficialBusinessTrip::CLASSIFICATION_CLIENT,
        false
    );
    $service->sync($trip, OfficialBusinessTrip::CLASSIFICATION_CLIENT, $validated);
    $persisted = $trip->details()->orderBy('sort_order')->get();

    expect($validated[0]['deal_id'])->toBe($regular['deal_id'])
        ->and($validated[0]['project_id'])->toBe($regular['project_id'])
        ->and($validated[1]['deal_id'])->toBeNull()
        ->and($validated[1]['project_id'])->toBe($project['project_id'])
        ->and($persisted[0]->deal_id)->toBe($regular['deal_id'])
        ->and($persisted[0]->project_id)->toBe($regular['project_id'])
        ->and($persisted[1]->deal_id)->toBeNull()
        ->and($persisted[1]->project_id)->toBe($project['project_id']);
});
test('client Project submission reports a friendly row error when project is missing', function (): void {
    $company = Company::create(['company_name' => 'Missing Project Client']);
    $row = validObV3ClientDetailRow($company->id, 'Project');

    try {
        app(OfficialBusinessCreateDetailService::class)
            ->validateRows([$row], OfficialBusinessTrip::CLASSIFICATION_CLIENT, false);
        test()->fail('Project is required for submission.');
    } catch (\Illuminate\Validation\ValidationException $exception) {
        expect($exception->errors()['details_json'])
            ->toContain('O.B. Detail 1 Related Project is required.')
            ->and(array_keys($exception->errors()))->not->toContain('rows.0.project_id');
    }
});

test('workspace type mismatch is rejected and linked Deal is derived from a valid selection', function (): void {
    $company = Company::create(['company_name' => 'Type Switch Client']);
    $regular = createObV3ClientEngagement($company->id, 'Regular');
    $project = createObV3ClientEngagement($company->id, 'Project');
    $service = app(OfficialBusinessCreateDetailService::class);

    $regularRow = validObV3ClientDetailRow($company->id, 'Regular', [
        'deal_id' => $regular['deal_id'],
        'project_id' => $project['project_id'],
    ]);
    expect(fn () => $service->validateRows([$regularRow], OfficialBusinessTrip::CLASSIFICATION_CLIENT, false))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    $projectRow = validObV3ClientDetailRow($company->id, 'Project', [
        'deal_id' => $regular['deal_id'],
        'project_id' => $project['project_id'],
    ]);
    $asProject = $service->validateRows([$projectRow], OfficialBusinessTrip::CLASSIFICATION_CLIENT, false)[0];

    expect($asProject['deal_id'])->toBeNull()
        ->and($asProject['project_id'])->toBe($project['project_id']);
});

test('client O.B. rejects Regular engagements and Projects belonging to another client', function (): void {
    $selectedClient = Company::create(['company_name' => 'Selected Client']);
    $otherClient = Company::create(['company_name' => 'Other Client']);
    $regular = createObV3ClientEngagement($otherClient->id, 'Regular');
    $project = createObV3ClientEngagement($otherClient->id, 'Project');
    $service = app(OfficialBusinessCreateDetailService::class);

    foreach ([
        validObV3ClientDetailRow($selectedClient->id, 'Regular', ['deal_id' => $regular['deal_id']]),
        validObV3ClientDetailRow($selectedClient->id, 'Project', ['project_id' => $project['project_id']]),
    ] as $row) {
        try {
            $service->validateRows([$row], OfficialBusinessTrip::CLASSIFICATION_CLIENT, false);
            test()->fail('Cross-client engagement selection must be rejected.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            expect($exception->errors()['details_json'][0])->toContain('must be active and belong to this client');
        }
    }
});

test('Client O.B. draft accepts missing Regular and Project engagement relationships', function (): void {
    $company = Company::create(['company_name' => 'Draft Client']);
    $service = app(OfficialBusinessCreateDetailService::class);

    $regular = $service->validateRows([
        ['company_id' => $company->id, 'engagement_type' => 'Regular'],
    ], OfficialBusinessTrip::CLASSIFICATION_CLIENT, true);
    $project = $service->validateRows([
        ['company_id' => $company->id, 'engagement_type' => 'Project'],
    ], OfficialBusinessTrip::CLASSIFICATION_CLIENT, true);

    expect($regular[0]['deal_id'] ?? null)->toBeNull()
        ->and($project[0]['project_id'] ?? null)->toBeNull();
});

test('internal and social detail fields persist independently', function (): void {
    $trip = createObV3CreateFoundationTrip($this, ['workflow_version' => 3]);
    $departmentId = DB::table('departments')->insertGetId(['department_name' => 'Operations', 'created_at' => now(), 'updated_at' => now()]);
    $contactId = DB::table('contacts')->insertGetId(['first_name' => 'Ava', 'last_name' => 'Client', 'created_at' => now(), 'updated_at' => now()]);
    $service = app(OfficialBusinessDetailService::class);

    $internal = $service->create($trip, [
        'classification' => OfficialBusinessTrip::CLASSIFICATION_INTERNAL,
        'internal_activity_type' => 'Government / Regulatory Transaction',
        'department_id' => $departmentId,
        'related_internal_type' => 'Document',
        'related_internal_id' => 44,
    ]);
    $social = $service->create($trip, [
        'classification' => OfficialBusinessTrip::CLASSIFICATION_SOCIAL,
        'activity_event_name' => 'Business Forum',
        'social_activity_type' => 'Networking',
        'organization_host' => 'Chamber',
        'contact_id' => $contactId,
        'related_contact_text' => 'Event coordinator',
    ]);

    expect($internal->department->department_name)->toBe('Operations')
        ->and($internal->related_internal_id)->toBe(44)
        ->and($social->contact->full_name)->toBe('Ava Client')
        ->and($social->organization_host)->toBe('Chamber');
});

test('database rejects duplicate travel or transportation rows for one detail', function (): void {
    $detail = app(OfficialBusinessDetailService::class)->create(createObV3CreateFoundationTrip($this));

    expect(fn () => OfficialBusinessTripDetailTravel::create([
        'official_business_trip_detail_id' => $detail->id,
    ]))->toThrow(QueryException::class)
        ->and(fn () => OfficialBusinessTripDetailTransportation::create([
            'official_business_trip_detail_id' => $detail->id,
        ]))->toThrow(QueryException::class);
});

test('travel details do not duplicate routes and updating a detail leaves child route-free', function (): void {
    $detail = app(OfficialBusinessDetailService::class)->create(
        createObV3CreateFoundationTrip($this),
        ['from_location' => 'Old From', 'destination' => 'Old Destination', 'purpose' => 'Old Purpose'],
        ['estimated_expense' => 125.50],
        ['mode' => 'Company Vehicle']
    );

    $detail->update(['from_location' => 'New From', 'destination' => 'New Destination', 'purpose' => 'New Purpose']);

    expect($detail->fresh()->travelDetail->estimated_expense)->toBe('125.50')
        ->and($detail->fresh()->transportationDetail->mode)->toBe('Company Vehicle')
        ->and(Schema::hasColumn('official_business_trip_detail_travel', 'destination'))->toBeFalse()
        ->and(Schema::hasColumn('official_business_trip_detail_transportation', 'purpose'))->toBeFalse();
});

test('draft detail synchronization initializes independent children, reorders updates and safely removes them', function (): void {
    $trip = createObV3CreateFoundationTrip($this, ['workflow_version' => 3]);
    $service = app(OfficialBusinessCreateDetailService::class);
    $rows = [
        ['from_location' => 'Office', 'destination' => 'North', 'purpose' => 'Visit', 'internal_activity_type' => 'Office Operations', 'department_id' => null,
            'travel' => ['departure_date' => '2026-10-08', 'departure_time' => '08:30', 'expected_arrival_date' => '2026-10-08', 'expected_arrival_time' => '09:00', 'estimated_expense' => 0],
            'transportation' => ['mode' => 'Walking', 'flight_number' => 'STALE']],
        ['from_location' => 'Office', 'destination' => 'South', 'purpose' => 'Deliver', 'internal_activity_type' => 'Delivery / Pickup',
            'travel' => ['departure_date' => '2026-10-09'], 'transportation' => ['mode' => 'Air', 'airline' => 'Local Air', 'flight_number' => 'LA42']],
    ];
    $service->sync($trip, OfficialBusinessTrip::CLASSIFICATION_INTERNAL, $rows);

    $details = $trip->details()->with(['travelDetail', 'transportationDetail'])->orderBy('sort_order')->get();
    expect($details)->toHaveCount(2)
        ->and(OfficialBusinessTripDetailTravel::whereIn('official_business_trip_detail_id', $details->pluck('id'))->count())->toBe(2)
        ->and(OfficialBusinessTripDetailTransportation::whereIn('official_business_trip_detail_id', $details->pluck('id'))->count())->toBe(2)
        ->and($details[0]->travelDetail->estimated_expense)->toBe('0.00')
        ->and($details[0]->transportationDetail->flight_number)->toBeNull();

    $reordered = [
        ['id' => $details[1]->id, ...$rows[1], 'from_location' => 'Revised Office', 'destination' => 'Revised South'],
        ['id' => $details[0]->id, ...$rows[0]],
    ];
    $service->sync($trip, OfficialBusinessTrip::CLASSIFICATION_INTERNAL, $reordered);
    $ordered = $trip->details()->with(['travelDetail', 'transportationDetail'])->orderBy('sort_order')->get();
    expect($ordered->pluck('id')->all())->toBe([$details[1]->id, $details[0]->id])
        ->and($ordered[0]->from_location)->toBe('Revised Office')
        ->and($ordered[0]->travelDetail->departure_date->toDateString())->toBe('2026-10-09')
        ->and($ordered[0]->transportationDetail->flight_number)->toBe('LA42');

    $generalAttachment = ActivityAttachment::create([
        'activity_type' => 'OFFICIAL_BUSINESS_TRAVEL', 'activity_id' => $trip->id, 'filename' => 'general.pdf',
    ]);
    $detailAttachment = $details[0]->attachments()->create([
        'activity_type' => 'OFFICIAL_BUSINESS_TRAVEL', 'activity_id' => $trip->id, 'filename' => 'detail.pdf',
    ]);

    try {
        $service->sync($trip, OfficialBusinessTrip::CLASSIFICATION_INTERNAL, [$reordered[0]]);
        test()->fail('A detail with linked evidence cannot be removed silently.');
    } catch (\Illuminate\Validation\ValidationException $exception) {
        expect(collect($exception->errors())->flatten()->all())->toContain(
            'Remove or intentionally relink attachments for O.B. Detail 2 before removing the detail.'
        );
    }
    expect($trip->details()->count())->toBe(2)
        ->and(DB::table('official_business_trip_detail_travel')->whereIn('official_business_trip_detail_id', $trip->details()->select('id'))->count())->toBe(2)
        ->and(DB::table('official_business_trip_detail_transportation')->whereIn('official_business_trip_detail_id', $trip->details()->select('id'))->count())->toBe(2)
        ->and(DB::table('official_business_trip_detail_travel')->whereNotIn('official_business_trip_detail_id', $trip->details()->select('id'))->count())->toBe(0)
        ->and(DB::table('official_business_trip_detail_transportation')->whereNotIn('official_business_trip_detail_id', $trip->details()->select('id'))->count())->toBe(0);
    expect($generalAttachment->fresh()->official_business_trip_detail_id)->toBeNull()
        ->and($detailAttachment->fresh()->official_business_trip_detail_id)->toBe($details[0]->id)
        ->and(ActivityAttachment::whereKey($generalAttachment->id)->exists())->toBeTrue()
        ->and(ActivityAttachment::whereKey($detailAttachment->id)->exists())->toBeTrue();

    app(OfficialBusinessCreateAttachmentService::class)->prepareExisting($trip, [[
        'id' => $detailAttachment->id, 'remove' => true,
    ]], [$reordered[0]]);
    $service->sync($trip, OfficialBusinessTrip::CLASSIFICATION_INTERNAL, [$reordered[0]]);
    expect($trip->details()->count())->toBe(1)
        ->and(ActivityAttachment::whereKey($detailAttachment->id)->exists())->toBeFalse()
        ->and(ActivityAttachment::whereKey($generalAttachment->id)->exists())->toBeTrue();
});

test('classified submit validation reports row fields while drafts accept incomplete children', function (): void {
    $service = app(OfficialBusinessCreateDetailService::class);
    $row = ['from_location' => 'Office', 'destination' => 'Client', 'purpose' => 'Visit',
        'activity_event_name' => 'Forum', 'social_activity_type' => 'Networking',
        'travel' => [], 'transportation' => []];

    expect($service->validateRows([$row], OfficialBusinessTrip::CLASSIFICATION_SOCIAL, true)[0]['travel'])->toBe([]);
    try {
        $service->validateRows([$row], OfficialBusinessTrip::CLASSIFICATION_SOCIAL, false);
        test()->fail('Incomplete classified travel must not submit.');
    } catch (\Illuminate\Validation\ValidationException $exception) {
        $messages = collect($exception->errors())->flatten()->all();
        expect($messages)->toContain('O.B. Detail 1 Departure Date is required.')
            ->and($messages)->toContain('O.B. Detail 1 Expected Arrival Time is required.')
            ->and($messages)->toContain('O.B. Detail 1 Mode of Transportation is required.');
    }
});

test('zero expense and walking transportation satisfy classified travel completeness', function (): void {
    $row = ['from_location' => 'Office', 'destination' => 'Nearby', 'purpose' => 'Meeting',
        'activity_event_name' => 'Local forum', 'social_activity_type' => 'Networking',
        'travel' => ['departure_date' => '2026-10-08', 'departure_time' => '08:00',
            'expected_arrival_date' => '2026-10-08', 'expected_arrival_time' => '08:15', 'estimated_expense' => 0],
        'transportation' => ['mode' => 'Walking']];

    $validated = app(OfficialBusinessCreateDetailService::class)
        ->validateRows([$row], OfficialBusinessTrip::CLASSIFICATION_SOCIAL, false);

    expect($validated[0]['travel']['estimated_expense'])->toBe(0)
        ->and($validated[0]['transportation']['mode'])->toBe('Walking');
});

test('mode-specific submit validation identifies the detail and missing field', function (): void {
    $row = ['from_location' => 'Office', 'destination' => 'Airport', 'purpose' => 'Flight',
        'activity_event_name' => 'Travel', 'social_activity_type' => 'Networking',
        'travel' => ['departure_date' => '2026-10-08', 'departure_time' => '08:00',
            'expected_arrival_date' => '2026-10-08', 'expected_arrival_time' => '10:00'],
        'transportation' => ['mode' => 'Air', 'airline' => 'Air Co']];

    try {
        app(OfficialBusinessCreateDetailService::class)
            ->validateRows([$row], OfficialBusinessTrip::CLASSIFICATION_SOCIAL, false);
        test()->fail('Missing flight number must fail submission.');
    } catch (\Illuminate\Validation\ValidationException $exception) {
        expect(collect($exception->errors())->flatten()->all())
            ->toContain('O.B. Detail 1 Flight Number is required.');
    }
});

test('all transportation modes persist only their compatible fields', function (): void {
    $trip = createObV3CreateFoundationTrip($this, ['workflow_version' => 3]);
    $modeFields = [
        'Company Vehicle' => ['vehicle' => 'Van', 'plate_number' => 'ABC-1', 'driver' => 'Lee'],
        'Private Vehicle' => ['vehicle' => 'Car', 'plate_number' => 'XYZ-2', 'owner_driver' => 'Sam'],
        'Ride-Hailing' => ['provider' => 'Grab'],
        'Taxi' => ['other_details' => 'Metered'],
        'Public Transportation' => ['transport_type' => 'Train'],
        'Rental Vehicle' => ['rental_provider' => 'Rent Co', 'vehicle' => 'SUV', 'plate_number' => 'RENT-1'],
        'Air' => ['airline' => 'Air Co', 'flight_number' => 'AC10'],
        'Sea/Ferry' => ['shipping_line' => 'Ferry Co', 'vessel_trip' => 'Vessel 2'],
        'Walking' => [],
        'Other' => ['other_details' => 'Specify mode'],
    ];
    $rows = [];
    foreach ($modeFields as $mode => $fields) {
        $rows[] = ['from_location' => 'A', 'destination' => 'B', 'purpose' => 'Test',
            'internal_activity_type' => 'Office Operations', 'transportation' => ['mode' => $mode, ...$fields, 'stale_field' => 'discard'], 'travel' => []];
    }
    $service = app(OfficialBusinessCreateDetailService::class);
    $service->sync($trip, OfficialBusinessTrip::CLASSIFICATION_INTERNAL, $rows);
    $saved = $trip->details()->with('transportationDetail')->orderBy('sort_order')->get();
    expect($saved->pluck('transportationDetail.mode')->all())->toBe(array_keys($modeFields));
    foreach ($saved as $row) {
        expect($row->transportationDetail->getAttributes())->not->toHaveKey('stale_field');
    }
});

test('detail attachment linkage extends generic attachments without changing general attachments', function (): void {
    $detail = app(OfficialBusinessDetailService::class)->create(createObV3CreateFoundationTrip($this));
    $general = ActivityAttachment::create([
        'activity_type' => 'OFFICIAL_BUSINESS_TRAVEL',
        'activity_id' => $detail->official_business_trip_id,
        'filename' => 'general.pdf',
    ]);
    $rowAttachment = $detail->attachments()->create([
        'activity_type' => 'OFFICIAL_BUSINESS_TRAVEL',
        'activity_id' => $detail->official_business_trip_id,
        'filename' => 'row.pdf',
    ]);

    expect($general->official_business_trip_detail_id)->toBeNull()
        ->and($rowAttachment->official_business_trip_detail_id)->toBe($detail->id)
        ->and($detail->attachments->pluck('id')->all())->toBe([$rowAttachment->id]);
});

test('classified create attachments persist detail links and repeated saves update one attachment', function (): void {
    Storage::fake('public');
    $trip = createObV3CreateFoundationTrip($this, ['workflow_version' => 3]);
    $detailRow = ['client_key' => 'detail-draft-key', 'from_location' => 'Office', 'destination' => 'Client', 'purpose' => 'Visit'];
    app(OfficialBusinessCreateDetailService::class)->sync($trip, OfficialBusinessTrip::CLASSIFICATION_INTERNAL, [$detailRow]);
    $file = UploadedFile::fake()->createWithContent('memo.pdf', 'test pdf');
    $request = Request::create('/', 'POST');
    $request->files->set('attachment_files', [0 => $file]);
    $service = app(OfficialBusinessCreateAttachmentService::class);
    $row = ['type' => 'Memo', 'detail_key' => 'detail-draft-key', 'description' => 'Office visit evidence'];

    $service->persist($trip, [$row], [$detailRow], $request);
    $attachment = ActivityAttachment::query()->where('activity_id', $trip->id)->sole();
    expect($attachment->official_business_trip_detail_id)->toBe($trip->details()->sole()->id)
        ->and($attachment->category)->toBe('Memo')
        ->and($attachment->metadata['description'])->toBe('Office visit evidence')
        ->and(Storage::disk('public')->exists($attachment->storage_path))->toBeTrue();

    $firstRow = $detailRow + ['id' => $trip->details()->sole()->id];
    $secondRow = ['client_key' => 'detail-second-key', 'from_location' => 'Office', 'destination' => 'Other', 'purpose' => 'Deliver'];
    $details = app(OfficialBusinessCreateDetailService::class);
    $details->sync($trip, OfficialBusinessTrip::CLASSIFICATION_INTERNAL, [$firstRow, $secondRow]);
    $secondRow['id'] = $trip->details()->orderBy('sort_order')->get()[1]->id;
    $details->sync($trip, OfficialBusinessTrip::CLASSIFICATION_INTERNAL, [$secondRow, $firstRow]);
    expect($trip->details()->orderBy('sort_order')->get()->pluck('id')->all())->toBe([$secondRow['id'], $firstRow['id']])
        ->and($attachment->fresh()->official_business_trip_detail_id)->toBe($firstRow['id']);

    $requestWithoutFile = Request::create('/', 'POST');
    $service->persist($trip, [$row + ['id' => $attachment->id]], [$secondRow, $firstRow], $requestWithoutFile);
    expect(ActivityAttachment::query()->where('activity_id', $trip->id)->count())->toBe(1);
});

test('classified create attachment validation rejects an attachment owned by another O.B.', function (): void {
    $trip = createObV3CreateFoundationTrip($this, ['workflow_version' => 3]);
    $foreignTrip = createObV3CreateFoundationTrip($this, ['workflow_version' => 3]);
    $foreignAttachment = ActivityAttachment::create([
        'activity_type' => 'OFFICIAL_BUSINESS_TRAVEL', 'activity_id' => $foreignTrip->id, 'filename' => 'foreign.pdf',
    ]);
    $request = Request::create('/', 'POST', ['attachment_rows_json' => json_encode([
        ['id' => $foreignAttachment->id, 'type' => 'Memo', 'detail_key' => '', 'description' => ''],
    ])]);

    expect(fn () => app(OfficialBusinessCreateAttachmentService::class)->validate($request, $trip, [], true))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('suggestion service remembers From Destination and Purpose values', function (): void {
    $service = app(OfficialBusinessSuggestionService::class);

    $from = $service->remember('from', 'JK&C Office');
    $destination = $service->remember('destination', 'BIR Cebu');
    $purpose = $service->remember('purpose', 'Submit annual return');

    expect($from->field_type)->toBe('from')
        ->and($destination->field_type)->toBe('destination')
        ->and($purpose->field_type)->toBe('purpose');
});

test('suggestions deduplicate case-insensitively and track frequency and recency', function (): void {
    Carbon::setTestNow('2026-10-07 08:00:00');
    $service = app(OfficialBusinessSuggestionService::class);
    $first = $service->remember('from', '  JK&C   Office  ');
    Carbon::setTestNow('2026-10-07 09:30:00');
    $second = $service->remember('from', 'jk&c office');

    expect($second->id)->toBe($first->id)
        ->and($second->normalized_value)->toBe('jk&c office')
        ->and($second->frequency_count)->toBe(2)
        ->and($second->last_used_at->format('Y-m-d H:i:s'))->toBe('2026-10-07 09:30:00')
        ->and($service->suggest('from', 'office')->sole()->id)->toBe($first->id);

    Carbon::setTestNow();
});

test('classification does not alter existing ticket relationships or parent workflow fields', function (): void {
    $company = Company::create(['company_name' => 'Ticket Client']);
    $trip = createObV3CreateFoundationTrip($this, [
        'workflow_version' => 3,
        'is_client_travel' => true,
        'status' => 'Approved',
        'movement_status' => 'Departed',
        'remarks' => 'Keep this remark',
    ]);
    $ticket = $trip->tripTickets()->create(['company_id' => $company->id]);

    $trip->update(['classification' => OfficialBusinessTrip::CLASSIFICATION_CLIENT]);

    expect($trip->fresh()->status)->toBe('Approved')
        ->and($trip->fresh()->movement_status)->toBe('Departed')
        ->and($trip->fresh()->remarks)->toBe('Keep this remark')
        ->and($trip->fresh()->tripTickets()->sole()->id)->toBe($ticket->id);
});
