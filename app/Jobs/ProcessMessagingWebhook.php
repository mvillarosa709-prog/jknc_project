<?php

namespace App\Jobs;

use App\Models\MessagingWebhookEvent;
use App\Services\Messaging\MessengerProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessMessagingWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 30;

    public function __construct(public int $eventId)
    {
        $this->onQueue(config('messaging.messenger.queue', 'messaging'));
        $this->onConnection(config('messaging.messenger.queue_connection', 'database'));
    }

    public function backoff(): array
    {
        return [5, 30, 120, 600];
    }

    public function handle(MessengerProvider $provider): void
    {
        if (! config('messaging.messenger.enabled')) {
            $this->release(60);
            return;
        }
        try {
            DB::transaction(function () use ($provider) {
                $event = MessagingWebhookEvent::lockForUpdate()->findOrFail($this->eventId);
                if ($event->status === 'processed') {
                    Log::info('messenger.duplicate_job_ignored', ['event_id' => $event->id]);
                    return;
                }
                $event->attempts++;
                $summary = app(\App\Services\Messaging\MessengerWebhookPayload::class)
                    ->diagnosticSummary($event->payload, (string) config('messaging.messenger.page_id'));
                $provider->ingest($event->payload);
                $event->update(['status' => 'processed', 'payload' => $summary, 'error_code' => null, 'processed_at' => now()]);
            }, 3);
        } catch (\Throwable) {
            MessagingWebhookEvent::whereKey($this->eventId)->where('status', '!=', 'processed')
                ->update(['status' => 'failed', 'error_code' => 'processing_failed', 'attempts' => DB::raw('attempts + 1')]);
            Log::warning('Messenger webhook processing failed', ['event_id' => $this->eventId]);
            // Do not serialize payloads, SQL bindings or secrets into failed_jobs.
            throw new \RuntimeException('Messenger webhook processing failed; inspect event status.');
        }
    }
}
