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
                if (!Schema::hasColumn('transmittals', 'tracking_number')) {
                    $table->string('tracking_number')->nullable()->unique();
                }
                if (!Schema::hasColumn('transmittals', 'subject')) {
                    $table->string('subject')->nullable();
                }
                if (!Schema::hasColumn('transmittals', 'purpose')) {
                    $table->text('purpose')->nullable();
                }
                if (!Schema::hasColumn('transmittals', 'confidentiality')) {
                    $table->string('confidentiality')->default('standard');
                }
                if (!Schema::hasColumn('transmittals', 'status')) {
                    $table->string('status')->default('draft')->index();
                }
                if (!Schema::hasColumn('transmittals', 'prepared_by')) {
                    $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
                }
                if (!Schema::hasColumn('transmittals', 'approved_by')) {
                    $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                }
                if (!Schema::hasColumn('transmittals', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable();
                }
                if (!Schema::hasColumn('transmittals', 'approved_snapshot')) {
                    $table->json('approved_snapshot')->nullable();
                }
                if (!Schema::hasColumn('transmittals', 'approval_authority_code')) {
                    $table->string('approval_authority_code')->nullable()->unique();
                }
            });
        } else {
            Schema::create('transmittals', function (Blueprint $table) {
                $table->id();
                $table->string('tracking_number')->unique();
                $table->string('subject');
                $table->text('purpose');
                $table->string('confidentiality')->default('standard');
                $table->string('status')->default('draft')->index();
                $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->json('approved_snapshot')->nullable();
                $table->string('approval_authority_code')->nullable()->unique();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transmittals');
    }
};
