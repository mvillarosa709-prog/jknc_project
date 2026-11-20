<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('record_classifications', function (Blueprint $table): void {
            // The machine-readable retention rule. records.retention_period stays a
            // free-text label for display; this is what expiry dates are computed
            // from, because prose like "retain permanently" cannot be parsed.
            // Null means no expiry applies.
            $table->unsignedInteger('retention_months')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('record_classifications', function (Blueprint $table): void {
            $table->dropColumn('retention_months');
        });
    }
};
