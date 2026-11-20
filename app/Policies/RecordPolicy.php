<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Record;
use App\Services\CompanyContext;

/**
 * Who may do what to a record.
 *
 * Two rules run through every method here.
 *
 * Company scope is checked first, and a record outside the active company is
 * denied rather than refused for its role. That ordering matters: answering 403
 * for someone else's record would confirm the id exists.
 *
 * The prototype has no sign-in, so the role comes from the authenticated user
 * when there is one and from config('ordo.default_role') when there is not.
 */
class RecordPolicy
{
    public function __construct(private readonly CompanyContext $context) {}

    public function viewAny(?object $user): bool
    {
        return UserRole::for($user)->canView();
    }

    public function view(?object $user, Record $record): bool
    {
        if (! $this->inScope($record)) {
            return false;
        }

        $role = UserRole::for($user);

        // A confidential record is not merely kept out of listings; the role that
        // cannot see them is refused the record itself. Without this clause view()
        // could not deny a confidential record to anybody, so the id-addressable
        // routes had nothing to enforce even once their policy was wired up.
        if ((bool) $record->is_confidential && ! $role->canSeeConfidential()) {
            return false;
        }

        return $role->canView();
    }

    public function update(?object $user, Record $record): bool
    {
        return $this->inScope($record) && UserRole::for($user)->canEdit();
    }

    /**
     * Creating a record without naming one first — filing, and any write that
     * produces a record as a side effect. It follows the same ability as editing
     * an existing one, because that is what it is: a records write that consumes
     * a Record Unit.
     */
    public function create(?object $user): bool
    {
        return UserRole::for($user)->canEdit();
    }

    public function archive(?object $user, Record $record): bool
    {
        return $this->inScope($record) && UserRole::for($user)->canArchive();
    }

    /**
     * Legal holds are reserved for a System Admin.
     */
    public function applyLegalHold(?object $user, Record $record): bool
    {
        return $this->inScope($record) && UserRole::for($user)->canApplyLegalHold();
    }

    public function releaseLegalHold(?object $user, Record $record): bool
    {
        return $this->applyLegalHold($user, $record);
    }

    public function link(?object $user, Record $record): bool
    {
        return $this->inScope($record) && UserRole::for($user)->canLink();
    }

    public function export(?object $user): bool
    {
        return UserRole::for($user)->canExport();
    }

    /**
     * Whether confidential records are included in the current user's listings.
     */
    public function viewConfidential(?object $user): bool
    {
        return UserRole::for($user)->canSeeConfidential();
    }

    /**
     * The picker searches across records, so it follows export visibility rather
     * than plain viewing.
     */
    public function usePicker(?object $user): bool
    {
        return UserRole::for($user)->canSeeConfidential();
    }

    /**
     * Company scope, checked before any role decision.
     *
     * A record that belongs to another company is denied here rather than
     * refused for its role, so its existence is never confirmed to a user who
     * has no business knowing about it.
     */
    private function inScope(Record $record): bool
    {
        try {
            $company = $this->context->current();
        } catch (\LogicException) {
            // No company context: nothing is in scope.
            return false;
        }

        return (string) $record->tenant_id === (string) $company->tenant_id
            && (string) $record->company_id === (string) $company->id;
    }
}
