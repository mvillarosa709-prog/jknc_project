<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('official_business_trips', function (Blueprint $table): void {
            if (! Schema::hasColumn('official_business_trips', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('official_business_trips', function (Blueprint $table): void {
            if (Schema::hasColumn('official_business_trips', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
