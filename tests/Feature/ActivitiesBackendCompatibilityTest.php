<?php

use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\TaskTimer;
use App\Models\Call;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Event;
use App\Models\Meeting;
use App\Models\OfficialBusinessTrip;
use App\Models\User;
use App\Http\Controllers\MessageConversationController;
use App\Models\MessageConversation;
use App\Models\MessageEntry;
use App\Support\ActivityDuplicateGuard;
use App\Support\ActivityTimelineBuilder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->withoutMiddleware();
    Http::preventStrayRequests();

    Schema::create('companies', function (Blueprint $table) {
        $table->id();
        $table->string('company_name')->nullable();
        $table->string('email')->nullable();
        $table->string('phone')->nullable();
        $table->timestamps();
    });

    Schema::create('contacts', function (Blueprint $table) {
        $table->id();
        $table->string('first_name')->nullable();
        $table->string('middle_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('name_extension')->nullable();
        $table->string('company_name')->nullable();
        $table->string('email')->nullable();
        $table->string('phone')->nullable();
        $table->timestamps();
    });

    Schema::create('deals', function (Blueprint $table) {
        $table->id();
        $table->string('deal_code')->nullable();
        $table->string('deal_name')->nullable();
        $table->timestamps();
    });

    Schema::create('projects', function (Blueprint $table) {
        $table->id();
        $table->string('project_code')->nullable();
        $table->string('name')->nullable();
        $table->timestamps();
    });

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password')->nullable();
        $table->string('role')->nullable();
        $table->boolean('is_active')->default(true);
        $table->timestamp('archived_at')->nullable();
        $table->timestamps();
    });

    Schema::create('employees', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->unsignedBigInteger('department_id')->nullable();
        $table->string('email')->nullable();
        $table->string('work_email')->nullable();
        $table->string('company_email')->nullable();
        $table->timestamps();
    });

    Schema::create('task_templates', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->text('description')->nullable();
        $table->unsignedBigInteger('parent_template_id')->nullable();
        $table->string('priority')->default('NORMAL');
        $table->unsignedInteger('estimated_duration_minutes')->nullable();
        $table->integer('relative_start_days')->nullable();
        $table->integer('relative_due_days')->nullable();
        $table->text('instructions')->nullable();
        $table->string('status')->default('ACTIVE');
        $table->string('category')->nullable();
        $table->unsignedBigInteger('created_by_user_id')->nullable();
        $table->timestamps();
    });

    Schema::create('tasks', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('parent_task_id')->nullable();
        $table->unsignedInteger('sort_order')->default(0);
        $table->string('name');
        $table->string('due_date')->nullable();
        $table->dateTime('start_date')->nullable();
        $table->unsignedInteger('estimated_duration_minutes')->nullable();
        $table->unsignedInteger('actual_duration_minutes')->nullable();
        $table->string('status')->nullable();
        $table->string('priority')->nullable();
        $table->string('recurrence')->nullable();
        $table->json('recurrence_config')->nullable();
        $table->string('reminder_type')->nullable();
        $table->unsignedInteger('reminder_value')->nullable();
        $table->dateTime('completed_at')->nullable();
        $table->dateTime('cancelled_at')->nullable();
        $table->unsignedBigInteger('task_template_id')->nullable();
        $table->unsignedBigInteger('company_id')->nullable();
        $table->unsignedBigInteger('contact_id')->nullable();
        $table->unsignedBigInteger('deal_id')->nullable();
        $table->unsignedBigInteger('project_id')->nullable();
        $table->string('related_to')->nullable();
        $table->string('owner')->nullable();
        $table->unsignedBigInteger('assigned_to_user_id')->nullable();
        $table->text('description')->nullable();
        $table->unsignedBigInteger('prepared_by_user_id')->nullable();
        $table->text('client_report_description')->nullable();
        $table->boolean('include_in_client_report')->default(false);
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('task_assignments', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('task_id');
        $table->unsignedBigInteger('user_id');
        $table->string('role')->default('assignee');
        $table->timestamps();
    });

    Schema::create('task_dependencies', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('blocking_task_id');
        $table->unsignedBigInteger('dependent_task_id');
        $table->string('dependency_type')->default('BLOCKS');
        $table->text('notes')->nullable();
        $table->timestamps();
    });

    Schema::create('task_timers', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('task_id');
        $table->unsignedBigInteger('user_id');
        $table->dateTime('started_at');
        $table->dateTime('stopped_at')->nullable();
        $table->dateTime('paused_at')->nullable();
        $table->unsignedInteger('paused_seconds')->default(0);
        $table->text('notes')->nullable();
        $table->timestamps();
    });

    Schema::create('events', function (Blueprint $table) {
        $table->id();
        foreach (['title', 'related_account', 'event_type', 'from', 'to', 'venue', 'mode', 'role', 'status', 'related_to', 'host'] as $column) {
            $table->string($column)->nullable();
        }
        $table->integer('participants')->nullable();
        $table->decimal('estimated_hours', 8, 2)->nullable();
        $table->decimal('actual_hours', 8, 2)->nullable();
        foreach (['company_id', 'contact_id', 'deal_id', 'project_id'] as $column) {
            $table->unsignedBigInteger($column)->nullable();
        }
        $table->unsignedBigInteger('prepared_by_user_id')->nullable();
        $table->text('client_report_description')->nullable();
        $table->boolean('include_in_client_report')->default(false);
        $table->timestamps();
    });

    Schema::create('calls', function (Blueprint $table) {
        $table->id();
        foreach (['contact', 'to', 'from', 'type', 'start_time', 'start_hour', 'duration', 'related_to', 'owner', 'purpose', 'agenda', 'audio_path'] as $column) {
            $table->string($column)->nullable();
        }
        $table->boolean('completed')->default(false);
        foreach (['company_id', 'contact_id', 'deal_id', 'project_id'] as $column) {
            $table->unsignedBigInteger($column)->nullable();
        }
        $table->unsignedBigInteger('prepared_by_user_id')->nullable();
        $table->text('client_report_description')->nullable();
        $table->boolean('include_in_client_report')->default(false);
        $table->timestamps();
    });

    Schema::create('meetings', function (Blueprint $table) {
        $table->id();
        foreach (['title', 'meeting_type', 'related_account', 'related_project', 'mode', 'owner', 'date', 'time', 'duration', 'actual_duration', 'location', 'related_to', 'status', 'video_path', 'audio_path'] as $column) {
            $table->string($column)->nullable();
        }
        $table->integer('attendees')->nullable();
        $table->text('description')->nullable();
        foreach (['has_video', 'has_audio', 'has_transcript', 'has_minutes'] as $column) {
            $table->boolean($column)->default(false);
        }
        foreach (['company_id', 'contact_id', 'deal_id', 'project_id'] as $column) {
            $table->unsignedBigInteger($column)->nullable();
        }
        $table->unsignedBigInteger('prepared_by_user_id')->nullable();
        $table->text('client_report_description')->nullable();
        $table->boolean('include_in_client_report')->default(false);
        $table->timestamps();
    });

    Schema::create('notes', function (Blueprint $table) {
        $table->id();
        $table->text('content');
        $table->string('owner')->nullable();
        $table->unsignedBigInteger('noteable_id');
        $table->string('noteable_type');
        $table->timestamps();
    });

    Schema::create('message_conversations', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('company_id')->nullable();
        $table->unsignedBigInteger('contact_id')->nullable();
        $table->unsignedBigInteger('assigned_user_id')->nullable();
        $table->string('conversation_title')->nullable();
        $table->string('channel');
        $table->string('line_account')->nullable();
        $table->string('handling')->default('Open');
        $table->boolean('is_linked')->default(true);
        $table->timestamp('last_message_at')->nullable();
        $table->string('external_conversation_id')->nullable();
        $table->json('technical_metadata')->nullable();
        $table->unsignedBigInteger('prepared_by_user_id')->nullable();
        $table->text('client_report_description')->nullable();
        $table->boolean('include_in_client_report')->default(false);
        $table->timestamps();
    });

    Schema::create('message_entries', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('message_conversation_id');
        $table->string('channel', 40)->nullable();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->string('direction');
        $table->string('sender')->nullable();
        $table->string('recipient')->nullable();
        $table->text('body');
        $table->string('message_type', 40)->default('text');
        $table->string('delivery_status')->default('Pending');
        $table->string('external_message_id')->nullable();
        $table->timestamp('sent_at')->nullable();
        $table->timestamp('received_at')->nullable();
        $table->timestamp('delivered_at')->nullable();
        $table->timestamp('read_at')->nullable();
        $table->json('technical_metadata')->nullable();
        $table->timestamps();
    });

    // Minimal attachment schema required by MessageEntry::activityAttachments().
    Schema::create('activity_attachments', function (Blueprint $table) {
        $table->id();
        $table->timestamps();
    });

    Schema::create('message_entry_attachments', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('message_entry_id');
        $table->unsignedBigInteger('activity_attachment_id');
        $table->timestamps();
        $table->unique(['message_entry_id', 'activity_attachment_id']);
    });

    Schema::create('official_business_trips', function (Blueprint $table) {
        $table->id();
        $table->string('ob_reference_no')->nullable();
        $table->unsignedBigInteger('employee_id')->nullable();
        $table->string('employee_name');
        $table->string('destination');
        $table->text('purpose')->nullable();
        $table->date('date_from');
        $table->date('date_to')->nullable();
        $table->dateTime('actual_departure')->nullable();
        $table->dateTime('actual_return')->nullable();
        $table->boolean('is_client_travel')->default(false);
        $table->string('client_name')->nullable();
        $table->json('team_members')->nullable();
        $table->json('attachment_paths')->nullable();
        $table->string('status')->default('Pending');
        $table->unsignedBigInteger('created_by')->nullable();
        $table->unsignedBigInteger('prepared_by_user_id')->nullable();
        $table->text('client_report_description')->nullable();
        $table->boolean('include_in_client_report')->default(false);
        $table->timestamps();
        $table->softDeletes();
    });

    $migration = require database_path('migrations/2026_08_24_000002_complete_activities_v2_safe_contracts.php');
    $migration->up();
    $taskCalendarMigration = require database_path('migrations/2026_08_26_140000_add_calendar_sync_fields_to_tasks_table.php');
    $taskCalendarMigration->up();
    $eventCalendarMigration = require database_path('migrations/2026_08_26_140001_add_calendar_sync_fields_to_events_table.php');
    $eventCalendarMigration->up();

    DB::table('companies')->insert(['id' => 1, 'company_name' => 'Acme', 'email' => 'acme@example.test']);
    DB::table('contacts')->insert(['id' => 1, 'first_name' => 'Alex', 'last_name' => 'Cruz', 'email' => 'alex@example.test']);
    DB::table('deals')->insert(['id' => 1, 'deal_code' => 'D-001', 'deal_name' => 'Acme Deal']);
    DB::table('projects')->insert(['id' => 1, 'project_code' => 'P-001', 'name' => 'Acme Project']);
    DB::table('users')->insert(['id' => 1, 'name' => 'Activity Owner', 'email' => 'owner@example.test', 'role' => 'Admin']);
    DB::table('users')->insert(['id' => 2, 'name' => 'Spoofed User', 'email' => 'spoofed@example.test']);
});

