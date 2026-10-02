<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('official_business_trip_ticket_stops', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('official_business_trip_ticket_id')
                ->constrained('official_business_trip_tickets', indexName: 'obtt_stop_ticket_fk')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('destination');
            $table->text('activity')->nullable();
            $table->dateTime('planned_at')->nullable();
            $table->dateTime('actual_arrival_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->string('resolution_status', 30)->default('Planned');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['official_business_trip_ticket_id', 'sequence'],
                'obtt_stop_sequence_unique'
            );
            $table->index(
                ['official_business_trip_ticket_id', 'resolution_status'],
                'obtt_stop_status_index'
            );
        });

        Schema::create('official_business_trip_ticket_personnel', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('official_business_trip_ticket_id')
                ->constrained('official_business_trip_tickets', indexName: 'obtt_personnel_ticket_fk')
                ->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees', indexName: 'obtt_personnel_employee_fk')->nullOnDelete();
            $table->string('employee_name_snapshot');
            $table->string('position_snapshot')->nullable();
            $table->string('department_snapshot')->nullable();
            $table->string('role', 40)->default('Traveler');
            $table->string('participation_status', 30)->default('Assigned');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['official_business_trip_ticket_id', 'employee_id'],
                'obtt_personnel_employee_unique'
            );
            $table->index(
                ['official_business_trip_ticket_id', 'participation_status'],
                'obtt_personnel_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('official_business_trip_ticket_personnel');
        Schema::dropIfExists('official_business_trip_ticket_stops');
    }
};
