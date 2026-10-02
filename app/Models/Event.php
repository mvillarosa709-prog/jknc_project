<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
   protected $fillable = [
    'title',
    'related_account',
    'event_type',
    'from',
    'to',
    'venue',
    'mode',
    'participants',
    'role',
    'status',
    'estimated_hours',
    'actual_hours',
    'duration_minutes',
    'company_id',
    'contact_id',
    'deal_id',
    'project_id',
    'related_to',
    'host',
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
            'duration_minutes' => 'integer',
            'include_in_client_report' => 'boolean',
            'calendar_synced_at' => 'datetime',
        ];
    }

    public function preparedBy()
    {
        return $this->belongsTo(User::class, 'prepared_by_user_id');
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
}