test('activity core metadata is server prepared immutable and boolean across source CRUD', function () {
    $this->actingAs(User::findOrFail(1));

    $cases = [
        ['tasks', ['name' => 'Core task'], ['name' => 'Core task updated']],
        ['events', ['title' => 'Core event'], ['title' => 'Core event updated']],
        ['calls', ['contact' => 'Core contact'], ['contact' => 'Core contact updated']],
        ['meetings', ['title' => 'Core meeting'], ['title' => 'Core meeting updated']],
    ];

    foreach ($cases as [$endpoint, $createPayload, $updatePayload]) {
        $created = $this->postJson("/api/{$endpoint}", $createPayload + [
            'prepared_by_user_id' => 2,
            'client_report_description' => "{$endpoint} client summary",
            'include_in_client_report' => true,
            'related_to' => 'Legacy activity label',
        ])->assertOk()
            ->assertJsonPath('prepared_by_user_id', 1)
            ->assertJsonPath('prepared_by.id', 1)
            ->assertJsonPath('client_report_description', "{$endpoint} client summary")
            ->assertJsonPath('include_in_client_report', true)
            ->assertJsonPath('related_to', 'Legacy activity label');

        $this->putJson("/api/{$endpoint}/{$created->json('id')}", $updatePayload + [
            'prepared_by_user_id' => 2,
            'client_report_description' => "{$endpoint} updated summary",
            'include_in_client_report' => false,
            'related_to' => 'Legacy activity label updated',
        ])->assertOk()
            ->assertJsonPath('prepared_by_user_id', 1)
            ->assertJsonPath('prepared_by.id', 1)
            ->assertJsonPath('client_report_description', "{$endpoint} updated summary")
            ->assertJsonPath('include_in_client_report', false)
            ->assertJsonPath('related_to', 'Legacy activity label updated');
    }

    $activities = collect($this->getJson('/api/activities')->assertOk()->json('activities'));

    expect($activities)->toHaveCount(4)
        ->and($activities->every(fn (array $activity) => $activity['prepared_by_user_id'] === 1))->toBeTrue()
        ->and($activities->every(fn (array $activity) => $activity['prepared_by']['id'] === 1))->toBeTrue()
        ->and($activities->every(fn (array $activity) => $activity['include_in_client_report'] === false))->toBeTrue();
});

test('message conversation is one prepared activity and entries remain children', function () {
    $this->withMiddleware();
    $this->actingAs(User::findOrFail(1));

    $created = $this->postJson('/api/message-conversations', [
        'channel' => 'SMS',
        'body' => 'First entry',
        'prepared_by_user_id' => 2,
        'client_report_description' => 'Conversation summary',
        'include_in_client_report' => true,
    ])->assertCreated()
        ->assertJsonPath('prepared_by_user_id', 1)
        ->assertJsonPath('prepared_by.id', 1)
        ->assertJsonPath('client_report_description', 'Conversation summary')
        ->assertJsonPath('include_in_client_report', true);

    $conversationId = $created->json('id');

    $this->putJson("/api/message-conversations/{$conversationId}", [
        'prepared_by_user_id' => 2,
        'client_report_description' => 'Updated conversation summary',
        'include_in_client_report' => false,
    ])->assertOk()
        ->assertJsonPath('prepared_by_user_id', 1)
        ->assertJsonPath('client_report_description', 'Updated conversation summary')
        ->assertJsonPath('include_in_client_report', false);

    $this->postJson("/api/message-conversations/{$conversationId}/messages", [
        'direction' => 'Incoming',
        'body' => 'Second entry',
    ])->assertCreated();

    expect(MessageConversation::count())->toBe(1)
        ->and(MessageConversation::findOrFail($conversationId)->entries()->count())->toBe(2)
        ->and(MessageConversation::findOrFail($conversationId)->prepared_by_user_id)->toBe(1);
});

