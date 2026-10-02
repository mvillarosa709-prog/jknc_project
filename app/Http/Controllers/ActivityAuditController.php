<?php

namespace App\Http\Controllers;

use App\Models\ActivityAuditLog;
use App\Services\ActivityTypeRegistry;
use App\Services\ActivityAuditService;
use Illuminate\Http\Request;

class ActivityAuditController extends Controller
{
    public function index(Request $request, string $type, int|string $id)
    {
        $id = (int) $id;

        $type = app(ActivityTypeRegistry::class)->normalize($type);
        app(ActivityTypeRegistry::class)->resolve($type, $id, $request->user());

        $audit = app(ActivityAuditService::class);
        $isOfficialBusiness = in_array($type, [
            ActivityTypeRegistry::OFFICIAL_BUSINESS_TRAVEL,
            ActivityTypeRegistry::OFFICIAL_BUSINESS_TRIP_TICKET,
        ], true);
        $canReadFinance = ! $isOfficialBusiness
            || app(\App\Services\OfficialBusinessAuthorizationService::class)->canReadFinance($request->user());

        return response()->json([
            'audit_log' => ActivityAuditLog::query()
                ->with('user:id,name,email')
                ->where('activity_type', $type)
                ->where('activity_id', $id)
                ->latest('id')
                ->get()
                ->reject(fn (ActivityAuditLog $log): bool => ! $canReadFinance
                    && $type === ActivityTypeRegistry::OFFICIAL_BUSINESS_TRIP_TICKET
                    && in_array($log->action, [
                        'billing_treatment_updated', 'travel_credit_used', 'travel_credit_reversed',
                        'additional_cost_added', 'additional_cost_removed', 'additional_cost_updated',
                    ], true))
                ->map(function (ActivityAuditLog $log) use ($audit, $isOfficialBusiness, $canReadFinance): array {
                    $entry = $audit->serialize($log);
                    if ($isOfficialBusiness && ! $canReadFinance) {
                        $entry['changes'] = null;
                        $entry['details'] = null;
                        unset($entry['metadata']['details']);
                    }

                    return $entry;
                })
                ->values(),
        ]);
    }
}
