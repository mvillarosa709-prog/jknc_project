<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proof_of_transmissions', function (Blueprint $table) {
            $table->foreignId('document_id')
                ->nullable()
                ->after('recipient_id')
                ->constrained('transmittal_documents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('proof_of_transmissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('document_id');
        });
    }
};
