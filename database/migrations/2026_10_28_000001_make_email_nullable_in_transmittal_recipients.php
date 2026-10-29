<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transmittal_recipients') && Schema::hasColumn('transmittal_recipients', 'email')) {
            Schema::table('transmittal_recipients', function (Blueprint $table): void {
                $table->string('email')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('transmittal_recipients') && Schema::hasColumn('transmittal_recipients', 'email')) {
            Schema::table('transmittal_recipients', function (Blueprint $table): void {
                $table->string('email')->nullable(false)->change();
            });
        }
    }
};
