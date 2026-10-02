<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Retain legacy rows. No verified, lossless backfill exists for this table.
        // Removal requires a separate release after production reconciliation.
    }

    public function down(): void
    {
        // Forward and reverse execution both leave legacy data untouched.
    }
};
