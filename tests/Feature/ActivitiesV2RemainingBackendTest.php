<?php

use App\Models\ActivityLink;
use App\Models\Employee;
use App\Models\EmailActivity;
use App\Models\EmailMailboxConnection;
use App\Models\Event;
use App\Models\Meeting;
use App\Models\OfficialBusinessTrip;
use App\Models\OfficialBusinessTripTicket;
use App\Models\Task;
use App\Models\User;
use App\Support\ActivityTimelineBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // Fail before any fixture DDL if a caller accidentally selects the working database.
    expect(config('database.default'))->toBe('sqlite');
    expect(config('database.connections.sqlite.database'))->toBe(':memory:');
    Http::preventStrayRequests();
    $this->withoutMiddleware();

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
    Schema::create('user_permissions', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id');
        $table->boolean('access_activities')->default(false);
        $table->boolean('access_hc_obf')->default(false);
        $table->boolean('access_hc_my_hc')->default(false);
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
        $table->string('email')->nullable();
        $table->timestamps();
    });

    Schema::create('company_bifs', function (Blueprint $table) {
        $table->id();
        $table->foreignId('company_id');
        $table->string('business_organization')->nullable();
        $table->string('bif_no')->nullable();
        $table->string('business_phone')->nullable();
        $table->string('mobile_no')->nullable();
        $table->string('tin_no')->nullable();
        $table->string('status')->nullable();
        $table->timestamps();
    });
    Schema::create('contacts', function (Blueprint $table) {
        $table->id();
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('email')->nullable();
        $table->timestamps();
    });
    Schema::create('company_contact', function (Blueprint $table) {
        $table->id();
        $table->foreignId('company_id');
        $table->foreignId('contact_id');
        $table->timestamps();
        $table->unique(['company_id', 'contact_id']);
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
    Schema::create('tasks', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('due_date')->nullable();
        $table->dateTime('start_date')->nullable();
        $table->unsignedInteger('estimated_duration_minutes')->nullable();
        $table->unsignedInteger('actual_duration_minutes')->nullable();
        $table->string('status')->nullable();
        $table->string('priority')->nullable();
        $table->string('owner')->nullable();
        $table->foreignId('assigned_to_user_id')->nullable();
        $table->text('description')->nullable();
        $table->foreignId('company_id')->nullable();
        $table->foreignId('contact_id')->nullable();
        $table->foreignId('deal_id')->nullable();
        $table->foreignId('project_id')->nullable();
        $table->string('related_to')->nullable();
        $table->foreignId('prepared_by_user_id')->nullable();
        $table->text('client_report_description')->nullable();
        $table->boolean('include_in_client_report')->default(false);
        $table->softDeletes();
        $table->timestamps();
    });
    Schema::create('task_assignments', function (Blueprint $table) {
        $table->id();
        $table->foreignId('task_id');
        $table->foreignId('user_id');
        $table->string('role')->default('assignee');
        $table->timestamps();
        $table->unique(['task_id', 'user_id', 'role']);
    });
    Schema::create('events', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->string('from')->nullable();
        $table->decimal('estimated_hours', 8, 2)->nullable();
        $table->decimal('actual_hours', 8, 2)->nullable();
        $table->string('status')->nullable();
        $table->string('host')->nullable();
        $table->foreignId('company_id')->nullable();
        $table->foreignId('contact_id')->nullable();
        $table->foreignId('deal_id')->nullable();
        $table->foreignId('project_id')->nullable();
        $table->string('related_to')->nullable();
        $table->foreignId('prepared_by_user_id')->nullable();
        $table->text('client_report_description')->nullable();
        $table->boolean('include_in_client_report')->default(false);
        $table->timestamps();
    });
    Schema::create('calls', function (Blueprint $table) {
        $table->id();
        $table->string('contact')->nullable();
        $table->string('purpose')->nullable();
        $table->string('start_time')->nullable();
        $table->string('start_hour')->nullable();
        $table->string('duration')->nullable();
        $table->string('owner')->nullable();
        $table->string('from')->nullable();
        $table->boolean('completed')->default(false);
        $table->string('audio_path')->nullable();
        $table->foreignId('company_id')->nullable();
        $table->foreignId('contact_id')->nullable();
        $table->foreignId('deal_id')->nullable();
        $table->foreignId('project_id')->nullable();
        $table->string('related_to')->nullable();
        $table->foreignId('prepared_by_user_id')->nullable();
        $table->text('client_report_description')->nullable();
        $table->boolean('include_in_client_report')->default(false);
        $table->timestamps();
    });
    Schema::create('meetings', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->string('meeting_type')->nullable();
        $table->string('related_account')->nullable();
        $table->string('related_project')->nullable();
        $table->string('mode')->nullable();
        $table->string('owner')->nullable();
        $table->string('date')->nullable();
        $table->string('time')->nullable();
        $table->string('duration')->nullable();
        $table->string('actual_duration')->nullable();
        $table->string('location')->nullable();
        $table->integer('attendees')->default(0);
        $table->text('description')->nullable();
        $table->string('status')->default('upcoming');
        $table->boolean('has_video')->default(false);
        $table->boolean('has_audio')->default(false);
        $table->boolean('has_transcript')->default(false);
        $table->boolean('has_minutes')->default(false);
        $table->string('video_path')->nullable();
        $table->string('audio_path')->nullable();
        $table->foreignId('company_id')->nullable();
        $table->foreignId('contact_id')->nullable();
        $table->foreignId('deal_id')->nullable();
        $table->foreignId('project_id')->nullable();
        $table->string('related_to')->nullable();
        $table->foreignId('prepared_by_user_id')->nullable();
        $table->text('client_report_description')->nullable();
        $table->boolean('include_in_client_report')->default(false);
        $table->timestamps();
    });
    Schema::create('message_conversations', function (Blueprint $table) {
        $table->id();
        $table->foreignId('company_id')->nullable();
        $table->foreignId('contact_id')->nullable();
        $table->foreignId('assigned_user_id')->nullable();
        $table->string('conversation_title')->nullable();
        $table->string('channel')->default('SMS');
        $table->string('line_account')->nullable();
        $table->string('handling')->default('Open');
        $table->boolean('is_linked')->default(true);
        $table->timestamp('last_message_at')->nullable();
        $table->string('external_conversation_id')->nullable();
        $table->json('technical_metadata')->nullable();
        $table->foreignId('prepared_by_user_id')->nullable();
        $table->text('client_report_description')->nullable();
        $table->boolean('include_in_client_report')->default(false);
        $table->timestamps();
    });
    Schema::create('message_entries', function (Blueprint $table) {
        $table->id();
        $table->foreignId('message_conversation_id');
        $table->foreignId('user_id')->nullable();
        $table->string('direction');
        $table->string('sender')->nullable();
        $table->string('recipient')->nullable();
        $table->text('body');
        $table->string('delivery_status')->default('Pending');
        $table->string('external_message_id')->nullable();
        $table->timestamp('sent_at')->nullable();
        $table->timestamp('received_at')->nullable();
        $table->json('technical_metadata')->nullable();
        $table->timestamps();
    });
    Schema::create('official_business_trips', function (Blueprint $table) {
        $table->id();
        $table->string('ob_reference_no')->nullable();
        $table->foreignId('employee_id');
        $table->string('employee_name');
        $table->string('destination');
        $table->text('purpose')->nullable();
        $table->date('date_from');
        $table->date('date_to')->nullable();
        $table->dateTime('actual_departure')->nullable();
        $table->dateTime('actual_return')->nullable();
        $table->boolean('is_client_travel')->default(false);
        $table->string('client_name')->nullable();
        $table->unsignedTinyInteger('workflow_version')->default(1);
        $table->json('team_members')->nullable();
        $table->json('attachment_paths')->nullable();
        $table->json('attachment_types')->nullable();
        $table->string('status')->default('Pending');
        $table->foreignId('created_by')->nullable();
        $table->foreignId('prepared_by_user_id')->nullable();
        $table->text('client_report_description')->nullable();
        $table->boolean('include_in_client_report')->default(false);
        $table->timestamps();
        $table->softDeletes();
    });

    // Minimal V3 O.B. relation schema required by OfficialBusinessActivityService.
    // Legacy records in this suite remain V1; these tables may stay empty.
    Schema::create('official_business_trip_tickets', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('official_business_trip_id');
        $table->unsignedBigInteger('company_id');
        $table->string('reference_no')->unique();
        $table->string('client_name_snapshot');
        $table->string('status')->default('Draft');
        $table->unsignedBigInteger('created_by_user_id')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('official_business_trip_ticket_personnel', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('official_business_trip_ticket_id');
        $table->unsignedBigInteger('employee_id')->nullable();
        $table->string('employee_name_snapshot')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });
    Schema::create('notes', function (Blueprint $table) {
        $table->id();
        $table->text('content');
        $table->string('owner')->nullable();
        $table->unsignedBigInteger('noteable_id');
        $table->string('noteable_type');
        $table->timestamps();
    });

    $migration = require database_path('migrations/2026_08_24_000001_complete_activities_v2_backend_contracts.php');
    $migration->up();
    $safeContractsMigration = require database_path('migrations/2026_08_24_000002_complete_activities_v2_safe_contracts.php');
    $safeContractsMigration->up();
    $emailActivitiesMigration = require database_path('migrations/2026_08_25_000001_create_email_activities_table.php');
    $emailActivitiesMigration->up();
    $gmailConnectionMigration = require database_path('migrations/2026_09_30_180000_create_email_mailbox_connections_table.php');
    $gmailConnectionMigration->up();
    $gmailEmailMigration = require database_path('migrations/2026_09_30_180001_extend_email_activities_for_gmail.php');
    $gmailEmailMigration->up();
    (require database_path('migrations/2026_10_01_180000_scope_email_activities_idempotency_to_mailbox.php'))->up();
    (require database_path('migrations/2026_10_01_180001_add_email_mailbox_sync_checkpoint.php'))->up();

    $this->user = User::query()->create([
        'name' => 'Activities Admin',
        'email' => 'activities-admin@example.test',
        'role' => 'Admin',
    ]);
    $this->actingAs($this->user);
});

test('related activities are canonical bidirectional duplicate safe and never delete sources', function () {
    $task = Task::query()->create(['name' => 'Linked Task']);
    $event = Event::query()->create(['title' => 'Linked Event']);

    $this->postJson("/api/activities/task/{$task->id}/related", [
        'related_type' => 'EVENT',
        'related_id' => $event->id,
    ])->assertCreated()->assertJsonPath('created', true);

    $this->postJson("/api/activities/event/{$event->id}/related", [
        'related_type' => 'task',
        'related_id' => $task->id,
    ])->assertOk()->assertJsonPath('created', false);

    $this->postJson("/api/activities/task/{$task->id}/related", [
        'related_type' => 'TASK',
        'related_id' => $task->id,
    ])->assertUnprocessable();

    $this->getJson("/api/activities/event/{$event->id}/related")
        ->assertOk()
        ->assertJsonCount(1, 'related_activities')
        ->assertJsonPath('related_activities.0.key', 'task-'.$task->id);

    $this->deleteJson("/api/activities/task/{$task->id}/related/event/{$event->id}")
        ->assertNoContent();

    expect(ActivityLink::count())->toBe(0)
        ->and(Task::find($task->id))->not->toBeNull()
        ->and(Event::find($event->id))->not->toBeNull();

    $this->assertDatabaseHas('activity_audit_logs', ['action' => 'related_activity_linked']);
    $this->assertDatabaseHas('activity_audit_logs', ['action' => 'related_activity_unlinked']);
});

test('normalized evidence upload listing and removal preserves complete metadata', function () {
    Storage::fake('public');
    $task = Task::query()->create(['name' => 'Evidence Task']);

    $created = $this->postJson("/api/activities/task/{$task->id}/attachments", [
        'attachment' => UploadedFile::fake()->create('proof.pdf', 125, 'application/pdf'),
        'category' => 'evidence',
    ])->assertCreated()
        ->assertJsonPath('source_activity_type', 'TASK')
        ->assertJsonPath('source_activity_id', $task->id)
        ->assertJsonPath('original_filename', 'proof.pdf')
        ->assertJsonPath('mime_type', 'application/pdf')
        ->assertJsonPath('category', 'evidence')
        ->assertJsonPath('uploaded_by_user_id', $this->user->id);

    $attachmentId = $created->json('id');
    $path = $created->json('storage_path');
    Storage::disk('public')->assertExists($path);

    $this->getJson("/api/activities/task/{$task->id}/attachments")
        ->assertOk()
        ->assertJsonCount(1, 'attachments')
        ->assertJsonPath('attachments.0.size_bytes', 128000);

    $this->deleteJson("/api/activities/task/{$task->id}/attachments/{$attachmentId}")
        ->assertNoContent();
    Storage::disk('public')->assertMissing($path);
    $this->assertDatabaseHas('activity_audit_logs', ['action' => 'attachment_removed']);
});

