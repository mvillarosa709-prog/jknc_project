<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transmittals', function (Blueprint $table) {
            $table->string('direction')->default('send');
            $table->date('transmittal_date')->nullable();
            $table->string('origin_office')->nullable();
            $table->text('recipient_address')->nullable();
            $table->string('related_company')->nullable();
            $table->string('delivery_method')->default('electronic');
            $table->string('edit_token_hash', 64)->nullable()->unique();
        });

        DB::table('transmittals')->whereNull('transmittal_date')->update([
            'transmittal_date' => DB::raw('DATE(created_at)'),
        ]);
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
