<?php

use App\Models\Call;
use App\Models\Contact;
use App\Models\Task;
use App\Models\User;
use App\Models\Visit;
use App\Models\Visitor;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->withoutMiddleware();

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password')->nullable();
        $table->string('role')->nullable();
        $table->boolean('must_change_password')->default(false);
        $table->timestamps();
    });

    Schema::create('user_permissions', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->boolean('access_activities')->default(false);
        $table->timestamps();
    });

    Schema::create('contacts', function (Blueprint $table) {
        $table->id();
        $table->string('first_name')->nullable();
        $table->string('middle_initial')->nullable();
        $table->string('last_name')->nullable();
        $table->string('name_extension')->nullable();
        $table->string('company_name')->nullable();
        $table->string('email')->nullable();
        $table->string('phone')->nullable();
        $table->timestamps();
    });

    Schema::create('branches', function (Blueprint $table) {
        $table->id();
        $table->string('branch_name');
        $table->unsignedBigInteger('address_id')->nullable();
        $table->string('branch_head')->nullable();
        $table->timestamps();
    });

    Schema::create('tasks', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('events', function (Blueprint $table) {
        $table->id();
        $table->string('title')->nullable();
        $table->timestamps();
    });

    Schema::create('calls', function (Blueprint $table) {
        $table->id();
        $table->string('contact')->nullable();
        $table->string('purpose')->nullable();
        $table->string('audio_path')->nullable();
        $table->timestamps();
    });

    Schema::create('meetings', function (Blueprint $table) {
        $table->id();
        $table->string('title')->nullable();
        $table->string('video_path')->nullable();
        $table->string('audio_path')->nullable();
        $table->timestamps();
    });

    Schema::create('visitors', function (Blueprint $table) {
        $table->id();
        $table->string('visitor_reference')->unique();
        $table->string('name');
        $table->string('mobile')->nullable();
        $table->string('email')->nullable();
        $table->string('company_name')->nullable();
        $table->unsignedBigInteger('contact_id')->nullable();
        $table->timestamps();
    });

    Schema::create('visits', function (Blueprint $table) {
        $table->id();
        $table->string('visit_reference')->unique();
        $table->unsignedBigInteger('visitor_id');
        $table->string('status');
        $table->timestamp('checked_in_at');
        $table->timestamp('checked_out_at')->nullable();
        $table->string('activity_type')->nullable();
        $table->unsignedBigInteger('activity_id')->nullable();
        $table->timestamps();
    });

    Schema::create('visit_movements', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('visit_id');
        $table->string('movement_type');
        $table->timestamp('occurred_at');
        $table->unsignedBigInteger('branch_id')->nullable();
        $table->decimal('latitude', 10, 7)->nullable();
        $table->decimal('longitude', 11, 7)->nullable();
        $table->decimal('accuracy_metres', 10, 2)->nullable();
        $table->timestamp('captured_at')->nullable();
        $table->string('source')->nullable();
        $table->string('location_label')->nullable();
        $table->timestamps();
        $table->unique(['visit_id', 'movement_type']);
    });
});