test('message reports enforce assigned prepared and administrator visibility before filters', function () {
    $userA = User::query()->create([
        'name' => 'Message User A',
        'email' => 'message-user-a@example.test',
        'role' => 'Employee',
    ]);
    $userB = User::query()->create([
        'name' => 'Message User B',
        'email' => 'message-user-b@example.test',
        'role' => 'Employee',
    ]);
    $superAdmin = User::query()->create([
        'name' => 'Activities SuperAdmin',
        'email' => 'activities-superadmin@example.test',
        'role' => 'SuperAdmin',
    ]);
    foreach ([$userA, $userB] as $user) {
        DB::table('user_permissions')->insert([
            'user_id' => $user->id,
            'access_activities' => true,
            'access_hc_obf' => false,
            'access_hc_my_hc' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $assigned = \App\Models\MessageConversation::query()->create([
        'assigned_user_id' => $userA->id,
        'conversation_title' => 'Assigned to A',
        'handling' => 'Open',
    ]);
    $assigned->prepared_by_user_id = $userB->id;
    $assigned->save();

    $prepared = \App\Models\MessageConversation::query()->create([
        'assigned_user_id' => $userB->id,
        'conversation_title' => 'Prepared by A',
        'handling' => 'Closed',
    ]);
    $prepared->prepared_by_user_id = $userA->id;
    $prepared->save();

    $hidden = \App\Models\MessageConversation::query()->create([
        'assigned_user_id' => $userB->id,
        'conversation_title' => 'Private to B',
        'handling' => 'Open',
    ]);
    $hidden->prepared_by_user_id = $userB->id;
    $hidden->save();

    $this->actingAs($userA)
        ->postJson('/api/activities/report', [
            'selected_ids' => ['message-'.$hidden->id],
        ])
        ->assertOk()
        ->assertJsonCount(0, 'rows');
    $this->postJson('/api/activities/export', [
        'format' => 'json',
        'selected_ids' => ['message-'.$hidden->id],
    ])->assertOk()
        ->assertJsonCount(0, 'rows');

    $this->postJson('/api/activities/report', [
        'selected_ids' => ['message-'.$assigned->id, 'message-'.$prepared->id],
    ])->assertOk()
        ->assertJsonCount(2, 'rows')
        ->assertJsonPath('rows.0.id', 'message-'.$assigned->id)
        ->assertJsonPath('rows.1.id', 'message-'.$prepared->id);

    $this->postJson('/api/activities/report', [
        'activity_types' => ['MESSAGE'],
        'status' => 'Open',
    ])->assertOk()
        ->assertJsonCount(1, 'rows')
        ->assertJsonPath('rows.0.id', 'message-'.$assigned->id);

    foreach ([$this->user, $superAdmin] as $administrator) {
        $this->actingAs($administrator)
            ->postJson('/api/activities/report', [
                'selected_ids' => [
                    'message-'.$assigned->id,
                    'message-'.$prepared->id,
                    'message-'.$hidden->id,
                ],
            ])
            ->assertOk()
            ->assertJsonCount(3, 'rows');
    }
});

test('message generic endpoints reject every operation outside assigned or prepared visibility', function () {
    Storage::fake('public');
    $userA = User::query()->create([
        'name' => 'Generic User A',
        'email' => 'generic-user-a@example.test',
        'role' => 'Employee',
    ]);
    $userB = User::query()->create([
        'name' => 'Generic User B',
        'email' => 'generic-user-b@example.test',
        'role' => 'Employee',
    ]);
    $task = Task::query()->create(['name' => 'Generic linked task']);
    $hidden = \App\Models\MessageConversation::query()->create([
        'assigned_user_id' => $userB->id,
        'conversation_title' => 'B private generic message',
    ]);
    $hidden->prepared_by_user_id = $userB->id;
    $hidden->save();

    $attachmentId = DB::table('activity_attachments')->insertGetId([
        'activity_type' => 'MESSAGE',
        'activity_id' => $hidden->id,
        'filename' => 'private-proof.pdf',
        'original_filename' => 'private-proof.pdf',
        'external_url' => 'https://example.test/private-proof.pdf',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('activity_audit_logs')->insert([
        'activity_type' => 'MESSAGE',
        'activity_id' => $hidden->id,
        'action' => 'private_message_action',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    ActivityLink::query()->create([
        'source_type' => 'MESSAGE',
        'source_id' => $hidden->id,
        'related_type' => 'TASK',
        'related_id' => $task->id,
        'created_by_user_id' => $userB->id,
    ]);

    $this->actingAs($userA);
    $this->getJson("/api/activities/message/{$hidden->id}/attachments")->assertNotFound();
    $this->postJson("/api/activities/message/{$hidden->id}/attachments", [
        'attachment' => UploadedFile::fake()->create('blocked.pdf', 10, 'application/pdf'),
    ])->assertNotFound();
    $this->deleteJson("/api/activities/message/{$hidden->id}/attachments/{$attachmentId}")->assertNotFound();
    $this->getJson("/api/activities/message/{$hidden->id}/audit-log")->assertNotFound();
    $this->getJson("/api/activities/message/{$hidden->id}/related")->assertNotFound();
    $this->postJson("/api/activities/message/{$hidden->id}/related", [
        'related_type' => 'TASK',
        'related_id' => $task->id,
    ])->assertNotFound();
    $this->deleteJson("/api/activities/message/{$hidden->id}/related/task/{$task->id}")->assertNotFound();

    $this->assertDatabaseHas('activity_attachments', ['id' => $attachmentId]);
    $this->assertDatabaseHas('activity_links', [
        'source_type' => 'MESSAGE',
        'source_id' => $hidden->id,
        'related_type' => 'TASK',
        'related_id' => $task->id,
    ]);
    $this->assertDatabaseCount('activity_attachments', 1);
    $this->assertDatabaseCount('activity_links', 1);
});

test('assigned and prepared message users retain generic attachment audit and link actions', function () {
    Storage::fake('public');
    $authorized = User::query()->create([
        'name' => 'Authorized Message User',
        'email' => 'authorized-message@example.test',
        'role' => 'Employee',
    ]);
    $other = User::query()->create([
        'name' => 'Other Message User',
        'email' => 'other-message@example.test',
        'role' => 'Employee',
    ]);
    $task = Task::query()->create(['name' => 'Authorized linked task']);
    $assigned = \App\Models\MessageConversation::query()->create([
        'assigned_user_id' => $authorized->id,
        'conversation_title' => 'Assigned generic message',
    ]);
    $assigned->prepared_by_user_id = $other->id;
    $assigned->save();
    $prepared = \App\Models\MessageConversation::query()->create([
        'assigned_user_id' => $other->id,
        'conversation_title' => 'Prepared generic message',
    ]);
    $prepared->prepared_by_user_id = $authorized->id;
    $prepared->save();

    $this->actingAs($authorized);
    $created = $this->postJson("/api/activities/message/{$assigned->id}/attachments", [
        'attachment' => UploadedFile::fake()->create('authorized.pdf', 10, 'application/pdf'),
    ])->assertCreated();
    $attachmentId = $created->json('id');

    $this->getJson("/api/activities/message/{$assigned->id}/attachments")
        ->assertOk()
        ->assertJsonCount(1, 'attachments');
    $this->getJson("/api/activities/message/{$assigned->id}/audit-log")
        ->assertOk()
        ->assertJsonFragment(['action' => 'attachment_uploaded']);
    $this->postJson("/api/activities/message/{$assigned->id}/related", [
        'related_type' => 'TASK',
        'related_id' => $task->id,
    ])->assertCreated();
    $this->getJson("/api/activities/message/{$assigned->id}/related")
        ->assertOk()
        ->assertJsonCount(1, 'related_activities');
    $this->deleteJson("/api/activities/message/{$assigned->id}/related/task/{$task->id}")
        ->assertNoContent();
    $this->deleteJson("/api/activities/message/{$assigned->id}/attachments/{$attachmentId}")
        ->assertNoContent();
    $this->getJson("/api/activities/message/{$prepared->id}/attachments")
        ->assertOk()
        ->assertJsonCount(0, 'attachments');
});

test('email and official business generic endpoints reuse their canonical visibility scopes', function () {
    Storage::fake('public');
    $userA = User::query()->create([
        'name' => 'Canonical User A',
        'email' => 'canonical-user-a@example.test',
        'role' => 'Employee',
    ]);
    $userB = User::query()->create([
        'name' => 'Canonical User B',
        'email' => 'canonical-user-b@example.test',
        'role' => 'Employee',
    ]);
    foreach ([$userA, $userB] as $user) {
        DB::table('user_permissions')->insert([
            'user_id' => $user->id,
            'access_activities' => true,
            'access_hc_obf' => false,
            'access_hc_my_hc' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
    $employeeA = Employee::query()->create([
        'user_id' => $userA->id,
        'first_name' => 'Canonical',
        'last_name' => 'User A',
        'work_email' => $userA->email,
    ]);
    $employeeB = Employee::query()->create([
        'user_id' => $userB->id,
        'first_name' => 'Canonical',
        'last_name' => 'User B',
        'work_email' => $userB->email,
    ]);
    $hiddenEmail = EmailActivity::query()->create([
        'provider' => 'imap',
        'mailbox_key' => $userB->email,
        'provider_message_id' => 'hidden-generic-email',
        'mailbox_owner_user_id' => $userB->id,
        'sender_email' => 'client@example.test',
        'recipients' => [$userB->email],
        'direction' => 'Incoming',
        'occurred_at' => now(),
    ]);
    $visibleEmail = EmailActivity::query()->create([
        'provider' => 'imap',
        'mailbox_key' => $userA->email,
        'provider_message_id' => 'visible-generic-email',
        'mailbox_owner_user_id' => $userA->id,
        'sender_email' => 'client@example.test',
        'recipients' => [$userA->email],
        'direction' => 'Incoming',
        'occurred_at' => now(),
    ]);
    $hiddenTrip = OfficialBusinessTrip::query()->create([
        'employee_id' => $employeeB->id,
        'employee_name' => $userB->name,
        'destination' => 'Hidden Site',
        'date_from' => now()->toDateString(),
        'created_by' => $userB->id,
    ]);
    $visibleTrip = OfficialBusinessTrip::query()->create([
        'employee_id' => $employeeA->id,
        'employee_name' => $userA->name,
        'destination' => 'Visible Site',
        'date_from' => now()->toDateString(),
        'created_by' => $userA->id,
    ]);
    $task = Task::query()->create(['name' => 'Canonical scope task']);
    $hiddenAttachmentIds = [
        'email' => DB::table('activity_attachments')->insertGetId([
            'activity_type' => 'EMAIL',
            'activity_id' => $hiddenEmail->id,
            'filename' => 'hidden-email.txt',
            'created_at' => now(),
            'updated_at' => now(),
        ]),
        'official-business-travel' => DB::table('activity_attachments')->insertGetId([
            'activity_type' => 'OFFICIAL_BUSINESS_TRAVEL',
            'activity_id' => $hiddenTrip->id,
            'filename' => 'hidden-trip.txt',
            'created_at' => now(),
            'updated_at' => now(),
        ]),
    ];
    ActivityLink::query()->create([
        'source_type' => 'EMAIL',
        'source_id' => $hiddenEmail->id,
        'related_type' => 'TASK',
        'related_id' => $task->id,
        'created_by_user_id' => $userB->id,
    ]);
    ActivityLink::query()->create([
        'source_type' => 'OFFICIAL_BUSINESS_TRAVEL',
        'source_id' => $hiddenTrip->id,
        'related_type' => 'TASK',
        'related_id' => $task->id,
        'created_by_user_id' => $userB->id,
    ]);

    $this->actingAs($userA);
    foreach ([
        'email' => $hiddenEmail->id,
        'official-business-travel' => $hiddenTrip->id,
    ] as $type => $hiddenId) {
        foreach (['attachments', 'audit-log', 'related'] as $endpoint) {
            $response = $this->getJson("/api/activities/{$type}/{$hiddenId}/{$endpoint}");
            $type === 'email' ? $response->assertNotFound() : $response->assertOk();
        }
        if ($type === 'email') {
            $this->postJson("/api/activities/{$type}/{$hiddenId}/attachments", [
                'attachment' => UploadedFile::fake()->create('blocked.txt', 1, 'text/plain'),
            ])->assertNotFound();
            $this->deleteJson("/api/activities/{$type}/{$hiddenId}/attachments/{$hiddenAttachmentIds[$type]}")
                ->assertNotFound();
            $this->postJson("/api/activities/{$type}/{$hiddenId}/related", [
                'related_type' => 'TASK',
                'related_id' => $task->id,
            ])->assertNotFound();
            $this->deleteJson("/api/activities/{$type}/{$hiddenId}/related/task/{$task->id}")
                ->assertNotFound();
        }
    }
    $this->assertDatabaseCount('activity_attachments', 2);
    $this->assertDatabaseCount('activity_links', 2);
    $this->getJson("/api/activities/email/{$visibleEmail->id}/attachments")->assertOk();
    $this->getJson("/api/activities/official-business-travel/{$visibleTrip->id}/attachments")->assertOk();
});

test('reporting selected rows override filters and missing durations remain uncovered', function () {
    $task = Task::query()->create([
        'name' => 'Selected Task',
        'start_date' => '2025-01-10 09:00:00',
        'actual_duration_minutes' => 30,
        'status' => 'Open',
        'prepared_by_user_id' => $this->user->id,
    ]);
    Event::query()->create([
        'title' => 'Filtered Event',
        'from' => '2026-08-24 10:00:00',
        'status' => 'Closed',
        'prepared_by_user_id' => $this->user->id,
    ]);
    \App\Models\Call::query()->create([
        'purpose' => 'Duration Call',
        'start_time' => '2026-08-24',
        'duration' => '45 mins',
        'prepared_by_user_id' => $this->user->id,
    ]);
    \App\Models\MessageConversation::query()->create([
        'conversation_title' => 'Confidential Conversation',
        'channel' => 'SMS',
        'prepared_by_user_id' => $this->user->id,
    ]);

    $selected = $this->postJson('/api/activities/report', [
        'selected_ids' => ['task-'.$task->id],
        'status' => 'Closed',
        'date_from' => '2026-01-01',
    ])->assertOk()
        ->assertJsonPath('selection_mode', 'selected')
        ->assertJsonCount(1, 'rows')
        ->assertJsonPath('rows.0.id', 'task-'.$task->id)
        ->assertJsonPath('duration_coverage.activities_with_duration', 1)
        ->assertJsonPath('duration_coverage.total_duration_minutes', 30);

    expect($selected->json('filters'))->toBe([]);

    $all = $this->postJson('/api/activities/report')
        ->assertOk()
        ->assertJsonPath('selection_mode', 'filtered')
        ->assertJsonPath('duration_coverage.activities_with_duration', 2)
        ->assertJsonPath('duration_coverage.total_activities', 4)
        ->assertJsonPath('duration_coverage.coverage_percentage', 50)
        ->assertJsonPath('duration_coverage.total_duration_minutes', 75);

    $message = collect($all->json('rows'))->firstWhere('source_activity_type', 'MESSAGE');
    expect($message['duration_minutes'])->toBeNull()
        ->and($message)->not->toHaveKey('body');

    $this->postJson('/api/activities/export', ['format' => 'csv', 'selected_ids' => ['task-'.$task->id]])
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertSee('Selected Task');

    $this->postJson('/api/activities/export', ['format' => 'pdf', 'selected_ids' => ['task-'.$task->id]])
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('townland august completed meetings and calls use one filtered dataset across csv and xlsx', function () {
    $townlandId = DB::table('companies')->insertGetId([
        'company_name' => 'Townland',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $otherCompanyId = DB::table('companies')->insertGetId([
        'company_name' => 'Other Client',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $meeting = Meeting::query()->create([
        'title' => 'Townland August Meeting',
        'company_id' => $townlandId,
        'date' => '2026-08-12',
        'status' => 'Completed',
        'duration_minutes' => 60,
    ]);
    $call = \App\Models\Call::query()->create([
        'purpose' => 'Townland August Call',
        'company_id' => $townlandId,
        'start_time' => '2026-08-20',
        'completed' => true,
        'duration_minutes' => 30,
    ]);
    Meeting::query()->create([
        'title' => 'Townland September Meeting',
        'company_id' => $townlandId,
        'date' => '2026-09-01',
        'status' => 'Completed',
    ]);
    Meeting::query()->create([
        'title' => 'Townland Upcoming Meeting',
        'company_id' => $townlandId,
        'date' => '2026-08-15',
        'status' => 'Upcoming',
    ]);
    Event::query()->create([
        'title' => 'Townland Completed Event',
        'company_id' => $townlandId,
        'from' => '2026-08-18',
        'status' => 'Completed',
    ]);
    Meeting::query()->create([
        'title' => 'Other Client Meeting',
        'company_id' => $otherCompanyId,
        'date' => '2026-08-12',
        'status' => 'Completed',
    ]);

    $filters = [
        'company_id' => $townlandId,
        'month' => '2026-08',
        'activity_types' => ['MEETING', 'CALL'],
        'status' => 'Completed',
    ];

    $dataset = $this->postJson('/api/activities/report', $filters)
        ->assertOk()
        ->assertJsonPath('selection_mode', 'filtered')
        ->assertJsonCount(2, 'rows')
        ->assertJsonPath('duration_coverage.total_activities', 2)
        ->assertJsonPath('duration_coverage.activities_with_duration', 2)
        ->assertJsonPath('duration_coverage.total_duration_minutes', 90);

    expect(collect($dataset->json('rows'))->pluck('id')->sort()->values()->all())
        ->toBe(collect(['meeting-'.$meeting->id, 'call-'.$call->id])->sort()->values()->all());

    $this->postJson('/api/activities/export', $filters + ['format' => 'csv'])
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertSee('"Activities with Duration",2', false)
        ->assertSee('Townland August Meeting')
        ->assertSee('Townland August Call')
        ->assertDontSee('Townland September Meeting')
        ->assertDontSee('Townland Completed Event');

    $xlsx = $this->postJson('/api/activities/export', $filters + ['format' => 'xlsx'])
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    expect(substr($xlsx->getContent(), 0, 2))->toBe('PK');

    $path = tempnam(sys_get_temp_dir(), 'activities-test-xlsx-');
    file_put_contents($path, $xlsx->getContent());
    $archive = new ZipArchive();

    try {
        expect($archive->open($path))->toBeTrue();
        $sheet = $archive->getFromName('xl/worksheets/sheet1.xml');
        expect($sheet)->toContain('Activities with Duration')
            ->toContain('Townland August Meeting')
            ->toContain('Townland August Call')
            ->not->toContain('Townland September Meeting')
            ->not->toContain('Townland Completed Event');
    } finally {
        $archive->close();
        @unlink($path);
    }
});

test('selected two row export overrides general report filters without adding other rows', function () {
    $first = Task::query()->create([
        'name' => 'Selected First',
        'start_date' => '2025-01-10 09:00:00',
        'status' => 'Open',
        'actual_duration_minutes' => 20,
    ]);
    $second = Event::query()->create([
        'title' => 'Selected Second',
        'from' => '2027-03-15 10:00:00',
        'status' => 'Scheduled',
    ]);
    Meeting::query()->create([
        'title' => 'Unselected Matching Filter',
        'date' => '2026-08-12',
        'status' => 'Completed',
    ]);

    $payload = [
        'selected_ids' => ['task-'.$first->id, 'event-'.$second->id],
        'month' => '2026-08',
        'activity_types' => ['MEETING'],
        'status' => 'Completed',
    ];

    $this->postJson('/api/activities/report', $payload)
        ->assertOk()
        ->assertJsonPath('selection_mode', 'selected')
        ->assertJsonCount(2, 'rows')
        ->assertJsonPath('filters', [])
        ->assertJsonPath('duration_coverage.total_activities', 2)
        ->assertJsonPath('duration_coverage.activities_with_duration', 1)
        ->assertJsonPath('duration_coverage.total_duration_minutes', 20);

    $this->postJson('/api/activities/export', $payload + ['format' => 'csv'])
        ->assertOk()
        ->assertSee('"Selection Mode",selected', false)
        ->assertSee('"Total Activities",2', false)
        ->assertSee('Selected First')
        ->assertSee('Selected Second')
        ->assertDontSee('Unselected Matching Filter');
});

test('known contact email sync links one canonical source across activities timeline and redacted reports', function () {
    $companyId = DB::table('companies')->insertGetId([
        'company_name' => 'Townland',
        'email' => 'hello@townland.test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $contactId = DB::table('contacts')->insertGetId([
        'first_name' => 'Tara',
        'last_name' => 'Townland',
        'email' => 'tara@townland.test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('company_contact')->insert([
        'company_id' => $companyId,
        'contact_id' => $contactId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $payload = [
        'provider' => 'Microsoft Graph',
        'mailbox_key' => 'ACTIVITIES@JKC.TEST',
        'provider_message_id' => 'immutable-provider-message-001',
        'subject' => 'Townland filing confirmation',
        'sender_email' => 'TARA@TOWNLAND.TEST',
        'recipients' => ['activities@jkc.test'],
        'direction' => 'Incoming',
        'occurred_at' => '2026-08-21T09:30:00+08:00',
        'summary' => 'Client confirmed the filing details.',
        'body' => 'PRIVATE FULL BODY MUST NOT APPEAR IN CLIENT REPORTS',
        'has_attachments' => true,
        'attachment_metadata' => [['name' => 'confirmation.pdf']],
    ];

    $created = $this->postJson('/api/email-activities/sync', $payload)
        ->assertCreated()
        ->assertJsonPath('provider', 'microsoft graph')
        ->assertJsonPath('mailbox_key', 'activities@jkc.test')
        ->assertJsonPath('company_id', $companyId)
        ->assertJsonPath('contact_id', $contactId)
        ->assertJsonPath('handling', 'Linked');

    $emailId = $created->json('id');

    $this->postJson('/api/email-activities/sync', array_merge($payload, [
        'summary' => 'Updated safe summary from the provider.',
    ]))->assertOk()
        ->assertJsonPath('id', $emailId);

    $this->assertDatabaseCount('email_activities', 1);
    $this->assertDatabaseHas('email_activities', [
        'id' => $emailId,
        'company_id' => $companyId,
        'contact_id' => $contactId,
        'provider_message_id' => 'immutable-provider-message-001',
    ]);

    $activities = $this->getJson('/api/activities')->assertOk();
    expect(collect($activities->json('emails'))->pluck('id')->all())->toBe([$emailId])
        ->and(collect($activities->json('activities'))->where('id', 'email-'.$emailId))->toHaveCount(1);

    $timeline = collect(app(ActivityTimelineBuilder::class)->forCompany(['id' => $companyId], $this->user));
    expect($timeline->where('source_activity_type', 'EMAIL'))->toHaveCount(1)
        ->and($timeline->firstWhere('source_activity_type', 'EMAIL')['source_id'])->toBe($emailId);

    $report = $this->postJson('/api/activities/report', [
        'company_id' => $companyId,
        'activity_types' => ['EMAIL'],
    ])->assertOk()
        ->assertJsonCount(1, 'rows')
        ->assertJsonPath('rows.0.source_activity_id', $emailId)
        ->assertJsonPath('rows.0.email_metadata.sender', 'tara@townland.test')
        ->assertJsonPath('rows.0.email_metadata.direction', 'Incoming');

    $encodedReport = json_encode($report->json('rows'));
    expect($encodedReport)->toContain('Updated safe summary from the provider.')
        ->not->toContain('PRIVATE FULL BODY MUST NOT APPEAR IN CLIENT REPORTS');

    $this->postJson('/api/activities/export', [
        'format' => 'csv',
        'company_id' => $companyId,
        'activity_types' => ['EMAIL'],
    ])->assertOk()
        ->assertSee('Townland filing confirmation')
        ->assertSee('Updated safe summary from the provider.')
        ->assertDontSee('PRIVATE FULL BODY MUST NOT APPEAR IN CLIENT REPORTS');

    expect(fn () => DB::table('email_activities')->insert([
        'provider' => 'microsoft graph',
        'mailbox_key' => 'activities@jkc.test',
        'provider_message_id' => 'immutable-provider-message-001',
        'mailbox_owner_user_id' => $this->user->id,
        'sender_email' => 'tara@townland.test',
        'recipients' => json_encode(['activities@jkc.test']),
        'direction' => 'Incoming',
        'occurred_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('email activity access remains private to its mailbox owner', function () {
    $owner = User::query()->create([
        'name' => 'Mailbox Owner',
        'email' => 'owner-mailbox@example.test',
        'role' => 'Employee',
    ]);
    $other = User::query()->create([
        'name' => 'Other Employee',
        'email' => 'other-mailbox@example.test',
        'role' => 'Employee',
    ]);
    $payload = [
        'provider' => 'imap',
        'mailbox_key' => 'owner-mailbox@example.test',
        'provider_message_id' => 'private-mailbox-message',
        'subject' => 'Scoped email',
        'sender_email' => 'known@example.test',
        'recipients' => ['owner-mailbox@example.test'],
        'direction' => 'Incoming',
        'occurred_at' => '2026-08-22T10:00:00+08:00',
    ];

    $this->actingAs($owner);
    $emailId = $this->postJson('/api/email-activities/sync', $payload)
        ->assertCreated()
        ->assertJsonPath('mailbox_owner_user_id', $owner->id)
        ->json('id');

    $this->actingAs($other);
    $this->getJson('/api/email-activities')->assertOk()->assertJsonCount(0);
    $this->getJson('/api/email-activities/'.$emailId)->assertNotFound();
    $this->postJson('/api/email-activities/sync', $payload)->assertNotFound();
    $this->assertDatabaseCount('email_activities', 1);

    $admin = User::query()->findOrFail($this->user->id);
    expect($admin->isAdmin())->toBeTrue()
        ->and(EmailActivity::query()->visibleTo($admin)->whereKey($emailId)->exists())->toBeFalse();
    $this->actingAs($admin);
    $this->getJson('/api/email-activities/'.$emailId)
        ->assertNotFound();
});

test('activity feeds and account timelines preserve per-type read visibility', function () {
    $owner = User::query()->create([
        'name' => 'Timeline Owner',
        'email' => 'timeline-owner@example.test',
        'role' => 'Employee',
    ]);
    $other = User::query()->create([
        'name' => 'Timeline Other',
        'email' => 'timeline-other@example.test',
        'role' => 'Employee',
    ]);

    foreach ([$owner, $other] as $user) {
        DB::table('user_permissions')->insert([
            'user_id' => $user->id,
            'access_activities' => true,
            'access_hc_obf' => false,
            'access_hc_my_hc' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $ownerEmployee = Employee::query()->create([
        'user_id' => $owner->id,
        'first_name' => 'Timeline',
        'last_name' => 'Owner',
        'work_email' => $owner->email,
    ]);
    $otherEmployee = Employee::query()->create([
        'user_id' => $other->id,
        'first_name' => 'Timeline',
        'last_name' => 'Other',
        'work_email' => $other->email,
    ]);
    $company = \App\Models\Company::query()->create(['company_name' => 'Scoped Timeline Client']);
    $contact = \App\Models\Contact::query()->create(['first_name' => 'Scoped', 'last_name' => 'Contact']);
    $sharedTask = Task::query()->create([
        'name' => 'Shared timeline task',
        'company_id' => $company->id,
        'contact_id' => $contact->id,
    ]);

    $ownerEmail = EmailActivity::query()->create([
        'provider' => 'imap',
        'mailbox_key' => $owner->email,
        'provider_message_id' => 'timeline-owner-email',
        'mailbox_owner_user_id' => $owner->id,
        'company_id' => $company->id,
        'contact_id' => $contact->id,
        'sender_email' => 'client@example.test',
        'recipients' => [$owner->email],
        'direction' => 'Incoming',
        'occurred_at' => now(),
    ]);
    $otherEmail = EmailActivity::query()->create([
        'provider' => 'imap',
        'mailbox_key' => $other->email,
        'provider_message_id' => 'timeline-other-email',
        'mailbox_owner_user_id' => $other->id,
        'company_id' => $company->id,
        'contact_id' => $contact->id,
        'sender_email' => 'client@example.test',
        'recipients' => [$other->email],
        'direction' => 'Incoming',
        'occurred_at' => now(),
    ]);

    $assignedMessage = \App\Models\MessageConversation::query()->create([
        'company_id' => $company->id,
        'contact_id' => $contact->id,
        'assigned_user_id' => $owner->id,
        'conversation_title' => 'Assigned owner message',
    ]);
    $preparedMessage = \App\Models\MessageConversation::query()->create([
        'company_id' => $company->id,
        'contact_id' => $contact->id,
        'assigned_user_id' => $other->id,
        'conversation_title' => 'Prepared owner message',
    ]);
    $preparedMessage->prepared_by_user_id = $owner->id;
    $preparedMessage->save();
    $hiddenMessage = \App\Models\MessageConversation::query()->create([
        'company_id' => $company->id,
        'contact_id' => $contact->id,
        'assigned_user_id' => $other->id,
        'conversation_title' => 'Other employee message',
    ]);
    $hiddenMessage->prepared_by_user_id = $other->id;
    $hiddenMessage->save();

    $ownerTrip = OfficialBusinessTrip::query()->create([
        'employee_id' => $ownerEmployee->id,
        'employee_name' => $owner->name,
        'destination' => 'Owner Site',
        'date_from' => '2026-09-04',
        'company_id' => $company->id,
        'contact_id' => $contact->id,
        'created_by' => $owner->id,
    ]);
    $otherTrip = OfficialBusinessTrip::query()->create([
        'employee_id' => $otherEmployee->id,
        'employee_name' => $other->name,
        'destination' => 'Other Site',
        'date_from' => '2026-09-04',
        'company_id' => $company->id,
        'contact_id' => $contact->id,
        'created_by' => $other->id,
    ]);

    $ownerTimeline = collect(app(ActivityTimelineBuilder::class)->forCompany($company, $owner));
    $ownerKeys = $ownerTimeline->map(fn (array $activity): string => $activity['source_activity_type'].':'.$activity['source_id']);
    $ownerContactKeys = collect(app(ActivityTimelineBuilder::class)->forContact($contact, $owner))
        ->map(fn (array $activity): string => $activity['source_activity_type'].':'.$activity['source_id']);

    expect($ownerKeys->all())
        ->toContain(
            'TASK:'.$sharedTask->id,
            'EMAIL:'.$ownerEmail->id,
            'MESSAGE:'.$assignedMessage->id,
            'MESSAGE:'.$preparedMessage->id,
            'OFFICIAL_BUSINESS_TRAVEL:'.$ownerTrip->id
        )
        ->not->toContain(
            'EMAIL:'.$otherEmail->id,
            'MESSAGE:'.$hiddenMessage->id,
            'OFFICIAL_BUSINESS_TRAVEL:'.$otherTrip->id
        );

    expect($ownerContactKeys->all())->toBe($ownerKeys->all());

    $sharedTask->delete();

    $this->actingAs($owner);
    $activities = $this->getJson('/api/activities')->assertOk();
    expect(collect($activities->json('messages'))->pluck('id')->all())
        ->toContain($assignedMessage->id, $preparedMessage->id)
        ->not->toContain($hiddenMessage->id);

    $adminTimeline = collect(app(ActivityTimelineBuilder::class)->forCompany($company, $this->user));
    $adminKeys = $adminTimeline->map(fn (array $activity): string => $activity['source_activity_type'].':'.$activity['source_id']);

    expect($adminKeys->all())
        ->not->toContain('EMAIL:'.$ownerEmail->id)
        ->not->toContain('EMAIL:'.$otherEmail->id)
        ->toContain(
        'MESSAGE:'.$assignedMessage->id,
        'MESSAGE:'.$preparedMessage->id,
        'MESSAGE:'.$hiddenMessage->id,
        'OFFICIAL_BUSINESS_TRAVEL:'.$ownerTrip->id,
        'OFFICIAL_BUSINESS_TRAVEL:'.$otherTrip->id
    );
});

test('hybrid meeting creates one calendar event and meet without default external email and updates idempotently', function () {
    Mail::fake();
    config()->set('services.google_calendar', [
        'calendar_id' => 'primary@example.test',
        'access_token' => 'fake-calendar-token',
        'api_base' => 'https://calendar.test/v3',
        'timezone' => 'Asia/Manila',
        'timeout' => 5,
    ]);
    Http::fake(fn () => Http::response([
        'id' => 'ordomeeting1',
        'hangoutLink' => 'https://meet.google.com/abc-defg-hij',
    ]));
    $companyId = DB::table('companies')->insertGetId([
        'company_name' => 'Townland',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $payload = [
        'title' => 'Hybrid Townland Meeting',
        'meeting_type' => 'Client Meeting',
        'mode' => 'Hybrid',
        'date' => '2026-08-28',
        'time' => '10:00',
        'duration' => '1 hr',
        'duration_minutes' => 60,
        'location' => 'Townland Office',
        'company_id' => $companyId,
        'external_attendees' => [['email' => 'client@example.test']],
        'add_to_calendar' => true,
        'create_google_meet' => true,
    ];

    $created = $this->postJson('/api/meetings', $payload)
        ->assertOk()
        ->assertJsonPath('calendar_provider', 'google')
        ->assertJsonPath('external_calendar_event_id', 'ordomeeting1')
        ->assertJsonPath('conference_url', 'https://meet.google.com/abc-defg-hij')
        ->assertJsonPath('calendar_sync_status', 'synced')
        ->assertJsonPath('notify_external_attendees', false);

    $meetingId = $created->json('id');

    $this->putJson('/api/meetings/'.$meetingId, $payload + ['title' => 'Updated Hybrid Townland Meeting'])
        ->assertOk()
        ->assertJsonPath('external_calendar_event_id', 'ordomeeting1')
        ->assertJsonPath('conference_url', 'https://meet.google.com/abc-defg-hij');

    $requests = Http::recorded();
    expect($requests->filter(fn (array $pair): bool => $pair[0]->method() === 'POST'))->toHaveCount(1)
        ->and($requests->filter(fn (array $pair): bool => $pair[0]->method() === 'PATCH'))->toHaveCount(2)
        ->and(Meeting::query()->count())->toBe(1);
    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'sendUpdates=none'));
    Mail::assertNothingSent();
});

test('explicit meeting notification setting is passed to google calendar with external attendees', function () {
    Mail::fake();
    config()->set('services.google_calendar', [
        'calendar_id' => 'primary@example.test',
        'access_token' => 'fake-calendar-token',
        'api_base' => 'https://calendar.test/v3',
        'timezone' => 'Asia/Manila',
        'timeout' => 5,
    ]);
    Http::fake(fn () => Http::response([
        'id' => 'ordomeeting1',
        'hangoutLink' => 'https://meet.google.com/notify-test',
    ]));

    $this->postJson('/api/meetings', [
        'title' => 'Notified Hybrid Meeting',
        'mode' => 'Hybrid',
        'date' => '2026-08-29',
        'time' => '09:00',
        'location' => 'Client Office',
        'external_attendees' => [['email' => 'client@example.test']],
        'add_to_calendar' => true,
        'create_google_meet' => true,
        'notify_external_attendees' => true,
    ])->assertOk()
        ->assertJsonPath('notify_external_attendees', true);

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'sendUpdates=all')
        && data_get($request->data(), 'attendees.0.email') === 'client@example.test');
    Mail::assertNothingSent();
});

test('calendar validation and failed external sync preserve an explicit retryable meeting state', function () {
    $this->postJson('/api/meetings', [
        'title' => 'Invalid Physical Meet',
        'mode' => 'Physical',
        'date' => '2026-08-30',
        'time' => '09:00',
        'location' => 'Office',
        'create_google_meet' => true,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('create_google_meet');

    config()->set('services.google_calendar', [
        'calendar_id' => 'primary@example.test',
        'access_token' => 'fake-calendar-token',
        'api_base' => 'https://calendar.test/v3',
        'timezone' => 'Asia/Manila',
        'timeout' => 5,
    ]);
    Http::fake(fn () => Http::response(['error' => 'provider unavailable'], 500));

    $response = $this->postJson('/api/meetings', [
        'title' => 'Retryable Hybrid Meeting',
        'mode' => 'Hybrid',
        'date' => '2026-08-30',
        'time' => '10:00',
        'location' => 'Office',
        'add_to_calendar' => true,
        'create_google_meet' => true,
    ])->assertStatus(202)
        ->assertJsonPath('calendar_sync_status', 'failed')
        ->assertJsonPath('external_calendar_event_id', null)
        ->assertJsonStructure(['calendar_sync_warning']);

    $this->assertDatabaseHas('meetings', [
        'id' => $response->json('id'),
        'calendar_provider' => 'google',
        'calendar_sync_status' => 'failed',
    ]);
});

test('meeting workspace finalizes structured minutes and converts action items to linked tasks', function () {
    $participant = User::query()->create([
        'name' => 'Internal Participant',
        'email' => 'participant@example.test',
        'role' => 'Employee',
    ]);
    $companyId = DB::table('companies')->insertGetId([
        'company_name' => 'Townland',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $meeting = Meeting::query()->create(['title' => 'Workspace Meeting', 'company_id' => $companyId]);

    $this->putJson("/api/meetings/{$meeting->id}/workspace", [
        'internal_participant_user_ids' => [$participant->id],
        'external_attendees' => [[
            'name' => 'Client Attendee',
            'email' => 'client@example.test',
            'organization' => 'Acme',
        ]],
        'structured_minutes' => [
            'summary' => 'Approved discussion summary',
            'decisions' => ['Proceed'],
        ],
        'recording_metadata' => [['filename' => 'recording.mp4']],
        'transcript_metadata' => [['filename' => 'transcript.txt']],
        'files_metadata' => [['filename' => 'agenda.pdf']],
        'finalize_minutes' => true,
    ])->assertOk()
        ->assertJsonPath('internal_participants.0.id', $participant->id)
        ->assertJsonPath('external_attendees.0.organization', 'Acme')
        ->assertJsonPath('structured_minutes.summary', 'Approved discussion summary')
        ->assertJsonPath('finalized_minutes_by.id', $this->user->id)
        ->assertJsonPath('minutes_versions.0.status', 'FINAL')
        ->assertJsonPath('minutes_versions.0.version_number', 1);

    $actionItemId = $this->postJson("/api/meetings/{$meeting->id}/action-items", [
        'description' => 'Prepare filing package',
        'assigned_to_user_id' => $participant->id,
        'due_date' => '2026-09-01',
    ])->assertCreated()->json('id');

    $converted = $this->postJson("/api/meetings/{$meeting->id}/action-items/{$actionItemId}/convert-to-task")
        ->assertCreated()
        ->assertJsonPath('task.name', 'Prepare filing package')
        ->assertJsonPath('task.status', 'Open');

    $taskId = $converted->json('task.id');
    $this->assertDatabaseHas('tasks', [
        'id' => $taskId,
        'prepared_by_user_id' => $this->user->id,
        'assigned_to_user_id' => $participant->id,
    ]);
    $this->assertDatabaseCount('activity_links', 1);
    $this->assertDatabaseHas('activity_audit_logs', ['action' => 'minutes_finalized']);
    $this->assertDatabaseHas('activity_audit_logs', ['action' => 'action_item_converted_to_task']);

    $timelineKeys = collect(app(ActivityTimelineBuilder::class)->forCompany(['id' => $companyId], $this->user))
        ->map(fn (array $activity): string => $activity['source_activity_type'].':'.$activity['source_id']);
    expect($timelineKeys->all())->toContain('MEETING:'.$meeting->id, 'TASK:'.$taskId);
});

test('a link never grants visibility to an unauthorized official business source', function () {
    $regular = User::query()->create([
        'name' => 'Regular Employee',
        'email' => 'regular@example.test',
        'role' => 'Employee',
    ]);
    Schema::getConnection()->table('user_permissions')->insert([
        'user_id' => $regular->id,
        'access_activities' => true,
        'access_hc_obf' => false,
        'access_hc_my_hc' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    Employee::query()->create([
        'user_id' => $regular->id,
        'first_name' => 'Regular',
        'last_name' => 'Employee',
        'work_email' => $regular->email,
    ]);
    $otherUser = User::query()->create([
        'name' => 'Other Employee',
        'email' => 'other-ob@example.test',
        'role' => 'Employee',
    ]);
    $otherEmployee = Employee::query()->create([
        'user_id' => $otherUser->id,
        'first_name' => 'Other',
        'last_name' => 'Employee',
        'work_email' => $otherUser->email,
    ]);
    $hiddenTrip = OfficialBusinessTrip::query()->create([
        'employee_id' => $otherEmployee->id,
        'employee_name' => 'Other Employee',
        'destination' => 'Hidden Site',
        'date_from' => '2026-08-24',
        'created_by' => $otherUser->id,
    ]);
    $task = Task::query()->create(['name' => 'Visible Task']);
    ActivityLink::query()->create([
        'source_type' => 'OFFICIAL_BUSINESS_TRAVEL',
        'source_id' => $hiddenTrip->id,
        'related_type' => 'TASK',
        'related_id' => $task->id,
        'created_by_user_id' => $this->user->id,
    ]);

    $this->actingAs($regular)
        ->getJson("/api/activities/task/{$task->id}/related")
        ->assertOk()
        ->assertJsonCount(1, 'related_activities')
        ->assertJsonPath('related_activities.0.type', 'OFFICIAL_BUSINESS_TRAVEL');
});

test('per employee Gmail connections isolate content, sending, threads and disconnects', function () {
    config()->set('services.gmail.enabled', true);
    $employeeA = User::query()->create(['name' => 'Gmail Employee A', 'email' => 'gmail-a@example.test', 'role' => 'Employee']);
    $employeeB = User::query()->create(['name' => 'Gmail Employee B', 'email' => 'gmail-b@example.test', 'role' => 'Employee']);
    DB::table('user_permissions')->insert([
        ['user_id' => $employeeA->id, 'access_activities' => true],
        ['user_id' => $employeeB->id, 'access_activities' => true],
    ]);

    $connectionA = EmailMailboxConnection::query()->create([
        'provider' => 'gmail', 'email_address' => 'a@gmail.example',
        'encrypted_refresh_token' => Crypt::encryptString('refresh-a'), 'user_id' => $employeeA->id,
    ]);
    $connectionB = EmailMailboxConnection::query()->create([
        'provider' => 'gmail', 'email_address' => 'b@gmail.example',
        'encrypted_refresh_token' => Crypt::encryptString('refresh-b'), 'user_id' => $employeeB->id,
    ]);
    $emailB = EmailActivity::query()->create([
        'provider' => 'gmail', 'mailbox_key' => $connectionB->email_address, 'provider_message_id' => 'gmail-b-1',
        'mailbox_owner_user_id' => $employeeB->id, 'mailbox_connection_id' => $connectionB->id,
        'sender_email' => 'client@example.test', 'recipients' => [$connectionB->email_address],
        'direction' => 'Incoming', 'occurred_at' => now(), 'subject' => 'Private B', 'body' => 'Private B body',
        'thread_id' => 'thread-b', 'rfc_message_id' => '<b-1@example.test>', 'delivery_status' => 'Received',
    ]);

    $this->actingAs($employeeA);
    $this->getJson('/api/email-activities/'.$emailB->id)->assertNotFound();
    $this->getJson('/api/email-activities/'.$emailB->id.'/thread')->assertNotFound();
    $this->postJson('/api/email-activities/'.$emailB->id.'/reply', ['subject' => 'No'])->assertNotFound();
    $this->get('/api/email-activities/'.$emailB->id.'/attachments/not-owned')->assertNotFound();
    $this->postJson('/api/email-activities/send', [
        'from_mailbox' => $connectionB->email_address,
        'to' => 'recipient@example.test', 'subject' => 'Spoof', 'body' => 'No',
    ])->assertNotFound();
    $this->post('/api/email/gmail/disconnect')->assertOk();
    expect($connectionB->fresh()->connection_status)->toBe('connected')
        ->and($connectionA->user_id)->toBe($employeeA->id);

    $this->actingAs($employeeB)->getJson('/api/email-activities/'.$emailB->id)->assertOk();
    $this->actingAs($this->user)->getJson('/api/email-activities/'.$emailB->id)->assertNotFound();
});

test('Gmail OAuth state is bound to the current JKNC user', function () {
    $employeeA = User::query()->create(['name' => 'OAuth Employee A', 'email' => 'oauth-a@example.test', 'role' => 'Employee']);
    $employeeB = User::query()->create(['name' => 'OAuth Employee B', 'email' => 'oauth-b@example.test', 'role' => 'Employee']);
    DB::table('user_permissions')->insert(['user_id' => $employeeA->id, 'access_activities' => true]);
    config()->set('services.gmail.enabled', true);
    config()->set('services.gmail.client_id', 'test-client');
    config()->set('services.gmail.client_secret', 'test-secret');
    config()->set('services.gmail.redirect_uri', 'https://jknc.test/api/email/gmail/callback');

    $this->withMiddleware()->actingAs($employeeA)
        ->withSession(['gmail_oauth_state' => ['value' => 'state-a', 'user_id' => $employeeB->id]])
        ->get('/api/email/gmail/callback?state=state-a&code=code-a')
        ->assertForbidden();

    expect(EmailMailboxConnection::query()->count())->toBe(0);
});

test('new cross activity endpoints retain the Activities permission middleware', function () {
    foreach ([
        'api/activities/report',
        'api/activities/export',
        'api/activities/{type}/{id}/related',
        'api/activities/{type}/{id}/attachments',
        'api/activities/{type}/{id}/audit-log',
        'api/email-activities',
        'api/email-activities/sync',
        'api/email-activities/{id}',
        'api/meetings/{meeting}/workspace',
        'api/tasks/workflow',
        'api/tasks/outline',
        'api/tasks/reorder',
        'api/tasks/bulk',
        'api/tasks/{task}/timer/{action}',
        'api/tasks/{task}/duplicate',
        'api/task-templates/{template}/instantiate',
    ] as $uri) {
        $route = collect(Route::getRoutes())->first(fn ($route) => $route->uri() === $uri);

        expect($route)->not->toBeNull()
            ->and($route->gatherMiddleware())->toContain('auth')
            ->and($route->gatherMiddleware())->toContain('human-capital.module:access_activities');
    }
});

test('manual message v2 metadata remains one conversation and contributes optional duration safely', function () {
    $company = \App\Models\Company::query()->create(['company_name' => 'Message Client']);
    $contact = \App\Models\Contact::query()->create(['first_name' => 'Mina', 'last_name' => 'Client']);

    $created = $this->postJson('/api/message-conversations', [
        'company_id' => $company->id,
        'contact_id' => $contact->id,
        'participant_contact_ids' => [$contact->id],
        'channel' => 'Viber',
        'direction' => 'Conversation Thread',
        'conversation_summary' => 'Confirmed the filing timetable.',
        'duration_minutes' => 25,
        'include_in_client_report' => true,
    ])->assertCreated()
        ->assertJsonPath('conversation_summary', 'Confirmed the filing timetable.')
        ->assertJsonPath('duration_minutes', 25)
        ->assertJsonPath('participant_contact_ids.0', $contact->id);

    expect(\App\Models\MessageConversation::count())->toBe(1);

    $report = $this->postJson('/api/activities/report', [
        'selected_ids' => ['message-'.$created->json('id')],
    ])->assertOk()
        ->assertJsonPath('rows.0.report_description', 'Confirmed the filing timetable.')
        ->assertJsonPath('rows.0.duration_minutes', 25)
        ->assertJsonPath('duration_coverage.by_activity_type.0.activity_type', 'MESSAGE');

    expect($report->json('rows.0'))->not->toHaveKey('body');
});

test('official business report ignores legacy stored duration and uses actual travel timestamps', function () {
    $department = \App\Models\Department::query()->create([
        'department_name' => 'Client Service',
    ]);

    $employee = Employee::query()->create([
        'user_id' => $this->user->id,
        'department_id' => $department->id,
        'first_name' => 'Activities',
        'last_name' => 'Admin',
        'work_email' => $this->user->email,
    ]);

    $company = \App\Models\Company::query()->create([
        'company_name' => 'Travel Client',
    ]);

    $legacyTrip = OfficialBusinessTrip::query()->create([
        'employee_id' => $employee->id,
        'employee_name' => 'Activities Admin',
        'destination' => 'Legacy Client Site',
        'purpose' => 'Legacy records delivery',
        'date_from' => '2026-08-24',
        'company_id' => $company->id,
        'duration_minutes' => 80,
        'prepared_by_user_id' => $this->user->id,
    ]);

    $this->postJson('/api/activities/report', [
        'selected_ids' => ['official-business-travel-'.$legacyTrip->id],
    ])->assertOk()
        ->assertJsonPath('rows.0.source_activity_id', $legacyTrip->id)
        ->assertJsonPath('rows.0.company_id', $company->id)
        ->assertJsonPath('rows.0.company', 'Travel Client')
        ->assertJsonPath('rows.0.duration_minutes', null)
        ->assertJsonPath('duration_coverage.activities_with_duration', 0)
        ->assertJsonPath('duration_coverage.total_duration_minutes', 0);

    $actualTrip = OfficialBusinessTrip::query()->create([
        'employee_id' => $employee->id,
        'employee_name' => 'Activities Admin',
        'destination' => 'Actual Client Site',
        'purpose' => 'Actual records delivery',
        'date_from' => '2026-08-24',
        'company_id' => $company->id,
        'actual_departure' => '2026-08-24 08:00:00',
        'actual_return' => '2026-08-24 09:20:00',
        'duration_minutes' => 999,
        'prepared_by_user_id' => $this->user->id,
    ]);

    $this->postJson('/api/activities/report', [
        'selected_ids' => ['official-business-travel-'.$actualTrip->id],
    ])->assertOk()
        ->assertJsonPath('rows.0.source_activity_id', $actualTrip->id)
        ->assertJsonPath('rows.0.duration_minutes', 80)
        ->assertJsonPath('rows.0.duration_hours', 1.33)
        ->assertJsonPath('duration_coverage.activities_with_duration', 1)
        ->assertJsonPath('duration_coverage.total_duration_minutes', 80);
});

test('gmail sync stores one canonical incoming message and is idempotent', function () {
    config()->set('services.gmail.enabled', true);
    $connection = EmailMailboxConnection::query()->create([
        'provider' => 'gmail',
        'email_address' => 'consultation@example.test',
        'encrypted_refresh_token' => Crypt::encryptString('refresh-token'),
        'user_id' => $this->user->id,
    ]);

    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        if (str_contains($request->url(), 'oauth2.googleapis.com/token')) return Http::response(['access_token' => 'access-token'], 200);
        if (str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/messages')) return Http::response(['messages' => [['id' => 'gmail-message-1']]], 200);
        return Http::response(['id' => 'gmail-message-1', 'threadId' => 'gmail-thread-1', 'labelIds' => ['INBOX'], 'payload' => [
            'headers' => [
                ['name' => 'From', 'value' => 'client@example.test'],
                ['name' => 'To', 'value' => 'consultation@example.test'],
                ['name' => 'Subject', 'value' => 'Consultation request'],
                ['name' => 'Message-ID', 'value' => '<gmail-message-1@example.test>'],
                ['name' => 'Date', 'value' => 'Wed, 30 Sep 2026 10:00:00 +0800'],
            ],
            'mimeType' => 'text/plain',
            'body' => ['data' => rtrim(strtr(base64_encode('Please advise on our consultation.'), '+/', '-_'), '=')],
        ]], 200);
    });

    app(\App\Services\Gmail\GmailTransport::class)->sync($connection);
    app(\App\Services\Gmail\GmailTransport::class)->sync($connection->fresh());

    expect(EmailActivity::query()->count())->toBe(1)
        ->and(EmailActivity::query()->first()->thread_id)->toBe('gmail-thread-1')
        ->and(EmailActivity::query()->first()->body)->toContain('consultation');
});

test('authorized compose creates one pending gmail delivery and preserves thread on reply', function () {
    config()->set('services.gmail.enabled', true);
    $connection = EmailMailboxConnection::query()->create([
        'provider' => 'gmail',
        'email_address' => 'consultation@example.test',
        'encrypted_refresh_token' => Crypt::encryptString('refresh-token'),
        'user_id' => $this->user->id,
    ]);
    Queue::fake();

    $incoming = EmailActivity::query()->create([
        'provider' => 'gmail', 'mailbox_key' => $connection->email_address, 'provider_message_id' => 'incoming-1',
        'mailbox_owner_user_id' => $this->user->id, 'mailbox_connection_id' => $connection->id,
        'thread_id' => 'thread-1', 'rfc_message_id' => '<incoming-1@example.test>',
        'sender_email' => 'client@example.test', 'recipients' => [$connection->email_address],
        'direction' => 'Incoming', 'occurred_at' => now(), 'subject' => 'Question', 'body' => 'Question',
        'delivery_status' => 'Received',
    ]);

    $this->postJson('/api/email-activities/send', [
        'from_mailbox' => $connection->email_address,
        'to' => ['recipient@example.test'], 'subject' => 'New consultation', 'body' => 'Hello',
    ])->assertStatus(202)->assertJsonPath('delivery_status', 'Pending');

    $this->postJson('/api/email-activities/'.$incoming->id.'/reply', [
        'body' => 'Here is our reply.',
    ])->assertStatus(202)->assertJsonPath('thread_id', 'thread-1')->assertJsonPath('in_reply_to', '<incoming-1@example.test>');

    expect(EmailActivity::query()->where('direction', 'Outgoing')->count())->toBe(2);
    Queue::assertPushed(\App\Jobs\SendGmailEmail::class, 2);
});

test('gmail send transitions pending email to sent with provider identity', function () {
    config()->set('services.gmail.enabled', true);
    $connection = EmailMailboxConnection::query()->create([
        'provider' => 'gmail', 'email_address' => 'consultation@example.test',
        'encrypted_refresh_token' => Crypt::encryptString('refresh-token'), 'user_id' => $this->user->id,
    ]);
    $email = EmailActivity::query()->create([
        'provider' => 'gmail', 'mailbox_key' => $connection->email_address, 'provider_message_id' => 'pending-send-1',
        'mailbox_owner_user_id' => $this->user->id, 'mailbox_connection_id' => $connection->id,
        'sender_email' => $connection->email_address, 'recipients' => ['recipient@example.test'],
        'direction' => 'Outgoing', 'occurred_at' => now(), 'subject' => 'Test', 'body' => 'Test',
        'delivery_status' => 'Pending', 'idempotency_key' => 'send-key-1',
    ]);
    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        if (str_contains($request->url(), 'oauth2.googleapis.com/token')) return Http::response(['access_token' => 'access-token'], 200);
        return Http::response(['id' => 'gmail-sent-1', 'threadId' => 'gmail-thread-1'], 200);
    });

    (new \App\Jobs\SendGmailEmail($email->id))->handle(app(\App\Services\Gmail\GmailTransport::class));
    $email->refresh();
    expect($email->delivery_status)->toBe('Sent')->and($email->provider_message_id)->toBe('gmail-sent-1');
});

test('an authorized employee can connect only their Gmail and refresh tokens never reach Activities JSON', function () {
    $employee = User::query()->create(['name' => 'Mailbox Employee', 'email' => 'mailbox-employee@example.test', 'role' => 'Employee']);

    config()->set('services.gmail.enabled', true);
    config()->set('services.gmail.client_id', 'test-client');
    config()->set('services.gmail.client_secret', 'test-secret');
    config()->set('services.gmail.redirect_uri', 'https://jknc.test/api/email/gmail/callback');
    Queue::fake();
    Http::preventStrayRequests();
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-token', 'refresh_token' => 'refresh-token'], 200),
        'https://gmail.googleapis.com/gmail/v1/users/me/profile' => Http::response(['emailAddress' => 'consultation@example.test'], 200),
    ]);

    DB::table('user_permissions')->insert(['user_id' => $employee->id, 'access_activities' => true]);
    $this->withMiddleware()->actingAs($employee)->get('/api/email/gmail/connect')->assertRedirect();

    $this->withMiddleware()->actingAs($this->user)->withSession(['gmail_oauth_state' => ['value' => 'state-1', 'user_id' => $this->user->id]])
        ->get('/api/email/gmail/callback?state=state-1&code=code-1')->assertRedirect('/activities?email=connected');

    $connection = EmailMailboxConnection::query()->firstOrFail();
    expect($connection->email_address)->toBe('consultation@example.test')
        ->and(Crypt::decryptString($connection->encrypted_refresh_token))->toBe('refresh-token');
    Queue::assertPushed(\App\Jobs\SyncGmailMailboxes::class, fn ($job): bool => $job->connectionId === $connection->id);
    $this->getJson('/api/activities')->assertOk()->assertDontSee('refresh-token');
});

test('gmail send records a failed provider response without exposing raw error details', function () {
    config()->set('services.gmail.enabled', true);
    $connection = EmailMailboxConnection::query()->create([
        'provider' => 'gmail', 'email_address' => 'consultation@example.test',
        'encrypted_refresh_token' => Crypt::encryptString('refresh-token'), 'user_id' => $this->user->id,
    ]);
    $email = EmailActivity::query()->create([
        'provider' => 'gmail', 'mailbox_key' => $connection->email_address, 'provider_message_id' => 'pending-send-fail',
        'mailbox_owner_user_id' => $this->user->id, 'mailbox_connection_id' => $connection->id,
        'sender_email' => $connection->email_address, 'recipients' => ['recipient@example.test'],
        'direction' => 'Outgoing', 'occurred_at' => now(), 'subject' => 'Test', 'body' => 'Test',
        'delivery_status' => 'Pending', 'idempotency_key' => 'send-key-fail',
    ]);
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-token'], 200),
        'https://gmail.googleapis.com/gmail/v1/users/me/messages/send' => Http::response(['error' => ['message' => 'private provider detail']], 500),
    ]);

    (new \App\Jobs\SendGmailEmail($email->id))->handle(app(\App\Services\Gmail\GmailTransport::class));
    expect($email->refresh()->delivery_status)->toBe('Failed')
        ->and($email->provider_error)->toBe('Gmail delivery failed.');
});

test('gmail disabled is a kill switch for oauth sync compose reply and queued jobs', function () {
    config()->set('services.gmail.enabled', false);
    Http::fake();

    $this->get('/api/email/gmail/connect')->assertStatus(503);
    $this->withSession(['gmail_oauth_state' => ['value' => 'disabled-state', 'user_id' => $this->user->id]])
        ->get('/api/email/gmail/callback?state=disabled-state&code=disabled-code')
        ->assertStatus(503);
    $this->postJson('/api/email-activities/sync', [
        'provider' => 'gmail', 'mailbox_key' => 'disabled@example.test', 'provider_message_id' => 'disabled-1',
        'sender_email' => 'sender@example.test', 'recipients' => ['disabled@example.test'],
        'direction' => 'Incoming', 'occurred_at' => now()->toISOString(),
    ])->assertStatus(503);
    $this->postJson('/api/email-activities/send', [
        'to' => ['recipient@example.test'], 'subject' => 'Disabled', 'body' => 'Disabled',
    ])->assertStatus(503);

    $connection = EmailMailboxConnection::query()->create([
        'provider' => 'gmail', 'email_address' => 'disabled@example.test',
        'encrypted_refresh_token' => Crypt::encryptString('refresh-token'), 'user_id' => $this->user->id,
    ]);
    $incoming = EmailActivity::query()->create([
        'provider' => 'gmail', 'mailbox_key' => $connection->email_address, 'provider_message_id' => 'disabled-incoming',
        'mailbox_owner_user_id' => $this->user->id, 'mailbox_connection_id' => $connection->id,
        'sender_email' => 'sender@example.test', 'recipients' => [$connection->email_address],
        'direction' => 'Incoming', 'occurred_at' => now(), 'subject' => 'Incoming', 'body' => 'Incoming',
        'delivery_status' => 'Received',
    ]);
    $this->postJson('/api/email-activities/'.$incoming->id.'/reply', [
        'body' => 'Reply while disabled',
    ])->assertStatus(503);
    $email = EmailActivity::query()->create([
        'provider' => 'gmail', 'mailbox_key' => $connection->email_address, 'provider_message_id' => 'disabled-pending',
        'mailbox_owner_user_id' => $this->user->id, 'mailbox_connection_id' => $connection->id,
        'sender_email' => $connection->email_address, 'recipients' => ['recipient@example.test'],
        'direction' => 'Outgoing', 'occurred_at' => now(), 'subject' => 'Disabled', 'body' => 'Disabled',
        'delivery_status' => 'Pending', 'idempotency_key' => 'disabled-send',
    ]);

    (new \App\Jobs\SyncGmailMailboxes($connection->id))->handle(app(\App\Services\Gmail\GmailTransport::class));
    (new \App\Jobs\SendGmailEmail($email->id))->handle(app(\App\Services\Gmail\GmailTransport::class));

    Http::assertNothingSent();
    expect($email->fresh()->delivery_status)->toBe('Pending')
        ->and($connection->fresh()->last_synced_at)->toBeNull();
});

test('gmail reauthorization preserves the existing refresh token when Google omits one', function () {
    config()->set('services.gmail.enabled', true);
    config()->set('services.gmail.client_id', 'test-client');
    config()->set('services.gmail.client_secret', 'test-secret');
    config()->set('services.gmail.redirect_uri', 'https://jknc.test/api/email/gmail/callback');
    Queue::fake();
    Http::preventStrayRequests();

    $connection = EmailMailboxConnection::query()->create([
        'provider' => 'gmail', 'email_address' => 'old@example.test',
        'encrypted_refresh_token' => Crypt::encryptString('existing-refresh-token'), 'user_id' => $this->user->id,
    ]);
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-token'], 200),
        'https://gmail.googleapis.com/gmail/v1/users/me/profile' => Http::response(['emailAddress' => 'new@example.test'], 200),
    ]);

    $this->withMiddleware()->actingAs($this->user)
        ->withSession(['gmail_oauth_state' => ['value' => 'reauth-state', 'user_id' => $this->user->id]])
        ->get('/api/email/gmail/callback?state=reauth-state&code=reauth-code')
        ->assertRedirect('/activities?email=connected');

    expect(Crypt::decryptString($connection->fresh()->encrypted_refresh_token))->toBe('existing-refresh-token')
        ->and($connection->fresh()->email_address)->toBe('new@example.test');
});

test('gmail compose rejects unsafe or oversized attachments', function () {
    config()->set('services.gmail.enabled', true);
    $connection = EmailMailboxConnection::query()->create([
        'provider' => 'gmail', 'email_address' => 'attachments@example.test',
        'encrypted_refresh_token' => Crypt::encryptString('refresh-token'), 'user_id' => $this->user->id,
    ]);

    $this->postJson('/api/email-activities/send', [
        'from_mailbox' => $connection->email_address,
        'to' => ['recipient@example.test'], 'subject' => 'Attachment', 'body' => 'Attachment',
        'attachments' => [UploadedFile::fake()->create('unsafe.exe', 10, 'application/x-msdownload')],
    ])->assertStatus(422);

    expect(EmailActivity::query()->where('direction', 'Outgoing')->count())->toBe(0);
});

test('gmail jobs use isolated queues and per-mailbox overlap middleware', function () {
    $sync = new \App\Jobs\SyncGmailMailboxes(41);
    $send = new \App\Jobs\SendGmailEmail(42);

    expect($sync->queue)->toBe('gmail-sync')
        ->and($send->queue)->toBe('gmail-send')
        ->and($sync->middleware())->toHaveCount(1)
        ->and($sync->tries)->toBe(3)
        ->and($sync->timeout)->toBe(120)
        ->and($send->tries)->toBe(1)
        ->and($send->timeout)->toBe(90);
});

test('gmail migrations roll back only their own schema in dependency order', function () {
    (require database_path('migrations/2026_10_01_180001_add_email_mailbox_sync_checkpoint.php'))->down();
    (require database_path('migrations/2026_10_01_180000_scope_email_activities_idempotency_to_mailbox.php'))->down();
    $gmailEmailMigration = require database_path('migrations/2026_09_30_180001_extend_email_activities_for_gmail.php');
    $gmailConnectionMigration = require database_path('migrations/2026_09_30_180000_create_email_mailbox_connections_table.php');

    $gmailEmailMigration->down();

    expect(Schema::hasTable('email_activities'))->toBeTrue()
        ->and(Schema::hasColumn('email_activities', 'mailbox_connection_id'))->toBeFalse()
        ->and(Schema::hasColumn('email_activities', 'thread_id'))->toBeFalse()
        ->and(Schema::hasColumn('email_activities', 'idempotency_key'))->toBeFalse();

    $gmailConnectionMigration->down();

    expect(Schema::hasTable('email_mailbox_connections'))->toBeFalse();
});

test('production readiness retains every legacy company activity through migration and rollback', function () {
    (require database_path('migrations/2026_03_16_000002_create_company_activities_table.php'))->up();
    DB::table('company_activities')->insert([
        'company_id' => 999, 'type' => 'Legacy custom type', 'description' => 'Irreplaceable original description',
        'assigned_user' => 'Legacy free-text assignee', 'notes' => 'Historical notes', 'status' => 'Pending',
        'due_at' => '2026-01-02 10:00:00', 'completed_at' => null,
    ]);
    $before = DB::table('company_activities')->get()->toArray();
    $migration = require database_path('migrations/2026_09_04_135548_drop_company_activities_table.php');
    $migration->up();
    expect(DB::table('company_activities')->get()->toArray())->toEqual($before);
    $migration->down();
    expect(DB::table('company_activities')->get()->toArray())->toEqual($before);
    // Canonical application services must not reintroduce reads/writes to the retained table.
    foreach (['app', 'routes', 'resources'] as $directory) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($directory))) as $file) {
            if ($file->isFile() && in_array($file->getExtension(), ['php', 'js'], true)) {
                expect(file_get_contents($file->getPathname()))->not->toContain('company_activities');
            }
        }
    }
});

test('production readiness Gmail authorization uses the Google web server endpoint without secrets', function () {
    config()->set('services.gmail.enabled', true);
    config()->set('services.gmail.client_id', 'test-web-client');
    config()->set('services.gmail.client_secret', 'synthetic-secret-not-for-url');
    config()->set('services.gmail.redirect_uri', 'https://production.example/api/email/gmail/callback');
    $url = app(\App\Services\Gmail\GmailTransport::class)->authorizationUrl('state with + special & characters');
    expect(parse_url($url, PHP_URL_SCHEME))->toBe('https')
        ->and(parse_url($url, PHP_URL_HOST))->toBe('accounts.google.com')
        ->and(parse_url($url, PHP_URL_PATH))->toBe('/o/oauth2/v2/auth')
        ->and($url)->not->toContain('synthetic-secret-not-for-url');
    parse_str(parse_url($url, PHP_URL_QUERY), $query);
    expect($query)->toMatchArray([
        'client_id' => 'test-web-client', 'redirect_uri' => 'https://production.example/api/email/gmail/callback',
        'response_type' => 'code', 'access_type' => 'offline', 'prompt' => 'consent',
        'state' => 'state with + special & characters',
        'scope' => 'https://www.googleapis.com/auth/gmail.readonly https://www.googleapis.com/auth/gmail.send',
    ])->and($query)->not->toHaveKey('client_secret');
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'synthetic-access'], 200),
        'https://gmail.googleapis.com/gmail/v1/users/me/profile' => Http::response(['emailAddress' => 'pilot@example.test'], 200),
    ]);
    $gmail = app(\App\Services\Gmail\GmailTransport::class);
    $gmail->exchangeCode('synthetic-code');
    expect($gmail->profile('synthetic-access')['emailAddress'])->toBe('pilot@example.test');
    Http::assertSent(fn ($request) => $request->url() === 'https://oauth2.googleapis.com/token'
        && $request['grant_type'] === 'authorization_code'
        && $request['redirect_uri'] === $query['redirect_uri']);
});

test('production readiness same client idempotency key is independent for each Gmail owner', function () {
    config()->set('services.gmail.enabled', true);
    Queue::fake();
    $owners = [
        User::query()->create(['name' => 'Pilot A', 'email' => 'pilot-a@example.test', 'role' => 'Admin']),
        User::query()->create(['name' => 'Pilot B', 'email' => 'pilot-b@example.test', 'role' => 'Admin']),
    ];
    $ids = [];
    foreach ($owners as $owner) {
        EmailMailboxConnection::query()->create([
            'provider' => 'gmail', 'email_address' => $owner->email, 'user_id' => $owner->id,
            'connection_status' => 'connected', 'encrypted_refresh_token' => Crypt::encryptString('synthetic-refresh'),
        ]);
        $this->actingAs($owner);
        $payload = ['to' => ['recipient@example.test'], 'subject' => 'Private send', 'body' => $owner->name];
        $first = $this->postJson('/api/email-activities/send', $payload, ['Idempotency-Key' => 'same-browser-key'])
            ->assertStatus(202)->assertJsonPath('mailbox_owner_user_id', $owner->id);
        $ids[] = $first->json('id');
        $this->postJson('/api/email-activities/send', $payload, ['Idempotency-Key' => 'same-browser-key'])
            ->assertStatus(202)->assertJsonPath('id', $first->json('id'));
    }
    expect($ids[0])->not->toBe($ids[1]);
    $this->assertDatabaseCount('email_activities', 2);
    Queue::assertPushed(\App\Jobs\SendGmailEmail::class, 2);
    $this->actingAs($owners[0]);
    $this->getJson('/api/email-activities/'.$ids[1])->assertNotFound();
    $this->getJson('/api/email-activities/'.$ids[1].'/thread')->assertNotFound();
    $this->getJson('/api/activities/email/'.$ids[1].'/attachments')->assertNotFound();
    $this->actingAs($owners[1]);
    $this->getJson('/api/email-activities/'.$ids[0])->assertNotFound();
    $rollback = require database_path('migrations/2026_10_01_180000_scope_email_activities_idempotency_to_mailbox.php');
    expect(fn () => $rollback->down())->toThrow(RuntimeException::class, 'forward-fix');
    $this->assertDatabaseCount('email_activities', 2);
});

test('production readiness Gmail incoming attachments are private and owner authorized', function () {
    config()->set('services.gmail.enabled', true);
    Storage::fake('local');
    Storage::fake('public');
    $mailbox = EmailMailboxConnection::query()->create([
        'provider' => 'gmail', 'email_address' => $this->user->email, 'user_id' => $this->user->id,
        'encrypted_refresh_token' => Crypt::encryptString('synthetic-refresh'),
    ]);
    Http::fake(function ($request) {
        $path = parse_url($request->url(), PHP_URL_PATH);
        if ($path === '/token') return Http::response(['access_token' => 'synthetic-access']);
        if (str_ends_with($path, '/attachments/private-file')) return Http::response(['data' => base64_encode('private bytes'), 'size' => 13]);
        if (str_ends_with($path, '/messages')) return Http::response(['messages' => [['id' => 'private-mail']]]);
        return Http::response(['id' => 'private-mail', 'payload' => ['headers' => [], 'parts' => [[
            'filename' => "..\\private-report\r\n.pdf", 'mimeType' => 'application/pdf',
            'body' => ['attachmentId' => 'private-file', 'size' => 13],
        ]]]]);
    });
    $gmail = app(\App\Services\Gmail\GmailTransport::class);
    $gmail->sync($mailbox);
    $gmail->sync($mailbox->fresh());
    $attachment = \App\Models\ActivityAttachment::query()->sole();
    expect($attachment->storage_disk)->toBe('local')
        ->and($attachment->original_filename)->toBe('private-report.pdf')
        ->and($attachment->mime_type)->toBe('application/pdf')
        ->and($attachment->size_bytes)->toBe(13)
        ->and(Storage::disk('public')->allFiles())->toBe([])
        ->and(Storage::disk('local')->get($attachment->storage_path))->toBe('private bytes');
    $url = '/api/email-activities/'.$attachment->activity_id.'/files/'.$attachment->id;
    $dto = app(\App\Services\ActivityAttachmentService::class)->serialize($attachment);
    expect($dto['storage_path'])->toBeNull()->and($dto['safe_url'])->toEndWith($url);
    $this->get($url)->assertOk()->assertDownload('private-report.pdf')
        ->assertHeader('Content-Type', 'application/pdf');
    $this->get('/api/email-activities/'.$attachment->activity_id.'/attachments/private-file')
        ->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $other = User::query()->create(['name' => 'Other admin', 'email' => 'other-private@example.test', 'role' => 'Admin']);
    $this->actingAs($other)->get($url)->assertNotFound();
    $this->get('/api/email-activities/'.$attachment->activity_id.'/attachments/private-file')->assertNotFound();
    $this->getJson('/api/activities/email/'.$attachment->activity_id.'/attachments')->assertNotFound();
    $this->withMiddleware()->get('/storage/'.$attachment->storage_path)->assertForbidden();
    auth()->logout();
    $this->withMiddleware()->getJson($url)->assertUnauthorized();
});

test('production readiness Gmail compose and generic email attachments use private storage only', function () {
    config()->set('services.gmail.enabled', true);
    Queue::fake();
    Storage::fake('local');
    Storage::fake('public');
    EmailMailboxConnection::query()->create([
        'provider' => 'gmail', 'email_address' => $this->user->email, 'user_id' => $this->user->id,
        'encrypted_refresh_token' => Crypt::encryptString('synthetic-refresh'),
    ]);
    $sent = $this->postJson('/api/email-activities/send', [
        'to' => ['recipient@example.test'], 'subject' => 'Private file', 'body' => 'Private',
        'attachments' => [UploadedFile::fake()->create('outgoing.txt', 1, 'text/plain')],
    ])->assertStatus(202);
    $id = $sent->json('id');
    $uploaded = $this->postJson('/api/activities/email/'.$id.'/attachments', [
        'attachment' => UploadedFile::fake()->create('additional.txt', 1, 'text/plain'),
    ])->assertCreated();
    expect(\App\Models\ActivityAttachment::query()->pluck('storage_disk')->all())->toBe(['local', 'local'])
        ->and(Storage::disk('public')->allFiles())->toBe([]);
    $this->get('/api/email-activities/'.$id.'/files/'.$uploaded->json('id'))->assertOk();
});

test('production readiness Gmail cap preserves a fixed history window then resumes incrementally', function (int $total) {
    config()->set('services.gmail.enabled', true);
    config()->set('services.gmail.initial_sync_max_messages', 2);
    $this->travelTo(\Carbon\Carbon::parse('2026-10-01 12:00:00 UTC')->setTimezone(config('app.timezone')));
    $boundary = now()->timestamp;
    $connection = EmailMailboxConnection::query()->create([
        'provider' => 'gmail', 'email_address' => $this->user->email, 'user_id' => $this->user->id,
        'encrypted_refresh_token' => Crypt::encryptString('synthetic-refresh'),
    ]);
    $queries = [];
    Http::fake(function ($request) use (&$queries, $total) {
        $path = parse_url($request->url(), PHP_URL_PATH);
        if ($path === '/token') return Http::response(['access_token' => 'synthetic-access']);
        if (str_ends_with($path, '/messages')) {
            $queries[] = $request['q'];
            $ids = count($queries) === 1 ? range(1, $total) : ['new'];
            return Http::response(['messages' => array_map(fn ($id) => ['id' => 'message-'.$id], $ids)]);
        }
        return Http::response(['id' => basename($path), 'payload' => ['headers' => []]]);
    });
    $gmail = app(\App\Services\Gmail\GmailTransport::class);
    expect($gmail->sync($connection))->toBe(min($total, 2));
    $connection->refresh();
    if ($total > 2) {
        expect($connection->initial_sync_status)->toBe('capped')
            ->and($connection->sync_status)->toBe('continuing')
            ->and($connection->last_synced_at)->toBeNull()
            ->and($connection->sync_checkpoint['pending'])->toBe(['message-3']);
        expect($connection->toArray())->not->toHaveKey('sync_checkpoint');
        $this->travel(5)->minutes();
        expect($gmail->sync($connection))->toBe(1);
    }
    $connection->refresh();
    expect($connection->initial_sync_status)->toBe('complete')
        ->and($connection->sync_checkpoint)->toBeNull()
        ->and($connection->initial_sync_count)->toBe($total)
        ->and($connection->last_synced_at->timestamp)->toBe($boundary);
    expect($queries[0])->toBe('in:inbox after:'.($boundary - 14 * 86400).' before:'.$boundary);
    $this->travel(1)->minutes();
    $gmail->sync($connection);
    expect($queries[1])->toContain('after:'.($boundary - 120));
    $gmail->sync($connection->fresh());
    $this->assertDatabaseCount('email_activities', $total + 1);
})->with(['under cap' => 1, 'exactly cap' => 2, 'over cap' => 3]);

test('production readiness Gmail cap resumes the provider page token without skipping pages', function () {
    config()->set('services.gmail.enabled', true);
    config()->set('services.gmail.initial_sync_max_messages', 1);
    $connection = EmailMailboxConnection::query()->create([
        'provider' => 'gmail', 'email_address' => $this->user->email, 'user_id' => $this->user->id,
        'encrypted_refresh_token' => Crypt::encryptString('synthetic-refresh'),
    ]);
    $queries = [];
    Http::fake(function ($request) use (&$queries) {
        $path = parse_url($request->url(), PHP_URL_PATH);
        if ($path === '/token') return Http::response(['access_token' => 'synthetic-access']);
        if (str_ends_with($path, '/messages')) {
            $queries[] = $request['q'];
            return Http::response(($request->data()['pageToken'] ?? null) === 'second-page'
                ? ['messages' => [['id' => 'second']]]
                : ['messages' => [['id' => 'first']], 'nextPageToken' => 'second-page']);
        }
        return Http::response(['id' => basename($path), 'payload' => ['headers' => []]]);
    });
    $gmail = app(\App\Services\Gmail\GmailTransport::class);
    $gmail->sync($connection);
    expect($connection->fresh()->sync_checkpoint['next'])->toBe('second-page');
    $gmail->sync($connection->fresh());
    expect($queries[1])->toBe($queries[0]);
    expect($connection->fresh()->initial_sync_status)->toBe('complete');
    $this->assertDatabaseCount('email_activities', 2);
});

test('production readiness Gmail sync retries retain unfinished IDs and never duplicate completed rows', function () {
    config()->set('services.gmail.enabled', true);
    $connection = EmailMailboxConnection::query()->create([
        'provider' => 'gmail', 'email_address' => $this->user->email, 'user_id' => $this->user->id,
        'encrypted_refresh_token' => Crypt::encryptString('synthetic-refresh'),
    ]);
    $failOnce = true;
    Http::fake(function ($request) use (&$failOnce) {
        $path = parse_url($request->url(), PHP_URL_PATH);
        if ($path === '/token') return Http::response(['access_token' => 'synthetic-access']);
        if (str_ends_with($path, '/messages')) return Http::response(['messages' => [['id' => 'one'], ['id' => 'two']]]);
        if (str_ends_with($path, '/two') && $failOnce) {
            $failOnce = false;
            return Http::response([], 503);
        }
        return Http::response(['id' => basename($path), 'payload' => ['headers' => []]]);
    });
    $gmail = app(\App\Services\Gmail\GmailTransport::class);
    expect(fn () => $gmail->sync($connection))->toThrow(\Illuminate\Http\Client\RequestException::class);
    expect($connection->fresh()->sync_checkpoint['pending'])->toBe(['two'])
        ->and($connection->fresh()->last_synced_at)->toBeNull();
    expect($gmail->sync($connection->fresh()))->toBe(1);
    $gmail->sync($connection->fresh());
    $this->assertDatabaseCount('email_activities', 2);
    expect($connection->fresh()->sync_status)->toBe('complete');
});

test('production readiness Gmail rejects a repeated page token instead of endless pagination', function () {
    config()->set('services.gmail.enabled', true);
    $connection = EmailMailboxConnection::query()->create([
        'provider' => 'gmail', 'email_address' => $this->user->email, 'user_id' => $this->user->id,
        'encrypted_refresh_token' => Crypt::encryptString('synthetic-refresh'),
    ]);
    Http::fake(fn ($request) => Http::response(str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/token')
        ? ['access_token' => 'synthetic-access'] : ['messages' => [], 'nextPageToken' => 'repeated']));
    expect(fn () => app(\App\Services\Gmail\GmailTransport::class)->sync($connection))
        ->toThrow(RuntimeException::class, 'pagination did not advance');
    expect($connection->fresh()->last_synced_at)->toBeNull()
        ->and($connection->fresh()->sync_status)->not->toBe('complete');
    Http::assertSentCount(4);
});

test('production readiness Gmail incremental cap does not advance past unfinished messages', function () {
    config()->set('services.gmail.enabled', true);
    config()->set('services.gmail.initial_sync_max_messages', 1);
    $previous = now()->subHour()->startOfSecond();
    $connection = EmailMailboxConnection::query()->create([
        'provider' => 'gmail', 'email_address' => $this->user->email, 'user_id' => $this->user->id,
        'encrypted_refresh_token' => Crypt::encryptString('synthetic-refresh'), 'last_synced_at' => $previous,
    ]);
    $connection->forceFill(['initial_sync_status' => 'complete'])->save();
    Http::fake(function ($request) {
        $path = parse_url($request->url(), PHP_URL_PATH);
        if ($path === '/token') return Http::response(['access_token' => 'synthetic-access']);
        if (str_ends_with($path, '/messages')) return Http::response(['messages' => [['id' => 'increment-one'], ['id' => 'increment-two']]]);
        return Http::response(['id' => basename($path), 'payload' => ['headers' => []]]);
    });
    $gmail = app(\App\Services\Gmail\GmailTransport::class);
    $gmail->sync($connection);
    expect($connection->fresh()->last_synced_at->timestamp)->toBe($previous->timestamp)
        ->and($connection->fresh()->sync_status)->toBe('continuing');
    $gmail->sync($connection->fresh());
    expect($connection->fresh()->sync_status)->toBe('complete')
        ->and($connection->fresh()->initial_sync_status)->toBe('complete')
        ->and($connection->fresh()->initial_sync_count)->toBe(0);
    $this->assertDatabaseCount('email_activities', 2);
});

test('production readiness Activities queue leases exceed every integration job timeout', function () {
    $jobs = [new \App\Jobs\SyncGmailMailboxes(1), new \App\Jobs\SendGmailEmail(1),
        new \App\Jobs\ProcessMessagingWebhook(1), new \App\Jobs\SendMessengerMessage(1)];
    foreach (['database', 'redis', 'beanstalkd'] as $backend) {
        foreach ($jobs as $job) {
            expect(config('queue.connections.'.$backend.'.retry_after'))->toBeGreaterThan($job->timeout);
        }
    }
    expect($jobs[0]->middleware()[0]->expiresAfter)->toBe(180)
        ->and($jobs[0]->backoff())->toBe([60, 180])
        ->and($jobs[1]->tries)->toBe(1)->and($jobs[1]->failOnTimeout)->toBeTrue()
        ->and($jobs[2]->backoff())->toBe([5, 30, 120, 600])
        ->and($jobs[3]->failOnTimeout)->toBeTrue();
    foreach (['DB_QUEUE_RETRY_AFTER', 'REDIS_QUEUE_RETRY_AFTER', 'BEANSTALKD_QUEUE_RETRY_AFTER'] as $key) {
        $original = getenv($key);
        putenv($key.'=30');
        try {
            $configured = require config_path('queue.php');
            foreach (['database', 'redis', 'beanstalkd'] as $backend) {
                expect($configured['connections'][$backend]['retry_after'])->toBeGreaterThanOrEqual(180);
            }
        } finally {
            putenv($original === false ? $key : $key.'='.$original);
        }
    }
});

test('production readiness All Activities Blade compiles without changing Tasks views', function () {
    $source = file_get_contents(resource_path('views/activities/index.blade.php'));
    $compiled = \Illuminate\Support\Facades\Blade::compileString($source);
    expect($compiled)->not->toBeEmpty()
        ->and($source)->toContain('Initial history incomplete; continuing next sync');
    $manifest = fopen(base_path('docs/deployment/activities-deployment-manifest.csv'), 'r');
    $columns = fgetcsv($manifest, escape: '');
    $views = [];
    while ($values = fgetcsv($manifest, escape: '')) {
        $row = array_combine($columns, $values);
        if (str_ends_with($row['path'], '.blade.php')) $views[] = $row['path'];
    }
    fclose($manifest);
    foreach ($views as $path) {
        // Parse compiled PHP without rendering templates or writing runtime caches.
        $php = \Illuminate\Support\Facades\Blade::compileString(file_get_contents(base_path($path)));
        token_get_all($php, TOKEN_PARSE);
    }
    expect($views)->not->toBeEmpty();
});

test('connected gmail mailbox requires an encrypted refresh token', function () {
    expect(fn () => EmailMailboxConnection::query()->create([
        'provider' => 'gmail', 'email_address' => 'invalid@example.test', 'user_id' => $this->user->id,
        'connection_status' => 'connected',
    ]))->toThrow(\InvalidArgumentException::class);
});
test('multi-client v3 official business report rows follow the master status without changing other activities', function () {
    $department = \App\Models\Department::query()->create(['department_name' => 'Client Service']);
    $employee = Employee::query()->create([
        'user_id' => $this->user->id,
        'department_id' => $department->id,
        'first_name' => 'Activities',
        'last_name' => 'Admin',
        'work_email' => $this->user->email,
    ]);
    $clientA = \App\Models\Company::query()->create(['company_name' => 'Ticket Client A']);
    $clientB = \App\Models\Company::query()->create(['company_name' => 'Ticket Client B']);
    $trip = OfficialBusinessTrip::query()->create([
        'ob_reference_no' => 'OBF-REPORT-MULTI',
        'employee_id' => $employee->id,
        'employee_name' => 'Activities Admin',
        'destination' => 'Client sites',
        'purpose' => 'Multi-client filing',
        'date_from' => '2026-08-24',
        'company_id' => $clientA->id,
        'is_client_travel' => true,
        'workflow_version' => 3,
        'status' => 'Submitted',
        'created_by' => $this->user->id,
        'prepared_by_user_id' => $this->user->id,
        'include_in_client_report' => true,
    ]);
    $ticketA = OfficialBusinessTripTicket::query()->create([
        'official_business_trip_id' => $trip->id,
        'company_id' => $clientA->id,
        'created_by_user_id' => $this->user->id,
    ]);
    $ticketB = OfficialBusinessTripTicket::query()->create([
        'official_business_trip_id' => $trip->id,
        'company_id' => $clientB->id,
        'created_by_user_id' => $this->user->id,
    ]);
    Task::query()->create([
        'name' => 'Unrelated task',
        'status' => 'Open',
        'prepared_by_user_id' => $this->user->id,
    ]);

    $report = $this->postJson('/api/activities/report')->assertOk();
    $tripRows = collect($report->json('rows'))->where('source_activity_type', 'OFFICIAL_BUSINESS_TRAVEL');
    expect($tripRows)->toHaveCount(2)
        ->and($tripRows->pluck('id')->sort()->values()->all())->toBe(collect([
            'official-business-trip-ticket-'.$ticketA->id,
            'official-business-trip-ticket-'.$ticketB->id,
        ])->sort()->values()->all())
        ->and($tripRows->pluck('source_activity_parent_id')->unique()->values()->all())->toBe([$trip->id])
        ->and($tripRows->pluck('status')->unique()->values()->all())->toBe(['Submitted'])
        ->and(collect($report->json('rows'))->firstWhere('source_activity_type', 'TASK')['status'])->toBe('Open');

    $trip->update(['status' => 'Approved']);
    $approvedRows = collect($this->postJson('/api/activities/report')->assertOk()->json('rows'))
        ->where('source_activity_type', 'OFFICIAL_BUSINESS_TRAVEL');
    expect($approvedRows)->toHaveCount(2)
        ->and($approvedRows->pluck('status')->unique()->values()->all())->toBe(['Approved']);

    $this->postJson('/api/activities/report', [
        'company_id' => $clientA->id,
        'activity_types' => ['OFFICIAL_BUSINESS_TRAVEL'],
    ])->assertOk()
        ->assertJsonCount(1, 'rows')
        ->assertJsonPath('rows.0.id', 'official-business-trip-ticket-'.$ticketA->id)
        ->assertJsonPath('rows.0.status', 'Approved')
        ->assertJsonPath('rows.0.company_id', $clientA->id)
        ->assertJsonPath('rows.0.company', 'Ticket Client A');

    $this->postJson('/api/activities/report', [
        'company_id' => $clientB->id,
        'activity_types' => ['OFFICIAL_BUSINESS_TRAVEL'],
    ])->assertOk()
        ->assertJsonCount(1, 'rows')
        ->assertJsonPath('rows.0.id', 'official-business-trip-ticket-'.$ticketB->id)
        ->assertJsonPath('rows.0.status', 'Approved')
        ->assertJsonPath('rows.0.company_id', $clientB->id)
        ->assertJsonPath('rows.0.company', 'Ticket Client B');

    $this->postJson('/api/activities/report', [
        'ob_master_ids' => [$trip->id],
        'ob_client' => 'Ticket Client A',
        'ob_reference' => 'OBF-REPORT-MULTI',
        'ob_employee' => 'Activities Admin',
        'ob_ticket_reference' => $ticketA->reference_no,
    ])->assertOk()
        ->assertJsonCount(1, 'rows')
        ->assertJsonPath('rows.0.id', 'official-business-trip-ticket-'.$ticketA->id);
    $this->postJson('/api/activities/report', [
        'ob_master_ids' => [],
    ])->assertOk()->assertJsonCount(0, 'rows');
    $this->postJson('/api/activities/report', [
        'selected_ids' => ['official-business-trip-ticket-'.$ticketB->id],
        'ob_master_ids' => [],
    ])->assertOk()->assertJsonPath('rows.0.id', 'official-business-trip-ticket-'.$ticketB->id);

    $nonFinance = User::query()->create([
        'name' => 'Non Finance Reporter',
        'email' => 'non-finance-ob-report@example.test',
        'role' => 'Employee',
    ]);
    $this->actingAs($nonFinance)->postJson('/api/activities/report', [
        'ob_credit_usage' => 'with',
    ])->assertForbidden();

    expect(app(ActivityTimelineBuilder::class)->forCompany($clientB, $this->user))
        ->toHaveCount(1);
});
test('report supports documented previous period presets and per type duration breakdown', function () {
    \Carbon\Carbon::setTestNow('2026-08-24 12:00:00');
    Task::query()->create([
        'name' => 'Yesterday work',
        'start_date' => '2026-08-23 10:00:00',
        'actual_duration_minutes' => 60,
        'prepared_by_user_id' => $this->user->id,
    ]);
    Event::query()->create([
        'title' => 'Today work',
        'from' => '2026-08-24 10:00:00',
        'duration_minutes' => 30,
        'prepared_by_user_id' => $this->user->id,
    ]);

    $this->postJson('/api/activities/report', ['date_preset' => 'yesterday'])
        ->assertOk()
        ->assertJsonCount(1, 'rows')
        ->assertJsonPath('rows.0.title', 'Yesterday work')
        ->assertJsonPath('duration_coverage.total_duration_hours', 1)
        ->assertJsonPath('duration_coverage.by_activity_type.0.activity_type', 'TASK');

    \Carbon\Carbon::setTestNow();
});
test('company client report routes retain activities permission middleware', function () {
    foreach ([
        'company/{company}/report',
        'company/{company}/report/pdf',
    ] as $uri) {
        $route = collect(Route::getRoutes())
            ->first(fn ($route) => $route->uri() === $uri);

        expect($route)->not->toBeNull();

        expect($route->gatherMiddleware())
            ->toContain('human-capital.module:access_activities');
    }
});

test('company client report selected rows override optional filters while staying company scoped', function () {
    $companyId = DB::table('companies')->insertGetId([
        'company_name' => 'Client Alpha',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $otherCompanyId = DB::table('companies')->insertGetId([
        'company_name' => 'Client Beta',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $selectedTask = Task::query()->create([
        'name' => 'Selected Alpha Task',
        'company_id' => $companyId,
        'status' => 'Open',
        'start_date' => '2026-09-04 09:00:00',
        'actual_duration_minutes' => 45,
        'prepared_by_user_id' => $this->user->id,
    ]);

    Task::query()->create([
        'name' => 'Other Company Task',
        'company_id' => $otherCompanyId,
        'status' => 'Open',
        'start_date' => '2026-09-04 10:00:00',
        'actual_duration_minutes' => 30,
        'prepared_by_user_id' => $this->user->id,
    ]);

    $this->get(route('company.report.pdf', [
        'company' => $companyId,
        'selected_ids' => ['task-'.$selectedTask->id],
        'status' => 'Completed',
        'date_from' => '2026-01-01',
        'date_to' => '2026-01-31',
    ]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $metadata = json_decode(
        DB::table('activity_audit_logs')
            ->where('action', 'export_generated')
            ->latest('id')
            ->value('metadata'),
        true
    );

    expect($metadata['selection_mode'])->toBe('selected')
        ->and($metadata['row_count'])->toBe(1)
        ->and($metadata['parameters']['company_id'])->toBe($companyId);
});

test('company client report invalid and cross company selections never broaden scope', function () {
    $companyId = DB::table('companies')->insertGetId([
        'company_name' => 'Client Alpha',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $otherCompanyId = DB::table('companies')->insertGetId([
        'company_name' => 'Client Beta',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Task::query()->create([
        'name' => 'Visible Alpha Task',
        'company_id' => $companyId,
        'status' => 'Open',
        'start_date' => '2026-09-04 09:00:00',
        'prepared_by_user_id' => $this->user->id,
    ]);

    $crossCompanyTask = Task::query()->create([
        'name' => 'Cross Company Task',
        'company_id' => $otherCompanyId,
        'status' => 'Open',
        'start_date' => '2026-09-04 10:00:00',
        'prepared_by_user_id' => $this->user->id,
    ]);

    foreach ([
        ['0'],
        ['task-999999'],
        ['task-'.$crossCompanyTask->id],
    ] as $selection) {
        $this->get(route('company.report.pdf', [
            'company' => $companyId,
            'selected_ids' => $selection,
        ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $metadata = json_decode(
            DB::table('activity_audit_logs')
                ->where('action', 'export_generated')
                ->latest('id')
                ->value('metadata'),
            true
        );

        expect($metadata['selection_mode'])->toBe('selected')
            ->and($metadata['row_count'])->toBe(0)
            ->and($metadata['parameters']['company_id'])->toBe($companyId);
    }
});

test('company client report mixed selections retain only authorized current company rows', function () {
    $companyId = DB::table('companies')->insertGetId([
        'company_name' => 'Client Alpha',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $otherCompanyId = DB::table('companies')->insertGetId([
        'company_name' => 'Client Beta',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $validTask = Task::query()->create([
        'name' => 'Valid Alpha Task',
        'company_id' => $companyId,
        'status' => 'Open',
        'start_date' => '2026-09-04 09:00:00',
        'actual_duration_minutes' => 60,
        'prepared_by_user_id' => $this->user->id,
    ]);

    $crossCompanyTask = Task::query()->create([
        'name' => 'Cross Company Task',
        'company_id' => $otherCompanyId,
        'status' => 'Open',
        'start_date' => '2026-09-04 10:00:00',
        'actual_duration_minutes' => 30,
        'prepared_by_user_id' => $this->user->id,
    ]);

    $this->get(route('company.report.pdf', [
        'company' => $companyId,
        'selected_ids' => [
            'task-'.$validTask->id,
            'task-'.$crossCompanyTask->id,
            'task-999999',
        ],
    ]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $metadata = json_decode(
        DB::table('activity_audit_logs')
            ->where('action', 'export_generated')
            ->latest('id')
            ->value('metadata'),
        true
    );

    expect($metadata['selection_mode'])->toBe('selected')
        ->and($metadata['row_count'])->toBe(1)
        ->and($metadata['parameters']['company_id'])->toBe($companyId);
});

test('company client filtered pdf records export audit metadata', function () {
    $companyId = DB::table('companies')->insertGetId([
        'company_name' => 'Client Alpha',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Task::query()->create([
        'name' => 'Filtered Task',
        'company_id' => $companyId,
        'status' => 'Open',
        'start_date' => '2026-09-04 09:00:00',
        'actual_duration_minutes' => 30,
        'prepared_by_user_id' => $this->user->id,
    ]);

    $this->get(route('company.report.pdf', [
        'company' => $companyId,
        'date_from' => '2026-09-01',
        'date_to' => '2026-09-30',
        'activity_types' => ['TASK'],
        'status' => 'Open',
    ]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $metadata = json_decode(
        DB::table('activity_audit_logs')
            ->where('action', 'export_generated')
            ->latest('id')
            ->value('metadata'),
        true
    );

    expect($metadata['selection_mode'])->toBe('filtered')
        ->and($metadata['row_count'])->toBe(1)
        ->and($metadata['parameters']['company_id'])->toBe($companyId)
        ->and($metadata['parameters']['activity_types'])->toBe(['TASK'])
        ->and($metadata['parameters']['status'])->toBe('Open')
        ->and($metadata['parameters']['format'])->toBe('pdf')
        ->and($metadata['parameters']['company_report'])->toBeTrue();
});
