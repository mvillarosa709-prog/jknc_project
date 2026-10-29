<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        channels: __DIR__ . '/../routes/channels.php',
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_AWS_ELB
        );

        $middleware->validateCsrfTokens(except: [
            'finance/supplier/completion/*',
        ]);

        $middleware->alias([
            'prevent-back-history' => \App\Http\Middleware\PreventBackHistory::class,
            'human-capital.module' => \App\Http\Middleware\HumanCapitalModuleAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('townhall:archive-expired')->everyMinute();
        $schedule->command('reports:generate-scheduled')->everyMinute();
        $schedule->command('natgov:sync-reminders')->everyMinute();
        $schedule->command('activities:dispatch-reminders')->everyMinute();
        $schedule->command('activities:generate-recurring')->hourly();
        $schedule->call(function (): void {
            if (! \App\Services\Gmail\GmailTransport::enabled()) {
                return;
            }

            \App\Models\EmailMailboxConnection::query()
                ->where('provider', 'gmail')
                ->where('connection_status', 'connected')
                ->whereNotNull('encrypted_refresh_token')
                ->pluck('id')
                ->each(fn (int $id) => \App\Jobs\SyncGmailMailboxes::dispatch($id));
        })->name('gmail:sync-mailboxes')->withoutOverlapping(1)->everyTwoMinutes();
    })
    ->create();
