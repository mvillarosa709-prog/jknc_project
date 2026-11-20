<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('companies')) {
            Schema::create('companies', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->nullable();
                $table->string('name')->nullable();
                $table->string('code')->nullable();
                $table->string('business_name')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('companies', function (Blueprint $table) {
                if (! Schema::hasColumn('companies', 'tenant_id')) {
                    $table->string('tenant_id')->nullable();
                }
                if (! Schema::hasColumn('companies', 'name')) {
                    $table->string('name')->nullable();
                }
                if (! Schema::hasColumn('companies', 'code')) {
                    $table->string('code')->nullable();
                }
                if (! Schema::hasColumn('companies', 'business_name')) {
                    $table->string('business_name')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
    }
};
