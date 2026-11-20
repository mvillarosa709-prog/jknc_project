<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transmittals')) {
            Schema::create('transmittals', function (Blueprint $table) {
                $table->id();

                $table->string('transmittal_no')->nullable()->unique();
                $table->date('transmittal_date')->nullable();

                $table->string('mode')->default('SEND'); // SEND / RECEIVE

                $table->string('party_name')->nullable(); // external party
                $table->string('office_name')->nullable(); // office

                $table->text('address')->nullable();

                $table->string('delivery_type')->nullable(); // By Person / Registered Mail / Electronic
                $table->string('by_person_who')->nullable();
                $table->string('registered_mail_provider')->nullable();
                $table->string('electronic_method')->nullable();
                $table->string('recipient_email')->nullable();

                $table->boolean('action_delivery')->default(false);
                $table->boolean('action_pick_up')->default(false);
                $table->boolean('action_drop_off')->default(false);
                $table->boolean('action_email')->default(false);

                $table->string('prepared_by_name')->nullable();
                $table->string('approved_by_name')->nullable();
                $table->string('approved_position')->nullable();
                $table->string('document_custodian')->nullable();
                $table->string('delivered_by')->nullable();
                $table->string('received_by')->nullable();
                $table->dateTime('received_at')->nullable();

                $table->string('workflow_status')->default('Uploaded'); // Uploaded, Submitted, Accepted, Reverted, Archived
                $table->string('approval_status')->default('Pending'); // Pending, Approved, Rejected, Needs Revision
                $table->text('review_note')->nullable();

                $table->unsignedBigInteger('submitted_by')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->dateTime('approved_at')->nullable();

                $table->timestamps();

                $table->index('workflow_status');
                $table->index('approval_status');
                $table->index('submitted_by');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transmittals');
    }
};