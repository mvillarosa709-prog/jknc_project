<?php

use App\Services\ResumableTransmittalUploadService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('ordo:uploads:cleanup', function (ResumableTransmittalUploadService $uploads) {
    $count = $uploads->cleanupExpired();
    $this->info("Removed {$count} expired resumable upload(s).");
})->purpose('Remove expired private resumable uploads');

Schedule::command('ordo:uploads:cleanup')->daily()->withoutOverlapping();
