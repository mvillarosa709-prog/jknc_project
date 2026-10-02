<?php

namespace App\Jobs;

use App\Models\EmailMailboxConnection;
use App\Services\Gmail\GmailTransport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncGmailMailboxes implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(public int $connectionId)
    {
        $this->onQueue(config('services.gmail.sync_queue', 'gmail-sync'));
    }

    public function backoff(): array
    {
        return [60, 180];
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('gmail-sync:'.$this->connectionId))
                ->releaseAfter(30)
                ->expireAfter($this->timeout + 60),
        ];
    }

    public function handle(GmailTransport $gmail): void
    {
        if (! GmailTransport::enabled()) {
            return;
        }

        $connection = EmailMailboxConnection::query()
            ->whereKey($this->connectionId)
            ->where('provider', 'gmail')
            ->where('connection_status', 'connected')
            ->whereNotNull('encrypted_refresh_token')
            ->first();
        if (! $connection) return;

        try {
            $gmail->sync($connection);
        } catch (\Throwable $e) {
            $connection->forceFill(['last_error_at' => now(), 'last_error_message' => 'Gmail synchronization failed.'])->save();
            report($e);
            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        EmailMailboxConnection::query()
            ->whereKey($this->connectionId)
            ->update([
                'last_error_at' => now(),
                'last_error_message' => 'Gmail synchronization failed.',
            ]);
    }
}
