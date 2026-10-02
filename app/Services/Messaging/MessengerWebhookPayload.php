<?php

namespace App\Services\Messaging;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use UnexpectedValueException;

class MessengerWebhookPayload
{
    /** Retain only normalized routing/receipt evidence, never bodies, media URLs or arbitrary metadata. */
    public function diagnosticSummary(array $payload, string $page): array
    {
        $summary = [];
        foreach ($this->normalize($payload, $page)['entry'] as $batch) {
            foreach ($batch['messaging'] as $event) {
                $echo = ($event['message']['is_echo'] ?? false) === true;
                foreach (['message', 'delivery', 'read'] as $kind) {
                    if (! isset($event[$kind])) continue;
                    $summary[] = [
                        'kind' => $kind === 'message' && $echo ? 'echo' : $kind,
                        'account_id' => $page,
                        'peer_id' => $event[$echo ? 'recipient' : 'sender']['id'],
                        'timestamp_ms' => $event['timestamp'] ?? null,
                        'message_ids' => $kind === 'message' ? [$event['message']['mid']] : ($event[$kind]['mids'] ?? []),
                        'watermark_ms' => $event[$kind]['watermark'] ?? null,
                    ];
                }
            }
        }
        return ['diagnostic_version' => 1, 'events' => $summary];
    }

    /** Keep only fields consumed by the adapter; never queue arbitrary provider metadata. */
    public function normalize(array $payload, string $page): array
    {
        $this->require(($payload['object'] ?? null) === 'page'
            && is_array($payload['entry'] ?? null) && array_is_list($payload['entry'])
            && count($payload['entry']) <= 1000);
        $batches = [];
        foreach ($payload['entry'] as $batch) {
            $this->require(is_array($batch));
            if ($this->identity($batch['id'] ?? null) !== $page) {
                throw new UnexpectedValueException('Unexpected account.');
            }
            $events = $batch['messaging'] ?? [];
            $this->require(is_array($events) && array_is_list($events) && count($events) <= 1000);
            $normalized = [];
            foreach ($events as $event) {
                $this->require(is_array($event));
                $supported = array_intersect(['message', 'delivery', 'read'], array_keys($event));
                if (! $supported) {
                    Log::info('messenger.unsupported_event');
                    continue;
                }
                foreach ($supported as $key) {
                    $this->require(is_array($event[$key]));
                }
                $this->require(is_array($event['sender'] ?? null) && is_array($event['recipient'] ?? null));
                $item = ['sender' => ['id' => $this->identity($event['sender']['id'] ?? null)],
                    'recipient' => ['id' => $this->identity($event['recipient']['id'] ?? null)]];
                if (isset($event['timestamp'])) {
                    $item['timestamp'] = $this->milliseconds($event['timestamp']);
                }
                if (isset($event['message'])) {
                    $message = $event['message'];
                    $this->require(is_string($message['mid'] ?? null) && $message['mid'] !== '' && strlen($message['mid']) <= 255);
                    $item['message'] = ['mid' => $message['mid']];
                    // Only our opaque correlation marker survives; never arbitrary provider metadata.
                    if (is_string($message['metadata'] ?? null)
                        && preg_match('/^jknc:[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D', $message['metadata'])) {
                        $item['message']['metadata'] = $message['metadata'];
                    }
                    if (isset($message['text'])) {
                        $this->require(is_string($message['text']) && strlen($message['text']) <= 65535);
                        $item['message']['text'] = $message['text'];
                    }
                    if (isset($message['is_echo'])) {
                        $this->require(is_bool($message['is_echo']));
                        $item['message']['is_echo'] = $message['is_echo'];
                    }
                    $attachments = $message['attachments'] ?? [];
                    $this->require(is_array($attachments) && array_is_list($attachments) && count($attachments) <= 100);
                    foreach ($attachments as $media) {
                        $this->require(is_array($media) && is_string($media['type'] ?? null)
                            && strlen($media['type']) <= 40 && is_array($media['payload'] ?? []));
                        $reference = [];
                        foreach (['url' => 8192, 'attachment_id' => 255] as $key => $max) {
                            if (isset($media['payload'][$key])) {
                                $value = $media['payload'][$key];
                                $this->require(is_string($value) && strlen($value) <= $max);
                                $reference[$key] = $value;
                            }
                        }
                        $item['message']['attachments'][] = ['type' => $media['type'], 'payload' => $reference];
                    }
                }
                foreach (['delivery', 'read'] as $kind) {
                    if (! isset($event[$kind])) {
                        continue;
                    }
                    $item[$kind] = [];
                    if (isset($event[$kind]['watermark'])) {
                        $item[$kind]['watermark'] = $this->milliseconds($event[$kind]['watermark']);
                    }
                    if ($kind === 'delivery' && isset($event[$kind]['mids'])) {
                        $mids = $event[$kind]['mids'];
                        $this->require(is_array($mids) && array_is_list($mids) && count($mids) <= 1000);
                        foreach ($mids as $mid) {
                            $this->require(is_string($mid) && $mid !== '' && strlen($mid) <= 255);
                        }
                        $item[$kind]['mids'] = array_values(array_unique($mids));
                    }
                    $this->require(! empty($item[$kind]));
                }
                $echo = ($item['message']['is_echo'] ?? false) === true;
                $this->require($item[$echo ? 'sender' : 'recipient']['id'] === $page);
                $normalized[] = $item;
            }
            if ($normalized) {
                $batches[] = ['id' => $page, 'messaging' => $normalized];
            }
        }

        return ['object' => 'page', 'entry' => $batches];
    }

    private function identity(mixed $value): string
    {
        $this->require(is_string($value) || is_int($value));
        $value = (string) $value;
        $this->require((bool) preg_match('/^[0-9]{1,80}$/D', $value));

        return $value;
    }

    private function milliseconds(mixed $value): int
    {
        $this->require(is_int($value) || (is_string($value) && ctype_digit($value)));
        // Canonical tables use MySQL TIMESTAMP. Reject out-of-range values instead of poisoning retries.
        $this->require($value > 0 && $value <= 2147483647000);

        return (int) $value;
    }

    private function require(bool $condition): void
    {
        if (! $condition) {
            throw new InvalidArgumentException('Invalid Messenger event shape.');
        }
    }
}
