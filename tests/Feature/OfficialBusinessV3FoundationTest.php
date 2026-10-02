<?php

use App\Models\Company;
use App\Models\OfficialBusinessTrip;
use App\Models\OfficialBusinessTripTicket;
use App\Models\User;
use App\Services\OfficialBusinessTripTicketService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

function obV3Migration(): Illuminate\Database\Migrations\Migration
{
    return require database_path('migrations/2026_09_16_160000_add_official_business_v3_domain_foundation.php');
}

beforeEach(function () {
    // Never run these migration/rollback checks against a persisted database.
    if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
        throw new RuntimeException('O.B. foundation tests require SQLite :memory:.');
    }

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email');
        $table->string('role');
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
    Schema::create('employees', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->nullable();
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

    // Use the actual master migrations, not a substitute V3 ticket table.
    (require database_path('migrations/2026_05_11_160306_create_official_business_trips_table.php'))->up();
    (require database_path('migrations/2026_08_26_112720_add_actual_travel_timestamps_to_official_business_trips_table.php'))->up();
    (require database_path('migrations/2026_08_26_130000_add_soft_deletes_to_official_business_trips_table.php'))->up();

    $this->owner = User::create(['name' => 'Owner', 'email' => 'owner@example.test', 'role' => 'Employee']);
    DB::table('employees')->insert(['id' => 1, 'user_id' => $this->owner->id]);
    $this->client = Company::create(['company_name' => 'Client A']);
    $this->master = OfficialBusinessTrip::create([
        'ob_reference_no' => 'OBF-LEGACY-UNCHANGED',
        'employee_id' => 1,
        'employee_name' => 'Owner',
        'destination' => 'Legacy destination',
        'purpose' => 'Legacy purpose',
        'date_from' => '2026-08-20',
        'is_client_travel' => true,
        'created_by' => $this->owner->id,
        'status' => 'Completed',
        'team_members' => ['Legacy participant'],
        'travel_credit_details' => ['used_this_month' => 2],
        'attachment_paths' => ['legacy/private-evidence.pdf'],
    ]);
    $this->legacyBefore = (array) DB::table('official_business_trips')->find($this->master->id);
    obV3Migration()->up();
    (require database_path('migrations/2026_09_17_110100_add_official_business_v3_ticket_details.php'))->up();
    $this->service = app(OfficialBusinessTripTicketService::class);
});

test('foundation migration preserves legacy values and does not backfill tickets', function () {
    $after = (array) DB::table('official_business_trips')->find($this->master->id);
    expect($after['workflow_version'])->toBe(1);
    unset($after['workflow_version']);
    expect($after)->toBe($this->legacyBefore)
        ->and(OfficialBusinessTripTicket::count())->toBe(0);
});

test('one master supports distinct client tickets and repeated requests keep stable identity', function () {
    $other = Company::create(['company_name' => 'Client B']);
    $a = $this->service->forClient($this->owner, $this->master, $this->client);
    $b = $this->service->forClient($this->owner, $this->master, $other);
    $again = $this->service->forClient($this->owner, $this->master, $this->client);

    expect($again->id)->toBe($a->id)
        ->and($again->reference_no)->toBe($a->reference_no)
        ->and($a->reference_no)->toStartWith('OBTT-'.$this->master->id.'-')
        ->and($a->reference_no)->not->toBe($b->reference_no)
        ->and($this->master->tripTickets()->count())->toBe(2)
        ->and($this->client->officialBusinessTripTickets()->sole()->id)->toBe($a->id)
        ->and($a->officialBusinessTrip->id)->toBe($this->master->id)
        ->and($a->company->id)->toBe($this->client->id)
        ->and($a->createdBy->id)->toBe($this->owner->id)
        ->and($a->status)->toBe('Draft')
        ->and($this->master->fresh()->status)->toBe('Completed')
        ->and($this->master->fresh()->workflow_version)->toBe(1);

    $after = (array) DB::table('official_business_trips')->find($this->master->id);
    unset($after['workflow_version']);
    expect($after)->toBe($this->legacyBefore)
        ->and(array_intersect(['attachment_paths', 'team_members', 'travel_credit_details'], array_keys($a->getAttributes())))->toBe([]);
});

test('v3 completed master rejects new trip ticket creation', function () {
    $master = $this->master->replicate();
    $master->ob_reference_no = 'OBV3-COMPLETED-CREATE';
    $master->workflow_version = 3;
    $master->status = 'Completed';
    $master->is_client_travel = true;
    $master->save();

    expect(fn () => $this->service->forClient(
        $this->owner,
        $master,
        $this->client
    ))->toThrow(\Illuminate\Validation\ValidationException::class);

    expect(
        OfficialBusinessTripTicket::query()
            ->where('official_business_trip_id', $master->id)
            ->count()
    )->toBe(0);
});

