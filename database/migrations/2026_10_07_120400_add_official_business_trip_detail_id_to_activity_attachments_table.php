<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_attachments', function (Blueprint $table): void {
            $table->foreignId('official_business_trip_detail_id')
                ->nullable()
                ->after('activity_id')
                ->constrained('official_business_trip_details')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('activity_attachments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('official_business_trip_detail_id');
        });
    }
};
