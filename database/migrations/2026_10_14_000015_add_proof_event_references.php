<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proof_of_transmissions', function (Blueprint $table) {
            $table->string('transaction_reference', 40)->nullable()->index();
            $table->timestamp('acknowledged_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('proof_of_transmissions', function (Blueprint $table) {
            $table->dropColumn(['transaction_reference', 'acknowledged_at']);
        });
    }
};
