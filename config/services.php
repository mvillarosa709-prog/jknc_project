<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google_calendar' => [
        'calendar_id' => env('GOOGLE_CALENDAR_ID'),
        'access_token' => env('GOOGLE_CALENDAR_ACCESS_TOKEN'),
        'bridge_url' => env('GOOGLE_CALENDAR_BRIDGE_URL'),
        'bridge_secret' => env('GOOGLE_CALENDAR_BRIDGE_SECRET'),
        'api_base' => env('GOOGLE_CALENDAR_API_BASE', 'https://www.googleapis.com/calendar/v3'),
        'timezone' => env('GOOGLE_CALENDAR_TIMEZONE', env('APP_TIMEZONE', 'Asia/Manila')),
        'timeout' => (int) env('GOOGLE_CALENDAR_TIMEOUT', 15),
    ],

    'gmail' => [
        'enabled' => (bool) env('GMAIL_ENABLED', false),
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
        'api_base' => 'https://gmail.googleapis.com/gmail/v1',
        'oauth_base' => 'https://oauth2.googleapis.com',
        'authorization_endpoint' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'initial_sync_days' => (int) env('GMAIL_INITIAL_SYNC_DAYS', 14),
        'sync_limit' => (int) env('GMAIL_SYNC_LIMIT', 50),
        'initial_sync_max_messages' => (int) env('GMAIL_INITIAL_SYNC_MAX_MESSAGES', 500),
        'sync_queue' => env('GMAIL_SYNC_QUEUE', 'gmail-sync'),
        'send_queue' => env('GMAIL_SEND_QUEUE', 'gmail-send'),
        'attachment_max_kb' => (int) env('GMAIL_ATTACHMENT_MAX_KB', 10240),
        'attachment_mimes' => array_values(array_filter(array_map('trim', explode(',', env('GMAIL_ATTACHMENT_MIMES', 'application/pdf,image/jpeg,image/png,text/plain,application/zip,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'))))),
        'scopes' => [
            'https://www.googleapis.com/auth/gmail.readonly',
            'https://www.googleapis.com/auth/gmail.send',
        ],
    ],

];
