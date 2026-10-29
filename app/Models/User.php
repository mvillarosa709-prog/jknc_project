<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'transmittal_role',
        'can_edit_user_roles',
        'can_delete_users',
        'is_active',
        'must_change_password',
        'temporary_password_expires_at',
        'password_changed_at',
        'disabled_at',
        'disabled_by',
        'disabled_reason',
        'archived_at',
        'archived_by',
        'archived_reason',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function canApproveTransmittals(): bool
    {
        return $this->transmittal_role === 'administrator' || $this->isSuperAdmin() || $this->isAdmin();
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'can_edit_user_roles' => 'boolean',
            'can_delete_users' => 'boolean',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'temporary_password_expires_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'disabled_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * Override Laravel's default password reset email.
     * This sends the JK&C branded reset password notification.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function setRoleAttribute($value): void
    {
        $normalized = strtolower(trim((string) $value));

        $this->attributes['role'] = match ($normalized) {
            'superadmin' => 'SuperAdmin',
            'admin' => 'Admin',
            'employee' => 'Employee',
            'client' => 'Client',
            default => trim((string) $value),
        };
    }

    public function userPermission()
    {
        return $this->hasOne(UserPermission::class);
    }

    public function employeeProfile()
    {
        return $this->hasOne(Employee::class, 'user_id');
    }

    public function contactProfile()
    {
        return $this->hasOne(Contact::class, 'user_id');
    }

    public function disabledBy()
    {
        return $this->belongsTo(User::class, 'disabled_by');
    }

    public function archivedBy()
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function isSuperAdmin(): bool
    {
        return strtolower((string) $this->role) === 'superadmin';
    }

    public function isAdmin(): bool
    {
        return strtolower((string) $this->role) === 'admin';
    }

    public function isEmployee(): bool
    {
        return strtolower((string) $this->role) === 'employee';
    }

    public function isClient(): bool
    {
        return strtolower((string) $this->role) === 'client';
    }

    public function canManageRoles(): bool
    {
        return $this->isSuperAdmin() || ($this->isAdmin() && (bool) $this->can_edit_user_roles);
    }

    public function canDeleteUsers(): bool
    {
        return $this->isSuperAdmin() || ($this->isAdmin() && (bool) $this->can_delete_users);
    }

    public function isFinanceTreasurer(): bool
    {
        return (bool) data_get($this->userPermission, 'finance_treasurer', false);
    }

    public function isFinancePresident(): bool
    {
        return (bool) data_get($this->userPermission, 'finance_president', false);
    }

    public function isFinanceApprover(): bool
    {
        return (bool) data_get($this->userPermission, 'finance_approver', false);
    }

    public function isDisabled(): bool
    {
        return !((bool) ($this->is_active ?? true))
            || !is_null($this->disabled_at)
            || !is_null($this->archived_at);
    }

    public function isArchived(): bool
    {
        return !is_null($this->archived_at);
    }

    public function hasUserAccountPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $userPermission = $this->userPermission;

        return $userPermission && isset($userPermission->{$permission})
            ? (bool) $userPermission->{$permission}
            : false;
    }

    public function canCreateUserAccount(): bool
    {
        return $this->hasUserAccountPermission('create_user_account');
    }

    public function canEditUserAccount(): bool
    {
        return $this->hasUserAccountPermission('edit_user_account');
    }

    public function canDisableEnableUserAccount(): bool
    {
        return $this->hasUserAccountPermission('disable_enable_user_account');
    }

    public function canResetUserPassword(): bool
    {
        return $this->hasUserAccountPermission('reset_user_password');
    }

    public function canDeleteUserAccount(): bool
    {
        return $this->hasUserAccountPermission('delete_user_account');
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $userPermission = $this->userPermission;

        if ($userPermission && isset($userPermission->{$permission})) {
            return (bool) $userPermission->{$permission};
        }

        $rolePermission = RolePermission::where('role', $this->role)->first();

        return $rolePermission && isset($rolePermission->{$permission})
            ? (bool) $rolePermission->{$permission}
            : false;
    }
}
