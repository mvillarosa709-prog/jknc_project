<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transmittal_receipts', function (Blueprint $table) {
            $table->string('receipt_no')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transmittal_receipts', function (Blueprint $table) {
            $table->string('receipt_no')->nullable(false)->change();
        });
    }
};
