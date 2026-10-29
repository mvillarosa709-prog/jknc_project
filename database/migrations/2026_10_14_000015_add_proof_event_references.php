<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('proof_of_transmissions')) {
            Schema::table('proof_of_transmissions', function (Blueprint $table) {
                if (!Schema::hasColumn('proof_of_transmissions', 'transaction_reference')) {
                    $table->string('transaction_reference', 40)->nullable()->index();
                }
                if (!Schema::hasColumn('proof_of_transmissions', 'acknowledged_at')) {
                    $table->timestamp('acknowledged_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('proof_of_transmissions', function (Blueprint $table) {
            $table->dropColumn(['transaction_reference', 'acknowledged_at']);
        });
    }
};
