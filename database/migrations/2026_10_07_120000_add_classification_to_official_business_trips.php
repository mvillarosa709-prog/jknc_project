<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('official_business_trips', function (Blueprint $table): void {
            $table->string('classification', 30)
                ->nullable()
                ->after('is_client_travel')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('official_business_trips', function (Blueprint $table): void {
            $table->dropIndex(['classification']);
            $table->dropColumn('classification');
        });
    }
};
