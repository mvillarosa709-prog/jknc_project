<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transmittal_receipts')) {
            Schema::table('transmittal_receipts', function (Blueprint $table) {
                // Idagdag ang mga columns o constraints kung wala pa sa lumang table
                if (!Schema::hasColumn('transmittal_receipts', 'recipient_id')) {
                    $table->foreignId('recipient_id')->after('transmittal_id')->constrained('transmittal_recipients')->cascadeOnDelete();
                }
                if (!Schema::hasColumn('transmittal_receipts', 'receipt_code')) {
                    $table->string('receipt_code')->unique()->nullable();
                }
                if (!Schema::hasColumn('transmittal_receipts', 'snapshot')) {
                    $table->json('snapshot')->nullable();
                }
                if (!Schema::hasColumn('transmittal_receipts', 'received_at')) {
                    $table->timestamp('received_at')->nullable();
                }
                if (!Schema::hasColumn('transmittal_receipts', 'ip_address')) {
                    $table->string('ip_address', 45)->nullable();
                }
                if (!Schema::hasColumn('transmittal_receipts', 'user_agent')) {
                    $table->text('user_agent')->nullable();
                }
            });
        } else {
            Schema::create('transmittal_receipts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('transmittal_id')->constrained()->cascadeOnDelete();
                $table->foreignId('recipient_id')->constrained('transmittal_recipients')->cascadeOnDelete();
                $table->string('signed_name');
                $table->text('comments')->nullable();
                $table->string('receipt_code')->unique();
                $table->json('snapshot');
                $table->timestamp('received_at');
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transmittal_receipts');
    }
};