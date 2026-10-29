<?php

namespace App\Providers;

use App\Models\Call;
use App\Models\Event;
use App\Models\Meeting;
use App\Models\MessageConversation;
use App\Models\OfficialBusinessTrip;
use App\Models\Task;
use App\Models\TaskTimer;
use App\Models\Transmittal;
use App\Observers\ActivityAuditObserver;
use App\Observers\TaskTimerAuditObserver;
use App\Policies\TransmittalPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Task::observe(ActivityAuditObserver::class);
        Event::observe(ActivityAuditObserver::class);
        Call::observe(ActivityAuditObserver::class);
        Meeting::observe(ActivityAuditObserver::class);
        MessageConversation::observe(ActivityAuditObserver::class);
        OfficialBusinessTrip::observe(ActivityAuditObserver::class);
        TaskTimer::observe(TaskTimerAuditObserver::class);

        Gate::policy(Transmittal::class, TransmittalPolicy::class);

        if (app()->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
