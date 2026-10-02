<?php

use App\Http\Controllers\CompanyServiceController;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    expect(DB::connection()->getDriverName())->toBe('sqlite')
        ->and(DB::connection()->getDatabaseName())->toBe(':memory:');
});

function validateLiveService(array $changes = []): array
{
    $request = Request::create('/services', 'POST', array_replace([
        'service_name' => 'Local reconciliation fixture',
        'service_description' => 'Local only',
        'service_activity_output' => 'Test output',
        'service_area' => ['Accounting & Compliance Advisory'],
        'engagement_structure' => ['Regular (Retainer)'],
        'frequency' => 'No Frequency / Not Applicable',
        'unit' => 'Fixed', 'price_fee' => 100, 'tax_type' => 'Tax Inclusive',
    ], $changes));

    return (new ReflectionMethod(CompanyServiceController::class, 'validateService'))
        ->invoke(app(CompanyServiceController::class), $request, collect(), true);
}

test('live service non scheduled frequencies need neither schedule nor deadline', function ($frequency) {
    $validated = validateLiveService(['frequency' => $frequency]);
    expect($validated['frequency'])->toBe($frequency)
        ->and($validated['schedule_rule'] ?? null)->toBeNull()
        ->and($validated['deadline'] ?? null)->toBeNull();
})->with(['No Frequency / Not Applicable', 'One-time', 'Per Transaction', null]);

test('recurring scheduled services require a schedule rule without requiring a deadline', function ($engagement) {
    try {
        validateLiveService(['frequency' => 'Monthly', 'engagement_structure' => [$engagement]]);
        $this->fail('A recurring scheduled service must require a schedule rule.');
    } catch (ValidationException $exception) {
        expect(array_keys($exception->errors()))->toBe(['schedule_rule']);
    }
    $validated = validateLiveService([
        'frequency' => 'Monthly', 'engagement_structure' => [$engagement], 'schedule_rule' => 'Every month',
    ]);
    expect($validated['schedule_rule'])->toBe('Every month')
        ->and($validated['deadline'] ?? null)->toBeNull();
})->with(['Regular (Retainer)', 'Hybrid']);

test('project engagements do not require recurring schedules and optional deadlines stay validated', function () {
    expect(validateLiveService(['frequency' => 'Monthly', 'engagement_structure' => ['Project Engagement']])['frequency'])
        ->toBe('Monthly');
    expect(validateLiveService(['deadline' => '2026-12-01'])['deadline'])->toBe('2026-12-01');
    try {
        validateLiveService(['deadline' => 'invalid date']);
        $this->fail('Invalid supplied deadlines must be rejected.');
    } catch (ValidationException $exception) {
        expect(array_keys($exception->errors()))->toBe(['deadline']);
    }
});

test('service modal renders save failures and escaped validation messages only when errors exist', function () {
    $data = ['title' => 'Service', 'action' => '/services', 'companyLocked' => false, 'lockedCompany' => null];
    foreach (['assignedUnitOptions', 'categories', 'customFields', 'engagementOptions', 'frequencyOptions',
        'reminderLeadTimes', 'serviceAreaOptions', 'taxTypeOptions', 'unitOptions'] as $key) {
        $data[$key] = collect();
    }
    $data['errors'] = (new ViewErrorBag)->put('default', new MessageBag(['schedule_rule' => ['Schedule required <script>']]));
    $html = view('services.partials.service-form-modal', $data)->render();
    expect($html)->toContain('Unable to save service.', 'Schedule required &lt;script&gt;')
        ->not->toContain('Schedule required <script>');
    $data['errors'] = new ViewErrorBag;
    expect(view('services.partials.service-form-modal', $data)->render())->not->toContain('Unable to save service.');
});
