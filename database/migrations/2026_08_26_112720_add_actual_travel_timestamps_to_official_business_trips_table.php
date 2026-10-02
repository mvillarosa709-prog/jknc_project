<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('official_business_trips', function (Blueprint $table): void {
            $table->dateTime('actual_departure')->nullable()->after('return_time');
            $table->dateTime('actual_return')->nullable()->after('actual_departure');
        });
    }

    public function down(): void
    {
        Schema::table('official_business_trips', function (Blueprint $table): void {
            $table->dropColumn([
                'actual_departure',
                'actual_return',
            ]);
        });
    }
};
