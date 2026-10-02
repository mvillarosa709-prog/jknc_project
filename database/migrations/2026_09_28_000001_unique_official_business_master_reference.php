<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicate = DB::table('official_business_trips')
            ->select('ob_reference_no')
            ->whereNotNull('ob_reference_no')
            ->groupBy('ob_reference_no')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicate) {
            throw new RuntimeException('Duplicate master O.B. references must be reviewed before adding the unique index.');
        }

        Schema::table('official_business_trips', function (Blueprint $table): void {
            $table->unique('ob_reference_no', 'ob_master_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::table('official_business_trips', function (Blueprint $table): void {
            $table->dropUnique('ob_master_reference_unique');
        });
    }
};
