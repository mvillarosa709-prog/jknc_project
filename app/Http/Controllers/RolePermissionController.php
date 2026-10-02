<?php

namespace App\Http\Controllers;

use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class RolePermissionController extends Controller
{
    private array $accountManagementPermissionColumns = [
        'create_user_account',
        'edit_user_account',
        'disable_enable_user_account',
        'reset_user_password',
        'delete_user_account',
    ];

    private array $financePermissionColumns = [
        'access_finance',
        'create_finance',
        'approve_finance',
        'access_finance_supplier',
        'access_finance_service',
        'access_finance_product',
        'access_finance_chart_account',
        'access_finance_bank_account',
        'access_finance_pr',
        'access_finance_po',
        'access_finance_ca',
        'access_finance_lr',
        'access_finance_err',
        'access_finance_dv',
        'access_finance_pda',
        'access_finance_crf',
        'access_finance_ibtf',
        'access_finance_arf',
    ];

    private array $humanCapitalPermissionColumns = [
        'access_hc_organizational',
        'access_hc_payroll',
        'access_hc_employee_profile',
        'access_hc_recruitment',
        'access_hc_onboarding',
        'access_hc_deployment',
        'access_hc_my_hc',
        'access_hc_attendance',
        'access_hc_obf',
        'create_hc_obf',
        'approve_hc_obf',
        'control_hc_obf_travel',
        'manage_hc_obf',
        'access_hc_obf_finance',
        'admin_hc_obf',
        'access_hc_employee_requests',
        'access_hc_employee_relations',
        'access_hc_memos',
        'access_hc_training',
        'access_hc_performance',
        'access_hc_awards',
        'access_hc_offboarding',
    ];

    public function index()
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('manage_users')) {
            abort(403, 'Unauthorized');
        }

        RolePermission::firstOrCreate(
            ['role' => 'SuperAdmin'],
            [
                'manage_users' => true,
                'access_admin_dashboard' => true,
                ...array_fill_keys($this->accountManagementPermissionColumns, true),

                'approve_townhall' => true,
                'create_townhall' => true,
                'create_corporate' => true,
                'approve_corporate' => true,
                'approve_policies' => true,
                'access_townhall' => true,
                'access_corporate' => true,
                'access_activities' => true,
                'access_contacts' => true,
                'access_company' => true,

                'access_transmittal' => true,
                'access_deals' => true,
                'access_services' => true,
                'access_project' => true,
                'access_regular' => true,
                'access_product' => true,
                'access_policies' => true,

                'create_sales_marketing' => true,
                'approve_sales_marketing' => true,
                'access_sales_marketing' => true,

                'access_human_capital' => true,
                ...array_fill_keys($this->humanCapitalPermissionColumns, true),

                ...array_fill_keys($this->financePermissionColumns, true),
            ]
        );

        RolePermission::firstOrCreate(
            ['role' => 'Admin'],
            [
                'manage_users' => true,
                'access_admin_dashboard' => true,
                ...array_fill_keys($this->accountManagementPermissionColumns, false),

                'approve_townhall' => true,
                'create_townhall' => true,
                'create_corporate' => true,
                'approve_corporate' => true,
                'approve_policies' => true,
                'access_townhall' => true,
                'access_corporate' => true,
                'access_activities' => true,
                'access_contacts' => true,
                'access_company' => true,

                'access_transmittal' => true,
                'access_deals' => true,
                'access_services' => true,
                'access_project' => true,
                'access_regular' => true,
                'access_product' => true,
                'access_policies' => true,

                'create_sales_marketing' => true,
                'approve_sales_marketing' => true,
                'access_sales_marketing' => true,

                'access_human_capital' => true,
                ...array_fill_keys($this->humanCapitalPermissionColumns, true),

                ...array_fill_keys($this->financePermissionColumns, true),
            ]
        );

        RolePermission::firstOrCreate(
            ['role' => 'Employee'],
            [
                'manage_users' => false,
                'access_admin_dashboard' => false,
                ...array_fill_keys($this->accountManagementPermissionColumns, false),

                'approve_townhall' => false,
                'create_townhall' => false,
                'create_corporate' => false,
                'approve_corporate' => false,
                'approve_policies' => false,
                'access_townhall' => true,
                'access_corporate' => true,
                'access_activities' => false,
                'access_contacts' => false,
                'access_company' => false,

                'access_transmittal' => false,
                'access_deals' => false,
                'access_services' => false,
                'access_project' => false,
                'access_regular' => false,
                'access_product' => false,
                'access_policies' => false,

                'create_sales_marketing' => true,
                'approve_sales_marketing' => false,
                'access_sales_marketing' => true,

                'access_human_capital' => false,
                ...array_fill_keys($this->humanCapitalPermissionColumns, false),
                'create_hc_obf' => true,

                ...array_fill_keys($this->financePermissionColumns, false),
            ]
        );

        RolePermission::firstOrCreate(
            ['role' => 'Client'],
            [
                'manage_users' => false,
                'access_admin_dashboard' => false,
                ...array_fill_keys($this->accountManagementPermissionColumns, false),

                'approve_townhall' => false,
                'create_townhall' => false,
                'create_corporate' => false,
                'approve_corporate' => false,
                'approve_policies' => false,
                'access_townhall' => true,
                'access_corporate' => false,
                'access_activities' => false,
                'access_contacts' => false,
                'access_company' => false,

                'access_transmittal' => false,
                'access_deals' => false,
                'access_services' => false,
                'access_project' => false,
                'access_regular' => false,
                'access_product' => false,
                'access_policies' => false,

                'create_sales_marketing' => false,
                'approve_sales_marketing' => false,
                'access_sales_marketing' => false,

                'access_human_capital' => false,
                ...array_fill_keys($this->humanCapitalPermissionColumns, false),

                ...array_fill_keys($this->financePermissionColumns, false),
            ]
        );

        $permissions = RolePermission::orderByRaw("
            CASE role
                WHEN 'SuperAdmin' THEN 1
                WHEN 'Admin' THEN 2
                WHEN 'Employee' THEN 3
                WHEN 'Client' THEN 4
                ELSE 5
            END
        ")->get();

        return view('admin.role-permissions', compact('permissions'));
    }

    public function update(Request $request, $id)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->hasPermission('manage_users')) {
            abort(403, 'Unauthorized');
        }

        $permission = RolePermission::findOrFail($id);

        if ($permission->role === 'SuperAdmin') {
            abort(403, 'SuperAdmin role permissions cannot be modified.');
        }

        $updates = [];

        foreach (
            array_merge([
                'manage_users',
                'access_admin_dashboard',

                'approve_townhall',
                'create_townhall',

                'create_corporate',
                'approve_corporate',
                'approve_policies',
                'access_townhall',
                'access_corporate',
                'access_activities',
                'access_contacts',
                'access_company',

                'access_transmittal',
                'access_deals',
                'access_services',
                'access_project',
                'access_regular',
                'access_product',
                'access_policies',

                'create_sales_marketing',
                'approve_sales_marketing',
                'access_sales_marketing',

                'access_human_capital',
            ], $this->accountManagementPermissionColumns, $this->humanCapitalPermissionColumns, $this->financePermissionColumns) as $column
        ) {
            if (Schema::hasColumn('role_permissions', $column)) {
                $updates[$column] = $request->has($column);
            }
        }

        $permission->update($updates);

        return redirect()->route('admin.role-permissions')
            ->with('success', 'Role permissions updated successfully.');
    }
}
