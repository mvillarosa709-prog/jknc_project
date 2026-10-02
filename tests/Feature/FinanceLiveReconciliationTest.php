<?php

use App\Http\Controllers\FinanceController;
use App\Models\FinanceRecord;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    // Isolate these regressions from historical MySQL-only data migrations.
    expect(DB::connection()->getDriverName())->toBe('sqlite')
        ->and(DB::connection()->getDatabaseName())->toBe(':memory:');
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        foreach (['name', 'email', 'password', 'role', 'remember_token'] as $column) {
            $table->string($column)->nullable();
        }
        $table->timestamp('email_verified_at')->nullable();
        $table->boolean('is_active')->default(true);
        $table->timestamp('archived_at')->nullable();
        $table->timestamps();
    });
    Schema::create('user_permissions', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        foreach ((new UserPermission)->getFillable() as $column) {
            if ($column !== 'user_id') {
                $table->boolean($column)->default(false);
            }
        }
        $table->timestamps();
    });
    Schema::create('role_permissions', function (Blueprint $table) {
        $table->id();
        $table->string('role');
        $table->timestamps();
    });
    Schema::create('employees', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->nullable();
        foreach (['first_name', 'last_name', 'position', 'email'] as $column) {
            $table->string($column)->nullable();
        }
        $table->timestamps();
    });
    Schema::create('contacts', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->nullable();
        foreach (['cif_status', 'salutation', 'first_name', 'middle_initial', 'middle_name',
            'last_name', 'name_extension', 'email', 'phone', 'contact_address', 'company_name',
            'company_address', 'position', 'customer_type', 'client_status', 'cif_no', 'tin'] as $column) {
            $table->string($column)->nullable();
        }
        $table->timestamps();
    });
    Schema::create('finance_records', function (Blueprint $table) {
        $table->id();
        foreach (['module_key', 'record_number', 'record_title', 'status', 'workflow_status',
            'approval_status', 'relationship_status', 'next_action', 'disbursement_status',
            'review_note', 'share_token', 'user'] as $column) {
            $table->string($column)->nullable();
        }
        $table->date('record_date')->nullable();
        $table->decimal('amount', 15, 2)->nullable();
        foreach (['submitted_by', 'approved_by'] as $column) {
            $table->unsignedBigInteger($column)->nullable();
        }
        foreach (['submitted_at', 'approved_at', 'shared_at', 'supplier_completed_at'] as $column) {
            $table->timestamp($column)->nullable();
        }
        foreach (['data', 'attachments', 'transaction_progress'] as $column) {
            $table->json($column)->nullable();
        }
        $table->timestamps();
    });
    Schema::create('payroll_periods', function (Blueprint $table) {
        $table->id();
        $table->date('period_start')->nullable();
        $table->timestamps();
    });
});

test('live finance listings use newest ids and cap index and source document results', function () {
    $admin = User::factory()->create(['role' => 'SuperAdmin']);
    $ids = [];
    for ($i = 0; $i < 205; $i++) {
        $ids[] = FinanceRecord::query()->create([
            'module_key' => 'ca',
            'record_number' => 'CA-LIVE-'.$i,
            'record_title' => 'Local reconciliation fixture',
            // Older dates on newer ids distinguish indexed ordering from date ordering.
            'record_date' => now()->subDays($i)->toDateString(),
            'amount' => 100,
            'workflow_status' => 'Accepted',
            'approval_status' => 'Approved',
            'submitted_by' => $admin->id,
            'data' => [],
            'attachments' => [],
        ])->id;
    }
    $deleted = FinanceRecord::query()->create([
        'module_key' => 'ca', 'record_number' => 'CA-LIVE-DELETED',
        'record_title' => 'Excluded fixture', 'workflow_status' => 'Deleted',
        'approval_status' => 'Pending', 'submitted_by' => $admin->id, 'data' => [],
    ]);
    $this->actingAs($admin);
    $view = app(FinanceController::class)->index(Request::create('/finance', 'GET', ['module' => 'ca']));
    $data = $view->getData();
    $expected = array_reverse($ids);
    expect($data['records']->pluck('id')->all())->toBe(array_slice($expected, 0, 100))
        ->and($data['sourceRecords']->pluck('id')->all())->toBe(array_slice($expected, 0, 200));

    $active = (new ReflectionMethod(FinanceController::class, 'financeActiveRecords'))
        ->invoke(app(FinanceController::class));
    expect($active->pluck('id')->all())->toBe($expected)
        ->and($active->contains('id', $deleted->id))->toBeFalse();
});

test('finance listings retain module access and record ownership restrictions', function () {
    $employee = User::factory()->create(['role' => 'employee']);
    $other = User::factory()->create(['role' => 'employee']);
    $this->actingAs($employee)->get(route('finance'))->assertForbidden();
    UserPermission::query()->updateOrCreate(['user_id' => $employee->id], ['access_finance_ca' => true]);
    $employee->unsetRelation('userPermission');
    $own = FinanceRecord::query()->create([
        'module_key' => 'ca', 'record_number' => 'CA-OWN', 'record_title' => 'Own fixture',
        'workflow_status' => 'Accepted', 'approval_status' => 'Approved',
        'submitted_by' => $employee->id, 'data' => [],
    ]);
    FinanceRecord::query()->create([
        'module_key' => 'ca', 'record_number' => 'CA-OTHER', 'record_title' => 'Other fixture',
        'workflow_status' => 'Accepted', 'approval_status' => 'Approved',
        'submitted_by' => $other->id, 'data' => [],
    ]);
    $this->actingAs($employee->fresh());
    $data = app(FinanceController::class)->index(Request::create('/finance', 'GET', ['module' => 'ca']))->getData();
    expect($data['records']->pluck('id')->all())->toBe([$own->id])
        ->and($data['sourceRecords']->pluck('id')->all())->toBe([$own->id]);
});
