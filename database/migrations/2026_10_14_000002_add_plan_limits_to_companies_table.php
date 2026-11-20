<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'storage_limit_bytes')) {
                $table->unsignedBigInteger('storage_limit_bytes')->nullable();
            }
        });

        $defaults = (array) config('ordo.plan_defaults', []);
        $recordUnits = (int) ($defaults['record_units'] ?? 1000);
        $storageBytes = (int) ($defaults['storage_bytes'] ?? 5368709120);

        if ($recordUnits > 0) {
            DB::table('companies')
                ->whereNull('record_unit_allocation')
                ->update(['record_unit_allocation' => $recordUnits]);
        }

        if ($storageBytes > 0) {
            DB::table('companies')
                ->whereNull('storage_limit_bytes')
                ->update(['storage_limit_bytes' => $storageBytes]);
        }
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'storage_limit_bytes')) {
                $table->dropColumn('storage_limit_bytes');
            }
        });
    }
};
