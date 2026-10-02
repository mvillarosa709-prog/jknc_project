<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('official_business_trip_tickets', function (Blueprint $table): void {
            $table->string('archived_reason', 80)->nullable()->after('status');
            $table->foreignId('archived_by_user_id')
                ->nullable()
                ->after('archived_reason')
                ->constrained('users', indexName: 'obtt_archived_by_user_fk')
                ->nullOnDelete();
            $table->timestamp('archived_at')->nullable()->after('archived_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('official_business_trip_tickets', function (Blueprint $table): void {
            $table->dropForeign('obtt_archived_by_user_fk');
            $table->dropColumn(['archived_reason', 'archived_by_user_id', 'archived_at']);
        });
    }
};
