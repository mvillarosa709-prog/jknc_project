<?php

namespace App\Enums;

/**
 * What a person is allowed to do with records.
 *
 * The prototype has no sign-in yet, so a user may carry no role at all. Everything
 * therefore resolves through UserRole::for(), which falls back to the configured
 * default rather than treating a missing role as "no permissions".
 */
enum UserRole: string
{
    case SystemAdmin = 'system_admin';
    case RecordsManager = 'records_manager';
    case Auditor = 'auditor';
    case StandardUser = 'standard_user';

    public function label(): string
    {
        return match ($this) {
            self::SystemAdmin => 'System Admin',
            self::RecordsManager => 'Records Manager',
            self::Auditor => 'Auditor',
            self::StandardUser => 'Standard User',
        };
    }

    /**
     * The role to apply for a given user, falling back to the configured default.
     *
     * An unrecognised or missing role deliberately lands on the most restricted
     * value rather than the most permissive one.
     */
    public static function for(?object $user): self
    {
        $role = $user?->role ?? null;

        // The User model casts role to this enum, so it may already be resolved.
        if ($role instanceof self) {
            return $role;
        }

        if (is_string($role) && ($resolved = self::tryFrom($role)) !== null) {
            return $resolved;
        }

        $fallback = config('ordo.default_role');

        return is_string($fallback) && ($resolved = self::tryFrom($fallback)) !== null
            ? $resolved
            : self::StandardUser;
    }

    public function canView(): bool
    {
        return true;
    }

    /**
     * Editing record metadata. Auditors observe rather than change.
     */
    public function canEdit(): bool
    {
        return in_array($this, [self::SystemAdmin, self::RecordsManager], true);
    }

    public function canArchive(): bool
    {
        return in_array($this, [self::SystemAdmin, self::RecordsManager], true);
    }

    /**
     * Legal holds are the most consequential write on a record, so they are
     * reserved for a System Admin.
     */
    public function canApplyLegalHold(): bool
    {
        return $this === self::SystemAdmin;
    }

    public function canDiscard(): bool
    {
        return in_array($this, [self::SystemAdmin, self::RecordsManager], true);
    }

    public function canLink(): bool
    {
        return in_array($this, [self::SystemAdmin, self::RecordsManager], true);
    }

    /**
     * Changing the Classification and Index scheme.
     *
     * Reserved for a System Admin. Every filed Record points at a Classification
     * and an Index, and those codes decide what a Record is and how long it is
     * kept, so the scheme is reference data rather than day-to-day records work:
     * a Records Manager files records against it but does not rewrite it.
     */
    public function canManageScheme(): bool
    {
        return $this === self::SystemAdmin;
    }

    /**
     * Bulk and compliance exports pull more data than the register itself, so
     * they are narrower than ordinary viewing.
     */
    public function canExport(): bool
    {
        return $this !== self::StandardUser;
    }

    /**
     * Whether records marked confidential are included in listings.
     *
     * Confidential is not the same as hidden: a Standard User simply never sees
     * these rows in the register, the dashboard or search results.
     */
    public function canSeeConfidential(): bool
    {
        return in_array($this, [self::SystemAdmin, self::RecordsManager, self::Auditor], true);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::SystemAdmin->value => self::SystemAdmin->label(),
            self::RecordsManager->value => self::RecordsManager->label(),
            self::Auditor->value => self::Auditor->label(),
            self::StandardUser->value => self::StandardUser->label(),
        ];
    }
}
