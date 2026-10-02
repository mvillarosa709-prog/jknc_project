<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessMessagingWebhook;
use App\Models\MessagingWebhookEvent;
use App\Services\Messaging\MessengerWebhookPayload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MessengerWebhookController extends Controller
{
    private function configured(): void
    {
        abort_unless(config('messaging.messenger.enabled')
            && filled(config('messaging.messenger.app_secret'))
            && filled(config('messaging.messenger.verify_token'))
            && filled(config('messaging.messenger.page_id')), 503);
        $driver = config('queue.connections.'.config('messaging.messenger.queue_connection').'.driver');
        abort_unless(in_array($driver, ['database', 'redis', 'sqs', 'beanstalkd'], true), 503);
    }

    public function verify(Request $request)
    {
        $this->configured();
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $token = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));
        $valid = $mode === 'subscribe' && is_string($token)
            && hash_equals((string) config('messaging.messenger.verify_token'), $token)
            && is_string($challenge) && $challenge !== '' && strlen($challenge) <= 1024;
        if (! $valid) {
            Log::notice('messenger.verification_rejected');
            abort(403);
        }

        return response($challenge, 200)->header('Content-Type', 'text/plain');
    }

    public function receive(Request $request)
    {
        $this->configured();
        $raw = $request->getContent();
        abort_if(strlen($raw) > 1048576, 413);
        $provided = $request->header('X-Hub-Signature-256');
        $signature = 'sha256='.hash_hmac('sha256', $raw, (string) config('messaging.messenger.app_secret'));
        if (! is_string($provided) || ! preg_match('/^sha256=[a-f0-9]{64}$/D', $provided)
            || ! hash_equals($signature, $provided)) {
            Log::notice('messenger.signature_rejected');
            abort(403);
        }
        try {
            $payload = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            abort(400);
        }
        abort_unless(is_array($payload), 400);
        try {
            $payload = app(MessengerWebhookPayload::class)->normalize($payload, (string) config('messaging.messenger.page_id'));
        } catch (\UnexpectedValueException) {
            Log::notice('messenger.account_rejected');
            abort(403);
        } catch (\InvalidArgumentException) {
            Log::notice('messenger.payload_rejected');
            abort(400);
        }
        if (! $payload['entry']) {
            return response('EVENT_RECEIVED', 200)->header('Content-Type', 'text/plain');
        }
        $event = null;
        try {
            $event = MessagingWebhookEvent::firstOrCreate(
                ['provider' => 'messenger', 'fingerprint' => hash('sha256', $raw)],
                ['payload' => $payload]
            );
            if ($event->status !== 'processed') {
                ProcessMessagingWebhook::dispatch($event->id)->afterCommit();
            } else {
                Log::info('messenger.duplicate_event_ignored', ['event_id' => $event->id]);
            }
        } catch (\Throwable) {
            Log::error('messenger.accept_failed', ['event_id' => $event?->id]);
            abort(503);
        }

        return response('EVENT_RECEIVED', 200)->header('Content-Type', 'text/plain');
    }
}
