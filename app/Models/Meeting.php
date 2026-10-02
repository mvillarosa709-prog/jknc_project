<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Meeting extends Model
{
    protected $fillable = [
        'title',
        'meeting_type',
        'related_account',
        'related_project',
        'mode',
        'owner',
        'date',
        'time',
        'duration',
        'actual_duration',
        'duration_minutes',
        'location',
        'attendees',
        'company_id',
        'contact_id',
        'deal_id',
        'project_id',
        'related_to',
        'status',
        'description',
        'has_video',
        'has_audio',
        'has_transcript',
        'has_minutes',
        'video_path',
        'audio_path',
        'client_report_description',
        'include_in_client_report',
        'internal_participant_user_ids',
        'external_attendees',
        'structured_minutes',
        'recording_metadata',
        'transcript_metadata',
        'files_metadata',
        'calendar_provider',
        'external_calendar_event_id',
        'conference_url',
        'calendar_sync_status',
        'calendar_synced_at',
        'calendar_sync_error',
        'notify_external_attendees',
        'external_notification_recipient_ids',
    ];

    protected function casts(): array
    {
        return [
            'include_in_client_report' => 'boolean',
            'duration_minutes' => 'integer',
            'internal_participant_user_ids' => 'array',
            'external_attendees' => 'array',
            'structured_minutes' => 'array',
            'finalized_minutes_at' => 'datetime',
            'recording_metadata' => 'array',
            'transcript_metadata' => 'array',
            'files_metadata' => 'array',
            'calendar_synced_at' => 'datetime',
            'notify_external_attendees' => 'boolean',
            'external_notification_recipient_ids' => 'array',
        ];
    }

    protected $appends = [
        'video_url',
        'audio_url',
    ];

    public function getVideoUrlAttribute()
    {
        if (!$this->video_path) {
            return null;
        }

        return str_starts_with($this->video_path, 'http')
            ? $this->video_path
            : url($this->video_path);
    }

    public function getAudioUrlAttribute()
    {
        if (!$this->audio_path) {
            return null;
        }

        return str_starts_with($this->audio_path, 'http')
            ? $this->audio_path
            : url($this->audio_path);
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

    public function finalizedMinutesBy()
    {
        return $this->belongsTo(User::class, 'finalized_minutes_by_user_id');
    }

    public function actionItems()
    {
        return $this->hasMany(MeetingActionItem::class);
    }

    public function minutesVersions()
    {
        return $this->hasMany(MeetingMinutesVersion::class)->orderByDesc('version_number');
    }
}
