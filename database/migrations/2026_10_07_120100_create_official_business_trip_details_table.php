<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('official_business_trip_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('official_business_trip_id')
                ->constrained('official_business_trips')
                ->cascadeOnDelete();
            $table->string('classification', 30)->nullable()->index();
            $table->unsignedInteger('sort_order')->default(0);

            $table->string('from_location')->nullable();
            $table->string('destination')->nullable();
            $table->text('purpose')->nullable();

            // Company is the canonical client identity used by current O.B. Trip Tickets.
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('engagement_type', 60)->nullable();
            $table->foreignId('deal_id')->nullable()->constrained('deals')->nullOnDelete();
            // Regular engagements are Project records with a Regular engagement_type.
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();

            $table->string('internal_activity_type', 120)->nullable();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('related_internal_type', 120)->nullable();
            $table->unsignedBigInteger('related_internal_id')->nullable();

            $table->string('activity_event_name')->nullable();
            $table->string('social_activity_type', 120)->nullable();
            $table->string('organization_host')->nullable();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('related_contact_text')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['official_business_trip_id', 'sort_order'],
                'ob_trip_detail_parent_order_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('official_business_trip_details');
    }
};
