<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('official_business_trip_personnel', function (Blueprint $table): void {
            $table->string('nature_of_travel', 80)->nullable();
            $table->string('immediate_superior_snapshot')->nullable();
            $table->string('role', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('official_business_trip_personnel', function (Blueprint $table): void {
            $table->dropColumn(['nature_of_travel', 'immediate_superior_snapshot', 'role']);
        });
    }
};
