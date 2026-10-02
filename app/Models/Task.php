<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'parent_task_id',
        'sort_order',
        'name',
        'due_date',
        'start_date',
        'estimated_duration_minutes',
        'actual_duration_minutes',
        'manual_actual_duration_minutes',
        'status',
        'priority',
        'recurrence',
        'recurrence_config',
        'reminder_type',
        'reminder_value',
        'completed_at',
        'cancelled_at',
        'task_template_id',
        'company_id',
        'contact_id',
        'deal_id',
        'project_id',
        'related_to',
        'owner',
        'assigned_to_user_id',
        'description',
        'client_report_description',
        'include_in_client_report',
        'calendar_provider',
        'external_calendar_event_id',
        'calendar_sync_status',
        'calendar_synced_at',
        'calendar_sync_error',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'estimated_duration_minutes' => 'integer',
            'actual_duration_minutes' => 'integer',
            'manual_actual_duration_minutes' => 'integer',
            'recurrence_config' => 'array',
            'reminder_value' => 'integer',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'include_in_client_report' => 'boolean',
            'calendar_synced_at' => 'datetime',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function contactRecord()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function notes()
    {
        return $this->morphMany(Note::class, 'noteable');
    }

    public function parentTask()
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    public function subtasks()
    {
        return $this->hasMany(Task::class, 'parent_task_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function primaryAssignee()
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function preparedBy()
    {
        return $this->belongsTo(User::class, 'prepared_by_user_id');
    }

    public function template()
    {
        return $this->belongsTo(TaskTemplate::class, 'task_template_id');
    }

    public function timers()
    {
        return $this->hasMany(TaskTimer::class);
    }

    public function assignees()
    {
        return $this->belongsToMany(
            User::class,
            'task_assignments',
            'task_id',
            'user_id'
        )
        ->withPivot('role')
        ->withTimestamps();
    }

    /**
     * Tasks that must be completed before this task.
     */
    public function blockingTasks()
    {
        return $this->belongsToMany(
            Task::class,
            'task_dependencies',
            'dependent_task_id',
            'blocking_task_id'
        )
        ->withPivot('dependency_type', 'notes')
        ->withTimestamps();
    }

    /**
     * Tasks that this task is blocking.
     */
    public function dependentTasks()
    {
        return $this->belongsToMany(
            Task::class,
            'task_dependencies',
            'blocking_task_id',
            'dependent_task_id'
        )
        ->withPivot('dependency_type', 'notes')
        ->withTimestamps();
    }
}
