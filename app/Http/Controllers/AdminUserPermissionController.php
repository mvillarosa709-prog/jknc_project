<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class AdminUserPermissionController extends Controller
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
        'finance_treasurer',
        'finance_president',
        'finance_approver',
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
        if (!Auth::user()->hasPermission('manage_users')) {
            abort(403, 'Unauthorized');
        }

        $users = User::where('role', '!=', 'SuperAdmin')
            ->whereNull('archived_at')
            ->orderBy('name')
            ->paginate(10);

        return view('admin.user-permissions', compact('users'));
    }

    public function edit($id)
    {
        if (!Auth::user()->hasPermission('manage_users')) {
            abort(403, 'Unauthorized');
        }

        $user = User::findOrFail($id);

        if ($user->role === 'SuperAdmin') {
            abort(403, 'SuperAdmin permissions cannot be modified.');
        }

        $permission = UserPermission::firstOrNew(
            ['user_id' => $user->id],
            [
                'manage_users' => false,
                'create_user_account' => false,
                'edit_user_account' => false,
                'disable_enable_user_account' => false,
                'reset_user_password' => false,
                'delete_user_account' => false,
                'access_admin_dashboard' => false,
                'approve_townhall' => false,
                'create_townhall' => false,
                'create_corporate' => false,
                'approve_corporate' => false,
                'approve_policies' => false,
                'access_townhall' => false,
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

        return view('admin.edit-user-permissions', compact('user', 'permission'));
    }

    public function update(Request $request, $id)
    {
        if (!Auth::user()->hasPermission('manage_users')) {
            abort(403, 'Unauthorized');
        }

        $user = User::findOrFail($id);

        if ($user->role === 'SuperAdmin') {
            abort(403, 'SuperAdmin permissions cannot be modified.');
        }

        if ($user->id === Auth::id()) {
            if (!$request->has('manage_users') || !$request->has('access_admin_dashboard')) {
                return back()->with('error', 'You cannot remove your own critical admin permissions.');
            }
        }

        $permission = UserPermission::firstOrCreate(['user_id' => $user->id]);

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
                'finance_treasurer',
                'finance_president',
                'finance_approver',
            ], $this->accountManagementPermissionColumns, $this->humanCapitalPermissionColumns, $this->financePermissionColumns) as $column
        ) {
            if (Schema::hasColumn('user_permissions', $column)) {
                $updates[$column] = $request->has($column);
            }
        }

        $permission->update($updates);

        return redirect()->route('admin.user-permissions')->with('success', 'User permissions updated successfully.');
    }
}
