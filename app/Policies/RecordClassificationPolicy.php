<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Services\CompanyContext;

/**
 * Who may manage the Classification and Index scheme.
 *
 * Browsing the scheme is open to anyone who can see records, because the scheme
 * is reference data that appears throughout the register and the drawers.
 * Changing it is not: a code entered here is the code every future Record is
 * filed against, so it is reserved for a System Admin.
 */
class RecordClassificationPolicy
{
    public function __construct(private readonly CompanyContext $context) {}

    public function viewAny(?object $user): bool
    {
        return UserRole::for($user)->canView();
    }

    /**
     * Adding a Classification or an Index.
     *
     * Checked before any write happens, so a role that may not edit the scheme
     * cannot create a code even by posting to the endpoint directly.
     */
    public function create(?object $user): bool
    {
        return $this->hasContext() && UserRole::for($user)->canManageScheme();
    }

    /**
     * With no company resolved there is nothing to scope a write to, so the
     * answer is no rather than an unguarded write.
     */
    private function hasContext(): bool
    {
        try {
            $this->context->current();
        } catch (\LogicException) {
            return false;
        }

        return true;
    }
}
