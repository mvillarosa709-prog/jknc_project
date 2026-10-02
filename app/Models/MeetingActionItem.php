<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeetingActionItem extends Model
{
    protected $fillable = [
        'meeting_id',
        'description',
        'assigned_to_user_id',
        'due_date',
        'status',
        'task_id',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return ['due_date' => 'date'];
    }

    public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
