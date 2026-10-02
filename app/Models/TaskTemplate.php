<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskTemplate extends Model
{
    protected $fillable = [
        'title',
        'description',
        'parent_template_id',
        'priority',
        'estimated_duration_minutes',
        'relative_start_days',
        'relative_due_days',
        'instructions',
        'status',
        'category',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'estimated_duration_minutes' => 'integer',
            'relative_start_days' => 'integer',
            'relative_due_days' => 'integer',
        ];
    }

    public function parentTemplate()
    {
        return $this->belongsTo(TaskTemplate::class, 'parent_template_id');
    }

    public function childTemplates()
    {
        return $this->hasMany(TaskTemplate::class, 'parent_template_id');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
