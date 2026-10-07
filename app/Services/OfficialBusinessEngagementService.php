<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Collection;

/** Reads the existing Regular/Project workspaces; never provisions client work. */
class OfficialBusinessEngagementService
{
    public function eligible(int $companyId, string $type): Collection
    {
        return Project::query()->where('company_id', $companyId)->orderBy('name')->get()
            ->filter(fn (Project $workspace): bool => $this->isEligible($workspace, $type))
            ->values();
    }

    public function isEligible(Project $workspace, string $type): bool
    {
        $regular = str_contains(strtolower(trim((string) $workspace->engagement_type)), 'regular');

        return in_array($type, ['Regular', 'Project'], true)
            && $regular === ($type === 'Regular')
            && ! $workspace->isShell()
            && in_array(strtolower(trim((string) $workspace->status)), [
                'start', 'sow', 'rsat', 'in progress', 'for ntp approval',
                'execution', 'reporting', 'delivery',
            ], true);
    }

    /** Legacy deal-only IDs are usable only when they identify exactly one workspace. */
    public function legacyRegular(int $companyId, int $dealId): ?Project
    {
        $matches = $this->eligible($companyId, 'Regular')
            ->filter(fn (Project $workspace): bool => (int) $workspace->deal_id === $dealId);

        return $matches->count() === 1 ? $matches->first() : null;
    }
}
