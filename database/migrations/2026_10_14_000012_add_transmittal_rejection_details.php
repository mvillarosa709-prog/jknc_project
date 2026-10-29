<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transmittals') && Schema::hasColumn('transmittals', 'status')) {
            DB::table('transmittals')
                ->where('status', 'draft')
                ->update(['status' => 'pending_approval']);
        }

        if (Schema::hasTable('transmittals')) {
            Schema::table('transmittals', function (Blueprint $table) {
                if (!Schema::hasColumn('transmittals', 'rejected_by')) {
                    $table->foreignId('rejected_by')->nullable()->constrained('users')->restrictOnDelete();
                }
                if (!Schema::hasColumn('transmittals', 'rejected_at')) {
                    $table->timestamp('rejected_at')->nullable();
                }
                if (!Schema::hasColumn('transmittals', 'rejection_reason')) {
                    $table->text('rejection_reason')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('transmittals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn(['rejected_at', 'rejection_reason']);
        });
    }
};
