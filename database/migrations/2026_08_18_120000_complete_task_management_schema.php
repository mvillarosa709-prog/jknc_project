<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Task Templates
        |--------------------------------------------------------------------------
        */
        if (! Schema::hasTable('task_templates')) {
            Schema::create('task_templates', function (Blueprint $table) {
                $table->id();

                $table->string('title');
                $table->text('description')->nullable();

                $table->foreignId('parent_template_id')
                    ->nullable()
                    ->constrained('task_templates')
                    ->cascadeOnDelete();

                $table->string('priority', 20)->default('NORMAL');

                $table->unsignedInteger('estimated_duration_minutes')->nullable();

                $table->integer('relative_start_days')->nullable();
                $table->integer('relative_due_days')->nullable();

                $table->text('instructions')->nullable();

                $table->string('status', 20)->default('ACTIVE');
                $table->string('category', 100)->nullable();

                $table->foreignId('created_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamps();

                $table->index('status');
                $table->index('category');
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Upgrade Tasks
        |--------------------------------------------------------------------------
        */
        Schema::table('tasks', function (Blueprint $table) {

            if (! Schema::hasColumn('tasks', 'parent_task_id')) {
                $table->unsignedBigInteger('parent_task_id')
                    ->nullable()
                    ->after('id');
            }

            if (! Schema::hasColumn('tasks', 'sort_order')) {
                $table->unsignedInteger('sort_order')
                    ->default(0)
                    ->after('parent_task_id');
            }

            if (! Schema::hasColumn('tasks', 'start_date')) {
                $table->dateTime('start_date')
                    ->nullable()
                    ->after('due_date');
            }

            if (! Schema::hasColumn('tasks', 'estimated_duration_minutes')) {
                $table->unsignedInteger('estimated_duration_minutes')
                    ->nullable()
                    ->after('start_date');
            }

            if (! Schema::hasColumn('tasks', 'actual_duration_minutes')) {
                $table->unsignedInteger('actual_duration_minutes')
                    ->nullable()
                    ->after('estimated_duration_minutes');
            }

            if (! Schema::hasColumn('tasks', 'recurrence')) {
                $table->string('recurrence', 40)
                    ->nullable()
                    ->after('priority');
            }

            if (! Schema::hasColumn('tasks', 'recurrence_config')) {
                $table->json('recurrence_config')
                    ->nullable()
                    ->after('recurrence');
            }

            if (! Schema::hasColumn('tasks', 'reminder_type')) {
                $table->string('reminder_type', 40)
                    ->nullable()
                    ->after('recurrence_config');
            }

            if (! Schema::hasColumn('tasks', 'reminder_value')) {
                $table->unsignedInteger('reminder_value')
                    ->nullable()
                    ->after('reminder_type');
            }

            if (! Schema::hasColumn('tasks', 'completed_at')) {
                $table->dateTime('completed_at')
                    ->nullable()
                    ->after('reminder_value');
            }

            if (! Schema::hasColumn('tasks', 'cancelled_at')) {
                $table->dateTime('cancelled_at')
                    ->nullable()
                    ->after('completed_at');
            }

            if (! Schema::hasColumn('tasks', 'task_template_id')) {
                $table->unsignedBigInteger('task_template_id')
                    ->nullable()
                    ->after('cancelled_at');
            }

            if (! Schema::hasColumn('tasks', 'assigned_to_user_id')) {
                $table->unsignedBigInteger('assigned_to_user_id')
                    ->nullable()
                    ->after('owner');
            }

            if (! Schema::hasColumn('tasks', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        /*
        |--------------------------------------------------------------------------
        | Task Foreign Keys
        |--------------------------------------------------------------------------
        */
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreign('parent_task_id')
                ->references('id')
                ->on('tasks')
                ->nullOnDelete();

            $table->foreign('task_template_id')
                ->references('id')
                ->on('task_templates')
                ->nullOnDelete();

            $table->foreign('assigned_to_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        /*
        |--------------------------------------------------------------------------
        | Task Assignments
        |--------------------------------------------------------------------------
        */
        if (! Schema::hasTable('task_assignments')) {
            Schema::create('task_assignments', function (Blueprint $table) {
                $table->id();

                $table->foreignId('task_id')
                    ->constrained('tasks')
                    ->cascadeOnDelete();

                $table->foreignId('user_id')
                    ->constrained('users')
                    ->cascadeOnDelete();

                $table->string('role', 30)->default('assignee');

                $table->timestamps();

                $table->unique(
                    ['task_id', 'user_id', 'role'],
                    'task_assignments_task_user_role_unique'
                );
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Task Dependencies
        |--------------------------------------------------------------------------
        */
        if (! Schema::hasTable('task_dependencies')) {
            Schema::create('task_dependencies', function (Blueprint $table) {
                $table->id();

                $table->foreignId('blocking_task_id')
                    ->constrained('tasks')
                    ->cascadeOnDelete();

                $table->foreignId('dependent_task_id')
                    ->constrained('tasks')
                    ->cascadeOnDelete();

                $table->string('dependency_type', 40)->default('BLOCKS');
                $table->text('notes')->nullable();

                $table->timestamps();

                $table->unique(
                    ['blocking_task_id', 'dependent_task_id'],
                    'task_dependencies_blocking_dependent_unique'
                );

                $table->index(
                    ['dependent_task_id', 'dependency_type'],
                    'task_dependencies_dependent_type_index'
                );
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Task Timers
        |--------------------------------------------------------------------------
        */
        if (! Schema::hasTable('task_timers')) {
            Schema::create('task_timers', function (Blueprint $table) {
                $table->id();

                $table->foreignId('task_id')
                    ->constrained('tasks')
                    ->cascadeOnDelete();

                $table->foreignId('user_id')
                    ->constrained('users')
                    ->cascadeOnDelete();

                $table->dateTime('started_at');
                $table->dateTime('stopped_at')->nullable();
                $table->dateTime('paused_at')->nullable();

                $table->unsignedInteger('paused_seconds')->default(0);

                $table->text('notes')->nullable();

                $table->timestamps();

                $table->index(['task_id', 'user_id']);
                $table->index(['started_at', 'stopped_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('task_timers');
        Schema::dropIfExists('task_dependencies');
        Schema::dropIfExists('task_assignments');

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['parent_task_id']);
            $table->dropForeign(['task_template_id']);
            $table->dropForeign(['assigned_to_user_id']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn([
                'parent_task_id',
                'sort_order',
                'start_date',
                'estimated_duration_minutes',
                'actual_duration_minutes',
                'recurrence',
                'recurrence_config',
                'reminder_type',
                'reminder_value',
                'completed_at',
                'cancelled_at',
                'task_template_id',
                'assigned_to_user_id',
                'deleted_at',
            ]);
        });

        Schema::dropIfExists('task_templates');
    }
};
