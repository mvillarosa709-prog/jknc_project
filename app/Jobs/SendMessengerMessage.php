<?php

namespace App\Jobs;

use App\Models\MessageEntry;
use App\Services\Messaging\MessengerOutbound;
use App\Services\Messaging\MessengerProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendMessengerMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 30;
    public bool $failOnTimeout = true;

    public function __construct(public int $entryId)
    {
        $this->onQueue(config('messaging.messenger.outbound_queue', 'messenger-outbound'));
        $this->onConnection(config('messaging.messenger.queue_connection', 'database'));
    }

    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function handle(MessengerProvider $provider): void
    {
        // Disabled jobs are consumed, never held for surprise delivery after re-enabling.
        if (! MessengerOutbound::enabled()) {
            app(MessengerOutbound::class)->cancel($this->entryId);
            return;
        }
        try {
            if ($entry = MessageEntry::find($this->entryId)) {
                $provider->send($entry);
            }
        } catch (\Throwable) {
            // Never serialize HTTP exceptions (headers/body) or SQL bindings into failed_jobs.
            throw new \RuntimeException('Messenger send deferred or unavailable; inspect the outbound attempt.');
        }
    }

    public function failed(?\Throwable $exception): void
    {
        // The original exception is intentionally neither logged nor persisted here.
        app(MessengerOutbound::class)->abandon($this->entryId);
    }
}