test('event call and meeting create and update responses include normalized relationships', function () {
    $this->actingAs(User::findOrFail(1));

    $cases = [
        ['events', ['title' => 'Launch', 'status' => 'Scheduled'], 'title', 'Launch updated'],
        ['calls', ['contact' => 'Alex Cruz', 'type' => 'Outbound'], 'contact', 'Alex Cruz updated'],
        ['meetings', ['title' => 'Kickoff', 'status' => 'completed'], 'title', 'Kickoff updated'],
    ];

    foreach ($cases as [$endpoint, $payload, $updatedField, $updatedValue]) {
        $payload += [
            'company_id' => 1,
            'contact_id' => 1,
            'deal_id' => 1,
            'project_id' => 1,
            'related_to' => 'Acme, Alex Cruz',
        ];

        $created = $this->postJson("/api/{$endpoint}", $payload)
            ->assertOk()
            ->assertJsonPath('company.id', 1)
            ->assertJsonPath('contact_record.id', 1)
            ->assertJsonPath('deal.id', 1)
            ->assertJsonPath('project.id', 1)
            ->assertJsonPath('related_to', 'Acme, Alex Cruz');

        $payload[$updatedField] = $updatedValue;

        $this->putJson("/api/{$endpoint}/{$created->json('id')}", $payload)
            ->assertOk()
            ->assertJsonPath($updatedField, $updatedValue)
            ->assertJsonPath('company.id', 1)
            ->assertJsonPath('contact_record.id', 1)
            ->assertJsonPath('deal.id', 1)
            ->assertJsonPath('project.id', 1);

        $this->actingAs(User::findOrFail(2))
            ->putJson("/api/{$endpoint}/{$created->json('id')}", $payload)
            ->assertForbidden();
        $this->actingAs(User::findOrFail(1));
    }
});

test('event frontend payload fields persist through create update and delete', function () {
    $this->actingAs(User::findOrFail(1));
    config()->set('services.google_calendar', [
        'calendar_id' => 'primary@example.test',
        'access_token' => 'fake-calendar-token',
        'api_base' => 'https://calendar.test/v3',
        'timezone' => 'Asia/Manila',
        'timeout' => 5,
    ]);
    Http::fake(fn () => Http::response(['id' => 'payload-calendar-event']));

    $created = $this->postJson('/api/events', [
        'title' => 'Client workshop',
        'related_account' => 'Acme',
        'event_type' => 'Training',
        'from' => '2026-08-20 08:00',
        'to' => '2026-08-20 17:00',
        'venue' => 'Conference Hall',
        'mode' => 'Physical',
        'participants' => 40,
        'role' => 'Organizer',
        'status' => 'Scheduled',
        'estimated_hours' => 9,
        'actual_hours' => 0,
        'related_to' => 'Acme, Alex Cruz',
        'host' => 'owner@example.test',
        'company_id' => 1,
        'contact_id' => 1,
        'deal_id' => 1,
        'project_id' => 1,
    ])->assertSuccessful()
        ->assertJsonPath('event_type', 'Training')
        ->assertJsonPath('venue', 'Conference Hall')
        ->assertJsonPath('related_to', 'Acme, Alex Cruz')
        ->assertJsonPath('company.id', 1)
        ->assertJsonPath('contact_record.id', 1);

    $eventId = $created->json('id');

    $this->putJson("/api/events/{$eventId}", [
        'title' => 'Client workshop updated',
        'status' => 'Completed',
        'actual_hours' => 8.5,
        'related_to' => 'Legacy label updated',
        'company_id' => 1,
        'contact_id' => 1,
        'deal_id' => 1,
        'project_id' => 1,
    ])->assertSuccessful()
        ->assertJsonPath('title', 'Client workshop updated')
        ->assertJsonPath('status', 'Completed')
        ->assertJsonPath('related_to', 'Legacy label updated')
        ->assertJsonPath('project.id', 1);

    $this->deleteJson("/api/events/{$eventId}")->assertNoContent();

    expect(Event::find($eventId))->toBeNull();
});

