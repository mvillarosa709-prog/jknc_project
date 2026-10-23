<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proof_of_transmissions', function (Blueprint $table): void {
            $table->foreignId('recipient_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('proof_of_transmissions', function (Blueprint $table): void {
            $table->foreignId('recipient_id')->nullable(false)->change();
        });
    }
};
