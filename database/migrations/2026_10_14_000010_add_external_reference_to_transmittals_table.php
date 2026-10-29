<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transmittals', function (Blueprint $table) {
            $table->string('external_reference', 120)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('transmittals', function (Blueprint $table) {
            $table->dropColumn('external_reference');
        });
    }
};