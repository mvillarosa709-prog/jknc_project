<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            if (! Schema::hasColumn('meetings', 'meeting_type')) {
                $table->string('meeting_type')->nullable()->after('title');
            }

            if (! Schema::hasColumn('meetings', 'related_account')) {
                $table->string('related_account')->nullable()->after('meeting_type');
            }

            if (! Schema::hasColumn('meetings', 'related_project')) {
                $table->string('related_project')->nullable()->after('related_account');
            }

            if (! Schema::hasColumn('meetings', 'mode')) {
                $table->string('mode')->nullable()->after('related_project');
            }

            if (! Schema::hasColumn('meetings', 'actual_duration')) {
                $table->string('actual_duration')->nullable()->after('duration');
            }
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            if (Schema::hasColumn('meetings', 'meeting_type')) {
                $table->dropColumn('meeting_type');
            }

            if (Schema::hasColumn('meetings', 'related_account')) {
                $table->dropColumn('related_account');
            }

            if (Schema::hasColumn('meetings', 'related_project')) {
                $table->dropColumn('related_project');
            }

            if (Schema::hasColumn('meetings', 'mode')) {
                $table->dropColumn('mode');
            }

            if (Schema::hasColumn('meetings', 'actual_duration')) {
                $table->dropColumn('actual_duration');
            }
        });
    }
};