test('v3 completed master rejects direct trip ticket creation', function () {
    $master = $this->master->replicate();
    $master->ob_reference_no = 'OBV3-COMPLETED-DIRECT';
    $master->workflow_version = 3;
    $master->status = 'Completed';
    $master->is_client_travel = true;
    $master->save();

    expect(fn () => $master->tripTickets()->create([
        'company_id' => $this->client->id,
        'created_by_user_id' => $this->owner->id,
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);

    expect(
        OfficialBusinessTripTicket::query()
            ->where('official_business_trip_id', $master->id)
            ->count()
    )->toBe(0);
});

test('archived v3 ticket cannot be restored after master completion', function () {
    $master = $this->master->replicate();
    $master->ob_reference_no = 'OBV3-COMPLETED-RESTORE';
    $master->workflow_version = 3;
    $master->status = 'Draft';
    $master->is_client_travel = true;
    $master->save();

    $ticket = $this->service->forClient(
        $this->owner,
        $master,
        $this->client
    );

    $ticket->delete();

    expect(
        OfficialBusinessTripTicket::withTrashed()
            ->findOrFail($ticket->id)
            ->trashed()
    )->toBeTrue();

    $master->forceFill([
        'status' => 'Completed',
    ])->save();

    $archived = OfficialBusinessTripTicket::withTrashed()
        ->findOrFail($ticket->id);

    expect(fn () => $archived->restore())
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    expect(
        OfficialBusinessTripTicket::withTrashed()
            ->findOrFail($ticket->id)
            ->trashed()
    )->toBeTrue();
});
test('v3 for completion master still allows trip ticket creation', function () {
    $master = $this->master->replicate();
    $master->ob_reference_no = 'OBV3-FOR-COMPLETION-CREATE';
    $master->workflow_version = 3;
    $master->status = 'For Completion';
    $master->is_client_travel = true;
    $master->save();

    $ticket = $this->service->forClient(
        $this->owner,
        $master,
        $this->client
    );

    expect($ticket->exists)->toBeTrue()
        ->and($ticket->official_business_trip_id)->toBe($master->id)
        ->and($ticket->status)->toBe('Draft');
});

test('v3 cancelled and rejected masters reject new trip ticket creation', function () {
    foreach (['Cancelled', 'Rejected'] as $status) {
        $master = $this->master->replicate();
        $master->ob_reference_no = 'OBV3-TERMINAL-'.strtoupper(str_replace(' ', '-', $status));
        $master->workflow_version = 3;
        $master->status = $status;
        $master->is_client_travel = true;
        $master->save();

        expect(fn () => $this->service->forClient(
            $this->owner,
            $master,
            $this->client
        ))->toThrow(\Illuminate\Validation\ValidationException::class);

        expect(
            OfficialBusinessTripTicket::query()
                ->where('official_business_trip_id', $master->id)
                ->count()
        )->toBe(0);
    }
});
test('database rejects duplicate master client pairs including archived tickets', function () {
    $ticket = $this->service->forClient($this->owner, $this->master, $this->client);
    $ticket->delete();
    expect(fn () => DB::table('official_business_trip_tickets')->insert([
        'official_business_trip_id' => $this->master->id,
        'company_id' => $this->client->id,
        'reference_no' => 'DIFFERENT-REFERENCE',
        'client_name_snapshot' => 'Client A',
    ]))->toThrow(QueryException::class);
});

test('database rejects reused references across clients', function () {
    $ticket = $this->service->forClient($this->owner, $this->master, $this->client);
    $other = Company::create(['company_name' => 'Client B']);
    expect(fn () => DB::table('official_business_trip_tickets')->insert([
        'official_business_trip_id' => $this->master->id,
        'company_id' => $other->id,
        'reference_no' => $ticket->reference_no,
        'client_name_snapshot' => 'Client B',
    ]))->toThrow(QueryException::class);
});

test('the same client can have tickets under different masters', function () {
    $otherMaster = $this->master->replicate();
    $otherMaster->ob_reference_no = 'OTHER-MASTER';
    $otherMaster->save();
    $first = $this->service->forClient($this->owner, $this->master, $this->client);
    $second = $this->service->forClient($this->owner, $otherMaster, $this->client);
    expect($first->reference_no)->not->toBe($second->reference_no)
        ->and($this->client->officialBusinessTripTickets()->count())->toBe(2);
});

test('internal masters reject tickets through both the service and model', function () {
    $this->master->update(['is_client_travel' => false]);
    expect(fn () => $this->service->forClient($this->owner, $this->master, $this->client))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $this->master->tripTickets()->create(['company_id' => $this->client->id]))
        ->toThrow(ValidationException::class);
    expect(OfficialBusinessTripTicket::count())->toBe(0);
});

test('active tickets block direct internal conversion while archived tickets do not', function () {
    $ticket = $this->service->forClient(
        $this->owner,
        $this->master,
        $this->client
    );

    expect(fn () => $this->master->update([
        'is_client_travel' => false,
    ]))->toThrow(ValidationException::class);

    expect($this->master->fresh()->is_client_travel)->toBeTrue();

    $ticket->delete();

    $this->master->update([
        'is_client_travel' => false,
    ]);

    $archivedTicket = OfficialBusinessTripTicket::withTrashed()
        ->findOrFail($ticket->id);

    expect($this->master->fresh()->is_client_travel)->toBeFalse()
        ->and($archivedTicket->trashed())->toBeTrue();
});

test('ticket identities are immutable and company renames preserve their snapshot', function () {
    $ticket = $this->service->forClient($this->owner, $this->master, $this->client);
    $this->client->update(['company_name' => 'Renamed Client']);
    expect($ticket->fresh()->client_name_snapshot)->toBe('Client A');
    foreach (['official_business_trip_id' => 999, 'company_id' => 999, 'reference_no' => 'REASSIGNED', 'client_name_snapshot' => 'REASSIGNED'] as $field => $value) {
        expect(fn () => $ticket->fresh()->forceFill([$field => $value])->save())->toThrow(ValidationException::class);
    }
});

test('database prevents orphan tickets and destructive parent or client deletion', function () {
    $ticket = $this->service->forClient($this->owner, $this->master, $this->client);
    expect(fn () => DB::table('official_business_trips')->where('id', $this->master->id)->delete())->toThrow(QueryException::class);
    expect(fn () => DB::table('companies')->where('id', $this->client->id)->delete())->toThrow(QueryException::class);
    expect(fn () => DB::table('official_business_trip_tickets')->where('id', $ticket->id)->update(['company_id' => 999]))->toThrow(QueryException::class);
    expect(fn () => DB::table('official_business_trip_tickets')->where('id', $ticket->id)->update(['official_business_trip_id' => 999]))->toThrow(QueryException::class);
});

test('policy discovery grants active employee read access while creation remains authorized', function () {
    $ticket = $this->service->forClient($this->owner, $this->master, $this->client);
    $stranger = User::create(['name' => 'Legacy participant', 'email' => 'stranger@example.test', 'role' => 'Employee']);

    expect(Gate::forUser($this->owner)->allows('view', $ticket))->toBeTrue()
        ->and(Gate::forUser($stranger)->allows('view', $ticket))->toBeTrue();

    expect(fn () => $this->service->forClient($stranger, $this->master, $this->client))
        ->toThrow(AuthorizationException::class);

    $linkedUser = User::create([
        'name' => 'Linked Employee',
        'email' => 'linked@example.test',
        'role' => 'Employee',
    ]);

    DB::table('employees')->insert([
        'id' => 2,
        'user_id' => $linkedUser->id,
    ]);

    DB::table('official_business_trip_ticket_personnel')->insert([
        'official_business_trip_ticket_id' => $ticket->id,
        'employee_id' => 2,
        'employee_name_snapshot' => 'Linked Employee',
        'role' => 'Traveler',
        'participation_status' => 'Assigned',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->master->update([
        'created_by' => null,
    ]);

    expect(Gate::forUser($linkedUser)->allows('view', $ticket->fresh()))->toBeTrue()
        ->and(Gate::forUser($stranger)->allows('view', $ticket->fresh()))->toBeTrue();
});

test('managers can create tickets while client and disabled accounts cannot', function () {
    $manager = User::create(['name' => 'Manager', 'email' => 'manager@example.test', 'role' => 'Employee']);
    DB::table('user_permissions')->insert(['user_id' => $manager->id, 'manage_hc_obf' => true]);
    $ticket = $this->service->forClient($manager, $this->master, $this->client);
    expect(Gate::forUser($manager)->allows('view', $ticket))->toBeTrue();

    foreach ([['role' => 'Client'], ['role' => 'Admin', 'is_active' => false]] as $attributes) {
        $user = User::create($attributes + ['name' => 'Blocked', 'email' => uniqid().'@example.test']);
        DB::table('user_permissions')->insert(['user_id' => $user->id, 'manage_hc_obf' => true]);
        expect(Gate::forUser($user)->allows('view', $ticket))->toBeFalse();
        expect(fn () => $this->service->forClient($user, $this->master, $this->client))->toThrow(AuthorizationException::class);
    }
});

test('archived identities are not recreated or silently restored', function () {
    $ticket = $this->service->forClient($this->owner, $this->master, $this->client);
    $ticket->delete();
    expect(fn () => $this->service->forClient($this->owner, $this->master, $this->client))->toThrow(ValidationException::class);
    expect(Gate::forUser($this->owner)->allows('view', $ticket))->toBeFalse()
        ->and(OfficialBusinessTripTicket::withTrashed()->count())->toBe(1);
    $ticket->restore();
    expect($this->service->forClient($this->owner, $this->master, $this->client)->reference_no)->toBe($ticket->reference_no);
});

test('soft-deleting the master preserves tickets but denies access until restoration', function () {
    $ticket = $this->service->forClient($this->owner, $this->master, $this->client);
    $this->master->delete();
    expect(OfficialBusinessTripTicket::count())->toBe(1)
        ->and(Gate::forUser($this->owner)->allows('view', $ticket->fresh()))->toBeFalse();
    expect(fn () => $this->service->forClient($this->owner, $this->master, $this->client))
        ->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);
    $this->master->restore();
    expect(Gate::forUser($this->owner)->allows('view', $ticket->fresh()))->toBeTrue();
});

test('migration rollback restores the original master schema and data and can reapply', function () {
    $this->service->forClient($this->owner, $this->master, $this->client);
    obV3Migration()->down();
    expect(Schema::hasTable('official_business_trip_tickets'))->toBeFalse()
        ->and(Schema::hasColumn('official_business_trips', 'workflow_version'))->toBeFalse()
        ->and((array) DB::table('official_business_trips')->find($this->master->id))->toBe($this->legacyBefore);
    obV3Migration()->up();
    expect(OfficialBusinessTripTicket::count())->toBe(0)
        ->and($this->master->fresh()->workflow_version)->toBe(1);
});

test('legacy internal conversion still works before deployment or after migration rollback', function () {
    obV3Migration()->down();
    $this->master->update(['is_client_travel' => false]);
    expect($this->master->fresh()->is_client_travel)->toBeFalse()
        ->and($this->master->fresh()->status)->toBe('Completed');
});

test('v3 master with trip ticket history cannot be permanently deleted', function () {
    $admin = User::create([
        'name' => 'O.B. Delete Administrator',
        'email' => 'ob-delete-admin@example.test',
        'role' => 'Admin',
    ]);

    DB::table('user_permissions')->insert([
        'user_id' => $admin->id,
        'admin_hc_obf' => true,
    ]);

    $ticket = $this->service->forClient(
        $admin,
        $this->master,
        $this->client
    );

    $this->master->delete();

    expect(
        OfficialBusinessTrip::query()
            ->find($this->master->id)
    )->toBeNull()
        ->and(
            OfficialBusinessTrip::withTrashed()
                ->find($this->master->id)
                ?->trashed()
        )->toBeTrue()
        ->and(
            OfficialBusinessTripTicket::withTrashed()
                ->find($ticket->id)
        )->not->toBeNull();

    $response = $this
        ->actingAs($admin)
        ->deleteJson(route('activities.official-business.force-delete', [
            'id' => $this->master->id,
        ]));

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors('record');

    expect(
        OfficialBusinessTrip::withTrashed()
            ->find($this->master->id)
    )->not->toBeNull()
        ->and(
            OfficialBusinessTripTicket::withTrashed()
                ->find($ticket->id)
        )->not->toBeNull();
});

test('v3 master with archived trip ticket history also cannot be permanently deleted', function () {
    $admin = User::create([
        'name' => 'Archived History Administrator',
        'email' => 'archived-history-admin@example.test',
        'role' => 'Admin',
    ]);

    DB::table('user_permissions')->insert([
        'user_id' => $admin->id,
        'admin_hc_obf' => true,
    ]);

    $ticket = $this->service->forClient(
        $admin,
        $this->master,
        $this->client
    );

    $ticket->delete();
    $this->master->delete();

    $response = $this
        ->actingAs($admin)
        ->deleteJson(route('activities.official-business.force-delete', [
            'id' => $this->master->id,
        ]));

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors('record');

    expect(
        OfficialBusinessTrip::withTrashed()
            ->find($this->master->id)
    )->not->toBeNull()
        ->and(
            OfficialBusinessTripTicket::withTrashed()
                ->find($ticket->id)
        )->not->toBeNull();
});
