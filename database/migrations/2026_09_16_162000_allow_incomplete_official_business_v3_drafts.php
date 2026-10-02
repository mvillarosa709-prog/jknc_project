<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('official_business_trips', function (Blueprint $table): void {
            $table->unsignedBigInteger('employee_id')->nullable()->change();
            $table->string('employee_name')->nullable()->change();
            $table->string('destination')->nullable()->change();
            $table->text('purpose')->nullable()->change();
            $table->date('date_from')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (
            DB::table('official_business_trips')
                ->whereNull('employee_id')
                ->orWhereNull('employee_name')
                ->orWhereNull('destination')
                ->orWhereNull('purpose')
                ->orWhereNull('date_from')
                ->exists()
        ) {
            throw new RuntimeException(
                'Cannot restore NOT NULL Official Business fields while incomplete draft records exist.'
            );
        }

        Schema::table('official_business_trips', function (Blueprint $table): void {
            $table->unsignedBigInteger('employee_id')->nullable(false)->change();
            $table->string('employee_name')->nullable(false)->change();
            $table->string('destination')->nullable(false)->change();
            $table->text('purpose')->nullable(false)->change();
            $table->date('date_from')->nullable(false)->change();
        });
    }
};
