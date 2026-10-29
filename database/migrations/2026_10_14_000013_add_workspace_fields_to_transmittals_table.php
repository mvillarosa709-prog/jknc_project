<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transmittals')) {
            Schema::table('transmittals', function (Blueprint $table) {
                if (!Schema::hasColumn('transmittals', 'direction')) {
                    $table->string('direction')->default('send');
                }
                if (!Schema::hasColumn('transmittals', 'transmittal_date')) {
                    $table->date('transmittal_date')->nullable();
                }
                if (!Schema::hasColumn('transmittals', 'origin_office')) {
                    $table->string('origin_office')->nullable();
                }
                if (!Schema::hasColumn('transmittals', 'recipient_address')) {
                    $table->text('recipient_address')->nullable();
                }
                if (!Schema::hasColumn('transmittals', 'related_company')) {
                    $table->string('related_company')->nullable();
                }
                if (!Schema::hasColumn('transmittals', 'delivery_method')) {
                    $table->string('delivery_method')->default('electronic');
                }
                if (!Schema::hasColumn('transmittals', 'edit_token_hash')) {
                    $table->string('edit_token_hash', 64)->nullable()->unique();
                }
            });

            if (Schema::hasColumn('transmittals', 'transmittal_date')) {
                DB::table('transmittals')->whereNull('transmittal_date')->update([
                    'transmittal_date' => DB::raw('DATE(created_at)'),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (DB::table('transmittals')->whereNotNull('edit_token_hash')->exists()) {
            throw new RuntimeException('Active draft edit credentials exist; the workspace migration cannot be rolled back safely.');
        }

        Schema::table('transmittals', function (Blueprint $table) {
            $table->dropUnique(['edit_token_hash']);
            $table->dropColumn([
                'direction',
                'transmittal_date',
                'origin_office',
                'recipient_address',
                'related_company',
                'delivery_method',
                'edit_token_hash',
            ]);
        });
    }
};
