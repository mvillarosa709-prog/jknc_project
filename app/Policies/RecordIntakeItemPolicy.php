<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\RecordIntakeItem;
use App\Services\CompanyContext;

/**
 * Permissions over the intake staging queue.
 *
 * An unfiled item carries no Document Reference and no Record Unit, so filing one
 * follows the ordinary edit rule; discarding is treated as the more consequential
 * of the two because it takes a document out of the queue entirely.
 */
class RecordIntakeItemPolicy
{
    public function __construct(private readonly CompanyContext $context) {}

    public function viewAny(?object $user): bool
    {
        return UserRole::for($user)->canView();
    }

    public function view(?object $user, RecordIntakeItem $item): bool
    {
        return $this->inScope($item) && UserRole::for($user)->canView();
    }

    public function file(?object $user, RecordIntakeItem $item): bool
    {
        return $this->inScope($item) && UserRole::for($user)->canEdit();
    }

    public function bulkFile(?object $user): bool
    {
        return UserRole::for($user)->canEdit();
    }

    public function discard(?object $user, RecordIntakeItem $item): bool
    {
        return $this->inScope($item) && UserRole::for($user)->canDiscard();
    }

    /**
     * Scope first: a foreign item is denied before any role is considered, so its
     * existence is never confirmed.
     */
    private function inScope(RecordIntakeItem $item): bool
    {
        try {
            $company = $this->context->current();
        } catch (\LogicException) {
            return false;
        }

        return (string) $item->tenant_id === (string) $company->tenant_id
            && (string) $item->company_id === (string) $company->id;
    }
}