test('legacy related_to-only payloads remain valid and normalized ids reject missing records', function () {
    $this->postJson('/api/events', [
        'title' => 'Legacy event',
        'related_to' => 'Legacy account label',
    ])->assertOk()
        ->assertJsonPath('related_to', 'Legacy account label')
        ->assertJsonPath('company', null)
        ->assertJsonPath('contact_record', null);

    foreach (['events' => ['title' => 'Invalid'], 'calls' => ['contact' => 'Invalid'], 'meetings' => ['title' => 'Invalid']] as $endpoint => $payload) {
        $this->postJson("/api/{$endpoint}", $payload + ['company_id' => 999999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('company_id');
    }
});

test('activities index returns all core types and does not persist computed meeting status', function () {
    $this->actingAs(User::findOrFail(1));

    $relationFields = [
        'related_to' => 'Legacy label',
        'company_id' => 1,
        'contact_id' => 1,
        'deal_id' => 1,
        'project_id' => 1,
    ];

    Task::create([
        'name' => 'Indexed task',
        'start_date' => '2026-08-19 09:15:00',
        'estimated_duration_minutes' => 90,
        'actual_duration_minutes' => 75,
        'status' => 'Open',
        'owner' => 'owner@example.test',
    ] + $relationFields);
    Event::create([
        'title' => 'Indexed event',
        'from' => '2026-08-20 13:30:00',
        'estimated_hours' => 3,
        'actual_hours' => 2.5,
        'status' => 'Scheduled',
        'host' => 'owner@example.test',
    ] + $relationFields);
    Call::create([
        'contact' => 'Indexed call',
        'purpose' => 'Indexed follow-up call',
        'start_time' => '2026-08-21',
        'start_hour' => '10:45',
        'duration' => '45 mins',
        'audio_path' => 'storage/audios/indexed-call.mp3',
        'owner' => 'owner@example.test',
        'completed' => true,
    ] + $relationFields);
    $meeting = Meeting::create([
        'title' => 'Indexed meeting',
        'date' => now()->subDay()->toDateString(),
        'time' => '08:00',
        'duration' => '30 mins',
        'status' => 'upcoming',
    ] + $relationFields);

    MessageConversation::create([
    'company_id' => 1,
    'contact_id' => 1,
    'assigned_user_id' => 1,
    'conversation_title' => 'Indexed Viber conversation',
    'channel' => 'Viber',
    'handling' => 'Open',
    'is_linked' => true,
    'last_message_at' => '2026-08-22 14:30:00',
    'client_report_description' => 'Indexed message summary',
    'include_in_client_report' => true,
]);

    $officialBusinessTrip = OfficialBusinessTrip::create([
        'ob_reference_no' => 'OBF-INDEXED-1',
        'employee_name' => 'Activity Owner',
        'destination' => 'Client office',
        'purpose' => 'Indexed official business',
        'date_from' => '2026-08-23',
        'is_client_travel' => true,
        'client_name' => 'Acme',
        'status' => 'Approved',
        'created_by' => 1,
        'client_report_description' => 'Indexed travel summary',
        'include_in_client_report' => true,
    ]);
    $officialBusinessTrip->prepared_by_user_id = 1;
    $officialBusinessTrip->save();

$response = $this->getJson('/api/activities')
        ->assertOk()
        ->assertJsonPath('tasks.0.related_to', 'Legacy label')
        ->assertJsonPath('events.0.related_to', 'Legacy label')
        ->assertJsonPath('events.0.company.id', 1)
        ->assertJsonPath('events.0.contact_record.id', 1)
        ->assertJsonPath('events.0.deal.id', 1)
        ->assertJsonPath('events.0.project.id', 1)
        ->assertJsonPath('calls.0.related_to', 'Legacy label')
        ->assertJsonPath('meetings.0.related_to', 'Legacy label')
        ->assertJsonPath('meetings.0.status', 'completed');

    expect($response->json('tasks'))->toHaveCount(1)
        ->and($response->json('events'))->toHaveCount(1)
        ->and($response->json('calls'))->toHaveCount(1)
        ->and($response->json('meetings'))->toHaveCount(1)
    ->and($response->json('messages'))->toHaveCount(1)
    ->and($response->json('activities'))->toHaveCount(6)
        ->and($meeting->fresh()->status)->toBe('upcoming');

    $activities = collect($response->json('activities'))->keyBy('id');

    expect($activities->keys()->all())->toContain(
        'task-1',
        'event-1',
        'call-1',
        'meeting-1',
        'message-1',
        'official-business-travel-1'
    )
        ->and($activities['task-1']['type'])->toBe('task')
        ->and($activities['task-1']['title'])->toBe('Indexed task')
        ->and($activities['task-1']['account_id'])->toBe(1)
        ->and($activities['task-1']['account'])->toBe('Acme')
        ->and($activities['task-1']['contact'])->toBe('Alex Cruz')
        ->and($activities['task-1']['deal_id'])->toBe(1)
        ->and($activities['task-1']['deal']['code'])->toBe('D-001')
        ->and($activities['task-1']['project_id'])->toBe(1)
        ->and($activities['task-1']['project']['code'])->toBe('P-001')
        ->and($activities['task-1']['source_status'])->toBe('Open')
        ->and($activities['task-1']['display_status'])->toBe('Open')
        ->and($activities['task-1']['duration_minutes'])->toBe(75)
        ->and($activities['task-1']['duration_source'])->toBe('actual')
        ->and($activities['task-1']['occurred_at'])->toContain('2026-08-19T09:15:00')
        ->and($activities['event-1']['duration_minutes'])->toBe(150)
        ->and($activities['event-1']['duration_source'])->toBe('actual')
        ->and($activities['event-1']['occurred_at'])->toContain('2026-08-20T13:30:00')
        ->and($activities['call-1']['title'])->toBe('Indexed follow-up call')
        ->and($activities['call-1']['duration_minutes'])->toBe(45)
        ->and($activities['call-1']['source_status'])->toBe('Completed')
        ->and($activities['call-1']['occurred_at'])->toContain('2026-08-21T10:45:00')
        ->and($activities['call-1']['attachments'][0]['kind'])->toBe('audio')
        ->and($activities['call-1']['attachments'][0]['path'])->toBe('storage/audios/indexed-call.mp3')
        ->and($activities['meeting-1']['duration_minutes'])->toBe(30)
        ->and($activities['meeting-1']['source_status'])->toBe('upcoming')
        ->and($activities['meeting-1']['display_status'])->toBe('completed')
        ->and($activities['meeting-1']['related_record'])->toBe('Legacy label')
    ->and($activities['message-1']['type'])->toBe('message')
    ->and($activities['message-1']['title'])->toBe('Indexed Viber conversation')
    ->and($activities['message-1']['account_id'])->toBe(1)
    ->and($activities['message-1']['account'])->toBe('Acme')
    ->and($activities['message-1']['contact_id'])->toBe(1)
    ->and($activities['message-1']['contact'])->toBe('Alex Cruz')
    ->and($activities['message-1']['source_status'])->toBe('Open')
    ->and($activities['message-1']['display_status'])->toBe('Open')
    ->and($activities['message-1']['occurred_at'])->toContain('2026-08-22T14:30:00')
    ->and($activities['message-1']['duration_minutes'])->toBeNull()
    ->and($activities['message-1']['duration_source'])->toBeNull()
    ->and($activities['message-1']['client_report_description'])->toBe('Indexed message summary')
    ->and($activities['message-1']['include_in_client_report'])->toBeTrue()
    ->and($activities['message-1']['attachments'])->toBe([])
    ->and($activities['message-1']['created_at'])->not->toBeNull()
    ->and($activities['message-1']['channel'])->toBe('Viber')
    ->and($activities['message-1']['entry_count'])->toBe(0)
    ->and($activities['official-business-travel-1']['type'])->toBe('official_business_travel')
    ->and($activities['official-business-travel-1']['source_id'])->toBe(1)
    ->and($activities['official-business-travel-1']['account'])->toBe('Acme')
    ->and($activities['official-business-travel-1']['duration_minutes'])->toBeNull()
    ->and($activities['official-business-travel-1']['client_report_description'])->toBe('Indexed travel summary')
    ->and($activities['official-business-travel-1']['include_in_client_report'])->toBeTrue();
});

test('task calendar synchronization creates patches and removes one all-day event', function () {
    $this->actingAs(User::findOrFail(1));
    Mail::fake();
    config()->set('services.google_calendar', [
        'calendar_id' => 'primary@example.test',
        'access_token' => 'fake-calendar-token',
        'api_base' => 'https://calendar.test/v3',
        'timezone' => 'Asia/Manila',
        'timeout' => 5,
    ]);
    Http::fake(fn () => Http::response(['id' => 'ordotask1']));

    $created = $this->postJson('/api/tasks', [
        'name' => 'Submit compliance report',
        'due_date' => '2026-09-10',
        'description' => 'Prepare the final filing.',
    ])->assertSuccessful()
        ->assertJsonPath('external_calendar_event_id', 'ordotask1')
        ->assertJsonPath('calendar_sync_status', 'synced');

    $taskId = $created->json('id');
    $this->putJson('/api/tasks/'.$taskId, [
        'name' => 'Submit revised compliance report',
        'due_date' => '2026-09-12',
    ])->assertSuccessful()
        ->assertJsonPath('external_calendar_event_id', 'ordotask1');

    $this->putJson('/api/tasks/'.$taskId, [
        'name' => 'Submit revised compliance report',
        'due_date' => null,
    ])->assertSuccessful()
        ->assertJsonPath('external_calendar_event_id', null)
        ->assertJsonPath('calendar_sync_status', null);

    $requests = Http::recorded();
    expect($requests->filter(fn (array $pair): bool => $pair[0]->method() === 'POST'))->toHaveCount(1)
        ->and($requests->filter(fn (array $pair): bool => $pair[0]->method() === 'PATCH'))->toHaveCount(1)
        ->and($requests->filter(fn (array $pair): bool => $pair[0]->method() === 'DELETE'))->toHaveCount(1);

    $post = $requests->first(fn (array $pair): bool => $pair[0]->method() === 'POST')[0];
    $patch = $requests->first(fn (array $pair): bool => $pair[0]->method() === 'PATCH')[0];
    $postStart = \Carbon\CarbonImmutable::parse(
        data_get($post->data(), 'start.dateTime')
    );
    $postEnd = \Carbon\CarbonImmutable::parse(
        data_get($post->data(), 'end.dateTime')
    );
    $patchStart = \Carbon\CarbonImmutable::parse(
        data_get($patch->data(), 'start.dateTime')
    );
    $patchEnd = \Carbon\CarbonImmutable::parse(
        data_get($patch->data(), 'end.dateTime')
    );

    expect($postStart->format('Y-m-d'))->toBe('2026-09-10')
        ->and($postEnd->format('Y-m-d'))->toBe('2026-09-10')
        ->and((int) $postStart->diffInMinutes($postEnd))->toBe(30)
        ->and(data_get($post->data(), 'start.timeZone'))->toBe('Asia/Manila')
        ->and(data_get($post->data(), 'end.timeZone'))->toBe('Asia/Manila')
        ->and($patchStart->format('Y-m-d'))->toBe('2026-09-12')
        ->and($patchEnd->format('Y-m-d'))->toBe('2026-09-12')
        ->and((int) $patchStart->diffInMinutes($patchEnd))->toBe(30)
        ->and(data_get($patch->data(), 'start.timeZone'))->toBe('Asia/Manila')
        ->and(data_get($patch->data(), 'end.timeZone'))->toBe('Asia/Manila')
        ->and($patch->url())->toContain('/events/ordotask1')
        ->and($post->url())->toContain('sendUpdates=none');
    Mail::assertNothingSent();
});

test('task calendar failure and deletion keep local changes authoritative', function () {
    $this->actingAs(User::findOrFail(1));
    config()->set('services.google_calendar', [
        'calendar_id' => 'primary@example.test',
        'access_token' => 'fake-calendar-token',
        'api_base' => 'https://calendar.test/v3',
        'timezone' => 'Asia/Manila',
        'timeout' => 5,
    ]);
    Http::fake(fn () => Http::response(['error' => 'provider unavailable'], 500));

    $response = $this->postJson('/api/tasks', [
        'name' => 'Locally authoritative task',
        'due_date' => '2026-09-15',
    ])->assertStatus(202)
        ->assertJsonPath('calendar_sync_status', 'failed')
        ->assertJsonStructure(['calendar_sync_warning']);
    $this->assertDatabaseHas('tasks', ['id' => $response->json('id'), 'name' => 'Locally authoritative task']);

    $task = Task::query()->create([
        'name' => 'Delete synchronized task',
        'external_calendar_event_id' => 'ordotask-delete',
        'calendar_provider' => 'google',
        'calendar_sync_status' => 'synced',
    ]);
    $this->deleteJson('/api/tasks/'.$task->id)
        ->assertStatus(202)
        ->assertJsonStructure(['calendar_sync_warning']);
    expect(Task::withTrashed()->findOrFail($task->id)->trashed())->toBeTrue();
    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE'
        && str_contains($request->url(), '/events/ordotask-delete'));
});

test('event calendar synchronization preserves exact Manila schedule and one external id', function () {
    $this->actingAs(User::findOrFail(1));
    Mail::fake();
    config()->set('services.google_calendar', [
        'calendar_id' => 'primary@example.test',
        'access_token' => 'fake-calendar-token',
        'api_base' => 'https://calendar.test/v3',
        'timezone' => 'Asia/Manila',
        'timeout' => 5,
    ]);
    Http::fake(fn () => Http::response(['id' => 'ordoevent1']));

    $created = $this->postJson('/api/events', [
        'title' => 'Client briefing',
        'from' => '2026-09-20 09:15',
        'to' => '2026-09-20 10:45',
        'venue' => 'Makati Office',
    ])->assertSuccessful()
        ->assertJsonPath('external_calendar_event_id', 'ordoevent1');
    $eventId = $created->json('id');

    $this->putJson('/api/events/'.$eventId, [
        'title' => 'Updated client briefing',
        'from' => '2026-09-20 11:00',
        'to' => '2026-09-20 12:30',
        'venue' => 'Makati Office',
    ])->assertOk()
        ->assertJsonPath('external_calendar_event_id', 'ordoevent1');

    $this->putJson('/api/events/'.$eventId, [
        'title' => 'Unscheduled client briefing',
        'from' => '2026-09-21',
        'to' => '2026-09-22',
    ])->assertOk()
        ->assertJsonPath('external_calendar_event_id', null);

    $requests = Http::recorded();
    expect($requests->filter(fn (array $pair): bool => $pair[0]->method() === 'POST'))->toHaveCount(1)
        ->and($requests->filter(fn (array $pair): bool => $pair[0]->method() === 'PATCH'))->toHaveCount(1)
        ->and($requests->filter(fn (array $pair): bool => $pair[0]->method() === 'DELETE'))->toHaveCount(1);
    $post = $requests->first(fn (array $pair): bool => $pair[0]->method() === 'POST')[0];
    $patch = $requests->first(fn (array $pair): bool => $pair[0]->method() === 'PATCH')[0];
    expect(data_get($post->data(), 'start.dateTime'))->toBe('2026-09-20T09:15:00+08:00')
        ->and(data_get($post->data(), 'end.dateTime'))->toBe('2026-09-20T10:45:00+08:00')
        ->and(data_get($post->data(), 'start.timeZone'))->toBe('Asia/Manila')
        ->and(data_get($patch->data(), 'start.dateTime'))->toBe('2026-09-20T11:00:00+08:00')
        ->and($patch->url())->toContain('/events/ordoevent1');
    Mail::assertNothingSent();
});

test('event calendar failures preserve local save and local deletion', function () {
    $this->actingAs(User::findOrFail(1));
    config()->set('services.google_calendar', [
        'calendar_id' => 'primary@example.test',
        'access_token' => 'fake-calendar-token',
        'api_base' => 'https://calendar.test/v3',
        'timezone' => 'Asia/Manila',
        'timeout' => 5,
    ]);
    Http::fake(fn () => Http::response(['error' => 'provider unavailable'], 500));

    $response = $this->postJson('/api/events', [
        'title' => 'Locally authoritative event',
        'from' => '2026-09-21 13:00',
        'to' => '2026-09-21 14:00',
    ])->assertStatus(202)
        ->assertJsonPath('calendar_sync_status', 'failed')
        ->assertJsonStructure(['calendar_sync_warning']);
    $this->assertDatabaseHas('events', ['id' => $response->json('id'), 'title' => 'Locally authoritative event']);

    $event = Event::query()->create([
        'title' => 'Delete synchronized event',
        'external_calendar_event_id' => 'ordoevent-delete',
        'calendar_provider' => 'google',
        'calendar_sync_status' => 'synced',
    ]);
    $this->deleteJson('/api/events/'.$event->id)
        ->assertStatus(202)
        ->assertJsonStructure(['calendar_sync_warning']);
    $this->assertDatabaseMissing('events', ['id' => $event->id]);
    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE'
        && str_contains($request->url(), '/events/ordoevent-delete'));
});

