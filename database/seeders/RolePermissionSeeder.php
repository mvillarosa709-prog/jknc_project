<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RolePermission;

class RolePermissionSeeder extends Seeder
{
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
        'access_hc_offboarding',
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
    ];

    public function run(): void
    {
        RolePermission::updateOrCreate(
            ['role' => 'SuperAdmin'],
            [
                'manage_users' => true,
                'access_admin_dashboard' => true,

                'create_townhall' => true,
                'approve_townhall' => true,

                'create_corporate' => true,
                'approve_corporate' => true,

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

        RolePermission::updateOrCreate(
            ['role' => 'Admin'],
            [
                'manage_users' => true,
                'access_admin_dashboard' => true,

                'create_townhall' => true,
                'approve_townhall' => true,

                'create_corporate' => true,
                'approve_corporate' => true,

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

        RolePermission::updateOrCreate(
            ['role' => 'Employee'],
            [
                'manage_users' => false,
                'access_admin_dashboard' => false,

                'create_townhall' => false,
                'approve_townhall' => false,

                'create_corporate' => false,
                'approve_corporate' => false,

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

                'access_human_capital' => true,
                ...array_fill_keys($this->humanCapitalPermissionColumns, false),
                'create_hc_obf' => true,

                ...array_fill_keys($this->financePermissionColumns, false),
            ]
        );

        RolePermission::updateOrCreate(
            ['role' => 'Client'],
            [
                'manage_users' => false,
                'access_admin_dashboard' => false,

                'create_townhall' => false,
                'approve_townhall' => false,

                'create_corporate' => false,
                'approve_corporate' => false,

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

                'create_sales_marketing' => false,
                'approve_sales_marketing' => false,
                'access_sales_marketing' => false,

                'access_human_capital' => false,
                ...array_fill_keys($this->humanCapitalPermissionColumns, false),

                ...array_fill_keys($this->financePermissionColumns, false),
            ]
        );
    }
}
