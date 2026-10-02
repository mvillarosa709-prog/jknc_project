<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('related_account')->nullable()->after('title');
            $table->string('event_type')->nullable()->after('related_account');

            $table->string('venue')->nullable()->after('to');
            $table->string('mode')->nullable()->after('venue');

            $table->integer('participants')->nullable()->after('mode');

            $table->string('role')->nullable()->after('participants');

            $table->string('status')->nullable()->after('role');

            $table->decimal('estimated_hours', 8, 2)->nullable()->after('status');
            $table->decimal('actual_hours', 8, 2)->nullable()->after('estimated_hours');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'related_account',
                'event_type',
                'venue',
                'mode',
                'participants',
                'role',
                'status',
                'estimated_hours',
                'actual_hours'
            ]);
        });
    }
};
