<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_travel_credit_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained('companies')->restrictOnDelete();
            $table->decimal('available_units', 12, 2)->default(0);
            $table->decimal('credit_value', 15, 2)->default(500);
            $table->timestamps();
        });

        Schema::create('company_travel_credit_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_travel_credit_account_id')
                ->constrained('company_travel_credit_accounts', indexName: 'ctce_account_fk')
                ->restrictOnDelete();
            $table->foreignId('official_business_trip_ticket_id')
                ->nullable()
                ->constrained('official_business_trip_tickets', indexName: 'ctce_ticket_fk')
                ->restrictOnDelete();
            $table->string('entry_type', 30);
            $table->decimal('units_delta', 12, 2);
            $table->decimal('unit_value', 15, 2);
            $table->decimal('balance_after', 12, 2);
            $table->text('reason')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users', indexName: 'ctce_recorder_fk')->nullOnDelete();
            $table->foreignId('reverses_entry_id')->nullable()->constrained('company_travel_credit_entries', indexName: 'ctce_reverses_fk')->restrictOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by_user_id')->nullable()->constrained('users', indexName: 'ctce_reversed_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['official_business_trip_ticket_id', 'entry_type'], 'ctce_ticket_type_index');
            $table->index(['company_travel_credit_account_id', 'created_at'], 'ctce_account_created_index');
        });

        Schema::table('official_business_trip_ticket_financial_treatments', function (Blueprint $table): void {
            $table->foreignId('travel_credit_entry_id')
                ->nullable()
                ->after('travel_credit_value')
                ->constrained('company_travel_credit_entries', indexName: 'obttft_credit_entry_fk')
                ->restrictOnDelete();
            $table->unique('travel_credit_entry_id', 'obttft_credit_entry_unique');
        });
    }

    public function down(): void
    {
        Schema::table('official_business_trip_ticket_financial_treatments', function (Blueprint $table): void {
            $table->dropUnique('obttft_credit_entry_unique');
            $table->dropForeign('obttft_credit_entry_fk');
            $table->dropColumn('travel_credit_entry_id');
        });
        Schema::dropIfExists('company_travel_credit_entries');
        Schema::dropIfExists('company_travel_credit_accounts');
    }
};
