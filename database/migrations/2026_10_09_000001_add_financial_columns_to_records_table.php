<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('records', function (Blueprint $table): void {
            $table->decimal('amount', 14, 2)->nullable()->after('external_reference_number');
            $table->string('currency', 3)->default('PHP')->after('amount');
            $table->text('notes')->nullable()->after('financials');
        });
    }

    public function down(): void
    {
        Schema::table('records', function (Blueprint $table): void {
            $table->dropColumn(['amount', 'currency', 'notes']);
        });
    }
};