test('meeting deletion removes its existing calendar event without notifications', function () {
    $this->actingAs(User::findOrFail(1));
    config()->set('services.google_calendar', [
        'calendar_id' => 'primary@example.test',
        'access_token' => 'fake-calendar-token',
        'api_base' => 'https://calendar.test/v3',
        'timezone' => 'Asia/Manila',
        'timeout' => 5,
    ]);
    Http::fake(fn () => Http::response(null, 204));
    $meeting = Meeting::query()->create([
        'title' => 'Synchronized meeting',
        'external_calendar_event_id' => 'ordomeeting-delete',
        'calendar_provider' => 'google',
        'calendar_sync_status' => 'synced',
    ]);

    $this->deleteJson('/api/meetings/'.$meeting->id)->assertNoContent();
    $this->assertDatabaseMissing('meetings', ['id' => $meeting->id]);
    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE'
        && str_contains($request->url(), '/events/ordomeeting-delete')
        && str_contains($request->url(), 'sendUpdates=none'));
});

test('task reconciled management fields persist and update with legacy compatibility', function () {
    $this->actingAs(User::findOrFail(1));
    config()->set('services.google_calendar', [
        'calendar_id' => 'primary@example.test',
        'access_token' => 'fake-calendar-token',
        'api_base' => 'https://calendar.test/v3',
        'timezone' => 'Asia/Manila',
        'timeout' => 5,
    ]);
    Http::fake(fn () => Http::response(['id' => 'payload-calendar-event']));

    $created = $this->postJson('/api/tasks', [
        'name' => 'Reconciled task',
        'start_date' => '2026-08-19 09:00:00',
        'due_date' => '2026-08-20',
        'estimated_duration_minutes' => 90,
        'actual_duration_minutes' => 15,
        'status' => 'In Progress',
        'priority' => 'High',
        'recurrence' => 'Weekly',
        'recurrence_config' => ['weekday' => 'Wednesday'],
        'reminder_type' => 'minutes_before',
        'reminder_value' => 30,
        'assigned_to_user_id' => 1,
        'assigned_user_ids' => [1],
        'related_to' => 'Legacy task label',
        'company_id' => 1,
        'contact_id' => 1,
        'deal_id' => 1,
        'project_id' => 1,
    ])->assertSuccessful()
        ->assertJsonPath('estimated_duration_minutes', 90)
        ->assertJsonPath('recurrence', 'Weekly')
        ->assertJsonPath('reminder_value', 30)
        ->assertJsonPath('owner', 'owner@example.test')
        ->assertJsonPath('related_to', 'Legacy task label')
        ->assertJsonPath('assignees.0.id', 1)
        ->assertJsonPath('company.id', 1);

    $taskId = $created->json('id');

    $this->putJson("/api/tasks/{$taskId}", [
        'name' => 'Reconciled task',
        'status' => 'Completed',
        'actual_duration_minutes' => 75,
        'related_to' => 'Legacy task label updated',
    ])->assertSuccessful()
        ->assertJsonPath('status', 'Completed')
        ->assertJsonPath('actual_duration_minutes', 75)
        ->assertJsonPath('related_to', 'Legacy task label updated');

    $task = Task::findOrFail($taskId);

    expect($task->completed_at)->not->toBeNull()
        ->and($task->recurrence_config)->toBe(['weekday' => 'Wednesday']);
});

