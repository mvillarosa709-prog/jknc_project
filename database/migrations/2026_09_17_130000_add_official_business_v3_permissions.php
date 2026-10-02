<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $permissions = [
        'create_hc_obf',
        'approve_hc_obf',
        'control_hc_obf_travel',
        'manage_hc_obf',
        'access_hc_obf_finance',
        'admin_hc_obf',
    ];

    public function up(): void
    {
        foreach (['role_permissions', 'user_permissions'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                foreach ($this->permissions as $permission) {
                    if (! Schema::hasColumn($tableName, $permission)) {
                        $table->boolean($permission)->default(false);
                    }
                }
            });

            if (Schema::hasColumn($tableName, 'access_hc_obf')) {
                DB::table($tableName)
                    ->where('access_hc_obf', true)
                    ->update([
                        'create_hc_obf' => true,
                        'approve_hc_obf' => true,
                        'control_hc_obf_travel' => true,
                        'manage_hc_obf' => true,
                    ]);
            }

            if (Schema::hasColumn($tableName, 'access_finance')) {
                DB::table($tableName)
                    ->where('access_finance', true)
                    ->update(['access_hc_obf_finance' => true]);
            }
        }

        if (Schema::hasTable('role_permissions')) {
            DB::table('role_permissions')
                ->whereIn('role', ['SuperAdmin', 'Admin'])
                ->update(array_fill_keys($this->permissions, true));
        }

        if (Schema::hasTable('user_permissions') && Schema::hasTable('users')) {
            DB::table('user_permissions')
                ->whereIn('user_id', DB::table('users')->whereIn('role', ['SuperAdmin', 'Admin'])->select('id'))
                ->update(['admin_hc_obf' => true]);
        }
    }

    public function down(): void
    {
        foreach (['user_permissions', 'role_permissions'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            $columns = array_values(array_filter(
                $this->permissions,
                fn (string $permission): bool => Schema::hasColumn($tableName, $permission)
            ));

            if ($columns !== []) {
                Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn($columns));
            }
        }
    }
};
