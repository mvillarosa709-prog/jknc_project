<?php

namespace App\Jobs;

use App\Models\EmailActivity;
use App\Services\Gmail\GmailTransport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendGmailEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 90;
    public bool $failOnTimeout = true;

    public function __construct(public int $emailId)
    {
        $this->onQueue(config('services.gmail.send_queue', 'gmail-send'));
    }

    public function handle(GmailTransport $gmail): void
    {
        if (! GmailTransport::enabled()) {
            return;
        }

        $email = EmailActivity::query()->with(['mailboxConnection', 'attachments'])->find($this->emailId);
        if (! $email || $email->delivery_status !== 'Pending') return;

        try {
            $sent = $gmail->send($email);
            $email->forceFill([
                'provider_message_id' => $sent['id'] ?? $email->provider_message_id,
                'thread_id' => $sent['threadId'] ?? $email->thread_id,
                'delivery_status' => 'Sent',
                'sent_at' => now(),
                'provider_error' => null,
            ])->save();
        } catch (\Throwable $e) {
            $email->forceFill(['delivery_status' => 'Failed', 'provider_error' => 'Gmail delivery failed.'])->save();
            report($e);
        }
    }

    public function failed(Throwable $exception): void
    {
        EmailActivity::query()
            ->whereKey($this->emailId)
            ->where('delivery_status', 'Pending')
            ->update([
                'delivery_status' => 'Failed',
                'provider_error' => 'Gmail delivery failed.',
            ]);
    }
}