test('call and meeting frontend payload fields persist with legacy and normalized relations', function () {
    $this->actingAs(User::findOrFail(1));
    config()->set('services.google_calendar', [
        'calendar_id' => 'primary@example.test',
        'access_token' => 'fake-calendar-token',
        'api_base' => 'https://calendar.test/v3',
        'timezone' => 'Asia/Manila',
        'timeout' => 5,
    ]);
    Http::fake(fn () => Http::response(['id' => 'payload-calendar-event']));

    $call = $this->postJson('/api/calls', [
        'contact' => 'Alex Cruz / owner@example.test',
        'to' => 'Alex Cruz',
        'from' => 'owner@example.test',
        'type' => 'Outbound',
        'start_time' => '2026-08-19',
        'start_hour' => '10:30',
        'duration' => '15 mins',
        'purpose' => 'Requirements follow-up',
        'agenda' => 'Confirm next steps',
        'completed' => false,
        'related_to' => 'Acme, Alex Cruz',
        'company_id' => 1,
        'contact_id' => 1,
        'deal_id' => 1,
        'project_id' => 1,
    ])->assertOk()
        ->assertJsonPath('purpose', 'Requirements follow-up')
        ->assertJsonPath('related_to', 'Acme, Alex Cruz')
        ->assertJsonPath('contact_record.id', 1);

    $this->putJson('/api/calls/'.$call->json('id'), [
        'contact' => 'Alex Cruz / owner@example.test',
        'completed' => true,
        'agenda' => 'Completed agenda',
        'related_to' => 'Legacy call label',
    ])->assertOk()
        ->assertJsonPath('completed', 1)
        ->assertJsonPath('agenda', 'Completed agenda')
        ->assertJsonPath('related_to', 'Legacy call label');

    $meeting = $this->postJson('/api/meetings', [
        'title' => 'Client kickoff',
        'meeting_type' => 'Client Meeting',
        'related_account' => 'Acme',
        'related_project' => 'Acme Project',
        'mode' => 'Online',
        'owner' => 'owner@example.test',
        'date' => now()->addDay()->toDateString(),
        'time' => '09:00',
        'duration' => '1 hr 30 mins',
        'actual_duration' => '0 mins',
        'attendees' => 1,
        'related_to' => 'Alex Cruz',
        'description' => 'Kickoff agenda',
        'company_id' => 1,
        'contact_id' => 1,
        'deal_id' => 1,
        'project_id' => 1,
    ])->assertOk()
        ->assertJsonPath('meeting_type', 'Client Meeting')
        ->assertJsonPath('mode', 'Online')
        ->assertJsonPath('related_to', 'Alex Cruz')
        ->assertJsonPath('project.id', 1);

    $this->putJson('/api/meetings/'.$meeting->json('id'), [
        'title' => 'Client kickoff updated',
        'actual_duration' => '75 mins',
        'status' => 'completed',
        'related_to' => 'Alex Cruz, Acme',
    ])->assertOk()
        ->assertJsonPath('actual_duration', '75 mins')
        ->assertJsonPath('status', 'completed')
        ->assertJsonPath('related_to', 'Alex Cruz, Acme');
});

test('task pivots hierarchy templates timers and soft deletes persist', function () {
    $this->actingAs(User::findOrFail(1));

    $template = TaskTemplate::create(['title' => 'Standard follow-up']);
    $blockingTask = Task::create(['name' => 'Prerequisite', 'status' => 'Open']);

    $response = $this->postJson('/api/tasks', [
        'name' => 'Dependent task',
        'status' => 'Open',
        'parent_task_id' => $blockingTask->id,
        'task_template_id' => $template->id,
        'assigned_user_ids' => [1],
        'dependency_task_ids' => [$blockingTask->id],
        'related_to' => 'Legacy task label',
    ])->assertOk()
        ->assertJsonPath('assignees.0.id', 1)
        ->assertJsonPath('parent_task.id', $blockingTask->id)
        ->assertJsonPath('blocking_tasks.0.id', $blockingTask->id);

    $task = Task::findOrFail($response->json('id'));
    TaskTimer::create([
        'task_id' => $task->id,
        'user_id' => 1,
        'started_at' => now(),
        'paused_seconds' => 30,
    ]);

    expect($task->template->is($template))->toBeTrue()
        ->and($task->timers()->count())->toBe(1)
        ->and($task->timers()->first()->paused_seconds)->toBe(30);

    $this->deleteJson("/api/tasks/{$task->id}")->assertNoContent();

    expect(Task::find($task->id))->toBeNull()
        ->and(Task::withTrashed()->find($task->id))->not->toBeNull();
});

test('message conversations and entries persist together', function () {
    $this->withMiddleware();
    $this->actingAs(User::findOrFail(1));

    $created = $this->postJson('/api/message-conversations', [
        'company_id' => 1,
        'contact_id' => 1,
        'channel' => 'SMS',
        'body' => 'Initial message',
    ])->assertCreated()
        ->assertJsonPath('latest_entry.body', 'Initial message')
        ->assertJsonPath('is_linked', true);

    $conversationId = $created->json('id');
    $this->postJson("/api/message-conversations/{$conversationId}/messages", [
        'direction' => 'Incoming',
        'sender' => '+639171234567',
        'body' => 'Reply message',
    ])->assertCreated()
        ->assertJsonPath('delivery_status', 'Received');

    $this->getJson("/api/message-conversations/{$conversationId}")
        ->assertOk()
        ->assertJsonCount(2, 'entries')
        ->assertJsonPath('entries.0.body', 'Initial message')
        ->assertJsonPath('entries.1.body', 'Reply message');
});

