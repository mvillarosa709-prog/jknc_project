<?php

namespace App\Http\Controllers;

use App\Mail\SupplierCompletionMail;
use App\Mail\SupplierRevertMail;
use App\Models\Company;
use App\Models\CompanyBif;
use App\Models\Contact;
use App\Models\GisRecord;
use App\Models\EmployeePayrollProfile;
use App\Models\Employee;
use App\Models\FinanceRecord;
use App\Models\PayrollPeriod;
use App\Models\PayrollSummary;
use App\Models\PayrollSummaryItem;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\FinanceRecordWorkflowNotification;
use App\Services\PayrollCalculator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Throwable;

class FinanceController extends Controller
{
    private const MODULES = [
        'supplier' => 'Supplier',
        'service' => 'Service',
        'product' => 'Product',
        'chart_account' => 'Chart of Accounts',
        'bank_account' => 'Bank Accounts',
        'pr' => 'Purchase Request',
        'po' => 'Purchase Order',
        'ca' => 'Cash Advance',
        'lr' => 'Liquidation Report',
        'err' => 'Expense Reimbursement Request',
        'dv' => 'Disbursement Voucher',
        'pda' => 'Payroll Disbursement Authorization',
        'crf' => 'Cash Return Form',
        'ibtf' => 'Interbank Fund Transfer Form',
        'arf' => 'Asset Registration Form',
    ];

    private const WORKFLOW_STATUSES = [
        'Uploaded',
        'Submitted',
        'On Hold',
        'Shared',
        'Accepted',
        'Reverted',
        'Archived',
        'Delete Requested',
        'Deleted',
    ];

    private const DROPDOWN_SETTINGS_KEY = 'finance_dropdown_options';
    private const ATTACHMENT_TYPES_SETTINGS_KEY = 'finance_attachment_types';
    private const LABEL_OVERRIDES_SETTINGS_KEY = 'finance_label_overrides';
    private const DV_ALLOW_INSUFFICIENT_FUNDS_APPROVAL_SETTINGS_KEY = 'finance_allow_dv_approval_with_insufficient_funds';

    private function canApproveFinance(): bool
    {
        return $this->canAdministerFinance();
    }

    private function requestTypeModuleKeys(): array
    {
        return ['pr', 'po', 'ca', 'lr', 'err', 'dv', 'pda', 'crf', 'ibtf', 'arf', 'bank_account', 'chart_account'];
    }

    private function financeModulePermissionMap(): array
    {
        return [
            'supplier' => 'access_finance_supplier',
            'service' => 'access_finance_service',
            'product' => 'access_finance_product',
            'chart_account' => 'access_finance_chart_account',
            'bank_account' => 'access_finance_bank_account',
            'pr' => 'access_finance_pr',
            'po' => 'access_finance_po',
            'ca' => 'access_finance_ca',
            'lr' => 'access_finance_lr',
            'err' => 'access_finance_err',
            'dv' => 'access_finance_dv',
            'pda' => 'access_finance_pda',
            'crf' => 'access_finance_crf',
            'ibtf' => 'access_finance_ibtf',
            'arf' => 'access_finance_arf',
        ];
    }

    private function moduleRequiresTwoPersonApproval(string $moduleKey): bool
    {
       return in_array($moduleKey, [
        'supplier',
        'pr',
        'po',
        'ca',
        'lr',
        'err',
        'dv',
        'pda',
        'crf',
        'ibtf',
        'arf',
        'bank_account',
        'chart_account',
    ], true);
    }

    private function financeApprovalThreshold(string $moduleKey): int
    {
        return $this->moduleRequiresTwoPersonApproval($moduleKey) ? 2 : 1;
    }

    private function financeApprovalRolePriority(): array
    {
        return ['Treasurer', 'President', 'Approver'];
    }

    private function financeUsesFinanceOperationsLabels(?string $moduleKey): bool
    {
        return in_array((string) $moduleKey, [
            'pr',
            'po',
            'ca',
            'lr',
            'err',
            'pda',
            'crf',
            'arf',
        ], true);
    }

    private function financeDisplayApprovalRoleLabels(?string $moduleKey): array
    {
        return $this->financeUsesFinanceOperationsLabels($moduleKey)
            ? ['Finance', 'Operations']
            : ['Treasurer', 'President'];
    }

    private function financeUserDisplayName(?int $userId, string $fallback = 'N/A'): string
    {
        if (blank($userId)) {
            return $fallback;
        }

        static $userDisplayNames = [];

        $userId = (int) $userId;

        if (array_key_exists($userId, $userDisplayNames)) {
            return $userDisplayNames[$userId] ?: $fallback;
        }

        $user = User::query()
            ->with(['employeeProfile', 'contactProfile'])
            ->find($userId);

        if (! $user) {
            $userDisplayNames[$userId] = null;
            return $fallback;
        }

        return $userDisplayNames[$userId] = trim((string) ($user->name
            ?: optional($user->employeeProfile)->full_name
            ?: optional($user->contactProfile)->first_name . ' ' . optional($user->contactProfile)->last_name
            ?: $user->email
            ?: $fallback));
    }

    private function financeApprovalActorNames(FinanceRecord $record): array
    {
        $actions = collect((array) data_get($record->data ?? [], 'approval_actions', []))
            ->filter(fn ($action) => is_array($action))
            ->values();

        return $actions
            ->map(fn (array $action) => trim((string) (data_get($action, 'approved_by_name') ?: data_get($action, 'official_name') ?: data_get($action, 'approver_role') ?: 'Finance Approver')))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function financeSubmittedByName(FinanceRecord $record): string
    {
        if ($record->module_key === 'supplier') {
            $supplierName = trim((string) data_get($record->data ?? [], 'supplier_submitted_by_name', data_get($record->data ?? [], 'representative_full_name', '')));
            $supplierEmail = trim((string) data_get($record->data ?? [], 'supplier_submitted_by_email', data_get($record->data ?? [], 'email_address', '')));

            if ($supplierName !== '' && $supplierEmail !== '') {
                return $supplierName . ' (' . $supplierEmail . ')';
            }

            if ($supplierName !== '') {
                return $supplierName;
            }

            if ($supplierEmail !== '') {
                return $supplierEmail;
            }
        }

        return $this->financeUserDisplayName($record->submitted_by, $record->user ?: 'N/A');
    }

    private function financeNormalizePersonName(?string $value): string
    {
        $normalized = Str::of((string) $value)
            ->lower()
            ->replaceMatches('/[^a-z0-9\s]/', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->value();

        return $normalized;
    }

    private function financeLatestApprovedGisRecord(): ?GisRecord
    {
        if (!Schema::hasTable('gis_records')) {
            return null;
        }

        $query = GisRecord::query()
            ->with('directors')
            ->where(function ($query) {
                $query->where('approval_status', 'Approved')
                    ->orWhere('workflow_status', 'Accepted');
            });

        $query->where(function ($query) {
            $query->where('corporation_name', 'like', '%JK&C%')
                ->orWhere('corporation_name', 'like', '%JKNC%')
                ->orWhere('corporation_name', 'like', '%John Kelly%')
                ->orWhere('trade_name', 'like', '%JK&C%')
                ->orWhere('trade_name', 'like', '%JKNC%')
                ->orWhere('trade_name', 'like', '%John Kelly%')
                ->orWhere('parent_company_name', 'like', '%JK&C%')
                ->orWhere('parent_company_name', 'like', '%JKNC%')
                ->orWhere('subsidiary_name', 'like', '%JK&C%')
                ->orWhere('subsidiary_name', 'like', '%JKNC%');
        });

        return $query
            ->orderByDesc('period_date')
            ->orderByDesc('created_at')
            ->first();
    }

    private function financeLatestOfficialBif(): ?CompanyBif
    {
        if (!Schema::hasTable('company_bifs')) {
            return null;
        }

        return CompanyBif::query()
            ->orderByDesc('approved_at')
            ->orderByDesc('submitted_at')
            ->orderByDesc('created_at')
            ->first();
    }

    private function financeResolveOfficialApproverContactEmail(?string $officerName): ?string
    {
        $normalizedOfficerName = $this->financeNormalizePersonName($officerName);

        if ($normalizedOfficerName === '') {
            return null;
        }

        static $contactEmailMatches = [];
        static $contactsWithEmail = null;

        if (array_key_exists($normalizedOfficerName, $contactEmailMatches)) {
            return $contactEmailMatches[$normalizedOfficerName];
        }

        $contactsWithEmail ??= Contact::query()
            ->whereNotNull('email')
            ->get();

        return $contactEmailMatches[$normalizedOfficerName] = $contactsWithEmail
            ->map(function (Contact $contact) use ($normalizedOfficerName) {
                $contactName = $this->financeNormalizePersonName(trim(implode(' ', array_filter([
                    $contact->first_name,
                    $contact->middle_name ?: $contact->middle_initial,
                    $contact->last_name,
                    $contact->name_extension,
                ]))));

                $score = 0;
                if ($contactName !== '' && $contactName === $normalizedOfficerName) {
                    $score = 300;
                } elseif ($contactName !== '' && (Str::contains($contactName, $normalizedOfficerName) || Str::contains($normalizedOfficerName, $contactName))) {
                    $score = 220;
                }

                return [
                    'email' => $contact->email,
                    'score' => $score,
                ];
            })
            ->filter(fn (array $candidate) => $candidate['score'] > 0 && filled($candidate['email']))
            ->sortByDesc('score')
            ->pluck('email')
            ->first();
    }

    private function financeResolveOfficialApproverUser(?string $officerName, string $role, ?string $officialEmail = null): ?User
    {
        static $resolvedUsers = [];
        static $users = null;

        $cacheKey = implode('|', [
            $this->financeNormalizePersonName($officerName),
            Str::lower(trim($role)),
            Str::lower(trim((string) $officialEmail)),
        ]);

        if (array_key_exists($cacheKey, $resolvedUsers)) {
            return $resolvedUsers[$cacheKey];
        }

        if (filled($officialEmail)) {
            $emailMatch = User::query()
                ->whereRaw('LOWER(email) = ?', [Str::lower(trim((string) $officialEmail))])
                ->first();

            if ($emailMatch) {
                return $resolvedUsers[$cacheKey] = $emailMatch;
            }
        }

        $users ??= User::query()
            ->with(['employeeProfile', 'contactProfile'])
            ->get();

        $normalizedOfficerName = $this->financeNormalizePersonName($officerName);
        $roleNeedle = Str::lower($role);

        return $resolvedUsers[$cacheKey] = $users
            ->map(function (User $user) use ($normalizedOfficerName, $roleNeedle) {
                $candidateNames = collect([
                    $user->name,
                    optional($user->employeeProfile)->full_name,
                    optional($user->contactProfile)->first_name && optional($user->contactProfile)->last_name
                        ? trim(optional($user->contactProfile)->first_name . ' ' . optional($user->contactProfile)->last_name)
                        : null,
                ])->filter()->unique()->values();

                $score = 0;

                foreach ($candidateNames as $candidateName) {
                    $normalizedCandidate = $this->financeNormalizePersonName($candidateName);

                    if ($normalizedOfficerName !== '' && $normalizedCandidate === $normalizedOfficerName) {
                        $score = max($score, 300);
                    } elseif (
                        $normalizedOfficerName !== ''
                        && (Str::contains($normalizedCandidate, $normalizedOfficerName) || Str::contains($normalizedOfficerName, $normalizedCandidate))
                    ) {
                        $score = max($score, 220);
                    } elseif ($normalizedOfficerName !== '') {
                        $tokens = collect(explode(' ', $normalizedOfficerName))->filter();
                        if ($tokens->isNotEmpty() && $tokens->every(fn ($token) => Str::contains($normalizedCandidate, $token))) {
                            $score = max($score, 180);
                        }
                    }
                }

                $positionHaystack = Str::lower(trim(implode(' ', array_filter([
                    $user->position,
                    optional($user->employeeProfile)->position,
                    optional($user->contactProfile)->position,
                    $user->name,
                ]))));

                if ($roleNeedle !== '' && Str::contains($positionHaystack, $roleNeedle)) {
                    $score += 40;
                }

                return [
                    'user' => $user,
                    'score' => $score,
                ];
            })
            ->filter(fn (array $candidate) => $candidate['score'] > 0)
            ->sortByDesc('score')
            ->pluck('user')
            ->first();
    }

    private function financeOfficialApproverDirectory(): array
    {
        static $directory = null;

        if ($directory !== null) {
            return $directory;
        }

        $officialRows = collect();
        $gisRecord = $this->financeLatestApprovedGisRecord();
        $bifRecord = $this->financeLatestOfficialBif();

        if ($gisRecord) {
            $officialRows = $officialRows->merge(
                $gisRecord->directors
                    ->filter(fn ($director) => filled($director->officer_name) && in_array(Str::lower(trim((string) $director->officer_type)), ['president', 'treasurer'], true))
                    ->map(function ($director) {
                        return [
                            'official_name' => trim((string) $director->officer_name),
                            'role' => trim((string) $director->officer_type) ?: 'Officer',
                            'email' => trim((string) $director->email),
                            'source' => 'GIS',
                        ];
                    })
            );
        }

        if ($bifRecord) {
            $officialRows = $officialRows->merge(collect([
                [
                    'official_name' => trim((string) $bifRecord->president_name),
                    'role' => 'President',
                    'email' => null,
                    'source' => 'BIF',
                ],
                [
                    'official_name' => trim((string) $bifRecord->treasurer_name),
                    'role' => 'Treasurer',
                    'email' => null,
                    'source' => 'BIF',
                ],
            ])->filter(fn (array $row) => filled($row['official_name'])));
        }

        $permissionRows = $this->financePermissionApproverRows();
        if ($permissionRows->isNotEmpty()) {
            $officialRows = $officialRows->merge($permissionRows);
        }

        $officialRows = $officialRows
            ->filter(fn (array $row) => filled($row['official_name']))
            ->unique(fn (array $row) => $this->financeNormalizePersonName($row['official_name']) . '|' . Str::lower((string) $row['role']))
            ->values();

        $options = $officialRows
            ->map(function (array $row) {
                $officialEmail = trim((string) ($row['email'] ?? ''))
                    ?: $this->financeResolveOfficialApproverContactEmail($row['official_name']);
                $user = $this->financeResolveOfficialApproverUser($row['official_name'], $row['role'], $officialEmail);
                if (!$user) {
                    return null;
                }

                return [
                    'user_id' => (int) $user->id,
                    'user_name' => $user->name,
                    'user_email' => $officialEmail ?: $user->email,
                    'official_name' => $row['official_name'],
                    'role' => $row['role'],
                    'source' => $row['source'],
                    'label' => trim($row['official_name'] . ' (' . $row['role'] . ')'),
                ];
            })
            ->filter()
            ->unique(fn (array $option) => Str::lower((string) $option['role']) . '|' . (int) $option['user_id'])
            ->values();

        $defaults = collect($this->financeApprovalRolePriority())
            ->map(function (string $role) use ($options) {
                $match = $options->first(function (array $option) use ($role) {
                    return Str::lower((string) $option['role']) === Str::lower($role);
                });

                return $match ? ['role' => $role, ...$match] : null;
            })
            ->filter()
            ->unique(fn (array $option) => (int) $option['user_id'])
            ->values();

        return $directory = [
            'options' => $options->all(),
            'default_steps' => $defaults->map(fn (array $option, int $index) => [
                'step' => $index + 1,
                'role' => $option['role'],
                'label' => $option['role'],
                'required' => true,
                'user_id' => $option['user_id'],
                'user_name' => $option['user_name'],
                'user_email' => $option['user_email'],
                'official_name' => $option['official_name'],
                'source' => $option['source'],
            ])->all(),
            'available_default_roles' => $defaults
                ->pluck('role')
                ->map(fn ($role) => trim((string) $role))
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'missing_default_roles' => collect($this->financeApprovalRolePriority())
                ->reject(fn (string $role) => $defaults->contains(fn (array $option) => Str::lower((string) $option['role']) === Str::lower($role)))
                ->values()
                ->all(),
        ];
    }

    private function financePermissionApproverRows()
    {
        if (
            !Schema::hasTable('user_permissions')
            || !Schema::hasColumn('user_permissions', 'finance_treasurer')
            || !Schema::hasColumn('user_permissions', 'finance_president')
            || !Schema::hasColumn('user_permissions', 'finance_approver')
        ) {
            return collect();
        }

        return User::query()
            ->whereHas('userPermission', function ($query) {
                $query->where('finance_treasurer', true)
                    ->orWhere('finance_president', true)
                    ->orWhere('finance_approver', true);
            })
            ->get()
            ->flatMap(function (User $user) {
                $permission = $user->userPermission;
                if (! $permission) {
                    return [];
                }

                $officialName = trim((string) (
                    $user->name
                    ?: optional($user->employeeProfile)->full_name
                    ?: optional($user->contactProfile)->full_name
                    ?: $user->email
                    ?: 'Finance Approver'
                ));

                $rows = [];

                if ((bool) data_get($permission, 'finance_treasurer')) {
                    $rows[] = [
                        'official_name' => $officialName,
                        'role' => 'Treasurer',
                        'email' => trim((string) $user->email),
                        'source' => 'User Permission',
                    ];
                }

                if ((bool) data_get($permission, 'finance_president')) {
                    $rows[] = [
                        'official_name' => $officialName,
                        'role' => 'President',
                        'email' => trim((string) $user->email),
                        'source' => 'User Permission',
                    ];
                }

                if ((bool) data_get($permission, 'finance_approver')) {
                    $rows[] = [
                        'official_name' => $officialName,
                        'role' => 'Approver',
                        'email' => trim((string) $user->email),
                        'source' => 'User Permission',
                    ];
                }

                return $rows;
            })
            ->values();
    }

    private function financeOfficialApproverOptionByUserId(mixed $userId): ?array
    {
        $targetId = (int) $userId;

        if ($targetId <= 0) {
            return null;
        }

        return collect($this->financeOfficialApproverDirectory()['options'])
            ->first(fn (array $option) => (int) ($option['user_id'] ?? 0) === $targetId);
    }

    private function financeEmployeeApproverOptionByUserId(mixed $userId): ?array
    {
        $targetId = (int) $userId;

        if ($targetId <= 0 || !Schema::hasTable('employees')) {
            return null;
        }

        $employee = Employee::query()
            ->when(Schema::hasTable('departments'), fn ($query) => $query->with('department'))
            ->with('user')
            ->where('user_id', $targetId)
            ->first();

        if (! $employee || blank($employee->user_id)) {
            return null;
        }

        $employeeName = trim((string) ($employee->full_name ?: $employee->employee_code ?: ('Employee #' . $employee->id)));

        return [
            'user_id' => (int) $employee->user_id,
            'user_name' => $employee->user?->name ?: $employeeName,
            'user_email' => $employee->user?->email ?: $employee->login_email ?: $employee->email,
            'official_name' => $employeeName,
            'role' => trim((string) $employee->position) ?: 'Employee',
            'source' => 'Employee',
            'label' => trim(implode(' - ', array_filter([
                $employeeName,
                $employee->position ?: null,
                $employee->department?->department_name ?: null,
            ]))) ?: ('Employee #' . $employee->id),
        ];
    }

    private function financeApproverOptionByUserId(mixed $userId, ?string $moduleKey = null): ?array
    {
        return $this->financeOfficialApproverOptionByUserId($userId);
    }

    private function financeSelectedApprovalUserIds(array $data): array
    {
        $userIds = [
            data_get($data, 'first_approver_user_id'),
            data_get($data, 'second_approver_user_id'),
        ];

        if (blank($userIds[0]) && blank($userIds[1])) {
            $userIds = collect((array) data_get($data, 'approval_steps', []))
                ->pluck('user_id')
                ->all();
        }

        return collect($userIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function financeApprovalRoleLabelsForRecord(?FinanceRecord $record = null, ?string $moduleKey = null): array
    {
        $moduleKey ??= $record?->module_key;
        $dataRoles = collect((array) data_get($record?->data ?? [], 'approval_steps', []))
            ->pluck('role')
            ->map(fn ($role) => trim((string) $role))
            ->filter()
            ->values()
            ->all();

        $priorityRoles = $this->financeDisplayApprovalRoleLabels($moduleKey);

        return [
            $dataRoles[0] ?? $priorityRoles[0],
            $dataRoles[1] ?? $priorityRoles[1],
        ];
    }

    private function defaultFinanceApprovalSteps(array $data = [], ?string $moduleKey = null): array
    {
        $moduleKey ??= (string) data_get($data, 'module_key', '');
        $existing = array_values((array) data_get($data, 'approval_steps', []));

        if (!$this->moduleRequiresTwoPersonApproval($moduleKey)) {
            return $existing;
        }

        $selectedUserIds = $this->financeSelectedApprovalUserIds($data);
        $defaultSteps = $this->financeOfficialApproverDirectory()['default_steps'];
        $steps = [];

        foreach (($selectedUserIds ?: array_column($defaultSteps, 'user_id')) as $index => $userId) {
            $option = $this->financeApproverOptionByUserId($userId, $moduleKey);
            if (!$option) {
                continue;
            }

            $fallbackRoles = $this->financeDisplayApprovalRoleLabels($moduleKey);
            $role = trim((string) ($option['role'] ?? data_get($defaultSteps, $index . '.role', $fallbackRoles[$index] ?? ($index === 0 ? 'Treasurer' : 'President'))));

            $steps[] = [
                'step' => $index + 1,
                'role' => $role,
                'label' => $role,
                'required' => true,
                'user_id' => $option['user_id'],
                'user_name' => $option['user_name'],
                'user_email' => $option['user_email'],
                'official_name' => $option['official_name'],
                'source' => $option['source'],
            ];
        }

        return array_slice($steps, 0, 2);
    }

    private function initializeFinanceApprovalState(array $data, string $moduleKey): array
    {
        $data['approval_steps'] = $this->defaultFinanceApprovalSteps($data, $moduleKey);
        $data['approval_required_count'] = $this->financeApprovalThreshold($moduleKey);
        $data['approval_actions'] = array_values((array) data_get($data, 'approval_actions', []));
        $data['first_approver_user_id'] = data_get($data['approval_steps'], '0.user_id');
        $data['second_approver_user_id'] = data_get($data['approval_steps'], '1.user_id');

        return $data;
    }

    private function financeApprovalActions(FinanceRecord $record): array
    {
        return array_values(array_filter((array) data_get($record->data ?? [], 'approval_actions', []), 'is_array'));
    }

    private function financeUserApprovalRole(): string
    {
        $user = Auth::user();
        $permission = $user?->userPermission;

        if ((bool) data_get($permission, 'finance_treasurer')) {
            return 'Treasurer';
        }

        if ((bool) data_get($permission, 'finance_president')) {
            return 'President';
        }

        if ((bool) data_get($permission, 'finance_approver')) {
            return 'Approver';
        }

        $position = trim((string) ($user?->position ?: $user?->employeeProfile?->position ?: ''));
        $name = trim((string) ($user?->name ?: ''));
        $haystack = Str::lower($position . ' ' . $name);

        if (Str::contains($haystack, 'treasurer')) {
            return 'Treasurer';
        }

        if (Str::contains($haystack, 'president')) {
            return 'President';
        }

        return $position ?: 'Finance Approver';
    }

    private function financeApprovalStepForUser(FinanceRecord $record, ?int $userId = null): ?array
    {
        $targetUserId = $userId ?: (int) Auth::id();

        $step = collect((array) data_get($record->data ?? [], 'approval_steps', []))
            ->first(fn (array $step) => (int) ($step['user_id'] ?? 0) === $targetUserId);

        if ($step) {
            return $step;
        }

        return collect($this->financeOfficialApproverDirectory()['default_steps'])
            ->first(fn (array $step) => (int) ($step['user_id'] ?? 0) === $targetUserId);
    }

    private function financeApprovalRoutingDisplayValue(FinanceRecord $record, string $fieldName): string
    {
        $data = $record->data ?? [];
        $stepIndex = $fieldName === 'first_approver_user_id' ? 0 : 1;
        $step = data_get($data, "approval_steps.$stepIndex", []);
        $roleLabels = $this->financeApprovalRoleLabelsForRecord($record);

        if (!is_array($step)) {
            return $this->financePdfValue(data_get($data, $fieldName));
        }

        $userId = data_get($step, 'user_id');
        $role = trim((string) data_get($step, 'role', $roleLabels[$stepIndex] ?? ($fieldName === 'first_approver_user_id' ? 'Treasurer' : 'President')));
        $userName = $this->financeUserDisplayName(is_numeric($userId) ? (int) $userId : null, '');
        $officialName = trim((string) data_get($step, 'official_name', ''));

        if ($userName !== '') {
            return $role !== '' ? $userName . ' (' . $role . ')' : $userName;
        }

        if ($officialName !== '') {
            return $role !== '' ? $officialName . ' (' . $role . ')' : $officialName;
        }

        return $role !== '' ? $role : $this->financePdfValue($userId);
    }

    private function financeRecordApproverUserIds(FinanceRecord $record): array
    {
        $approverIds = collect((array) data_get($record->data ?? [], 'approval_steps', []))
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (!empty($approverIds) || !$this->moduleRequiresTwoPersonApproval($record->module_key)) {
            return $approverIds;
        }

        return collect($this->financeOfficialApproverDirectory()['default_steps'])
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function currentUserIsFinanceApprover(FinanceRecord $record): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        $userId = (int) $user->id;
        $email = Str::lower(trim((string) $user->email));
        $approverIds = $this->financeRecordApproverUserIds($record);

        if (in_array($userId, $approverIds, true)) {
            return true;
        }

        return collect($this->financeOfficialApproverDirectory()['options'])
            ->contains(function (array $option) use ($userId, $email): bool {
                $role = Str::lower(trim((string) data_get($option, 'role', '')));

                return in_array($role, ['treasurer', 'president', 'approver'], true)
                    && (
                        (int) data_get($option, 'user_id', 0) === $userId
                        || ($email !== '' && Str::lower(trim((string) data_get($option, 'user_email', ''))) === $email)
                    );
            });
    }

    private function currentUserIsDefaultFinanceApprover(): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        $email = Str::lower(trim((string) $user->email));
        $userId = (int) $user->id;

        return collect($this->financeOfficialApproverDirectory()['default_steps'])
            ->contains(function (array $step) use ($userId, $email): bool {
                return (int) data_get($step, 'user_id', 0) === $userId
                    || ($email !== '' && Str::lower(trim((string) data_get($step, 'user_email', ''))) === $email);
            });
    }

    private function currentUserHasFinanceWideAccess(): bool
    {
        return $this->canAdministerFinance();
    }

    private function currentUserCanOpenFinance(): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        if ($this->currentUserHasFinanceWideAccess()) {
            return true;
        }

        if (
            $user->hasPermission('access_finance')
            || $user->hasPermission('create_finance')
            || $user->hasPermission('approve_finance')
            || $this->currentUserIsDefaultFinanceApprover()
        ) {
            return true;
        }

        return collect($this->financeModulePermissionMap())
            ->contains(fn (string $permission) => $user->hasPermission($permission));
    }

    private function currentUserCanAccessFinanceModule(string $moduleKey): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        if ($this->currentUserHasFinanceWideAccess()) {
            return true;
        }

        $permission = $this->financeModulePermissionMap()[$moduleKey] ?? null;

        return filled($permission) && $user->hasPermission($permission);
    }

    private function canViewFinanceRecord(FinanceRecord $record): bool
    {
        return $this->currentUserHasFinanceWideAccess()
            || ($this->currentUserCanAccessFinanceModule($record->module_key) && (int) $record->submitted_by === (int) Auth::id())
            || $this->currentUserOwnsFinanceRevisionRequest($record)
            || $this->currentUserIsFinanceApprover($record);
    }

    private function financeRequesterUserId(FinanceRecord $record): ?int
    {
        if (! Schema::hasTable('employees')) {
            return null;
        }

        if ((string) data_get($record->data ?? [], 'requester_mode', 'own_request') !== 'request_for_another') {
            return null;
        }

        $employeeId = data_get($record->data ?? [], 'requester_employee_id');

        if (blank($employeeId)) {
            return null;
        }

        static $requesterUserIds = [];

        $employeeId = (int) $employeeId;

        if (array_key_exists($employeeId, $requesterUserIds)) {
            return $requesterUserIds[$employeeId];
        }

        $employee = Employee::query()
            ->select(['id', 'user_id'])
            ->find($employeeId);

        if (! $employee || blank($employee->user_id)) {
            $requesterUserIds[$employeeId] = null;
            return null;
        }

        return $requesterUserIds[$employeeId] = (int) $employee->user_id;
    }

    private function currentUserOwnsFinanceRevisionRequest(FinanceRecord $record): bool
    {
        if (! Auth::check()) {
            return false;
        }

        if (! $this->currentUserCanAccessFinanceModule($record->module_key)) {
            return false;
        }

        if (($record->workflow_status ?? 'Uploaded') !== 'Reverted') {
            return false;
        }

        return (int) ($this->financeRequesterUserId($record) ?? 0) === (int) Auth::id();
    }

    private function currentUserHasApprovedFinanceRecord(FinanceRecord $record): bool
    {
        $userId = Auth::id();

        return collect($this->financeApprovalActions($record))
            ->contains(fn (array $action) => (int) data_get($action, 'approved_by') === (int) $userId);
    }

    private function canAdministerFinance(): bool
    {
        $user = Auth::user();

        return $user
            && ($user->isSuperAdmin() || $user->isAdmin() || $user->hasPermission('manage_users'));
    }

    private function canManageFinanceSettings(): bool
    {
        return $this->canAdministerFinance();
    }

    private function financeNoteVisibilityOptions(): array
    {
        return [
            ['value' => 'all', 'label' => 'All Finance Viewers'],
            ['value' => 'approvers', 'label' => 'All Approvers'],
            ['value' => 'president', 'label' => 'President Only'],
            ['value' => 'treasurer', 'label' => 'Treasurer Only'],
            ['value' => 'president_treasurer', 'label' => 'President + Treasurer'],
            ['value' => 'admins', 'label' => 'Admins Only'],
        ];
    }

    private function financeNoteVisibilityLabel(string $visibility): string
    {
        $normalized = Str::lower(trim($visibility));

        return collect($this->financeNoteVisibilityOptions())
            ->firstWhere('value', $normalized)['label']
            ?? 'Finance Viewers';
    }

    private function financeCurrentUserAuthorityRoles(): array
    {
        $roles = [];
        $user = Auth::user();

        if (! $user) {
            return $roles;
        }

        if ($this->canAdministerFinance()) {
            $roles[] = 'admin';
        }

        if ($this->canApproveFinance() || $this->currentUserIsDefaultFinanceApprover()) {
            $roles[] = 'approver';
        }

        $email = Str::lower(trim((string) $user->email));
        $userId = (int) $user->id;

        foreach ($this->financeOfficialApproverDirectory()['options'] as $option) {
            if (
                (int) data_get($option, 'user_id', 0) === $userId
                || Str::lower(trim((string) data_get($option, 'user_email', ''))) === $email
            ) {
                $roles[] = Str::lower(trim((string) data_get($option, 'role', '')));
            }
        }

        return collect($roles)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function financeCurrentUserCanAddNote(FinanceRecord $record): bool
    {
        return $this->canViewFinanceRecord($record)
            && (
                $this->canApproveFinance()
                || $this->canEditRecord($record)
                || $this->canSubmitRecord($record)
                || $this->canApproveSubmittedFinanceRecord($record)
                || $this->canRevertSubmittedFinanceRecord($record)
                || $this->canHoldSubmittedFinanceRecord($record)
                || $this->canArchiveFinanceRecord($record)
                || $this->canRequestDeleteRecord($record)
                || $this->canShareSupplierRecord($record)
            );
    }

    private function financeNoteVisibilityAllowsUser(string $visibility, array $userRoles): bool
    {
        $normalized = Str::lower(trim($visibility));

        if ($normalized === '' || $normalized === 'all') {
            return true;
        }

        if (in_array('admin', $userRoles, true)) {
            return true;
        }

        return match ($normalized) {
            'approvers' => in_array('approver', $userRoles, true) || in_array('president', $userRoles, true) || in_array('treasurer', $userRoles, true),
            'president' => in_array('president', $userRoles, true),
            'treasurer' => in_array('treasurer', $userRoles, true),
            'president_treasurer' => in_array('president', $userRoles, true) || in_array('treasurer', $userRoles, true),
            'admins' => false,
            default => false,
        };
    }

    private function financeVisibleNotesForRecord(FinanceRecord $record): array
    {
        $userRoles = $this->financeCurrentUserAuthorityRoles();
        $notes = collect((array) data_get($record->data ?? [], 'finance_notes', []))
            ->filter(fn ($note) => is_array($note))
            ->map(function (array $note) use ($userRoles): ?array {
                $visibility = Str::lower(trim((string) data_get($note, 'visibility', 'all')));
                $authorId = (int) data_get($note, 'author_user_id', 0);
                $currentUserId = (int) Auth::id();

                if ($authorId !== $currentUserId && ! $this->financeNoteVisibilityAllowsUser($visibility, $userRoles)) {
                    return null;
                }

                $body = trim((string) data_get($note, 'note', data_get($note, 'body', '')));

                if ($body === '') {
                    return null;
                }

                return [
                    'id' => (string) data_get($note, 'id', uniqid('note_', true)),
                    'note' => $body,
                    'visibility' => $visibility ?: 'all',
                    'visibility_label' => $this->financeNoteVisibilityLabel($visibility ?: 'all'),
                    'author_name' => trim((string) data_get($note, 'author_name', data_get($note, 'created_by_name', 'System'))) ?: 'System',
                    'author_email' => trim((string) data_get($note, 'author_email', '')),
                    'created_at' => trim((string) data_get($note, 'created_at', data_get($note, 'created_on', ''))),
                    'source' => trim((string) data_get($note, 'source', 'manual')) ?: 'manual',
                ];
            })
            ->filter()
            ->values()
            ->all();

        $workflowNote = trim((string) ($record->review_note ?? ''));

        if ($workflowNote !== '') {
            array_unshift($notes, [
                'id' => 'workflow-note',
                'note' => $workflowNote,
                'visibility' => 'all',
                'visibility_label' => 'Workflow Note',
                'author_name' => $this->financeUserDisplayName($record->approved_by ?: $record->submitted_by, 'Finance Team'),
                'author_email' => '',
                'created_at' => optional($record->approved_at ?: $record->submitted_at ?: $record->updated_at)?->format('Y-m-d H:i:s') ?: '',
                'source' => 'workflow',
            ]);
        }

        return $notes;
    }

    private function financeDropdownSettings(): array
    {
        static $cached = null;
        static $resolved = false;

        if ($resolved) {
            return $cached ?? [];
        }

        if (
            !Schema::hasTable('settings')
            || !Schema::hasColumn('settings', 'key')
            || !Schema::hasColumn('settings', 'value')
        ) {
            $resolved = true;
            $cached = [];
            return [];
        }

        $setting = Setting::query()
            ->where('key', self::DROPDOWN_SETTINGS_KEY)
            ->first();

        if (!$setting || blank($setting->value)) {
            $resolved = true;
            $cached = [];
            return [];
        }

        $decoded = json_decode((string) $setting->value, true);

        $cached = is_array($decoded) ? $decoded : [];
        $resolved = true;

        return $cached;
    }

    private function financeDvApprovalAllowedWithInsufficientFunds(): bool
    {
        if (
            !Schema::hasTable('settings')
            || !Schema::hasColumn('settings', 'key')
            || !Schema::hasColumn('settings', 'value')
        ) {
            return false;
        }

        $setting = Setting::query()
            ->where('key', self::DV_ALLOW_INSUFFICIENT_FUNDS_APPROVAL_SETTINGS_KEY)
            ->first();

        if (!$setting || blank($setting->value)) {
            return false;
        }

        $decoded = json_decode((string) $setting->value, true);

        if (is_array($decoded) && array_key_exists('allow', $decoded)) {
            return filter_var($decoded['allow'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
        }

        return filter_var($setting->value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
    }

    private function financeDefaultAttachmentTypes(): array
    {
        return [
            ['label' => 'Supporting Document', 'value' => 'Supporting Document', 'active' => true, 'hidden' => false],
            ['label' => 'Purchase Order', 'value' => 'Purchase Order', 'active' => true, 'hidden' => false],
            ['label' => 'Invoice', 'value' => 'Invoice', 'active' => true, 'hidden' => false],
            ['label' => 'Official Receipt', 'value' => 'Official Receipt', 'active' => true, 'hidden' => false],
            ['label' => 'Billing Statement', 'value' => 'Billing Statement', 'active' => true, 'hidden' => false],
            ['label' => 'Contract', 'value' => 'Contract', 'active' => true, 'hidden' => false],
            ['label' => 'Quotation', 'value' => 'Quotation', 'active' => true, 'hidden' => false],
            ['label' => 'Payroll Register', 'value' => 'Payroll Register', 'active' => true, 'hidden' => false],
            ['label' => 'Liquidation Report', 'value' => 'Liquidation Report', 'active' => true, 'hidden' => false],
            ['label' => 'Deposit Slip', 'value' => 'Deposit Slip', 'active' => true, 'hidden' => false],
            ['label' => 'Bank Confirmation', 'value' => 'Bank Confirmation', 'active' => true, 'hidden' => false],
            ['label' => 'Proof of Transfer', 'value' => 'Proof of Transfer', 'active' => true, 'hidden' => false],
            ['label' => 'OR', 'value' => 'OR', 'active' => true, 'hidden' => true],
            ['label' => 'DR', 'value' => 'DR', 'active' => true, 'hidden' => true],
        ];
    }

    private function sanitizeFinanceAttachmentTypes(array $types): array
    {
        $normalized = [];
        $seen = [];

        foreach ($types as $type) {
            if (!is_array($type)) {
                $type = ['label' => (string) $type, 'value' => (string) $type];
            }

            $label = trim((string) ($type['label'] ?? $type['value'] ?? ''));
            $value = trim((string) ($type['value'] ?? $type['label'] ?? ''));

            if ($label === '' && $value === '') {
                continue;
            }

            $label = $label !== '' ? Str::limit($label, 255, '') : $value;
            $value = $value !== '' ? Str::limit($value, 255, '') : $label;
            $dedupeKey = Str::lower($value);

            if ($dedupeKey === '' || isset($seen[$dedupeKey])) {
                continue;
            }

            $status = Str::lower(trim((string) ($type['status'] ?? '')));
            $visibility = Str::lower(trim((string) ($type['visibility'] ?? '')));
            $active = array_key_exists('active', $type)
                ? filter_var($type['active'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
                : ! in_array($status, ['inactive', 'disabled', 'off'], true);
            $hidden = array_key_exists('hidden', $type)
                ? filter_var($type['hidden'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
                : in_array($visibility, ['hidden', 'hide', 'off'], true);

            $seen[$dedupeKey] = true;
            $normalized[] = [
                'label' => $label,
                'value' => $value,
                'active' => $active !== false,
                'hidden' => $hidden === true,
            ];
        }

        return $normalized ?: $this->financeDefaultAttachmentTypes();
    }

    private function financeAttachmentTypesSettings(): array
    {
        static $cached = null;
        static $resolved = false;

        if ($resolved) {
            return $cached ?? $this->financeDefaultAttachmentTypes();
        }

        if (
            !Schema::hasTable('settings')
            || !Schema::hasColumn('settings', 'key')
            || !Schema::hasColumn('settings', 'value')
        ) {
            $cached = $this->financeDefaultAttachmentTypes();
            $resolved = true;
            return $this->financeDefaultAttachmentTypes();
        }

        $setting = Setting::query()
            ->where('key', self::ATTACHMENT_TYPES_SETTINGS_KEY)
            ->first();

        if (!$setting || blank($setting->value)) {
            $cached = $this->financeDefaultAttachmentTypes();
            $resolved = true;
            return $this->financeDefaultAttachmentTypes();
        }

        $decoded = json_decode((string) $setting->value, true);

        $cached = $this->sanitizeFinanceAttachmentTypes(is_array($decoded) ? $decoded : []);
        $resolved = true;

        return $cached;
    }

    private function financeDefaultLabelOverrides(): array
    {
        return [];
    }

    private function sanitizeFinanceLabelOverrides(array $settings): array
    {
        $moduleKeys = $this->moduleKeys();
        $sanitized = [];

        foreach ($settings as $moduleKey => $moduleConfig) {
            if (!is_string($moduleKey) || !in_array($moduleKey, $moduleKeys, true) || !is_array($moduleConfig)) {
                continue;
            }

            $fieldLabels = [];
            foreach ((array) data_get($moduleConfig, 'field_labels', []) as $fieldName => $fieldLabel) {
                if (!is_string($fieldName) || !preg_match('/^[A-Za-z0-9_]+$/', $fieldName)) {
                    continue;
                }

                $cleanLabel = trim((string) $fieldLabel);
                if ($cleanLabel === '') {
                    continue;
                }

                $fieldLabels[$fieldName] = Str::limit($cleanLabel, 255, '');
            }

            $recordNumberLabel = trim((string) data_get($moduleConfig, 'record_number_label', ''));
            $recordTitleLabel = trim((string) data_get($moduleConfig, 'record_title_label', ''));
            $recordDateLabel = trim((string) data_get($moduleConfig, 'record_date_label', ''));

            $sanitized[$moduleKey] = array_filter([
                'record_number_label' => $recordNumberLabel !== '' ? Str::limit($recordNumberLabel, 255, '') : null,
                'record_title_label' => $recordTitleLabel !== '' ? Str::limit($recordTitleLabel, 255, '') : null,
                'record_date_label' => $recordDateLabel !== '' ? Str::limit($recordDateLabel, 255, '') : null,
                'field_labels' => $fieldLabels,
            ], fn ($value) => $value !== null && $value !== []);
        }

        return $sanitized ?: $this->financeDefaultLabelOverrides();
    }

    private function financeLabelOverridesSettings(): array
    {
        static $cached = null;
        static $resolved = false;

        if ($resolved) {
            return $cached ?? $this->financeDefaultLabelOverrides();
        }

        if (
            !Schema::hasTable('settings')
            || !Schema::hasColumn('settings', 'key')
            || !Schema::hasColumn('settings', 'value')
        ) {
            $cached = $this->financeDefaultLabelOverrides();
            $resolved = true;
            return $this->financeDefaultLabelOverrides();
        }

        $setting = Setting::query()
            ->where('key', self::LABEL_OVERRIDES_SETTINGS_KEY)
            ->first();

        if (!$setting || blank($setting->value)) {
            $cached = $this->financeDefaultLabelOverrides();
            $resolved = true;
            return $this->financeDefaultLabelOverrides();
        }

        $decoded = json_decode((string) $setting->value, true);

        $cached = $this->sanitizeFinanceLabelOverrides(is_array($decoded) ? $decoded : []);
        $resolved = true;

        return $cached;
    }

    private function financeVisibleAttachmentTypes(): array
    {
        return collect($this->financeAttachmentTypesSettings())
            ->filter(fn (array $type) => !blank(data_get($type, 'value')) && data_get($type, 'active', true) && !data_get($type, 'hidden', false))
            ->values()
            ->all();
    }

    private function financeAttachmentTypeValues(): array
    {
        return collect($this->financeAttachmentTypesSettings())
            ->pluck('value')
            ->filter()
            ->map(fn ($value) => (string) $value)
            ->unique(fn (string $value) => Str::lower($value))
            ->values()
            ->all();
    }

    private function financeDefaultAttachmentTypeValue(): string
    {
        return (string) data_get($this->financeVisibleAttachmentTypes(), '0.value', 'Supporting Document');
    }

    private function sanitizeFinanceDropdownSettings(array $settings): array
    {
        $moduleKeys = $this->moduleKeys();
        $sanitized = [];

        foreach ($settings as $moduleKey => $fields) {
            if (!is_string($moduleKey) || !in_array($moduleKey, $moduleKeys, true) || !is_array($fields)) {
                continue;
            }

            foreach ($fields as $fieldName => $options) {
                if (!is_string($fieldName) || !preg_match('/^[A-Za-z0-9_]+$/', $fieldName) || !is_array($options)) {
                    continue;
                }

                $fieldOptions = [];

                foreach ($options as $option) {
                    $value = is_array($option) ? trim((string) ($option['value'] ?? '')) : trim((string) $option);
                    $label = is_array($option) ? trim((string) ($option['label'] ?? $value)) : $value;

                    if ($value === '') {
                        continue;
                    }

                    $fieldOptions[$value] = [
                        'value' => Str::limit($value, 255, ''),
                        'label' => Str::limit($label !== '' ? $label : $value, 255, ''),
                    ];
                }

                $sanitized[$moduleKey][$fieldName] = array_values($fieldOptions);
            }
        }

        return $sanitized;
    }

    private function moduleKeys(): array
    {
        return array_keys(self::MODULES);
    }

    private function accessibleFinanceModuleLabels(): array
    {
        if ($this->currentUserHasFinanceWideAccess()) {
            return self::MODULES;
        }

        if (!$this->currentUserCanOpenFinance()) {
            return [];
        }

        $moduleKeys = collect(array_keys(self::MODULES))
            ->filter(fn (string $moduleKey) => $this->currentUserCanAccessFinanceModule($moduleKey))
            ->unique()
            ->values();

        return $moduleKeys
            ->mapWithKeys(fn (string $moduleKey) => [$moduleKey => self::MODULES[$moduleKey]])
            ->all();
    }

    private function moduleLabel(string $moduleKey): string
    {
        return self::MODULES[$moduleKey] ?? Str::headline($moduleKey);
    }

    private function moduleRecordTitleLabel(string $moduleKey): string
    {
        static $cache = [];

        if (array_key_exists($moduleKey, $cache)) {
            return $cache[$moduleKey];
        }

        $labelOverrides = $this->financeLabelOverridesSettings();
        $override = data_get($labelOverrides, "{$moduleKey}.record_title_label");
        if (filled($override)) {
            return $cache[$moduleKey] = (string) $override;
        }

        return $cache[$moduleKey] = match ($moduleKey) {
            'supplier' => 'Registered Business Name',
            'service' => 'Service Name',
            'product' => 'Product Name',
            'chart_account' => 'Account Name',
            'bank_account' => 'Bank Account Name',
            'pr' => 'Title',
            'po' => 'Order Title',
            'ca' => 'Cash Advance',
            'lr' => 'Liquidating Person',
            'err' => 'Requestor',
            'dv' => 'Payee',
            'pda' => 'Payroll Period',
            'crf' => 'Returnee',
            'ibtf' => 'Transfer Title',
            'arf' => 'Asset Name',
            default => 'Record Name',
        };
    }

    private function moduleRecordNumberLabel(string $moduleKey): string
    {
        static $cache = [];

        if (array_key_exists($moduleKey, $cache)) {
            return $cache[$moduleKey];
        }

        $labelOverrides = $this->financeLabelOverridesSettings();
        $override = data_get($labelOverrides, "{$moduleKey}.record_number_label");
        if (filled($override)) {
            return $cache[$moduleKey] = (string) $override;
        }

        return $cache[$moduleKey] = match ($moduleKey) {
            'supplier' => 'Supplier Code / ID',
            'service' => 'Service Code / Item Code',
            'product' => 'Product Code / Item Code',
            'chart_account' => 'Account Code',
            'bank_account' => 'Account Number',
            'pr' => 'PR Number',
            'po' => 'PO Number',
            'ca' => 'CA Number',
            'lr' => 'LR Number',
            'err' => 'ERR Number',
            'dv' => 'DV Number',
            'pda' => 'PDA Number',
            'crf' => 'CRF Number',
            'ibtf' => 'IBTF Number',
            'arf' => 'ARF Number',
            default => 'Record Number',
        };
    }

    private function moduleRecordDateLabel(string $moduleKey): string
    {
        static $cache = [];

        if (array_key_exists($moduleKey, $cache)) {
            return $cache[$moduleKey];
        }

        $labelOverrides = $this->financeLabelOverridesSettings();
        $override = data_get($labelOverrides, "{$moduleKey}.record_date_label");
        if (filled($override)) {
            return $cache[$moduleKey] = (string) $override;
        }

        return $cache[$moduleKey] = 'Record Date';
    }

    private function moduleFieldLabel(string $moduleKey, string $fieldName, string $defaultLabel): string
    {
        $labelOverrides = $this->financeLabelOverridesSettings();
        $override = data_get($labelOverrides, "{$moduleKey}.field_labels.{$fieldName}");

        return filled($override) ? (string) $override : $defaultLabel;
    }

    private function supplierSelectedCategories(array $data): array
    {
        $categories = data_get($data, 'supplier_category');

        if (is_array($categories)) {
            return array_values(array_filter(array_map(static fn ($value) => trim((string) $value), $categories)));
        }

        $normalized = trim((string) $categories);
        if ($normalized === '') {
            return [];
        }

        return array_values(array_filter(array_map(static fn ($value) => trim((string) $value), preg_split('/[,;|]+/', $normalized) ?: [])));
    }

    private function supplierPreviewFieldVisible(array $data, string $fieldName): bool
    {
        return match ($fieldName) {
            'entity_type_other' => data_get($data, 'entity_type') === 'Others',
            'corporation_type' => in_array(data_get($data, 'entity_type'), ['Corporation', 'One Person Corporation (OPC)', 'Foreign Company'], true),
            'supplier_category_other' => in_array('Others', $this->supplierSelectedCategories($data), true),
            'payment_terms_other' => data_get($data, 'payment_terms') === 'Others',
            'preferred_payment_method_other' => data_get($data, 'preferred_payment_method') === 'Others',
            'online_payment_details' => data_get($data, 'preferred_payment_method') === 'Online Payment',
            'id_type_other' => data_get($data, 'id_type') === 'Others',
            default => true,
        };
    }

    private function conditionalPreviewFieldVisible(array $data, string $fieldName): bool
    {
        if (in_array($fieldName, [
            'entity_type_other',
            'corporation_type',
            'supplier_category_other',
            'payment_terms_other',
            'preferred_payment_method_other',
            'online_payment_details',
            'id_type_other',
        ], true)) {
            return $this->supplierPreviewFieldVisible($data, $fieldName);
        }

        return match ($fieldName) {
            'other_business_purpose_specify' => data_get($data, 'cash_advance_type') === 'Other Business Purpose',
            'other_expense_specify' => in_array('Other Expense', (array) data_get($data, 'usage_categories', []), true),
            default => true,
        };
    }

    private function supplierCompletionLabels(): array
    {
        return [
            'record_number_label' => $this->moduleRecordNumberLabel('supplier'),
            'record_title_label' => $this->moduleRecordTitleLabel('supplier'),
            'record_date_label' => $this->moduleRecordDateLabel('supplier'),
            'trade_name' => $this->moduleFieldLabel('supplier', 'trade_name', 'Trade Name / Brand Name'),
            'entity_type' => $this->moduleFieldLabel('supplier', 'entity_type', 'Entity Type'),
            'entity_type_other' => $this->moduleFieldLabel('supplier', 'entity_type_other', 'Specify Other Entity Type'),
            'corporation_type' => $this->moduleFieldLabel('supplier', 'corporation_type', 'If Corporation, Specify Corporation Type'),
            'registration_number' => $this->moduleFieldLabel('supplier', 'registration_number', 'Registration Number'),
            'tin' => $this->moduleFieldLabel('supplier', 'tin', 'Tax Identification Number (TIN)'),
            'vat_status' => $this->moduleFieldLabel('supplier', 'vat_status', 'VAT Status'),
            'business_permit_number' => $this->moduleFieldLabel('supplier', 'business_permit_number', 'Business Permit Number'),
            'permit_expiry_date' => $this->moduleFieldLabel('supplier', 'permit_expiry_date', 'Permit Expiry Date'),
            'years_in_operation' => $this->moduleFieldLabel('supplier', 'years_in_operation', 'Years in Operation'),
            'nature_of_business' => $this->moduleFieldLabel('supplier', 'nature_of_business', 'Nature of Business'),
            'products_services_offered' => $this->moduleFieldLabel('supplier', 'products_services_offered', 'Products / Services Offered'),
            'supplier_category' => $this->moduleFieldLabel('supplier', 'supplier_category', 'Supplier Category'),
            'supplier_category_other' => $this->moduleFieldLabel('supplier', 'supplier_category_other', 'Specify Other Supplier Category'),
            'registered_address' => $this->moduleFieldLabel('supplier', 'registered_address', 'Registered Address'),
            'office_address' => $this->moduleFieldLabel('supplier', 'office_address', 'Office Address'),
            'warehouse_address' => $this->moduleFieldLabel('supplier', 'warehouse_address', 'Warehouse Address'),
            'billing_address' => $this->moduleFieldLabel('supplier', 'billing_address', 'Billing Address'),
            'telephone_number' => $this->moduleFieldLabel('supplier', 'telephone_number', 'Telephone Number'),
            'mobile_number' => $this->moduleFieldLabel('supplier', 'mobile_number', 'Mobile Number'),
            'email_address' => $this->moduleFieldLabel('supplier', 'email_address', 'Official Email Address'),
            'website_social_media' => $this->moduleFieldLabel('supplier', 'website_social_media', 'Website / Social Media'),
            'representative_full_name' => $this->moduleFieldLabel('supplier', 'representative_full_name', 'Authorized Representative Full Name'),
            'designation' => $this->moduleFieldLabel('supplier', 'designation', 'Authorized Representative Position / Designation'),
            'phone_number' => $this->moduleFieldLabel('supplier', 'phone_number', 'Authorized Representative Mobile Number'),
            'representative_email_address' => $this->moduleFieldLabel('supplier', 'representative_email_address', 'Authorized Representative Email Address'),
            'accounting_contact_person' => $this->moduleFieldLabel('supplier', 'accounting_contact_person', 'Accounting Contact Person'),
            'accounting_contact_number' => $this->moduleFieldLabel('supplier', 'accounting_contact_number', 'Accounting Contact Number'),
            'accounting_email_address' => $this->moduleFieldLabel('supplier', 'accounting_email_address', 'Accounting Email Address'),
            'id_type' => $this->moduleFieldLabel('supplier', 'id_type', 'ID Type'),
            'id_type_other' => $this->moduleFieldLabel('supplier', 'id_type_other', 'Specify Other ID Type'),
            'id_number' => $this->moduleFieldLabel('supplier', 'id_number', 'ID Number'),
            'date_signed' => $this->moduleFieldLabel('supplier', 'date_signed', 'Date Signed'),
            'person_accomplishing_full_name' => $this->moduleFieldLabel('supplier', 'person_accomplishing_full_name', 'Person Accomplishing the Form Full Name'),
            'person_accomplishing_position' => $this->moduleFieldLabel('supplier', 'person_accomplishing_position', 'Position / Designation'),
            'payment_terms' => $this->moduleFieldLabel('supplier', 'payment_terms', 'Payment Terms'),
            'payment_terms_other' => $this->moduleFieldLabel('supplier', 'payment_terms_other', 'Specify Other Payment Terms'),
            'preferred_payment_method' => $this->moduleFieldLabel('supplier', 'preferred_payment_method', 'Preferred Payment Method'),
            'online_payment_details' => $this->moduleFieldLabel('supplier', 'online_payment_details', 'Online Payment Details'),
            'preferred_payment_method_other' => $this->moduleFieldLabel('supplier', 'preferred_payment_method_other', 'Specify Other Payment Method'),
            'bank_name' => $this->moduleFieldLabel('supplier', 'bank_name', 'Bank Name'),
            'bank_branch' => $this->moduleFieldLabel('supplier', 'bank_branch', 'Bank Branch'),
            'bank_account_name' => $this->moduleFieldLabel('supplier', 'bank_account_name', 'Bank Account Name'),
            'bank_account_number' => $this->moduleFieldLabel('supplier', 'bank_account_number', 'Bank Account Number'),
            'swift_code' => $this->moduleFieldLabel('supplier', 'swift_code', 'Swift Code'),
        ];
    }

    private function recordTitleRequiredModules(): array
    {
        return ['supplier', 'service', 'product', 'chart_account', 'bank_account'];
    }

    private function financeAutoRecordTitle(string $moduleKey, array $data, ?string $existingTitle = null): string
    {
        $existingTitle = trim((string) $existingTitle);

        if ($existingTitle !== '' && ! $this->recordTitleLooksLikePlaceholder($moduleKey, $existingTitle)) {
            return $existingTitle;
        }

        if ($moduleKey !== 'ca') {
            return $existingTitle;
        }

        $requestorName = trim((string) (
            data_get($data, 'employee_name')
            ?: data_get($data, 'requested_by_name')
            ?: data_get($data, 'requestor')
            ?: data_get($data, 'employee_email')
        ));

        $purpose = trim((string) data_get($data, 'purpose', ''));
        $cashAdvanceType = trim((string) data_get($data, 'cash_advance_type', ''));

        $derivedTitle = $purpose !== ''
            ? $purpose
            : ($requestorName !== ''
                ? $requestorName . ' Cash Advance'
                : ($cashAdvanceType !== '' ? $cashAdvanceType : 'Cash Advance'));

        return Str::limit($derivedTitle, 255, '');
    }

    private function recordTitleLooksLikePlaceholder(string $moduleKey, ?string $recordTitle): bool
    {
        $title = Str::lower(trim((string) $recordTitle));

        if ($title === '') {
            return false;
        }

        $placeholderTitles = [
            Str::lower($this->moduleRecordTitleLabel($moduleKey)),
            Str::lower($this->moduleLabel($moduleKey)),
        ];

        if ($moduleKey === 'supplier') {
            $placeholderTitles[] = 'supplier completion';
            $placeholderTitles[] = 'supplier name';
        }

        if ($moduleKey === 'pr') {
            $placeholderTitles[] = 'request title';
        }

        return in_array($title, array_unique($placeholderTitles), true);
    }

    private function cleanRecordTitleForDisplay(string $moduleKey, ?string $recordTitle): string
    {
        return $this->recordTitleLooksLikePlaceholder($moduleKey, $recordTitle)
            ? ''
            : trim((string) $recordTitle);
    }

    private function financeModuleShowsRecordTitle(string $moduleKey): bool
    {
        return ! in_array($moduleKey, ['pr', 'err', 'crf', 'ca'], true);
    }

    private function financeHistoryActor(): string
    {
        $user = Auth::user();

        return $user?->name ?: $user?->email ?: 'System';
    }

    private function financeHistoryActorForActions(FinanceRecord $record, array $actions): ?string
    {
        $normalizedActions = collect($actions)
            ->map(fn (string $action) => Str::lower(trim($action)))
            ->filter()
            ->values()
            ->all();

        if ($normalizedActions === []) {
            return null;
        }

        $history = collect((array) data_get($record->data ?? [], 'history', []))
            ->filter(fn ($entry) => is_array($entry))
            ->values();

        $matched = $history->reverse()->first(function (array $entry) use ($normalizedActions): bool {
            return in_array(Str::lower(trim((string) data_get($entry, 'action'))), $normalizedActions, true);
        });

        return trim((string) data_get($matched, 'changed_by', '')) ?: null;
    }

    private function financeOwnershipRequestedByName(FinanceRecord $record): string
    {
        return $this->financeRequestedByNameFromData($record->module_key, $record->data ?? [], $record);
    }

    private function financeRequestedByNameFromData(string $moduleKey, array $data, ?FinanceRecord $record = null): string
    {
        $value = match ($moduleKey) {
            'supplier' => data_get($data, 'supplier_submitted_by_name')
                ?: data_get($data, 'representative_full_name')
                ?: data_get($data, 'trade_name')
                ?: data_get($data, 'company_name'),
            'pr', 'ca', 'lr', 'err', 'crf', 'pda' => data_get($data, 'requestor')
                ?: data_get($data, 'employee_name')
                ?: data_get($data, 'requester_name')
                ?: data_get($data, 'supplier_name'),
            'arf' => data_get($data, 'custodian_name')
                ?: data_get($data, 'supplier_name')
                ?: data_get($data, 'trade_name'),
            default => data_get($data, 'requestor')
                ?: data_get($data, 'employee_name')
                ?: data_get($data, 'supplier_name'),
        };

        if (filled($value)) {
            return trim((string) $value);
        }

        return $record ? $this->financeSubmittedByName($record) : '';
    }

    private function financeOwnershipSummary(FinanceRecord $record): array
    {
        $data = $record->data ?? [];
        $approvedBy = $this->financeApprovalActorNames($record);

        $fields = [
            'Created By' => trim((string) (data_get($data, 'created_by_name') ?: $record->user ?: $this->financeUserDisplayName($record->submitted_by, 'N/A'))),
            'Requested By' => $this->financeOwnershipRequestedByName($record) ?: 'N/A',
            'Submitted By' => $this->financeSubmittedByName($record) ?: 'N/A',
            'Approved By' => $approvedBy ? implode(', ', $approvedBy) : $this->financeUserDisplayName($record->approved_by, 'N/A'),
            'Reverted By' => trim((string) (data_get($data, 'reverted_by_name') ?: $this->financeHistoryActorForActions($record, ['Reverted', 'Delete Rejected']) ?: 'N/A')),
            'Held By' => trim((string) (data_get($data, 'held_by_name') ?: $this->financeHistoryActorForActions($record, ['Placed On Hold']) ?: 'N/A')),
            'Updated By' => trim((string) (data_get($data, 'updated_by_name') ?: $this->financeHistoryActorForActions($record, ['Updated', 'Relationship Status Updated', 'Supplier Email Updated', 'Supplier Submitted']) ?: 'N/A')),
            'Released By' => trim((string) (data_get($data, 'released_by_name') ?: data_get($data, 'release_recorded_by_name') ?: $this->financeHistoryActorForActions($record, ['Released', 'Funds Released', 'Cash Released', 'Payment Released', 'Disbursed']) ?: 'N/A')),
            'Received By' => trim((string) (data_get($data, 'received_by_name') ?: data_get($data, 'custodian_acknowledged_by_name') ?: $this->financeHistoryActorForActions($record, ['Asset Acknowledged', 'Received']) ?: 'N/A')),
            'Liquidated By' => trim((string) (data_get($data, 'liquidated_by_name') ?: ($record->module_key === 'lr' ? $this->financeSubmittedByName($record) : $this->financeHistoryActorForActions($record, ['Liquidated', 'Submitted'])) ?: 'N/A')),
        ];

        return collect($fields)
            ->except(['Submitted By', 'Approved By'])
            ->map(fn (string $value, string $label) => [
                'label' => $label,
                'value' => $value !== '' ? $value : 'N/A',
            ])
            ->values()
            ->all();
    }

    private function financeHistorySnapshot(array $values): array
    {
        $snapshot = [];
        $cleanHistoryValue = function (array $items) use (&$cleanHistoryValue): array {
            $cleaned = [];

            foreach ($items as $key => $value) {
                if (in_array((string) $key, ['history', 'dv_payload', 'data'], true)) {
                    continue;
                }

                $cleaned[$key] = is_array($value) ? $cleanHistoryValue($value) : $value;
            }

            return $cleaned;
        };
        $walk = function (array $items, string $prefix = '') use (&$snapshot, &$walk, $cleanHistoryValue): void {
            foreach ($items as $key => $value) {
                if (in_array((string) $key, ['history', 'dv_payload', 'data'], true)) {
                    continue;
                }

                $field = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

                if (is_array($value)) {
                    $value = $cleanHistoryValue($value);
                    $hasNested = collect($value)->contains(fn ($nested) => is_array($nested));
                    if ($hasNested) {
                        $snapshot[$field] = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    } else {
                        $snapshot[$field] = implode(', ', array_map(fn ($item) => is_bool($item) ? ($item ? 'Yes' : 'No') : (string) $item, $value));
                    }
                    continue;
                }

                $snapshot[$field] = is_bool($value) ? ($value ? 'Yes' : 'No') : (string) ($value ?? '');
            }
        };

        $walk($values);

        return $snapshot;
    }

    private function appendFinanceHistoryEntry(array $data, string $action, string $moduleKey, array $oldValues = [], array $newValues = [], ?string $reason = null): array
    {
        $oldSnapshot = $this->financeHistorySnapshot($oldValues);
        $newSnapshot = $this->financeHistorySnapshot($newValues);
        $fieldNames = array_unique(array_merge(array_keys($oldSnapshot), array_keys($newSnapshot)));
        $changes = [];

        foreach ($fieldNames as $fieldName) {
            $oldValue = $oldSnapshot[$fieldName] ?? '';
            $newValue = $newSnapshot[$fieldName] ?? '';

            if ($fieldName === 'data') {
                continue;
            }

            if ($oldValue === $newValue && $action !== 'Created') {
                continue;
            }

            $changes[] = [
                'field' => $fieldName,
                'old_value' => $action === 'Created' ? null : $oldValue,
                'new_value' => $newValue,
            ];
        }

        $history = array_values((array) data_get($data, 'history', []));
        $history[] = [
            'action' => $action,
            'module' => $moduleKey,
            'changed_by' => $this->financeHistoryActor(),
            'changed_by_id' => Auth::id(),
            'changed_at' => now()->format('Y-m-d H:i:s'),
            'reason' => $reason,
            'changes' => $changes,
        ];

        $data['history'] = $history;

        return $data;
    }

    private function financePdfImageDataUri(string $relativePath): ?string
    {
        $absolutePath = public_path($relativePath);

        if (!is_file($absolutePath)) {
            return null;
        }

        $contents = file_get_contents($absolutePath);
        if ($contents === false) {
            return null;
        }

        $mime = mime_content_type($absolutePath) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }

    private function financeAttachmentUrl(?array $attachment): ?string
    {
        $path = trim((string) data_get($attachment, 'path', ''));

        if ($path === '') {
            return null;
        }

        return route('uploads.show', ['path' => $path]);
    }

    private function financeAttachmentAbsolutePath(?array $attachment): ?string
    {
        $path = trim((string) data_get($attachment, 'path', ''));

        if ($path === '') {
            return null;
        }

        return public_path(ltrim($path, '/\\'));
    }

    private function financeAttachmentIsImage(?array $attachment): bool
    {
        $mime = Str::lower((string) data_get($attachment, 'mime', ''));
        $name = Str::lower((string) data_get($attachment, 'name', data_get($attachment, 'path', '')));

        return str_starts_with($mime, 'image/')
            || Str::endsWith($name, ['.jpg', '.jpeg', '.png', '.gif', '.webp', '.bmp', '.svg']);
    }

    private function financeAttachmentDataUri(?array $attachment): ?string
    {
        if (! $this->financeAttachmentIsImage($attachment)) {
            return null;
        }

        $absolutePath = $this->financeAttachmentAbsolutePath($attachment);

        if (! $absolutePath || ! is_file($absolutePath)) {
            return null;
        }

        $contents = @file_get_contents($absolutePath);

        if ($contents === false) {
            return null;
        }

        $mime = mime_content_type($absolutePath) ?: (string) data_get($attachment, 'mime', 'image/jpeg');

        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }

    private function financePdfLookupLabel(array $lookupOptions, string $moduleKey, mixed $id): ?string
    {
        if (blank($id) || !array_key_exists($moduleKey, $lookupOptions)) {
            $record = FinanceRecord::query()
                ->where('module_key', $moduleKey)
                ->whereKey($id)
                ->first();

            return $record && $this->canViewFinanceRecord($record)
                ? $this->optionLabel($record)
                : null;
        }

        foreach ($lookupOptions[$moduleKey] as $option) {
            if ((string) ($option['id'] ?? '') === (string) $id) {
                return (string) ($option['label'] ?? $option['record_number'] ?? $option['id']);
            }
        }

        $record = FinanceRecord::query()
            ->where('module_key', $moduleKey)
            ->whereKey($id)
            ->first();

        return $record && $this->canViewFinanceRecord($record)
            ? $this->optionLabel($record)
            : null;
    }

    private function financeAttachmentSummary(FinanceRecord $record): array
    {
        $attachments = collect((array) ($record->attachments ?? []))
            ->filter(fn ($attachment) => is_array($attachment))
            ->values();

        $imageCount = $attachments->filter(fn (array $attachment) => $this->financeAttachmentIsImage($attachment))->count();
        $typeSummary = $attachments
            ->map(fn (array $attachment) => trim((string) data_get($attachment, 'category', 'Supporting Document')))
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take(6)
            ->map(fn (int $count, string $category) => $category . ' x ' . $count)
            ->values()
            ->all();

        return [
            'total_count' => $attachments->count(),
            'image_count' => $imageCount,
            'document_count' => max($attachments->count() - $imageCount, 0),
            'type_summary' => $typeSummary,
            'items' => $attachments
                ->map(function (array $attachment): array {
                    return [
                        'name' => trim((string) data_get($attachment, 'name', data_get($attachment, 'path', 'Attachment'))),
                        'category' => trim((string) data_get($attachment, 'category', 'Supporting Document')),
                        'uploaded_by' => trim((string) data_get($attachment, 'uploaded_by', '')),
                        'uploaded_at' => trim((string) data_get($attachment, 'uploaded_at', '')),
                    ];
                })
                ->all(),
        ];
    }

    private function financeApprovalTrailRows(FinanceRecord $record): array
    {
        $data = $record->data ?? [];
        $steps = collect((array) data_get($data, 'approval_steps', []))
            ->filter(fn ($step) => is_array($step))
            ->values();
        $actions = collect((array) data_get($data, 'approval_actions', []))
            ->filter(fn ($action) => is_array($action))
            ->values();

        $rows = $steps->map(function (array $step) use ($actions): array {
            $stepNumber = (int) data_get($step, 'step', 0);
            $stepRole = trim((string) data_get($step, 'role', data_get($step, 'label', 'Approver')));
            $normalizedStepRole = Str::lower($stepRole);
            $userId = (int) data_get($step, 'user_id', 0);

            $matchedAction = $actions->first(function (array $action) use ($stepNumber, $normalizedStepRole, $userId): bool {
                $actionStep = (int) data_get($action, 'step', data_get($action, 'approval_step', 0));
                $actionRole = Str::lower(trim((string) data_get($action, 'approver_role', data_get($action, 'role', data_get($action, 'label', '')))));
                $actionUserId = (int) data_get($action, 'approved_by', 0);

                if ($stepNumber > 0 && $actionStep > 0 && $actionStep === $stepNumber) {
                    return true;
                }

                if ($normalizedStepRole !== '' && $actionRole !== '' && $actionRole === $normalizedStepRole) {
                    return true;
                }

                return $userId > 0 && $actionUserId === $userId;
            });

            return [
                'step' => $stepNumber ?: null,
                'role' => $stepRole ?: 'Approver',
                'approver' => trim((string) data_get($matchedAction, 'approved_by_name', data_get($step, 'user_name', data_get($step, 'official_name', 'Pending')))) ?: 'Pending',
                'approved_at' => trim((string) data_get($matchedAction, 'approved_at', '')) ?: null,
                'status' => $matchedAction ? 'Approved' : 'Pending',
            ];
        })->all();

        if ($rows !== []) {
            return $rows;
        }

        if (filled($record->approved_by) || filled($record->approved_at)) {
            return [[
                'step' => null,
                'role' => 'Final Approval',
                'approver' => $this->financeUserDisplayName($record->approved_by, 'Pending'),
                'approved_at' => optional($record->approved_at)->format('Y-m-d H:i:s') ?: null,
                'status' => $record->approved_at ? 'Approved' : 'Pending',
            ]];
        }

        return [];
    }

    private function financeCompleteDataRows(FinanceRecord $record): array
    {
        $data = array_merge($record->data ?? [], $this->financeLifecycleSnapshot($record));
        $excludedKeys = [
            'history',
            'approval_actions',
            'approval_steps',
            'approval_required_count',
            'approval_completed_count',
            'approval_remaining_count',
            'transaction_progress',
            'attachments',
            'dv_payload',
        ];
        $rows = [];

        $walk = function (array $items, string $prefix = '') use (&$rows, &$walk, $excludedKeys): void {
            foreach ($items as $key => $value) {
                if (in_array((string) $key, $excludedKeys, true)) {
                    continue;
                }

                $field = $prefix === '' ? (string) $key : $prefix . '.' . $key;

                if (is_array($value)) {
                    if ($value === []) {
                        continue;
                    }

                    $hasNested = collect($value)->contains(fn ($nested) => is_array($nested));
                    $display = $hasNested
                        ? json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                        : implode(', ', array_map(function ($item) {
                            if (is_bool($item)) {
                                return $item ? 'Yes' : 'No';
                            }

                            return trim((string) $item);
                        }, $value));

                    if (trim((string) $display) !== '') {
                        $rows[] = [
                            'label' => Str::headline(str_replace(['.', '_'], ' ', $field)),
                            'value' => $display,
                        ];
                    }

                    continue;
                }

                if ($value === null || $value === '') {
                    continue;
                }

                $rows[] = [
                    'label' => Str::headline(str_replace(['.', '_'], ' ', $field)),
                    'value' => $this->financePdfValue($value),
                ];
            }
        };

        $walk($data);

        return array_values($rows);
    }

    private function financePdfValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            $flattened = array_filter(array_map(function ($item) {
                if (is_array($item)) {
                    return json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }

                return blank($item) ? null : (string) $item;
            }, $value));

            return $flattened ? implode(', ', $flattened) : 'N/A';
        }

        $stringValue = trim((string) $value);

        return $stringValue === '' ? 'N/A' : $stringValue;
    }

    private function financeBarcodeSvg(?string $value): string
    {
        $text = trim((string) $value);

        if ($text === '') {
            return '';
        }

        $normalized = strtoupper(preg_replace('/\s+/', '', $text) ?: '');
        if ($normalized === '') {
            return '';
        }

        $seed = 0;
        foreach (str_split($normalized) as $index => $char) {
            $seed += ord($char) * ($index + 3);
        }

        $bits = '1010';
        foreach (str_split($normalized) as $char) {
            $code = ord($char) ^ ($seed & 0xff);
            $bits .= str_pad(decbin($code), 8, '0', STR_PAD_LEFT);
        }
        $bits .= '110101';

        $unit = 2;
        $quietZone = 10;
        $height = 56;
        $textY = 84;
        $cursor = $quietZone;
        $current = $bits[0] ?? '0';
        $runLength = 0;
        $rects = '';

        $flushRun = function () use (&$rects, &$cursor, &$runLength, &$current, $unit, $height) {
            if ($runLength > 0 && $current === '1') {
                $rects .= sprintf(
                    '<rect x="%d" y="8" width="%d" height="%d" fill="#111827" />',
                    $cursor,
                    $runLength * $unit,
                    $height
                );
            }

            $cursor += $runLength * $unit;
            $runLength = 0;
        };

        foreach (str_split($bits) as $bit) {
            if ($bit === $current) {
                $runLength++;
                continue;
            }

            $flushRun();
            $current = $bit;
            $runLength = 1;
        }

        $flushRun();

        $width = max(240, $cursor + $quietZone);
        $safeText = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        $centerX = (int) round($width / 2);

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {$width} 96" role="img" aria-label="Barcode for {$safeText}">
    <rect x="0" y="0" width="{$width}" height="96" rx="10" fill="#ffffff" />
    <rect x="0" y="0" width="{$width}" height="96" rx="10" fill="none" stroke="#e5e7eb" />
    <g>{$rects}</g>
    <text x="{$centerX}" y="{$textY}" text-anchor="middle" font-family="monospace" font-size="11" fill="#111827">{$safeText}</text>
</svg>
SVG;
    }

    private function financePreviewFieldValue(FinanceRecord $record, string $fieldName, array $lookupOptions): string
    {
        $data = $record->data ?? [];
        $value = data_get($data, $fieldName);
        $linkedCaData = [];
        $linkedLrData = [];
        $liquidationLineItemsTotal = 0.0;
        $liquidationCashAdvance = 0.0;
        $liquidationActualExpenses = 0.0;
        $liquidationVariance = 0.0;
        $liquidationVarianceIndicator = '';

        if ($record->module_key === 'lr') {
            static $linkedCaCache = [];
            $linkedCaId = (string) data_get($data, 'linked_ca_id', '');

            if ($linkedCaId !== '') {
                if (!array_key_exists($linkedCaId, $linkedCaCache)) {
                    $linkedCaCache[$linkedCaId] = $this->financeResolveModuleRecord('ca', $linkedCaId)?->data ?? [];
                }

                $linkedCaData = is_array($linkedCaCache[$linkedCaId] ?? null) ? $linkedCaCache[$linkedCaId] : [];
            }

            $liquidationLineItems = array_values(array_filter((array) data_get($data, 'line_items', []), function ($item) {
                return is_array($item) && collect($item)->contains(fn ($value) => !blank($value));
            }));

            foreach ($liquidationLineItems as $item) {
                $liquidationLineItemsTotal += $this->financeLiquidationLineItemTotal($item);
            }

            $liquidationCashAdvance = (float) (data_get($data, 'total_cash_advance') ?: data_get($linkedCaData, 'amount_requested') ?: 0);
            $liquidationActualExpenses = $liquidationLineItemsTotal > 0
                ? $liquidationLineItemsTotal
                : (float) (data_get($data, 'actual_expenses') ?: data_get($data, 'grand_total') ?: $liquidationCashAdvance);
            if ($liquidationLineItemsTotal <= 0 && $liquidationActualExpenses <= 0 && $liquidationCashAdvance > 0) {
                $liquidationActualExpenses = $liquidationCashAdvance;
            }
            $liquidationVariance = $liquidationCashAdvance - $liquidationActualExpenses;
            $liquidationVarianceIndicator = $liquidationVariance > 0 ? 'Overage' : ($liquidationVariance < 0 ? 'Shortage' : 'Balanced');
        }

        if (in_array($record->module_key, ['err', 'crf'], true)) {
            static $linkedLrCache = [];
            $linkedLrId = (string) data_get($data, 'linked_lr_id', '');

            if ($linkedLrId !== '') {
                if (!array_key_exists($linkedLrId, $linkedLrCache)) {
                    $linkedLrCache[$linkedLrId] = $this->financeResolveModuleRecord('lr', $linkedLrId)?->data ?? [];
                }

                $linkedLrData = is_array($linkedLrCache[$linkedLrId] ?? null) ? $linkedLrCache[$linkedLrId] : [];
            }
        }

        if ($fieldName === 'requester_mode') {
            $fallback = data_get($linkedLrData, 'requester_mode')
                ?: data_get($linkedCaData, 'requester_mode')
                ?: 'own_request';

            return match ($value ?: $fallback) {
                'own_request' => 'Own Request',
                'request_for_another' => 'Request for Another',
                default => $this->financePdfValue($value ?: $fallback),
            };
        }

        if (in_array($fieldName, ['completion_mode', 'vat_status', 'accreditation_status', 'tax_type', 'payment_type', 'mode_of_release', 'mode_of_return', 'reimbursement_mode', 'bank_status', 'account_type', 'account_status', 'service_status', 'product_status', 'normal_balance', 'priority', 'request_type', 'release_schedule'], true)) {
            return $this->financePdfValue($value);
        }

        return match ($fieldName) {
            'requester_employee_id' => $this->financePdfLookupLabel($lookupOptions, 'employee', $value) ?: $this->financePdfValue($value),
            'first_approver_user_id', 'second_approver_user_id' => $this->financeApprovalRoutingDisplayValue($record, $fieldName),
            'supplier_id' => $this->financePdfLookupLabel($lookupOptions, 'supplier', $value) ?: $this->financePdfValue($value),
            'coa_id', 'parent_account_id', 'payroll_expense_coa_id', 'asset_coa_id', 'paid_through' => $this->financePdfLookupLabel($lookupOptions, 'chart_account', $value) ?: $this->financePdfValue($value),
            'bank_account_id', 'funding_bank_account_id', 'receiving_bank_account_id', 'source_bank_account_id', 'destination_bank_account_id' => $this->financePdfLookupLabel($lookupOptions, 'bank_account', $value) ?: $this->financePdfValue($value),
            'linked_pr_id' => $this->financePdfLookupLabel($lookupOptions, 'pr', $value) ?: $this->financePdfValue($value),
            'linked_po_id' => $this->financePdfLookupLabel($lookupOptions, 'po', $value) ?: $this->financePdfValue($value),
            'linked_ca_id' => $this->financePdfLookupLabel($lookupOptions, 'ca', $value) ?: $this->financePdfValue($value),
            'linked_lr_id' => $this->financePdfLookupLabel($lookupOptions, 'lr', $value) ?: $this->financePdfValue($value),
            'linked_crf_id' => $this->financePdfLookupLabel($lookupOptions, 'crf', $value) ?: $this->financePdfValue($value),
            'linked_dv_id' => $this->financePdfLookupLabel($lookupOptions, 'dv', $value) ?: $this->financePdfValue($value),
            'payroll_period_id' => $this->financePdfLookupLabel($lookupOptions, 'payroll_period', $value) ?: $this->financePdfValue($value),
            'source_document_id' => $this->financePdfLookupLabel($lookupOptions, (string) data_get($data, 'source_document_type', ''), $value) ?: $this->financePdfValue($value),
            'master_item_id' => $this->financePdfLookupLabel($lookupOptions, (string) data_get($data, 'master_item_type', 'product'), $value) ?: $this->financePdfValue($value),
            'linked_item_id' => $this->financePdfLookupLabel($lookupOptions, (string) data_get($data, 'linked_item_type', 'product'), $value) ?: $this->financePdfValue($value),
            'requester_employee_id' => $this->financePdfLookupLabel($lookupOptions, 'employee', $value)
                ?: $this->financePdfLookupLabel($lookupOptions, 'employee', data_get($linkedLrData, 'requester_employee_id'))
                ?: $this->financePdfLookupLabel($lookupOptions, 'employee', data_get($linkedCaData, 'requester_employee_id'))
                ?: $this->financePdfValue($value ?: data_get($linkedLrData, 'requester_employee_id') ?: data_get($linkedCaData, 'requester_employee_id')),
            'requestor' => $this->financePdfValue($value ?: data_get($linkedLrData, 'requestor') ?: data_get($linkedLrData, 'employee_name') ?: data_get($linkedCaData, 'requestor') ?: data_get($linkedCaData, 'employee_name') ?: data_get($linkedCaData, 'user') ?: ''),
            'employee_id' => $this->financePdfValue($value ?: data_get($linkedLrData, 'employee_id') ?: data_get($linkedCaData, 'employee_id') ?: ''),
            'employee_name' => $this->financePdfValue($value ?: data_get($linkedLrData, 'employee_name') ?: data_get($linkedLrData, 'requestor') ?: data_get($linkedCaData, 'employee_name') ?: data_get($linkedCaData, 'requestor') ?: data_get($linkedCaData, 'user') ?: ''),
            'employee_email' => $this->financePdfValue($value ?: data_get($linkedLrData, 'employee_email') ?: data_get($linkedCaData, 'employee_email') ?: ''),
            'contact_number' => $this->financePdfValue($value ?: data_get($linkedLrData, 'contact_number') ?: data_get($linkedCaData, 'contact_number') ?: ''),
            'position' => $this->financePdfValue($value ?: data_get($linkedLrData, 'position') ?: data_get($linkedCaData, 'position') ?: ''),
            'department' => $this->financePdfValue($value ?: data_get($linkedLrData, 'department') ?: data_get($linkedCaData, 'department') ?: ''),
            'superior' => $this->financePdfValue($value ?: data_get($linkedLrData, 'superior') ?: data_get($linkedCaData, 'superior') ?: ''),
            'superior_email' => $this->financePdfValue($value ?: data_get($linkedLrData, 'superior_email') ?: data_get($linkedCaData, 'superior_email') ?: ''),
            'for_client' => $this->financePdfValue($value ?: data_get($linkedCaData, 'for_client') ?: 'N/A'),
            'client_names' => $this->financePdfLookupLabel($lookupOptions, 'client', $value)
                ?: $this->financePdfLookupLabel($lookupOptions, 'client', data_get($linkedCaData, 'client_names'))
                ?: $this->financePdfValue($value ?: data_get($linkedCaData, 'client_names') ?: 'N/A'),
            'purpose' => $this->financePdfValue($value ?: data_get($linkedLrData, 'purpose') ?: data_get($linkedCaData, 'purpose') ?: data_get($linkedCaData, 'justification') ?: ''),
            'expense_details' => $this->financePdfValue($value ?: data_get($linkedLrData, 'purpose') ?: 'Shortage from linked liquidation report.'),
            'amount' => $record->module_key === 'err'
                ? $this->financePdfValue($value ?: number_format(abs((float) data_get($linkedLrData, 'variance', 0)), 2, '.', ''))
                : $this->financePdfValue($value),
            'amount_returned' => $record->module_key === 'crf'
                ? $this->financePdfValue($value ?: number_format(abs((float) data_get($linkedLrData, 'variance', 0)), 2, '.', ''))
                : $this->financePdfValue($value),
            'actual_expenses' => $record->module_key === 'lr'
                ? $this->financePdfValue(number_format($liquidationActualExpenses, 2, '.', ''))
                : $this->financePdfValue($value),
            'total_cash_advance' => $record->module_key === 'lr'
                ? $this->financePdfValue(number_format($liquidationCashAdvance, 2, '.', ''))
                : $this->financePdfValue($value),
            'grand_total' => $record->module_key === 'lr'
                ? $this->financePdfValue(number_format($liquidationActualExpenses, 2, '.', ''))
                : $this->financePdfValue($value),
            'variance' => $record->module_key === 'lr'
                ? $this->financePdfValue(number_format($liquidationVariance, 2, '.', ''))
                : $this->financePdfValue($value),
            'line_items_total' => $record->module_key === 'lr'
                ? $this->financePdfValue(number_format($liquidationLineItemsTotal, 2, '.', ''))
                : $this->financePdfValue($value),
            'variance_indicator' => $record->module_key === 'lr'
                ? $this->financePdfValue($liquidationVarianceIndicator)
                : $this->financePdfValue($value),
            default => $this->financePdfValue($value),
        };
    }

    private function financePreviewRow(FinanceRecord $record, array $lookupOptions, string $fieldName, ?string $label = null): array
    {
        return [
            'label' => $label ?: Str::headline(str_replace('_id', ' id', $fieldName)),
            'value' => $this->financePreviewFieldValue($record, $fieldName, $lookupOptions),
        ];
    }

    private function financeActiveRecords(): \Illuminate\Support\Collection
    {
        static $cache = null;

        if ($cache === null) {
            $cache = FinanceRecord::query()
                ->where('workflow_status', '!=', 'Deleted')
                ->orderByDesc('id')
                ->get()
                ->values();
        }

        return $cache;
    }

    private function financeActiveRecordById(int $recordId): ?FinanceRecord
    {
        static $cache = null;

        if ($cache === null) {
            $cache = $this->financeActiveRecords()->keyBy(fn (FinanceRecord $record) => (int) $record->id);
        }

        return $cache->get($recordId);
    }

    private function financeActiveRecordsByModule(string $moduleKey): \Illuminate\Support\Collection
    {
        static $cache = [];

        if (!array_key_exists($moduleKey, $cache)) {
            $cache[$moduleKey] = $this->financeActiveRecords()
                ->filter(fn (FinanceRecord $record) => $record->module_key === $moduleKey)
                ->sortByDesc(fn (FinanceRecord $record) => optional($record->created_at)->getTimestamp() ?: 0)
                ->values();
        }

        return $cache[$moduleKey];
    }

    private function financeResolveModuleRecord(string $moduleKey, mixed $recordId): ?FinanceRecord
    {
        if (blank($recordId) || !is_numeric($recordId)) {
            return null;
        }

        $record = $this->financeActiveRecordById((int) $recordId);

        return $record && $record->module_key === $moduleKey ? $record : null;
    }

    private function financeResolveSupplierRecord(mixed $recordId): ?FinanceRecord
    {
        return $this->financeResolveModuleRecord('supplier', $recordId);
    }

    private function financeSupplierDisplayName(?FinanceRecord $record): string
    {
        if (! $record) {
            return '';
        }

        return trim((string) (
            data_get($record->data ?? [], 'business_name')
            ?: data_get($record->data ?? [], 'supplier_name')
            ?: data_get($record->data ?? [], 'trade_name')
            ?: data_get($record->data ?? [], 'representative_full_name')
            ?: data_get($record->data ?? [], 'company_name')
            ?: $record->record_title
            ?: ''));
    }

    private function financeSupplierReferenceData(?FinanceRecord $record): array
    {
        if (! $record) {
            return [];
        }

        $data = $record->data ?? [];
        $supplierName = $this->financeSupplierDisplayName($record);
        $tradeName = trim((string) (data_get($data, 'trade_name') ?: data_get($data, 'business_name') ?: $supplierName));

        return array_filter([
            'supplier_id' => $record->id,
            'supplier_name' => $supplierName ?: $record->record_title,
            'trade_name' => $tradeName !== '' ? $tradeName : null,
            'supplier_email' => data_get($data, 'email_address'),
            'supplier_phone' => data_get($data, 'phone_number'),
            'supplier_contact_name' => data_get($data, 'representative_full_name'),
            'company_name' => data_get($data, 'company_name') ?: data_get($data, 'business_name') ?: $supplierName,
        ], fn ($value) => ! blank($value));
    }

    private function financeCompanyReferenceData(?Company $company): array
    {
        if (! $company) {
            return [];
        }

        $latestBif = $company->latestBif;

        return array_filter([
            'company_id' => $company->id,
            'company_name' => $company->company_name,
            'company_email' => $company->email,
            'company_phone' => $company->phone,
            'company_website' => $company->website,
            'company_address' => $company->address,
            'company_owner_name' => $company->owner_name,
            'company_primary_contact_id' => $company->primary_contact_id,
            'company_bif_id' => $latestBif?->id,
        ], fn ($value) => ! blank($value));
    }

    private function financeHydrateReferenceData(string $moduleKey, array $data): array
    {
        if ($moduleKey === 'supplier') {
            $companyId = data_get($data, 'company_id');
            if (filled($companyId) && Schema::hasTable('companies')) {
                $company = Company::query()->with('latestBif')->find((int) $companyId);
                if ($company) {
                    $data = array_replace($data, $this->financeCompanyReferenceData($company));
                }
            }
        }

        if ($moduleKey === 'po') {
            $linkedPr = $this->financeResolveModuleRecord('pr', data_get($data, 'linked_pr_id'));
            if ($linkedPr) {
                $data = array_replace($data, array_filter([
                    'supplier_id' => data_get($data, 'supplier_id') ?: data_get($linkedPr->data ?? [], 'supplier_id'),
                    'supplier_name' => data_get($data, 'supplier_name') ?: data_get($linkedPr->data ?? [], 'supplier_name') ?: data_get($linkedPr->data ?? [], 'supplier_name'),
                    'project' => data_get($data, 'project') ?: data_get($linkedPr->data ?? [], 'project') ?: data_get($linkedPr->data ?? [], 'project_name') ?: data_get($linkedPr->data ?? [], 'project_code'),
                    'cost_center' => data_get($data, 'cost_center') ?: data_get($linkedPr->data ?? [], 'cost_center') ?: data_get($linkedPr->data ?? [], 'cost_center_code') ?: data_get($linkedPr->data ?? [], 'cost_center_name'),
                    'trade_name' => data_get($data, 'trade_name') ?: data_get($linkedPr->data ?? [], 'trade_name'),
                    'company_name' => data_get($data, 'company_name') ?: data_get($linkedPr->data ?? [], 'company_name'),
                ], fn ($value) => ! blank($value)));
            }
        }

        if ($moduleKey === 'arf') {
            $linkedPo = $this->financeResolveModuleRecord('po', data_get($data, 'linked_po_id'));
            $linkedDv = $this->financeResolveModuleRecord('dv', data_get($data, 'linked_dv_id'));
            $sourceRecord = $linkedPo ?: $linkedDv;

            if ($sourceRecord) {
                $sourceData = $sourceRecord->data ?? [];
                $data = array_replace($data, array_filter([
                    'supplier_id' => data_get($data, 'supplier_id')
                        ?: data_get($sourceData, 'supplier_id')
                        ?: data_get($sourceData, 'linked_pr_supplier_id'),
                    'asset_coa_id' => data_get($data, 'asset_coa_id')
                        ?: data_get($sourceData, 'coa_id')
                        ?: data_get($sourceData, 'asset_coa_id')
                        ?: data_get($sourceData, 'linked_coa_id'),
                    'acquisition_cost' => data_get($data, 'acquisition_cost')
                        ?: data_get($sourceData, 'total_amount')
                        ?: data_get($sourceData, 'amount')
                        ?: data_get($sourceData, 'acquisition_cost'),
                    'acquisition_date' => data_get($data, 'acquisition_date')
                        ?: optional($sourceRecord->record_date)->format('Y-m-d'),
                    'supplier_name' => data_get($data, 'supplier_name')
                        ?: data_get($sourceData, 'supplier_name')
                        ?: data_get($sourceData, 'trade_name'),
                    'trade_name' => data_get($data, 'trade_name')
                        ?: data_get($sourceData, 'trade_name'),
                ], fn ($value) => ! blank($value)));
            }

            $supplierRecord = $this->financeResolveSupplierRecord(data_get($data, 'supplier_id'));
            if ($supplierRecord) {
                $data = array_replace($data, $this->financeSupplierReferenceData($supplierRecord));
            }
        }

        if ($moduleKey === 'dv') {
            $sourceType = (string) data_get($data, 'source_document_type', '');
            $sourceId = data_get($data, 'source_document_id');
            $sourceRecord = $sourceType && $sourceId
                ? $this->financeResolveModuleRecord($sourceType, $sourceId)
                : null;

            if ($sourceRecord) {
                $sourcePayload = $this->fallbackDvPayload($sourceRecord);
                $data = array_replace($data, array_filter([
                    'supplier_id' => data_get($data, 'supplier_id') ?: data_get($sourcePayload, 'supplier_id'),
                    'bank_account_id' => data_get($data, 'bank_account_id') ?: data_get($sourcePayload, 'bank_account_id'),
                    'payment_type' => data_get($data, 'payment_type') ?: data_get($sourcePayload, 'payment_type'),
                    'disbursement_type' => data_get($data, 'disbursement_type') ?: data_get($sourcePayload, 'disbursement_type'),
                    'payee_type' => data_get($data, 'payee_type') ?: data_get($sourcePayload, 'payee_type'),
                    'payee_name' => data_get($data, 'payee_name') ?: data_get($sourcePayload, 'payee_name'),
                    'coa_id' => data_get($data, 'coa_id') ?: data_get($sourcePayload, 'coa_id'),
                    'fund_source' => data_get($data, 'fund_source') ?: data_get($sourcePayload, 'fund_source'),
                    'department' => data_get($data, 'department') ?: data_get($sourcePayload, 'department'),
                    'purpose' => data_get($data, 'purpose') ?: data_get($sourcePayload, 'purpose'),
                    'payment_date' => data_get($data, 'payment_date') ?: data_get($sourcePayload, 'payment_date'),
                    'due_date' => data_get($data, 'due_date') ?: data_get($sourcePayload, 'due_date'),
                    'withholding_tax' => data_get($data, 'withholding_tax') ?: data_get($sourcePayload, 'withholding_tax'),
                    'vat_amount' => data_get($data, 'vat_amount') ?: data_get($sourcePayload, 'vat_amount'),
                    'currency' => data_get($data, 'currency') ?: data_get($sourcePayload, 'currency'),
                    'exchange_rate' => data_get($data, 'exchange_rate') ?: data_get($sourcePayload, 'exchange_rate'),
                    'received_by_name' => data_get($data, 'received_by_name') ?: data_get($sourcePayload, 'received_by_name'),
                    'date_received' => data_get($data, 'date_received') ?: data_get($sourcePayload, 'date_received'),
                    'reference_number' => data_get($data, 'reference_number') ?: data_get($sourcePayload, 'reference_number'),
                    'remarks' => data_get($data, 'remarks') ?: data_get($sourcePayload, 'remarks'),
                ], fn ($value) => !blank($value)));
            }
        }

        return $data;
    }

    private function financeRecordIsApproved(?FinanceRecord $record): bool
    {
        if (! $record) {
            return false;
        }

        if (($record->workflow_status ?? '') === 'Accepted' || ($record->approval_status ?? '') === 'Approved') {
            return true;
        }

        $data = $record->data ?? [];
        $approvalRequired = (int) data_get($data, 'approval_required_count', $this->financeApprovalThreshold($record->module_key));
        $approvalCompleted = (int) data_get($data, 'approval_completed_count', 0);

        if (
            Str::lower((string) ($record->workflow_status ?? '')) === 'archived'
            && $approvalRequired > 0
            && $approvalCompleted >= $approvalRequired
        ) {
            return true;
        }

        return false;
    }

    private function financeRecordIsReleased(?FinanceRecord $record): bool
    {
        if (!$record) {
            return false;
        }

        if (
            filled(data_get($record->data ?? [], 'released_at'))
            || filled(data_get($record->data ?? [], 'released_by_name'))
            || filled(data_get($record->data ?? [], 'release_recorded_at'))
            || filled(data_get($record->data ?? [], 'release_recorded_by_name'))
        ) {
            return true;
        }

        $status = Str::lower((string) ($record->status ?? ''));
        $workflow = Str::lower((string) ($record->workflow_status ?? ''));
        $relationshipStatus = in_array($record->module_key, ['dv', 'ca'], true)
            ? ''
            : Str::lower($this->financeDerivedRelationshipStatus($record));
        $paymentStatus = Str::lower((string) data_get($record->data ?? [], 'ca_payment_status', data_get($record->data ?? [], 'payment_status')));

        return collect([$status, $workflow, $relationshipStatus, $paymentStatus])
            ->contains(fn ($value) => in_array($value, [
                'released',
                'paid',
                'disbursed',
                'fully released',
                'completed',
                'closed',
            ], true));
    }

    private function financeRecordIsFinalLocked(FinanceRecord $record): bool
    {
        if (in_array($record->workflow_status ?? 'Uploaded', ['Delete Requested', 'Deleted'], true)) {
            return true;
        }

        $status = Str::lower((string) ($record->status ?? ''));
        $relationshipStatus = Str::lower($this->financeDerivedRelationshipStatus($record));

        return collect([$status, $relationshipStatus])
            ->contains(fn ($value) => in_array($value, [
                'completed',
                'paid',
                'disbursed',
                'partially disbursed',
                'fully disbursed',
                'liquidated',
                'closed',
                'correction requested',
                'payroll released',
            ], true));
    }

    private function financeSystemStatusForRecord(FinanceRecord $record): string
    {
        $workflowStatus = Str::lower((string) ($record->workflow_status ?? 'Uploaded'));
        $approvalStatus = Str::lower((string) ($record->approval_status ?? 'Pending'));
        $relationshipStatus = Str::lower($this->financeDerivedRelationshipStatus($record));
        $paymentStatus = Str::lower((string) data_get($record->data ?? [], 'ca_payment_status', data_get($record->data ?? [], 'payment_status')));

        if (in_array($workflowStatus, ['deleted', 'delete requested'], true)) {
            return 'Cancelled';
        }

        if ($workflowStatus === 'correction requested') {
            return 'On Hold';
        }

        if ($workflowStatus === 'on hold' || $approvalStatus === 'on hold') {
            return 'On Hold';
        }

        if ($workflowStatus === 'reverted' || in_array($approvalStatus, ['needs revision', 'reverted'], true)) {
            return 'Reverted';
        }

        if ($workflowStatus === 'uploaded') {
            return 'Draft';
        }

        if ($workflowStatus === 'submitted') {
            return 'Pending Approval';
        }

        if ($workflowStatus === 'partially approved' || $approvalStatus === 'partially approved') {
            return 'Partially Approved';
        }

        if (in_array($relationshipStatus, ['completed', 'payroll released', 'transfer completed'], true) || $workflowStatus === 'archived') {
            return 'Completed';
        }

        if (in_array($relationshipStatus, ['disbursed', 'paid', 'partially disbursed', 'fully disbursed'], true) || in_array($paymentStatus, ['disbursed', 'paid', 'fully disbursed', 'fully released'], true)) {
            return 'Disbursed';
        }

        if (in_array($relationshipStatus, [
            'awaiting disbursement',
            'pending disbursement',
            'approved for payment',
            'approved for release',
            'for payment processing',
            'awaiting disbursement voucher',
        ], true)) {
            return 'Awaiting Disbursement';
        }

        if ($record->module_key === 'pr') {
            return $this->financeRecordIsApproved($record) ? 'Approved' : ($workflowStatus ?: 'Draft');
        }

        if (in_array($record->module_key, ['po', 'ca', 'err', 'pda', 'ibtf'], true)) {
            if ($record->module_key === 'ca' && $this->financeRecordIsReleased($record)) {
                return 'Disbursed';
            }

            return $this->financeRecordIsApproved($record) ? 'Awaiting Disbursement' : ($workflowStatus ?: 'Draft');
        }

        if ($record->module_key === 'dv') {
            if ($this->financeRecordIsReleased($record)) {
                return (string) data_get($record->data ?? [], 'source_document_type') === 'ca'
                    ? 'Disbursed'
                    : 'Completed';
            }

            return $this->financeRecordIsApproved($record) ? 'Approved' : ($workflowStatus ?: 'Draft');
        }

        if (in_array($record->module_key, ['lr', 'arf'], true)) {
            return $this->financeRecordIsApproved($record) ? 'Completed' : ($workflowStatus ?: 'Draft');
        }

        if ($workflowStatus === 'accepted' || $approvalStatus === 'approved') {
            return 'Approved';
        }

        return 'Draft';
    }

    private function financeOwnLinkedRecordIds(FinanceRecord $record): array
    {
        $data = $record->data ?? [];
        $ids = [];

        foreach (['linked_pr_id', 'linked_po_id', 'linked_ca_id', 'linked_lr_id', 'linked_dv_id', 'source_document_id'] as $field) {
            $value = data_get($data, $field);
            if (!blank($value) && is_numeric($value)) {
                $ids[] = (int) $value;
            }
        }

        return array_values(array_unique(array_filter($ids, fn ($id) => $id > 0)));
    }

    private function financeRecordsLinkedTo(FinanceRecord $record): \Illuminate\Support\Collection
    {
        return $this->financeActiveRecords()
            ->filter(fn (FinanceRecord $candidate) => (int) $candidate->id !== (int) $record->id)
            ->filter(fn (FinanceRecord $candidate) => in_array((int) $record->id, $this->financeOwnLinkedRecordIds($candidate), true))
            ->values();
    }

    private function financeLifecycleRecordIds(FinanceRecord $record): array
    {
        $seen = [(int) $record->id => true];
        $queue = [(int) $record->id];
        $guard = 0;

        while ($queue && $guard < 100) {
            $guard++;
            $current = $this->financeActiveRecordById((int) array_shift($queue));

            if (!$current) {
                continue;
            }

            $linkedIds = $this->financeOwnLinkedRecordIds($current);
            $linkedIds = array_merge(
                $linkedIds,
                $this->financeRecordsLinkedTo($current)->pluck('id')->map(fn ($id) => (int) $id)->all()
            );

            foreach (array_unique($linkedIds) as $linkedId) {
                if ($linkedId <= 0 || isset($seen[$linkedId])) {
                    continue;
                }

                $seen[$linkedId] = true;
                $queue[] = $linkedId;
            }
        }

        return array_keys($seen);
    }

    private function financeRelatedRecords(string $moduleKey, callable $filter): \Illuminate\Support\Collection
    {
        return $this->financeActiveRecordsByModule($moduleKey)
            ->filter($filter)
            ->values();
    }

    private function financeFirstRelatedRecord(string $moduleKey, callable $filter): ?FinanceRecord
    {
        return $this->financeRelatedRecords($moduleKey, $filter)->first();
    }

    private function financeFirstDvForSource(string $moduleKey, mixed $recordId): ?FinanceRecord
    {
        return $this->financeFirstRelatedRecord('dv', function (FinanceRecord $record) use ($moduleKey, $recordId) {
            return (string) data_get($record->data ?? [], 'source_document_type') === $moduleKey
                && (string) data_get($record->data ?? [], 'source_document_id') === (string) $recordId;
        });
    }

    private function financeSourceDocumentPayeeSnapshot(FinanceRecord $record): array
    {
        $data = $record->data ?? [];
        $moduleKey = (string) $record->module_key;
        $requestor = trim((string) data_get($data, 'requestor', data_get($data, 'employee_name', '')));
        $representative = trim((string) data_get($data, 'representative_full_name', data_get($data, 'supplier_submitted_by_name', '')));
        $position = Str::lower(trim((string) data_get($data, 'position', data_get($data, 'officer_type', ''))));
        $destinationBankAccount = $this->financeResolveModuleRecord('bank_account', data_get($data, 'destination_bank_account_id'));
        $bankAccount = trim((string) collect([
            data_get($data, 'destination_account_code'),
            $destinationBankAccount?->record_title,
            $destinationBankAccount?->record_number,
            data_get($data, 'destination_bank_account_id'),
        ])->first(fn ($value) => !blank($value)) ?: '');
        $payrollLabel = trim((string) collect([
            data_get($data, 'payroll_period_label'),
            data_get($data, 'payroll_period_name'),
            $record->record_title,
        ])->first(fn ($value) => !blank($value)) ?: '');

        return match ($moduleKey) {
            'po' => [
                'payee_type' => 'Supplier',
                'payee_name' => trim((string) data_get($data, 'supplier_name', data_get($data, 'representative_full_name', ''))),
            ],
            'ca', 'err' => [
                'payee_type' => $representative !== ''
                    ? 'Authorized Representative'
                    : (in_array($position, ['president', 'treasurer', 'secretary', 'vice president', 'vice-president', 'chairperson', 'chairman', 'director'], true) ? 'Officer' : 'Employee'),
                'payee_name' => $representative !== '' ? $representative : ($requestor !== '' ? $requestor : trim((string) data_get($data, 'supplier_name', ''))),
            ],
            'pda' => [
                'payee_type' => 'Payroll Group',
                'payee_name' => $payrollLabel !== '' ? $payrollLabel : trim((string) data_get($data, 'payroll_period_id', '')),
            ],
            'ibtf' => [
                'payee_type' => 'Receiving Bank Account',
                'payee_name' => $bankAccount !== '' ? $bankAccount : trim((string) data_get($data, 'destination_bank_account_id', '')),
            ],
            'crf' => [
                'payee_type' => match ((string) data_get($data, 'mode_of_return', 'Cash')) {
                    'Bank Transfer' => 'Bank Account',
                    'Check' => 'Chart of Account',
                    default => 'Returnee',
                },
                'payee_name' => trim((string) collect([
                    data_get($data, 'mode_of_return') === 'Bank Transfer' ? data_get($data, 'recipient_bank_account') : null,
                    data_get($data, 'requestor'),
                    data_get($data, 'employee_name'),
                ])->first(fn ($value) => !blank($value)) ?: ''),
            ],
            default => [
                'payee_type' => '',
                'payee_name' => trim((string) collect([
                    $requestor,
                    $representative,
                    data_get($data, 'supplier_name', ''),
                ])->first(fn ($value) => !blank($value)) ?: ''),
            ],
        };
    }

    private function financeDvsForSource(string $moduleKey, mixed $recordId): \Illuminate\Support\Collection
    {
        return $this->financeRelatedRecords('dv', function (FinanceRecord $record) use ($moduleKey, $recordId) {
            return (string) data_get($record->data ?? [], 'source_document_type') === $moduleKey
                && (string) data_get($record->data ?? [], 'source_document_id') === (string) $recordId;
        });
    }

    private function financeSourceDocumentAmount(FinanceRecord $record): float
    {
        $data = $record->data ?? [];
        $amountCandidates = match ($record->module_key) {
            'pda' => [
                data_get($data, 'total_payroll_amount'),
                data_get($data, 'amount'),
                $record->amount,
            ],
            'ca' => [
                data_get($data, 'amount_requested'),
                data_get($data, 'total_cash_advance'),
                data_get($data, 'amount'),
                $record->amount,
            ],
            default => [
                data_get($data, 'amount'),
                data_get($data, 'grand_total'),
                data_get($data, 'amount_requested'),
                data_get($data, 'total_cash_advance'),
                data_get($data, 'amount_returned'),
                data_get($data, 'total_payroll_amount'),
                data_get($data, 'acquisition_cost'),
                $record->amount,
            ],
        };

        return (float) (collect($amountCandidates)->first(fn ($value) => ! blank($value)) ?? 0);
    }

    private function financeSourceDisbursementSummary(FinanceRecord $record): array
    {
        $dvs = $this->financeDvsForSource($record->module_key, $record->id);
        $releasedDvs = $dvs->filter(fn (FinanceRecord $dv) => $this->financeRecordIsReleased($dv))->values();
        $approvedDvs = $dvs->filter(fn (FinanceRecord $dv) => $this->financeRecordIsApproved($dv))->values();
        $amount = max($this->financeSourceDocumentAmount($record), 0);
        $totalDisbursed = (float) $releasedDvs->sum(fn (FinanceRecord $dv) => (float) (data_get($dv->data ?? [], 'amount', $dv->amount) ?: $dv->amount ?: 0));
        $remainingBalance = max($amount - $totalDisbursed, 0);
        $percentagePaid = $amount > 0 ? min(($totalDisbursed / $amount) * 100, 100) : 0;
        $releasedCount = $releasedDvs->count();
        $approvedCount = $approvedDvs->count();

        return [
            'source_amount' => number_format($amount, 2, '.', ''),
            'total_disbursed_amount' => number_format($totalDisbursed, 2, '.', ''),
            'remaining_balance' => number_format($remainingBalance, 2, '.', ''),
            'percentage_paid' => number_format($percentagePaid, 2, '.', ''),
            'dv_count' => $dvs->count(),
            'released_dv_count' => $releasedCount,
            'approved_dv_count' => $approvedCount,
            'pending_dv_count' => max($dvs->count() - $approvedCount, 0),
            'pending_release_count' => max($approvedCount - $releasedCount, 0),
            'has_partial_disbursement' => $totalDisbursed > 0 && $remainingBalance > 0,
            'is_fully_disbursed' => $amount > 0 && $remainingBalance <= 0 && $releasedCount > 0,
            'is_partially_disbursed' => $totalDisbursed > 0 && $remainingBalance > 0,
        ];
    }

    private function financeFirstPoForPr(mixed $recordId): ?FinanceRecord
    {
        return $this->financeFirstRelatedRecord('po', fn (FinanceRecord $record) => (string) data_get($record->data ?? [], 'linked_pr_id') === (string) $recordId);
    }

    private function financeFirstLiquidationForCa(mixed $recordId): ?FinanceRecord
    {
        return $this->financeFirstRelatedRecord('lr', fn (FinanceRecord $record) => (string) data_get($record->data ?? [], 'linked_ca_id') === (string) $recordId);
    }

    private function financeFirstCashReturnForLr(mixed $recordId): ?FinanceRecord
    {
        return $this->financeFirstRelatedRecord('crf', fn (FinanceRecord $record) => (string) data_get($record->data ?? [], 'linked_lr_id') === (string) $recordId);
    }

    private function financeFirstErrForLr(mixed $recordId): ?FinanceRecord
    {
        return $this->financeFirstRelatedRecord('err', fn (FinanceRecord $record) => (string) data_get($record->data ?? [], 'linked_lr_id') === (string) $recordId);
    }

    private function financeLifecycleLinkedRecords(FinanceRecord $record): array
    {
        $data = $record->data ?? [];
        $po = match ($record->module_key) {
            'pr' => $this->financeFirstPoForPr($record->id),
            'po' => $record,
            default => $this->financeResolveModuleRecord('po', data_get($data, 'linked_po_id')),
        };
        $pr = match ($record->module_key) {
            'pr' => $record,
            'po' => $this->financeResolveModuleRecord('pr', data_get($data, 'linked_pr_id')),
            default => $po ? $this->financeResolveModuleRecord('pr', data_get($po->data ?? [], 'linked_pr_id')) : null,
        };

        $dv = match ($record->module_key) {
            'dv' => $record,
            'pr' => $po ? $this->financeFirstDvForSource('po', $po->id) : null,
            'po', 'ca', 'err', 'pda', 'ibtf' => $this->financeFirstDvForSource($record->module_key, $record->id),
            'lr', 'arf' => $this->financeResolveModuleRecord('dv', data_get($data, 'linked_dv_id')),
            default => null,
        };

        $ca = $record->module_key === 'ca'
            ? $record
            : $this->financeResolveModuleRecord('ca', data_get($data, 'linked_ca_id'));

        $lr = $record->module_key === 'lr'
            ? $record
            : ($ca ? $this->financeFirstLiquidationForCa($ca->id) : $this->financeResolveModuleRecord('lr', data_get($data, 'linked_lr_id')));
        $crf = $record->module_key === 'crf'
            ? $record
            : ($lr ? $this->financeFirstCashReturnForLr($lr->id) : $this->financeResolveModuleRecord('crf', data_get($data, 'linked_crf_id')));
        $err = $record->module_key === 'err'
            ? $record
            : ($lr ? $this->financeFirstErrForLr($lr->id) : $this->financeResolveModuleRecord('err', data_get($data, 'linked_err_id')));

        $arf = $this->financeFirstRelatedRecord('arf', function (FinanceRecord $candidate) use ($po, $dv) {
            return ($po && (string) data_get($candidate->data ?? [], 'linked_po_id') === (string) $po->id)
                || ($dv && (string) data_get($candidate->data ?? [], 'linked_dv_id') === (string) $dv->id);
        });

        return compact('po', 'pr', 'dv', 'ca', 'lr', 'crf', 'err', 'arf');
    }

    private function financeLifecycleDisbursementSourceRecord(FinanceRecord $record, ?FinanceRecord $po = null): ?FinanceRecord
    {
        return match ($record->module_key) {
            'pr' => $po,
            'po', 'ca', 'err', 'pda', 'ibtf' => $record,
            default => null,
        };
    }

    private function financeConnectedRecordFieldsForModule(string $moduleKey): array
    {
        return match ($moduleKey) {
            'pr' => [
                ['name' => 'linked_po_id', 'label' => 'Linked PO'],
                ['name' => 'linked_dv_id', 'label' => 'Linked DV'],
            ],
            'po' => [
                ['name' => 'linked_pr_id', 'label' => 'Linked PR'],
                ['name' => 'linked_dv_id', 'label' => 'Linked DV'],
                ['name' => 'supplier_id', 'label' => 'Supplier'],
            ],
            'ca' => [
                ['name' => 'linked_dv_id', 'label' => 'Linked DV'],
                ['name' => 'linked_lr_id', 'label' => 'Linked LR'],
                ['name' => 'linked_crf_id', 'label' => 'Linked CRF'],
            ],
            'lr' => [
                ['name' => 'linked_ca_id', 'label' => 'Linked CA'],
                ['name' => 'linked_dv_id', 'label' => 'Linked DV'],
                ['name' => 'linked_crf_id', 'label' => 'Linked CRF'],
            ],
            'err' => [
                ['name' => 'linked_lr_id', 'label' => 'Linked LR'],
                ['name' => 'linked_dv_id', 'label' => 'Linked DV'],
            ],
            'dv' => [
                ['name' => 'source_document_type', 'label' => 'Source Document Type'],
                ['name' => 'source_document_id', 'label' => 'Source Document'],
                ['name' => 'linked_pr_id', 'label' => 'Linked PR'],
                ['name' => 'linked_po_id', 'label' => 'Linked PO'],
                ['name' => 'linked_ca_id', 'label' => 'Linked CA'],
                ['name' => 'linked_lr_id', 'label' => 'Linked LR'],
                ['name' => 'linked_crf_id', 'label' => 'Linked CRF'],
            ],
            'pda' => [
                ['name' => 'payroll_period_id', 'label' => 'Payroll Period'],
                ['name' => 'linked_dv_id', 'label' => 'Linked DV'],
            ],
            'crf' => [
                ['name' => 'linked_lr_id', 'label' => 'Linked LR'],
            ],
            'ibtf' => [
                ['name' => 'linked_dv_id', 'label' => 'Linked DV'],
                ['name' => 'source_bank_account_id', 'label' => 'Source Bank Account'],
                ['name' => 'destination_bank_account_id', 'label' => 'Destination Bank Account'],
            ],
            'arf' => [
                ['name' => 'linked_po_id', 'label' => 'Linked PO'],
                ['name' => 'linked_dv_id', 'label' => 'Linked DV'],
            ],
            default => [],
        };
    }

    private function financeConnectedRecordsSection(FinanceRecord $record, array $lookupOptions): ?array
    {
        $rows = array_values(array_filter(array_map(
            fn (array $field) => $this->financePreviewRow($record, $lookupOptions, $field['name'], $field['label']),
            $this->financeConnectedRecordFieldsForModule($record->module_key)
        ), fn (array $row) => filled(data_get($row, 'value'))));

        if ($rows === []) {
            return null;
        }

        return [
            'type' => 'fields',
            'title' => 'Connected Records',
            'rows' => $rows,
        ];
    }

    private function financeDerivedRelationshipStatus(FinanceRecord $record): string
    {
        if (($record->workflow_status ?? '') === 'Deleted') {
            return 'Cancelled';
        }

        if (($record->workflow_status ?? '') === 'Correction Requested') {
            return 'Correction Requested';
        }

        if (($record->workflow_status ?? '') === 'Uploaded') {
            return 'Draft';
        }

        if (in_array(Str::lower((string) ($record->status ?? '')), ['cancelled', 'inactive'], true)) {
            return 'Cancelled';
        }

        ['po' => $po, 'dv' => $dv, 'ca' => $ca, 'lr' => $lr, 'arf' => $arf] = $this->financeLifecycleLinkedRecords($record);

        if ($record->module_key === 'pr') {
            if (!$this->financeRecordIsApproved($record)) {
                return $record->workflow_status ?: 'Draft';
            }

            if (!$po) {
                return 'Awaiting Purchase Order';
            }

            if (!$this->financeRecordIsApproved($po)) {
                return 'Converted to Purchase Order';
            }

            $disbursement = $this->financeSourceDisbursementSummary($po);

            if (!$dv) {
                return 'Purchase Order Approved';
            }

            if ($disbursement['is_partially_disbursed']) {
                return 'Partially Disbursed';
            }

            if (!$this->financeRecordIsApproved($dv)) {
                return 'For Payment Processing';
            }

            if ($disbursement['is_fully_disbursed'] || $this->financeRecordIsReleased($dv)) {
                return 'Disbursed';
            }

            return 'Approved for Payment';
        }

        if ($record->module_key === 'po') {
            if (!$this->financeRecordIsApproved($record)) {
                return $record->workflow_status ?: 'Draft';
            }

            $disbursement = $this->financeSourceDisbursementSummary($record);

            if (!$dv) {
                return 'Awaiting Disbursement';
            }

            if ($disbursement['is_partially_disbursed']) {
                return 'Partially Disbursed';
            }

            if (!$this->financeRecordIsApproved($dv)) {
                return 'Pending Disbursement';
            }

            if ($disbursement['is_fully_disbursed'] || $this->financeRecordIsReleased($dv)) {
                return 'Disbursed';
            }

            return 'Approved for Payment';
        }

        if ($record->module_key === 'dv') {
            if ($this->financeRecordIsReleased($record)) {
                return 'Disbursed';
            }

            return $this->financeRecordIsApproved($record)
                ? 'Approved'
                : 'Pending Approval';
        }

        if ($record->module_key === 'ca') {
            if (!$this->financeRecordIsApproved($record)) {
                return $record->workflow_status ?: 'Draft';
            }

            $disbursement = $this->financeSourceDisbursementSummary($record);
            $dvCount = (int) data_get($disbursement, 'dv_count', 0);
            $approvedDvCount = (int) data_get($disbursement, 'approved_dv_count', 0);
            $pendingReleaseCount = (int) data_get($disbursement, 'pending_release_count', 0);

            if ($dvCount === 0) {
                return 'Awaiting Disbursement';
            }

            if ($disbursement['is_fully_disbursed']) {
                if (!$lr) {
                    return 'Awaiting Liquidation';
                }

                return $this->financeRecordIsApproved($lr) ? 'Completed' : 'Awaiting Liquidation Approval';
            }

            if ($disbursement['is_partially_disbursed']) {
                return 'Partially Disbursed';
            }

            if ($approvedDvCount === 0) {
                return 'Pending Disbursement';
            }

            if ($pendingReleaseCount > 0) {
                return 'Approved for Release';
            }

            if (!$lr) {
                return 'Awaiting Liquidation';
            }

            return $this->financeRecordIsApproved($lr) ? 'Completed' : 'Awaiting Liquidation Approval';
        }

        if ($record->module_key === 'lr') {
            if (! $this->financeRecordIsApproved($record)) {
                return $record->workflow_status ?: 'Draft';
            }

            $data = $record->data ?? [];
            $linkedCa = filled(data_get($data, 'linked_ca_id'))
                ? $this->financeResolveModuleRecord('ca', data_get($data, 'linked_ca_id'))
                : null;
            $caAmount = (float) (data_get($data, 'total_cash_advance') ?: ($linkedCa?->amount ?: data_get($linkedCa?->data ?? [], 'amount_requested') ?: 0));
            $lineItems = array_values(array_filter((array) data_get($data, 'line_items', []), function ($item) {
                return is_array($item) && collect($item)->contains(fn ($value) => !blank($value));
            }));
            $lineItemsTotal = 0.0;
            foreach ($lineItems as $item) {
                $lineItemsTotal += $this->financeLiquidationLineItemTotal($item);
            }
            $actualExpenses = $lineItemsTotal > 0
                ? $lineItemsTotal
                : (float) (data_get($data, 'actual_expenses') ?: data_get($data, 'grand_total') ?: $caAmount);
            $storedVarianceIndicator = (string) data_get($data, 'variance_indicator', '');
            if ($lineItemsTotal <= 0 && $actualExpenses <= 0 && $caAmount > 0 && ! in_array($storedVarianceIndicator, ['Overage', 'Shortage'], true)) {
                $actualExpenses = $caAmount;
            }
            $variance = $caAmount - $actualExpenses;
            $varianceIndicator = in_array($storedVarianceIndicator, ['Overage', 'Shortage', 'Balanced'], true)
                ? $storedVarianceIndicator
                : ($variance > 0 ? 'Overage' : ($variance < 0 ? 'Shortage' : 'Balanced'));
            if ($varianceIndicator === 'Overage') {
                $crf = $this->financeFirstCashReturnForLr($record->id);
                if ($crf && $this->financeRecordIsApproved($crf)) {
                    return 'Completed';
                }

                return 'Awaiting CRF';
            }

            if ($varianceIndicator === 'Shortage') {
                $err = $this->financeFirstErrForLr($record->id);
                if ($err && $this->financeRecordIsApproved($err)) {
                    return 'Completed';
                }

                return 'Awaiting ERR';
            }

            return 'Completed';
        }

        if (in_array($record->module_key, ['err', 'pda', 'ibtf'], true)) {
            if (!$this->financeRecordIsApproved($record)) {
                return $record->workflow_status ?: 'Draft';
            }

            $disbursement = $this->financeSourceDisbursementSummary($record);

            if (!$dv) {
                return 'Awaiting Disbursement Voucher';
            }

            if ($disbursement['is_partially_disbursed']) {
                return 'Partially Disbursed';
            }

            if (!$this->financeRecordIsApproved($dv)) {
                return 'Pending Disbursement';
            }

            if ($disbursement['is_fully_disbursed']) {
                return match ($record->module_key) {
                    'pda' => 'Payroll Released',
                    'ibtf' => 'Transfer Completed',
                    default => 'Completed',
                };
            }

            return match ($record->module_key) {
                'pda' => $this->financeRecordIsReleased($dv) ? 'Payroll Released' : 'Approved for Payment',
                'ibtf' => $this->financeRecordIsReleased($dv) ? 'Transfer Completed' : 'Approved for Payment',
                default => $this->financeRecordIsReleased($dv) ? 'Completed' : 'Approved for Payment',
            };
        }

        if ($record->module_key === 'crf') {
            $linkedLrApproved = $lr ? $this->financeRecordIsApproved($lr) : false;

            if (! $linkedLrApproved) {
                return 'Awaiting Linked LR Approval';
            }

            if (! $this->financeRecordIsApproved($record)) {
                return in_array(Str::lower((string) ($record->workflow_status ?? '')), ['uploaded', 'submitted'], true)
                    ? 'Submitted'
                    : ($record->workflow_status ?: 'Draft');
            }

            return 'Completed';
        }

        if ($record->module_key === 'arf') {
            return $this->financeRecordIsApproved($record) ? 'Approved' : ($record->workflow_status ?: 'Draft');
        }

        return $this->financeRecordIsApproved($record) ? 'Approved' : ($record->workflow_status ?: 'Draft');
    }

    private function financeSystemRelationshipStatus(FinanceRecord $record): string
    {
        return $this->financeDerivedRelationshipStatus($record);
    }

    private function financeDerivedNextAction(FinanceRecord $record, string $relationshipStatus): string
    {
        $moduleKey = $record->module_key;

        return match (true) {
            $relationshipStatus === 'Cancelled' => 'No further action',
            $relationshipStatus === 'Correction Requested' => 'Review Correction Request',
            $relationshipStatus === 'Completed' => 'No further action',
            $relationshipStatus === 'Partially Disbursed' => 'Create Disbursement Voucher',
            in_array($relationshipStatus, ['Fully Disbursed', 'Disbursed'], true) => match ($moduleKey) {
                'ca' => 'Submit Liquidation Report',
                'po', 'pr' => 'No further action',
                'err', 'pda', 'ibtf' => 'No further action',
                default => 'Continue workflow',
            },
            $moduleKey === 'pr' && $relationshipStatus === 'Awaiting Purchase Order' => 'Create Purchase Order',
            $moduleKey === 'pr' && $relationshipStatus === 'Converted to Purchase Order' => 'Approve Purchase Order',
            $moduleKey === 'pr' && $relationshipStatus === 'Purchase Order Approved' => 'Create Disbursement Voucher',
            $moduleKey === 'pr' && in_array($relationshipStatus, ['For Payment Processing', 'Pending Disbursement'], true) => 'Approve Disbursement Voucher',
            $moduleKey === 'pr' && $relationshipStatus === 'Approved for Payment' => 'Release Funds',
            $moduleKey === 'po' && $relationshipStatus === 'Awaiting Disbursement' => 'Create Disbursement Voucher',
            $moduleKey === 'po' && $relationshipStatus === 'Pending Disbursement' => 'Approve Disbursement Voucher',
            $moduleKey === 'po' && $relationshipStatus === 'Approved for Payment' => 'Release Funds',
            $moduleKey === 'dv' && $relationshipStatus === 'Pending Approval' => 'Approve Disbursement Voucher',
            $moduleKey === 'dv' && in_array($relationshipStatus, ['Approved', 'Approved for Payment'], true) => 'Release Funds',
            $moduleKey === 'dv' && $relationshipStatus === 'Disbursed' => 'No further action',
            $moduleKey === 'ca' && $relationshipStatus === 'Awaiting Disbursement' => 'Create Disbursement Voucher',
            $moduleKey === 'ca' && $relationshipStatus === 'Pending Disbursement' => 'Approve Disbursement Voucher',
            $moduleKey === 'ca' && $relationshipStatus === 'Approved for Release' => 'Release Funds',
            $moduleKey === 'ca' && $relationshipStatus === 'Awaiting Liquidation' => 'Submit Liquidation Report',
            $moduleKey === 'ca' && $relationshipStatus === 'Awaiting Liquidation Approval' => 'Approve Liquidation Report',
            $moduleKey === 'lr' && in_array($relationshipStatus, ['Awaiting Cash Return', 'Awaiting CRF'], true) => 'Create Cash Return Form',
            $moduleKey === 'lr' && $relationshipStatus === 'Awaiting ERR' => 'Create ERR Form',
            $moduleKey === 'lr' && $relationshipStatus === 'Completed' => 'No further action',
            $moduleKey === 'crf' && $relationshipStatus === 'Awaiting Linked LR Approval' => 'Wait for Linked LR Approval',
            $moduleKey === 'crf' && $relationshipStatus === 'Draft' => 'Submit Cash Return Form',
            $moduleKey === 'crf' && $relationshipStatus === 'Submitted' => 'Approve Cash Return Form',
            $moduleKey === 'crf' && $relationshipStatus === 'Completed' => 'No further action',
            in_array($moduleKey, ['err', 'pda', 'ibtf'], true) && $relationshipStatus === 'Awaiting Disbursement Voucher' => 'Create Disbursement Voucher',
            in_array($moduleKey, ['err', 'pda', 'ibtf'], true) && $relationshipStatus === 'Pending Disbursement' => 'Approve Disbursement Voucher',
            $moduleKey === 'pda' && $relationshipStatus === 'Payroll Released' => 'No further action',
            $moduleKey === 'ibtf' && $relationshipStatus === 'Transfer Completed' => 'No further action',
            in_array($moduleKey, ['err', 'pda', 'ibtf'], true) && $relationshipStatus === 'Approved for Payment' => 'Release Funds',
            $relationshipStatus === 'Disbursed' => 'No further action',
            default => 'Continue workflow',
        };
    }

    private function financeProgressTracker(FinanceRecord $record, string $relationshipStatus): array
    {
        $err = null;
        ['dv' => $dv, 'lr' => $lr, 'crf' => $crf, 'err' => $err] = $this->financeLifecycleLinkedRecords($record);

        if ($record->module_key === 'lr') {
            $liquidationSubmitted = filled($record->submitted_at) || $this->financeRecordIsApproved($record);
            $liquidationApproved = $this->financeRecordIsApproved($record);
            $data = $record->data ?? [];
            $linkedCa = filled(data_get($data, 'linked_ca_id'))
                ? $this->financeResolveModuleRecord('ca', data_get($data, 'linked_ca_id'))
                : null;
            $caAmount = (float) (data_get($data, 'total_cash_advance') ?: ($linkedCa?->amount ?: data_get($linkedCa?->data ?? [], 'amount_requested') ?: 0));
            $lineItems = array_values(array_filter((array) data_get($data, 'line_items', []), function ($item) {
                return is_array($item) && collect($item)->contains(fn ($value) => !blank($value));
            }));
            $lineItemsTotal = 0.0;
            foreach ($lineItems as $item) {
                $lineItemsTotal += $this->financeLiquidationLineItemTotal($item);
            }
            $actualExpenses = $lineItemsTotal > 0
                ? $lineItemsTotal
                : (float) (data_get($data, 'actual_expenses') ?: data_get($data, 'grand_total') ?: $caAmount);
            $storedVarianceIndicator = (string) data_get($data, 'variance_indicator', '');
            if ($lineItemsTotal <= 0 && $actualExpenses <= 0 && $caAmount > 0 && ! in_array($storedVarianceIndicator, ['Overage', 'Shortage'], true)) {
                $actualExpenses = $caAmount;
            }
            $variance = $caAmount - $actualExpenses;
            $varianceIndicator = in_array($storedVarianceIndicator, ['Overage', 'Shortage', 'Balanced'], true)
                ? $storedVarianceIndicator
                : ($variance > 0 ? 'Overage' : ($variance < 0 ? 'Shortage' : 'Balanced'));
            $crfRequired = $varianceIndicator === 'Overage';
            $errRequired = $varianceIndicator === 'Shortage';
            $crfCreated = $crfRequired ? (bool) $crf : false;
            $crfApproved = $crfRequired ? ($crf ? $this->financeRecordIsApproved($crf) : false) : false;
            $errCreated = $errRequired ? (bool) $err : false;
            $errApproved = $errRequired ? ($err ? $this->financeRecordIsApproved($err) : false) : false;
            $completed = $liquidationApproved && (!$crfRequired || $crfApproved) && (!$errRequired || $errApproved);

            $steps = [
                ['label' => 'Liquidation Report Submitted', 'completed' => $liquidationSubmitted],
                ['label' => 'Liquidation Report Approved', 'completed' => $liquidationApproved],
            ];

            if ($crfRequired) {
                $steps[] = ['label' => 'CRF Created', 'completed' => $crfCreated];
                $steps[] = ['label' => 'CRF Approved', 'completed' => $crfApproved];
            } elseif ($errRequired) {
                $steps[] = ['label' => 'ERR Created', 'completed' => $errCreated];
                $steps[] = ['label' => 'ERR Approved', 'completed' => $errApproved];
            }

            $steps[] = ['label' => 'Transaction Completed', 'completed' => $completed];

            $firstPending = collect($steps)->search(fn (array $step) => !$step['completed']);

            return array_map(function (array $step, int $index) use ($firstPending) {
                $step['state'] = $step['completed']
                    ? 'completed'
                    : ($firstPending === $index ? 'current' : 'pending');

                return $step;
            }, $steps, array_keys($steps));
        }

        if ($record->module_key === 'arf') {
            $data = $record->data ?? [];
            $sourceRecord = null;
            if (filled(data_get($data, 'linked_po_id'))) {
                $sourceRecord = $this->financeResolveModuleRecord('po', data_get($data, 'linked_po_id'));
            } elseif (filled(data_get($data, 'linked_dv_id'))) {
                $sourceRecord = $this->financeResolveModuleRecord('dv', data_get($data, 'linked_dv_id'));
            }

            $sourceDocumentApproved = $sourceRecord ? $this->financeRecordIsApproved($sourceRecord) : false;
            $assetRegistrationSubmitted = filled($record->submitted_at) || $this->financeRecordIsApproved($record);
            $assetRegistrationApproved = $this->financeRecordIsApproved($record);
            $assetTagReady = filled(data_get($data, 'asset_code')) && filled(data_get($data, 'location'));
            $custodianAssigned = filled(data_get($data, 'custodian'));
            $custodianAcknowledged = ! $custodianAssigned || filled(data_get($data, 'custodian_acknowledged_at'));
            $completed = $sourceDocumentApproved
                && $assetRegistrationSubmitted
                && $assetRegistrationApproved
                && $assetTagReady
                && $custodianAcknowledged;

            $steps = [
                ['label' => 'Source Document Approved', 'completed' => $sourceDocumentApproved],
                ['label' => 'Asset Registration Submitted', 'completed' => $assetRegistrationSubmitted],
                ['label' => 'Asset Registration Approved', 'completed' => $assetRegistrationApproved],
                ['label' => 'Asset Tag Ready', 'completed' => $assetTagReady],
            ];

            if ($custodianAssigned) {
                $steps[] = ['label' => 'Custodian Acknowledged', 'completed' => $custodianAcknowledged];
            }

            $steps[] = ['label' => 'Transaction Completed', 'completed' => $completed];

            $firstPending = collect($steps)->search(fn (array $step) => !$step['completed']);

            return array_map(function (array $step, int $index) use ($firstPending) {
                $step['state'] = $step['completed']
                    ? 'completed'
                    : ($firstPending === $index ? 'current' : 'pending');

                return $step;
            }, $steps, array_keys($steps));
        }

        if ($record->module_key === 'crf') {
            $submitted = filled($record->submitted_at) || $this->financeRecordIsApproved($record);
            $approved = $this->financeRecordIsApproved($record);
            $completed = $approved;

            $steps = [
                ['label' => 'CRF Submitted', 'completed' => $submitted],
                ['label' => 'CRF Approved', 'completed' => $approved],
                ['label' => 'Transaction Completed', 'completed' => $completed],
            ];

            $firstPending = collect($steps)->search(fn (array $step) => !$step['completed']);

            return array_map(function (array $step, int $index) use ($firstPending) {
                $step['state'] = $step['completed']
                    ? 'completed'
                    : ($firstPending === $index ? 'current' : 'pending');

                return $step;
            }, $steps, array_keys($steps));
        }

        $sourceRecord = $record->module_key === 'dv'
            ? $this->financeResolveModuleRecord(
                (string) data_get($record->data ?? [], 'source_document_type', ''),
                data_get($record->data ?? [], 'source_document_id')
            )
            : $record;
        $sourceDocumentApproved = $sourceRecord ? $this->financeRecordIsApproved($sourceRecord) : $this->financeRecordIsApproved($record);
        $dvCreated = $record->module_key === 'dv' || (bool) $dv;
        $dvApproved = $record->module_key === 'dv'
            ? $this->financeRecordIsApproved($record)
            : $this->financeRecordIsApproved($dv);
        $fundsReleased = $record->module_key === 'ca'
            ? $this->financeRecordIsReleased($dv)
            : (
                $this->financeRecordIsReleased($record)
                || $this->financeRecordIsReleased($dv)
            );
        $supportingDocumentsSubmitted = collect([$record, $sourceRecord, $dv, $lr])
            ->filter(fn ($candidate) => $candidate instanceof FinanceRecord)
            ->contains(fn (FinanceRecord $candidate) => !blank((array) ($candidate->attachments ?? [])));
        $completed = in_array($relationshipStatus, ['Completed', 'Disbursed'], true)
            || $relationshipStatus === 'Payroll Released'
            || $relationshipStatus === 'Transfer Completed'
            || in_array(Str::lower((string) ($record->status ?? '')), ['completed', 'paid', 'liquidated', 'closed'], true);

        $steps = [
            ['label' => 'Source Document Approved', 'completed' => $sourceDocumentApproved],
            ['label' => 'DV Created', 'completed' => $dvCreated],
            ['label' => 'DV Approved', 'completed' => $dvApproved],
            ['label' => 'Fund Release', 'completed' => $fundsReleased],
            ['label' => 'Supporting Documents Submitted', 'completed' => $supportingDocumentsSubmitted],
            ['label' => 'Transaction Completed', 'completed' => $completed],
        ];

        $firstPending = collect($steps)->search(fn (array $step) => !$step['completed']);

        return array_map(function (array $step, int $index) use ($firstPending) {
            $step['state'] = $step['completed']
                ? 'completed'
                : ($firstPending === $index ? 'current' : 'pending');

            return $step;
        }, $steps, array_keys($steps));
    }

    private function financeLifecycleSnapshot(FinanceRecord $record): array
    {
        $relationshipStatus = $this->financeDerivedRelationshipStatus($record);
        $err = null;
        ['po' => $po, 'pr' => $pr, 'dv' => $dv, 'ca' => $ca, 'lr' => $lr, 'crf' => $crf, 'err' => $err, 'arf' => $arf] = $this->financeLifecycleLinkedRecords($record);
        $disbursementSource = $this->financeLifecycleDisbursementSourceRecord($record, $po);
        $disbursementSummary = $disbursementSource ? $this->financeSourceDisbursementSummary($disbursementSource) : [];
        $disbursementStatus = data_get($disbursementSummary, 'is_partially_disbursed')
            ? 'Partially Disbursed'
            : (data_get($disbursementSummary, 'is_fully_disbursed') ? 'Fully Disbursed' : 'Awaiting Disbursement');

        if ($disbursementStatus !== 'Fully Disbursed') {
            if ($record->module_key === 'ca' && in_array($relationshipStatus, ['Awaiting Liquidation', 'Completed'], true)) {
                $disbursementStatus = 'Fully Disbursed';
            } elseif (in_array($record->module_key, ['err', 'pda', 'ibtf'], true) && in_array($relationshipStatus, ['Payroll Released', 'Transfer Completed', 'Completed'], true)) {
                $disbursementStatus = 'Fully Disbursed';
            } elseif ($record->module_key === 'po' && $relationshipStatus === 'Fully Disbursed') {
                $disbursementStatus = 'Fully Disbursed';
            }
        }

        return [
            'relationship_status' => $relationshipStatus,
            'next_action' => $this->financeDerivedNextAction($record, $relationshipStatus),
            'transaction_progress' => $this->financeProgressTracker($record, $relationshipStatus),
            'total_disbursed_amount' => data_get($disbursementSummary, 'total_disbursed_amount'),
            'remaining_balance' => data_get($disbursementSummary, 'remaining_balance'),
            'percentage_paid' => data_get($disbursementSummary, 'percentage_paid'),
            'disbursement_status' => $disbursementStatus,
            'dv_count' => data_get($disbursementSummary, 'dv_count', 0),
            'released_dv_count' => data_get($disbursementSummary, 'released_dv_count', 0),
            'approved_dv_count' => data_get($disbursementSummary, 'approved_dv_count', 0),
            'linked_pr_id' => $pr?->id,
            'linked_po_id' => $po?->id,
            'linked_dv_id' => $dv?->id,
            'linked_ca_id' => $ca?->id,
            'linked_lr_id' => $lr?->id,
            'linked_crf_id' => $crf?->id,
            'linked_err_id' => $err?->id,
            'linked_arf_id' => $arf?->id,
            'related_record_ids' => array_values(array_diff($this->financeLifecycleRecordIds($record), [(int) $record->id])),
        ];
    }

    private function syncFinanceRelationshipLifecycle(FinanceRecord $record): void
    {
        $rootRecord = $record->fresh() ?: $record;

        foreach ($this->financeLifecycleRecordIds($rootRecord) as $recordId) {
            $linkedRecord = FinanceRecord::query()->find($recordId);

            if (!$linkedRecord) {
                continue;
            }

            $oldData = $linkedRecord->data ?? [];
            $snapshot = $this->financeLifecycleSnapshot($linkedRecord);
            $snapshotChanged = collect($snapshot)->contains(function ($value, $key) use ($oldData, $linkedRecord) {
                $existingValue = match ($key) {
                    'relationship_status', 'next_action', 'disbursement_status', 'transaction_progress' => $linkedRecord->{$key},
                    default => data_get($oldData, $key),
                };

                return json_encode($existingValue) !== json_encode($value);
            });
            $derivedStatus = $this->financeSystemStatusForRecord($linkedRecord);
            $statusChanged = (string) ($linkedRecord->status ?? '') !== $derivedStatus;
            $hasChanged = $snapshotChanged || $statusChanged;

            if (!$hasChanged) {
                continue;
            }

            $newData = array_merge($oldData, $snapshot);
            $historyAction = $statusChanged
                ? ($snapshotChanged ? 'Status and Relationship Status Updated' : 'Status Updated')
                : 'Relationship Status Updated';

            $newData = $this->appendFinanceHistoryEntry($newData, $historyAction, $linkedRecord->module_key, [
                'status' => $linkedRecord->status,
                'relationship_status' => data_get($oldData, 'relationship_status'),
                'next_action' => data_get($oldData, 'next_action'),
            ], [
                'status' => $derivedStatus,
                'relationship_status' => $snapshot['relationship_status'],
                'next_action' => $snapshot['next_action'],
            ]);

            $updatePayload = [
                'data' => $newData,
                'relationship_status' => $snapshot['relationship_status'] ?? null,
                'next_action' => $snapshot['next_action'] ?? null,
                'disbursement_status' => $snapshot['disbursement_status'] ?? null,
                'transaction_progress' => $snapshot['transaction_progress'] ?? null,
            ];

            if ($statusChanged) {
                $updatePayload['status'] = $derivedStatus;
            }

            $linkedRecord->update($updatePayload);
            if ($linkedRecord->module_key === 'ca' && $snapshot['relationship_status'] === 'Awaiting Liquidation') {
                $this->sendFinanceRecordWorkflowNotification($linkedRecord, 'liquidation_due');
            }
        }
    }

    private function financeLiquidationLineItemTotal(array $item): float
    {
        if (!blank(data_get($item, 'total'))) {
            return (float) data_get($item, 'total', 0);
        }

        $quantity = (float) data_get($item, 'quantity', 0);
        $amount = (float) data_get($item, 'amount', 0);
        $rowSubtotal = (float) data_get($item, 'subtotal', $quantity * $amount);
        $discountPercent = (float) str_replace('%', '', (string) data_get($item, 'discount', '0'));
        $manualDiscount = (float) data_get($item, 'discount_amount', 0);
        $discountAmount = $discountPercent > 0 ? $rowSubtotal * ($discountPercent / 100) : $manualDiscount;
        $shippingAmount = (float) data_get($item, 'shipping_amount', 0);
        $taxAmount = (float) data_get($item, 'tax_amount', 0);
        $whtAmount = (float) data_get($item, 'wht_amount', 0);

        return $rowSubtotal - $discountAmount + $shippingAmount + $taxAmount - $whtAmount;
    }

    private function financeNormalizeDateString(mixed $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function financeLegacyLineItems(FinanceRecord $record): array
    {
        $data = $record->data ?? [];

        $items = match ($record->module_key) {
            'pr' => [$this->financeNormalizeTaxLineItem([
                'item_module' => 'product',
                'item_record_id' => data_get($data, 'master_item_id'),
                'item_id' => data_get($data, 'master_item_id'),
                'description' => data_get($data, 'description_specification'),
                'category' => data_get($data, 'master_item_type'),
                'quantity' => data_get($data, 'quantity'),
                'amount' => data_get($data, 'unit_cost'),
                'subtotal' => data_get($data, 'estimated_total_cost'),
                'discount_amount' => data_get($data, 'discount_amount'),
                'shipping_amount' => data_get($data, 'shipping_amount'),
                'tax_amount' => data_get($data, 'tax_amount'),
                'wht_amount' => data_get($data, 'wht_amount'),
                'total' => data_get($data, 'estimated_total_cost'),
                'supplier_id' => data_get($data, 'supplier_id'),
            ])],
            'po' => [$this->financeNormalizeTaxLineItem([
                'item_module' => data_get($data, 'linked_item_type'),
                'item_record_id' => data_get($data, 'linked_item_id'),
                'item_id' => data_get($data, 'linked_item_id'),
                'description' => data_get($data, 'purpose'),
                'category' => data_get($data, 'linked_item_type'),
                'quantity' => data_get($data, 'quantity'),
                'amount' => data_get($data, 'unit_cost'),
                'subtotal' => data_get($data, 'total_amount'),
                'discount_amount' => data_get($data, 'discount_amount'),
                'shipping_amount' => data_get($data, 'shipping_amount'),
                'tax_amount' => data_get($data, 'tax_amount'),
                'wht_amount' => data_get($data, 'wht_amount'),
                'total' => data_get($data, 'total_amount'),
                'supplier_id' => data_get($data, 'supplier_id'),
            ])],
            default => [],
        };

        return array_values(array_filter($items, function (array $item) {
            return collect($item)->contains(fn ($value) => !blank($value) && $value !== 0 && $value !== '0');
        }));
    }

    private function financeNormalizeTaxType(?string $taxType): string
    {
        $normalized = Str::of((string) $taxType)
            ->trim()
            ->replace(['_', 'â€“'], [' ', '-'])
            ->replaceMatches('/\s+/', ' ')
            ->lower()
            ->toString();

        return match ($normalized) {
            'vat' => 'VAT',
            'expanded withholding tax', 'expanded wht', 'ewt', 'withholding tax' => 'Expanded Withholding Tax',
            'vat exempt', 'vat-exempt', 'tax exempt' => 'VAT Exempt',
            'zero rated', 'zero-rated', 'zero rated transactions' => 'Zero Rated',
            'non-vat', 'non vat', 'non vat transaction' => 'Non-VAT',
            'n/a', 'na', 'not applicable' => 'N/A',
            default => blank($taxType) ? 'N/A' : trim((string) $taxType),
        };
    }

    private function financeNormalizeTaxLineItem(array $item): array
    {
        $quantity = (float) data_get($item, 'quantity', 0);
        $amount = (float) data_get($item, 'amount', 0);
        $subtotal = (float) data_get($item, 'subtotal', $quantity * $amount);
        $discount = (string) data_get($item, 'discount', '0%');
        $discountPercent = (float) str_replace('%', '', $discount);
        $manualDiscount = (float) data_get($item, 'discount_amount', 0);
        $discountAmount = $discountPercent > 0 ? $subtotal * ($discountPercent / 100) : $manualDiscount;
        $shippingAmount = (float) data_get($item, 'shipping_amount', 0);
        $taxType = $this->financeNormalizeTaxType(data_get($item, 'tax_type', 'N/A'));
        $taxableAmount = max($subtotal - $discountAmount, 0);
        $taxAmount = 0.0;
        $whtAmount = 0.0;
        $taxRate = 0.0;
        $whtRate = 0.0;
        $taxImpactLabel = 'No tax impact';

        match ($taxType) {
            'VAT' => (function () use ($taxableAmount, &$taxAmount, &$taxRate, &$taxImpactLabel) {
                $taxRate = 0.12;
                $taxAmount = round($taxableAmount * $taxRate, 2);
                $taxImpactLabel = 'VAT 12%';
            })(),
            'Expanded Withholding Tax' => (function () use ($taxableAmount, &$whtAmount, &$whtRate, &$taxImpactLabel) {
                $whtRate = 0.01;
                $whtAmount = round($taxableAmount * $whtRate, 2);
                $taxImpactLabel = 'Expanded Withholding Tax 1%';
            })(),
            'Non-VAT' => (function () use ($taxableAmount, &$taxAmount, &$taxRate, &$taxImpactLabel) {
                $taxRate = 0.03;
                $taxAmount = round($taxableAmount * $taxRate, 2);
                $taxImpactLabel = 'Non-VAT 3%';
            })(),
            'VAT Exempt' => $taxImpactLabel = 'VAT Exempt',
            'Zero Rated' => $taxImpactLabel = 'Zero Rated',
            default => null,
        };

        $total = round($subtotal - $discountAmount + $shippingAmount + $taxAmount - $whtAmount, 2);

        data_set($item, 'tax_type', $taxType);
        data_set($item, 'subtotal', number_format($subtotal, 2, '.', ''));
        data_set($item, 'discount_amount', number_format($discountAmount, 2, '.', ''));
        data_set($item, 'shipping_amount', number_format($shippingAmount, 2, '.', ''));
        data_set($item, 'taxable_amount', number_format($taxableAmount, 2, '.', ''));
        data_set($item, 'tax_rate', number_format($taxRate * 100, 2, '.', ''));
        data_set($item, 'wht_rate', number_format($whtRate * 100, 2, '.', ''));
        data_set($item, 'tax_amount', number_format($taxAmount, 2, '.', ''));
        data_set($item, 'wht_amount', number_format($whtAmount, 2, '.', ''));
        data_set($item, 'tax_impact_label', $taxImpactLabel);
        data_set($item, 'total', number_format($total, 2, '.', ''));

        return $item;
    }

    private function financeResolvedLineItems(FinanceRecord $record, array $lookupOptions): array
    {
        $data = $record->data ?? [];
        $rawItems = array_values(array_filter(
            (array) data_get($data, 'line_items', []),
            fn ($item) => is_array($item)
        ));

        if (!$rawItems) {
            $rawItems = $this->financeLegacyLineItems($record);
        }

        $lineItems = [];

        foreach ($rawItems as $item) {
            $item = $this->financeNormalizeTaxLineItem($item);
            $itemModule = (string) data_get($item, 'item_module', '');
            if (!in_array($itemModule, ['product', 'service'], true)) {
                $itemModule = $record->module_key === 'po'
                    ? ((string) data_get($item, 'linked_item_type', 'product') ?: 'product')
                    : 'product';
            }

            $itemRecordId = data_get($item, 'item_record_id');
            if (blank($itemRecordId)) {
                $itemRecordId = data_get($item, 'linked_item_id', data_get($item, 'item_id'));
            }

            $itemRecord = $this->financeResolveModuleRecord($itemModule, $itemRecordId);
            $quantity = (float) data_get($item, 'quantity', 0);
            $amount = (float) data_get($item, 'amount', 0);
            $subtotal = (float) data_get($item, 'subtotal', $quantity * $amount);
            $discount = (string) data_get($item, 'discount', '0%');
            $discountAmount = (float) data_get($item, 'discount_amount', 0);
            $shippingAmount = (float) data_get($item, 'shipping_amount', 0);
            $taxType = (string) data_get($item, 'tax_type', 'N/A');
            $taxAmount = (float) data_get($item, 'tax_amount', 0);
            $whtAmount = (float) data_get($item, 'wht_amount', 0);
            $total = (float) data_get($item, 'total', $subtotal - $discountAmount + $shippingAmount + $taxAmount - $whtAmount);
            $supplierId = data_get($item, 'supplier_id')
                ?: data_get($itemRecord?->data, 'supplier_id')
                ?: ($record->module_key === 'pr' ? null : data_get($data, 'supplier_id'));
            $clientId = data_get($item, 'client_id');

            $lineItems[] = [
                'item_module' => $itemModule,
                'item_record_id' => $itemRecordId,
                'item' => $itemRecord?->record_title
                    ?: $this->financePdfLookupLabel($lookupOptions, $itemModule, $itemRecordId)
                    ?: $this->financePdfValue(data_get($item, 'item_id', $itemRecordId)),
                'description' => $this->financePdfValue(data_get($item, 'description')),
                'category' => $this->financePdfValue(data_get($item, 'category') ?: Str::headline($itemModule)),
                'quantity' => $this->financePdfValue(data_get($item, 'quantity')),
                'amount' => $this->financePdfValue(data_get($item, 'amount')),
                'subtotal' => number_format($subtotal, 2),
                'discount' => $this->financePdfValue($discount ?: '0%'),
                'discount_amount' => number_format($discountAmount, 2),
                'shipping_amount' => number_format($shippingAmount, 2),
                'tax_type' => $this->financePdfValue($taxType ?: 'N/A'),
                'tax_amount' => number_format($taxAmount, 2),
                'wht_amount' => number_format($whtAmount, 2),
                'tax_impact_label' => $this->financePdfValue(data_get($item, 'tax_impact_label')),
                'total' => number_format($total, 2),
                'total_value' => $total,
                'supplier_id' => $supplierId,
                'supplier_name' => $this->financePdfValue(data_get($item, 'supplier_name')),
                'supplier_label' => $this->financePdfValue(data_get($item, 'supplier_label'))
                    ?: $this->financePdfValue(data_get($item, 'supplier_name'))
                    ?: $this->financePdfLookupLabel($lookupOptions, 'supplier', $supplierId)
                    ?: $this->financePdfValue($supplierId),
                'client_id' => $clientId,
                'client_label' => blank($clientId) ? '' : (
                    $this->financePdfLookupLabel($lookupOptions, 'client', $clientId)
                    ?: $this->financePdfValue($clientId)
                ),
            ];
        }

        return array_values(array_filter($lineItems, function (array $item) {
            return collect([
                $item['item'] ?? null,
                $item['description'] ?? null,
                $item['quantity'] ?? null,
                $item['amount'] ?? null,
                $item['total'] ?? null,
            ])->contains(fn ($value) => trim((string) $value) !== '' && $value !== 'N/A');
        }));
    }

    private function financePoSupplierGroups(array $lineItems): array
    {
        $groups = [];

        foreach ($lineItems as $item) {
            $groupKey = (string) ($item['supplier_id'] ?: $item['supplier_label'] ?: $item['supplier_name'] ?: 'unspecified');

            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'supplier_id' => $item['supplier_id'] ?: null,
                    'supplier_label' => $item['supplier_label'] ?: $item['supplier_name'] ?: 'Unspecified Supplier',
                    'items' => [],
                    'group_total_value' => 0,
                ];
            }

            $groups[$groupKey]['items'][] = $item;
            $groups[$groupKey]['group_total_value'] += (float) ($item['total_value'] ?? 0);
        }

        return array_values(array_map(function (array $group) {
            $group['items_count'] = count($group['items']);
            $group['group_total'] = number_format((float) $group['group_total_value'], 2);

            return $group;
        }, $groups));
    }

    private function financePreviewItemizationLineItems(FinanceRecord $record, array $lookupOptions): array
    {
        if ($record->module_key === 'dv') {
            $sourceType = (string) data_get($record->data ?? [], 'source_document_type', '');
            $sourceId = data_get($record->data ?? [], 'source_document_id');
            $sourceRecord = $sourceType !== '' && filled($sourceId)
                ? $this->financeResolveModuleRecord($sourceType, $sourceId)
                : null;

            return $sourceRecord
                ? $this->financeResolvedLineItems($sourceRecord, $lookupOptions)
                : [];
        }

        if (in_array($record->module_key, ['err', 'crf'], true)) {
            $linkedLrId = data_get($record->data ?? [], 'linked_lr_id');
            $linkedLr = filled($linkedLrId)
                ? $this->financeResolveModuleRecord('lr', $linkedLrId)
                : null;

            if ($linkedLr) {
                return $this->financeResolvedLineItems($linkedLr, $lookupOptions);
            }
        }

        return $this->financeResolvedLineItems($record, $lookupOptions);
    }

    private function financePreviewSections(FinanceRecord $record, array $lookupOptions, bool $forceSupplierTemplate = false): array
    {
        $moduleKey = $record->module_key;
        $data = $record->data ?? [];
        $isConsumableInventory = $moduleKey === 'arf' && Str::lower((string) data_get($data, 'item_classification')) === 'consumable inventory';
        $notesSection = [
            'type' => 'notes',
            'title' => 'Review Notes',
        ];

        $section = function (string $title, array $fields) use ($record, $lookupOptions): array {
            return [
                'type' => 'fields',
                'title' => $title,
                'rows' => array_map(fn ($field) => is_array($field)
                    ? $this->financePreviewRow($record, $lookupOptions, $field['name'], $field['label'] ?? null)
                    : $this->financePreviewRow($record, $lookupOptions, $field), $fields),
            ];
        };

        return match ($moduleKey) {
            'supplier' => data_get($data, 'completion_mode') === 'send_to_supplier' && blank($record->supplier_completed_at) && ! $forceSupplierTemplate
                ? []
                : [
                    $section('Supplier Profile', array_values(array_filter([
                        ['name' => 'completion_mode', 'label' => 'Completion Mode'],
                        ['name' => 'date_accomplished', 'label' => 'Date Accomplished'],
                        ['name' => 'trade_name', 'label' => 'Trade Name / Brand Name'],
                        ['name' => 'entity_type', 'label' => 'Entity Type'],
                        ['name' => 'entity_type_other', 'label' => 'Specify Other Entity Type'],
                        ['name' => 'corporation_type', 'label' => 'If Corporation, Specify Corporation Type'],
                        ['name' => 'registration_number', 'label' => 'Registration Number'],
                        ['name' => 'tin', 'label' => 'Tax Identification Number (TIN)'],
                    ], fn ($field) => ! isset($field['name']) || $this->conditionalPreviewFieldVisible($data, $field['name'])))),
                $section('Business Details', array_values(array_filter([
                    ['name' => 'vat_status', 'label' => 'VAT Status'],
                    ['name' => 'business_permit_number', 'label' => 'Business Permit Number'],
                    ['name' => 'permit_expiry_date', 'label' => 'Permit Expiry Date'],
                    ['name' => 'nature_of_business', 'label' => 'Nature of Business'],
                    ['name' => 'products_services_offered', 'label' => 'Products / Services Offered'],
                    ['name' => 'supplier_category', 'label' => 'Supplier Category'],
                    ['name' => 'supplier_category_other', 'label' => 'Specify Other Supplier Category'],
                    ['name' => 'years_in_operation', 'label' => 'Years in Operation'],
                ], fn ($field) => ! isset($field['name']) || $this->conditionalPreviewFieldVisible($data, $field['name'])))),
                $section('Addresses & Contacts', [
                    ['name' => 'registered_address', 'label' => 'Registered Address'],
                    ['name' => 'office_address', 'label' => 'Office Address'],
                    ['name' => 'warehouse_address', 'label' => 'Warehouse Address'],
                    ['name' => 'telephone_number', 'label' => 'Telephone Number'],
                    ['name' => 'mobile_number', 'label' => 'Mobile Number'],
                    ['name' => 'email_address', 'label' => 'Official Email Address'],
                    ['name' => 'website_social_media', 'label' => 'Website / Social Media'],
                ]),
                $section('Authorized Representative', [
                    ['name' => 'representative_full_name', 'label' => 'Authorized Representative Full Name'],
                    ['name' => 'designation', 'label' => 'Position / Designation'],
                    ['name' => 'phone_number', 'label' => 'Mobile Number'],
                    ['name' => 'representative_email_address', 'label' => 'Email Address'],
                ]),
                $section('Billing & Payment', array_values(array_filter([
                    ['name' => 'billing_address', 'label' => 'Billing Address'],
                    ['name' => 'accounting_contact_person', 'label' => 'Accounting Contact Person'],
                    ['name' => 'accounting_contact_number', 'label' => 'Accounting Contact Number'],
                    ['name' => 'accounting_email_address', 'label' => 'Accounting Email Address'],
                    ['name' => 'payment_terms', 'label' => 'Payment Terms'],
                    ['name' => 'payment_terms_other', 'label' => 'Specify Other Payment Terms'],
                    ['name' => 'preferred_payment_method', 'label' => 'Preferred Payment Method'],
                    ['name' => 'preferred_payment_method_other', 'label' => 'Specify Other Payment Method'],
                    ['name' => 'online_payment_details', 'label' => 'Online Payment Details'],
                    ['name' => 'bank_name', 'label' => 'Bank Name'],
                    ['name' => 'bank_branch', 'label' => 'Bank Branch'],
                    ['name' => 'bank_account_name', 'label' => 'Bank Account Name'],
                    ['name' => 'bank_account_number', 'label' => 'Bank Account Number'],
                    ['name' => 'swift_code', 'label' => 'Swift Code'],
                ], fn ($field) => ! isset($field['name']) || $this->conditionalPreviewFieldVisible($data, $field['name'])))),
                $section('Acknowledgment', array_values(array_filter([
                    ['name' => 'person_accomplishing_full_name', 'label' => 'Person Accomplishing the Form'],
                    ['name' => 'person_accomplishing_position', 'label' => 'Position / Designation'],
                    ['name' => 'id_type', 'label' => 'ID Type'],
                    ['name' => 'id_type_other', 'label' => 'Specify Other ID Type'],
                    ['name' => 'id_number', 'label' => 'ID Number'],
                    ['name' => 'date_signed', 'label' => 'Date Signed'],
                ], fn ($field) => ! isset($field['name']) || $this->conditionalPreviewFieldVisible($data, $field['name'])))),
                $notesSection,
            ],
            'service' => [
                $section('Service Profile', [
                    ['name' => 'service_description', 'label' => 'Service Description'],
                    ['name' => 'products_services_provided', 'label' => 'Products / Services Provided'],
                    ['name' => 'supplier_id', 'label' => 'Supplier'],
                    ['name' => 'coa_id', 'label' => 'Account'],
                    ['name' => 'category', 'label' => 'Category'],
                    ['name' => 'unit_of_measure', 'label' => 'Unit of Measure'],
                    ['name' => 'default_cost', 'label' => 'Default Cost'],
                ]),
                $section('Classification & Notes', [
                    ['name' => 'tax_type', 'label' => 'Tax Type'],
                    ['name' => 'service_status', 'label' => 'Service Status'],
                    ['name' => 'remarks', 'label' => 'Remarks'],
                ]),
                $notesSection,
            ],
            'product' => [
                $section('Product Profile', [
                    ['name' => 'product_description', 'label' => 'Product Description'],
                    ['name' => 'supplier_id', 'label' => 'Supplier'],
                    ['name' => 'coa_id', 'label' => 'Account'],
                    ['name' => 'category', 'label' => 'Category'],
                    ['name' => 'unit_of_measure', 'label' => 'Unit of Measure'],
                    ['name' => 'default_cost', 'label' => 'Default Cost'],
                ]),
                $section('Classification & Notes', [
                    ['name' => 'tax_type', 'label' => 'Tax Type'],
                    ['name' => 'product_status', 'label' => 'Product Status'],
                    ['name' => 'remarks', 'label' => 'Remarks'],
                ]),
                $notesSection,
            ],
            'chart_account' => [
                $section('Account Profile', [
                    ['name' => 'account_description', 'label' => 'Account Description'],
                    ['name' => 'is_sub_account', 'label' => 'Sub-Account'],
                    ['name' => 'parent_account_id', 'label' => 'Main Account'],
                    ['name' => 'account_type', 'label' => 'Account Type'],
                    ['name' => 'account_group', 'label' => 'Account Group'],
                ]),
                $section('Bank Profile', [
                    ['name' => 'bank_account_name', 'label' => 'Bank Account Name'],
                    ['name' => 'bank_profile', 'label' => 'Bank Profile'],
                    ['name' => 'bank_account_number', 'label' => 'Bank Account Number'],
                ]),
                $section('Balance & Status', [
                    ['name' => 'normal_balance', 'label' => 'Normal Balance'],
                    ['name' => 'account_status', 'label' => 'Status'],
                    ['name' => 'remarks', 'label' => 'Remarks'],
                ]),
                $notesSection,
            ],
            'bank_account' => [
                $section('Bank Profile', [
                    ['name' => 'bank_name', 'label' => 'Bank Name'],
                    ['name' => 'branch', 'label' => 'Branch'],
                    ['name' => 'currency', 'label' => 'Currency'],
                    ['name' => 'account_type', 'label' => 'Account Type'],
                    ['name' => 'bank_account_number', 'label' => 'Bank Account Number'],
                    ['name' => 'bank_status', 'label' => 'Status'],
                ]),
                $section('Accounting Link & Notes', [
                    ['name' => 'linked_coa_id', 'label' => 'Linked Chart of Account'],
                    ['name' => 'signatory_notes', 'label' => 'Signatory Notes'],
                    ['name' => 'remarks', 'label' => 'Remarks'],
                ]),
                $notesSection,
            ],
            'pr' => [
                $section('Request Details', [
                    ['name' => 'priority', 'label' => 'Priority'],
                    ['name' => 'needed_date', 'label' => 'Needed Date'],
                    ['name' => 'for_client', 'label' => 'Is this for a client?'],
                    ['name' => 'pr_reason_categories', 'label' => 'Reason (tick all that apply)'],
                ]),
                $section('Requester Details', [
                    ['name' => 'requester_mode', 'label' => 'Requester Option'],
                    ['name' => 'requester_employee_id', 'label' => 'Selected Employee'],
                    ['name' => 'requestor', 'label' => 'Employee Name'],
                    ['name' => 'employee_id', 'label' => 'Employee ID'],
                    ['name' => 'employee_email', 'label' => 'Email'],
                    ['name' => 'contact_number', 'label' => 'Contact #'],
                    ['name' => 'position', 'label' => 'Position'],
                    ['name' => 'department', 'label' => 'Department'],
                    ['name' => 'superior', 'label' => 'Superior'],
                    ['name' => 'superior_email', 'label' => 'Superior Email'],
                ]),
                $section('Vendor / Supplier Details', [
                    ['name' => 'supplier_id', 'label' => 'Supplier'],
                    ['name' => 'new_vendor', 'label' => 'New Vendor?'],
                    ['name' => 'vendor_id_number', 'label' => 'Vendor ID Number'],
                    ['name' => 'vendors_tin', 'label' => 'Vendors TIN#'],
                    ['name' => 'company_name', 'label' => 'Company'],
                    ['name' => 'vendor_address', 'label' => 'Address'],
                    ['name' => 'city', 'label' => 'City'],
                    ['name' => 'province', 'label' => 'Province'],
                    ['name' => 'zip', 'label' => 'Zip'],
                    ['name' => 'vendor_phone', 'label' => 'Phone Number'],
                    ['name' => 'vendor_email', 'label' => 'Email'],
                ]),
                $section('Project Allocation', [
                    ['name' => 'project', 'label' => 'Project'],
                    ['name' => 'cost_center', 'label' => 'Cost Center'],
                ]),
                ['type' => 'line_items', 'title' => 'Items / Cost Details'],
                $section('Purpose & Notes', [
                    ['name' => 'purpose', 'label' => 'Purpose / Justification'],
                    ['name' => 'remarks', 'label' => 'Remarks'],
                ]),
            ],
            'po' => [
                $section('Order Details', [
                    ['name' => 'linked_pr_id', 'label' => 'Linked PR'],
                    ['name' => 'supplier_id', 'label' => 'Supplier'],
                    ['name' => 'project', 'label' => 'Project'],
                    ['name' => 'cost_center', 'label' => 'Cost Center'],
                    ['name' => 'linked_item_type', 'label' => 'Items / Services Type'],
                    ['name' => 'linked_item_id', 'label' => 'Items / Services'],
                    ['name' => 'quantity', 'label' => 'Quantity'],
                    ['name' => 'unit_cost', 'label' => 'Unit Cost'],
                    ['name' => 'total_amount', 'label' => 'Total Amount'],
                    ['name' => 'expected_delivery_date', 'label' => 'Expected Delivery Date'],
                    ['name' => 'delivery_address', 'label' => 'Delivery Address'],
                    ['name' => 'terms_and_conditions', 'label' => 'Terms and Conditions'],
                    ['name' => 'remarks', 'label' => 'Remarks'],
                    ['name' => 'coa_id', 'label' => 'Account'],
                ]),
                ['type' => 'line_items', 'title' => 'Items / Cost Details'],
            ],
            'ca' => [
                $section('Request Details', array_values(array_filter([
                    ['name' => 'requester_mode', 'label' => 'Requester Option'],
                    ['name' => 'requester_employee_id', 'label' => 'Selected Employee'],
                    ['name' => 'employee_id', 'label' => 'Employee ID'],
                    ['name' => 'employee_name', 'label' => 'Employee Name'],
                    ['name' => 'employee_email', 'label' => 'Email'],
                    ['name' => 'contact_number', 'label' => 'Contact #'],
                    ['name' => 'position', 'label' => 'Position'],
                    ['name' => 'department', 'label' => 'Department'],
                    ['name' => 'superior', 'label' => 'Superior'],
                    ['name' => 'superior_email', 'label' => 'Superior Email'],
                    ['name' => 'needed_date', 'label' => 'Needed Date'],
                    ['name' => 'priority', 'label' => 'Priority'],
                    ['name' => 'cash_advance_type', 'label' => 'Cash Advance Type'],
                    ['name' => 'for_client', 'label' => 'For Client?'],
                    ['name' => 'client_names', 'label' => 'Client Name(s)'],
                    ['name' => 'amount_requested', 'label' => 'Amount Requested'],
                    ['name' => 'release_schedule', 'label' => 'Release Schedule'],
                    ['name' => 'release_count', 'label' => 'Number of Releases'],
                    ['name' => 'amount_per_release', 'label' => 'Amount per Release'],
                    ['name' => 'mode_of_release', 'label' => 'Mode of Release'],
                    ['name' => 'paid_through', 'label' => 'Paid Through'],
                    ['name' => 'purpose', 'label' => 'Justification / Business Need'],
                    ['name' => 'usage_categories', 'label' => 'Cash Advance Usage / Expense Categories'],
                    ['name' => 'other_business_purpose_specify', 'label' => 'Other Business Purpose - Specify'],
                    ['name' => 'other_expense_specify', 'label' => 'Other Expense - Specify'],
                ], fn ($field) => ! isset($field['name']) || $this->conditionalPreviewFieldVisible($data, $field['name'])))),
                $section('Requester Details', [
                    ['name' => 'requester_employee_id', 'label' => 'Selected Employee'],
                    ['name' => 'employee_id', 'label' => 'Employee ID'],
                    ['name' => 'employee_name', 'label' => 'Employee Name'],
                    ['name' => 'employee_email', 'label' => 'Email'],
                    ['name' => 'contact_number', 'label' => 'Contact #'],
                    ['name' => 'position', 'label' => 'Position'],
                    ['name' => 'department', 'label' => 'Department'],
                    ['name' => 'superior', 'label' => 'Superior'],
                    ['name' => 'superior_email', 'label' => 'Superior Email'],
                ]),
                $section('Declarations & Authorizations', [
                    ['name' => 'official_business_cash_advance', 'label' => 'Official Business Cash Advance'],
                    ['name' => 'employee_cash_advance_personal', 'label' => 'Employee Cash Advance - Personal Purpose'],
                    ['name' => 'liquidation_non_compliance', 'label' => 'Liquidation Non-Compliance'],
                    ['name' => 'automatic_salary_deduction_authorization', 'label' => 'Automatic Salary Deduction Authorization'],
                    ['name' => 'final_pay_deduction_authorization', 'label' => 'Final Pay Deduction Authorization'],
                    ['name' => 'policy_acknowledgment', 'label' => 'Policy Acknowledgment'],
                ]),
                $section('Funding & Notes', [
                    ['name' => 'remarks', 'label' => 'Remarks'],
                ]),
            ],
            'lr' => [
                $section('Liquidation Details', [
                    ['name' => 'requester_mode', 'label' => 'Requester Option'],
                    ['name' => 'requester_employee_id', 'label' => 'Selected Employee'],
                    ['name' => 'linked_ca_id', 'label' => 'CA Reference No.'],
                    ['name' => 'purpose', 'label' => 'Justification / Business Need'],
                    ['name' => 'total_cash_advance', 'label' => 'CA Amount'],
                    ['name' => 'actual_expenses', 'label' => 'Actual Expenses'],
                    ['name' => 'for_client', 'label' => 'For Client?'],
                    ['name' => 'client_names', 'label' => 'Client Name(s)'],
                    ['name' => 'employee_id', 'label' => 'Employee ID'],
                    ['name' => 'employee_name', 'label' => 'Employee Name'],
                    ['name' => 'employee_email', 'label' => 'Email'],
                    ['name' => 'contact_number', 'label' => 'Contact #'],
                    ['name' => 'position', 'label' => 'Position'],
                    ['name' => 'department', 'label' => 'Department'],
                    ['name' => 'superior', 'label' => 'Superior'],
                    ['name' => 'superior_email', 'label' => 'Superior Email'],
                ]),
                ['type' => 'line_items', 'title' => 'Liquidation Items'],
            ],
            'err' => [
                $section('Reimbursement Details', [
                    ['name' => 'requester_mode', 'label' => 'Requester Option'],
                    ['name' => 'requester_employee_id', 'label' => 'Selected Employee'],
                    ['name' => 'requestor', 'label' => 'Requestor'],
                    ['name' => 'linked_lr_id', 'label' => 'Linked LR'],
                    ['name' => 'expense_details', 'label' => 'Expense Details'],
                    ['name' => 'amount', 'label' => 'Amount'],
                    ['name' => 'reimbursement_payment_details', 'label' => 'Reimbursement Payment Details'],
                    ['name' => 'manual_liquidation_entry', 'label' => 'Manually edit reimbursement details'],
                    ['name' => 'reimbursement_mode', 'label' => 'Mode of Reimbursement'],
                    ...match (data_get($data, 'reimbursement_mode')) {
                        'Cash' => [
                            ['name' => 'cash_receiver_name', 'label' => 'Name of Receiver'],
                        ],
                        'Bank Transfer' => [
                            ['name' => 'recipient_bank_account', 'label' => 'Bank Account'],
                            ['name' => 'recipient_bank_number', 'label' => 'Bank Number'],
                        ],
                        'Check' => [
                            ['name' => 'bank_account_id', 'label' => 'Bank Account Source'],
                        ],
                        default => [],
                    },
                    ['name' => 'remarks', 'label' => 'Remarks'],
                ]),
            ],
            'dv' => [
                ...((function () use ($section, $data) {
                    $dvSourceType = Str::lower(trim((string) data_get($data, 'source_document_type', '')));
                    $isErrSource = $dvSourceType === 'err';
                    $isCrfSource = $dvSourceType === 'crf';
                    $isIbtfSource = $dvSourceType === 'ibtf';
                    $isErrCheck = $isErrSource && data_get($data, 'payment_type') === 'Check';
                    $isCrfCheck = $isCrfSource && data_get($data, 'payment_type') === 'Check';

                    $voucherFields = [
                        ['name' => 'source_document_type', 'label' => 'Linked Source Document Type'],
                        ['name' => 'source_document_id', 'label' => 'Linked Source Document'],
                        ['name' => 'payee_type', 'label' => 'Payee Type'],
                        ['name' => 'payee_name', 'label' => 'Payee'],
                        ...($isErrSource || $isCrfSource || $isIbtfSource ? [] : [['name' => 'supplier_id', 'label' => 'Supplier']]),
                        ['name' => 'amount', 'label' => 'Amount'],
                        ['name' => 'payment_type', 'label' => 'Payment Type'],
                        ['name' => 'disbursement_type', 'label' => 'Disbursement Type'],
                        ['name' => 'payment_date', 'label' => 'Payment Date'],
                        ...($isErrSource || $isCrfSource || $isIbtfSource ? [] : [['name' => 'due_date', 'label' => 'Due Date']]),
                    ];

                    $fundingFields = [
                        ...($isErrSource
                            ? ($isErrCheck ? [['name' => 'bank_account_id', 'label' => 'Bank Account']] : [])
                            : ($isCrfSource
                                ? ($isCrfCheck ? [['name' => 'bank_account_id', 'label' => 'Bank Account']] : [])
                                : [['name' => 'bank_account_id', 'label' => 'Bank Account']]))
                        ,
                        ['name' => 'coa_id', 'label' => 'Account'],
                        ...($isErrSource || $isCrfSource || $isIbtfSource ? [] : [['name' => 'fund_source', 'label' => 'Fund Source / Project']]),
                        ...($isCrfSource || $isIbtfSource ? [] : [['name' => 'department', 'label' => 'Department']]),
                        ['name' => 'reference_number', 'label' => 'Reference Number'],
                        ['name' => 'purpose', 'label' => 'Purpose'],
                        ['name' => 'remarks', 'label' => 'Remarks'],
                    ];

                    $sections = [
                        $section('Voucher Details', $voucherFields),
                        $section('Funding & Notes', $fundingFields),
                    ];

                    if (!$isErrSource && !$isCrfSource && !$isIbtfSource) {
                        $sections[] = $section('Tax & Receipt', [
                            ['name' => 'withholding_tax', 'label' => 'Withholding Tax'],
                            ['name' => 'vat_amount', 'label' => 'VAT'],
                            ['name' => 'net_amount', 'label' => 'Net Amount'],
                            ['name' => 'currency', 'label' => 'Currency'],
                            ['name' => 'exchange_rate', 'label' => 'Exchange Rate'],
                            ['name' => 'received_by_name', 'label' => 'Received By'],
                            ['name' => 'date_received', 'label' => 'Date Received'],
                        ]);
                    }

                    return $sections;
                })()),
            ],
            'pda' => [
                $section('Payroll Details', [
                    ['name' => 'payroll_period_id', 'label' => 'Payroll Period'],
                    ['name' => 'period_start', 'label' => 'Period Start'],
                    ['name' => 'period_end', 'label' => 'Period End'],
                    ['name' => 'payroll_start', 'label' => 'Payroll Start'],
                    ['name' => 'payroll_end', 'label' => 'Payroll End'],
                    ['name' => 'pay_date', 'label' => 'Pay Date'],
                    ['name' => 'total_payroll_amount', 'label' => 'Total Payroll Amount'],
                    ['name' => 'employee_count', 'label' => 'Employees Included'],
                    ['name' => 'basic_salary_total', 'label' => 'Basic Salary'],
                    ['name' => 'yearly_basic_total', 'label' => 'Yearly Basic'],
                    ['name' => 'daily_rate_total', 'label' => 'Daily Rate Total'],
                    ['name' => 'hourly_rate_total', 'label' => 'Hourly Rate Total'],
                    ['name' => 'minute_rate_total', 'label' => 'Minute Rate Total'],
                    ['name' => 'gross_pay_total', 'label' => 'Gross Pay'],
                    ['name' => 'benefits_total', 'label' => 'Benefits'],
                    ['name' => 'allowances_total', 'label' => 'Allowances'],
                    ['name' => 'deductions_total', 'label' => 'Deductions'],
                    ['name' => 'night_differential_total', 'label' => 'Night Differential'],
                    ['name' => 'holiday_pay_total', 'label' => 'Holiday Pay'],
                    ['name' => 'department', 'label' => 'Department / Coverage'],
                    ['name' => 'funding_bank_account_id', 'label' => 'Funding Bank Account'],
                    ['name' => 'payroll_expense_coa_id', 'label' => 'Payroll Expense Account'],
                ]),
                $section('Supporting Notes', [
                    ['name' => 'supporting_payroll_summary', 'label' => 'Supporting Payroll Summary'],
                    ['name' => 'employee_payroll_breakdown', 'label' => 'Employee Payroll Breakdown'],
                    ['name' => 'remarks', 'label' => 'Remarks'],
                ]),
            ],
            'crf' => [
                $section('Return Details', [
                    ['name' => 'requester_mode', 'label' => 'Requester Option'],
                    ['name' => 'requester_employee_id', 'label' => 'Selected Employee'],
                    ['name' => 'requestor', 'label' => 'Returnee'],
                    ['name' => 'linked_lr_id', 'label' => 'Linked LR'],
                    ['name' => 'amount_returned', 'label' => 'Amount Returned'],
                    ['name' => 'mode_of_return', 'label' => 'Mode of Return'],
                    ['name' => 'receiving_bank_account_id', 'label' => 'Receiving Bank / Cash Account'],
                    ['name' => 'coa_id', 'label' => 'Account'],
                ]),
                $this->financeConnectedRecordsSection($record, $lookupOptions),
                [
                    'type' => 'attachments',
                    'title' => 'Attachments',
                ],
                [
                    'type' => 'history',
                    'title' => 'Record History / Audit Trail',
                ],
                $section('Reference & Notes', [
                    ['name' => 'reference_number', 'label' => 'Reference Number'],
                    ['name' => 'remarks', 'label' => 'Remarks'],
                ]),
                $notesSection,
            ],
            'ibtf' => [
                $section('Transfer Details', [
                    ['name' => 'source_bank_account_id', 'label' => 'Source Bank Account'],
                    ['name' => 'destination_bank_account_id', 'label' => 'Destination Bank Account'],
                    ['name' => 'amount', 'label' => 'Amount'],
                    ['name' => 'reason', 'label' => 'Reason / Purpose'],
                    ['name' => 'source_account_code', 'label' => 'Source Account Code'],
                    ['name' => 'destination_account_code', 'label' => 'Destination Account Code'],
                    ['name' => 'transfer_reference_number', 'label' => 'Transfer Reference Number'],
                    ['name' => 'remarks', 'label' => 'Remarks'],
                ]),
            ],
            'arf' => [
                $section('Asset / Inventory Details', [
                    ['name' => 'item_classification', 'label' => 'Item Classification'],
                    ['name' => 'linked_po_id', 'label' => 'Linked PO'],
                    ['name' => 'linked_dv_id', 'label' => 'Linked DV'],
                    ['name' => 'supplier_id', 'label' => 'Supplier'],
                    ['name' => 'asset_code', 'label' => 'Asset Code'],
                    ['name' => 'item_name', 'label' => 'Item Name'],
                    ['name' => 'item_code', 'label' => 'Item Code'],
                    ['name' => 'sku', 'label' => 'SKU'],
                    ['name' => 'barcode', 'label' => 'Barcode'],
                    ['name' => 'qr_code', 'label' => 'QR Code'],
                    ['name' => 'asset_description', 'label' => 'Asset Description'],
                    ['name' => 'asset_category', 'label' => 'Category'],
                    ['name' => 'serial_number', 'label' => 'Serial Number'],
                    ['name' => 'model', 'label' => 'Model'],
                ]),
                $section('Inventory & Goods Receiving', [
                    ['name' => 'goods_receiving_reference', 'label' => 'Goods Receiving Reference'],
                    ['name' => 'ordered_quantity', 'label' => 'Ordered Quantity'],
                    ['name' => 'delivered_quantity', 'label' => 'Delivered Quantity'],
                    ['name' => 'accepted_quantity', 'label' => 'Accepted Quantity'],
                    ['name' => 'rejected_quantity', 'label' => 'Rejected Quantity'],
                    ['name' => 'unit_of_measure', 'label' => 'Unit of Measure'],
                    ['name' => 'beginning_quantity', 'label' => 'Beginning Quantity'],
                    ['name' => 'current_quantity', 'label' => 'Current Quantity'],
                    ['name' => 'reserved_quantity', 'label' => 'Reserved Quantity'],
                    ['name' => 'available_quantity', 'label' => 'Available Quantity'],
                    ['name' => 'reorder_level', 'label' => 'Reorder Level'],
                    ['name' => 'minimum_stock_level', 'label' => 'Minimum Stock Level'],
                    ['name' => 'maximum_stock_level', 'label' => 'Maximum Stock Level'],
                    ['name' => 'safety_stock_level', 'label' => 'Safety Stock Level'],
                    ['name' => 'unit_cost', 'label' => 'Unit Cost'],
                    ['name' => 'total_cost', 'label' => 'Total Cost'],
                    ['name' => 'average_cost', 'label' => 'Average Cost'],
                    ['name' => 'last_purchase_cost', 'label' => 'Last Purchase Cost'],
                ]),
                $section('Valuation & Custody', [
                    ['name' => 'acquisition_cost', 'label' => 'Acquisition Cost'],
                    ['name' => 'acquisition_date', 'label' => 'Acquisition Date'],
                    ['name' => 'asset_coa_id', 'label' => 'Asset Account from Chart of Accounts'],
                    ['name' => 'location', 'label' => 'Location'],
                    ['name' => 'department', 'label' => 'Department'],
                    ['name' => 'custodian', 'label' => 'Custodian'],
                    ...($isConsumableInventory ? [] : [
                        ['name' => 'useful_life', 'label' => 'Useful Life (Years)'],
                        ['name' => 'residual_value', 'label' => 'Residual Value'],
                        ['name' => 'depreciable_amount', 'label' => 'Depreciable Amount'],
                        ['name' => 'annual_depreciation', 'label' => 'Annual Depreciation'],
                        ['name' => 'monthly_depreciation', 'label' => 'Monthly Depreciation'],
                        ['name' => 'accumulated_depreciation', 'label' => 'Accumulated Depreciation'],
                        ['name' => 'net_book_value', 'label' => 'Net Book Value'],
                    ]),
                    ['name' => 'movement_history_note', 'label' => 'Latest Movement Highlight'],
                    ['name' => 'remarks', 'label' => 'Remarks'],
                ]),
            ],
            default => [],
        };
    }

    private function financeCashAdvancePaymentTracking(FinanceRecord $record): ?array
    {
        if ($record->module_key !== 'ca') {
            return null;
        }

        $data = $record->data ?? [];
        $amount = (float) data_get($data, 'amount_requested', $record->amount ?? 0);
        $releaseCount = max((int) data_get($data, 'release_count', 1), 1);
        $amountPerRelease = (float) data_get($data, 'amount_per_release', $releaseCount > 0 ? $amount / $releaseCount : $amount);
        $entries = collect((array) data_get($data, 'ca_payment_entries', []))
            ->filter(fn ($entry) => is_array($entry))
            ->values();
        $entriesByRelease = $entries->groupBy(fn (array $entry) => (int) data_get($entry, 'release_no', 0));
        $releaseEntries = collect((array) data_get($data, 'release_entries', []))
            ->filter(fn ($entry) => is_array($entry))
            ->keyBy(fn (array $entry) => (int) data_get($entry, 'release_no', 0));

        $rows = collect(range(1, $releaseCount))->map(function (int $releaseNo) use ($releaseCount, $amount, $amountPerRelease, $entriesByRelease, $releaseEntries, $data) {
            $paymentEntries = $entriesByRelease->get($releaseNo, collect());
            $paidAmount = $paymentEntries->sum(fn (array $entry) => (float) data_get($entry, 'payment_amount', 0));
            $schedule = (array) $releaseEntries->get($releaseNo, []);
            $scheduledAmount = (float) data_get(
                $schedule,
                'scheduled_amount',
                $releaseNo === $releaseCount
                    ? max($amount - ($amountPerRelease * ($releaseCount - 1)), 0)
                    : $amountPerRelease
            );
            $latestPayment = $paymentEntries->last() ?: [];
            $status = $paidAmount >= $scheduledAmount && $scheduledAmount > 0
                ? 'Paid'
                : ($paidAmount > 0 ? 'Partial' : 'Pending');

            return [
                'no' => $releaseNo,
                'scheduled_date' => data_get($schedule, 'scheduled_date') ?: ($releaseNo === 1 ? (data_get($data, 'cash_release_date') ?: '-') : '-'),
                'scheduled_time' => data_get($schedule, 'scheduled_time') ?: ($releaseNo === 1 ? (data_get($data, 'cash_release_time') ?: '-') : '-'),
                'scheduled_amount' => number_format($scheduledAmount, 2),
                'scheduled_remarks' => trim((string) data_get($schedule, 'scheduled_remarks', '')),
                'paid_amount' => number_format($paidAmount, 2),
                'payment_date' => data_get($latestPayment, 'payment_date') ?: '-',
                'payment_remarks' => $paymentEntries->pluck('payment_remarks')->filter()->implode(' | '),
                'status' => $status,
            ];
        })->values();

        $totalPaid = $entries->sum(fn (array $entry) => (float) data_get($entry, 'payment_amount', 0));
        $remainingBalance = max($amount - $totalPaid, 0);
        $paidCount = $rows->where('status', 'Paid')->count();
        $status = $remainingBalance <= 0 && $amount > 0 ? 'Fully Released' : ($totalPaid > 0 ? 'Partially Released' : 'Pending Release');

        return [
            'summary' => [
                ['label' => 'Per Release', 'value' => number_format($amountPerRelease, 2)],
                ['label' => 'Total Cash Advance', 'value' => number_format($amount, 2)],
                ['label' => 'Released / Paid', 'value' => number_format($totalPaid, 2)],
                ['label' => 'Remaining for Release', 'value' => number_format($remainingBalance, 2)],
                ['label' => 'Releases Paid', 'value' => $paidCount],
                ['label' => 'Releases Remaining', 'value' => max($releaseCount - $paidCount, 0)],
                ['label' => 'Status', 'value' => $status],
            ],
            'rows' => $rows->all(),
        ];
    }

    private function financePdfContext(FinanceRecord $record, bool $includeLogo = true, bool $forceSupplierTemplate = false): array
    {
        $data = array_merge($record->data ?? [], $this->financeLifecycleSnapshot($record));
        $lookupOptions = $this->resolveLookupOptions();
        $moduleLabel = $this->moduleLabel($record->module_key);
        $recordTitleLabel = $this->moduleRecordTitleLabel($record->module_key);
        $approvalActorNames = $this->financeApprovalActorNames($record);
        $ownershipCards = $this->financeOwnershipSummary($record);
        $isTemplatePreview = $forceSupplierTemplate;
        $showRecordTitle = $this->financeModuleShowsRecordTitle($record->module_key);
        $companyName = 'John Kelly & Company';
        $companyLegalName = 'JK&C INC.';
        $companyLogo = $includeLogo ? $this->financePdfImageDataUri('images/imaglogo.png') : null;
        if ($isTemplatePreview) {
            $summaryCards = [
                ['label' => 'Module', 'value' => $moduleLabel],
                ['label' => 'Record Number', 'value' => $this->normalizeFinanceRecordNumber($record->module_key, $record->record_number)],
                ['label' => 'Record Date', 'value' => optional($record->record_date)->format('Y-m-d') ?: 'N/A'],
            ];
            if ($showRecordTitle) {
                array_splice($summaryCards, 2, 0, [[
                    'label' => $recordTitleLabel,
                    'value' => $record->record_title ?: 'N/A',
                ]]);
            }
        } else {
            $summaryCards = $record->module_key === 'pr'
                ? [
                    ['label' => 'Module', 'value' => $moduleLabel],
                    ['label' => 'Request Number', 'value' => $this->normalizeFinanceRecordNumber($record->module_key, $record->record_number)],
                    ['label' => 'Requestor', 'value' => data_get($data, 'requestor') ?: data_get($data, 'employee_name') ?: 'N/A'],
                    ['label' => 'Priority', 'value' => data_get($data, 'priority') ?: 'N/A'],
                    ['label' => 'Date Needed', 'value' => data_get($data, 'needed_date') ?: 'N/A'],
                    ['label' => 'Amount', 'value' => $record->amount !== null ? number_format((float) $record->amount, 2) : 'N/A'],
                    ['label' => 'Record Date', 'value' => optional($record->record_date)->format('Y-m-d') ?: 'N/A'],
                    ['label' => 'Workflow', 'value' => $record->workflow_status ?: 'N/A'],
                    ['label' => 'Approval', 'value' => $record->approval_status ?: 'N/A'],
                    ['label' => 'Submitted By', 'value' => $this->financeSubmittedByName($record)],
                    ['label' => 'Approved By', 'value' => $approvalActorNames ? implode(', ', $approvalActorNames) : $this->financeUserDisplayName($record->approved_by, 'N/A')],
                    ...$ownershipCards,
                    ['label' => 'Relationship Status', 'value' => data_get($data, 'relationship_status') ?: 'N/A'],
                    ['label' => 'Next Action', 'value' => data_get($data, 'next_action') ?: 'N/A'],
                    ['label' => 'Submitted At', 'value' => optional($record->submitted_at)->format('Y-m-d H:i:s') ?: 'N/A'],
                    ['label' => 'Approved At', 'value' => optional($record->approved_at)->format('Y-m-d H:i:s') ?: 'N/A'],
                    ...($record->module_key === 'ca' ? [
                        ['label' => $this->financeDisplayApprovalRoleLabels($record->module_key)[0], 'value' => $this->financeApprovalRoutingDisplayValue($record, 'first_approver_user_id')],
                        ['label' => $this->financeDisplayApprovalRoleLabels($record->module_key)[1], 'value' => $this->financeApprovalRoutingDisplayValue($record, 'second_approver_user_id')],
                    ] : []),
                    ...($record->module_key === 'pda' ? [
                        ['label' => 'Payroll Period', 'value' => $this->financePdfLookupLabel($lookupOptions, 'payroll_period', data_get($data, 'payroll_period_id')) ?: data_get($data, 'payroll_period_id') ?: 'N/A'],
                        ['label' => $this->financeDisplayApprovalRoleLabels($record->module_key)[0], 'value' => $this->financeApprovalRoutingDisplayValue($record, 'first_approver_user_id')],
                        ['label' => $this->financeDisplayApprovalRoleLabels($record->module_key)[1], 'value' => $this->financeApprovalRoutingDisplayValue($record, 'second_approver_user_id')],
                    ] : []),
                    ...($record->module_key === 'err' ? [
                        ['label' => 'Linked LR', 'value' => $this->financePdfLookupLabel($lookupOptions, 'lr', data_get($data, 'linked_lr_id')) ?: data_get($data, 'linked_lr_id') ?: 'N/A'],
                        ['label' => 'Reimbursement Mode', 'value' => data_get($data, 'reimbursement_mode') ?: 'N/A'],
                    ] : []),
                    ...($record->module_key === 'lr' ? [
                        ['label' => 'Attachments', 'value' => max(count((array) ($record->attachments ?? [])), 0) . ' file' . (count((array) ($record->attachments ?? [])) === 1 ? '' : 's')],
                    ] : []),
                    ...($record->module_key === 'crf' ? [
                        ['label' => 'Attachments', 'value' => max(count((array) ($record->attachments ?? [])), 0) . ' file' . (count((array) ($record->attachments ?? [])) === 1 ? '' : 's')],
                        ['label' => 'History Entries', 'value' => count((array) data_get($data, 'history', []))],
                    ] : []),
                    ...($record->module_key === 'arf' ? [
                        ['label' => 'Attachments', 'value' => max(count((array) ($record->attachments ?? [])), 0) . ' file' . (count((array) ($record->attachments ?? [])) === 1 ? '' : 's')],
                        ['label' => 'Photo Attachments', 'value' => collect((array) ($record->attachments ?? []))->filter(fn ($attachment) => $this->financeAttachmentIsImage(is_array($attachment) ? $attachment : []))->count()],
                    ] : []),
                ]
                : [
                    ['label' => 'Module', 'value' => $moduleLabel],
                    ['label' => 'Record Number', 'value' => $this->normalizeFinanceRecordNumber($record->module_key, $record->record_number)],
                    ['label' => 'Record Date', 'value' => optional($record->record_date)->format('Y-m-d') ?: 'N/A'],
                    ['label' => 'Record Time', 'value' => data_get($data, 'transaction_time') ?: 'N/A'],
                    ['label' => 'Amount', 'value' => $record->amount !== null ? number_format((float) $record->amount, 2) : 'N/A'],
                    ['label' => 'Status', 'value' => $record->status ?: 'N/A'],
                    ['label' => 'Workflow', 'value' => $record->workflow_status ?: 'N/A'],
                    ['label' => 'Approval', 'value' => $record->approval_status ?: 'N/A'],
                    ['label' => 'Submitted By', 'value' => $this->financeSubmittedByName($record)],
                    ['label' => 'Approved By', 'value' => $approvalActorNames ? implode(', ', $approvalActorNames) : $this->financeUserDisplayName($record->approved_by, 'N/A')],
                    ...$ownershipCards,
                    ['label' => 'Relationship Status', 'value' => data_get($data, 'relationship_status') ?: 'N/A'],
                    ['label' => 'Next Action', 'value' => data_get($data, 'next_action') ?: 'N/A'],
                    ['label' => 'Submitted At', 'value' => optional($record->submitted_at)->format('Y-m-d H:i:s') ?: 'N/A'],
                    ['label' => 'Approved At', 'value' => optional($record->approved_at)->format('Y-m-d H:i:s') ?: 'N/A'],
                    ...($record->module_key === 'ca' ? [
                        ['label' => $this->financeDisplayApprovalRoleLabels($record->module_key)[0], 'value' => $this->financeApprovalRoutingDisplayValue($record, 'first_approver_user_id')],
                        ['label' => $this->financeDisplayApprovalRoleLabels($record->module_key)[1], 'value' => $this->financeApprovalRoutingDisplayValue($record, 'second_approver_user_id')],
                    ] : []),
                    ...($record->module_key === 'lr' ? [
                        ['label' => 'Attachments', 'value' => max(count((array) ($record->attachments ?? [])), 0) . ' file' . (count((array) ($record->attachments ?? [])) === 1 ? '' : 's')],
                    ] : []),
                    ...($record->module_key === 'crf' ? [
                        ['label' => 'Attachments', 'value' => max(count((array) ($record->attachments ?? [])), 0) . ' file' . (count((array) ($record->attachments ?? [])) === 1 ? '' : 's')],
                        ['label' => 'History Entries', 'value' => count((array) data_get($data, 'history', []))],
                    ] : []),
                    ...($record->module_key === 'arf' ? [
                        ['label' => 'Attachments', 'value' => max(count((array) ($record->attachments ?? [])), 0) . ' file' . (count((array) ($record->attachments ?? [])) === 1 ? '' : 's')],
                        ['label' => 'Photo Attachments', 'value' => collect((array) ($record->attachments ?? []))->filter(fn ($attachment) => $this->financeAttachmentIsImage(is_array($attachment) ? $attachment : []))->count()],
                    ] : []),
                ];

            if ($showRecordTitle) {
                array_splice($summaryCards, 2, 0, [[
                    'label' => $recordTitleLabel,
                    'value' => $record->record_title ?: 'N/A',
                ]]);
            }
        }

        $lineItems = $this->financeResolvedLineItems($record, $lookupOptions);
        $itemizationLineItems = $this->financePreviewItemizationLineItems($record, $lookupOptions);
        $lineItemsTotal = array_reduce($lineItems, function (float $carry, array $item) {
            return $carry + (float) ($item['total_value'] ?? 0);
        }, 0.0);
        $poSupplierGroups = $record->module_key === 'po'
            ? $this->financePoSupplierGroups($lineItems)
            : [];

        $costSummary = match ($record->module_key) {
            'lr' => [
                ['label' => 'Subtotal', 'value' => $data['subtotal'] ?? '0.00'],
                ['label' => 'Discount Total', 'value' => $data['discount_total'] ?? '0.00'],
                ['label' => 'Tax Total', 'value' => $data['tax_total'] ?? '0.00'],
                ['label' => 'Shipping Total', 'value' => $data['shipping_total'] ?? '0.00'],
                ['label' => 'WHT Total', 'value' => $data['wht_total'] ?? '0.00'],
                ['label' => 'Grand Total', 'value' => $data['grand_total'] ?? $record->amount ?? '0.00'],
            ],
            default => [
                ['label' => 'Subtotal', 'value' => $data['subtotal'] ?? $record->amount ?? '0.00'],
                ['label' => 'Discount', 'value' => $data['discount'] ?? '0%'],
                ['label' => 'Discount Amount', 'value' => $data['discount_amount'] ?? '0.00'],
                ['label' => 'Shipping', 'value' => $data['shipping_amount'] ?? '0.00'],
                ['label' => 'Tax (VAT/EWT/VAT Exempt/Zero Rated)', 'value' => $data['tax_type'] ?? 'N/A'],
                ['label' => 'Tax Amount', 'value' => $data['tax_amount'] ?? '0.00'],
                ['label' => 'WHT', 'value' => $data['wht_amount'] ?? '0.00'],
                ['label' => 'Grand Total', 'value' => $data['grand_total'] ?? $record->amount ?? '0.00'],
            ],
        };

        $attachments = array_values(array_map(function ($attachment) {
            $attachment = is_array($attachment) ? $attachment : [];
            $attachment['url'] = $this->financeAttachmentUrl($attachment);
            $attachment['download_url'] = $attachment['url'] ? $attachment['url'] . '?download=1' : null;
            $attachment['is_image'] = $this->financeAttachmentIsImage($attachment);
            $attachment['image_data_uri'] = $attachment['is_image'] ? $this->financeAttachmentDataUri($attachment) : null;

            return $attachment;
        }, array_filter((array) ($record->attachments ?? []), fn ($attachment) => !blank(data_get($attachment, 'name')) || !blank(data_get($attachment, 'path')))));

        $attachmentSummary = $this->financeAttachmentSummary($record);
        $approvalTrailRows = $this->financeApprovalTrailRows($record);
        $completeDataRows = $this->financeCompleteDataRows($record);

        $isConsumableInventory = $record->module_key === 'arf' && Str::lower((string) data_get($data, 'item_classification')) === 'consumable inventory';
        $detailRows = $record->module_key === 'arf'
            ? array_values(array_filter([
                $this->financePreviewRow($record, $lookupOptions, 'item_classification', 'Item Classification'),
                $this->financePreviewRow($record, $lookupOptions, 'asset_code', 'Asset Code'),
                $this->financePreviewRow($record, $lookupOptions, 'linked_po_id', 'Linked PO'),
                $this->financePreviewRow($record, $lookupOptions, 'linked_dv_id', 'Linked DV'),
                $this->financePreviewRow($record, $lookupOptions, 'supplier_id', 'Supplier'),
                $this->financePreviewRow($record, $lookupOptions, 'goods_receiving_reference', 'Goods Receiving Reference'),
                $this->financePreviewRow($record, $lookupOptions, 'item_name', 'Item Name'),
                $this->financePreviewRow($record, $lookupOptions, 'item_code', 'Item Code'),
                $this->financePreviewRow($record, $lookupOptions, 'sku', 'SKU'),
                $this->financePreviewRow($record, $lookupOptions, 'barcode', 'Barcode'),
                $this->financePreviewRow($record, $lookupOptions, 'qr_code', 'QR Code'),
                $this->financePreviewRow($record, $lookupOptions, 'unit_of_measure', 'Unit of Measure'),
                $this->financePreviewRow($record, $lookupOptions, 'beginning_quantity', 'Beginning Quantity'),
                $this->financePreviewRow($record, $lookupOptions, 'current_quantity', 'Current Quantity'),
                $this->financePreviewRow($record, $lookupOptions, 'reserved_quantity', 'Reserved Quantity'),
                $this->financePreviewRow($record, $lookupOptions, 'available_quantity', 'Available Quantity'),
                $this->financePreviewRow($record, $lookupOptions, 'reorder_level', 'Reorder Level'),
                $this->financePreviewRow($record, $lookupOptions, 'minimum_stock_level', 'Minimum Stock Level'),
                $this->financePreviewRow($record, $lookupOptions, 'maximum_stock_level', 'Maximum Stock Level'),
                $this->financePreviewRow($record, $lookupOptions, 'safety_stock_level', 'Safety Stock Level'),
                $this->financePreviewRow($record, $lookupOptions, 'unit_cost', 'Unit Cost'),
                $this->financePreviewRow($record, $lookupOptions, 'total_cost', 'Total Cost'),
                $this->financePreviewRow($record, $lookupOptions, 'average_cost', 'Average Cost'),
                $this->financePreviewRow($record, $lookupOptions, 'last_purchase_cost', 'Last Purchase Cost'),
                $this->financePreviewRow($record, $lookupOptions, 'asset_description', 'Asset Description'),
                $this->financePreviewRow($record, $lookupOptions, 'asset_category', 'Asset Category'),
                $this->financePreviewRow($record, $lookupOptions, 'serial_number', 'Serial Number'),
                $this->financePreviewRow($record, $lookupOptions, 'model', 'Model'),
                $this->financePreviewRow($record, $lookupOptions, 'acquisition_cost', 'Acquisition Cost'),
                $this->financePreviewRow($record, $lookupOptions, 'acquisition_date', 'Acquisition Date'),
                $this->financePreviewRow($record, $lookupOptions, 'asset_coa_id', 'Asset Account from Chart of Accounts'),
                $this->financePreviewRow($record, $lookupOptions, 'location', 'Location'),
                $this->financePreviewRow($record, $lookupOptions, 'department', 'Department'),
                $this->financePreviewRow($record, $lookupOptions, 'custodian', 'Custodian'),
                ...($isConsumableInventory ? [] : [
                    $this->financePreviewRow($record, $lookupOptions, 'useful_life', 'Useful Life (Years)'),
                    $this->financePreviewRow($record, $lookupOptions, 'residual_value', 'Residual Value'),
                    $this->financePreviewRow($record, $lookupOptions, 'depreciable_amount', 'Depreciable Amount'),
                    $this->financePreviewRow($record, $lookupOptions, 'annual_depreciation', 'Annual Depreciation'),
                    $this->financePreviewRow($record, $lookupOptions, 'monthly_depreciation', 'Monthly Depreciation'),
                    $this->financePreviewRow($record, $lookupOptions, 'accumulated_depreciation', 'Accumulated Depreciation'),
                    $this->financePreviewRow($record, $lookupOptions, 'net_book_value', 'Net Book Value'),
                ]),
                $this->financePreviewRow($record, $lookupOptions, 'remarks', 'Remarks'),
            ], fn ($row) => is_array($row) && filled(data_get($row, 'value'))))
            : [];

        $liquidationReport = $record->module_key === 'lr'
            ? (function () use ($record, $lookupOptions, $data, $lineItemsTotal) {
                $linkedCa = filled(data_get($data, 'linked_ca_id'))
                    ? $this->financeResolveModuleRecord('ca', data_get($data, 'linked_ca_id'))
                    : null;
                $linkedCaData = $linkedCa?->data ?? [];
                $caAmount = (float) (data_get($data, 'total_cash_advance') ?: ($linkedCa?->amount ?: data_get($linkedCaData, 'amount_requested') ?: 0));
                $actualExpenses = (float) (data_get($data, 'actual_expenses') ?: data_get($data, 'grand_total') ?: $caAmount);
                if ($lineItemsTotal <= 0 && $actualExpenses <= 0 && $caAmount > 0) {
                    $actualExpenses = $caAmount;
                }
                $variance = $caAmount - $actualExpenses;
                $varianceIndicator = $variance > 0 ? 'Overage' : ($variance < 0 ? 'Shortage' : 'Balanced');

                return [
                'ca_reference_no' => $this->financePdfLookupLabel($lookupOptions, 'ca', data_get($data, 'linked_ca_id')) ?: data_get($data, 'linked_ca_id') ?: 'N/A',
                'ca_amount' => number_format($caAmount, 2, '.', ''),
                'for_client' => data_get($data, 'for_client') ?: (data_get($linkedCaData, 'for_client') ?: 'N/A'),
                'client_names' => data_get($data, 'client_names') ?: (data_get($linkedCaData, 'client_names') ?: 'N/A'),
                'line_items_total' => number_format($lineItemsTotal, 2),
                'actual_expenses' => number_format($actualExpenses, 2, '.', ''),
                'variance' => number_format($variance, 2, '.', ''),
                'variance_indicator' => $varianceIndicator,
                'purpose' => data_get($data, 'purpose') ?: 'N/A',
                'remarks' => data_get($data, 'remarks') ?: 'N/A',
                'employee_name' => data_get($data, 'employee_name') ?: data_get($linkedCaData, 'employee_name') ?: data_get($data, 'requestor') ?: data_get($linkedCaData, 'requestor') ?: data_get($data, 'employee_id') ?: 'N/A',
                'status_label' => $this->financePdfValue($varianceIndicator),
                'calculation_label' => 'Line Items Total',
                'calculation_formula' => 'Line Items Total = Sum of all line item totals',
            ];
            })()
            : null;

        $linkedLiquidationContext = in_array($record->module_key, ['err', 'crf'], true)
            ? (function () use ($record, $data, $lookupOptions) {
                $linkedLr = filled(data_get($data, 'linked_lr_id'))
                    ? $this->financeResolveModuleRecord('lr', data_get($data, 'linked_lr_id'))
                    : null;
                $linkedLrData = $linkedLr?->data ?? [];
                $varianceAmount = abs((float) data_get($linkedLrData, 'variance', 0));

                return [
                    'requester_mode' => data_get($data, 'requester_mode') ?: data_get($linkedLrData, 'requester_mode') ?: 'own_request',
                    'requestor' => data_get($data, 'requestor') ?: data_get($linkedLrData, 'requestor') ?: data_get($linkedLrData, 'employee_name') ?: 'N/A',
                    'amount' => $record->module_key === 'err'
                        ? number_format((float) (data_get($data, 'amount') ?: $varianceAmount), 2, '.', '')
                        : number_format((float) (data_get($data, 'amount_returned') ?: $varianceAmount), 2, '.', ''),
                    'linked_lr_label' => $this->financePdfLookupLabel($lookupOptions, 'lr', data_get($data, 'linked_lr_id')) ?: data_get($data, 'linked_lr_id') ?: 'N/A',
                ];
            })()
            : null;

        $dvSourceDocumentType = $record->module_key === 'dv'
            ? (string) data_get($data, 'source_document_type', '')
            : '';
        $dvSourceRecord = $record->module_key === 'dv' && $dvSourceDocumentType !== '' && filled(data_get($data, 'source_document_id'))
            ? $this->financeResolveModuleRecord($dvSourceDocumentType, data_get($data, 'source_document_id'))
            : null;
        $dvSourceLineItems = $dvSourceRecord
            ? $this->financeResolvedLineItems($dvSourceRecord, $lookupOptions)
            : [];
        $dvSourcePoSupplierGroups = $dvSourceRecord && $dvSourceRecord->module_key === 'po'
            ? $this->financePoSupplierGroups($dvSourceLineItems)
            : [];

        return [
            'companyName' => $companyName,
            'companyLegalName' => $companyLegalName,
            'companyLogo' => $companyLogo,
            'moduleLabel' => $moduleLabel,
            'recordTitleLabel' => $recordTitleLabel,
            'record' => $record,
            'linkedLiquidationContext' => $linkedLiquidationContext,
            'assetTag' => $record->module_key === 'arf' ? [
                'asset_code' => data_get($data, 'asset_code') ?: $record->record_number ?: 'N/A',
                'location' => data_get($data, 'location') ?: 'N/A',
                'serial_number' => data_get($data, 'serial_number') ?: 'N/A',
                'barcode_svg' => $this->financeBarcodeSvg(data_get($data, 'asset_code') ?: $record->record_number ?: ''),
            ] : null,
            'isTemplatePreview' => $forceSupplierTemplate,
            'summaryCards' => $summaryCards,
            'detailRows' => $detailRows,
            'previewSections' => $this->financePreviewSections($record, $lookupOptions, $forceSupplierTemplate),
            'lineItems' => $lineItems,
            'itemizationLineItems' => $itemizationLineItems,
            'poSupplierGroups' => $poSupplierGroups,
            'dvSourceDocumentType' => $dvSourceDocumentType,
            'dvSourceRecord' => $dvSourceRecord,
            'dvSourceLineItems' => $dvSourceLineItems,
            'dvSourcePoSupplierGroups' => $dvSourcePoSupplierGroups,
            'costSummary' => $costSummary,
            'liquidationReport' => $liquidationReport,
            'cashAdvancePaymentTracking' => $this->financeCashAdvancePaymentTracking($record),
            'transactionProgress' => $this->financeProgressTracker($record, $this->financeDerivedRelationshipStatus($record)),
            'attachments' => $attachments,
            'attachmentSummary' => $attachmentSummary,
            'approvalTrailRows' => $approvalTrailRows,
            'completeDataRows' => $completeDataRows,
            'chartAccountLabel' => $this->financePdfLookupLabel($lookupOptions, 'chart_account', data_get($data, 'coa_id')) ?: data_get($data, 'coa_id') ?: 'N/A',
        ];
    }

    private function financeRecordPdfFilename(FinanceRecord $record): string
    {
        $recordNumber = $this->normalizeFinanceRecordNumber($record->module_key, $record->record_number)
            ?: ('finance-record-' . $record->id);

        return Str::slug($recordNumber) . '.pdf';
    }

    private function syncLinkedChartAccountBankProfile(FinanceRecord $bankAccountRecord): void
    {
        if ($bankAccountRecord->module_key !== 'bank_account') {
            return;
        }

        $linkedCoaId = data_get($bankAccountRecord->data, 'linked_coa_id');
        if (blank($linkedCoaId)) {
            return;
        }

        $chartAccount = FinanceRecord::query()
            ->where('module_key', 'chart_account')
            ->find($linkedCoaId);

        if (! $chartAccount) {
            return;
        }

        $chartData = is_array($chartAccount->data) ? $chartAccount->data : [];
        $bankProfile = trim(collect([
            data_get($bankAccountRecord->data, 'bank_name'),
            data_get($bankAccountRecord->data, 'branch'),
            data_get($bankAccountRecord->data, 'currency'),
        ])->filter(fn ($value) => filled($value))->implode(' | '));

        $chartData['bank_account_name'] = $bankAccountRecord->record_title ?: ($chartData['bank_account_name'] ?? '');
        $chartData['bank_account_number'] = data_get($bankAccountRecord->data, 'bank_account_number') ?: ($chartData['bank_account_number'] ?? '');
        if ($bankProfile !== '') {
            $chartData['bank_profile'] = $bankProfile;
        }
        $chartData['updated_by_name'] = Auth::user()?->name ?: ($chartData['updated_by_name'] ?? 'System');

        $chartAccount->update([
            'data' => $chartData,
        ]);
    }

    private function financeRecordPdfData(FinanceRecord $record): string
    {
        $freshRecord = $record->fresh() ?: $record;
        $forceSupplierTemplate = $freshRecord->module_key === 'supplier'
            && data_get($freshRecord->data ?? [], 'completion_mode') === 'send_to_supplier';

        return Pdf::loadView('finance.pdf', $this->financePdfContext($freshRecord, false, $forceSupplierTemplate))
            ->setPaper('letter', 'portrait')
            ->output();
    }

    private function financeRecordSystemUrl(FinanceRecord $record): string
    {
        return route('finance.record.open', $record);
    }

    private function financeApproverUsersForRecord(FinanceRecord $record)
    {
        $approverIds = $this->financeRecordApproverUserIds($record);

        if (empty($approverIds)) {
            return collect();
        }

        return User::query()
            ->whereIn('id', $approverIds)
            ->get()
            ->values();
    }

    private function financeRoleNotificationUsers(): \Illuminate\Support\Collection
    {
        if (!Schema::hasTable('user_permissions') || !Schema::hasColumn('user_permissions', 'finance_treasurer') || !Schema::hasColumn('user_permissions', 'finance_president') || !Schema::hasColumn('user_permissions', 'finance_approver')) {
            return collect();
        }

        return User::query()
            ->with(['userPermission', 'employeeProfile', 'contactProfile'])
            ->whereHas('userPermission', function ($query) {
                $query->where('finance_treasurer', true)
                    ->orWhere('finance_president', true)
                    ->orWhere('finance_approver', true);
            })
            ->get()
            ->filter()
            ->unique('id')
            ->values();
    }

    private function financeAdminNotificationUsers()
    {
        return User::query()
            ->with('userPermission')
            ->get()
            ->filter(fn (User $user) => $user->isSuperAdmin()
                || $user->isAdmin()
                || $user->hasPermission('manage_users')
                || $user->hasPermission('access_admin_dashboard'))
            ->values();
    }

    private function financeInventoryHistoryBoardItems(int $limit = 12): array
    {
        if (! Schema::hasTable('finance_records')) {
            return [];
        }

        $movementActions = ['stock_in', 'stock_out', 'stock_transfer', 'stock_return', 'stock_adjustment'];

        return FinanceRecord::query()
            ->where('module_key', 'arf')
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn (FinanceRecord $record) => $this->canViewFinanceRecord($record))
            ->flatMap(function (FinanceRecord $record) use ($movementActions) {
                $historyEntries = array_values(array_filter((array) data_get($record->data ?? [], 'history', []), 'is_array'));

                return collect($historyEntries)
                    ->filter(function (array $entry) use ($movementActions): bool {
                        $action = Str::snake((string) data_get($entry, 'action', ''));
                        $movementType = Str::snake((string) data_get($entry, 'movement_type', ''));
                        $eventType = Str::snake((string) data_get($entry, 'event_type', ''));

                        return in_array($action, $movementActions, true)
                            || in_array($movementType, $movementActions, true)
                            || in_array($eventType, $movementActions, true);
                    })
                    ->map(function (array $entry) use ($record): array {
                        $changeMap = collect((array) data_get($entry, 'changes', []))
                            ->filter(fn ($change) => is_array($change) && filled(data_get($change, 'field')))
                            ->mapWithKeys(fn (array $change) => [
                                (string) data_get($change, 'field') => [
                                    'old_value' => data_get($change, 'old_value'),
                                    'new_value' => data_get($change, 'new_value'),
                                ],
                            ])
                            ->all();
                        $changedAt = trim((string) data_get($entry, 'changed_at', data_get($entry, 'created_at', '')));
                        $quantity = data_get($changeMap, 'movement_quantity.new_value')
                            ?? data_get($entry, 'new_values.movement_quantity')
                            ?? data_get($changeMap, 'current_quantity.new_value')
                            ?? data_get($entry, 'new_values.current_quantity');
                        $availableQuantity = data_get($changeMap, 'available_quantity.new_value')
                            ?? data_get($entry, 'new_values.available_quantity');
                        $action = trim((string) data_get($entry, 'action', 'Inventory Movement'));
                        $previousLocation = trim((string) data_get($changeMap, 'location.old_value'));
                        $currentLocation = trim((string) (
                            data_get($changeMap, 'location.new_value')
                            ?? data_get($entry, 'new_values.location')
                            ?? data_get($record->data ?? [], 'location', '')
                        ));
                        $previousDepartment = trim((string) data_get($changeMap, 'department.old_value'));
                        $currentDepartment = trim((string) (
                            data_get($changeMap, 'department.new_value')
                            ?? data_get($entry, 'new_values.department')
                            ?? data_get($record->data ?? [], 'department', '')
                        ));
                        $reason = trim((string) data_get($entry, 'reason', ''));
                        $displayLocation = $currentLocation;
                        $displayDepartment = $currentDepartment;

                        if ($action === 'Stock Transfer') {
                            if (str_contains($reason, '|') && str_contains($reason, '->')) {
                                $reason = trim((string) Str::before($reason, '|'));
                            }

                            $fromLabel = collect([$previousLocation ?: null, $previousDepartment ?: null])->filter()->implode(' / ');
                            $toLabel = collect([$currentLocation ?: null, $currentDepartment ?: null])->filter()->implode(' / ');

                            if ($fromLabel !== '' || $toLabel !== '') {
                                $displayLocation = $fromLabel !== '' ? ('From: ' . $fromLabel) : 'From: Not recorded';
                                $displayDepartment = $toLabel !== '' ? ('To: ' . $toLabel) : 'To: Not recorded';
                            }
                        }

                        return [
                            'record_number' => $record->record_number ?: ('FIN-' . $record->id),
                            'record_title' => $record->record_title ?: ('Inventory Record #' . $record->id),
                            'action' => $action,
                            'changed_by' => trim((string) data_get($entry, 'changed_by', 'System')) ?: 'System',
                            'changed_at' => $changedAt !== '' ? $changedAt : optional($record->updated_at)->format('M d, Y h:i A'),
                            'reason' => $reason,
                            'quantity' => filled($quantity) ? (string) $quantity : null,
                            'available_quantity' => filled($availableQuantity) ? (string) $availableQuantity : null,
                            'previous_location' => $previousLocation,
                            'current_location' => $currentLocation,
                            'previous_department' => $previousDepartment,
                            'current_department' => $currentDepartment,
                            'location' => $displayLocation,
                            'department' => $displayDepartment,
                        ];
                    });
            })
            ->sortByDesc(fn (array $item) => strtotime((string) ($item['changed_at'] ?? '')) ?: 0)
            ->take($limit)
            ->values()
            ->all();
    }

    private function financeNotificationRecipients(FinanceRecord $record, string $action)
    {
        $owner = $record->submitted_by ? User::query()->find($record->submitted_by) : null;
        $approvers = $this->financeApproverUsersForRecord($record);
        $roleRecipients = $this->financeRoleNotificationUsers();

        if ($approvers->isEmpty()) {
            $fallbackApproverIds = collect($this->financeOfficialApproverDirectory()['default_steps'] ?? [])
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values();

            if ($fallbackApproverIds->isNotEmpty()) {
                $approvers = User::query()
                    ->whereIn('id', $fallbackApproverIds->all())
                    ->get()
                    ->values();
            }
        }

        $workflowRecipients = $roleRecipients->merge($approvers)->unique('id')->values();
        $adminRecipients = $this->financeAdminNotificationUsers();
        $pendingApprovers = $workflowRecipients->reject(fn (User $user) => collect($this->financeApprovalActions($record))
            ->contains(fn (array $actionRow) => (int) data_get($actionRow, 'approved_by') === (int) $user->id));
        $inventoryAlertRecipients = $adminRecipients;

        if ($record->module_key === 'arf') {
            $custodianId = data_get($record->data ?? [], 'custodian');
            if (is_numeric($custodianId)) {
                $custodianEmployee = Employee::query()->with('user')->find((int) $custodianId);
                if ($custodianEmployee?->user) {
                    $inventoryAlertRecipients = $inventoryAlertRecipients->merge([$custodianEmployee->user]);
                }
            }
        }

        $recipients = match ($action) {
            'submitted', 'supplier_submitted' => $workflowRecipients,
            'updated' => in_array($record->workflow_status ?? 'Uploaded', ['Submitted', 'On Hold', 'Shared'], true)
                || $record->module_key === 'crf'
                ? $workflowRecipients->merge($owner ? [$owner] : [])
                : collect($owner ? [$owner] : []),
            'partially_approved' => $pendingApprovers->merge($owner ? [$owner] : []),
            'liquidation_due' => collect($owner ? [$owner] : [])->merge($adminRecipients)->merge($workflowRecipients),
            'inventory_alert' => $inventoryAlertRecipients->merge($workflowRecipients),
            'insufficient_funds' => $workflowRecipients->merge($adminRecipients)->merge($owner ? [$owner] : []),
            'accounting_imbalance' => $workflowRecipients->merge($adminRecipients)->merge($owner ? [$owner] : []),
            'approved', 'reverted', 'held' => collect($owner ? [$owner] : [])->merge($workflowRecipients),
            'delete_requested' => $adminRecipients->merge($owner ? [$owner] : [])->merge($workflowRecipients),
            default => collect($owner ? [$owner] : [])->merge($workflowRecipients),
        };

        $recipients = $recipients
            ->merge($workflowRecipients)
            ->filter()
            ->unique('id')
            ->values();

        if ($action !== 'delete_requested') {
            $recipients = $recipients
                ->reject(fn (User $user) => Auth::check() && (int) $user->id === (int) Auth::id())
                ->values();
        }

        return $recipients;
    }

    private function sendFinanceRecordWorkflowNotification(FinanceRecord $record, string $action, ?string $reviewNote = null): void
    {
        $freshRecord = $record->fresh() ?: $record;
        $recipients = $this->financeNotificationRecipients($freshRecord, $action);

        if ($recipients->isEmpty()) {
            return;
        }

        $recordLabel = trim(implode(' - ', array_filter([
            $this->normalizeFinanceRecordNumber($freshRecord->module_key, $freshRecord->record_number),
            $freshRecord->record_title,
        ]))) ?: ($this->moduleLabel($freshRecord->module_key) . ' #' . $freshRecord->id);

        [$title, $body, $buttonLabel] = match ($action) {
            'submitted' => [
                'Finance Request Submitted: ' . $recordLabel,
                'A finance record has been submitted and is ready for review.',
                'Review Request',
            ],
            'supplier_submitted' => [
                'Supplier Completion Submitted: ' . $recordLabel,
                'A supplier has submitted the completion form and it is ready for internal review.',
                'Review Request',
            ],
            'liquidation_due' => [
                'Liquidation Due: ' . $recordLabel,
                'A cash advance is awaiting liquidation and requires follow-up.',
                'Review Record',
            ],
            'liquidation_approval' => [
                'Liquidation Approved: ' . $recordLabel,
                'A liquidation report has been approved.',
                'View Record',
            ],
            'disbursement' => [
                'Disbursement Recorded: ' . $recordLabel,
                'A finance disbursement has been recorded and linked records were updated.',
                'View Record',
            ],
            'fund_release' => [
                'Funds Released: ' . $recordLabel,
                'Funds have been released for this finance record.',
                'View Record',
            ],
            'inventory_alert' => [
                'Inventory Alert: ' . $recordLabel,
                'Stock levels are at or below the reorder threshold.',
                'Review Record',
            ],
            'insufficient_funds' => [
                'Insufficient Funds: ' . $recordLabel,
                'The available balance is lower than the requested amount. Please review the funding status before approving.',
                'Review Record',
            ],
            'accounting_imbalance' => [
                'Accounting Imbalance: ' . $recordLabel,
                'Total debit and total credit do not match. Please correct the accounting entries before approval.',
                'Review Record',
            ],
            'approved' => [
                'Finance Record Approved: ' . $recordLabel,
                'A finance record has been approved.',
                'View Record',
            ],
            'partially_approved' => [
                'Finance Approval Recorded: ' . $recordLabel,
                'A finance approval has been recorded and another approval is still required.',
                'Review Request',
            ],
            'reverted' => [
                'Finance Record Returned for Revision: ' . $recordLabel,
                'A finance record has been returned for revision.',
                'View Record',
            ],
            'held' => [
                'Finance Record Placed on Hold: ' . $recordLabel,
                'A finance record has been placed on hold.',
                'View Record',
            ],
            'updated' => [
                'Finance Record Updated: ' . $recordLabel,
                'A finance record has been updated.',
                'View Record',
            ],
            'delete_requested' => [
                'Finance Record Deletion Requested: ' . $recordLabel,
                'A finance record deletion has been requested and is pending admin approval.',
                'Review Request',
            ],
            'delete_approved' => [
                'Finance Record Deletion Approved: ' . $recordLabel,
                'A finance record has been approved for deletion.',
                'View Record',
            ],
            'delete_rejected' => [
                'Finance Record Deletion Rejected: ' . $recordLabel,
                'A finance record deletion request has been rejected.',
                'View Record',
            ],
            'archived' => [
                'Finance Record Archived: ' . $recordLabel,
                'A finance record has been archived.',
                'View Record',
            ],
            'unarchived' => [
                'Finance Record Unarchived: ' . $recordLabel,
                'A finance record has been unarchived and is now active.',
                'View Record',
            ],
            default => [
                'Finance Record Notification: ' . $recordLabel,
                'A finance record requires attention.',
                'View Record',
            ],
        };

        $recordUrl = $this->financeRecordSystemUrl($freshRecord);
        [$accentColor, $accentSoftColor, $badgeLabel] = match ($action) {
            'approved' => ['#15803d', '#dcfce7', 'Approved'],
            'partially_approved',
            'submitted',
            'supplier_submitted',
            'updated',
            'disbursement',
            'fund_release',
            'liquidation_approval' => ['#1d4ed8', '#dbeafe', 'For Review'],
            'held' => ['#b45309', '#fef3c7', 'On Hold'],
            'reverted',
            'delete_requested',
            'delete_rejected' => ['#b91c1c', '#fee2e2', 'Needs Attention'],
            'delete_approved',
            'archived',
            'unarchived' => ['#1f2937', '#e5e7eb', 'Finance Update'],
            default => ['#1d4ed8', '#dbeafe', 'Finance Update'],
        };
        $actionButtons = [
            [
                'label' => 'Open Record',
                'url' => $recordUrl,
                'color' => $accentColor,
            ],
        ];

        $notification = new FinanceRecordWorkflowNotification(
            recordId: $freshRecord->id,
            action: $action,
            title: $title,
            body: $body,
            buttonLabel: $buttonLabel,
            url: $recordUrl,
            actionButtons: $actionButtons,
            reviewNote: $reviewNote
        );

        try {
            Notification::send($recipients, $notification);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function sendFinanceAssetCustodianNotification(FinanceRecord $record, string $action): void
    {
        $freshRecord = $record->fresh() ?: $record;
        $roleRecipients = $this->financeRoleNotificationUsers();
        $custodianId = data_get($freshRecord->data ?? [], 'custodian');

        if (!is_numeric($custodianId)) {
            return;
        }

        $custodian = Employee::query()
            ->with('user')
            ->find((int) $custodianId);

        $recipient = $custodian?->user;

        if (!$recipient) {
            return;
        }

        $recordLabel = trim(implode(' - ', array_filter([
            $this->normalizeFinanceRecordNumber($freshRecord->module_key, $freshRecord->record_number),
            $freshRecord->record_title,
        ]))) ?: ($this->moduleLabel($freshRecord->module_key) . ' #' . $freshRecord->id);

        [$title, $body, $buttonLabel] = match ($action) {
            'transferred' => [
                'Asset Custodian Updated: ' . $recordLabel,
                'An asset has been transferred to a different custodian.',
                'View Asset',
            ],
            default => [
                'Asset Assigned: ' . $recordLabel,
                'An asset has been assigned to you for custody.',
                'View Asset',
            ],
        };

        try {
            Notification::send($roleRecipients->merge([$recipient])->unique('id')->values(), new FinanceRecordWorkflowNotification(
                recordId: $freshRecord->id,
                action: $action,
                title: $title,
                body: $body,
                buttonLabel: $buttonLabel,
                url: $this->financeRecordSystemUrl($freshRecord),
            ));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function sendFinanceInventoryAlertNotification(FinanceRecord $record): void
    {
        $freshRecord = $record->fresh() ?: $record;

        if ($freshRecord->module_key !== 'arf') {
            return;
        }

        if (Str::lower((string) data_get($freshRecord->data ?? [], 'item_classification')) !== 'consumable inventory') {
            return;
        }

        $currentQuantity = (float) data_get($freshRecord->data ?? [], 'current_quantity', 0);
        $reorderLevel = (float) data_get($freshRecord->data ?? [], 'reorder_level', 0);

        if ($reorderLevel <= 0 || $currentQuantity > $reorderLevel) {
            return;
        }

        $this->sendFinanceRecordWorkflowNotification($freshRecord, 'inventory_alert');
    }

    private function acceptedRecordQuery(string $moduleKey, array $dataConstraints = [])
    {
        $query = FinanceRecord::query()
            ->where('module_key', $moduleKey)
            ->where(function ($statusQuery) {
                $statusQuery->where('workflow_status', 'Accepted')
                    ->orWhere('approval_status', 'Approved');
            });

        foreach ($dataConstraints as $field => $value) {
            $query->where("data->{$field}", $value);
        }

        return $query;
    }

    private function financeRecordEligibleAsSourceDocument(FinanceRecord $record): bool
    {
        if (in_array($record->workflow_status ?? 'Uploaded', ['Cancelled', 'Reverted', 'Closed'], true)) {
            return false;
        }

        $relationshipStatus = Str::lower($this->financeDerivedRelationshipStatus($record));
        $paymentStatus = Str::lower((string) data_get($record->data ?? [], 'payment_status', data_get($record->data ?? [], 'ca_payment_status')));

        if (in_array($relationshipStatus, [
            'cancelled',
            'reverted',
            'closed',
            'completed',
            'disbursed',
            'liquidated',
            'paid',
            'fully disbursed',
            'fully released',
        ], true)) {
            return false;
        }

        if ($record->module_key === 'ca') {
            return true;
        }

        if (in_array($paymentStatus, [
            'fully disbursed',
            'fully released',
            'disbursed',
            'paid',
        ], true)) {
            return false;
        }

        return true;
    }

    private function visibleAcceptedFinanceRecords(string $moduleKey, array $dataConstraints = [])
    {
        $records = $this->financeActiveRecordsByModule($moduleKey)
            ->filter(function (FinanceRecord $record) use ($dataConstraints): bool {
                $accepted = ($record->workflow_status === 'Accepted')
                    || ($record->approval_status === 'Approved');

                if (! $accepted) {
                    return false;
                }

                foreach ($dataConstraints as $field => $value) {
                    if (data_get($record->data ?? [], $field) != $value) {
                        return false;
                    }
                }

                return $this->financeRecordEligibleAsSourceDocument($record);
            })
            ->values();

        if ($this->canApproveFinance()) {
            return $records;
        }

        return $records
            ->filter(fn (FinanceRecord $record) => $this->canViewFinanceRecord($record) || $this->currentUserCanOpenFinance())
            ->values();
    }

    private function canEditRecord(FinanceRecord $record): bool
    {
        if ($this->financeRecordIsFinalLocked($record)) {
            return false;
        }

        if ($this->canApproveFinance()) {
            return true;
        }

        return $this->currentUserCanAccessFinanceModule($record->module_key)
            && (
                (int) $record->submitted_by === (int) Auth::id()
                || $this->currentUserOwnsFinanceRevisionRequest($record)
            )
            && in_array($record->workflow_status ?? 'Uploaded', ['Uploaded', 'Reverted'], true);
    }

    private function canSubmitRecord(FinanceRecord $record): bool
    {
        if (in_array($record->workflow_status ?? 'Uploaded', ['Delete Requested', 'Deleted'], true)) {
            return false;
        }

        return $this->currentUserCanAccessFinanceModule($record->module_key)
            && (
                (int) $record->submitted_by === (int) Auth::id()
                || $this->currentUserOwnsFinanceRevisionRequest($record)
            )
            && in_array($record->workflow_status ?? 'Uploaded', ['Uploaded', 'Reverted'], true);
    }

    private function canShareSupplierRecord(FinanceRecord $record): bool
    {
        return $record->module_key === 'supplier'
            && $this->currentUserCanAccessFinanceModule($record->module_key)
            && (int) $record->submitted_by === (int) Auth::id()
            && in_array($record->workflow_status ?? 'Uploaded', ['Uploaded', 'Reverted'], true)
            && data_get($record->data, 'completion_mode') === 'send_to_supplier';
    }

    private function canRequestDeleteRecord(FinanceRecord $record): bool
    {
        if (! Auth::check()) {
            return false;
        }

        if ($this->financeRecordIsFinalLocked($record)) {
            return false;
        }

        if (in_array($record->workflow_status ?? 'Uploaded', ['Delete Requested', 'Deleted'], true)) {
            return false;
        }

        return $this->canApproveFinance()
            || ($this->currentUserCanAccessFinanceModule($record->module_key) && (int) $record->submitted_by === (int) Auth::id());
    }

    private function canApproveSubmittedFinanceRecord(FinanceRecord $record): bool
    {
        return ($this->canApproveFinance() || $this->currentUserIsFinanceApprover($record))
            && in_array($record->workflow_status ?? 'Uploaded', ['Submitted', 'Pending Approval', 'On Hold'], true)
            && in_array($record->approval_status ?? 'Pending', ['Pending', 'Partially Approved', 'On Hold'], true)
            && ! $this->currentUserHasApprovedFinanceRecord($record);
    }

    private function canRevertSubmittedFinanceRecord(FinanceRecord $record): bool
    {
        return ($this->canApproveFinance() || $this->currentUserIsFinanceApprover($record))
            && in_array($record->workflow_status ?? 'Uploaded', ['Submitted', 'On Hold'], true);
    }

    private function canHoldSubmittedFinanceRecord(FinanceRecord $record): bool
    {
        return $this->canRevertSubmittedFinanceRecord($record);
    }

    private function canArchiveFinanceRecord(FinanceRecord $record): bool
    {
        return $this->canApproveFinance()
            && in_array($record->workflow_status ?? 'Uploaded', ['Accepted', 'Reverted'], true);
    }

    private function canUnarchiveFinanceRecord(FinanceRecord $record): bool
    {
        return $this->canApproveFinance()
            && ($record->workflow_status ?? 'Uploaded') === 'Archived';
    }

    private function canApproveDeleteRequest(FinanceRecord $record): bool
    {
        return $this->canApproveFinance()
            && ($record->workflow_status ?? 'Uploaded') === 'Delete Requested';
    }

    private function financeLatestCorrectionRequest(?FinanceRecord $record): ?array
    {
        if (! $record) {
            return null;
        }

        return collect((array) data_get($record->data ?? [], 'correction_requests', []))
            ->filter(fn ($request) => is_array($request))
            ->reverse()
            ->first();
    }

    private function financeCorrectionRequestAllowed(FinanceRecord $record): bool
    {
        if (! Auth::check()) {
            return false;
        }

        return $this->financeRecordIsFinalLocked($record)
            || in_array($record->workflow_status ?? 'Uploaded', ['Completed', 'Paid', 'Disbursed', 'Liquidated', 'Closed'], true);
    }

    private function canRequestCorrectionRecord(FinanceRecord $record): bool
    {
        return $this->financeCorrectionRequestAllowed($record)
            && ((int) $record->submitted_by === (int) Auth::id() || $this->canApproveFinance());
    }

    private function canApproveCorrectionRequest(FinanceRecord $record): bool
    {
        return $this->canApproveFinance()
            && ($record->workflow_status ?? 'Uploaded') === 'Correction Requested';
    }

    private function canManageSupplierCompletion(FinanceRecord $record): bool
    {
        return $record->module_key === 'supplier'
            && data_get($record->data, 'completion_mode') === 'send_to_supplier'
            && ($this->canApproveFinance() || (int) $record->submitted_by === (int) Auth::id());
    }

    private function supplierCompletionEmailAddress(FinanceRecord $record): ?string
    {
        $email = trim((string) data_get($record->data, 'email_address', ''));

        return blank($email) ? null : $email;
    }

    private function ensureSupplierCompletionLink(FinanceRecord $record): string
    {
        $data = $this->appendFinanceHistoryEntry($record->data ?? [], 'Shared Supplier Completion', $record->module_key, [
            'workflow_status' => $record->workflow_status,
            'approval_status' => $record->approval_status,
            'shared_at' => optional($record->shared_at)->format('Y-m-d H:i:s'),
        ], [
            'workflow_status' => 'Shared',
            'approval_status' => 'Pending Supplier Completion',
            'shared_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $record->update([
            'share_token' => $record->share_token ?: Str::random(64),
            'shared_at' => now(),
            'workflow_status' => 'Shared',
            'approval_status' => 'Pending Supplier Completion',
            'data' => $data,
        ]);

        return route('finance.supplier.completion', $record->fresh()->share_token);
    }

    private function sendSupplierCompletionEmail(FinanceRecord $record): string
    {
        $email = $this->supplierCompletionEmailAddress($record);

        if (blank($email)) {
            throw ValidationException::withMessages([
                'data.email_address' => 'Supplier email address is required to send the completion form.',
            ]);
        }

        $link = $this->ensureSupplierCompletionLink($record);
        $freshRecord = $record->fresh();

        Mail::to($email)->send(
            new SupplierCompletionMail(
                $freshRecord,
                $link,
                $this->financeRecordPdfData($freshRecord),
                $this->financeRecordPdfFilename($freshRecord),
                $this->supplierCompletionLabels()
            )
        );

        return $link;
    }

    private function sendSupplierRevertEmail(FinanceRecord $record, string $reason, ?string $revertedByName = null): void
    {
        $email = $this->supplierCompletionEmailAddress($record);

        if (blank($email)) {
            return;
        }

        $freshRecord = $record->fresh() ?: $record;
        $completionUrl = $freshRecord->share_token
            ? route('finance.supplier.completion', $freshRecord->share_token)
            : null;

        Mail::to($email)->send(
            new SupplierRevertMail(
                $freshRecord,
                $reason,
                $revertedByName,
                $completionUrl,
                $this->financeRecordPdfData($freshRecord),
                $this->financeRecordPdfFilename($freshRecord)
            )
        );
    }

    private function optionLabel(FinanceRecord $record): string
    {
        $recordTitle = $this->cleanRecordTitleForDisplay($record->module_key, $record->record_title);
        $parts = array_filter([
            $this->normalizeFinanceRecordNumber($record->module_key, $record->record_number),
            $recordTitle,
        ]);

        if ($record->module_key === 'bank_account') {
            $parts[] = trim((string) data_get($record->data, 'bank_account_number', ''));
        }

        if ($parts) {
            return implode(' - ', $parts);
        }

        $dataTitle = data_get($record->data, 'title')
            ?: data_get($record->data, 'business_name')
            ?: data_get($record->data, 'account_name')
            ?: data_get($record->data, 'service_name')
            ?: data_get($record->data, 'product_name')
            ?: data_get($record->data, 'payee')
            ?: data_get($record->data, 'supplier_name');

        if ($dataTitle) {
            return (string) $dataTitle;
        }

        return $this->moduleLabel($record->module_key) . ' #' . $record->id;
    }

    private function recordPrefixForModule(string $moduleKey): string
    {
        return match ($moduleKey) {
            'supplier' => 'SUP',
            'service' => 'SRV',
            'product' => 'PRD',
            'chart_account' => 'COA',
            'bank_account' => 'BA',
            'pr' => 'PR',
            'po' => 'PO',
            'ca' => 'CA',
            'lr' => 'LR',
            'err' => 'ERR',
            'dv' => 'DV',
            'pda' => 'PDA',
            'crf' => 'CRF',
            'ibtf' => 'IBTF',
            'arf' => 'ARF',
            default => Str::upper(Str::substr($moduleKey ?: 'FIN', 0, 5)),
        };
    }

    private function normalizeFinanceRecordNumber(string $moduleKey, ?string $recordNumber): string
    {
        $value = trim((string) $recordNumber);
        $prefix = $this->recordPrefixForModule($moduleKey);

        if ($value === '') {
            return $prefix . '-' . str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        }

        if (preg_match('/^([A-Z0-9]{2,10})-(\d+)$/i', $value, $matches)) {
            $normalizedPrefix = strtoupper($matches[1]);
            $numeric = substr(str_pad($matches[2], 5, '0', STR_PAD_LEFT), -5);

            return $normalizedPrefix . '-' . $numeric;
        }

        if (preg_match('/^\d+$/', $value)) {
            return $prefix . '-' . substr(str_pad($value, 5, '0', STR_PAD_LEFT), -5);
        }

        return $value;
    }

    private function resolveLookupOptions(): array
    {
        $options = [];

        foreach (self::MODULES as $moduleKey => $label) {
            $options[$moduleKey] = $this->visibleAcceptedFinanceRecords($moduleKey)
                ->map(function (FinanceRecord $record) {
                    $option = [
                        'id' => $record->id,
                        'label' => $this->optionLabel($record),
                        'record_number' => $record->record_number,
                        'record_title' => $record->record_title,
                    ];

                    if ($record->module_key === 'chart_account') {
                        $option['account_type'] = data_get($record->data, 'account_type');
                        $option['account_group'] = data_get($record->data, 'account_group');
                        $option['account_description'] = data_get($record->data, 'account_description');
                    }

                    if ($record->module_key === 'product') {
                        $option['data'] = [
                            'product_description' => data_get($record->data, 'product_description'),
                            'category' => data_get($record->data, 'category'),
                            'default_cost' => data_get($record->data, 'default_cost'),
                            'supplier_id' => data_get($record->data, 'supplier_id'),
                            'tax_type' => data_get($record->data, 'tax_type'),
                            'unit_of_measure' => data_get($record->data, 'unit_of_measure'),
                        ];
                    }

                    if ($record->module_key === 'service') {
                        $option['data'] = [
                            'service_description' => data_get($record->data, 'service_description'),
                            'products_services_provided' => data_get($record->data, 'products_services_provided'),
                            'category' => data_get($record->data, 'category'),
                            'default_cost' => data_get($record->data, 'default_cost'),
                            'supplier_id' => data_get($record->data, 'supplier_id'),
                            'tax_type' => data_get($record->data, 'tax_type'),
                            'unit_of_measure' => data_get($record->data, 'unit_of_measure'),
                        ];
                    }

                    return $option;
                })
                ->values();
        }

        $options['dv_ca'] = $this->visibleAcceptedFinanceRecords('dv', ['source_document_type' => 'ca'])
            ->map(fn (FinanceRecord $record) => [
                'id' => $record->id,
                'label' => $this->optionLabel($record),
                'record_number' => $record->record_number,
                'record_title' => $record->record_title,
            ])
            ->values();

        $options['dv_arf'] = $this->financeActiveRecordsByModule('dv')
            ->filter(function (FinanceRecord $record): bool {
                if (! $this->canViewFinanceRecord($record)) {
                    return false;
                }

                if (in_array($record->workflow_status ?? 'Uploaded', ['Delete Requested', 'Deleted', 'Cancelled', 'Reverted'], true)) {
                    return false;
                }

                return Str::lower((string) data_get($record->data ?? [], 'source_document_type')) === 'po';
            })
            ->sortByDesc('record_date')
            ->sortByDesc('created_at')
            ->map(fn (FinanceRecord $record) => [
                'id' => $record->id,
                'label' => $this->optionLabel($record),
                'record_number' => $record->record_number,
                'record_title' => $record->record_title,
            ])
            ->values();

        $options['lr_overage'] = $this->visibleAcceptedFinanceRecords('lr', ['variance_indicator' => 'Overage'])
            ->map(fn (FinanceRecord $record) => [
                'id' => $record->id,
                'label' => $this->optionLabel($record),
                'record_number' => $record->record_number,
                'record_title' => $record->record_title,
            ])
            ->values();

        $options['lr_shortage'] = $this->visibleAcceptedFinanceRecords('lr', ['variance_indicator' => 'Shortage'])
            ->map(fn (FinanceRecord $record) => [
                'id' => $record->id,
                'label' => $this->optionLabel($record),
                'record_number' => $record->record_number,
                'record_title' => $record->record_title,
            ])
            ->values();

        $options['client'] = $this->financeClientLookupOptions();

        $options['employee'] = Schema::hasTable('employees')
            ? Employee::query()
                ->when(Schema::hasTable('departments'), fn ($query) => $query->with('department'))
                ->with('user')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get()
                ->map(fn (Employee $employee) => $this->employeeRequesterOption($employee))
                ->values()
            : collect();

        $options['payroll_period'] = PayrollPeriod::query()
            ->orderByDesc('period_start')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (PayrollPeriod $period) => $this->payrollPeriodSnapshot($period))
            ->values();

        return $options;
    }

    private function financeClientLookupOptions(): \Illuminate\Support\Collection
    {
        $contactOptions = Schema::hasTable('contacts')
            ? Contact::query()
                ->whereRaw('LOWER(COALESCE(cif_status, "")) = ?', ['approved'])
                ->with([
                    'companies:id,company_name',
                    'primaryCompanies:id,company_name,primary_contact_id',
                ])
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get([
                    'id',
                    'salutation',
                    'first_name',
                    'middle_initial',
                    'middle_name',
                    'last_name',
                    'name_extension',
                    'email',
                    'phone',
                    'contact_address',
                    'company_name',
                    'company_address',
                    'position',
                    'customer_type',
                    'client_status',
                    'cif_no',
                    'tin',
                ])
                ->map(fn (Contact $contact) => $this->financeContactClientOption($contact))
                ->values()
            : collect();

        return $contactOptions
            ->concat($this->financeCompanyPersonClientOptions($contactOptions))
            ->unique(fn (array $option) => (string) ($option['id'] ?? ''))
            ->values();
    }

    private function financeContactClientOption(Contact $contact): array
    {
        $companyNames = collect([$contact->company_name])
            ->merge($contact->companies->pluck('company_name'))
            ->merge($contact->primaryCompanies->pluck('company_name'))
            ->filter()
            ->unique()
            ->values();
        $displayName = $this->contactDisplayName($contact);
        $label = $this->contactDisplayName($contact, includeEmail: true);

        if ($companyNames->isNotEmpty()) {
            $label .= ' - '.$companyNames->implode(', ');
        }

        return [
            'id' => $contact->id,
            'label' => $label,
            'record_title' => $displayName,
            'full_name' => $displayName,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'contact_address' => $contact->contact_address,
            'company_name' => $companyNames->first() ?: $contact->company_name,
            'company_names' => $companyNames->all(),
            'company_address' => $contact->company_address,
            'position' => $contact->position,
            'customer_type' => $contact->customer_type,
            'client_status' => $contact->client_status,
            'cif_no' => $contact->cif_no,
            'tin' => $contact->tin,
            'source' => 'contacts',
        ];
    }

    private function financeCompanyPersonClientOptions(?\Illuminate\Support\Collection $contactOptions = null): \Illuminate\Support\Collection
    {
        if (! Schema::hasTable('companies') || ! Schema::hasTable('company_bifs')) {
            return collect();
        }

        $contactOptions ??= collect();

        return Company::query()
            ->with('latestBif')
            ->whereHas('latestBif', function ($query) {
                $query->whereRaw('LOWER(COALESCE(status, "")) = ?', ['approved']);
            })
            ->orderBy('company_name')
            ->get(['id', 'company_name', 'address'])
            ->flatMap(function (Company $company) use ($contactOptions) {
                $bif = $company->latestBif;

                if (! $bif || strtolower((string) $bif->status) !== 'approved') {
                    return collect();
                }

                return $this->financeCompanyBifPersonRows($bif)
                    ->reject(fn (array $person) => $this->financeCompanyPersonMatchesContact($person, $company->company_name, $contactOptions))
                    ->map(fn (array $person) => $this->financeCompanyPersonClientOption($company, $bif, $person));
            })
            ->unique(fn (array $option) => (string) ($option['id'] ?? ''))
            ->values();
    }

    private function financeCompanyBifPersonRows(CompanyBif $bif): \Illuminate\Support\Collection
    {
        $rows = collect($bif->authorized_signatories ?? [])
            ->filter(fn (array $item) => filled($item['full_name'] ?? null))
            ->map(fn (array $item) => $this->financeCompanyPersonRow($item, 'Authorized Signatory'));

        if ($rows->isEmpty() && filled($bif->authorized_signatory_name)) {
            $rows->push($this->financeCompanyPersonRow([
                'full_name' => $bif->authorized_signatory_name,
                'address' => $bif->authorized_signatory_address,
                'nationality' => $bif->authorized_signatory_nationality,
                'date_of_birth' => optional($bif->authorized_signatory_date_of_birth)?->format('Y-m-d'),
                'tin' => $bif->authorized_signatory_tin,
                'position' => $bif->authorized_signatory_position,
            ], 'Authorized Signatory'));
        }

        $ubos = collect($bif->ubos ?? [])
            ->filter(fn (array $item) => filled($item['full_name'] ?? null))
            ->map(fn (array $item) => $this->financeCompanyPersonRow($item, 'UBO (20%+ Stockholder)'));

        if ($ubos->isEmpty() && filled($bif->ubo_name)) {
            $ubos->push($this->financeCompanyPersonRow([
                'full_name' => $bif->ubo_name,
                'address' => $bif->ubo_address,
                'nationality' => $bif->ubo_nationality,
                'date_of_birth' => optional($bif->ubo_date_of_birth)?->format('Y-m-d'),
                'tin' => $bif->ubo_tin,
                'position' => $bif->ubo_position,
            ], 'UBO (20%+ Stockholder)'));
        }

        if (filled($bif->authorized_contact_person_name)) {
            $rows->push($this->financeCompanyPersonRow([
                'full_name' => $bif->authorized_contact_person_name,
                'position' => $bif->authorized_contact_person_position,
                'email' => $bif->authorized_contact_person_email,
                'phone' => $bif->authorized_contact_person_phone,
            ], 'Authorized Contact Person'));
        }

        return $rows
            ->concat($ubos)
            ->unique(fn (array $item) => Str::lower(($item['role_label'] ?? '').'|'.($item['full_name'] ?? '').'|'.($item['email'] ?? '').'|'.($item['phone'] ?? '')))
            ->values();
    }

    private function financeCompanyPersonRow(array $item, string $roleLabel): array
    {
        return [
            'role_label' => $roleLabel,
            'full_name' => trim((string) ($item['full_name'] ?? '')),
            'position' => $item['position'] ?? null,
            'email' => $item['email'] ?? null,
            'phone' => $item['phone'] ?? null,
            'address' => $item['address'] ?? null,
            'nationality' => $item['nationality'] ?? null,
            'date_of_birth' => $item['date_of_birth'] ?? null,
            'tin' => $item['tin'] ?? null,
        ];
    }

    private function financeCompanyPersonClientOption(Company $company, CompanyBif $bif, array $person): array
    {
        $fullName = trim((string) ($person['full_name'] ?? ''));
        $roleLabel = trim((string) ($person['role_label'] ?? 'Company Person'));
        $companyName = trim((string) $company->company_name);
        $email = trim((string) ($person['email'] ?? ''));
        $label = $fullName;

        if ($email !== '') {
            $label .= " ({$email})";
        }

        $label .= ' - '.$roleLabel;

        if ($companyName !== '') {
            $label .= ' - '.$companyName;
        }

        return [
            'id' => $this->financeCompanyPersonClientId($company->id, $roleLabel, $person),
            'label' => $label,
            'record_title' => $fullName,
            'full_name' => $fullName,
            'email' => $email,
            'phone' => $person['phone'] ?? null,
            'contact_address' => $person['address'] ?? null,
            'company_name' => $companyName,
            'company_names' => [$companyName],
            'company_address' => $company->address,
            'position' => $person['position'] ?? null,
            'customer_type' => 'Company BIF Person',
            'client_status' => null,
            'cif_no' => null,
            'tin' => $person['tin'] ?? null,
            'source' => 'company_bif',
            'company_id' => $company->id,
            'company_bif_id' => $bif->id,
            'role_label' => $roleLabel,
        ];
    }

    private function financeCompanyPersonClientId(int $companyId, string $roleLabel, array $person): string
    {
        $signature = Str::lower(implode('|', [
            $roleLabel,
            $person['full_name'] ?? '',
            $person['email'] ?? '',
            $person['phone'] ?? '',
        ]));

        return 'company-person:'.$companyId.':'.Str::slug($roleLabel ?: 'person').':'.substr(sha1($signature), 0, 12);
    }

    private function financeCompanyPersonMatchesContact(array $person, string $companyName, \Illuminate\Support\Collection $contactOptions): bool
    {
        $email = Str::lower(trim((string) ($person['email'] ?? '')));
        $phone = preg_replace('/\D+/', '', (string) ($person['phone'] ?? ''));
        $fullName = Str::lower(trim((string) ($person['full_name'] ?? '')));
        $companyName = Str::lower(trim($companyName));

        if ($email === '' && $phone === '' && $fullName === '') {
            return false;
        }

        return $contactOptions->contains(function (array $option) use ($email, $phone, $fullName, $companyName) {
            if ($email !== '' && Str::lower(trim((string) ($option['email'] ?? ''))) === $email) {
                return true;
            }

            if ($phone !== '' && preg_replace('/\D+/', '', (string) ($option['phone'] ?? '')) === $phone) {
                return true;
            }

            $optionName = Str::lower(trim((string) ($option['full_name'] ?? $option['record_title'] ?? '')));
            $optionCompanyNames = collect($option['company_names'] ?? [])
                ->push($option['company_name'] ?? '')
                ->filter()
                ->map(fn ($name) => Str::lower(trim((string) $name)));

            return $fullName !== ''
                && $optionName === $fullName
                && ($companyName === '' || $optionCompanyNames->contains($companyName));
        });
    }

    private function employeeRequesterOption(Employee $employee): array
    {
        $employeeName = trim(collect([$employee->first_name, $employee->last_name])->filter()->implode(' '));
        $departmentName = $employee->relationLoaded('department')
            ? (string) ($employee->department?->department_name ?? '')
            : '';
        $superior = $employee->relationLoaded('department')
            ? (string) ($employee->department?->department_head ?? '')
            : '';
        $superiorEmail = $this->resolveEmployeeSuperiorEmail($superior);
        $labelParts = array_filter([
            $employee->employee_code,
            $employeeName ?: null,
            $employee->email ? "({$employee->email})" : null,
        ]);

        return [
            'id' => $employee->id,
            'user_id' => $employee->user_id ? (int) $employee->user_id : null,
            'label' => implode(' - ', $labelParts) ?: 'Employee #' . $employee->id,
            'record_title' => $employeeName,
            'full_name' => $employeeName,
            'user_name' => $employee->user?->name ?: $employeeName,
            'employee_id' => $employee->employee_code,
            'employee_code' => $employee->employee_code,
            'employee_email' => $employee->email,
            'user_email' => $employee->user?->email ?: $employee->login_email ?: $employee->email,
            'email' => $employee->email,
            'contact_number' => $employee->phone_number,
            'phone_number' => $employee->phone_number,
            'phone' => $employee->phone_number,
            'position' => $employee->position,
            'department' => $departmentName,
            'department_name' => $departmentName,
            'superior' => $superior,
            'superior_email' => $superiorEmail,
            'address' => $employee->address,
        ];
    }

    private function financeAssetEventLabel(string $eventType): string
    {
        return match ($eventType) {
            'acknowledged' => 'Asset Acknowledged',
            'transfer' => 'Asset Transferred',
            'loss' => 'Asset Reported Lost',
            'damage' => 'Asset Reported Damaged',
            'return' => 'Asset Returned',
            'disposal' => 'Asset Disposed',
            'stock_in' => 'Stock In',
            'stock_out' => 'Stock Out',
            'stock_transfer' => 'Stock Transfer',
            'stock_return' => 'Stock Return',
            'stock_adjustment' => 'Stock Adjustment',
            default => Str::headline(str_replace('_', ' ', $eventType)),
        };
    }

    private function financeAssetStatusForEvent(string $eventType): string
    {
        return match ($eventType) {
            'acknowledged' => 'Acknowledged',
            'transfer' => 'Transferred',
            'loss' => 'Lost',
            'damage' => 'Damaged',
            'return' => 'Returned',
            'disposal' => 'Disposed',
            'stock_in' => 'In Stock',
            'stock_out' => 'Issued',
            'stock_transfer' => 'Transferred',
            'stock_return' => 'Returned',
            'stock_adjustment' => 'Adjusted',
            default => 'Active',
        };
    }

    private function financeAssetEventAffectsInventory(string $eventType): bool
    {
        return in_array($eventType, ['stock_in', 'stock_out', 'stock_transfer', 'stock_return', 'stock_adjustment'], true);
    }

    private function financeAssetCurrentUserEmployeeId(): ?int
    {
        return Auth::user()?->employeeProfile?->id ? (int) Auth::user()->employeeProfile->id : null;
    }

    private function financeAssetCustodianEmployee(FinanceRecord $record): ?Employee
    {
        $custodianId = data_get($record->data ?? [], 'custodian');

        if (blank($custodianId) || !Schema::hasTable('employees')) {
            return null;
        }

        return Employee::query()
            ->when(Schema::hasTable('departments'), fn ($query) => $query->with('department'))
            ->find($custodianId);
    }

    private function financeAssetCustodianDisplayName(FinanceRecord $record): string
    {
        $employee = $this->financeAssetCustodianEmployee($record);

        if (! $employee) {
            return data_get($record->data ?? [], 'custodian_name') ?: $this->financePdfLookupLabel($this->resolveLookupOptions(), 'employee', data_get($record->data ?? [], 'custodian')) ?: 'N/A';
        }

        return $this->employeeRequesterOption($employee)['label'] ?? ($employee->full_name ?: $employee->employee_code ?: 'N/A');
    }

    private function financeAssetCanAcknowledge(FinanceRecord $record): bool
    {
        if ($record->module_key !== 'arf') {
            return false;
        }

        $custodianId = (int) data_get($record->data ?? [], 'custodian', 0);
        $currentEmployeeId = $this->financeAssetCurrentUserEmployeeId();

        if ($custodianId <= 0 || $currentEmployeeId === null) {
            return false;
        }

        return $custodianId === $currentEmployeeId
            && blank(data_get($record->data ?? [], 'custodian_acknowledged_at'));
    }

    private function financeAssetCanManage(FinanceRecord $record): bool
    {
        return $this->canAdministerFinance()
            || (int) $record->submitted_by === (int) Auth::id()
            || $this->financeAssetCanAcknowledge($record);
    }

    private function financeSendAssetCustodianNotification(FinanceRecord $record, string $action, ?string $note = null): void
    {
        if ($record->module_key !== 'arf') {
            return;
        }

        $employee = $this->financeAssetCustodianEmployee($record);
        if (! $employee) {
            return;
        }

        $recordLabel = trim(implode(' - ', array_filter([
            $this->normalizeFinanceRecordNumber($record->module_key, $record->record_number),
            $record->record_title,
        ]))) ?: ($this->moduleLabel($record->module_key) . ' #' . $record->id);

        [$title, $body, $buttonLabel] = match ($action) {
            'assigned' => [
                'Asset Custodian Assigned: ' . $recordLabel,
                'You have been assigned as custodian for this asset. Please review and acknowledge receipt.',
                'View Asset',
            ],
            'transferred' => [
                'Asset Custodian Updated: ' . $recordLabel,
                'The custodian assignment for this asset has been updated. Please review the asset details and acknowledgement status.',
                'View Asset',
            ],
            'acknowledged' => [
                'Asset Receipt Acknowledged: ' . $recordLabel,
                'The assigned custodian acknowledged receipt of this asset.',
                'View Asset',
            ],
            default => [
                'Asset Update: ' . $recordLabel,
                'An asset record assigned to you has been updated.',
                'View Asset',
            ],
        };

        $notification = new FinanceRecordWorkflowNotification(
            recordId: $record->id,
            action: $action,
            title: $title,
            body: $body,
            buttonLabel: $buttonLabel,
            url: route('finance.preview.html', $record->id),
            reviewNote: $note
        );

        if ($employee->user) {
            Notification::send($employee->user, $notification);
            return;
        }

        $email = $employee->login_email ?: $employee->email;
        if (filled($email)) {
            Notification::route('mail', $email)->notify($notification);
        }
    }

    private function financeAppendAssetEventHistory(FinanceRecord $record, string $eventType, array $oldValues = [], array $newValues = [], ?string $note = null, ?array $baseData = null): array
    {
        $data = $baseData ?? ($record->data ?? []);
        $data = $this->appendFinanceHistoryEntry($data, $this->financeAssetEventLabel($eventType), $record->module_key, $oldValues, $newValues, $note);
        $data['asset_last_event'] = $this->financeAssetEventLabel($eventType);
        $data['asset_status'] = $this->financeAssetStatusForEvent($eventType);
        $data['asset_last_event_at'] = now()->toDateTimeString();
        $data['asset_last_event_by'] = Auth::user()?->name ?: 'System';
        if ($note !== null) {
            $data['asset_last_event_note'] = trim($note);
        }

        return $data;
    }

    private function financeSyncConsumableInventoryCosts(array $data): array
    {
        if (Str::lower((string) data_get($data, 'item_classification')) !== 'consumable inventory') {
            return $data;
        }

        $currentQuantity = max((float) data_get($data, 'current_quantity', data_get($data, 'accepted_quantity', data_get($data, 'beginning_quantity', 0))), 0);
        $reservedQuantity = max((float) data_get($data, 'reserved_quantity', 0), 0);
        $unitCost = max((float) data_get($data, 'unit_cost', 0), 0);
        $averageCost = max((float) data_get($data, 'average_cost', 0), 0);
        $lastPurchaseCost = max((float) data_get($data, 'last_purchase_cost', 0), 0);
        $acquisitionCost = max((float) data_get($data, 'acquisition_cost', 0), 0);

        if ($unitCost <= 0 && $currentQuantity > 0 && $acquisitionCost > 0) {
            $unitCost = $acquisitionCost / $currentQuantity;
        }

        if ($unitCost <= 0 && $averageCost > 0) {
            $unitCost = $averageCost;
        }

        if ($unitCost <= 0 && $lastPurchaseCost > 0) {
            $unitCost = $lastPurchaseCost;
        }

        if ($averageCost <= 0 && $unitCost > 0) {
            $averageCost = $unitCost;
        }

        if ($lastPurchaseCost <= 0 && $unitCost > 0) {
            $lastPurchaseCost = $unitCost;
        }

        data_set($data, 'unit_cost', number_format($unitCost, 2, '.', ''));
        data_set($data, 'average_cost', number_format($averageCost, 2, '.', ''));
        data_set($data, 'last_purchase_cost', number_format($lastPurchaseCost, 2, '.', ''));
        data_set($data, 'available_quantity', number_format(max($currentQuantity - $reservedQuantity, 0), 2, '.', ''));
        data_set($data, 'total_cost', number_format($currentQuantity * $unitCost, 2, '.', ''));

        if (blank(data_get($data, 'acquisition_cost')) && $currentQuantity > 0 && $unitCost > 0) {
            data_set($data, 'acquisition_cost', number_format($currentQuantity * $unitCost, 2, '.', ''));
        }

        return $data;
    }

    private function payrollPeriodSnapshot(PayrollPeriod $period, bool $persistMissingSummaries = false): array
    {
        $summaries = PayrollSummary::query()
            ->with(['employee', 'payrollLevel.salaryGrade', 'items'])
            ->where('payroll_period_id', $period->id)
            ->get();

        if ($persistMissingSummaries || $summaries->isEmpty()) {
            $generatedSummaries = $this->generatePayrollSummariesForPeriod($period, $persistMissingSummaries);
            if ($generatedSummaries->isNotEmpty()) {
                $summaries = $generatedSummaries;
            }
        }

        $employeeLines = $summaries->map(function (PayrollSummary $summary) {
            $breakdown = $summary->breakdown_json ?? [];
            $employee = $summary->employee;
            $level = $summary->payrollLevel;
            $grade = $level?->salaryGrade;

            return [
                'employee_id' => $summary->employee_id,
                'employee_code' => $employee?->employee_code,
                'employee_name' => trim((string) ($employee?->full_name ?: $employee?->name ?: '')),
                'salary_grade' => $grade?->name,
                'salary_grade_code' => $grade?->code,
                'payroll_level' => $level?->level_name,
                'computation_type' => $summary->computation_type,
                'work_schedule' => data_get($breakdown, 'work_schedule') ?: $level?->work_schedule_label,
                'hours_per_day' => (float) data_get($breakdown, 'hours_per_day', $level?->hours_per_day ?: 0),
                'monthly_basic_salary' => (float) data_get($breakdown, 'monthly_basic_salary', 0),
                'yearly_basic_salary' => (float) data_get($breakdown, 'yearly_basic_salary', 0),
                'applicable_daily_rate' => (float) data_get($breakdown, 'applicable_daily_rate', 0),
                'hourly_rate' => (float) data_get($breakdown, 'hourly_rate', 0),
                'minute_rate' => (float) data_get($breakdown, 'minute_rate', 0),
                'gross_pay' => (float) $summary->gross_pay,
                'total_benefits' => (float) $summary->total_benefits,
                'total_allowances' => (float) $summary->total_allowances,
                'total_deductions' => (float) $summary->total_deductions,
                'night_differential_amount' => (float) $summary->night_differential_amount,
                'holiday_pay_amount' => (float) $summary->holiday_pay_amount,
                'net_pay' => (float) $summary->net_pay,
                'status' => $summary->status,
            ];
        })->values();

        $periodLabel = trim(sprintf(
            '%s (%s to %s)',
            $period->name,
            optional($period->period_start)->format('Y-m-d') ?: 'N/A',
            optional($period->period_end)->format('Y-m-d') ?: 'N/A'
        ));

        return [
            'id' => $period->id,
            'label' => $periodLabel,
            'record_title' => $period->name,
            'period_start' => optional($period->period_start)->format('Y-m-d'),
            'period_end' => optional($period->period_end)->format('Y-m-d'),
            'payroll_start' => optional($period->payroll_start)->format('Y-m-d'),
            'payroll_end' => optional($period->payroll_end)->format('Y-m-d'),
            'pay_date' => optional($period->pay_date)->format('Y-m-d'),
            'dispute_start' => optional($period->dispute_start)->format('Y-m-d'),
            'dispute_end' => optional($period->dispute_end)->format('Y-m-d'),
            'status' => $period->status,
            'employee_count' => $employeeLines->count(),
            'basic_salary_total' => round((float) $employeeLines->sum('monthly_basic_salary'), 2),
            'yearly_basic_total' => round((float) $employeeLines->sum('yearly_basic_salary'), 2),
            'daily_rate_total' => round((float) $employeeLines->sum('applicable_daily_rate'), 2),
            'hourly_rate_total' => round((float) $employeeLines->sum('hourly_rate'), 2),
            'minute_rate_total' => round((float) $employeeLines->sum('minute_rate'), 4),
            'gross_pay_total' => round((float) $employeeLines->sum('gross_pay'), 2),
            'benefits_total' => round((float) $employeeLines->sum('total_benefits'), 2),
            'allowances_total' => round((float) $employeeLines->sum('total_allowances'), 2),
            'deductions_total' => round((float) $employeeLines->sum('total_deductions'), 2),
            'night_differential_total' => round((float) $employeeLines->sum('night_differential_amount'), 2),
            'holiday_pay_total' => round((float) $employeeLines->sum('holiday_pay_amount'), 2),
            'total_payroll_amount' => round((float) $employeeLines->sum('net_pay'), 2),
            'payroll_summary_ids' => $summaries->pluck('id')->filter()->values()->all(),
            'employee_lines' => $employeeLines->all(),
        ];
    }

    private function generatePayrollSummariesForPeriod(PayrollPeriod $period, bool $persist): \Illuminate\Support\Collection
    {
        $profiles = EmployeePayrollProfile::query()
            ->with(['employee', 'payrollLevel.salaryGrade'])
            ->get();

        if ($profiles->isEmpty()) {
            return collect();
        }

        $calculator = app(PayrollCalculator::class);
        $summaries = collect();

        foreach ($profiles as $profile) {
            if (! $profile->employee || ! $profile->payrollLevel || ! $profile->payrollLevel->salaryGrade) {
                continue;
            }

            $computed = $calculator->compute($profile, $period);

            if ($persist) {
                $summary = PayrollSummary::updateOrCreate(
                    [
                        'employee_id' => $profile->employee_id,
                        'payroll_period_id' => $period->id,
                    ],
                    [
                        'payroll_level_id' => $profile->payroll_level_id,
                        'computation_type' => $computed['computation_type'],
                        'gross_pay' => $computed['gross_pay'],
                        'total_benefits' => $computed['total_benefits'],
                        'total_allowances' => $computed['total_allowances'],
                        'total_deductions' => $computed['total_deductions'],
                        'night_differential_amount' => $computed['night_differential_amount'],
                        'holiday_pay_amount' => $computed['holiday_pay_amount'],
                        'net_pay' => $computed['net_pay'],
                        'breakdown_json' => $computed['breakdown'],
                        'status' => 'generated',
                    ]
                );

                PayrollSummaryItem::where('payroll_summary_id', $summary->id)->delete();
                foreach ($computed['items'] as $item) {
                    PayrollSummaryItem::create([
                        'payroll_summary_id' => $summary->id,
                        'item_type' => $item['item_type'],
                        'category' => $item['category'],
                        'name' => $item['name'],
                        'amount' => $item['amount'],
                        'meta_json' => $item['meta_json'] ?? null,
                    ]);
                }

                $summary->load(['employee', 'payrollLevel.salaryGrade', 'items']);
                $summaries->push($summary);
                continue;
            }

            $summary = new PayrollSummary([
                'employee_id' => $profile->employee_id,
                'payroll_period_id' => $period->id,
                'payroll_level_id' => $profile->payroll_level_id,
                'computation_type' => $computed['computation_type'],
                'gross_pay' => $computed['gross_pay'],
                'total_benefits' => $computed['total_benefits'],
                'total_allowances' => $computed['total_allowances'],
                'total_deductions' => $computed['total_deductions'],
                'night_differential_amount' => $computed['night_differential_amount'],
                'holiday_pay_amount' => $computed['holiday_pay_amount'],
                'net_pay' => $computed['net_pay'],
                'breakdown_json' => $computed['breakdown'],
                'status' => 'computed',
            ]);
            $summary->setRelation('employee', $profile->employee);
            $summary->setRelation('payrollLevel', $profile->payrollLevel);
            $summaries->push($summary);
        }

        return $summaries;
    }

    private function resolveCurrentUserContactProfile(): ?array
    {
        $user = Auth::user();

        if (! $user) {
            return null;
        }

        $email = trim((string) $user->email);
        $name = trim((string) $user->name);

        if ($email === '' && $name === '') {
            return null;
        }

        $contact = Schema::hasTable('contacts')
            ? Contact::query()
                ->where(function ($query) use ($email, $name) {
                    if ($email !== '') {
                        $query->where('email', $email);
                    }

                    if ($name !== '') {
                        $method = $email !== '' ? 'orWhereRaw' : 'whereRaw';
                        $query->{$method}("TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) = ?", [$name])
                            ->orWhereRaw("TRIM(CONCAT_WS(' ', first_name, last_name)) = ?", [$name]);
                    }
                })
                ->first()
            : null;

        $employee = $this->resolveCurrentUserEmployee($email, $name);

        $userProfileFields = [
            $user->getAttribute('employee_id'),
            $user->getAttribute('employee_code'),
            $user->getAttribute('phone'),
            $user->getAttribute('contact_number'),
            $user->getAttribute('position'),
            $user->getAttribute('department'),
            $user->getAttribute('superior'),
            $user->getAttribute('superior_email'),
        ];

        if (! $contact && ! $employee && ! collect($userProfileFields)->contains(fn ($value) => filled($value))) {
            return null;
        }

        $employeeName = $employee
            ? trim(collect([$employee->first_name, $employee->last_name])->filter()->implode(' '))
            : '';
        $departmentName = $employee?->relationLoaded('department')
            ? (string) ($employee->department?->department_name ?? '')
            : '';
        $superior = $employee?->relationLoaded('department')
            ? (string) ($employee->department?->department_head ?? '')
            : '';
        $superiorEmail = $this->resolveEmployeeSuperiorEmail($superior);

        return [
            'id' => $contact?->id,
            'contact_id' => $contact?->id,
            'employee_record_id' => $employee?->id,
            'name' => $this->firstFilledFinanceValue($employeeName, $contact ? $this->contactDisplayName($contact) : null, $name),
            'email' => $this->firstFilledFinanceValue($employee?->email, $contact?->email, $email),
            'phone' => $this->firstFilledFinanceValue($employee?->phone_number, $contact?->phone, $user->getAttribute('phone'), $user->getAttribute('contact_number')),
            'contact_number' => $this->firstFilledFinanceValue($employee?->phone_number, $contact?->phone, $user->getAttribute('contact_number'), $user->getAttribute('phone')),
            'employee_id' => $this->firstFilledFinanceValue($employee?->employee_code, $user->getAttribute('employee_id'), $user->getAttribute('employee_code')),
            'employee_code' => $this->firstFilledFinanceValue($employee?->employee_code, $user->getAttribute('employee_code'), $user->getAttribute('employee_id')),
            'position' => $this->firstFilledFinanceValue($employee?->position, $contact?->position, $user->getAttribute('position')),
            'department' => $this->firstFilledFinanceValue($departmentName, $contact?->company_name, $user->getAttribute('department')),
            'company_name' => $contact?->company_name,
            'address' => $this->firstFilledFinanceValue($employee?->address, $contact?->contact_address),
            'contact_address' => $this->firstFilledFinanceValue($contact?->contact_address, $employee?->address),
            'superior' => $this->firstFilledFinanceValue($superior, $user->getAttribute('superior')),
            'superior_email' => $this->firstFilledFinanceValue($superiorEmail, $user->getAttribute('superior_email')),
            'cif_no' => $contact?->cif_no,
            'tin' => $contact?->tin,
        ];
    }

    private function resolveCurrentUserEmployee(string $email, string $name): ?Employee
    {
        if (! Schema::hasTable('employees')) {
            return null;
        }

        $query = Employee::query();

        if (Schema::hasTable('departments')) {
            $query->with('department');
        }

        return $query
            ->where(function ($query) use ($email, $name) {
                if ($email !== '') {
                    $query->where('email', $email);
                }

                if ($name !== '') {
                    $method = $email !== '' ? 'orWhereRaw' : 'whereRaw';
                    $query->{$method}("TRIM(CONCAT_WS(' ', first_name, last_name)) = ?", [$name]);
                }
            })
            ->first();
    }

    private function resolveEmployeeSuperiorEmail(string $superior): string
    {
        $superior = trim($superior);

        if ($superior === '') {
            return '';
        }

        if (Schema::hasTable('employees')) {
            $employee = Employee::query()
                ->whereRaw("TRIM(CONCAT_WS(' ', first_name, last_name)) = ?", [$superior])
                ->first(['email']);

            if ($employee?->email) {
                return $employee->email;
            }
        }

        if (Schema::hasTable('users')) {
            $user = \App\Models\User::query()
                ->where('name', $superior)
                ->first(['email']);

            if ($user?->email) {
                return $user->email;
            }
        }

        return '';
    }

    private function firstFilledFinanceValue(mixed ...$values): mixed
    {
        foreach ($values as $value) {
            if (! blank($value)) {
                return $value;
            }
        }

        return '';
    }

    private function contactDisplayName(Contact $contact, bool $includeEmail = false): string
    {
        $name = trim(collect([
            $contact->salutation,
            $contact->first_name,
            $contact->middle_name ?: $contact->middle_initial,
            $contact->last_name,
            $contact->name_extension,
        ])->filter()->implode(' '));

        if ($name === '') {
            $name = trim((string) ($contact->company_name ?: $contact->email ?: 'Contact #'.$contact->id));
        }

        if ($includeEmail && filled($contact->email)) {
            return "{$name} ({$contact->email})";
        }

        return $name;
    }

    private function transformRecord(FinanceRecord $record): array
    {
        $snapshot = $this->financeLifecycleSnapshot($record);
        $data = array_merge($record->data ?? [], $snapshot, [
            'relationship_status' => $snapshot['relationship_status'] ?? $record->relationship_status,
            'next_action' => $snapshot['next_action'] ?? $record->next_action,
            'disbursement_status' => $snapshot['disbursement_status'] ?? $record->disbursement_status,
            'transaction_progress' => $snapshot['transaction_progress'] ?? $record->transaction_progress,
        ]);
        $existingDvPayload = is_array(data_get($data, 'dv_payload')) ? data_get($data, 'dv_payload') : [];
        $relationshipStatus = (string) ($snapshot['relationship_status'] ?? $data['relationship_status'] ?: $this->financeSystemRelationshipStatus($record));

        if ($record->module_key === 'arf') {
            $data['custodian_name'] = data_get($data, 'custodian_name') ?: $this->financeAssetCustodianDisplayName($record);
            $data['asset_status'] = data_get($data, 'asset_status') ?: 'Active';
            $data['asset_last_event'] = data_get($data, 'asset_last_event') ?: 'Asset Registered';
        }

        if ($record->module_key === 'dv') {
            $data['line_items'] = $this->financeNormalizedDvLineItems($record, $data);
            $data = array_merge($data, $this->financeDvAccountingSummaryFromLineItems((array) $data['line_items']));
        }

        $data['line_items'] = $this->financeTransformLineItemReceiptAttachments((array) data_get($data, 'line_items', []));
        $attachments = $this->financeTransformRecordAttachments($record, $data);

        return [
            'id' => $record->id,
            'module_key' => $record->module_key,
            'module_label' => $this->moduleLabel($record->module_key),
            'record_number' => $this->normalizeFinanceRecordNumber($record->module_key, $record->record_number),
            'record_title' => $this->cleanRecordTitleForDisplay($record->module_key, $record->record_title),
            'display_label' => $this->optionLabel($record),
            'record_date' => optional($record->record_date)->format('Y-m-d'),
            'amount' => $record->amount,
            'status' => $this->financeSystemStatusForRecord($record),
            'relationship_status' => $relationshipStatus,
            'next_action' => $data['next_action'] ?? null,
            'disbursement_status' => $data['disbursement_status'] ?? null,
            'transaction_progress' => $data['transaction_progress'] ?? [],
            'workflow_status' => $record->workflow_status ?? 'Uploaded',
            'approval_status' => $record->approval_status ?? 'Pending',
            'submitted_by' => $record->submitted_by,
            'submitted_by_name' => $this->financeSubmittedByName($record),
            'submitted_at' => optional($record->submitted_at)->format('Y-m-d H:i:s'),
            'approved_by' => $record->approved_by,
            'approved_by_name' => $this->financeUserDisplayName($record->approved_by),
            'approval_actor_names' => $this->financeApprovalActorNames($record),
            'approved_at' => optional($record->approved_at)->format('Y-m-d H:i:s'),
            'review_note' => $record->review_note,
            'visible_finance_notes' => $this->financeVisibleNotesForRecord($record),
            'data' => array_merge($data, [
                'dv_payload' => array_merge(
                    $this->fallbackDvPayload($record),
                    $existingDvPayload
                ),
            ]),
            'attachments' => $attachments,
            'share_token' => $record->share_token,
            'shared_at' => optional($record->shared_at)->format('Y-m-d H:i:s'),
            'supplier_completed_at' => optional($record->supplier_completed_at)->format('Y-m-d H:i:s'),
            'user' => $record->user,
            'can_edit' => $this->canEditRecord($record),
            'can_submit' => $this->canSubmitRecord($record),
            'can_share_supplier' => $this->canShareSupplierRecord($record),
            'can_review' => $this->canApproveSubmittedFinanceRecord($record),
            'can_approve' => $this->canApproveSubmittedFinanceRecord($record),
            'can_revert' => $this->canRevertSubmittedFinanceRecord($record),
            'can_hold' => $this->canHoldSubmittedFinanceRecord($record),
            'can_archive' => $this->canArchiveFinanceRecord($record),
            'can_unarchive' => $this->canUnarchiveFinanceRecord($record),
            'can_request_delete' => $this->canRequestDeleteRecord($record),
            'can_approve_delete' => $this->canApproveDeleteRequest($record),
            'can_add_note' => $this->financeCurrentUserCanAddNote($record),
            'can_acknowledge_asset' => $this->financeAssetCanAcknowledge($record),
            'ownership' => $this->financeOwnershipSummary($record),
            'supplier_completion_url' => $record->share_token
                ? route('finance.supplier.completion', $record->share_token)
                : null,
        ];
    }

    private function transformRecordForIndex(FinanceRecord $record): array
    {
        $data = $record->data ?? [];
        $data['line_items'] = $this->financeTransformLineItemReceiptAttachments((array) data_get($data, 'line_items', []));
        $attachments = $this->financeTransformRecordAttachments($record, $data);

        return [
            'id' => $record->id,
            'module_key' => $record->module_key,
            'module_label' => $this->moduleLabel($record->module_key),
            'record_number' => $this->normalizeFinanceRecordNumber($record->module_key, $record->record_number),
            'record_title' => $this->cleanRecordTitleForDisplay($record->module_key, $record->record_title),
            'display_label' => $this->optionLabel($record),
            'record_date' => optional($record->record_date)->format('Y-m-d'),
            'amount' => $record->amount,
            'status' => $record->status ?: 'Draft',
            'relationship_status' => $record->relationship_status,
            'next_action' => $record->next_action,
            'disbursement_status' => $record->disbursement_status,
            'transaction_progress' => [],
            'workflow_status' => $record->workflow_status ?? 'Uploaded',
            'approval_status' => $record->approval_status ?? 'Pending',
            'submitted_by' => $record->submitted_by,
            'submitted_by_name' => $this->financeSubmittedByName($record),
            'submitted_at' => optional($record->submitted_at)->format('Y-m-d H:i:s'),
            'approved_by' => $record->approved_by,
            'approved_by_name' => $this->financeUserDisplayName($record->approved_by),
            'approval_actor_names' => [],
            'approved_at' => optional($record->approved_at)->format('Y-m-d H:i:s'),
            'review_note' => $record->review_note,
            'visible_finance_notes' => [],
            'data' => $data,
            'attachments' => $attachments,
            'share_token' => $record->share_token,
            'shared_at' => optional($record->shared_at)->format('Y-m-d H:i:s'),
            'supplier_completed_at' => optional($record->supplier_completed_at)->format('Y-m-d H:i:s'),
            'user' => $record->user,
            'can_edit' => $this->canEditRecord($record),
            'can_submit' => $this->canSubmitRecord($record),
            'can_share_supplier' => $this->canShareSupplierRecord($record),
            'can_review' => $this->canApproveSubmittedFinanceRecord($record),
            'can_approve' => $this->canApproveSubmittedFinanceRecord($record),
            'can_revert' => $this->canRevertSubmittedFinanceRecord($record),
            'can_hold' => $this->canHoldSubmittedFinanceRecord($record),
            'can_archive' => $this->canArchiveFinanceRecord($record),
            'can_unarchive' => $this->canUnarchiveFinanceRecord($record),
            'can_request_delete' => $this->canRequestDeleteRecord($record),
            'can_approve_delete' => $this->canApproveDeleteRequest($record),
            'can_add_note' => $this->financeCurrentUserCanAddNote($record),
            'can_acknowledge_asset' => $this->financeAssetCanAcknowledge($record),
            'ownership' => [],
            'supplier_completion_url' => $record->share_token
                ? route('finance.supplier.completion', $record->share_token)
                : null,
        ];
    }

    private function financeActionResponse(Request $request, string $message, FinanceRecord $record, int $status = 200)
    {
        $this->syncFinanceRelationshipLifecycle($record->fresh() ?: $record);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'data' => $this->transformRecord($record->fresh()),
            ], $status);
        }

        return back()->with('success', $message);
    }

    private function defaultAcceptedFinanceRecordId(string $moduleKey): string
    {
        static $cache = [];

        if (!array_key_exists($moduleKey, $cache)) {
            $cache[$moduleKey] = (string) (FinanceRecord::query()
                ->where('module_key', $moduleKey)
                ->where(function ($query) {
                    $query->where('workflow_status', 'Accepted')
                        ->orWhere('approval_status', 'Approved');
                })
                ->orderByDesc('record_date')
                ->orderByDesc('created_at')
                ->value('id') ?: '');
        }

        return $cache[$moduleKey];
    }

    private function fallbackDvPayload(FinanceRecord $record): array
    {
        $data = $record->data ?? [];
        $sourceSnapshot = $this->financeSourceDocumentSnapshot($record);

        $firstFilled = function (array $values) {
            foreach ($values as $value) {
                if (!blank($value)) {
                    return $value;
                }
            }

            return null;
        };

        $paymentType = $firstFilled([
            data_get($data, 'payment_type'),
            data_get($data, 'mode_of_release'),
            data_get($data, 'reimbursement_mode'),
            data_get($data, 'mode_of_return'),
            data_get($data, 'paid_through'),
        ]) ?: 'Cash';

        $payload = [
            'supplier_id' => data_get($data, 'supplier_id') ?? '',
            'payee_type' => data_get($data, 'payee_type') ?: data_get($this->financeSourceDocumentPayeeSnapshot($record), 'payee_type', ''),
            'payee_name' => data_get($data, 'payee_name') ?: data_get($this->financeSourceDocumentPayeeSnapshot($record), 'payee_name', ''),
            'amount' => $firstFilled([
                ((string) $record->module_key === 'ca' ? data_get($data, 'amount_requested') : null),
                ((string) $record->module_key === 'ca' ? data_get($data, 'total_cash_advance') : null),
                data_get($data, 'amount'),
                data_get($data, 'grand_total'),
                data_get($data, 'amount_requested'),
                data_get($data, 'total_cash_advance'),
                data_get($data, 'amount_returned'),
                data_get($data, 'total_payroll_amount'),
                data_get($data, 'acquisition_cost'),
                $record->amount,
            ]) ?? '',
            'payment_type' => $paymentType,
            'disbursement_type' => $paymentType,
            'bank_account_id' => $firstFilled([
                data_get($data, 'bank_account_id'),
                data_get($data, 'funding_bank_account_id'),
                data_get($data, 'receiving_bank_account_id'),
                data_get($data, 'source_bank_account_id'),
                data_get($data, 'destination_bank_account_id'),
            ]) ?: $this->defaultAcceptedFinanceRecordId('bank_account'),
            'coa_id' => $firstFilled([
                data_get($data, 'coa_id'),
                data_get($data, 'payroll_expense_coa_id'),
                data_get($data, 'asset_coa_id'),
            ]) ?: $this->defaultAcceptedFinanceRecordId('chart_account'),
            'fund_source' => $firstFilled([
                data_get($data, 'fund_source'),
                data_get($data, 'project'),
                data_get($data, 'department'),
            ]) ?? '',
            'department' => data_get($data, 'department') ?: data_get($data, 'requesting_department') ?: '',
            'reference_number' => $firstFilled([
                data_get($data, 'reference_number'),
                data_get($data, 'transfer_reference_number'),
                $record->record_number ? $record->record_number . '-REF' : null,
            ]) ?? '',
            'purpose' => $firstFilled([
                data_get($data, 'purpose'),
                data_get($data, 'expense_details'),
                data_get($data, 'reason'),
                data_get($data, 'supporting_payroll_summary'),
                data_get($data, 'asset_description'),
                $record->record_title,
            ]) ?? '',
            'payment_date' => optional($record->record_date)->format('Y-m-d') ?: '',
            'due_date' => $firstFilled([
                data_get($data, 'due_date'),
                data_get($data, 'needed_date'),
                data_get($data, 'expected_delivery_date'),
                data_get($data, 'pay_date'),
                data_get($data, 'acquisition_date'),
            ]) ?? '',
            'withholding_tax' => $firstFilled([
                data_get($data, 'withholding_tax'),
                data_get($data, 'wht_amount'),
                data_get($data, 'wht_total'),
            ]) ?? ((string) $record->module_key === 'ca' ? '0.00' : ''),
            'vat_amount' => $firstFilled([
                data_get($data, 'vat_amount'),
                data_get($data, 'tax_amount'),
                data_get($data, 'tax_total'),
            ]) ?? ((string) $record->module_key === 'ca' ? '0.00' : ''),
            'currency' => data_get($data, 'currency') ?: 'PHP',
            'exchange_rate' => data_get($data, 'exchange_rate') ?: ((string) $record->module_key === 'ca' || data_get($data, 'currency', 'PHP') === 'PHP' ? '1.00' : ''),
            'received_by_name' => data_get($data, 'received_by_name') ?: data_get($sourceSnapshot, 'requester', '') ?: data_get($sourceSnapshot, 'employee_name', '') ?: data_get($sourceSnapshot, 'payee_name', ''),
            'date_received' => data_get($data, 'date_received') ?: ((string) $record->module_key === 'ca' ? now()->format('Y-m-d') : ''),
            'remarks' => data_get($data, 'remarks') ?: '',
            'source_record_number' => data_get($sourceSnapshot, 'source_record_number', $record->record_number ?: ''),
            'source_record_date' => data_get($sourceSnapshot, 'source_record_date', optional($record->record_date)->format('Y-m-d') ?: ''),
            'source_requester' => data_get($sourceSnapshot, 'requester', ''),
            'source_department' => data_get($sourceSnapshot, 'department', ''),
            'source_project' => data_get($sourceSnapshot, 'project', ''),
            'source_cost_center' => data_get($sourceSnapshot, 'cost_center', ''),
            'source_fund_source' => data_get($sourceSnapshot, 'fund_source', ''),
            'source_amount' => data_get($sourceSnapshot, 'amount', $record->amount ?: ''),
            'source_remaining_balance' => data_get($sourceSnapshot, 'remaining_balance', ''),
            'source_current_balance' => data_get($sourceSnapshot, 'current_balance', data_get($sourceSnapshot, 'amount', '')),
            'source_reserved_balance' => data_get($sourceSnapshot, 'reserved_balance', data_get($sourceSnapshot, 'total_disbursed_amount', '0.00')),
            'source_available_balance' => data_get($sourceSnapshot, 'available_balance', data_get($sourceSnapshot, 'remaining_balance', '0.00')),
            'source_approval_status' => data_get($sourceSnapshot, 'approval_status', $record->approval_status ?: 'Pending'),
            'source_approved_by_name' => data_get($sourceSnapshot, 'approved_by_name', $this->financeUserDisplayName($record->approved_by)),
            'source_approved_at' => data_get($sourceSnapshot, 'approved_at', optional($record->approved_at)->format('Y-m-d H:i:s') ?: ''),
            'source_supplier_name' => data_get($sourceSnapshot, 'supplier_name', ''),
            'source_employee_name' => data_get($sourceSnapshot, 'employee_name', ''),
            'source_payee_type' => data_get($sourceSnapshot, 'payee_type', ''),
            'source_payee_name' => data_get($sourceSnapshot, 'payee_name', ''),
            'source_status' => data_get($sourceSnapshot, 'status', $this->financeSystemStatusForRecord($record)),
            'source_workflow_status' => data_get($sourceSnapshot, 'workflow_status', $record->workflow_status ?? 'Uploaded'),
            'source_relationship_status' => data_get($sourceSnapshot, 'relationship_status', $this->financeDerivedRelationshipStatus($record)),
        ];

        if ((string) $record->module_key === 'err') {
            $payload['supplier_id'] = '';
            $payload['fund_source'] = '';
            $payload['due_date'] = '';
            $payload['withholding_tax'] = '';
            $payload['vat_amount'] = '';
            $payload['received_by_name'] = '';
            $payload['date_received'] = '';
            $payload['bank_account_id'] = data_get($data, 'reimbursement_mode') === 'Check'
                ? ($payload['bank_account_id'] ?? '')
                : '';
            $payload['purpose'] = $firstFilled([
                data_get($data, 'expense_details'),
                data_get($data, 'purpose'),
                $record->record_title,
            ]) ?? '';
        }

        if ((string) $record->module_key === 'ibtf') {
            $payload['supplier_id'] = '';
            $payload['fund_source'] = '';
            $payload['department'] = '';
            $payload['due_date'] = '';
            $payload['withholding_tax'] = '';
            $payload['vat_amount'] = '';
            $payload['received_by_name'] = '';
            $payload['date_received'] = '';
            $payload['payment_type'] = data_get($data, 'payment_type') ?: 'Bank Transfer';
            $payload['disbursement_type'] = data_get($data, 'disbursement_type') ?: $payload['payment_type'];
            $payload['bank_account_id'] = data_get($data, 'source_bank_account_id') ?: ($payload['bank_account_id'] ?? '');
            $payload['reference_number'] = $firstFilled([
                data_get($data, 'transfer_reference_number'),
                data_get($data, 'reference_number'),
                $record->record_number ? $record->record_number . '-REF' : null,
            ]) ?? '';
            $payload['purpose'] = $firstFilled([
                data_get($data, 'reason'),
                data_get($data, 'purpose'),
                $record->record_title,
            ]) ?? '';
        }

        if ((string) $record->module_key === 'crf') {
            $modeOfReturn = (string) data_get($data, 'mode_of_return', 'Cash');
            $payload['supplier_id'] = '';
            $payload['fund_source'] = '';
            $payload['department'] = '';
            $payload['due_date'] = '';
            $payload['withholding_tax'] = '';
            $payload['vat_amount'] = '';
            $payload['received_by_name'] = '';
            $payload['date_received'] = '';
            $payload['payment_type'] = data_get($data, 'payment_type') ?: $modeOfReturn ?: 'Cash';
            $payload['disbursement_type'] = data_get($data, 'disbursement_type') ?: $payload['payment_type'];
            $payload['bank_account_id'] = $modeOfReturn === 'Check'
                ? ($payload['bank_account_id'] ?? '')
                : '';
            $payload['purpose'] = $firstFilled([
                data_get($data, 'purpose'),
                data_get($data, 'remarks'),
                $record->record_title,
            ]) ?? '';
        }

        $payload['line_items'] = $this->financeDvLineItemsFromSource($record, $payload);

        return $payload;
    }

    private function financeSourceDocumentSnapshot(FinanceRecord $record): array
    {
        $data = $record->data ?? [];
        $disbursementSummary = $this->financeSourceDisbursementSummary($record);
        $payeeSnapshot = $this->financeSourceDocumentPayeeSnapshot($record);
        $firstFilled = function (array $values) {
            foreach ($values as $value) {
                if (!blank($value)) {
                    return $value;
                }
            }

            return null;
        };

        $amount = $firstFilled([
            data_get($data, 'amount'),
            data_get($data, 'grand_total'),
            data_get($data, 'amount_requested'),
            data_get($data, 'total_cash_advance'),
            data_get($data, 'amount_returned'),
            data_get($data, 'total_payroll_amount'),
            data_get($data, 'acquisition_cost'),
            $record->amount,
        ]) ?? '';

        $remainingBalance = $firstFilled([
            data_get($data, 'ca_payment_remaining_balance'),
            data_get($data, 'remaining_balance'),
            data_get($data, 'balance_remaining'),
            data_get($data, 'remaining_amount'),
            data_get($data, 'available_balance'),
            data_get($disbursementSummary, 'remaining_balance'),
        ]) ?? '';

        $requester = trim((string) $firstFilled([
            data_get($data, 'requestor'),
            data_get($data, 'employee_name'),
            data_get($data, 'requester_name'),
            data_get($data, 'submitted_by_name'),
            $this->financeSubmittedByName($record),
        ]) ?: '');

        $supplierName = trim((string) $firstFilled([
            data_get($data, 'supplier_name'),
            data_get($data, 'supplier_submitted_by_name'),
            data_get($data, 'representative_full_name'),
            data_get($payeeSnapshot, 'payee_type') === 'Supplier' ? data_get($payeeSnapshot, 'payee_name') : null,
        ]) ?: '');

        $resolvedBankAccountLabel = trim((string) $firstFilled([
            data_get($data, 'bank_account_name'),
            data_get($data, 'funding_bank_account_name'),
            data_get($data, 'source_bank_account_name'),
            data_get($data, 'destination_bank_account_name'),
            data_get($data, 'receiving_bank_account_name'),
            data_get($data, 'bank_account_label'),
        ]) ?: '');

        if ($resolvedBankAccountLabel === '') {
            foreach ([
                data_get($data, 'bank_account_id'),
                data_get($data, 'funding_bank_account_id'),
                data_get($data, 'source_bank_account_id'),
                data_get($data, 'destination_bank_account_id'),
                data_get($data, 'receiving_bank_account_id'),
                data_get($data, 'paid_through'),
            ] as $bankAccountId) {
                $bankAccountRecord = blank($bankAccountId)
                    ? null
                    : $this->financeResolveModuleRecord('bank_account', $bankAccountId);

                if ($bankAccountRecord) {
                    $resolvedBankAccountLabel = trim((string) collect([
                        $bankAccountRecord->record_title,
                        $bankAccountRecord->record_number,
                    ])->first(fn ($value) => !blank($value)) ?: '');

                    if ($resolvedBankAccountLabel !== '') {
                        break;
                    }
                }
            }
        }

        $employeeName = trim((string) $firstFilled([
            data_get($data, 'employee_name'),
            data_get($data, 'requestor'),
            data_get($data, 'requester_name'),
        ]) ?: '');

        $snapshot = [
            'source_record_number' => $record->record_number ?: '',
            'source_record_date' => optional($record->record_date)->format('Y-m-d') ?: '',
            'requester' => $requester,
            'department' => trim((string) ($firstFilled([
                data_get($data, 'department'),
                data_get($data, 'requesting_department'),
            ]) ?: '')),
            'project' => trim((string) ($firstFilled([
                data_get($data, 'project'),
                data_get($data, 'project_name'),
                data_get($data, 'project_code'),
                data_get($data, 'fund_source'),
                data_get($data, 'department'),
                data_get($data, 'requesting_department'),
            ]) ?: '')),
            'cost_center' => trim((string) ($firstFilled([
                data_get($data, 'cost_center'),
                data_get($data, 'cost_center_code'),
                data_get($data, 'cost_center_name'),
                data_get($data, 'department'),
                data_get($data, 'requesting_department'),
                data_get($data, 'fund_source'),
            ]) ?: '')),
            'fund_source' => trim((string) ($firstFilled([
                data_get($data, 'fund_source'),
                $resolvedBankAccountLabel,
                data_get($data, 'project'),
                data_get($data, 'department'),
            ]) ?: '')),
            'amount' => is_numeric($amount) ? number_format((float) $amount, 2, '.', '') : $amount,
            'remaining_balance' => is_numeric($remainingBalance) ? number_format((float) $remainingBalance, 2, '.', '') : $remainingBalance,
            'current_balance' => is_numeric($amount) ? number_format((float) $amount, 2, '.', '') : $amount,
            'reserved_balance' => is_numeric($amount) && is_numeric($remainingBalance)
                ? number_format(max(((float) $amount) - ((float) $remainingBalance), 0), 2, '.', '')
                : data_get($disbursementSummary, 'total_disbursed_amount', '0.00'),
            'available_balance' => is_numeric($remainingBalance) ? number_format((float) $remainingBalance, 2, '.', '') : data_get($disbursementSummary, 'remaining_balance', '0.00'),
            'total_disbursed_amount' => data_get($disbursementSummary, 'total_disbursed_amount', '0.00'),
            'percentage_paid' => data_get($disbursementSummary, 'percentage_paid', '0.00'),
            'dv_count' => data_get($disbursementSummary, 'dv_count', 0),
            'released_dv_count' => data_get($disbursementSummary, 'released_dv_count', 0),
            'approval_status' => $record->approval_status ?: 'Pending',
            'approved_by_name' => trim((string) ($this->financeUserDisplayName($record->approved_by) ?: data_get($data, 'approved_by_name', ''))),
            'approved_at' => optional($record->approved_at)->format('Y-m-d H:i:s') ?: trim((string) data_get($data, 'approved_at', '')),
            'supplier_name' => $supplierName,
            'employee_name' => $employeeName,
            'payee_type' => data_get($payeeSnapshot, 'payee_type', ''),
            'payee_name' => data_get($payeeSnapshot, 'payee_name', ''),
            'status' => $this->financeSystemStatusForRecord($record),
            'workflow_status' => $record->workflow_status ?? 'Uploaded',
            'relationship_status' => $this->financeDerivedRelationshipStatus($record),
            'disbursement_status' => data_get($disbursementSummary, 'is_partially_disbursed')
                ? 'Partially Disbursed'
                : (data_get($disbursementSummary, 'is_fully_disbursed') ? 'Fully Disbursed' : 'Awaiting Disbursement'),
        ];

        if ((string) $record->module_key === 'err') {
            $snapshot['project'] = '';
            $snapshot['cost_center'] = '';
            $snapshot['fund_source'] = '';
            $snapshot['remaining_balance'] = '';
            $snapshot['current_balance'] = '';
            $snapshot['reserved_balance'] = '';
            $snapshot['available_balance'] = '';
            $snapshot['total_disbursed_amount'] = '';
            $snapshot['percentage_paid'] = '';
            $snapshot['disbursement_status'] = '';
        }

        if ((string) $record->module_key === 'ibtf') {
            $snapshot['requester'] = '';
            $snapshot['department'] = '';
            $snapshot['project'] = '';
            $snapshot['cost_center'] = '';
            $snapshot['fund_source'] = '';
            $snapshot['remaining_balance'] = '';
            $snapshot['current_balance'] = '';
            $snapshot['reserved_balance'] = '';
            $snapshot['available_balance'] = '';
            $snapshot['total_disbursed_amount'] = '';
            $snapshot['percentage_paid'] = '';
            $snapshot['supplier_name'] = '';
            $snapshot['employee_name'] = '';
            $snapshot['disbursement_status'] = '';
        }

        return $snapshot;
    }

    private function financeDvFundAvailabilityAssessment(FinanceRecord $financeRecord): ?array
    {
        if ($financeRecord->module_key !== 'dv') {
            return null;
        }

        $data = $financeRecord->data ?? [];
        $sourceDocumentType = (string) data_get($data, 'source_document_type', '');
        $sourceDocumentId = data_get($data, 'source_document_id');

        if ($sourceDocumentType === '' || blank($sourceDocumentId)) {
            return null;
        }

        $sourceRecord = $this->financeResolveModuleRecord($sourceDocumentType, $sourceDocumentId);

        if (!$sourceRecord) {
            return null;
        }

        $sourceSnapshot = $this->financeSourceDocumentSnapshot($sourceRecord);
        $requestedAmount = (float) data_get($data, 'amount', $financeRecord->amount ?: 0);
        $availableBalance = (float) data_get($sourceSnapshot, 'available_balance', data_get($sourceSnapshot, 'remaining_balance', 0));

        if ($sourceDocumentType === 'ca') {
            $disbursementSummary = $this->financeSourceDisbursementSummary($sourceRecord);
            $availableBalance = (float) data_get($disbursementSummary, 'remaining_balance', $availableBalance);
        } elseif ($sourceDocumentType === 'err') {
            $availableBalance = (float) data_get($sourceSnapshot, 'amount', $requestedAmount);
        } elseif ($sourceDocumentType === 'ibtf') {
            $availableBalance = (float) data_get($sourceSnapshot, 'amount', $requestedAmount);
        }

        $status = $requestedAmount > $availableBalance ? 'Insufficient Funds' : 'Sufficient Funds';

        return [
            'source_record' => $sourceRecord,
            'source_snapshot' => $sourceSnapshot,
            'requested_amount' => number_format($requestedAmount, 2, '.', ''),
            'available_balance' => number_format($availableBalance, 2, '.', ''),
            'status' => $status,
            'warning' => $status === 'Insufficient Funds'
                ? 'Requested amount exceeds the available balance. Approval is controlled by company policy.'
                : null,
            'policy_allows_approval' => $this->financeDvApprovalAllowedWithInsufficientFunds(),
        ];
    }

    private function financeDvAccountingAssessment(FinanceRecord $financeRecord): ?array
    {
        if ($financeRecord->module_key !== 'dv') {
            return null;
        }

        $lineItems = $this->financeNormalizedDvLineItems($financeRecord, $financeRecord->data ?? []);
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($lineItems as $lineItem) {
            $totalDebit += (float) data_get($lineItem, 'debit', 0);
            $totalCredit += (float) data_get($lineItem, 'credit', 0);
        }

        $totalDebit = round($totalDebit, 2);
        $totalCredit = round($totalCredit, 2);
        $difference = round(abs($totalDebit - $totalCredit), 2);
        $isBalanced = $difference < 0.01;

        return [
            'total_debit' => number_format($totalDebit, 2, '.', ''),
            'total_credit' => number_format($totalCredit, 2, '.', ''),
            'difference' => number_format($difference, 2, '.', ''),
            'status' => $isBalanced ? 'Balanced' : 'Unbalanced',
            'warning' => $isBalanced
                ? null
                : sprintf(
                    'Total Debit must equal Total Credit before the Disbursement Voucher can be approved. Current totals: Debit %s, Credit %s, Difference %s.',
                    number_format($totalDebit, 2, '.', ','),
                    number_format($totalCredit, 2, '.', ','),
                    number_format($difference, 2, '.', ',')
                ),
        ];
    }

    private function financeTransformLineItemReceiptAttachments(array $lineItems): array
    {
        return array_values(array_map(function ($lineItem) {
            if (!is_array($lineItem)) {
                return $lineItem;
            }

            $receiptAttachment = data_get($lineItem, 'receipt_attachment');
            $receiptAttachment = is_array($receiptAttachment) ? $receiptAttachment : [];

            $path = (string) (data_get($receiptAttachment, 'path') ?: data_get($lineItem, 'receipt_attachment_path') ?: '');
            $name = (string) (data_get($receiptAttachment, 'name') ?: data_get($lineItem, 'receipt_attachment_name') ?: '');

            if ($path === '' && $name === '') {
                return $lineItem;
            }

            $attachment = array_merge($receiptAttachment, [
                'name' => $name !== '' ? $name : 'Receipt',
                'path' => $path,
                'mime' => (string) (data_get($receiptAttachment, 'mime') ?: data_get($lineItem, 'receipt_attachment_mime') ?: ''),
                'size' => data_get($receiptAttachment, 'size') ?: data_get($lineItem, 'receipt_attachment_size'),
                'uploaded_at' => data_get($receiptAttachment, 'uploaded_at'),
                'uploaded_by' => data_get($receiptAttachment, 'uploaded_by'),
                'category' => data_get($receiptAttachment, 'category') ?: 'Line Item Receipt',
            ]);

            $attachment['url'] = $this->financeAttachmentUrl($attachment);
            $attachment['download_url'] = $attachment['url'] ? $attachment['url'] . '?download=1' : null;
            $attachment['is_image'] = $this->financeAttachmentIsImage($attachment);
            $attachment['image_data_uri'] = $attachment['is_image'] ? $this->financeAttachmentDataUri($attachment) : null;

            $lineItem['receipt_attachment'] = $attachment;
            $lineItem['receipt_attachment_name'] = $attachment['name'];
            $lineItem['receipt_attachment_path'] = $attachment['path'];
            $lineItem['receipt_attachment_mime'] = $attachment['mime'];
            $lineItem['receipt_attachment_size'] = $attachment['size'];

            return $lineItem;
        }, $lineItems));
    }

    private function financeTransformRecordAttachments(FinanceRecord $record, array $data = []): array
    {
        $directAttachments = array_values(array_map(function ($attachment) {
            $attachment = is_array($attachment) ? $attachment : [];
            $attachment['url'] = $this->financeAttachmentUrl($attachment);
            $attachment['download_url'] = $attachment['url'] ? $attachment['url'] . '?download=1' : null;
            $attachment['is_image'] = $this->financeAttachmentIsImage($attachment);
            $attachment['image_data_uri'] = $attachment['is_image'] ? $this->financeAttachmentDataUri($attachment) : null;

            return $attachment;
        }, (array) ($record->attachments ?? [])));

        $lineItemAttachments = collect((array) data_get($data, 'line_items', []))
            ->map(function ($lineItem) {
                $attachment = data_get($lineItem, 'receipt_attachment');

                return is_array($attachment) ? $attachment : null;
            })
            ->filter(fn ($attachment) => is_array($attachment) && (!blank(data_get($attachment, 'name')) || !blank(data_get($attachment, 'path'))))
            ->values()
            ->all();

        $merged = [];
        $seen = [];

        foreach (array_merge($directAttachments, $lineItemAttachments) as $attachment) {
            $key = implode('|', [
                (string) data_get($attachment, 'source', 'attachment'),
                (string) data_get($attachment, 'path', ''),
                (string) data_get($attachment, 'name', ''),
            ]);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $merged[] = $attachment;
        }

        return array_values($merged);
    }

    private function financeDvReleasePrerequisitesAssessment(FinanceRecord $financeRecord, array $data, array $attachments = []): ?array
    {
        if ($financeRecord->module_key !== 'dv') {
            return null;
        }

        $sourceDocumentType = (string) data_get($data, 'source_document_type', '');
        $sourceDocumentId = data_get($data, 'source_document_id');
        $sourceRecord = $sourceDocumentType !== '' && filled($sourceDocumentId)
            ? $this->financeResolveModuleRecord($sourceDocumentType, $sourceDocumentId)
            : null;
        $sourceSnapshot = $sourceRecord ? $this->financeSourceDocumentSnapshot($sourceRecord) : [];

        $requirements = [];

        if (!$this->financeRecordIsApproved($financeRecord)) {
            $requirements[] = 'Fully approved';
        }

        $effectiveFundSource = data_get($data, 'fund_source')
            ?: data_get($data, 'source_fund_source')
            ?: data_get($sourceSnapshot, 'fund_source')
            ?: data_get($sourceSnapshot, 'project')
            ?: data_get($sourceSnapshot, 'department');

        if (!in_array($sourceDocumentType, ['err', 'ibtf', 'crf'], true) && blank($effectiveFundSource)) {
            $requirements[] = 'fund source identified';
        }

        if ((blank(data_get($data, 'payee_type')) || blank(data_get($data, 'payee_name')))
            && (blank(data_get($sourceSnapshot, 'payee_type')) || blank(data_get($sourceSnapshot, 'payee_name')))) {
            $requirements[] = 'payee identified';
        }

        if (blank(data_get($data, 'payment_type')) || blank(data_get($data, 'disbursement_type'))) {
            $requirements[] = 'payment information complete';
        }

        $bankAccountRequired = data_get($data, 'payment_type') === 'Check'
            || in_array($sourceDocumentType, ['po', 'ca', 'pda', 'ibtf'], true);
        $missingBankAccount = $bankAccountRequired && blank(data_get($data, 'bank_account_id'));
        $missingCoa = blank(data_get($data, 'coa_id'));

        if ($missingBankAccount || $missingCoa) {
            $requirements[] = 'funding and accounting references identified';
        }

        $accountingAssessment = $this->financeDvAccountingAssessment(new FinanceRecord([
            'module_key' => 'dv',
            'data' => array_merge($financeRecord->data ?? [], $data),
        ]));

        if ($accountingAssessment && data_get($accountingAssessment, 'status') !== 'Balanced') {
            $requirements[] = 'debit and credit balanced';
        }

        if (!$requirements) {
            return [
                'status' => 'Ready',
                'warning' => null,
            ];
        }

        return [
            'status' => 'Blocked',
            'warning' => 'The Disbursement Voucher cannot be marked as disbursed until it is ' . collect($requirements)->join(', ') . '.',
            'missing' => $requirements,
        ];
    }

    private function financeDvSourceRecord(FinanceRecord $financeRecord, array $payload = []): ?FinanceRecord
    {
        $sourceType = (string) data_get($payload, 'source_document_type', data_get($financeRecord->data ?? [], 'source_document_type', ''));
        $sourceId = data_get($payload, 'source_document_id', data_get($financeRecord->data ?? [], 'source_document_id'));

        if (blank($sourceType) || blank($sourceId)) {
            return null;
        }

        return $this->financeResolveModuleRecord($sourceType, $sourceId);
    }

    private function financeResolveBankAccountCreditCoaId(mixed $bankAccountValue): ?int
    {
        $bankAccount = blank($bankAccountValue)
            ? null
            : $this->financeResolveModuleRecord('bank_account', $bankAccountValue);

        if (! $bankAccount) {
            return null;
        }

        $bankData = is_array($bankAccount->data ?? null) ? $bankAccount->data : [];
        $candidates = array_values(array_filter([
            data_get($bankData, 'linked_coa_id'),
            data_get($bankData, 'linked_chart_account_id'),
            data_get($bankData, 'coa_id'),
            data_get($bankData, 'chart_account_id'),
            data_get($bankData, 'bank_coa_id'),
            data_get($bankData, 'default_credit_coa_id'),
        ], fn ($value) => filled($value)));

        foreach ($candidates as $candidate) {
            if (is_numeric($candidate)) {
                $chartAccount = $this->financeResolveModuleRecord('chart_account', $candidate);
                if ($chartAccount) {
                    return (int) $chartAccount->id;
                }
            }
        }

        return null;
    }

    private function financeNormalizeDvLineItems(array $lineItems, FinanceRecord $financeRecord, array $payload = []): array
    {
        $normalizedRows = collect($lineItems)
            ->filter(fn ($item) => is_array($item) && collect($item)->contains(fn ($value) => !blank($value)))
            ->map(function (array $item, int $index): array {
                $debit = (float) data_get($item, 'debit', 0);
                $credit = (float) data_get($item, 'credit', 0);

                return [
                    'description' => trim((string) data_get($item, 'description', '')),
                    'account_code' => trim((string) data_get($item, 'account_code', '')),
                    'debit' => $debit > 0 ? number_format($debit, 2, '.', '') : '',
                    'credit' => $credit > 0 ? number_format($credit, 2, '.', '') : '',
                ];
            })
            ->values()
            ->all();

        $debitTotal = collect($normalizedRows)->sum(fn (array $row) => (float) data_get($row, 'debit', 0));
        $creditTotal = collect($normalizedRows)->sum(fn (array $row) => (float) data_get($row, 'credit', 0));
        if ($debitTotal <= 0) {
            return $normalizedRows;
        }

        $creditAccountId = $this->financeResolveBankAccountCreditCoaId(
            data_get($payload, 'bank_account_id')
                ?: data_get($financeRecord->data ?? [], 'bank_account_id')
                ?: data_get($payload, 'source_bank_account_id')
                ?: data_get($financeRecord->data ?? [], 'source_bank_account_id')
                ?: data_get($payload, 'funding_bank_account_id')
                ?: data_get($financeRecord->data ?? [], 'funding_bank_account_id')
                ?: data_get($payload, 'receiving_bank_account_id')
                ?: data_get($financeRecord->data ?? [], 'receiving_bank_account_id')
                ?: data_get($payload, 'destination_bank_account_id')
                ?: data_get($financeRecord->data ?? [], 'destination_bank_account_id')
        );

        $creditAccountCode = $creditAccountId ?: data_get($payload, 'coa_id') ?: data_get($financeRecord->data ?? [], 'coa_id') ?: data_get($financeRecord->data ?? [], 'asset_coa_id') ?: data_get($financeRecord->data ?? [], 'payroll_expense_coa_id');
        if (blank($creditAccountCode)) {
            return $normalizedRows;
        }

        $firstDebitRow = collect($normalizedRows)->first(fn (array $row) => (float) data_get($row, 'debit', 0) > 0) ?: ($normalizedRows[0] ?? []);
        $debitDescription = trim((string) data_get($firstDebitRow, 'description', ''))
            ?: trim((string) $financeRecord->record_number)
            ?: trim((string) $financeRecord->record_title)
            ?: 'Disbursement Voucher';
        $debitAccountCode = trim((string) data_get($firstDebitRow, 'account_code', ''));
        $creditLabel = trim((string) collect([
            $this->financeResolveModuleRecord('chart_account', $creditAccountCode)?->record_number,
            $this->financeResolveModuleRecord('chart_account', $creditAccountCode)?->record_title,
            $creditAccountCode,
        ])->first(fn ($value) => filled($value)) ?: '');

        return [
            [
                'description' => $debitDescription,
                'account_code' => $debitAccountCode,
                'debit' => number_format($debitTotal, 2, '.', ''),
                'credit' => '',
            ],
            [
                'description' => $creditLabel !== '' ? 'Credit ' . $creditLabel : 'Credit Fund Source',
                'account_code' => (string) $creditAccountCode,
                'debit' => '',
                'credit' => number_format($debitTotal, 2, '.', ''),
            ],
        ];
    }

    private function financeNormalizedDvLineItems(FinanceRecord $financeRecord, array $payload = []): array
    {
        if ($financeRecord->module_key !== 'dv') {
            return [];
        }

        $currentLineItems = (array) data_get($payload, 'line_items', data_get($financeRecord->data ?? [], 'line_items', []));
        $hasCurrentLineItems = collect($currentLineItems)
            ->filter(fn ($item) => is_array($item) && collect($item)->contains(fn ($value) => !blank($value)))
            ->isNotEmpty();

        if ($hasCurrentLineItems) {
            return $this->financeNormalizeDvLineItems($currentLineItems, $financeRecord, $payload);
        }

        $sourceRecord = $this->financeDvSourceRecord($financeRecord, $payload);
        if ($sourceRecord) {
            return $this->financeNormalizeDvLineItems(
                $this->financeDvLineItemsFromSource($sourceRecord, $payload),
                $financeRecord,
                $payload
            );
        }

        return $this->financeNormalizeDvLineItems(
            $currentLineItems,
            $financeRecord,
            $payload
        );
    }

    private function financeDvAccountingSummaryFromLineItems(array $lineItems): array
    {
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($lineItems as $lineItem) {
            if (! is_array($lineItem)) {
                continue;
            }

            $totalDebit += (float) data_get($lineItem, 'debit', 0);
            $totalCredit += (float) data_get($lineItem, 'credit', 0);
        }

        $totalDebit = round($totalDebit, 2);
        $totalCredit = round($totalCredit, 2);
        $difference = round(abs($totalDebit - $totalCredit), 2);

        return [
            'total_debit_amount' => number_format($totalDebit, 2, '.', ''),
            'total_credit_amount' => number_format($totalCredit, 2, '.', ''),
            'accounting_balance_difference' => number_format($difference, 2, '.', ''),
            'accounting_balance_status' => $difference < 0.01 ? 'Balanced' : 'Unbalanced',
        ];
    }

    private function financeDvLineItemsFromSource(FinanceRecord $record, array $payload): array
    {
        $data = $record->data ?? [];
        $rows = collect((array) data_get($data, 'line_items', []))
            ->filter(fn ($row) => is_array($row) && collect($row)->contains(fn ($value) => !blank($value)))
            ->values();

        if ($rows->isNotEmpty()) {
            $debitRows = $rows->map(function (array $row, int $index) use ($payload, $data) {
                $quantity = (float) data_get($row, 'quantity', 0);
                $unitAmount = (float) (data_get($row, 'amount') ?: data_get($row, 'unit_cost') ?: 0);
                $amount = (float) (data_get($row, 'total') ?: data_get($row, 'line_total') ?: data_get($row, 'debit') ?: data_get($row, 'credit') ?: ($quantity * $unitAmount) ?: $unitAmount);
                $description = trim(collect([
                    data_get($row, 'item_id') ?: data_get($row, 'description') ?: data_get($row, 'account_code') ?: 'Line '.($index + 1),
                    data_get($row, 'category') ? '('.data_get($row, 'category').')' : null,
                ])->filter()->implode(' '));

                return [
                    'description' => $description,
                    'account_code' => data_get($row, 'account_code') ?: data_get($row, 'coa_id') ?: data_get($payload, 'coa_id') ?: data_get($data, 'coa_id') ?: '',
                    'debit' => $amount > 0 ? number_format($amount, 2, '.', '') : '',
                    'credit' => '',
                ];
            })->values()->all();

            $totalDebit = array_sum(array_map(fn (array $row) => (float) data_get($row, 'debit', 0), $debitRows));
            $bankAccountRecord = $this->financeResolveModuleRecord(
                'bank_account',
                data_get($payload, 'bank_account_id')
                    ?: data_get($data, 'bank_account_id')
                    ?: data_get($data, 'funding_bank_account_id')
                    ?: data_get($data, 'receiving_bank_account_id')
                    ?: data_get($data, 'source_bank_account_id')
                    ?: data_get($data, 'destination_bank_account_id')
            );
            $creditAccountCode = data_get($bankAccountRecord?->data, 'linked_coa_id')
                ?: data_get($payload, 'coa_id')
                ?: data_get($data, 'coa_id')
                ?: data_get($data, 'payroll_expense_coa_id')
                ?: data_get($data, 'asset_coa_id');

            if ($totalDebit > 0 && filled($creditAccountCode)) {
                $debitRows[] = [
                    'description' => 'Credit ' . (data_get($bankAccountRecord?->data, 'bank_account_name') ?: data_get($bankAccountRecord?->data, 'bank_name') ?: data_get($payload, 'bank_account_id') ?: 'fund source'),
                    'account_code' => $creditAccountCode,
                    'debit' => '',
                    'credit' => number_format($totalDebit, 2, '.', ''),
                ];
            }

            return $this->financeNormalizeDvLineItems($debitRows, $record, $payload);
        }

        $amount = (float) (data_get($payload, 'amount') ?: $record->amount ?: data_get($data, 'amount') ?: 0);

        if ($record->module_key === 'ibtf') {
            return [
                [
                    'description' => 'Transfer to '.(data_get($data, 'destination_account_code') ?: data_get($data, 'destination_bank_account_id') ?: 'destination account'),
                    'account_code' => data_get($data, 'destination_account_code') ?: '',
                    'debit' => $amount > 0 ? number_format($amount, 2, '.', '') : '',
                    'credit' => '',
                ],
                [
                    'description' => 'Transfer from '.(data_get($data, 'source_account_code') ?: data_get($data, 'source_bank_account_id') ?: 'source account'),
                    'account_code' => data_get($data, 'source_account_code') ?: '',
                    'debit' => '',
                    'credit' => $amount > 0 ? number_format($amount, 2, '.', '') : '',
                ],
            ];
        }

        $bankAccountRecord = $this->financeResolveModuleRecord(
            'bank_account',
            data_get($payload, 'bank_account_id')
                ?: data_get($data, 'bank_account_id')
                ?: data_get($data, 'funding_bank_account_id')
                ?: data_get($data, 'receiving_bank_account_id')
                ?: data_get($data, 'source_bank_account_id')
                ?: data_get($data, 'destination_bank_account_id')
        );
        $creditAccountCode = data_get($bankAccountRecord?->data, 'linked_coa_id')
            ?: data_get($payload, 'coa_id')
            ?: data_get($data, 'coa_id')
            ?: data_get($data, 'payroll_expense_coa_id')
            ?: data_get($data, 'asset_coa_id');

        $lineItems = array_values(array_filter([
            [
                'description' => trim(collect([
                    $record->record_number ?: strtoupper($record->module_key),
                    data_get($payload, 'purpose') ?: $record->record_title ?: 'Source document amount',
                ])->filter()->implode(' - ')),
                'account_code' => data_get($payload, 'coa_id') ?: '',
                'debit' => $amount > 0 ? number_format($amount, 2, '.', '') : '',
                'credit' => '',
            ],
            $amount > 0 && filled($creditAccountCode) ? [
                'description' => 'Credit ' . (data_get($bankAccountRecord?->data, 'bank_account_name') ?: data_get($bankAccountRecord?->data, 'bank_name') ?: data_get($payload, 'bank_account_id') ?: 'fund source'),
                'account_code' => $creditAccountCode,
                'debit' => '',
                'credit' => number_format($amount, 2, '.', ''),
            ] : null,
        ], fn ($row) => is_array($row) && collect($row)->contains(fn ($value) => !blank($value))));

        return $this->financeNormalizeDvLineItems($lineItems, $record, $payload);
    }

    private function persistAttachments(Request $request, array $existingAttachments = []): array
    {
        $attachments = $existingAttachments;
        $category = trim((string) $request->input('attachment_category', ''));
        $allowedCategories = $this->financeAttachmentTypeValues();
        if ($category === '') {
            $category = $this->financeDefaultAttachmentTypeValue();
        } elseif ($allowedCategories && ! in_array($category, $allowedCategories, true)) {
            $category = $this->financeDefaultAttachmentTypeValue() ?: $category;
        }
        $attachmentLabels = (array) $request->input('attachment_labels', []);
        $attachmentCategories = (array) $request->input('attachment_categories', []);

        $storeFile = function ($file, string|int|null $key = null, ?string $forcedCategory = null) use (&$attachments, $attachmentLabels, $attachmentCategories, $category): void {
            if (!$file instanceof \Illuminate\Http\UploadedFile || !$file->isValid()) {
                return;
            }

            $path = $file->store('finance_documents', 'public');
            $attachmentCategory = $forcedCategory
                ?: data_get($attachmentCategories, (string) $key)
                ?: data_get($attachmentLabels, (string) $key)
                ?: $category
                ?: 'Supporting Document';

            $attachments[] = [
                'name' => $file->getClientOriginalName(),
                'path' => 'storage/' . $path,
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'category' => $attachmentCategory,
                'status' => 'uploaded',
                'uploaded_at' => now()->format('Y-m-d H:i:s'),
                'uploaded_by' => Auth::user()->name ?? 'Unknown User',
            ];
        };

        $walkFiles = function ($files, string|int|null $key = null, ?string $forcedCategory = null) use (&$walkFiles, $storeFile): void {
            if ($files instanceof \Illuminate\Http\UploadedFile) {
                $storeFile($files, $key, $forcedCategory);

                return;
            }

            foreach ((array) $files as $childKey => $childFile) {
                $walkFiles($childFile, $childKey, $forcedCategory);
            }
        };

        foreach ((array) $request->file('attachments') as $key => $file) {
            $walkFiles($file, $key);
        }

        foreach ((array) $request->file('arf_photos') as $key => $file) {
            $walkFiles($file, $key, 'Asset Photo');
        }

        return $attachments;
    }

    private function financeAttachLineItemReceipts(Request $request, array $data): array
    {
        $lineItems = array_values((array) data_get($data, 'line_items', []));

        foreach ((array) $request->file('line_item_receipts', []) as $index => $file) {
            if (! $file instanceof \Illuminate\Http\UploadedFile || ! $file->isValid()) {
                continue;
            }

            if (! array_key_exists($index, $lineItems) || ! is_array($lineItems[$index])) {
                continue;
            }

            $path = $file->store('finance_documents', 'public');
            $attachment = [
                'name' => $file->getClientOriginalName(),
                'path' => 'storage/' . $path,
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'uploaded_at' => now()->format('Y-m-d H:i:s'),
                'uploaded_by' => Auth::user()?->name ?? 'Unknown User',
            ];

            data_set($lineItems, "{$index}.receipt_attachment", $attachment);
            data_set($lineItems, "{$index}.receipt_attachment_name", data_get($attachment, 'name'));
            data_set($lineItems, "{$index}.receipt_attachment_path", data_get($attachment, 'path'));
            data_set($lineItems, "{$index}.receipt_attachment_mime", data_get($attachment, 'mime'));
            data_set($lineItems, "{$index}.receipt_attachment_size", data_get($attachment, 'size'));
        }

        data_set($data, 'line_items', $lineItems);

        return $data;
    }

    private function supplierAttachmentSlug(string $label): string
    {
        return Str::slug($label, '_');
    }

    private function supplierRequiredAttachmentLabels(?string $entityType): array
    {
        return match ($entityType) {
            'Corporation', 'One Person Corporation (OPC)', 'Partnership', 'Foreign Company' => [
                'SEC Certificate of Registration',
                'BIR 2303 Certificate of Registration',
                "Mayor's Permit / Business Permit",
                'Valid ID of Authorized Representative',
                'Company Profile',
                'Contract / Agreement',
            ],
            'Sole Proprietorship' => [
                'DTI Certificate of Registration',
                'BIR 2303 Certificate of Registration',
                "Mayor's Permit / Business Permit",
                'Valid ID of Owner / Authorized Representative',
                'Business Profile / Company Profile',
                'Contract / Agreement',
            ],
            'Cooperative' => [
                'CDA Certificate of Registration',
                'BIR 2303 Certificate of Registration',
                "Mayor's Permit / Business Permit",
                'Valid ID of Authorized Representative',
                'Cooperative Profile',
                'Contract / Agreement',
            ],
            'Freelancer / Individual Professional', 'Independent Contractor' => [
                'Resume',
                'Valid Government ID',
                'TIN / BIR Registration, if applicable',
                'Resume / Portfolio, if applicable',
                'Professional License, if applicable',
                'Signed Contract / Agreement',
            ],
            'Government Agency' => [
                'Agency Profile / Official Agency Information',
                'Authorized Representative ID',
                'Authority to Transact / Authorization Letter, if applicable',
                'Contract / Agreement / Purchase Order',
            ],
            'Non-Profit Organization' => [
                'SEC Registration / Relevant Registration Certificate',
                'BIR 2303 Certificate of Registration, if applicable',
                "Mayor's Permit / Business Permit, if applicable",
                'Valid ID of Authorized Representative',
                'Organization Profile',
                'Contract / Agreement',
            ],
            'Others' => [
                'Valid Registration Document, if applicable',
                'Valid ID of Authorized Representative',
                'Supplier Profile',
                'Contract / Agreement',
                'Other supporting documents required by the Company',
            ],
            default => [],
        };
    }

    private function supplierHasAttachment(FinanceRecord $record, string $label): bool
    {
        return collect((array) ($record->attachments ?? []))->contains(function ($attachment) use ($label) {
            $attachment = is_array($attachment) ? $attachment : [];

            return strcasecmp((string) data_get($attachment, 'category'), $label) === 0;
        });
    }

    private function commonValidationRules(): array
    {
        return [
            'module_key' => 'required|in:' . implode(',', $this->moduleKeys()),
            'record_number' => ['required', 'string', 'max:255', 'regex:/^[A-Z0-9]{2,10}-\d{5}$/'],
            'record_title' => 'nullable|string|max:255',
            'record_date' => 'required|date',
            'amount' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:Active,Inactive,For Approval,Released,Draft,Pending Approval,Partially Approved,Approved,On Hold,Reverted,Awaiting Disbursement,Disbursed,Completed,Cancelled',
            'data' => 'nullable|array',
            'data.transaction_time' => 'nullable|date_format:H:i',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
            'arf_photos' => 'nullable|array',
            'arf_photos.*' => 'file|max:10240|mimes:jpg,jpeg,png,webp,gif',
            'attachment_category' => ['nullable', 'string', 'max:255', Rule::in($this->financeAttachmentTypeValues())],
        ];
    }

    private function acceptedLinkedRecordRule(string $moduleKey, array $dataConstraints = [])
    {
        return Rule::exists('finance_records', 'id')->where(function ($query) use ($moduleKey, $dataConstraints) {
            $query->where('module_key', $moduleKey)
                ->where(function ($statusQuery) {
                    $statusQuery->where('workflow_status', 'Accepted')
                        ->orWhere('approval_status', 'Approved');
                });

            foreach ($dataConstraints as $field => $value) {
                $query->where("data->{$field}", $value);
            }
        });
    }

    private function arfLinkedDvRule()
    {
        return Rule::exists('finance_records', 'id')->where(function ($query) {
            $query->where('module_key', 'dv')
                ->where('data->source_document_type', 'po')
                ->whereNotIn('workflow_status', ['Delete Requested', 'Deleted', 'Cancelled', 'Reverted']);
        });
    }

    private function financeLineItemClientRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $value = trim((string) $value);

            if ($value === '') {
                return;
            }

            if (ctype_digit($value) && Schema::hasTable('contacts') && Contact::query()->whereKey((int) $value)->exists()) {
                return;
            }

            if (str_starts_with($value, 'company-person:') && $this->financeClientLookupOptions()->contains(fn (array $option) => (string) ($option['id'] ?? '') === $value)) {
                return;
            }

            $fail('The selected client is invalid.');
        };
    }

    private function normalizeCashAdvanceReleaseSchedule(mixed $value, mixed $releaseCount = 1): string
    {
        $normalized = Str::lower(trim((string) $value));

        if (in_array($normalized, ['full release', 'full'], true)) {
            return 'Full Release';
        }

        if (
            in_array($normalized, ['staggered release', 'staggered'], true)
            || Str::contains($normalized, ['stagger', 'schedule', 'installment', 'partial'])
        ) {
            return 'Staggered Release';
        }

        return ((int) $releaseCount) > 1 ? 'Staggered Release' : 'Full Release';
    }

    private function normalizeCashAdvanceReleaseEntries(array $data): array
    {
        $amount = (float) data_get($data, 'amount_requested', 0);
        $releaseCount = max((int) data_get($data, 'release_count', 1), 1);
        $amountPerRelease = (float) data_get($data, 'amount_per_release', $releaseCount > 0 ? $amount / $releaseCount : $amount);

        $submittedEntries = collect((array) data_get($data, 'release_entries', []))
            ->filter(fn ($entry) => is_array($entry))
            ->keyBy(fn (array $entry, int $index) => max((int) data_get($entry, 'release_no', $index + 1), 1));

        return collect(range(1, $releaseCount))
            ->map(function (int $releaseNo) use ($submittedEntries, $releaseCount, $amount, $amountPerRelease, $data): array {
                $entry = (array) $submittedEntries->get($releaseNo, []);
                $defaultAmount = $releaseNo === $releaseCount
                    ? max($amount - ($amountPerRelease * ($releaseCount - 1)), 0)
                    : $amountPerRelease;

                return [
                    'release_no' => $releaseNo,
                    'scheduled_date' => data_get($entry, 'scheduled_date')
                        ?: ($releaseNo === 1 ? (data_get($data, 'cash_release_date') ?: null) : null),
                    'scheduled_time' => data_get($entry, 'scheduled_time')
                        ?: ($releaseNo === 1 ? (data_get($data, 'cash_release_time') ?: null) : null),
                    'scheduled_amount' => number_format($defaultAmount, 2, '.', ''),
                    'scheduled_remarks' => trim((string) data_get($entry, 'scheduled_remarks', '')),
                ];
            })
            ->values()
            ->all();
    }

    private function moduleSpecificRules(string $moduleKey): array
    {
        $rules = [
            'supplier' => [
                'data.completion_mode' => 'nullable|in:complete_internally,send_to_supplier',
                'data.email_address' => 'required|email|max:255',
                'data.representative_full_name' => 'required|string|max:255',
                'data.phone_number' => 'required|string|max:255',
                'data.legal_acknowledgment' => 'accepted',
                'data.electronic_signature_consent' => 'accepted',
                'data.data_privacy_consent' => 'accepted',
                'data.confidentiality_undertaking' => 'accepted',
                'data.company_policy_compliance' => 'accepted',
                'data.false_information_penalty' => 'accepted',
            ],
            'service' => [
                'data.supplier_id' => ['required', $this->acceptedLinkedRecordRule('supplier')],
                'data.coa_id' => [
                    'required_unless:data.mode_of_return,Cash',
                    'nullable',
                    $this->acceptedLinkedRecordRule('chart_account'),
                ],
                'data.products_services_provided' => 'nullable|string|max:2000',
                'data.tax_type' => 'nullable|in:VAT,Expanded Withholding Tax,VAT Exempt,Zero Rated,Zero-Rated,Non-VAT,N/A',
            ],
            'product' => [
                'data.supplier_id' => ['required', $this->acceptedLinkedRecordRule('supplier')],
                'data.coa_id' => ['required', $this->acceptedLinkedRecordRule('chart_account')],
                'data.tax_type' => 'nullable|in:VAT,Expanded Withholding Tax,VAT Exempt,Zero Rated,Zero-Rated,Non-VAT,N/A',
            ],
            'chart_account' => [
                'data.is_sub_account' => 'nullable|boolean',
                'data.parent_account_id' => ['nullable', $this->acceptedLinkedRecordRule('chart_account')],
                'data.bank_account_name' => 'nullable|string|max:255',
                'data.bank_profile' => 'nullable|string|max:255',
                'data.bank_account_number' => 'nullable|string|max:255',
            ],
            'bank_account' => [
                'data.linked_coa_id' => ['required', $this->acceptedLinkedRecordRule('chart_account')],
                'data.bank_account_number' => 'nullable|string|max:255',
            ],
            'pr' => [
                'data.requesting_department' => 'nullable|string|max:255',
                'data.requester_mode' => 'nullable|in:own_request,request_for_another',
                'data.requester_employee_id' => ['required_if:data.requester_mode,request_for_another', 'nullable', Rule::exists('employees', 'id')],
                'data.requestor' => 'required|string|max:255',
                'data.department' => 'nullable|string|max:255',
                'data.request_type' => 'nullable|in:Service,Product',
                'data.supplier_id' => ['nullable', $this->acceptedLinkedRecordRule('supplier')],
                'data.master_item_type' => 'required|in:service,product',
                'data.master_item_id' => ['required', 'integer'],
                'data.coa_id' => ['nullable', $this->acceptedLinkedRecordRule('chart_account')],
                'data.quantity' => 'nullable|numeric|min:0',
                'data.unit_cost' => 'nullable|numeric|min:0',
                'data.estimated_total_cost' => 'required|numeric|min:0',
                'data.priority' => 'required|in:Normal,Urgent',
                'data.needed_date' => 'required|date',
                'data.purpose' => 'required|string|max:2000',
                'data.line_items' => 'nullable|array',
                'data.line_items.*.item_module' => 'nullable|in:service,product',
                'data.line_items.*.item_record_id' => 'nullable|integer',
                'data.line_items.*.item_id' => 'nullable|string|max:255',
                'data.line_items.*.description' => 'nullable|string|max:1000',
                'data.line_items.*.category' => 'nullable|string|max:255',
                'data.line_items.*.quantity' => 'nullable|numeric|min:0',
                'data.line_items.*.amount' => 'nullable|numeric|min:0',
                'data.line_items.*.tax_type' => 'nullable|in:VAT,Expanded Withholding Tax,VAT Exempt,Zero Rated,Zero-Rated,Non-VAT,N/A',
                'data.line_items.*.supplier_id' => ['nullable', $this->acceptedLinkedRecordRule('supplier')],
                'data.line_items.*.client_id' => ['nullable', 'string', 'max:255', $this->financeLineItemClientRule()],
            ],
            'po' => [
                'data.linked_pr_id' => ['required', $this->acceptedLinkedRecordRule('pr')],
                'data.supplier_id' => ['required', $this->acceptedLinkedRecordRule('supplier')],
                'data.linked_item_type' => 'required|in:service,product',
                'data.linked_item_id' => 'required|integer',
                'data.coa_id' => ['required', $this->acceptedLinkedRecordRule('chart_account')],
                'data.quantity' => 'nullable|numeric|min:0',
                'data.unit_cost' => 'nullable|numeric|min:0',
                'data.total_amount' => 'nullable|numeric|min:0',
                'data.line_items' => 'nullable|array',
                'data.line_items.*.item_module' => 'nullable|in:service,product',
                'data.line_items.*.item_record_id' => 'nullable|integer',
                'data.line_items.*.item_id' => 'nullable|string|max:255',
                'data.line_items.*.description' => 'nullable|string|max:1000',
                'data.line_items.*.category' => 'nullable|string|max:255',
                'data.line_items.*.quantity' => 'nullable|numeric|min:0',
                'data.line_items.*.amount' => 'nullable|numeric|min:0',
                'data.line_items.*.tax_type' => 'nullable|in:VAT,Expanded Withholding Tax,VAT Exempt,Zero Rated,Zero-Rated,Non-VAT,N/A',
                'data.line_items.*.supplier_id' => ['nullable', $this->acceptedLinkedRecordRule('supplier')],
                'data.line_items.*.client_id' => ['nullable', 'string', 'max:255', $this->financeLineItemClientRule()],
            ],
            'ca' => [
                'data.requester_mode' => 'nullable|in:own_request,request_for_another',
                'data.requester_employee_id' => ['required_if:data.requester_mode,request_for_another', 'nullable', Rule::exists('employees', 'id')],
                'data.requestor' => 'required|string|max:255',
                'data.purpose' => 'required|string|max:2000',
                'data.amount_requested' => 'required|numeric|min:0',
                'data.release_schedule' => 'nullable|in:Full Release,Staggered Release',
                'data.release_count' => 'nullable|integer|min:1',
                'data.amount_per_release' => 'nullable|numeric|min:0',
                'data.cash_release_date' => 'nullable|date',
                'data.cash_release_time' => 'nullable|date_format:H:i',
                'data.mode_of_release' => 'required|in:Cash,Bank Transfer,Check',
                'data.paid_through' => ['nullable', $this->acceptedLinkedRecordRule('chart_account')],
                'data.bank_account_id' => ['nullable', $this->acceptedLinkedRecordRule('bank_account')],
                'data.coa_id' => ['nullable', $this->acceptedLinkedRecordRule('chart_account')],
                'data.ca_payment_entries' => 'nullable|array',
                'data.ca_payment_entries.*.release_no' => 'nullable|integer|min:1',
                'data.ca_payment_entries.*.payment_date' => 'nullable|date',
                'data.ca_payment_entries.*.payment_amount' => 'nullable|numeric|min:0',
                'data.ca_payment_entries.*.payment_remarks' => 'nullable|string|max:1000',
                'data.release_entries' => 'nullable|array',
                'data.release_entries.*.release_no' => 'nullable|integer|min:1',
                'data.release_entries.*.scheduled_date' => 'nullable|date',
                'data.release_entries.*.scheduled_time' => 'nullable|date_format:H:i',
                'data.release_entries.*.scheduled_amount' => 'nullable|numeric|min:0',
                'data.release_entries.*.scheduled_remarks' => 'nullable|string|max:1000',
            ],
            'lr' => [
                'data.requester_mode' => 'nullable|in:own_request,request_for_another',
                'data.requester_employee_id' => ['required_if:data.requester_mode,request_for_another', 'nullable', Rule::exists('employees', 'id')],
                'data.linked_ca_id' => ['required', $this->acceptedLinkedRecordRule('ca')],
                'data.linked_dv_id' => ['nullable', $this->acceptedLinkedRecordRule('dv', ['source_document_type' => 'ca'])],
                'data.total_cash_advance' => 'required|numeric|min:0',
                'data.purpose' => 'required|string|max:2000',
                'data.employee_id' => 'nullable|string|max:255',
                'data.employee_name' => 'nullable|string|max:255',
                'data.employee_email' => 'nullable|email|max:255',
                'data.contact_number' => 'nullable|string|max:255',
                'data.position' => 'nullable|string|max:255',
                'data.department' => 'nullable|string|max:255',
                'data.superior' => 'nullable|string|max:255',
                'data.superior_email' => 'nullable|email|max:255',
                'data.for_client' => 'nullable|string|max:255',
                'data.client_names' => 'nullable|string|max:1000',
                'data.actual_expenses' => 'required|numeric|min:0',
                'data.variance' => 'required|numeric',
                'data.variance_indicator' => 'required|in:Shortage,Overage,Balanced',
                'data.coa_id' => ['nullable', $this->acceptedLinkedRecordRule('chart_account')],
                'data.line_items' => 'nullable|array',
                'data.line_items.*.item_module' => 'nullable|in:service,product',
                'data.line_items.*.item_record_id' => 'nullable|integer',
                'data.line_items.*.item_id' => 'nullable|string|max:255',
                'data.line_items.*.supplier_id' => ['nullable', $this->acceptedLinkedRecordRule('supplier')],
                'data.line_items.*.description' => 'nullable|string|max:1000',
                'data.line_items.*.category' => 'nullable|string|max:255',
                'data.line_items.*.quantity' => 'nullable|numeric|min:0',
                'data.line_items.*.amount' => 'nullable|numeric|min:0',
                'data.line_items.*.tax_type' => 'nullable|in:VAT,Expanded Withholding Tax,VAT Exempt,Zero Rated,Zero-Rated,Non-VAT,N/A',
                'data.line_items.*.supplier_id' => ['nullable', $this->acceptedLinkedRecordRule('supplier')],
                'data.line_items.*.client_id' => ['nullable', 'string', 'max:255', $this->financeLineItemClientRule()],
                'line_item_receipts' => 'nullable|array',
                'line_item_receipts.*' => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
            ],
            'err' => [
                'data.requester_mode' => 'nullable|in:own_request,request_for_another',
                'data.requester_employee_id' => ['required_if:data.requester_mode,request_for_another', 'nullable', Rule::exists('employees', 'id')],
                'data.requestor' => 'nullable|string|max:255',
                'data.linked_lr_id' => ['required', $this->acceptedLinkedRecordRule('lr', ['variance_indicator' => 'Shortage'])],
                'data.amount' => 'required|numeric|min:0',
                'data.reimbursement_payment_details' => 'nullable|string|max:1000',
                'data.manual_liquidation_entry' => 'nullable|boolean',
                'data.reimbursement_mode' => 'required|in:Cash,Bank Transfer,Check',
                'data.cash_receiver_name' => 'required_if:data.reimbursement_mode,Cash|nullable|string|max:255',
                'data.recipient_bank_account' => 'required_if:data.reimbursement_mode,Bank Transfer|nullable|string|max:255',
                'data.recipient_bank_number' => 'required_if:data.reimbursement_mode,Bank Transfer|nullable|string|max:255',
                'data.bank_account_id' => ['required_if:data.reimbursement_mode,Check', 'nullable', $this->acceptedLinkedRecordRule('bank_account')],
                'data.supplier_id' => ['nullable', $this->acceptedLinkedRecordRule('supplier')],
            ],
            'dv' => [
                'data.source_document_type' => 'required|in:po,ca,err,pda,ibtf',
                'data.source_document_id' => 'required',
                'data.amount' => 'required|numeric|min:0',
                'data.payment_type' => 'required|in:Cash,Check,Bank Transfer,E-Wallet',
                'data.disbursement_type' => 'required|in:Cash,Check,Bank Transfer,Petty Cash',
                'data.bank_account_id' => [
                    'nullable',
                    'required_if:data.payment_type,Check',
                    'required_if:data.source_document_type,po,ca,pda,ibtf',
                    $this->acceptedLinkedRecordRule('bank_account'),
                ],
                'data.coa_id' => ['required', $this->acceptedLinkedRecordRule('chart_account')],
                'data.supplier_id' => ['nullable', $this->acceptedLinkedRecordRule('supplier')],
                'data.fund_source' => 'nullable|string|max:255',
                'data.department' => 'nullable|string|max:255',
                'data.due_date' => 'nullable|date',
                'data.received_by_name' => 'nullable|string|max:255',
                'data.date_received' => 'nullable|date',
                'data.withholding_tax' => 'nullable|numeric|min:0',
                'data.vat_amount' => 'nullable|numeric|min:0',
                'data.net_amount' => 'nullable|numeric|min:0',
                'data.currency' => 'nullable|string|max:10',
                'data.exchange_rate' => 'nullable|numeric|min:0',
                'data.line_items' => 'nullable|array',
                'data.line_items.*.description' => 'nullable|string|max:1000',
                'data.line_items.*.account_code' => 'nullable|string|max:255',
                'data.line_items.*.debit' => 'nullable|numeric|min:0',
                'data.line_items.*.credit' => 'nullable|numeric|min:0',
            ],
            'pda' => [
                'data.payroll_period_id' => 'required|exists:payroll_periods,id',
                'data.total_payroll_amount' => 'nullable|numeric|min:0',
                'data.funding_bank_account_id' => ['required', $this->acceptedLinkedRecordRule('bank_account')],
                'data.payroll_expense_coa_id' => ['required', $this->acceptedLinkedRecordRule('chart_account')],
                'data.employee_count' => 'nullable|integer|min:0',
                'data.basic_salary_total' => 'nullable|numeric|min:0',
                'data.yearly_basic_total' => 'nullable|numeric|min:0',
                'data.daily_rate_total' => 'nullable|numeric|min:0',
                'data.hourly_rate_total' => 'nullable|numeric|min:0',
                'data.minute_rate_total' => 'nullable|numeric|min:0',
                'data.gross_pay_total' => 'nullable|numeric|min:0',
                'data.benefits_total' => 'nullable|numeric|min:0',
                'data.allowances_total' => 'nullable|numeric|min:0',
                'data.deductions_total' => 'nullable|numeric|min:0',
                'data.night_differential_total' => 'nullable|numeric|min:0',
                'data.holiday_pay_total' => 'nullable|numeric|min:0',
                'data.employee_payroll_breakdown' => 'nullable|string|max:10000',
            ],
            'crf' => [
                'data.requester_mode' => 'nullable|in:own_request,request_for_another',
                'data.requester_employee_id' => ['required_if:data.requester_mode,request_for_another', 'nullable', Rule::exists('employees', 'id')],
                'data.requestor' => 'required|string|max:255',
                'data.linked_lr_id' => ['required', $this->acceptedLinkedRecordRule('lr', ['variance_indicator' => 'Overage'])],
                'data.amount_returned' => 'required|numeric|min:0',
                'data.manual_liquidation_entry' => 'nullable|boolean',
                'data.cash_receiver_name' => 'required_if:data.mode_of_return,Cash|nullable|string|max:255',
                'data.recipient_bank_account' => 'required_if:data.mode_of_return,Bank Transfer|nullable|string|max:255',
                'data.recipient_bank_number' => 'required_if:data.mode_of_return,Bank Transfer|nullable|string|max:255',
                'data.coa_id' => ['required_if:data.mode_of_return,Check', 'nullable', $this->acceptedLinkedRecordRule('chart_account')],
            ],
            'ibtf' => [
                'data.source_bank_account_id' => ['required', $this->acceptedLinkedRecordRule('bank_account')],
                'data.destination_bank_account_id' => ['required', $this->acceptedLinkedRecordRule('bank_account')],
                'data.amount' => 'required|numeric|min:0',
                'data.reason' => 'required|string|max:2000',
            ],
            'arf' => [
                'data.linked_po_id' => ['nullable', $this->acceptedLinkedRecordRule('po')],
                'data.linked_dv_id' => ['nullable', $this->arfLinkedDvRule()],
                'data.item_classification' => 'required|in:Fixed Asset,Consumable Inventory',
                'data.asset_code' => 'required|string|max:255',
                'data.item_name' => 'nullable|string|max:255',
                'data.item_code' => 'nullable|string|max:255',
                'data.sku' => 'nullable|string|max:255',
                'data.barcode' => 'nullable|string|max:255',
                'data.qr_code' => 'nullable|string|max:255',
                'data.supplier_id' => ['required', $this->acceptedLinkedRecordRule('supplier')],
                'data.acquisition_cost' => 'required|numeric|min:0',
                'data.acquisition_date' => 'required|date',
                'data.asset_coa_id' => ['required', $this->acceptedLinkedRecordRule('chart_account')],
                'data.location' => 'nullable|string|max:255',
                'data.ordered_quantity' => 'nullable|numeric|min:0',
                'data.delivered_quantity' => 'nullable|numeric|min:0',
                'data.accepted_quantity' => 'nullable|numeric|min:0',
                'data.rejected_quantity' => 'nullable|numeric|min:0',
                'data.beginning_quantity' => 'nullable|numeric|min:0',
                'data.current_quantity' => 'nullable|numeric|min:0',
                'data.reserved_quantity' => 'nullable|numeric|min:0',
                'data.reorder_level' => 'nullable|numeric|min:0',
                'data.minimum_stock_level' => 'nullable|numeric|min:0',
                'data.maximum_stock_level' => 'nullable|numeric|min:0',
                'data.safety_stock_level' => 'nullable|numeric|min:0',
                'data.unit_cost' => 'nullable|numeric|min:0',
                'data.average_cost' => 'nullable|numeric|min:0',
                'data.last_purchase_cost' => 'nullable|numeric|min:0',
                'data.useful_life' => 'required_if:data.item_classification,Fixed Asset|nullable|numeric|min:1',
                'data.residual_value' => 'nullable|numeric|min:0',
                'data.custodian' => ['required', Rule::exists('employees', 'id')],
            ],
        ];

        if ($moduleKey === 'po') {
            $rules['data.linked_item_id'] = ['required', 'integer'];
        }

        if ($moduleKey === 'pr') {
            $rules['data.master_item_id'] = ['required', 'integer'];
        }

        if ($moduleKey === 'chart_account') {
            $rules['data.parent_account_id'] = ['nullable', $this->acceptedLinkedRecordRule('chart_account')];
        }

        if ($moduleKey === 'dv') {
            $rules['data.source_document_id'] = [
                'required',
                'integer',
            ];
        }

        if ($moduleKey === 'lr') {
            $rules['data.linked_dv_id'] = ['nullable', $this->acceptedLinkedRecordRule('dv', ['source_document_type' => 'ca'])];
        }

        if ($moduleKey === 'arf') {
            $rules['data.linked_po_id'] = ['nullable', $this->acceptedLinkedRecordRule('po')];
            $rules['data.linked_dv_id'] = ['nullable', $this->arfLinkedDvRule()];
            $rules['data.linked_po_id'][] = 'required_without:data.linked_dv_id';
            $rules['data.linked_dv_id'][] = 'required_without:data.linked_po_id';
        }

        return $rules[$moduleKey] ?? [];
    }

    private function validateModulePayload(Request $request, ?FinanceRecord $financeRecord = null): void
    {
        $moduleKey = (string) $request->input('module_key', $financeRecord?->module_key);

        if ($moduleKey === 'ca') {
            $data = (array) $request->input('data', []);
            $data['release_schedule'] = $this->normalizeCashAdvanceReleaseSchedule(
                $data['release_schedule'] ?? null,
                $data['release_count'] ?? 1
            );
            $request->merge(['data' => $data]);
        }

        $request->merge([
            'data' => $this->financeHydrateReferenceData($moduleKey, (array) $request->input('data', [])),
        ]);

        $supplierSendMode = $moduleKey === 'supplier'
            && data_get($request->input('data', []), 'completion_mode') === 'send_to_supplier';
        $rules = array_merge($this->commonValidationRules(), $this->moduleSpecificRules($moduleKey));

        if ($this->moduleRequiresTwoPersonApproval($moduleKey) && ! $supplierSendMode) {
            $directory = $this->financeOfficialApproverDirectory();
            $defaultStepUserIds = collect($directory['default_steps'] ?? [])->pluck('user_id')->filter()->values();

            if (blank(data_get($request->input('data', []), 'first_approver_user_id')) && $defaultStepUserIds->get(0)) {
                $data = (array) $request->input('data', []);
                $data['first_approver_user_id'] = $defaultStepUserIds->get(0);
                $request->merge(['data' => $data]);
            }

            if (blank(data_get($request->input('data', []), 'second_approver_user_id')) && $defaultStepUserIds->get(1)) {
                $data = (array) $request->input('data', []);
                $data['second_approver_user_id'] = $defaultStepUserIds->get(1);
                $request->merge(['data' => $data]);
            }

            $allowedApproverIds = collect($directory['options'] ?? [])
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->filter();

            $allowedApproverIds = $allowedApproverIds->unique()->values()->all();

            $rules['data.first_approver_user_id'] = ['required', 'integer', Rule::in($allowedApproverIds)];
            $rules['data.second_approver_user_id'] = ['required', 'integer', Rule::in($allowedApproverIds), 'different:data.first_approver_user_id'];
        }

        if (!$supplierSendMode && in_array($moduleKey, $this->recordTitleRequiredModules(), true)) {
            $rules['record_title'] = 'required|string|max:255';
        }

        if ($supplierSendMode) {
            $rules['record_number'] = 'nullable|string|max:255';
            $rules['record_title'] = 'nullable|string|max:255';
            $rules['record_date'] = 'nullable|date';
            $rules['amount'] = 'nullable|numeric|min:0';
            $rules['status'] = 'nullable|in:Active,Inactive';
            $rules['data.representative_full_name'] = 'nullable|string|max:255';
            $rules['data.designation'] = 'nullable|string|max:255';
            $rules['data.phone_number'] = 'nullable|string|max:255';
            $rules['data.alternate_contact_number'] = 'nullable|string|max:255';
            $rules['data.business_address'] = 'nullable|string|max:1000';
            $rules['data.billing_address'] = 'nullable|string|max:1000';
            $rules['data.tin'] = 'nullable|string|max:255';
            $rules['data.vat_status'] = 'nullable|string|max:255';
            $rules['data.payment_terms'] = 'nullable|string|max:255';
            $rules['data.accreditation_status'] = 'nullable|string|max:255';
            $rules['data.bank_name'] = 'nullable|string|max:255';
            $rules['data.bank_account_name'] = 'nullable|string|max:255';
            $rules['data.bank_account_number'] = 'nullable|string|max:255';
            $rules['data.remarks'] = 'nullable|string|max:2000';
            $rules['data.legal_acknowledgment'] = 'nullable';
            $rules['data.electronic_signature_consent'] = 'nullable';
            $rules['data.data_privacy_consent'] = 'nullable';
            $rules['data.confidentiality_undertaking'] = 'nullable';
            $rules['data.company_policy_compliance'] = 'nullable';
            $rules['data.false_information_penalty'] = 'nullable';
        }

        if ($financeRecord) {
            $rules['module_key'] = ['required', Rule::in([$financeRecord->module_key])];
        }

        $validated = $request->validate($rules);

        if ($this->moduleRequiresTwoPersonApproval($moduleKey) && ! $supplierSendMode) {
            $defaultStepCount = collect($this->financeOfficialApproverDirectory()['default_steps'] ?? [])
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->count();

            if ($defaultStepCount < 2) {
                throw ValidationException::withMessages([
                    'data.first_approver_user_id' => 'Official approver records are incomplete. Please make sure at least two of the Treasurer, President, or Approver roles are linked to active user accounts.',
                ]);
            }
        }

        if ($this->recordTitleLooksLikePlaceholder($moduleKey, data_get($validated, 'record_title'))) {
            throw ValidationException::withMessages([
                'record_title' => $this->moduleRecordTitleLabel($moduleKey) . ' must be entered as a real value, not saved as the placeholder text.',
            ]);
        }

        if ($moduleKey === 'chart_account' && !blank(data_get($validated, 'data.is_sub_account')) && blank(data_get($validated, 'data.parent_account_id'))) {
            throw ValidationException::withMessages([
                'data.parent_account_id' => 'Main Account is required when Sub-Account is enabled.',
            ]);
        }

        if ($moduleKey === 'pr') {
            $itemType = data_get($validated, 'data.master_item_type');
            $itemId = data_get($validated, 'data.master_item_id');

            $itemModule = $itemType === 'product' ? 'product' : 'service';
            if (!$this->recordExistsForWorkflow($itemModule, $itemId)) {
                throw ValidationException::withMessages([
                    'data.master_item_id' => 'The selected item must exist in the chosen master list and be approved.',
                ]);
            }
        }

        if ($moduleKey === 'po') {
            $itemType = data_get($validated, 'data.linked_item_type');
            $itemId = data_get($validated, 'data.linked_item_id');

            $itemModule = $itemType === 'product' ? 'product' : 'service';
            if (!$this->recordExistsForWorkflow($itemModule, $itemId)) {
                throw ValidationException::withMessages([
                    'data.linked_item_id' => 'The selected item must exist in the chosen master list and be approved.',
                ]);
            }
        }

        if ($moduleKey === 'dv') {
            $sourceDocumentType = data_get($validated, 'data.source_document_type');
            $sourceDocumentId = data_get($validated, 'data.source_document_id');

            $allowedModule = match ($sourceDocumentType) {
                'po' => 'po',
                'ca' => 'ca',
                'err' => 'err',
                'pda' => 'pda',
                'ibtf' => 'ibtf',
                'crf' => 'crf',
                default => null,
            };

            if (!$allowedModule || !$this->recordExistsForWorkflow($allowedModule, $sourceDocumentId)) {
                throw ValidationException::withMessages([
                    'data.source_document_id' => 'The linked source document is invalid or is not yet approved.',
                ]);
            }
        }

        if ($moduleKey === 'lr') {
            $linkedDvId = data_get($validated, 'data.linked_dv_id');
            $linkedCaId = data_get($validated, 'data.linked_ca_id');
            if (!blank($linkedDvId) && !$this->recordExistsForWorkflow('dv', $linkedDvId, ['source_document_type' => 'ca'])) {
                throw ValidationException::withMessages([
                    'data.linked_dv_id' => 'The selected DV must come from a cash advance.',
                ]);
            }

            if (!blank($linkedCaId) && !blank($linkedDvId)) {
                $linkedDv = FinanceRecord::query()->find($linkedDvId);
                $linkedCaFromDv = data_get($linkedDv?->data, 'source_document_id');

                if ((string) $linkedCaFromDv !== (string) $linkedCaId) {
                    throw ValidationException::withMessages([
                        'data.linked_ca_id' => 'The selected CA must match the CA used by the linked DV.',
                    ]);
                }
            }
        }

        if ($moduleKey === 'crf') {
            $linkedLrId = data_get($validated, 'data.linked_lr_id');

            if (!$this->recordExistsForWorkflow('lr', $linkedLrId, ['variance_indicator' => 'Overage'])) {
                throw ValidationException::withMessages([
                    'data.linked_lr_id' => 'The selected LR must be approved and marked as overage.',
                ]);
            }
        }

        if ($moduleKey === 'arf') {
            $linkedPoId = data_get($validated, 'data.linked_po_id');
            $linkedDvId = data_get($validated, 'data.linked_dv_id');
            $itemClassification = Str::lower((string) data_get($validated, 'data.item_classification', ''));

            if (blank($linkedPoId) && blank($linkedDvId)) {
                throw ValidationException::withMessages([
                    'data.linked_po_id' => 'ARF must be linked to either a PO or a DV.',
                    'data.linked_dv_id' => 'ARF must be linked to either a PO or a DV.',
                ]);
            }

            if ($itemClassification === 'fixed asset' && blank(data_get($validated, 'data.location'))) {
                throw ValidationException::withMessages([
                    'data.location' => 'Location is required for fixed assets.',
                ]);
            }

            if (filled($linkedDvId)) {
                $linkedDv = FinanceRecord::query()->find($linkedDvId);
                $linkedDvSourceType = Str::lower(trim((string) data_get($linkedDv?->data, 'source_document_type', '')));
                $linkedDvSourceId = data_get($linkedDv?->data, 'source_document_id');

                if ($linkedDvSourceType !== 'po') {
                    throw ValidationException::withMessages([
                        'data.linked_dv_id' => 'ARF only accepts DVs that came from a Purchase Order.',
                    ]);
                }

                if (filled($linkedPoId) && (string) $linkedDvSourceId !== (string) $linkedPoId) {
                    throw ValidationException::withMessages([
                        'data.linked_po_id' => 'The selected PO must match the PO used by the linked DV.',
                        'data.linked_dv_id' => 'The selected DV must belong to the selected PO.',
                    ]);
                }
            }

            $assetCode = trim((string) data_get($validated, 'data.asset_code', ''));
            $existingArfQuery = FinanceRecord::query()->where('module_key', 'arf');

            if ($financeRecord) {
                $existingArfQuery->where('id', '!=', $financeRecord->id);
            }

            $duplicateAsset = $existingArfQuery
                ->get()
                ->first(function (FinanceRecord $existing) use ($assetCode, $linkedPoId, $linkedDvId): bool {
                    $existingAssetCode = Str::lower(trim((string) data_get($existing->data ?? [], 'asset_code', '')));

                    if ($assetCode !== '' && $existingAssetCode === Str::lower($assetCode)) {
                        return true;
                    }

                    return filled($linkedPoId) && (string) data_get($existing->data ?? [], 'linked_po_id') === (string) $linkedPoId
                        || filled($linkedDvId) && (string) data_get($existing->data ?? [], 'linked_dv_id') === (string) $linkedDvId;
                });

            if ($duplicateAsset) {
                throw ValidationException::withMessages([
                    'data.asset_code' => 'An asset registration already exists for this asset code or source document.',
                    'data.linked_po_id' => 'This source document has already been registered as an asset.',
                    'data.linked_dv_id' => 'This source document has already been registered as an asset.',
                ]);
            }
        }

        if ($moduleKey === 'ibtf' && data_get($validated, 'data.source_bank_account_id') === data_get($validated, 'data.destination_bank_account_id')) {
            throw ValidationException::withMessages([
                'data.destination_bank_account_id' => 'Source and destination bank accounts must be different.',
            ]);
        }
    }

    private function normalizeModuleData(string $moduleKey, array $data): array
    {
        if ($moduleKey === 'supplier' && blank(data_get($data, 'completion_mode'))) {
            data_set($data, 'completion_mode', 'complete_internally');
        }

        $data = $this->financeHydrateReferenceData($moduleKey, $data);
        $data = $this->normalizeRequesterEmployeeData($moduleKey, $data);
        unset($data['relationship_status'], $data['next_action'], $data['transaction_progress'], $data['disbursement_status']);

        if (in_array($moduleKey, ['ca', 'lr'], true) && (string) data_get($data, 'for_client', '') !== 'Yes') {
            unset($data['client_names']);
        }

        if ($moduleKey === 'err') {
            unset($data['supplier_id'], $data['coa_id']);

            $mode = (string) data_get($data, 'reimbursement_mode', '');
            if ($mode !== 'Cash') {
                unset($data['cash_receiver_name']);
            }
            if ($mode !== 'Bank Transfer') {
                unset($data['recipient_bank_account'], $data['recipient_bank_number']);
            }
            if ($mode !== 'Check') {
                unset($data['bank_account_id']);
            }
        }

        if (in_array($moduleKey, ['pr', 'po', 'lr'], true)) {
            $lineItems = array_values(array_filter((array) data_get($data, 'line_items', []), function ($item) {
                return is_array($item) && collect($item)->contains(fn ($value) => !blank($value));
            }));

            if ($lineItems) {
                $subtotal = 0.0;
                $discountTotal = 0.0;
                $shippingTotal = 0.0;
                $taxTotal = 0.0;
                $whtTotal = 0.0;
                $grandTotal = 0.0;

                foreach ($lineItems as $index => $item) {
                    $normalizedItem = $this->financeNormalizeTaxLineItem($item);
                    $lineItems[$index] = $normalizedItem;

                    $subtotal += (float) data_get($normalizedItem, 'subtotal', 0);
                    $discountTotal += (float) data_get($normalizedItem, 'discount_amount', 0);
                    $shippingTotal += (float) data_get($normalizedItem, 'shipping_amount', 0);
                    $taxTotal += (float) data_get($normalizedItem, 'tax_amount', 0);
                    $whtTotal += (float) data_get($normalizedItem, 'wht_amount', 0);
                    $grandTotal += (float) data_get($normalizedItem, 'total', 0);
                }

                data_set($data, 'line_items', $lineItems);
                data_set($data, 'subtotal', number_format($subtotal, 2, '.', ''));
                data_set($data, 'discount_total', number_format($discountTotal, 2, '.', ''));
                data_set($data, 'shipping_total', number_format($shippingTotal, 2, '.', ''));
                data_set($data, 'tax_total', number_format($taxTotal, 2, '.', ''));
                data_set($data, 'wht_total', number_format($whtTotal, 2, '.', ''));
                data_set($data, 'grand_total', number_format($grandTotal, 2, '.', ''));
            }

            if ($moduleKey === 'pr') {
                $prGrandTotal = (float) data_get($data, 'grand_total', data_get($data, 'estimated_total_cost', data_get($data, 'amount', 0)));
                data_set($data, 'estimated_total_cost', number_format($prGrandTotal, 2, '.', ''));
                data_set($data, 'amount', number_format($prGrandTotal, 2, '.', ''));
            }

            if ($moduleKey === 'po') {
                $poGrandTotal = (float) data_get($data, 'grand_total', data_get($data, 'total_amount', data_get($data, 'amount', 0)));
                data_set($data, 'total_amount', number_format($poGrandTotal, 2, '.', ''));
                data_set($data, 'amount', number_format($poGrandTotal, 2, '.', ''));
            }

            if ($moduleKey === 'lr') {
                $actualExpenses = (float) data_get($data, 'grand_total', data_get($data, 'actual_expenses', 0));
                $cashAdvance = (float) data_get($data, 'total_cash_advance', 0);
                $variance = $cashAdvance - $actualExpenses;
                $varianceIndicator = $variance > 0 ? 'Overage' : ($variance < 0 ? 'Shortage' : 'Balanced');

                data_set($data, 'actual_expenses', number_format($actualExpenses, 2, '.', ''));
                data_set($data, 'variance', number_format($variance, 2, '.', ''));
                data_set($data, 'variance_indicator', $varianceIndicator);
            }
        }

        if ($moduleKey === 'dv') {
            $sourceType = (string) data_get($data, 'source_document_type', '');
            $sourceId = data_get($data, 'source_document_id');
            $submittedLineItems = array_values((array) data_get($data, 'line_items', []));
            $sourceRecord = $sourceType && $sourceId
                ? FinanceRecord::query()->where('module_key', $sourceType)->find($sourceId)
                : null;

            if ($sourceRecord) {
                $sourcePayload = $this->fallbackDvPayload($sourceRecord);
                foreach ([
                    'supplier_id',
                    'bank_account_id',
                    'payment_type',
                    'disbursement_type',
                    'payee_type',
                    'payee_name',
                    'coa_id',
                    'fund_source',
                    'department',
                    'purpose',
                    'payment_date',
                    'due_date',
                    'withholding_tax',
                    'vat_amount',
                    'currency',
                    'exchange_rate',
                    'received_by_name',
                    'date_received',
                    'reference_number',
                    'remarks',
                    'source_record_number',
                    'source_record_date',
                    'source_requester',
                    'source_department',
                    'source_project',
                    'source_cost_center',
                    'source_fund_source',
                    'source_amount',
                    'source_remaining_balance',
                    'source_current_balance',
                    'source_reserved_balance',
                    'source_available_balance',
                    'source_approval_status',
                    'source_approved_by_name',
                    'source_approved_at',
                    'source_supplier_name',
                    'source_employee_name',
                    'source_payee_type',
                    'source_payee_name',
                    'source_status',
                    'source_workflow_status',
                    'source_relationship_status',
                    'total_disbursed_amount',
                    'percentage_paid',
                    'disbursement_status',
                    'projected_balance_after_payment',
                ] as $lockedField) {
                    data_set($data, $lockedField, data_get($sourcePayload, $lockedField, data_get($data, $lockedField)));
                }

                $sourceLineItems = array_values((array) data_get($sourcePayload, 'line_items', []));
                $lineItems = array_map(function ($item, $index) use ($submittedLineItems) {
                    $submittedItem = $submittedLineItems[$index] ?? [];
                    if (!is_array($item) || !is_array($submittedItem)) {
                        return $item;
                    }

                    foreach (['debit', 'credit'] as $editableField) {
                        if (array_key_exists($editableField, $submittedItem)) {
                            $item[$editableField] = $submittedItem[$editableField];
                        }
                    }

                    return $item;
                }, $sourceLineItems, array_keys($sourceLineItems));

                data_set($data, 'line_items', $lineItems ?: $submittedLineItems);
                if (blank(data_get($data, 'amount'))) {
                    data_set($data, 'amount', data_get($sourcePayload, 'amount', data_get($data, 'amount')));
                }
            }

            if (blank(data_get($data, 'currency'))) {
                data_set($data, 'currency', 'PHP');
            }

            if (blank(data_get($data, 'disbursement_type'))) {
                data_set($data, 'disbursement_type', data_get($data, 'payment_type') ?: 'Cash');
            }

            $amount = (float) data_get($data, 'amount', 0);
            $withholdingTax = (float) data_get($data, 'withholding_tax', 0);
            $vatAmount = (float) data_get($data, 'vat_amount', 0);
            if (blank(data_get($data, 'net_amount'))) {
                data_set($data, 'net_amount', max($amount + $vatAmount - $withholdingTax, 0));
            }

            $availableBalance = (float) data_get($data, 'source_available_balance', data_get($data, 'source_remaining_balance', 0));
            $projectedBalanceAfterPayment = max($availableBalance - $amount, 0);
            data_set($data, 'projected_balance_after_payment', number_format($projectedBalanceAfterPayment, 2, '.', ''));
            data_set($data, 'source_projected_balance_after_payment', number_format($projectedBalanceAfterPayment, 2, '.', ''));
            foreach ([
                'fund_availability_status',
                'fund_availability_warning',
                'fund_availability_requested_amount',
                'fund_availability_available_balance',
                'fund_availability_checked_at',
                'fund_availability_checked_by',
                'fund_availability_checked_by_name',
                'fund_availability_policy_allows_approval',
            ] as $lockedField) {
                unset($data[$lockedField]);
            }

            $lineItems = array_values(array_filter((array) data_get($data, 'line_items', []), function ($item) {
                return is_array($item) && collect($item)->contains(fn ($value) => !blank($value));
            }));
            data_set($data, 'line_items', $lineItems);

            $totalDebit = 0.0;
            $totalCredit = 0.0;
            foreach ($lineItems as $lineItem) {
                $totalDebit += (float) data_get($lineItem, 'debit', 0);
                $totalCredit += (float) data_get($lineItem, 'credit', 0);
            }
            $totalDebit = round($totalDebit, 2);
            $totalCredit = round($totalCredit, 2);
            $difference = round(abs($totalDebit - $totalCredit), 2);
            data_set($data, 'total_debit_amount', number_format($totalDebit, 2, '.', ''));
            data_set($data, 'total_credit_amount', number_format($totalCredit, 2, '.', ''));
            data_set($data, 'accounting_balance_difference', number_format($difference, 2, '.', ''));
            data_set($data, 'accounting_balance_status', $difference < 0.01 ? 'Balanced' : 'Unbalanced');
        }

        if ($moduleKey === 'arf') {
            $custodianId = data_get($data, 'custodian');
            if (!blank($custodianId) && Schema::hasTable('employees')) {
                $employee = Employee::query()
                    ->when(Schema::hasTable('departments'), fn ($query) => $query->with('department'))
                    ->find($custodianId);

                if ($employee) {
                    $option = $this->employeeRequesterOption($employee);
                    data_set($data, 'custodian', $employee->id);
                    data_set($data, 'custodian_name', $option['full_name']);
                    data_set($data, 'custodian_employee_code', $option['employee_code']);
                    data_set($data, 'custodian_email', $option['email']);
                }
            }

            if (blank(data_get($data, 'asset_status'))) {
                data_set($data, 'asset_status', 'Active');
            }

            if (blank(data_get($data, 'asset_last_event'))) {
                data_set($data, 'asset_last_event', 'Asset Registered');
            }
        }

        if ($moduleKey === 'pda') {
            $period = PayrollPeriod::query()->find(data_get($data, 'payroll_period_id'));

            if ($period) {
                $snapshot = $this->payrollPeriodSnapshot($period, true);

                foreach ([
                    'period_start',
                    'period_end',
                    'payroll_start',
                    'payroll_end',
                    'pay_date',
                    'dispute_start',
                    'dispute_end',
                    'status',
                    'employee_count',
                    'basic_salary_total',
                    'yearly_basic_total',
                    'daily_rate_total',
                    'hourly_rate_total',
                    'minute_rate_total',
                    'gross_pay_total',
                    'benefits_total',
                    'allowances_total',
                    'deductions_total',
                    'night_differential_total',
                    'holiday_pay_total',
                    'total_payroll_amount',
                    'payroll_summary_ids',
                    'employee_lines',
                ] as $field) {
                    data_set($data, $field, data_get($snapshot, $field));
                }

                data_set($data, 'payroll_period_label', data_get($snapshot, 'label'));

                if (blank(data_get($data, 'supporting_payroll_summary'))) {
                    data_set($data, 'supporting_payroll_summary', sprintf(
                        '%d payroll summaries for %s. Basic: PHP %s; Gross: PHP %s; Benefits: PHP %s; Allowances: PHP %s; Deductions: PHP %s; Night Differential: PHP %s; Holiday Pay: PHP %s; Net Payroll: PHP %s.',
                        (int) data_get($snapshot, 'employee_count', 0),
                        data_get($data, 'payroll_period_label'),
                        number_format((float) data_get($snapshot, 'basic_salary_total', 0), 2),
                        number_format((float) data_get($snapshot, 'gross_pay_total', 0), 2),
                        number_format((float) data_get($snapshot, 'benefits_total', 0), 2),
                        number_format((float) data_get($snapshot, 'allowances_total', 0), 2),
                        number_format((float) data_get($snapshot, 'deductions_total', 0), 2),
                        number_format((float) data_get($snapshot, 'night_differential_total', 0), 2),
                        number_format((float) data_get($snapshot, 'holiday_pay_total', 0), 2),
                        number_format((float) data_get($snapshot, 'total_payroll_amount', 0), 2)
                    ));
                }

                if (blank(data_get($data, 'employee_payroll_breakdown'))) {
                    $lines = collect((array) data_get($snapshot, 'employee_lines', []))
                        ->map(function (array $line, int $index) {
                            return sprintf(
                                '%d. %s | %s / %s | Basic PHP %s | Gross PHP %s | Deductions PHP %s | Net PHP %s',
                                $index + 1,
                                data_get($line, 'employee_name') ?: data_get($line, 'employee_code') ?: 'Employee',
                                data_get($line, 'salary_grade') ?: 'No grade',
                                data_get($line, 'payroll_level') ?: 'No level',
                                number_format((float) data_get($line, 'monthly_basic_salary', 0), 2),
                                number_format((float) data_get($line, 'gross_pay', 0), 2),
                                number_format((float) data_get($line, 'total_deductions', 0), 2),
                                number_format((float) data_get($line, 'net_pay', 0), 2)
                            );
                        })
                        ->implode("\n");

                    data_set($data, 'employee_payroll_breakdown', $lines ?: 'No employee payroll profiles or summaries found for this period.');
                }
            }
        }

        if ($moduleKey === 'ca') {
            $cashAdvanceAmount = (float) data_get($data, 'amount_requested', 0);
            $releaseCount = max((int) data_get($data, 'release_count', 1), 1);
            $amountPerRelease = $releaseCount > 0 ? $cashAdvanceAmount / $releaseCount : $cashAdvanceAmount;
            $today = now()->toDateString();
            $releaseEntries = $this->normalizeCashAdvanceReleaseEntries($data);
            $entries = array_values(array_filter((array) data_get($data, 'ca_payment_entries', []), function ($entry) {
                return is_array($entry) && (
                    !blank(data_get($entry, 'payment_date'))
                    || (float) data_get($entry, 'payment_amount', 0) > 0
                    || !blank(data_get($entry, 'payment_remarks'))
                );
            }));

            $entries = array_values(array_map(function (array $entry, int $index) {
                return [
                    'release_no' => max((int) data_get($entry, 'release_no', $index + 1), 1),
                    'payment_date' => data_get($entry, 'payment_date') ?: null,
                    'payment_amount' => number_format((float) data_get($entry, 'payment_amount', 0), 2, '.', ''),
                    'payment_remarks' => trim((string) data_get($entry, 'payment_remarks', '')),
                ];
            }, $entries, array_keys($entries)));

            $totalPaid = collect($entries)->sum(fn (array $entry) => (float) data_get($entry, 'payment_amount', 0));
            $releaseEntryMap = collect($releaseEntries)->keyBy(fn (array $entry) => (int) data_get($entry, 'release_no', 0));
            $paidReleaseNos = collect($entries)
                ->groupBy(fn (array $entry) => (int) data_get($entry, 'release_no', 0))
                ->filter(function ($releaseEntries, $releaseNo) use ($releaseEntryMap, $amountPerRelease) {
                    $scheduledAmount = (float) data_get($releaseEntryMap->get((int) $releaseNo), 'scheduled_amount', $amountPerRelease);

                    return $scheduledAmount > 0
                        && $releaseEntries->sum(fn (array $entry) => (float) data_get($entry, 'payment_amount', 0)) >= $scheduledAmount;
                })
                ->keys()
                ->count();
            $remainingBalance = max($cashAdvanceAmount - $totalPaid, 0);

            $firstReleaseEntry = $releaseEntries[0] ?? [];
            $overdueCount = 0;
            foreach (range(1, $releaseCount) as $releaseNo) {
                $paymentEntries = collect($entries)->filter(fn (array $entry) => (int) data_get($entry, 'release_no', 0) === $releaseNo);
                $paidAmount = $paymentEntries->sum(fn (array $entry) => (float) data_get($entry, 'payment_amount', 0));
                $schedule = (array) ($releaseEntryMap->get($releaseNo) ?? []);
                $scheduledAmount = (float) data_get(
                    $schedule,
                    'scheduled_amount',
                    $releaseNo === $releaseCount
                        ? max($cashAdvanceAmount - ($amountPerRelease * ($releaseCount - 1)), 0)
                        : $amountPerRelease
                );
                $scheduledDate = $this->financeNormalizeDateString(data_get($schedule, 'scheduled_date'));
                $isOverdue = filled($scheduledDate) && $scheduledDate < $today;

                $status = $paidAmount >= $scheduledAmount && $scheduledAmount > 0
                    ? 'Paid'
                    : ($paidAmount > 0 ? 'Partial' : ($isOverdue ? 'Unpaid' : 'Pending'));
                if ($status === 'Unpaid') {
                    $overdueCount++;
                }
            }

            data_set($data, 'amount_per_release', number_format($amountPerRelease, 2, '.', ''));
            data_set($data, 'release_entries', $releaseEntries);
            data_set($data, 'cash_release_date', data_get($firstReleaseEntry, 'scheduled_date'));
            data_set($data, 'cash_release_time', data_get($firstReleaseEntry, 'scheduled_time'));
            data_set($data, 'ca_payment_entries', $entries);
            data_set($data, 'ca_payment_total_paid', number_format($totalPaid, 2, '.', ''));
            data_set($data, 'ca_payment_remaining_balance', number_format($remainingBalance, 2, '.', ''));
            data_set($data, 'ca_payment_paid_count', min($paidReleaseNos, $releaseCount));
            data_set($data, 'ca_payment_remaining_count', max($releaseCount - min($paidReleaseNos, $releaseCount), 0));
            data_set($data, 'ca_payment_overdue_count', $overdueCount);
            data_set($data, 'ca_payment_status', $remainingBalance <= 0 && $cashAdvanceAmount > 0
                ? 'Fully Released'
                : ($overdueCount > 0 ? 'Overdue / Unpaid' : ($totalPaid > 0 ? 'Partially Released' : 'Pending Release')));
        }

        if ($moduleKey === 'lr') {
            $lineItems = array_values(array_filter((array) data_get($data, 'line_items', []), function ($item) {
                return is_array($item) && collect($item)->contains(fn ($value) => !blank($value));
            }));

            $subtotal = 0;
            $discountTotal = 0;
            $shippingTotal = 0;
            $taxTotal = 0;
            $whtTotal = 0;
            $actualExpenses = 0;

            foreach ($lineItems as $index => $item) {
                $quantity = (float) data_get($item, 'quantity', 0);
                $amount = (float) data_get($item, 'amount', 0);
                $rowSubtotal = $quantity * $amount;
                $discountPercent = (float) str_replace('%', '', (string) data_get($item, 'discount', '0'));
                $manualDiscount = (float) data_get($item, 'discount_amount', 0);
                $discountAmount = $discountPercent > 0 ? $rowSubtotal * ($discountPercent / 100) : $manualDiscount;
                $shippingAmount = (float) data_get($item, 'shipping_amount', 0);
                $taxAmount = (float) data_get($item, 'tax_amount', 0);
                $whtAmount = (float) data_get($item, 'wht_amount', 0);
                $rowTotal = $rowSubtotal - $discountAmount + $shippingAmount + $taxAmount - $whtAmount;

                data_set($lineItems, "{$index}.subtotal", number_format($rowSubtotal, 2, '.', ''));
                data_set($lineItems, "{$index}.discount_amount", number_format($discountAmount, 2, '.', ''));
                data_set($lineItems, "{$index}.total", number_format($rowTotal, 2, '.', ''));

                $subtotal += $rowSubtotal;
                $discountTotal += $discountAmount;
                $shippingTotal += $shippingAmount;
                $taxTotal += $taxAmount;
                $whtTotal += $whtAmount;
                $actualExpenses += $rowTotal;
            }

            $cashAdvance = (float) data_get($data, 'total_cash_advance', 0);
            if (empty($lineItems)) {
                $actualExpenses = (float) data_get($data, 'actual_expenses', data_get($data, 'grand_total', $cashAdvance));
            }
            $variance = $cashAdvance - $actualExpenses;
            $varianceIndicator = $variance > 0 ? 'Overage' : ($variance < 0 ? 'Shortage' : 'Balanced');

            data_set($data, 'line_items', $lineItems);
            data_set($data, 'subtotal', number_format($subtotal, 2, '.', ''));
            data_set($data, 'discount_total', number_format($discountTotal, 2, '.', ''));
            data_set($data, 'shipping_total', number_format($shippingTotal, 2, '.', ''));
            data_set($data, 'tax_total', number_format($taxTotal, 2, '.', ''));
            data_set($data, 'wht_total', number_format($whtTotal, 2, '.', ''));
            data_set($data, 'grand_total', number_format($actualExpenses, 2, '.', ''));
            data_set($data, 'actual_expenses', number_format($actualExpenses, 2, '.', ''));
            data_set($data, 'variance', number_format($variance, 2, '.', ''));
            data_set($data, 'variance_indicator', $varianceIndicator);
        }

        if ($moduleKey === 'ibtf') {
            $bankCodeFor = function (mixed $bankAccountId): string {
                $bankAccount = blank($bankAccountId)
                    ? null
                    : FinanceRecord::query()->where('module_key', 'bank_account')->find($bankAccountId);
                $linkedCoaId = data_get($bankAccount?->data, 'linked_coa_id');
                $chartAccount = blank($linkedCoaId)
                    ? null
                    : FinanceRecord::query()->where('module_key', 'chart_account')->find($linkedCoaId);

                return (string) (
                    $chartAccount?->record_number
                    ?: data_get($chartAccount?->data, 'account_code')
                    ?: $linkedCoaId
                    ?: $bankAccount?->record_number
                    ?: ''
                );
            };

            if (blank(data_get($data, 'source_account_code'))) {
                data_set($data, 'source_account_code', $bankCodeFor(data_get($data, 'source_bank_account_id')));
            }

            if (blank(data_get($data, 'destination_account_code'))) {
                data_set($data, 'destination_account_code', $bankCodeFor(data_get($data, 'destination_bank_account_id')));
            }
        }

        if ($moduleKey === 'arf') {
            $classification = (string) data_get($data, 'item_classification', 'Fixed Asset');
            $currentQuantity = (float) data_get($data, 'current_quantity', data_get($data, 'accepted_quantity', data_get($data, 'beginning_quantity', 0)));
            $reservedQuantity = (float) data_get($data, 'reserved_quantity', 0);
            $unitCost = (float) data_get($data, 'unit_cost', 0);

            data_set($data, 'available_quantity', number_format(max($currentQuantity - $reservedQuantity, 0), 2, '.', ''));
            data_set($data, 'total_cost', number_format($currentQuantity * $unitCost, 2, '.', ''));

            if ($classification === 'Fixed Asset') {
                $acquisitionCost = (float) data_get($data, 'acquisition_cost', 0);
                $residualValue = (float) data_get($data, 'residual_value', 0);
                $usefulLife = max((float) data_get($data, 'useful_life', 0), 0);
                $depreciableAmount = max($acquisitionCost - $residualValue, 0);
                $annualDepreciation = $usefulLife > 0 ? $depreciableAmount / $usefulLife : 0;
                $monthlyDepreciation = $annualDepreciation / 12;
                $acquisitionDate = data_get($data, 'acquisition_date');
                $monthsElapsed = 0;

                if (!blank($acquisitionDate)) {
                    try {
                        $monthsElapsed = max(now()->startOfMonth()->diffInMonths(\Carbon\Carbon::parse($acquisitionDate)->startOfMonth()), 0);
                    } catch (\Throwable) {
                        $monthsElapsed = 0;
                    }
                }

                $accumulatedDepreciation = min($monthlyDepreciation * $monthsElapsed, $depreciableAmount);

                data_set($data, 'depreciable_amount', number_format($depreciableAmount, 2, '.', ''));
                data_set($data, 'annual_depreciation', number_format($annualDepreciation, 2, '.', ''));
                data_set($data, 'monthly_depreciation', number_format($monthlyDepreciation, 2, '.', ''));
                data_set($data, 'accumulated_depreciation', number_format($accumulatedDepreciation, 2, '.', ''));
                data_set($data, 'net_book_value', number_format(max($acquisitionCost - $accumulatedDepreciation, 0), 2, '.', ''));
            } else {
                foreach (['useful_life', 'residual_value', 'depreciable_amount', 'annual_depreciation', 'monthly_depreciation', 'accumulated_depreciation', 'net_book_value'] as $field) {
                    unset($data[$field]);
                }
            }

            $data = $this->financeSyncConsumableInventoryCosts($data);
        }

        if (in_array($moduleKey, ['err', 'crf'], true) && !filter_var(data_get($data, 'manual_liquidation_entry'), FILTER_VALIDATE_BOOLEAN)) {
            $linkedLr = FinanceRecord::query()
                ->where('module_key', 'lr')
                ->find(data_get($data, 'linked_lr_id'));

            if ($linkedLr) {
                $variance = abs((float) data_get($linkedLr->data, 'variance', 0));
                if ($moduleKey === 'err') {
                    data_set($data, 'amount', number_format($variance, 2, '.', ''));
                    data_set($data, 'expense_details', data_get($data, 'expense_details') ?: 'Shortage from linked liquidation report.');
                } else {
                    data_set($data, 'amount_returned', number_format($variance, 2, '.', ''));
                }
            }
        }

        return $data;
    }

    private function normalizeRequesterEmployeeData(string $moduleKey, array $data): array
    {
        if (! in_array($moduleKey, ['pr', 'ca', 'lr', 'err', 'crf'], true)) {
            return $data;
        }

        if ((string) data_get($data, 'requester_mode', 'own_request') !== 'request_for_another') {
            $currentEmployee = $this->resolveCurrentUserEmployee(Auth::user()?->email ?? '', Auth::user()?->name ?? '');
            if ($currentEmployee) {
                $option = $this->employeeRequesterOption($currentEmployee);
                data_set($data, 'requestor', data_get($data, 'requestor') ?: $option['full_name']);
                data_set($data, 'employee_name', data_get($data, 'employee_name') ?: $option['full_name']);
                data_set($data, 'employee_id', data_get($data, 'employee_id') ?: $option['employee_code']);
                data_set($data, 'employee_email', data_get($data, 'employee_email') ?: $option['email']);
                data_set($data, 'contact_number', data_get($data, 'contact_number') ?: $option['contact_number']);
                data_set($data, 'position', data_get($data, 'position') ?: $option['position']);
                data_set($data, 'department', data_get($data, 'department') ?: $option['department']);
                data_set($data, 'superior', data_get($data, 'superior') ?: $option['superior']);
                data_set($data, 'superior_email', data_get($data, 'superior_email') ?: $option['superior_email']);
            }

            unset($data['requester_employee_id']);

            return $data;
        }

        $employeeId = data_get($data, 'requester_employee_id');

        if (blank($employeeId) || ! Schema::hasTable('employees')) {
            return $data;
        }

        $employee = Employee::query()
            ->when(Schema::hasTable('departments'), fn ($query) => $query->with('department'))
            ->find($employeeId);

        if (! $employee) {
            return $data;
        }

        $option = $this->employeeRequesterOption($employee);

        data_set($data, 'requester_employee_id', $employee->id);
        data_set($data, 'requestor', $option['full_name']);
        data_set($data, 'employee_name', $option['full_name']);
        data_set($data, 'employee_id', $option['employee_code']);
        data_set($data, 'employee_email', $option['email']);
        data_set($data, 'contact_number', $option['contact_number']);
        data_set($data, 'position', $option['position']);
        data_set($data, 'department', $option['department']);
        data_set($data, 'superior', $option['superior']);
        data_set($data, 'superior_email', $option['superior_email']);

        return $data;
    }

    private function normalizeDvPayeeFields(array $data, string $recordTitle): array
    {
        $recordTitle = trim($recordTitle);
        $payeeName = trim((string) data_get($data, 'payee_name', ''));
        $payeeType = trim((string) data_get($data, 'payee_type', ''));
        $sourcePayeeName = trim((string) data_get($data, 'source_payee_name', ''));
        $sourcePayeeType = trim((string) data_get($data, 'source_payee_type', ''));
        $receivedByName = trim((string) data_get($data, 'received_by_name', ''));

        if ($payeeName === '') {
            $payeeName = $recordTitle !== ''
                ? $recordTitle
                : ($sourcePayeeName !== '' ? $sourcePayeeName : $receivedByName);
            data_set($data, 'payee_name', $payeeName);
        }

        if ($payeeType === '' && $payeeName !== '') {
            data_set($data, 'payee_type', $sourcePayeeType !== '' ? $sourcePayeeType : 'User');
        }

        return $data;
    }

    private function recordExistsForWorkflow(string $moduleKey, mixed $recordId, array $dataConstraints = []): bool
    {
        $record = $this->acceptedRecordQuery($moduleKey, $dataConstraints)
            ->where('id', $recordId)
            ->first();

        return $record instanceof FinanceRecord && $this->financeRecordEligibleAsSourceDocument($record);
    }

    public function index(Request $request)
    {
        $moduleKey = $request->get('module', 'supplier');
        $workflowFilter = $request->get('workflow_status', 'all');
        $moduleLabels = $this->accessibleFinanceModuleLabels();

        if (empty($moduleLabels)) {
            abort(403, 'You do not have permission to access finance modules.');
        }

        if (!array_key_exists($moduleKey, self::MODULES)) {
            $moduleKey = 'supplier';
        }

        if (!array_key_exists($moduleKey, $moduleLabels)) {
            $moduleKey = array_key_first($moduleLabels) ?: 'pr';
        }

        if (!in_array($workflowFilter, array_merge(['all'], self::WORKFLOW_STATUSES), true)) {
            $workflowFilter = 'all';
        }

        $records = FinanceRecord::query()
            ->where('workflow_status', '!=', 'Deleted')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->filter(fn (FinanceRecord $record) => $this->canViewFinanceRecord($record))
            ->map(fn (FinanceRecord $record) => $this->transformRecordForIndex($record))
            ->values();

        $inventoryHistoryBoard = $this->financeInventoryHistoryBoardItems();
        $lookupOptions = $this->resolveLookupOptions();
        $officialApproverDirectory = $this->financeOfficialApproverDirectory();

        $sourceRecords = FinanceRecord::query()
            ->where(function ($statusQuery) {
                $statusQuery->where('workflow_status', 'Accepted')
                    ->orWhere('approval_status', 'Approved');
            })
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->filter(fn (FinanceRecord $record) => $this->financeRecordEligibleAsSourceDocument($record))
            ->filter(fn (FinanceRecord $record) => $this->canViewFinanceRecord($record))
            ->map(fn (FinanceRecord $record) => $this->transformRecordForIndex($record))
            ->values();

        return view('finance.index', [
            'records' => $records,
            'sourceRecords' => $sourceRecords,
            'moduleLabels' => $moduleLabels,
            'lookupOptions' => $lookupOptions,
            'currentModule' => $moduleKey,
            'currentWorkflowFilter' => $workflowFilter,
            'canApproveFinance' => $this->canApproveFinance(),
            'canManageFinanceSettings' => $this->canManageFinanceSettings(),
            'financeDropdownOptions' => $this->financeDropdownSettings(),
            'financeAttachmentTypes' => $this->financeAttachmentTypesSettings(),
            'financeLabelOverrides' => $this->financeLabelOverridesSettings(),
            'financeNoteVisibilityOptions' => $this->financeNoteVisibilityOptions(),
            'officialApproverOptions' => $officialApproverDirectory['options'],
            'defaultApprovalSteps' => $officialApproverDirectory['default_steps'],
            'requestTypeModules' => $this->requestTypeModuleKeys(),
            'currentUserName' => Auth::user()->name ?? 'Unknown User',
            'currentUserEmail' => Auth::user()->email ?? '',
            'currentUserEmployeeId' => Auth::user()?->employeeProfile?->id,
            'currentUserContact' => $this->resolveCurrentUserContactProfile(),
            'currentUserFinanceRoles' => $this->financeCurrentUserAuthorityRoles(),
            'inventoryHistoryBoard' => $inventoryHistoryBoard,
        ]);
    }

    public function addNote(Request $request, FinanceRecord $financeRecord)
    {
        if (! $this->canViewFinanceRecord($financeRecord)) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:5000'],
            'visibility' => ['required', 'string', Rule::in(collect($this->financeNoteVisibilityOptions())->pluck('value')->all())],
        ]);

        $noteBody = trim((string) $validated['note']);
        $visibility = Str::lower(trim((string) $validated['visibility']));

        if ($noteBody === '') {
            throw ValidationException::withMessages([
                'note' => 'Please enter a note.',
            ]);
        }

        $data = $financeRecord->data ?? [];
        $existingNotes = array_values(array_filter((array) data_get($data, 'finance_notes', []), fn ($item) => is_array($item)));
        $newNote = [
            'id' => (string) Str::uuid(),
            'note' => $noteBody,
            'visibility' => $visibility ?: 'all',
            'visibility_label' => $this->financeNoteVisibilityLabel($visibility ?: 'all'),
            'author_user_id' => Auth::id(),
            'author_name' => Auth::user()?->name ?: 'Finance Team',
            'author_email' => Auth::user()?->email ?: '',
            'created_at' => now()->format('Y-m-d H:i:s'),
            'source' => 'manual',
        ];

        array_unshift($existingNotes, $newNote);

        $updatedData = $data;
        $updatedData['finance_notes'] = $existingNotes;
        $updatedData['updated_by_name'] = Auth::user()?->name ?: 'System';

        $dataForHistoryOld = [
            'finance_notes_count' => count((array) data_get($data, 'finance_notes', [])),
        ];
        $dataForHistoryNew = [
            'finance_notes_count' => count($existingNotes),
            'finance_notes_visibility' => $newNote['visibility'],
        ];

        $updatedData = $this->appendFinanceHistoryEntry(
            $updatedData,
            'Note Added',
            $financeRecord->module_key,
            $dataForHistoryOld,
            $dataForHistoryNew
        );

        $financeRecord->forceFill([
            'data' => $updatedData,
        ])->save();

        return $this->financeActionResponse($request, 'Note added successfully.', $financeRecord);
    }

    public function adminDashboard(Request $request)
    {
        if (! $this->canAdministerFinance()) {
            abort(403, 'Unauthorized');
        }

        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'module' => (string) $request->query('module', 'all'),
            'status' => (string) $request->query('status', 'all'),
        ];

        $allRecords = FinanceRecord::query()
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->get();

        $records = $allRecords
            ->filter(function (FinanceRecord $record) use ($filters): bool {
                if ($filters['module'] !== 'all' && $record->module_key !== $filters['module']) {
                    return false;
                }

                if ($filters['status'] !== 'all' && ($record->workflow_status ?? 'Uploaded') !== $filters['status']) {
                    return false;
                }

                if ($filters['search'] === '') {
                    return true;
                }

                $haystack = strtolower(implode(' ', [
                    $record->record_number,
                    $record->record_title,
                    $record->module_key,
                    $this->moduleLabel($record->module_key),
                    $record->workflow_status,
                    $record->approval_status,
                    $record->user,
                    data_get($record->data, 'delete_requested_by_name'),
                ]));

                return str_contains($haystack, strtolower($filters['search']));
            })
            ->map(function (FinanceRecord $record): FinanceRecord {
                $record->setAttribute('dashboard_can_approve', $this->canApproveSubmittedFinanceRecord($record));
                $record->setAttribute('dashboard_can_revert', $this->canRevertSubmittedFinanceRecord($record));
                $record->setAttribute('dashboard_can_archive', $this->canArchiveFinanceRecord($record));
                $record->setAttribute('dashboard_can_unarchive', $this->canUnarchiveFinanceRecord($record));
                $record->setAttribute('dashboard_can_approve_delete', $this->canApproveDeleteRequest($record));

                return $record;
            })
            ->values();

        $inventoryHistoryBoard = $this->financeInventoryHistoryBoardItems();

        $counts = [
            'submitted' => $allRecords->where('workflow_status', 'Submitted')->count(),
            'accepted' => $allRecords->where('workflow_status', 'Accepted')->count(),
            'delete_requested' => $allRecords->where('workflow_status', 'Delete Requested')->count(),
            'deleted' => $allRecords->where('workflow_status', 'Deleted')->count(),
        ];

        return view('admin.finance-dashboard', [
            'records' => $records,
            'moduleLabels' => self::MODULES,
            'workflowStatuses' => self::WORKFLOW_STATUSES,
            'filters' => $filters,
            'counts' => $counts,
            'inventoryHistoryBoard' => $inventoryHistoryBoard,
        ]);
    }

    public function updateDropdownSettings(Request $request)
    {
        if (!$this->canManageFinanceSettings()) {
            abort(403, 'Unauthorized');
        }

        if (
            !Schema::hasTable('settings')
            || !Schema::hasColumn('settings', 'key')
            || !Schema::hasColumn('settings', 'value')
        ) {
            return response()->json([
                'message' => 'Finance dropdown settings storage is not ready. Please run the database migrations.',
            ], 422);
        }

        $validated = $request->validate([
            'options' => ['required', 'array'],
            'attachment_types' => ['nullable', 'array'],
            'label_overrides' => ['nullable', 'array'],
        ]);

        $settings = $this->sanitizeFinanceDropdownSettings($validated['options']);

        Setting::query()->updateOrCreate(
            ['key' => self::DROPDOWN_SETTINGS_KEY],
            ['value' => json_encode($settings, JSON_UNESCAPED_SLASHES)]
        );

        $attachmentTypes = null;
        if ($request->has('attachment_types')) {
            $attachmentTypes = $this->sanitizeFinanceAttachmentTypes((array) ($validated['attachment_types'] ?? []));
            Setting::query()->updateOrCreate(
                ['key' => self::ATTACHMENT_TYPES_SETTINGS_KEY],
                ['value' => json_encode($attachmentTypes, JSON_UNESCAPED_SLASHES)]
            );
        }

        $labelOverrides = null;
        if ($request->has('label_overrides')) {
            $labelOverrides = $this->sanitizeFinanceLabelOverrides((array) ($validated['label_overrides'] ?? []));
            Setting::query()->updateOrCreate(
                ['key' => self::LABEL_OVERRIDES_SETTINGS_KEY],
                ['value' => json_encode($labelOverrides, JSON_UNESCAPED_SLASHES)]
            );
        }

        return response()->json([
            'message' => 'Finance dropdown settings saved.',
            'options' => $settings,
            'attachment_types' => $attachmentTypes,
            'label_overrides' => $labelOverrides,
        ]);
    }

    public function show(FinanceRecord $financeRecord)
    {
        if (!$this->canViewFinanceRecord($financeRecord)) {
            abort(403, 'Unauthorized');
        }

        return response()->json($this->transformRecord($financeRecord));
    }

    public function previewHtml(FinanceRecord $financeRecord)
    {
        if (! $this->canViewFinanceRecord($financeRecord)) {
            abort(403, 'Unauthorized');
        }

        return view('finance.preview-html', $this->financePdfContext($financeRecord->fresh()));
    }

    public function previewPdf(Request $request, FinanceRecord $financeRecord)
    {
        if (! $this->canViewFinanceRecord($financeRecord)) {
            abort(403, 'Unauthorized');
        }

        $forceTemplatePreview = $request->boolean('template');
        $context = $this->financePdfContext($financeRecord->fresh(), false, $forceTemplatePreview);
        $pdf = Pdf::loadView('finance.pdf', $context)->setPaper('letter', 'portrait');

        $fileName = $this->normalizeFinanceRecordNumber($financeRecord->module_key, $financeRecord->record_number) ?: 'finance-record';

        return $pdf->stream($fileName . '.pdf');
    }

    public function openRecord(FinanceRecord $financeRecord)
    {
        if (!$this->canViewFinanceRecord($financeRecord)) {
            abort(403, 'Unauthorized');
        }

        return redirect()->route('finance', [
            'module' => $financeRecord->module_key,
            'record' => $financeRecord->id,
        ]);
    }

    public function store(Request $request)
    {
        $this->validateModulePayload($request);

        if (! $this->currentUserCanAccessFinanceModule((string) $request->module_key)) {
            abort(403, 'You do not have permission to create records in this finance module.');
        }

        $attachments = $this->persistAttachments($request);
        $dataInput = $request->input('data', []);
        if ($request->module_key === 'lr') {
            $dataInput = $this->financeAttachLineItemReceipts($request, is_array($dataInput) ? $dataInput : []);
        }
        $data = $this->normalizeModuleData($request->module_key, $dataInput);
        $data = $this->initializeFinanceApprovalState($data, $request->module_key);
        $supplierSendMode = $request->module_key === 'supplier' && data_get($data, 'completion_mode') === 'send_to_supplier';
        if ($supplierSendMode) {
            $data['approval_steps'] = array_values((array) $data['approval_steps']);
            $data['approval_actions'] = array_values((array) $data['approval_actions']);
            $data['approval_required_count'] = max(2, (int) ($data['approval_required_count'] ?? 0));
            $data['approval_completed_count'] = 0;
            $data['approval_remaining_count'] = count((array) $data['approval_steps']);
            $data['first_approver_user_id'] = data_get($data, 'approval_steps.0.user_id');
            $data['second_approver_user_id'] = data_get($data, 'approval_steps.1.user_id');
        }
        $recordNumber = trim((string) $request->input('record_number', ''));
        $recordTitle = trim((string) $request->input('record_title', ''));
        $recordDate = $request->input('record_date');

        if ($supplierSendMode) {
            $recordNumber = $recordNumber ?: $this->normalizeFinanceRecordNumber('supplier', '');
            $recordTitle = $this->recordTitleLooksLikePlaceholder('supplier', $recordTitle) ? '' : $recordTitle;
            $recordDate = $recordDate ?: now()->toDateString();
        }

        $recordTitle = $this->financeAutoRecordTitle($request->module_key, $data, $recordTitle);
        if ($request->module_key === 'dv') {
            $data = $this->normalizeDvPayeeFields($data, $recordTitle);
            $data['line_items'] = $this->financeNormalizedDvLineItems(new FinanceRecord([
                'module_key' => 'dv',
                'record_number' => $recordNumber,
                'record_title' => $recordTitle,
                'data' => $data,
            ]), $data);
            $data = array_merge($data, $this->financeDvAccountingSummaryFromLineItems((array) $data['line_items']));
        }
        $recordNumber = $this->normalizeFinanceRecordNumber($request->module_key, $recordNumber);
        $recordAmount = in_array($request->module_key, ['dv', 'pda'], true)
            ? data_get($data, $request->module_key === 'pda' ? 'total_payroll_amount' : 'amount')
            : $request->amount;
        $actorName = Auth::user()?->name ?: 'System';
        $data['created_by_name'] = data_get($data, 'created_by_name') ?: $actorName;
        $data['requested_by_name'] = data_get($data, 'requested_by_name') ?: $this->financeRequestedByNameFromData($request->module_key, $data);
        $data['submitted_by_name'] = data_get($data, 'submitted_by_name') ?: $actorName;
        $data['updated_by_name'] = data_get($data, 'updated_by_name') ?: $actorName;
        $data = $this->appendFinanceHistoryEntry($data, 'Created', $request->module_key, [], [
            'record_number' => $recordNumber,
            'record_title' => $recordTitle,
            'record_date' => $recordDate,
            'amount' => $recordAmount,
            'status' => 'Draft',
            'workflow_status' => 'Uploaded',
            'approval_status' => 'Pending',
            'data' => $data,
            'attachments' => $attachments,
        ]);

        $record = FinanceRecord::create([
            'module_key' => $request->module_key,
            'record_number' => $recordNumber,
            'record_title' => $recordTitle,
            'record_date' => $recordDate,
            'amount' => $recordAmount,
            'status' => 'Draft',
            'workflow_status' => 'Uploaded',
            'approval_status' => 'Pending',
            'submitted_by' => Auth::id(),
            'submitted_at' => null,
            'approved_by' => null,
            'approved_at' => null,
            'review_note' => null,
            'data' => $data,
            'attachments' => $attachments,
            'share_token' => null,
            'shared_at' => null,
            'user' => Auth::user()->name ?? 'Unknown User',
        ]);

        if ($request->module_key === 'supplier' && data_get($data, 'completion_mode') === 'send_to_supplier') {
            $this->sendSupplierCompletionEmail($record);
            $record = $record->fresh();
        }

        $this->syncLinkedChartAccountBankProfile($record);
        $this->syncFinanceRelationshipLifecycle($record);
        $record = $record->fresh();
        if ($request->module_key === 'arf') {
            $this->sendFinanceAssetCustodianNotification($record, 'asset_assignment');
            $this->sendFinanceInventoryAlertNotification($record);
        }

        return response()->json([
            'message' => $record->workflow_status === 'Shared'
                ? 'Finance record created and emailed to the supplier.'
                : 'Finance record saved successfully.',
            'data' => $this->transformRecord($record),
        ], 201);
    }

    public function update(Request $request, FinanceRecord $financeRecord)
    {
        if (!$this->canEditRecord($financeRecord)) {
            abort(403, 'This record can no longer be edited.');
        }

        $this->validateModulePayload($request, $financeRecord);
        $oldCustodianId = data_get($financeRecord->data ?? [], 'custodian');
        $wasReleased = $this->financeRecordIsReleased($financeRecord);

        $existingAttachments = json_decode((string) $request->input('existing_attachments_json', '[]'), true);
        $existingAttachments = is_array($existingAttachments) ? $existingAttachments : [];
        $attachments = $this->persistAttachments($request, $existingAttachments);
        $dataInput = $request->input('data', []);
        if ($request->module_key === 'lr') {
            $dataInput = $this->financeAttachLineItemReceipts($request, is_array($dataInput) ? $dataInput : []);
        }
        $data = $this->normalizeModuleData($request->module_key, $dataInput);
        foreach (['approval_steps', 'approval_actions', 'approval_required_count', 'approval_completed_count', 'approval_remaining_count'] as $approvalField) {
            if (!array_key_exists($approvalField, $data) && array_key_exists($approvalField, (array) ($financeRecord->data ?? []))) {
                $data[$approvalField] = data_get($financeRecord->data, $approvalField);
            }
        }
        if (!array_key_exists('history', $data) && filled(data_get($financeRecord->data ?? [], 'history'))) {
            $data['history'] = array_values((array) data_get($financeRecord->data ?? [], 'history', []));
        }
        $data = $this->initializeFinanceApprovalState($data, $request->module_key);
        $supplierSendMode = $request->module_key === 'supplier' && data_get($data, 'completion_mode') === 'send_to_supplier';
        if ($supplierSendMode) {
            $data['approval_steps'] = array_values((array) $data['approval_steps']);
            $data['approval_actions'] = array_values((array) $data['approval_actions']);
            $data['approval_required_count'] = max(2, (int) ($data['approval_required_count'] ?? 0));
            $data['approval_completed_count'] = 0;
            $data['approval_remaining_count'] = count((array) $data['approval_steps']);
            $data['first_approver_user_id'] = data_get($data, 'approval_steps.0.user_id');
            $data['second_approver_user_id'] = data_get($data, 'approval_steps.1.user_id');
        }
        $recordNumber = trim((string) $request->input('record_number', ''));
        $recordTitle = trim((string) $request->input('record_title', ''));
        $recordDate = $request->input('record_date');

        if ($supplierSendMode) {
            $recordNumber = $recordNumber ?: ($financeRecord->record_number ?: $this->normalizeFinanceRecordNumber('supplier', ''));
            $recordTitle = $this->recordTitleLooksLikePlaceholder('supplier', $recordTitle) ? '' : $recordTitle;
            $recordDate = $recordDate ?: optional($financeRecord->record_date)->format('Y-m-d') ?: now()->toDateString();
        }

        $recordTitle = $this->financeAutoRecordTitle($request->module_key, $data, $recordTitle);
        if ($request->module_key === 'dv') {
            $data = $this->normalizeDvPayeeFields($data, $recordTitle);
            $data['line_items'] = $this->financeNormalizedDvLineItems(new FinanceRecord([
                'module_key' => 'dv',
                'record_number' => $recordNumber,
                'record_title' => $recordTitle,
                'data' => $data,
            ]), $data);
            $data = array_merge($data, $this->financeDvAccountingSummaryFromLineItems((array) $data['line_items']));
        }
        $recordNumber = $this->normalizeFinanceRecordNumber($request->module_key, $recordNumber);
        $recordAmount = in_array($request->module_key, ['dv', 'pda'], true)
            ? data_get($data, $request->module_key === 'pda' ? 'total_payroll_amount' : 'amount')
            : $request->amount;
        $actorName = Auth::user()?->name ?: 'System';
        $derivedStatus = $this->financeSystemStatusForRecord($financeRecord);
        $data['updated_by_name'] = $actorName;
        $data['requested_by_name'] = data_get($data, 'requested_by_name') ?: $this->financeRequestedByNameFromData($request->module_key, $data, $financeRecord);
        $oldHistorySnapshot = [
            'record_number' => $financeRecord->record_number,
            'record_title' => $financeRecord->record_title,
            'record_date' => optional($financeRecord->record_date)->format('Y-m-d'),
            'amount' => $financeRecord->amount,
            'status' => $derivedStatus,
            'workflow_status' => $financeRecord->workflow_status,
            'approval_status' => $financeRecord->approval_status,
            'review_note' => $financeRecord->review_note,
            'data' => $financeRecord->data ?? [],
            'attachments' => $financeRecord->attachments ?? [],
        ];
        $newHistorySnapshot = [
            'record_number' => $recordNumber,
            'record_title' => $recordTitle,
            'record_date' => $recordDate,
            'amount' => $recordAmount,
            'status' => $derivedStatus,
            'workflow_status' => $financeRecord->workflow_status,
            'approval_status' => ($financeRecord->workflow_status ?? 'Uploaded') === 'Reverted' ? 'Pending' : $financeRecord->approval_status,
            'review_note' => ($financeRecord->workflow_status ?? 'Uploaded') === 'Reverted' ? null : $financeRecord->review_note,
            'data' => $data,
            'attachments' => $attachments,
        ];
        $data = $this->appendFinanceHistoryEntry($data, 'Updated', $request->module_key, $oldHistorySnapshot, $newHistorySnapshot);

        $releaseIntent = filter_var(data_get($data, 'release_intent', false), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
        unset($data['release_intent']);

        if ($request->module_key === 'dv' && $wasReleased && ! $releaseIntent) {
            foreach ([
                'released_at',
                'released_by',
                'released_by_name',
                'release_recorded_at',
                'release_recorded_by',
                'release_recorded_by_name',
            ] as $fieldName) {
                if (!filled(data_get($data, $fieldName)) && filled(data_get($financeRecord->data ?? [], $fieldName))) {
                    $data[$fieldName] = data_get($financeRecord->data, $fieldName);
                }
            }
        }

        if ($request->module_key === 'dv' && $this->financeRecordIsApproved($financeRecord) && $releaseIntent) {
            if (blank(data_get($data, 'fund_source'))) {
                $data['fund_source'] = data_get($data, 'source_fund_source')
                    ?: data_get($data, 'source_project')
                    ?: data_get($data, 'department')
                    ?: data_get($financeRecord->data ?? [], 'source_fund_source')
                    ?: data_get($financeRecord->data ?? [], 'source_project')
                    ?: data_get($financeRecord->data ?? [], 'department')
                    ?: data_get($financeRecord->data ?? [], 'fund_source')
                    ?: 'N/A';
            }

            $referenceNumber = trim((string) data_get($data, 'reference_number', ''));

            if ($referenceNumber === '') {
                $data['reference_number'] = 'N/A';
            } else {
                $data['reference_number'] = $referenceNumber;
            }

            $releaseAssessment = $this->financeDvReleasePrerequisitesAssessment($financeRecord, $data, $attachments);

            if ($releaseAssessment && data_get($releaseAssessment, 'status') !== 'Ready') {
                throw ValidationException::withMessages([
                    'data.attachments' => data_get($releaseAssessment, 'warning') ?: 'The Disbursement Voucher cannot be marked as disbursed yet.',
                ]);
            }

            $data['released_at'] = data_get($data, 'released_at') ?: now()->toDateTimeString();
            $data['released_by'] = data_get($data, 'released_by') ?: Auth::id();
            $data['released_by_name'] = data_get($data, 'released_by_name') ?: $actorName;
            $data['release_recorded_at'] = data_get($data, 'release_recorded_at') ?: now()->toDateTimeString();
            $data['release_recorded_by'] = data_get($data, 'release_recorded_by') ?: Auth::id();
            $data['release_recorded_by_name'] = data_get($data, 'release_recorded_by_name') ?: $actorName;
        }

        $payload = [
            'module_key' => $request->module_key,
            'record_number' => $recordNumber,
            'record_title' => $recordTitle,
            'record_date' => $recordDate,
            'amount' => $recordAmount,
            'status' => $derivedStatus,
            'data' => $data,
            'attachments' => $attachments,
        ];

        if (($financeRecord->workflow_status ?? 'Uploaded') === 'Reverted') {
            $payload['approval_status'] = 'Pending';
            $payload['review_note'] = null;
        }

        $financeRecord->update($payload);

        if ($request->module_key === 'supplier' && data_get($data, 'completion_mode') === 'send_to_supplier' && blank($financeRecord->share_token)) {
            $this->sendSupplierCompletionEmail($financeRecord);
            $financeRecord = $financeRecord->fresh();
        }

        $this->syncLinkedChartAccountBankProfile($financeRecord);
        $this->syncFinanceRelationshipLifecycle($financeRecord);
        $financeRecord = $financeRecord->fresh();
        if ($request->module_key === 'arf') {
            $newCustodianId = data_get($financeRecord->data ?? [], 'custodian');
            $this->sendFinanceAssetCustodianNotification($financeRecord, ((string) $oldCustodianId !== (string) $newCustodianId) ? 'transferred' : 'asset_assignment');
            $this->sendFinanceInventoryAlertNotification($financeRecord);
        }
        if (
            $request->module_key === 'dv'
            && !$wasReleased
            && $this->financeRecordIsReleased($financeRecord)
        ) {
            $this->sendFinanceRecordWorkflowNotification($financeRecord, 'fund_release');
            $this->sendFinanceRecordWorkflowNotification($financeRecord, 'disbursement');
        }
        if (
            $request->module_key === 'dv'
            && (string) data_get($financeRecord->data ?? [], 'source_document_type') === 'ca'
            && !$wasReleased
            && $this->financeRecordIsReleased($financeRecord)
        ) {
            $linkedCa = $this->financeResolveModuleRecord('ca', data_get($financeRecord->data ?? [], 'source_document_id'));
            if ($linkedCa) {
                $this->sendFinanceRecordWorkflowNotification($linkedCa, 'liquidation_due');
            }
        }
        $this->sendFinanceRecordWorkflowNotification($financeRecord, 'updated');

        return response()->json([
            'message' => $financeRecord->workflow_status === 'Shared'
                ? 'Finance record updated and emailed to the supplier.'
                : 'Finance record updated successfully.',
            'data' => $this->transformRecord($financeRecord->fresh()),
        ]);
    }

    public function submit(FinanceRecord $financeRecord)
    {
        if ((int) $financeRecord->submitted_by !== (int) Auth::id()) {
            abort(403, 'Unauthorized');
        }

        if (!in_array($financeRecord->workflow_status ?? 'Uploaded', ['Uploaded', 'Reverted'], true)) {
            return response()->json([
                'message' => 'Only uploaded or reverted records can be submitted.'
            ], 422);
        }

        $data = $this->initializeFinanceApprovalState($financeRecord->data ?? [], $financeRecord->module_key);
        $data['approval_actions'] = [];
        $data['approval_completed_count'] = 0;
        $data['approval_remaining_count'] = $this->financeApprovalThreshold($financeRecord->module_key);
        $data['submitted_by_name'] = Auth::user()?->name ?: $this->financeSubmittedByName($financeRecord);
        $data['updated_by_name'] = Auth::user()?->name ?: $this->financeSubmittedByName($financeRecord);
        $derivedStatus = $this->financeSystemStatusForRecord($financeRecord);

        $data = $this->appendFinanceHistoryEntry($data, 'Submitted', $financeRecord->module_key, [
            'workflow_status' => $financeRecord->workflow_status,
            'approval_status' => $financeRecord->approval_status,
            'review_note' => $financeRecord->review_note,
            'status' => $derivedStatus,
            'data' => $financeRecord->data ?? [],
        ], [
            'workflow_status' => 'Submitted',
            'approval_status' => 'Pending',
            'review_note' => null,
            'status' => 'Pending Approval',
            'data' => $data,
        ]);

        $financeRecord->update([
            'workflow_status' => 'Submitted',
            'approval_status' => 'Pending',
            'submitted_at' => now(),
            'review_note' => null,
            'data' => $data,
            'status' => 'Pending Approval',
        ]);

        $this->syncFinanceRelationshipLifecycle($financeRecord);
        $financeRecord = $financeRecord->fresh();
        $this->sendFinanceRecordWorkflowNotification($financeRecord, 'submitted');

        return response()->json([
            'message' => 'Finance record submitted for review successfully.',
            'data' => $this->transformRecord($financeRecord),
        ]);
    }

    public function approve(Request $request, FinanceRecord $financeRecord)
    {
        if (!$this->canApproveSubmittedFinanceRecord($financeRecord)) {
            abort(403, 'Unauthorized');
        }

        $oldData = $financeRecord->data ?? [];
        $data = $this->initializeFinanceApprovalState($oldData, $financeRecord->module_key);

        if ($financeRecord->module_key === 'dv') {
            $sourceRecord = $this->financeDvSourceRecord($financeRecord, $data);
            $normalizedLineItems = $sourceRecord
                ? $this->financeNormalizeDvLineItems(
                    $this->financeDvLineItemsFromSource($sourceRecord, $data),
                    $financeRecord,
                    $data
                )
                : $this->financeNormalizedDvLineItems($financeRecord, $data);

            if (! empty($normalizedLineItems)) {
                $data['line_items'] = $normalizedLineItems;
                $data = array_merge($data, $this->financeDvAccountingSummaryFromLineItems($normalizedLineItems));
                $financeRecord->setAttribute('data', $data);
            }

            $fundAvailability = $this->financeDvFundAvailabilityAssessment($financeRecord);

            if ($fundAvailability) {
                $data = array_merge($data, [
                    'fund_availability_status' => data_get($fundAvailability, 'status', 'Sufficient Funds'),
                    'fund_availability_warning' => data_get($fundAvailability, 'warning'),
                    'fund_availability_requested_amount' => data_get($fundAvailability, 'requested_amount'),
                    'fund_availability_available_balance' => data_get($fundAvailability, 'available_balance'),
                    'fund_availability_checked_at' => now()->format('Y-m-d H:i:s'),
                    'fund_availability_checked_by' => Auth::id(),
                    'fund_availability_checked_by_name' => Auth::user()?->name ?: 'Finance Approver',
                    'fund_availability_policy_allows_approval' => data_get($fundAvailability, 'policy_allows_approval', false),
                ]);

                $data = $this->appendFinanceHistoryEntry($data, 'Fund Availability Checked', $financeRecord->module_key, [
                    'available_balance' => data_get($fundAvailability, 'available_balance'),
                    'requested_amount' => data_get($fundAvailability, 'requested_amount'),
                    'status' => data_get($fundAvailability, 'status'),
                    'warning' => data_get($fundAvailability, 'warning'),
                ], [
                    'available_balance' => data_get($fundAvailability, 'available_balance'),
                    'requested_amount' => data_get($fundAvailability, 'requested_amount'),
                    'status' => data_get($fundAvailability, 'status'),
                    'warning' => data_get($fundAvailability, 'warning'),
                ]);

                $financeRecord->update([
                    'data' => $data,
                ]);
                $financeRecord = $financeRecord->fresh();

                if (data_get($fundAvailability, 'status') === 'Insufficient Funds') {
                    $this->sendFinanceRecordWorkflowNotification($financeRecord, 'insufficient_funds', data_get($fundAvailability, 'warning'));

                    if (!data_get($fundAvailability, 'policy_allows_approval', false)) {
                        throw ValidationException::withMessages([
                            'data.amount' => data_get($fundAvailability, 'warning') ?: 'The requested amount exceeds the available balance.',
                        ]);
                    }
                }
            }

            $accountingAssessment = $this->financeDvAccountingAssessment($financeRecord);

            if ($accountingAssessment) {
                $data = array_merge($data, [
                    'accounting_balance_status' => data_get($accountingAssessment, 'status', 'Balanced'),
                    'accounting_balance_warning' => data_get($accountingAssessment, 'warning'),
                    'total_debit_amount' => data_get($accountingAssessment, 'total_debit', '0.00'),
                    'total_credit_amount' => data_get($accountingAssessment, 'total_credit', '0.00'),
                    'accounting_balance_difference' => data_get($accountingAssessment, 'difference', '0.00'),
                    'accounting_balance_checked_at' => now()->format('Y-m-d H:i:s'),
                    'accounting_balance_checked_by' => Auth::id(),
                    'accounting_balance_checked_by_name' => Auth::user()?->name ?: 'Finance Approver',
                ]);

                $data = $this->appendFinanceHistoryEntry($data, 'Accounting Entry Checked', $financeRecord->module_key, [
                    'total_debit' => data_get($accountingAssessment, 'total_debit'),
                    'total_credit' => data_get($accountingAssessment, 'total_credit'),
                    'difference' => data_get($accountingAssessment, 'difference'),
                    'status' => data_get($accountingAssessment, 'status'),
                    'warning' => data_get($accountingAssessment, 'warning'),
                ], [
                    'total_debit' => data_get($accountingAssessment, 'total_debit'),
                    'total_credit' => data_get($accountingAssessment, 'total_credit'),
                    'difference' => data_get($accountingAssessment, 'difference'),
                    'status' => data_get($accountingAssessment, 'status'),
                    'warning' => data_get($accountingAssessment, 'warning'),
                ]);

                $financeRecord->update([
                    'data' => $data,
                ]);
                $financeRecord = $financeRecord->fresh();

                if (data_get($accountingAssessment, 'status') === 'Unbalanced') {
                    $this->sendFinanceRecordWorkflowNotification($financeRecord, 'accounting_imbalance', data_get($accountingAssessment, 'warning'));
                    throw ValidationException::withMessages([
                        'data.line_items' => data_get($accountingAssessment, 'warning') ?: 'Total Debit must equal Total Credit before approval.',
                    ]);
                }
            }
        }

        $actions = array_values((array) data_get($data, 'approval_actions', []));
        $approvalStep = $this->financeApprovalStepForUser($financeRecord);
        $actions[] = [
            'approved_by' => Auth::id(),
            'approved_by_name' => Auth::user()?->name ?: 'Finance Approver',
            'approver_role' => $approvalStep['role'] ?? $this->financeUserApprovalRole(),
            'approved_at' => now()->format('Y-m-d H:i:s'),
        ];

        $uniqueApproverCount = collect($actions)
            ->pluck('approved_by')
            ->filter()
            ->unique()
            ->count();
        $requiredApprovals = $this->financeApprovalThreshold($financeRecord->module_key);
        $isFullyApproved = $uniqueApproverCount >= $requiredApprovals;
        $nextWorkflowStatus = $isFullyApproved ? 'Accepted' : 'Submitted';
        $nextApprovalStatus = $isFullyApproved ? 'Approved' : 'Partially Approved';

        $data['approval_actions'] = $actions;
        $data['approval_required_count'] = $requiredApprovals;
        $data['approval_completed_count'] = min($uniqueApproverCount, $requiredApprovals);
        $data['approval_remaining_count'] = max($requiredApprovals - $uniqueApproverCount, 0);
        $data['approved_by_name'] = Auth::user()?->name ?: 'Finance Approver';
        $data['updated_by_name'] = Auth::user()?->name ?: 'Finance Approver';
        $derivedStatus = $this->financeSystemStatusForRecord($financeRecord);

        $data = $this->appendFinanceHistoryEntry($data, $isFullyApproved ? 'Approved' : 'Partially Approved', $financeRecord->module_key, [
            'workflow_status' => $financeRecord->workflow_status,
            'approval_status' => $financeRecord->approval_status,
            'approved_by' => $financeRecord->approved_by,
            'approved_at' => optional($financeRecord->approved_at)->format('Y-m-d H:i:s'),
            'status' => $derivedStatus,
            'data' => $oldData,
        ], [
            'workflow_status' => $nextWorkflowStatus,
            'approval_status' => $nextApprovalStatus,
            'approved_by' => Auth::id(),
            'approved_at' => now()->format('Y-m-d H:i:s'),
            'status' => $isFullyApproved ? 'Approved' : 'Partially Approved',
            'data' => $data,
        ]);

        $financeRecord->update([
            'workflow_status' => $nextWorkflowStatus,
            'approval_status' => $nextApprovalStatus,
            'approved_by' => Auth::id(),
            'approved_at' => $isFullyApproved ? now() : null,
            'review_note' => null,
            'data' => $data,
            'status' => $isFullyApproved ? 'Approved' : 'Partially Approved',
        ]);
        $this->syncFinanceRelationshipLifecycle($financeRecord);
        $financeRecord = $financeRecord->fresh();
        $this->sendFinanceRecordWorkflowNotification($financeRecord, $isFullyApproved ? 'approved' : 'partially_approved');
        if ($isFullyApproved && $financeRecord->module_key === 'lr') {
            $this->sendFinanceRecordWorkflowNotification($financeRecord, 'liquidation_approval');
        }

        return $this->financeActionResponse(
            $request,
            $isFullyApproved
                ? 'Finance record approved successfully.'
                : 'Approval recorded. One more approver is required before final approval.',
            $financeRecord
        );
    }

    public function hold(Request $request, FinanceRecord $financeRecord)
    {
        if (!$this->canHoldSubmittedFinanceRecord($financeRecord)) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'review_note' => 'required|string|max:1000',
        ]);

        $data = $financeRecord->data ?? [];
        $data['held_by'] = Auth::id();
        $data['held_by_name'] = Auth::user()?->name ?: 'System';
        $data['held_at'] = now()->format('Y-m-d H:i:s');
        $derivedStatus = $this->financeSystemStatusForRecord($financeRecord);

        $data = $this->appendFinanceHistoryEntry($data, 'Placed On Hold', $financeRecord->module_key, [
            'workflow_status' => $financeRecord->workflow_status,
            'approval_status' => $financeRecord->approval_status,
            'review_note' => $financeRecord->review_note,
            'status' => $derivedStatus,
        ], [
            'workflow_status' => 'On Hold',
            'approval_status' => 'On Hold',
            'review_note' => $request->review_note,
            'status' => 'On Hold',
        ], $request->review_note);

        $financeRecord->update([
            'workflow_status' => 'On Hold',
            'approval_status' => 'On Hold',
            'review_note' => $request->review_note,
            'data' => $data,
            'status' => 'On Hold',
        ]);
        $this->syncFinanceRelationshipLifecycle($financeRecord);
        $financeRecord = $financeRecord->fresh();
        $this->sendFinanceRecordWorkflowNotification($financeRecord, 'held', $request->review_note);

        return $this->financeActionResponse($request, 'Finance record placed on hold.', $financeRecord);
    }

    public function revert(Request $request, FinanceRecord $financeRecord)
    {
        if (!$this->canRevertSubmittedFinanceRecord($financeRecord)) {
            abort(403, 'Unauthorized');
        }
        $request->validate([
            'reason' => 'required_without:review_note|string|max:1000',
            'review_note' => 'nullable|string|max:1000',
        ]);

        $reason = trim((string) $request->input('reason', $request->input('review_note', '')));
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'A revert reason is required.',
            ]);
        }

        $data = $financeRecord->data ?? [];
        $data['reverted_by'] = Auth::id();
        $data['reverted_by_name'] = Auth::user()?->name ?: 'System';
        $data['reverted_at'] = now()->format('Y-m-d H:i:s');
        $derivedStatus = $this->financeSystemStatusForRecord($financeRecord);

        $data = $this->appendFinanceHistoryEntry($data, 'Reverted', $financeRecord->module_key, [
            'workflow_status' => $financeRecord->workflow_status,
            'approval_status' => $financeRecord->approval_status,
            'review_note' => $financeRecord->review_note,
            'review_reason' => $reason,
            'status' => $derivedStatus,
        ], [
            'workflow_status' => 'Reverted',
            'approval_status' => 'Needs Revision',
            'review_note' => $reason,
            'review_reason' => $reason,
            'status' => 'Reverted',
        ], $reason);
        $data['approval_actions'] = [];
        $data['approval_completed_count'] = 0;
        $data['approval_remaining_count'] = $this->financeApprovalThreshold($financeRecord->module_key);

        $financeRecord->update([
            'workflow_status' => 'Reverted',
            'approval_status' => 'Needs Revision',
            'review_note' => $reason,
            'data' => $data,
            'status' => 'Reverted',
            'supplier_completed_at' => $financeRecord->module_key === 'supplier' ? null : $financeRecord->supplier_completed_at,
        ]);
        $this->syncFinanceRelationshipLifecycle($financeRecord);
        $financeRecord = $financeRecord->fresh();
        if ($financeRecord->module_key === 'supplier') {
            $this->sendSupplierRevertEmail($financeRecord, $reason, Auth::user()?->name ?: 'Finance Admin');
        }
        $this->sendFinanceRecordWorkflowNotification($financeRecord, 'reverted', $reason);

        return $this->financeActionResponse($request, 'Finance record reverted for revision.', $financeRecord);
    }

    public function acknowledgeAsset(Request $request, FinanceRecord $financeRecord)
    {
        if ($financeRecord->module_key !== 'arf') {
            abort(404);
        }

        if (! $this->financeAssetCanAcknowledge($financeRecord)) {
            abort(403, 'You are not the assigned custodian for this asset.');
        }

        $data = $financeRecord->data ?? [];
        $oldValues = [
            'custodian' => data_get($data, 'custodian'),
            'custodian_name' => data_get($data, 'custodian_name'),
            'asset_status' => data_get($data, 'asset_status'),
            'custodian_acknowledged_at' => data_get($data, 'custodian_acknowledged_at'),
        ];

        data_set($data, 'custodian_acknowledged_at', now()->toDateTimeString());
        data_set($data, 'custodian_acknowledged_by', Auth::id());
        data_set($data, 'custodian_acknowledged_by_name', Auth::user()?->name ?: 'System');
        data_set($data, 'received_by_name', Auth::user()?->name ?: 'System');
        data_set($data, 'asset_status', 'Acknowledged');
        data_set($data, 'asset_last_event', 'Asset Acknowledged');

        $data = $this->appendFinanceHistoryEntry($data, 'Asset Acknowledged', 'arf', $oldValues, [
            'custodian' => data_get($data, 'custodian'),
            'custodian_name' => data_get($data, 'custodian_name'),
            'asset_status' => data_get($data, 'asset_status'),
            'custodian_acknowledged_at' => data_get($data, 'custodian_acknowledged_at'),
        ]);

        $financeRecord->update(['data' => $data]);
        $financeRecord = $financeRecord->fresh();

        return $this->financeActionResponse($request, 'Asset receipt acknowledged successfully.', $financeRecord);
    }

    public function transferAsset(Request $request, FinanceRecord $financeRecord)
    {
        if ($financeRecord->module_key !== 'arf') {
            abort(404);
        }

        if (! $this->financeAssetCanManage($financeRecord)) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'new_custodian_id' => ['required', Rule::exists('employees', 'id')],
            'transfer_reason' => 'required|string|max:1000',
        ]);

        $newCustodian = Employee::query()
            ->when(Schema::hasTable('departments'), fn ($query) => $query->with('department'))
            ->find($validated['new_custodian_id']);

        if (! $newCustodian) {
            abort(422, 'The selected custodian could not be found.');
        }

        $newCustodianOption = $this->employeeRequesterOption($newCustodian);
        $data = $financeRecord->data ?? [];
        $oldValues = [
            'custodian' => data_get($data, 'custodian'),
            'custodian_name' => data_get($data, 'custodian_name'),
            'asset_status' => data_get($data, 'asset_status'),
        ];

        data_set($data, 'custodian', $newCustodian->id);
        data_set($data, 'custodian_name', $newCustodianOption['full_name']);
        data_set($data, 'custodian_employee_code', $newCustodianOption['employee_code']);
        data_set($data, 'custodian_email', $newCustodianOption['email']);
        data_set($data, 'received_by_name', $newCustodianOption['full_name']);
        data_set($data, 'custodian_acknowledged_at', null);
        data_set($data, 'custodian_acknowledged_by', null);
        data_set($data, 'custodian_acknowledged_by_name', null);
        data_set($data, 'asset_status', 'Transferred');
        data_set($data, 'asset_last_event', 'Asset Transferred');
        data_set($data, 'asset_last_event_note', trim((string) $validated['transfer_reason']));

        $data = $this->appendFinanceHistoryEntry($data, 'Asset Transferred', 'arf', $oldValues, [
            'custodian' => data_get($data, 'custodian'),
            'custodian_name' => data_get($data, 'custodian_name'),
            'asset_status' => data_get($data, 'asset_status'),
        ], trim((string) $validated['transfer_reason']));

        $financeRecord->update(['data' => $data]);
        $financeRecord = $financeRecord->fresh();

        $this->financeSendAssetCustodianNotification($financeRecord, 'transferred', trim((string) $validated['transfer_reason']));

        return $this->financeActionResponse($request, 'Asset transferred successfully.', $financeRecord);
    }

    public function recordAssetEvent(Request $request, FinanceRecord $financeRecord)
    {
        if ($financeRecord->module_key !== 'arf') {
            abort(404);
        }

        if (! $this->financeAssetCanManage($financeRecord)) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'event_type' => ['required', Rule::in(['loss', 'damage', 'return', 'disposal', 'stock_in', 'stock_out', 'stock_transfer', 'stock_return', 'stock_adjustment'])],
            'event_reason' => 'required|string|max:1000',
            'movement_quantity' => 'nullable|numeric|min:0',
            'movement_direction' => 'nullable|in:increase,decrease,set',
            'target_location' => 'nullable|string|max:255',
            'target_department' => 'nullable|string|max:255',
            'target_custodian' => ['nullable', Rule::exists('employees', 'id')],
        ]);

        $data = $financeRecord->data ?? [];
        $isInventoryMovement = $this->financeAssetEventAffectsInventory($validated['event_type']);
        $currentQuantity = (float) data_get($data, 'current_quantity', 0);
        $movementQuantity = max((float) ($validated['movement_quantity'] ?? 0), 0);
        $newQuantity = $currentQuantity;

        if ($isInventoryMovement) {
            $direction = $validated['movement_direction'] ?? match ($validated['event_type']) {
                'stock_out' => 'decrease',
                'stock_adjustment' => 'set',
                default => 'increase',
            };

            $newQuantity = $validated['event_type'] === 'stock_transfer'
                ? $currentQuantity
                : match ($direction) {
                    'decrease' => max($currentQuantity - $movementQuantity, 0),
                    'set' => $movementQuantity,
                    default => $currentQuantity + $movementQuantity,
                };
        }

        $oldValues = [
            'asset_status' => data_get($data, 'asset_status'),
            'asset_last_event' => data_get($data, 'asset_last_event'),
            'current_quantity' => data_get($data, 'current_quantity'),
            'available_quantity' => data_get($data, 'available_quantity'),
            'unit_cost' => data_get($data, 'unit_cost'),
            'average_cost' => data_get($data, 'average_cost'),
            'last_purchase_cost' => data_get($data, 'last_purchase_cost'),
            'total_cost' => data_get($data, 'total_cost'),
            'location' => data_get($data, 'location'),
            'department' => data_get($data, 'department'),
            'custodian' => data_get($data, 'custodian'),
            'movement_history_note' => data_get($data, 'movement_history_note'),
        ];

        data_set($data, 'asset_status', $this->financeAssetStatusForEvent($validated['event_type']));
        data_set($data, 'asset_last_event', $this->financeAssetEventLabel($validated['event_type']));
        data_set($data, 'asset_last_event_note', trim((string) $validated['event_reason']));
        data_set($data, 'updated_by_name', Auth::user()?->name ?: 'System');

        if ($isInventoryMovement) {
            data_set($data, 'current_quantity', number_format($newQuantity, 2, '.', ''));
            data_set($data, 'available_quantity', number_format(max($newQuantity - (float) data_get($data, 'reserved_quantity', 0), 0), 2, '.', ''));
            data_set($data, 'total_cost', number_format($newQuantity * (float) data_get($data, 'unit_cost', 0), 2, '.', ''));
            data_set($data, 'movement_history_note', trim((string) $validated['event_reason']));

            if ($validated['event_type'] === 'stock_transfer') {
                if (filled($validated['target_location'] ?? null)) {
                    data_set($data, 'location', trim((string) $validated['target_location']));
                }
                if (filled($validated['target_department'] ?? null)) {
                    data_set($data, 'department', trim((string) $validated['target_department']));
                }
                if (filled($validated['target_custodian'] ?? null)) {
                    data_set($data, 'custodian', (int) $validated['target_custodian']);
                    $custodianEmployee = Employee::query()
                        ->when(Schema::hasTable('departments'), fn ($query) => $query->with('department'))
                        ->find((int) $validated['target_custodian']);
                    if ($custodianEmployee) {
                        data_set($data, 'custodian_name', $this->employeeRequesterOption($custodianEmployee)['label'] ?? $custodianEmployee->full_name);
                        data_set($data, 'custodian_employee_code', $custodianEmployee->employee_code);
                        data_set($data, 'custodian_email', $custodianEmployee->login_email ?: $custodianEmployee->email);
                    }
                }
            }
        }

        if (Str::lower((string) data_get($data, 'item_classification')) === 'consumable inventory') {
            $data = $this->financeSyncConsumableInventoryCosts($data);
        }

        $data = $this->financeAppendAssetEventHistory($financeRecord, $validated['event_type'], $oldValues, [
            'asset_status' => data_get($data, 'asset_status'),
            'asset_last_event' => data_get($data, 'asset_last_event'),
            'asset_last_event_note' => data_get($data, 'asset_last_event_note'),
            'current_quantity' => data_get($data, 'current_quantity'),
            'available_quantity' => data_get($data, 'available_quantity'),
            'unit_cost' => data_get($data, 'unit_cost'),
            'average_cost' => data_get($data, 'average_cost'),
            'last_purchase_cost' => data_get($data, 'last_purchase_cost'),
            'total_cost' => data_get($data, 'total_cost'),
            'location' => data_get($data, 'location'),
            'department' => data_get($data, 'department'),
            'custodian' => data_get($data, 'custodian'),
            'movement_history_note' => data_get($data, 'movement_history_note'),
            'movement_quantity' => $isInventoryMovement ? number_format($movementQuantity, 2, '.', '') : null,
            'movement_direction' => $isInventoryMovement ? ($validated['movement_direction'] ?? ($validated['event_type'] === 'stock_transfer' ? 'transfer' : null)) : null,
            'target_location' => $validated['target_location'] ?? null,
            'target_department' => $validated['target_department'] ?? null,
            'target_custodian' => $validated['target_custodian'] ?? null,
        ], trim((string) $validated['event_reason']), $data);

        $financeRecord->update(['data' => $data]);
        $financeRecord = $financeRecord->fresh();

        $this->sendFinanceInventoryAlertNotification($financeRecord);

        return $this->financeActionResponse($request, $this->financeAssetEventLabel($validated['event_type']) . ' recorded successfully.', $financeRecord);
    }

    public function archive(Request $request, FinanceRecord $financeRecord)
    {
        if (!$this->canArchiveFinanceRecord($financeRecord)) {
            abort(403, 'Unauthorized');
        }

        $data = $this->appendFinanceHistoryEntry($financeRecord->data ?? [], 'Archived', $financeRecord->module_key, [
            'workflow_status' => $financeRecord->workflow_status,
            'approval_status' => $financeRecord->approval_status,
        ], [
            'workflow_status' => 'Archived',
            'approval_status' => 'Archived',
            'updated_by_name' => Auth::user()?->name ?: 'System',
        ]);

        $financeRecord->update([
            'workflow_status' => 'Archived',
            'approval_status' => 'Archived',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'data' => $data,
        ]);

        $financeRecord = $financeRecord->fresh();
        $this->sendFinanceRecordWorkflowNotification($financeRecord, 'archived');

        return $this->financeActionResponse($request, 'Finance record archived successfully.', $financeRecord);
    }

    public function unarchive(Request $request, FinanceRecord $financeRecord)
    {
        if (!$this->canUnarchiveFinanceRecord($financeRecord)) {
            abort(403, 'Unauthorized');
        }

        $data = $this->appendFinanceHistoryEntry($financeRecord->data ?? [], 'Unarchived', $financeRecord->module_key, [
            'workflow_status' => $financeRecord->workflow_status,
            'approval_status' => $financeRecord->approval_status,
            'review_note' => $financeRecord->review_note,
        ], [
            'workflow_status' => 'Accepted',
            'approval_status' => 'Approved',
            'review_note' => null,
            'updated_by_name' => Auth::user()?->name ?: 'System',
        ]);

        $financeRecord->update([
            'workflow_status' => 'Accepted',
            'approval_status' => 'Approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'review_note' => null,
            'data' => $data,
        ]);

        $financeRecord = $financeRecord->fresh();
        $this->sendFinanceRecordWorkflowNotification($financeRecord, 'unarchived');

        return $this->financeActionResponse($request, 'Finance record unarchived successfully.', $financeRecord);
    }

    public function requestDelete(Request $request, FinanceRecord $financeRecord)
    {
        if (! $this->canRequestDeleteRecord($financeRecord)) {
            abort(403, 'Unauthorized');
        }

        $data = $financeRecord->data ?? [];
        $data['delete_requested_by'] = Auth::id();
        $data['delete_requested_by_name'] = Auth::user()?->name ?: 'Unknown User';
        $data['delete_requested_at'] = now()->toDateTimeString();
        $data['delete_request_note'] = trim((string) $request->input('review_note', ''));
        $data = $this->appendFinanceHistoryEntry($data, 'Delete Requested', $financeRecord->module_key, [
            'workflow_status' => $financeRecord->workflow_status,
            'approval_status' => $financeRecord->approval_status,
            'review_note' => $financeRecord->review_note,
        ], [
            'workflow_status' => 'Delete Requested',
            'approval_status' => 'Deletion Pending',
            'review_note' => $data['delete_request_note'] ?: 'Deletion requested for admin approval.',
            'requested_by_name' => Auth::user()?->name ?: 'System',
        ], $data['delete_request_note'] ?: null);

        $financeRecord->update([
            'workflow_status' => 'Delete Requested',
            'approval_status' => 'Deletion Pending',
            'review_note' => $data['delete_request_note'] ?: 'Deletion requested for admin approval.',
            'data' => $data,
        ]);

        $financeRecord = $financeRecord->fresh();
        $this->sendFinanceRecordWorkflowNotification($financeRecord, 'delete_requested', $data['delete_request_note'] ?: null);

        return $this->financeActionResponse($request, 'Finance delete request submitted for admin approval.', $financeRecord);
    }

    public function approveDelete(Request $request, FinanceRecord $financeRecord)
    {
        if (! $this->canApproveDeleteRequest($financeRecord)) {
            abort(403, 'Unauthorized');
        }

        $data = $financeRecord->data ?? [];
        $data['delete_approved_by'] = Auth::id();
        $data['delete_approved_by_name'] = Auth::user()?->name ?: 'Admin User';
        $data['delete_approved_at'] = now()->toDateTimeString();
        $data = $this->appendFinanceHistoryEntry($data, 'Delete Approved', $financeRecord->module_key, [
            'workflow_status' => $financeRecord->workflow_status,
            'approval_status' => $financeRecord->approval_status,
            'status' => $financeRecord->status,
        ], [
            'workflow_status' => 'Deleted',
            'approval_status' => 'Deleted',
            'status' => 'Deleted',
            'approved_by_name' => Auth::user()?->name ?: 'Admin User',
        ]);

        $financeRecord->update([
            'workflow_status' => 'Deleted',
            'approval_status' => 'Deleted',
            'status' => 'Deleted',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'data' => $data,
        ]);

        $financeRecord = $financeRecord->fresh();
        $this->sendFinanceRecordWorkflowNotification($financeRecord, 'delete_approved');

        return $this->financeActionResponse($request, 'Finance record deletion approved.', $financeRecord);
    }

    public function rejectDelete(Request $request, FinanceRecord $financeRecord)
    {
        if (! $this->canApproveDeleteRequest($financeRecord)) {
            abort(403, 'Unauthorized');
        }

        $data = $financeRecord->data ?? [];
        $data['delete_rejected_by'] = Auth::id();
        $data['delete_rejected_by_name'] = Auth::user()?->name ?: 'Admin User';
        $data['delete_rejected_at'] = now()->toDateTimeString();
        $reviewNote = $request->input('review_note') ?: 'Deletion request rejected.';
        $data = $this->appendFinanceHistoryEntry($data, 'Delete Rejected', $financeRecord->module_key, [
            'workflow_status' => $financeRecord->workflow_status,
            'approval_status' => $financeRecord->approval_status,
            'review_note' => $financeRecord->review_note,
        ], [
            'workflow_status' => 'Reverted',
            'approval_status' => 'Needs Revision',
            'review_note' => $reviewNote,
            'reverted_by_name' => Auth::user()?->name ?: 'Admin User',
        ], $reviewNote);

        $financeRecord->update([
            'workflow_status' => 'Reverted',
            'approval_status' => 'Needs Revision',
            'review_note' => $reviewNote,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'data' => $data,
        ]);

        $financeRecord = $financeRecord->fresh();
        $this->sendFinanceRecordWorkflowNotification($financeRecord, 'delete_rejected', $reviewNote);

        return $this->financeActionResponse($request, 'Finance record deletion request rejected.', $financeRecord);
    }

    public function requestCorrection(Request $request, FinanceRecord $financeRecord)
    {
        if (! $this->canRequestCorrectionRecord($financeRecord)) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'request_type' => 'required|in:Correction,Reversal,Administrative Override',
            'reason' => 'required|string|max:1000',
            'proposed_changes' => 'required|array|min:1',
            'proposed_changes.record_number' => 'nullable|string|max:255|regex:/^[A-Z0-9]{2,10}-\d{5}$/',
            'proposed_changes.record_title' => 'nullable|string|max:255',
            'proposed_changes.record_date' => 'nullable|date',
            'proposed_changes.amount' => 'nullable|numeric',
            'proposed_changes.status' => 'nullable|string|max:255',
            'proposed_changes.data' => 'nullable|array',
        ]);

        $currentData = $financeRecord->data ?? [];
        $proposedChanges = (array) data_get($validated, 'proposed_changes', []);
        $proposedData = array_merge((array) $currentData, (array) data_get($proposedChanges, 'data', []));
        $requestTypeLabel = Str::headline((string) $validated['request_type']);

        $currentSnapshot = [
            'record_number' => $financeRecord->record_number,
            'record_title' => $financeRecord->record_title,
            'record_date' => optional($financeRecord->record_date)->format('Y-m-d'),
            'amount' => $financeRecord->amount,
            'status' => $financeRecord->status,
            'workflow_status' => $financeRecord->workflow_status,
            'approval_status' => $financeRecord->approval_status,
            'review_note' => $financeRecord->review_note,
            'data' => $currentData,
            'attachments' => $financeRecord->attachments ?? [],
        ];

        $correctedSnapshot = [
            'record_number' => data_get($proposedChanges, 'record_number', $financeRecord->record_number),
            'record_title' => data_get($proposedChanges, 'record_title', $financeRecord->record_title),
            'record_date' => data_get($proposedChanges, 'record_date', optional($financeRecord->record_date)->format('Y-m-d')),
            'amount' => data_get($proposedChanges, 'amount', $financeRecord->amount),
            'status' => data_get($proposedChanges, 'status', $financeRecord->status),
            'workflow_status' => $financeRecord->workflow_status,
            'approval_status' => $financeRecord->approval_status,
            'review_note' => $validated['reason'],
            'data' => array_merge($proposedData, [
                'correction_request_type' => $validated['request_type'],
                'correction_request_reason' => $validated['reason'],
            ]),
            'attachments' => $financeRecord->attachments ?? [],
        ];

        $correctionRequests = array_values((array) data_get($currentData, 'correction_requests', []));
        $correctionRequests[] = [
            'id' => (string) Str::uuid(),
            'type' => $validated['request_type'],
            'status' => 'Pending',
            'reason' => $validated['reason'],
            'requested_by' => Auth::id(),
            'requested_by_name' => Auth::user()?->name ?: 'System',
            'requested_at' => now()->format('Y-m-d H:i:s'),
            'original' => $currentSnapshot,
            'corrected' => $correctedSnapshot,
            'original_data' => data_get($currentSnapshot, 'data', []),
            'corrected_data' => data_get($correctedSnapshot, 'data', []),
            'original_attachments' => data_get($currentSnapshot, 'attachments', []),
            'corrected_attachments' => data_get($correctedSnapshot, 'attachments', []),
        ];

        $currentData['correction_requests'] = $correctionRequests;
        $currentData['correction_request_status'] = 'Pending';
        $currentData['correction_request_type'] = $validated['request_type'];
        $currentData['correction_requested_by'] = Auth::id();
        $currentData['correction_requested_by_name'] = Auth::user()?->name ?: 'System';
        $currentData['correction_requested_at'] = now()->format('Y-m-d H:i:s');
        $currentData['correction_request_reason'] = $validated['reason'];

        $currentData = $this->appendFinanceHistoryEntry($currentData, $requestTypeLabel . ' Requested', $financeRecord->module_key, $currentSnapshot, $correctedSnapshot, $validated['reason']);

        $financeRecord->update([
            'workflow_status' => 'Correction Requested',
            'approval_status' => 'Correction Pending',
            'review_note' => $validated['reason'],
            'data' => $currentData,
        ]);

        $financeRecord = $financeRecord->fresh();

        return $this->financeActionResponse($request, 'Correction request submitted successfully.', $financeRecord);
    }

    public function approveCorrection(Request $request, FinanceRecord $financeRecord)
    {
        if (! $this->canApproveCorrectionRequest($financeRecord)) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'review_note' => 'nullable|string|max:1000',
        ]);

        $data = $financeRecord->data ?? [];
        $correctionRequests = array_values((array) data_get($data, 'correction_requests', []));
        $latestIndex = collect($correctionRequests)
            ->keys()
            ->reverse()
            ->first(fn ($index) => is_array($correctionRequests[$index] ?? null) && in_array(data_get($correctionRequests[$index], 'status'), ['Pending', 'Submitted'], true));

        if ($latestIndex === null) {
            abort(422, 'No pending correction request exists for this record.');
        }

        $requestEntry = $correctionRequests[$latestIndex];
        $requestEntry['status'] = 'Approved';
        $requestEntry['approved_by'] = Auth::id();
        $requestEntry['approved_by_name'] = Auth::user()?->name ?: 'Admin User';
        $requestEntry['approved_at'] = now()->format('Y-m-d H:i:s');
        $requestEntry['approval_note'] = $request->input('review_note');
        $correctionRequests[$latestIndex] = $requestEntry;
        $requestTypeLabel = Str::headline((string) data_get($requestEntry, 'type', 'Correction'));

        $data['correction_requests'] = array_values($correctionRequests);
        $data['correction_request_status'] = 'Approved';
        $data['correction_approved_by'] = Auth::id();
        $data['correction_approved_by_name'] = Auth::user()?->name ?: 'Admin User';
        $data['correction_approved_at'] = now()->format('Y-m-d H:i:s');
        $data['correction_request_approval_note'] = $request->input('review_note');

        $originalSnapshot = data_get($requestEntry, 'original', []);
        $correctedSnapshot = data_get($requestEntry, 'corrected', data_get($requestEntry, 'proposed', []));
        $appliedSnapshot = [
            'record_number' => data_get($correctedSnapshot, 'record_number', $financeRecord->record_number),
            'record_title' => data_get($correctedSnapshot, 'record_title', $financeRecord->record_title),
            'record_date' => data_get($correctedSnapshot, 'record_date', optional($financeRecord->record_date)->format('Y-m-d')),
            'amount' => data_get($correctedSnapshot, 'amount', $financeRecord->amount),
            'status' => data_get($correctedSnapshot, 'status', $financeRecord->status),
            'workflow_status' => data_get($correctedSnapshot, 'workflow_status', $financeRecord->workflow_status),
            'approval_status' => data_get($correctedSnapshot, 'approval_status', $financeRecord->approval_status),
            'review_note' => $request->input('review_note'),
            'data' => data_get($correctedSnapshot, 'data', $financeRecord->data ?? []),
            'attachments' => data_get($correctedSnapshot, 'attachments', $financeRecord->attachments ?? []),
        ];

        $data = $this->appendFinanceHistoryEntry($data, $requestTypeLabel . ' Approved', $financeRecord->module_key, $originalSnapshot, $appliedSnapshot, $request->input('review_note'));

        $financeRecord->update([
            'record_number' => data_get($appliedSnapshot, 'record_number', $financeRecord->record_number),
            'record_title' => data_get($appliedSnapshot, 'record_title', $financeRecord->record_title),
            'record_date' => data_get($appliedSnapshot, 'record_date', optional($financeRecord->record_date)->format('Y-m-d')),
            'amount' => data_get($appliedSnapshot, 'amount', $financeRecord->amount),
            'status' => data_get($appliedSnapshot, 'status', $financeRecord->status),
            'workflow_status' => data_get($appliedSnapshot, 'workflow_status', $financeRecord->workflow_status),
            'approval_status' => data_get($appliedSnapshot, 'approval_status', $financeRecord->approval_status),
            'review_note' => $request->input('review_note'),
            'data' => $data,
        ]);

        $financeRecord = $financeRecord->fresh();

        return $this->financeActionResponse($request, 'Correction request approved.', $financeRecord);
    }

    public function rejectCorrection(Request $request, FinanceRecord $financeRecord)
    {
        if (! $this->canApproveCorrectionRequest($financeRecord)) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'review_note' => 'required|string|max:1000',
        ]);

        $data = $financeRecord->data ?? [];
        $correctionRequests = array_values((array) data_get($data, 'correction_requests', []));
        $latestIndex = collect($correctionRequests)
            ->keys()
            ->reverse()
            ->first(fn ($index) => is_array($correctionRequests[$index] ?? null) && in_array(data_get($correctionRequests[$index], 'status'), ['Pending', 'Submitted'], true));

        if ($latestIndex === null) {
            abort(422, 'No pending correction request exists for this record.');
        }

        $requestEntry = $correctionRequests[$latestIndex];
        $requestEntry['status'] = 'Rejected';
        $requestEntry['rejected_by'] = Auth::id();
        $requestEntry['rejected_by_name'] = Auth::user()?->name ?: 'Admin User';
        $requestEntry['rejected_at'] = now()->format('Y-m-d H:i:s');
        $requestEntry['rejection_note'] = $request->review_note;
        $correctionRequests[$latestIndex] = $requestEntry;

        $data['correction_requests'] = array_values($correctionRequests);
        $data['correction_request_status'] = 'Rejected';
        $data['correction_rejected_by'] = Auth::id();
        $data['correction_rejected_by_name'] = Auth::user()?->name ?: 'Admin User';
        $data['correction_rejected_at'] = now()->format('Y-m-d H:i:s');
        $data['correction_request_rejection_note'] = $request->review_note;

        $originalSnapshot = data_get($requestEntry, 'original', []);
        $correctedSnapshot = data_get($requestEntry, 'corrected', data_get($requestEntry, 'proposed', []));
        $restoredSnapshot = [
            'record_number' => data_get($originalSnapshot, 'record_number', $financeRecord->record_number),
            'record_title' => data_get($originalSnapshot, 'record_title', $financeRecord->record_title),
            'record_date' => data_get($originalSnapshot, 'record_date', optional($financeRecord->record_date)->format('Y-m-d')),
            'amount' => data_get($originalSnapshot, 'amount', $financeRecord->amount),
            'status' => data_get($originalSnapshot, 'status', $financeRecord->status),
            'workflow_status' => data_get($originalSnapshot, 'workflow_status', $financeRecord->workflow_status),
            'approval_status' => data_get($originalSnapshot, 'approval_status', $financeRecord->approval_status),
            'review_note' => $request->review_note,
            'data' => data_get($originalSnapshot, 'data', $financeRecord->data ?? []),
            'attachments' => data_get($originalSnapshot, 'attachments', $financeRecord->attachments ?? []),
        ];

        $requestTypeLabel = Str::headline((string) data_get($requestEntry, 'type', 'Correction'));
        $data = $this->appendFinanceHistoryEntry($data, $requestTypeLabel . ' Rejected', $financeRecord->module_key, $correctedSnapshot, $restoredSnapshot, $request->review_note);

        $financeRecord->update([
            'workflow_status' => data_get($originalSnapshot, 'workflow_status', $financeRecord->workflow_status),
            'approval_status' => data_get($originalSnapshot, 'approval_status', $financeRecord->approval_status),
            'review_note' => $request->review_note,
            'data' => $data,
        ]);

        $financeRecord = $financeRecord->fresh();

        return $this->financeActionResponse($request, 'Correction request rejected.', $financeRecord);
    }

    public function shareSupplierLink(FinanceRecord $financeRecord)
    {
        if (!$this->canManageSupplierCompletion($financeRecord)) {
            abort(403, 'Unauthorized');
        }

        $link = $this->sendSupplierCompletionEmail($financeRecord->fresh());
        $financeRecord = $financeRecord->fresh();

        return response()->json([
            'message' => 'Supplier completion link has been emailed.',
            'link' => $link,
            'data' => $this->transformRecord($financeRecord->fresh()),
        ]);
    }

    public function updateSupplierEmailAndResend(Request $request, FinanceRecord $financeRecord)
    {
        if (!$this->canManageSupplierCompletion($financeRecord)) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'email_address' => 'required|email|max:255',
        ]);

        $data = $financeRecord->data ?? [];
        $oldData = $data;
        $data['email_address'] = $request->email_address;
        $data = $this->appendFinanceHistoryEntry($data, 'Supplier Email Updated', $financeRecord->module_key, [
            'data' => $oldData,
        ], [
            'data' => $data,
        ]);

        $financeRecord->update([
            'data' => $data,
        ]);

        $link = $this->sendSupplierCompletionEmail($financeRecord->fresh());
        $financeRecord = $financeRecord->fresh();
        $this->sendFinanceRecordWorkflowNotification($financeRecord, 'updated');

        return response()->json([
            'message' => 'Supplier email updated and completion form resent.',
            'link' => $link,
            'data' => $this->transformRecord($financeRecord),
        ]);
    }

    public function supplierCompletionForm(string $token)
    {
        $record = FinanceRecord::query()
            ->where('module_key', 'supplier')
            ->where('share_token', $token)
            ->firstOrFail();

        return view('finance.supplier-completion', [
            'record' => $this->transformRecord($record),
            'supplierLabels' => $this->supplierCompletionLabels(),
        ]);
    }

    public function submitSupplierCompletion(Request $request, string $token)
    {
        $record = FinanceRecord::query()
            ->where('module_key', 'supplier')
            ->where('share_token', $token)
            ->firstOrFail();

        if (filled($record->supplier_completed_at) && ($record->workflow_status ?? 'Uploaded') !== 'Reverted') {
            return redirect()
                ->route('finance.supplier.completion', $token)
                ->with('success', 'Supplier information has already been submitted.');
        }

        $request->validate([
            'record_title' => 'required|string|max:255',
            'record_number' => 'required|string|max:255',
            'record_date' => 'required|date',
            'data.entity_type' => 'required|string|max:255',
            'data.representative_full_name' => 'required|string|max:255',
            'data.email_address' => 'required|email|max:255',
            'data.phone_number' => 'required|string|max:255',
            'data.representative_email_address' => 'nullable|email|max:255',
            'data.accounting_email_address' => 'nullable|email|max:255',
            'data.official_email_address' => 'nullable|email|max:255',
            'data.registered_address' => 'nullable|string|max:1000',
            'data.billing_address' => 'nullable|string|max:1000',
            'data.legal_acknowledgment' => 'accepted',
            'data.electronic_signature_consent' => 'accepted',
            'data.data_privacy_consent' => 'accepted',
            'data.confidentiality_undertaking' => 'accepted',
            'data.company_policy_compliance' => 'accepted',
            'data.false_information_penalty' => 'accepted',
            'attachments' => 'nullable|array',
            'attachments.*' => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
            'attachment_labels' => 'nullable|array',
            'attachment_category' => ['nullable', 'string', 'max:255', Rule::in($this->financeAttachmentTypeValues())],
        ]);

        if ($this->recordTitleLooksLikePlaceholder('supplier', $request->record_title)) {
            throw ValidationException::withMessages([
                'record_title' => 'Registered Business Name must be entered as a real value, not saved as the placeholder text.',
            ]);
        }

        $data = array_merge($record->data ?? [], $request->input('data', []));
        if (($data['id_type'] ?? '') !== 'Others') {
            $data['id_type_other'] = '';
        }
        $data['business_name'] = $request->record_title;
        $data['date_accomplished'] = $request->record_date;
        $data['date_signed'] = now()->format('Y-m-d H:i:s');
        $data['supplier_submitted_by_name'] = $request->input('data.representative_full_name', '');
        $data['supplier_submitted_by_email'] = $request->input('data.email_address', '');
        if (data_get($data, 'completion_mode') === 'send_to_supplier' && blank(data_get($data, 'approval_steps'))) {
            $defaultSteps = array_values($this->financeOfficialApproverDirectory()['default_steps']);
            $data['approval_steps'] = $defaultSteps;
            $data['approval_actions'] = [];
            $data['approval_required_count'] = count($defaultSteps);
            $data['approval_completed_count'] = 0;
            $data['approval_remaining_count'] = count($defaultSteps);
            $data['first_approver_user_id'] = data_get($defaultSteps, '0.user_id');
            $data['second_approver_user_id'] = data_get($defaultSteps, '1.user_id');
        }

        $attachments = $this->persistAttachments($request, (array) ($record->attachments ?? []));
        $data = $this->appendFinanceHistoryEntry($data, 'Supplier Submitted', 'supplier', [
            'record_title' => $record->record_title,
            'record_date' => optional($record->record_date)->format('Y-m-d'),
            'workflow_status' => $record->workflow_status,
            'approval_status' => $record->approval_status,
            'data' => $record->data ?? [],
            'attachments' => $record->attachments ?? [],
        ], [
            'record_title' => $request->record_title,
            'record_date' => $request->record_date,
            'workflow_status' => 'Submitted',
            'approval_status' => 'Pending',
            'data' => $data,
            'attachments' => $attachments,
        ]);

        $record->update([
            'record_number' => $request->record_number,
            'record_title' => $request->record_title,
            'record_date' => $request->record_date,
            'data' => $data,
            'attachments' => $attachments,
            'workflow_status' => 'Submitted',
            'approval_status' => 'Pending',
            'submitted_at' => now(),
            'approved_by' => null,
            'approved_at' => null,
            'supplier_completed_at' => now(),
        ]);

        $this->syncFinanceRelationshipLifecycle($record);
        $record = $record->fresh();
        $this->sendFinanceRecordWorkflowNotification($record, 'supplier_submitted');

        return redirect()
            ->route('finance.supplier.completion', $token)
            ->with('success', 'Supplier information submitted successfully. It is now ready for internal review.');
    }
}
