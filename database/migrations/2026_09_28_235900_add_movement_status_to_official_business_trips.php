<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('official_business_trips', function (Blueprint $table): void {
            $table->string('movement_status', 40)
                ->nullable()
                ->after('status')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('official_business_trips', function (Blueprint $table): void {
            $table->dropIndex(['movement_status']);
            $table->dropColumn('movement_status');
        });
    }
};