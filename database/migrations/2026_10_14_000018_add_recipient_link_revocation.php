<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transmittal_recipients')) {
            Schema::table('transmittal_recipients', function (Blueprint $table) {
                if (!Schema::hasColumn('transmittal_recipients', 'acknowledgment_revoked_at')) {
                    $table->timestamp('acknowledgment_revoked_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('transmittal_recipients', function (Blueprint $table) {
            $table->dropColumn('acknowledgment_revoked_at');
        });
    }
};
