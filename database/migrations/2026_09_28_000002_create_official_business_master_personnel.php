<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('official_business_trip_personnel', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('official_business_trip_id')->constrained('official_business_trips', indexName: 'ob_master_personnel_trip_fk')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees', indexName: 'ob_master_personnel_employee_fk')->nullOnDelete();
            $table->string('employee_name_snapshot');
            $table->string('position_snapshot')->nullable();
            $table->string('department_snapshot')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['official_business_trip_id', 'employee_id'], 'ob_master_personnel_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('official_business_trip_personnel');
    }
};
