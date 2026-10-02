<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('official_business_trip_ticket_financial_treatments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('official_business_trip_ticket_id')
                ->unique('obtt_financial_ticket_unique')
                ->constrained('official_business_trip_tickets', indexName: 'obtt_financial_ticket_fk')
                ->restrictOnDelete();
            $table->string('billing_treatment', 60);
            $table->decimal('travel_credit_units', 10, 2)->nullable();
            $table->decimal('travel_credit_value', 15, 2)->nullable();
            $table->boolean('evidence_required')->default(true);
            $table->text('evidence_requirement_notes')->nullable();
            $table->string('additional_costs_status', 30)->default('Pending');
            $table->text('notes')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users', indexName: 'obtt_financial_resolver_fk')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('official_business_trip_ticket_costs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('official_business_trip_ticket_id')
                ->constrained('official_business_trip_tickets', indexName: 'obtt_cost_ticket_fk')
                ->restrictOnDelete();
            $table->string('category', 80)->nullable();
            $table->text('description');
            $table->decimal('amount', 15, 2);
            $table->date('incurred_on')->nullable();
            $table->string('billing_treatment', 60);
            $table->text('evidence_reference')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users', indexName: 'obtt_cost_recorder_fk')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['official_business_trip_ticket_id', 'incurred_on'],
                'obtt_cost_ticket_date_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('official_business_trip_ticket_costs');
        Schema::dropIfExists('official_business_trip_ticket_financial_treatments');
    }
};