test('task outline hierarchy reorder bulk and system state contracts operate on the same task rows', function () {
    $this->actingAs(User::findOrFail(1));

    $created = $this->postJson('/api/tasks/outline', [
        'outline' => "Monthly Bookkeeping\n  Collect Documents\n    Bank Statements",
        'assigned_user_ids' => [1],
    ])->assertCreated()->assertJsonCount(3, 'tasks');

    $ids = collect($created->json('tasks'))->pluck('id');
    expect(Task::findOrFail($ids[1])->parent_task_id)->toBe($ids[0])
        ->and(Task::findOrFail($ids[2])->parent_task_id)->toBe($ids[1]);

    $this->patchJson('/api/tasks/reorder', [
        'items' => [[
            'id' => $ids[0],
            'parent_task_id' => $ids[2],
            'sort_order' => 0,
        ]],
    ])->assertUnprocessable();

    $this->patchJson('/api/tasks/bulk', [
        'task_ids' => [$ids[1], $ids[2]],
        'action' => 'update',
        'changes' => ['status' => 'Completed', 'priority' => 'High'],
    ])->assertOk()->assertJsonPath('affected', 2);

    $workflow = $this->getJson('/api/tasks/workflow?scope=my&state=no_deadline')
        ->assertOk();
    expect(collect($workflow->json('tasks'))->pluck('id'))->toContain($ids[0]);
});

test('task timers add server measured time to separately retained manual minutes', function () {
    $this->actingAs(User::findOrFail(1));
    $task = Task::query()->create([
        'name' => 'Timed task',
        'manual_actual_duration_minutes' => 15,
        'actual_duration_minutes' => 15,
    ]);

    $timerId = $this->postJson("/api/tasks/{$task->id}/timer/start")
        ->assertCreated()
        ->json('timer.id');
    DB::table('task_timers')->where('id', $timerId)->update(['started_at' => now()->subMinutes(5)]);

    $this->postJson("/api/tasks/{$task->id}/timer/stop")
        ->assertOk()
        ->assertJsonPath('task.actual_duration_minutes', 20);

    $this->assertDatabaseHas('tasks', [
        'id' => $task->id,
        'manual_actual_duration_minutes' => 15,
        'actual_duration_minutes' => 20,
    ]);
});

test('task branch duplication and templates copy structure without execution history', function () {
    $this->actingAs(User::findOrFail(1));
    $root = Task::query()->create([
        'name' => 'Monthly close',
        'start_date' => '2026-08-01 09:00:00',
        'due_date' => '2026-08-10',
        'status' => 'Completed',
        'actual_duration_minutes' => 90,
        'manual_actual_duration_minutes' => 90,
        'completed_at' => now(),
    ]);
    Task::query()->create([
        'name' => 'Reconcile bank',
        'parent_task_id' => $root->id,
        'start_date' => '2026-08-03 09:00:00',
        'due_date' => '2026-08-07',
        'status' => 'Completed',
        'actual_duration_minutes' => 30,
        'manual_actual_duration_minutes' => 30,
        'completed_at' => now(),
    ]);

    $copyId = $this->postJson("/api/tasks/{$root->id}/duplicate", [
        'target_start_date' => '2026-09-01',
        'company_id' => 1,
    ])->assertCreated()->json('id');
    $copy = Task::findOrFail($copyId);
    $copyChild = Task::query()->where('parent_task_id', $copyId)->firstOrFail();
    expect($copy->status)->toBe('Open')
        ->and($copy->actual_duration_minutes)->toBeNull()
        ->and($copy->completed_at)->toBeNull()
        ->and($copyChild->status)->toBe('Open')
        ->and($copyChild->actual_duration_minutes)->toBeNull()
        ->and($copyChild->company_id)->toBe(1);

    $templateId = $this->postJson("/api/tasks/{$root->id}/save-template")
        ->assertCreated()->json('id');
    $instanceId = $this->postJson("/api/task-templates/{$templateId}/instantiate", [
        'start_date' => '2026-10-01',
        'assigned_user_ids' => [1],
    ])->assertCreated()->json('id');
    expect(Task::query()->where('parent_task_id', $instanceId)->count())->toBe(1)
        ->and(Task::findOrFail($instanceId)->status)->toBe('Open');
});

test('client timeline includes every canonical company activity type and preserves source ids', function () {
    $company = Company::query()->findOrFail(1);

    $task = Task::query()->create(['name' => 'Client task', 'company_id' => $company->id]);
    $event = Event::query()->create(['title' => 'Client event', 'company_id' => $company->id]);
    $call = Call::query()->create(['purpose' => 'Client call', 'company_id' => $company->id]);
    $meeting = Meeting::query()->create(['title' => 'Client meeting', 'company_id' => $company->id]);
    $trip = OfficialBusinessTrip::query()->create([
        'employee_name' => 'Activity Owner',
        'destination' => 'Client office',
        'purpose' => 'Client filing',
        'date_from' => '2026-08-25',
        'company_id' => $company->id,
    ]);
    $message = MessageConversation::query()->create([
        'company_id' => $company->id,
        'conversation_title' => 'Client Viber thread',
        'channel' => 'Viber',
    ]);

    $timeline = collect(app(ActivityTimelineBuilder::class)->forCompany($company, User::query()->findOrFail(1)));
    $expected = collect([
        'TASK' => $task->id,
        'EVENT' => $event->id,
        'CALL' => $call->id,
        'MEETING' => $meeting->id,
        'OFFICIAL_BUSINESS_TRAVEL' => $trip->id,
        'MESSAGE' => $message->id,
    ]);

    expect($timeline)->toHaveCount(6)
        ->and($timeline->pluck('source_activity_type')->sort()->values()->all())
        ->toBe($expected->keys()->sort()->values()->all());

    $expected->each(function (int $sourceId, string $sourceType) use ($timeline): void {
        $activity = $timeline->firstWhere('source_activity_type', $sourceType);

        expect($activity['source_id'])->toBe($sourceId)
            ->and($activity['id'])->toEndWith('-'.$sourceId);
    });
});