test('visitor log endpoints require authentication and retain the Activities permission middleware', function () {
    $this->withMiddleware()
        ->getJson('/api/visitors')
        ->assertUnauthorized();

    foreach (['api/visitors', 'api/visits', 'api/visits/check-in'] as $uri) {
        $route = collect(Route::getRoutes())->first(fn ($route) => $route->uri() === $uri);

        expect($route)->not->toBeNull()
            ->and($route->gatherMiddleware())->toContain('auth')
            ->and($route->gatherMiddleware())->toContain('human-capital.module:access_activities');
    }

    $employee = User::query()->create([
        'name' => 'No Activities Access',
        'email' => 'no-activities@example.test',
        'role' => 'Employee',
    ]);
    Schema::getConnection()->table('user_permissions')->insert([
        'user_id' => $employee->id,
        'access_activities' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($employee)
        ->withMiddleware()
        ->getJson('/api/visitors')
        ->assertForbidden();
});

test('first-time visitors receive generated IDs, may link a Contact, and can be searched by every approved identity field', function () {
    $contact = Contact::query()->create([
        'first_name' => 'Ana',
        'last_name' => 'Reyes',
        'company_name' => 'Acme Corp',
        'email' => 'ana@acme.test',
        'phone' => '09171234567',
    ]);

    $created = $this->postJson('/api/visitors', [
        'name' => 'Ana Reyes',
        'mobile' => '09171234567',
        'email' => 'visitor@acme.test',
        'company_name' => 'Acme Corp',
        'contact_id' => $contact->id,
    ])->assertCreated()
        ->assertJsonPath('contact.id', $contact->id)
        ->assertJsonPath('company', 'Acme Corp');

    expect($created->json('visitor_reference'))->toMatch('/^VIS-\d{4}-\d{6}$/');

    foreach ([$created->json('visitor_reference'), 'Ana Reyes', '09171234567', 'visitor@acme.test'] as $term) {
        $this->getJson('/api/visitors?search='.urlencode($term))
            ->assertOk()
            ->assertJsonCount(1, 'visitors')
            ->assertJsonPath('visitors.0.id', $created->json('id'));
    }

    $this->postJson('/api/visitors', ['name' => 'Bad Email', 'email' => 'not-an-email'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

test('check-in enforces one active visit and permits completely nullable GPS', function () {
    $visitor = Visitor::query()->create(['name' => 'No GPS Visitor']);

    $created = $this->postJson('/api/visits/check-in', ['visitor_id' => $visitor->id])
        ->assertCreated()
        ->assertJsonPath('status', Visit::STATUS_CHECKED_IN)
        ->assertJsonPath('checked_out_at', null)
        ->assertJsonPath('check_in_movement.latitude', null)
        ->assertJsonPath('check_out_movement', null);

    expect($created->json('visit_reference'))->toMatch('/^VST-\d{8}-\d{4}$/');

    $this->postJson('/api/visits/check-in', ['visitor_id' => $visitor->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('visitor_id');

    $this->assertDatabaseCount('visits', 1);
    $this->assertDatabaseCount('visit_movements', 1);
});

test('checkout completes an active visit and stores its GPS independently from check-in', function () {
    $branchId = Schema::getConnection()->table('branches')->insertGetId([
        'branch_name' => 'Cebu Office',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $visitor = Visitor::query()->create(['name' => 'Moving Visitor']);

    $visitId = $this->postJson('/api/visits/check-in', [
        'visitor_id' => $visitor->id,
        'branch_id' => $branchId,
        'latitude' => 10.3157,
        'longitude' => 123.8854,
        'accuracy_metres' => 12.5,
        'captured_at' => '2026-08-19T09:00:00+08:00',
        'source' => 'browser',
        'location_label' => 'Cebu Lobby',
    ])->assertCreated()->json('id');

    $this->postJson("/api/visits/{$visitId}/check-out", [
        'latitude' => 10.3000,
        'longitude' => 123.9000,
        'accuracy_metres' => 25,
        'source' => 'browser',
        'location_label' => 'Client Site',
    ])->assertOk()
        ->assertJsonPath('status', Visit::STATUS_COMPLETED)
        ->assertJsonPath('check_in_movement.branch.id', $branchId)
        ->assertJsonPath('check_in_movement.location_label', 'Cebu Lobby')
        ->assertJsonPath('check_out_movement.location_label', 'Client Site')
        ->assertJsonPath('check_out_movement.branch', null);

    $this->assertDatabaseHas('visit_movements', [
        'visit_id' => $visitId,
        'movement_type' => 'check_in',
        'location_label' => 'Cebu Lobby',
    ]);
    $this->assertDatabaseHas('visit_movements', [
        'visit_id' => $visitId,
        'movement_type' => 'check_out',
        'location_label' => 'Client Site',
    ]);
});

test('checkout rejects a visit that is not actively checked in and blocks status injection', function () {
    $visitor = Visitor::query()->create(['name' => 'Completed Visitor']);
    $visit = Visit::query()->create([
        'visitor_id' => $visitor->id,
        'status' => Visit::STATUS_COMPLETED,
        'checked_in_at' => now()->subHour(),
        'checked_out_at' => now(),
    ]);

    $this->postJson("/api/visits/{$visit->id}/check-out")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('visit');

    $this->postJson('/api/visits/check-in', [
        'visitor_id' => $visitor->id,
        'status' => Visit::STATUS_COMPLETED,
    ])->assertUnprocessable()->assertJsonValidationErrors('status');
});

test('an optional supported Activity is validated, serialized, and never copied into another activity table', function () {
    $visitor = Visitor::query()->create(['name' => 'Linked Visitor']);
    $task = Task::query()->create(['name' => 'Client Consultation']);

    $this->postJson('/api/visits/check-in', [
        'visitor_id' => $visitor->id,
        'activity_type' => 'Task',
        'activity_id' => $task->id,
    ])->assertCreated()
        ->assertJsonPath('activity.type', 'Task')
        ->assertJsonPath('activity.id', $task->id)
        ->assertJsonPath('activity.label', 'Client Consultation');

    $this->assertDatabaseCount('tasks', 1);
    $this->assertDatabaseCount('events', 0);
    $this->assertDatabaseCount('calls', 0);
    $this->assertDatabaseCount('meetings', 0);

    $otherVisitor = Visitor::query()->create(['name' => 'Invalid Link Visitor']);
    $this->postJson('/api/visits/check-in', [
        'visitor_id' => $otherVisitor->id,
        'activity_type' => 'Email',
        'activity_id' => 1,
    ])->assertUnprocessable()->assertJsonValidationErrors('activity_type');

    $this->postJson('/api/visits/check-in', [
        'visitor_id' => $otherVisitor->id,
        'activity_type' => 'Call',
        'activity_id' => 999,
    ])->assertUnprocessable()->assertJsonValidationErrors('activity_id');
});

test('visit listing supports lifecycle, Pending Link display, and visitor search', function () {
    $visitor = Visitor::query()->create([
        'name' => 'Searchable Visitor',
        'company_name' => 'Search Company',
    ]);

    $visitId = $this->postJson('/api/visits/check-in', ['visitor_id' => $visitor->id])
        ->assertCreated()
        ->assertJsonPath('display_status', 'Pending Link')
        ->json('id');

    $this->getJson('/api/visits?status=Pending%20Link&search=Searchable')
        ->assertOk()
        ->assertJsonCount(1, 'visits')
        ->assertJsonPath('visits.0.id', $visitId)
        ->assertJsonCount(1, 'current_visits');

    $this->postJson("/api/visits/{$visitId}/check-out")->assertOk();

    $this->getJson('/api/visits?status=Completed')
        ->assertOk()
        ->assertJsonCount(1, 'visits')
        ->assertJsonCount(0, 'current_visits');
});
