<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transmittals', function (Blueprint $table) {
            $table->softDeletes();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancelled_from_status')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
        });

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