test('client timeline uses normalized ids and emits each canonical source only once', function () {
    DB::table('companies')->insert(['id' => 2, 'company_name' => 'Other Client']);

    $legacyTask = Task::query()->create([
        'name' => 'Legacy text false positive',
        'company_id' => 2,
        'related_to' => 'Acme',
    ]);
    $task = Task::query()->create([
        'name' => 'Normalized client task',
        'company_id' => 1,
        'contact_id' => 1,
        'related_to' => 'Other Client',
    ]);
    $message = MessageConversation::query()->create([
        'company_id' => 1,
        'contact_id' => 1,
        'conversation_title' => 'One conversation activity',
        'channel' => 'Viber',
    ]);
    DB::table('message_entries')->insert([
        [
            'message_conversation_id' => $message->id,
            'direction' => 'Outgoing',
            'body' => 'First',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'message_conversation_id' => $message->id,
            'direction' => 'Incoming',
            'body' => 'Second',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $timeline = collect(app(ActivityTimelineBuilder::class)->forCompany(['id' => 1, 'company_name' => 'Acme'], User::query()->findOrFail(1)));
    $keys = $timeline->map(fn (array $activity): string => $activity['source_activity_type'].':'.$activity['source_id']);
    $contactTimeline = collect(app(ActivityTimelineBuilder::class)->forContact(Contact::query()->findOrFail(1), User::query()->findOrFail(1)));
    $contactKeys = $contactTimeline->map(fn (array $activity): string => $activity['source_activity_type'].':'.$activity['source_id']);

    expect($keys->all())->toContain('TASK:'.$task->id, 'MESSAGE:'.$message->id)
        ->and($keys->all())->not->toContain('TASK:'.$legacyTask->id)
        ->and($keys->duplicates())->toBeEmpty()
        ->and($timeline->where('source_activity_type', 'MESSAGE'))->toHaveCount(1)
        ->and($timeline)->toHaveCount(2)
        ->and($contactKeys->all())->toContain('TASK:'.$task->id, 'MESSAGE:'.$message->id)
        ->and($contactKeys->duplicates())->toBeEmpty()
        ->and($contactTimeline)->toHaveCount(2);
});


test('official business timeline keeps one canonical source and uses actual travel duration after return', function () {
    $company = Company::query()->findOrFail(1);
    $contact = Contact::query()->findOrFail(1);

    $trip = OfficialBusinessTrip::query()->create([
        'ob_reference_no' => 'OBF-TIMELINE-1',
        'employee_name' => 'Timeline Traveler',
        'destination' => 'Client office',
        'purpose' => 'Client filing',
        'date_from' => '2026-08-26',
        'date_to' => '2026-08-26',
        'company_id' => $company->id,
        'contact_id' => $contact->id,
        'status' => 'Approved',
        'duration_minutes' => 999,
    ]);

    $companyTimeline = collect(app(ActivityTimelineBuilder::class)->forCompany($company, User::query()->findOrFail(1)));
    $contactTimeline = collect(app(ActivityTimelineBuilder::class)->forContact($contact, User::query()->findOrFail(1)));

    $companyActivity = $companyTimeline
        ->where('source_activity_type', 'OFFICIAL_BUSINESS_TRAVEL')
        ->firstWhere('source_id', $trip->id);

    $contactActivity = $contactTimeline
        ->where('source_activity_type', 'OFFICIAL_BUSINESS_TRAVEL')
        ->firstWhere('source_id', $trip->id);

    expect($companyActivity)->not->toBeNull()
        ->and($contactActivity)->not->toBeNull()
        ->and($companyActivity['id'])->toBe('official-business-travel-'.$trip->id)
        ->and($contactActivity['id'])->toBe('official-business-travel-'.$trip->id)
        ->and($companyActivity['status'])->toBe('Approved')
        ->and($companyActivity['duration_minutes'])->toBeNull()
        ->and($contactActivity['duration_minutes'])->toBeNull();

    $trip->update([
        'status' => 'Completed',
        'actual_departure' => '2026-08-26 08:00:00',
        'actual_return' => '2026-08-26 09:20:00',
        'duration_minutes' => 999,
    ]);

    $companyTimeline = collect(app(ActivityTimelineBuilder::class)->forCompany($company, User::query()->findOrFail(1)));
    $contactTimeline = collect(app(ActivityTimelineBuilder::class)->forContact($contact, User::query()->findOrFail(1)));

    $companyActivities = $companyTimeline
        ->where('source_activity_type', 'OFFICIAL_BUSINESS_TRAVEL')
        ->where('source_id', $trip->id);

    $contactActivities = $contactTimeline
        ->where('source_activity_type', 'OFFICIAL_BUSINESS_TRAVEL')
        ->where('source_id', $trip->id);

    expect($companyActivities)->toHaveCount(1)
        ->and($contactActivities)->toHaveCount(1)
        ->and($companyActivities->first()['status'])->toBe('Completed')
        ->and($contactActivities->first()['status'])->toBe('Completed')
        ->and($companyActivities->first()['duration_minutes'])->toBe(80)
        ->and($contactActivities->first()['duration_minutes'])->toBe(80)
        ->and($companyActivities->first()['actual_departure'])->not->toBeNull()
        ->and($companyActivities->first()['actual_return'])->not->toBeNull();
});

test('activity create endpoints reject rapid duplicates and keep valid new meeting ids', function () {
    $this->actingAs(User::findOrFail(1));

    $cases = [
        ['url' => '/api/tasks', 'payload' => ['name' => 'Duplicate Guard Task', 'start_date' => '2026-09-04', 'due_date' => '2026-09-05']],
        ['url' => '/api/events', 'payload' => ['title' => 'Duplicate Guard Event', 'from' => '2026-09-05 09:00', 'to' => '2026-09-05 10:00']],
        ['url' => '/api/calls', 'payload' => ['contact' => 'Duplicate Guard Contact', 'purpose' => 'Status update', 'start_time' => '2026-09-05', 'start_hour' => '09:00']],
        ['url' => '/api/meetings', 'payload' => ['title' => 'Duplicate Guard Meeting', 'mode' => 'Online', 'date' => '2026-09-05', 'time' => '11:00']],
    ];

    foreach ($cases as $case) {
        $created = $this->postJson($case['url'], $case['payload'])->assertSuccessful();
        expect((int) $created->json('id'))->toBeGreaterThan(0);

        $this->postJson($case['url'], $case['payload'])
            ->assertConflict()
            ->assertJsonPath('message', 'This record already exists.');
    }

    $this->postJson('/api/tasks', ['name' => 'Distinct Guard Task', 'due_date' => '2026-09-05'])
        ->assertSuccessful();

    $meetingId = Meeting::query()->where('title', 'Duplicate Guard Meeting')->value('id');
    if (! Schema::hasTable('meeting_action_items')) {
        Schema::create('meeting_action_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('meeting_id');
        });
    }
    if (! Schema::hasTable('meeting_minutes_versions')) {
        Schema::create('meeting_minutes_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('meeting_id');
        });
    }
    $this->getJson('/api/meetings/'.$meetingId.'/workspace')
        ->assertOk()
        ->assertJsonPath('meeting_id', $meetingId);

    $conversationPayload = [
        'conversation_title' => 'Duplicate Guard Conversation',
        'channel' => 'SMS',
        'body' => 'Only one initial message',
    ];
    $conversation = $this->postJson('/api/message-conversations', $conversationPayload)
        ->assertCreated();
    $this->postJson('/api/message-conversations', $conversationPayload)
        ->assertConflict()
        ->assertJsonPath('message', 'This record already exists.');

    $entryPayload = ['body' => 'Only one follow-up message', 'direction' => 'Outgoing'];
    $savedConversation = MessageConversation::query()->findOrFail($conversation->json('id'));
    $entryRequest = Request::create('/api/message-conversations/'.$savedConversation->id.'/messages', 'POST', $entryPayload);
    $entryRequest->setUserResolver(fn () => User::findOrFail(1));
    $messageController = app(MessageConversationController::class);
    $messageGuard = app(ActivityDuplicateGuard::class);

    expect($messageController->storeMessage($entryRequest, $savedConversation, $messageGuard)->getStatusCode())->toBe(201);
    expect(fn () => $messageController->storeMessage($entryRequest, $savedConversation, $messageGuard))
        ->toThrow(HttpResponseException::class);

    expect(Task::query()->whereIn('name', ['Duplicate Guard Task', 'Distinct Guard Task'])->count())->toBe(2)
        ->and(Event::query()->where('title', 'Duplicate Guard Event')->count())->toBe(1)
        ->and(Call::query()->where('contact', 'Duplicate Guard Contact')->count())->toBe(1)
        ->and(Meeting::query()->where('title', 'Duplicate Guard Meeting')->count())->toBe(1)
        ->and(MessageConversation::query()->where('conversation_title', 'Duplicate Guard Conversation')->count())->toBe(1)
        ->and(MessageEntry::query()->where('body', 'Only one follow-up message')->count())->toBe(1);
});
