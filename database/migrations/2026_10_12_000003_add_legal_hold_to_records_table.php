<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('records', function (Blueprint $table): void {
            // A legal hold freezes the record against edits and archiving until
            // it is explicitly released. The reason and setter are kept so the
            // hold can be explained and attributed years later.
            $table->boolean('is_legal_hold')->default(false)->after('retention_until');
            $table->text('legal_hold_reason')->nullable()->after('is_legal_hold');
            $table->timestamp('legal_hold_set_at')->nullable()->after('legal_hold_reason');
            $table->foreignUlid('legal_hold_set_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('records', function (Blueprint $table): void {
            $table->dropColumn(['is_legal_hold', 'legal_hold_reason', 'legal_hold_set_at', 'legal_hold_set_by']);
        });
    }
};
