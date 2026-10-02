<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('official_business_trips', function (Blueprint $table): void {
            // Existing and legacy-created records keep their existing lifecycle semantics.
            $table->unsignedTinyInteger('workflow_version')->default(1);
        });

        Schema::create('official_business_trip_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('official_business_trip_id')->constrained('official_business_trips')->restrictOnDelete();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('reference_no', 80)->unique();
            $table->string('client_name_snapshot');
            // A ticket is not completed merely because its legacy master is Completed.
            $table->string('status', 40)->default('Draft');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Includes soft-deleted tickets: restoring must not create duplicate identities.
            $table->unique(['official_business_trip_id', 'company_id'], 'obtt_master_client_unique');
            $table->index(['company_id', 'status'], 'obtt_client_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('official_business_trip_tickets');
        Schema::table('official_business_trips', function (Blueprint $table): void {
            $table->dropColumn('workflow_version');
        });
    }
};
