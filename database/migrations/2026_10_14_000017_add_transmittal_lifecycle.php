<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transmittals')) {
            Schema::table('transmittals', function (Blueprint $table) {
                if (!Schema::hasColumn('transmittals', 'deleted_at')) {
                    $table->softDeletes();
                }
                if (!Schema::hasColumn('transmittals', 'cancelled_by')) {
                    $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
                }
                if (!Schema::hasColumn('transmittals', 'cancelled_from_status')) {
                    $table->string('cancelled_from_status')->nullable();
                }
                if (!Schema::hasColumn('transmittals', 'cancelled_at')) {
                    $table->timestamp('cancelled_at')->nullable();
                }
                if (!Schema::hasColumn('transmittals', 'cancellation_reason')) {
                    $table->text('cancellation_reason')->nullable();
                }
            });
        }

        if (!Schema::hasTable('transmittal_lifecycle_archives')) {
            Schema::create('transmittal_lifecycle_archives', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('transmittal_id')->nullable()->index();
                $table->string('tracking_number')->index();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action');
                $table->text('reason');
                $table->json('archived_record');
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('transmittal_lifecycle_archives')
            && \Illuminate\Support\Facades\DB::table('transmittal_lifecycle_archives')->exists()) {
            throw new RuntimeException('Lifecycle archives exist; this audit-preserving migration cannot be rolled back safely.');
        }

        Schema::dropIfExists('transmittal_lifecycle_archives');
        Schema::table('transmittals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropSoftDeletes();
            $table->dropColumn([
                'cancelled_from_status',
                'cancelled_at',
                'cancellation_reason',
            ]);
        });
    }
};
