<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('official_business_suggestions', function (Blueprint $table): void {
            $table->id();
            $table->string('field_type', 30);
            $table->string('normalized_value');
            $table->text('display_value');
            // A non-null context key makes logical uniqueness reliable across MySQL and SQLite.
            $table->string('context_key', 96)->default('');
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('deal_id')->nullable()->constrained('deals')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->unsignedBigInteger('frequency_count')->default(1);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['field_type', 'normalized_value', 'context_key'],
                'ob_suggestion_logical_unique'
            );
            $table->index(['field_type', 'frequency_count', 'last_used_at'], 'ob_suggestion_rank_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('official_business_suggestions');
    }
};
