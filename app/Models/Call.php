<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Call extends Model
{
    protected $fillable = [
        'contact',
        'to',
        'from',
        'type',
        'start_time',
        'start_hour',
        'duration',
        'duration_minutes',
        'company_id',
        'contact_id',
        'deal_id',
        'project_id',
        'related_to',
        'owner',
        'completed',
        'purpose',
        'agenda',
        'audio_path',
        'client_report_description',
        'include_in_client_report',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'include_in_client_report' => 'boolean',
        ];
    }

    protected $appends = ['audio_url'];

    public function getAudioUrlAttribute()
    {
        if (!$this->audio_path) return null;
        return str_starts_with($this->audio_path, 'http') ? $this->audio_path : url($this->audio_path);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function preparedBy()
    {
        return $this->belongsTo(User::class, 'prepared_by_user_id');
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
