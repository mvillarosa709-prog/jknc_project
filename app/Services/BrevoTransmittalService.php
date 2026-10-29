<?php

namespace App\Services;

use App\Models\TransmittalRecipient;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class BrevoTransmittalService
{
    public function sendAcknowledgmentRequest(TransmittalRecipient $recipient, string $token): string
    {
        $apiKey = config('ordo.brevo.api_key');
        $senderEmail = config('ordo.brevo.sender_email');

        if (! is_string($apiKey) || $apiKey === '' || ! is_string($senderEmail) || $senderEmail === '') {
            throw new RuntimeException('Brevo is not configured. Set BREVO_API_KEY and BREVO_SENDER_EMAIL.');
        }

        $transmittal = $recipient->transmittal;
        $url = route('acknowledgments.show', ['token' => $token]);
        $attachments = $transmittal->documents()
            ->where('category', 'attachment')
            ->get(['original_name', 'storage_path'])
            ->map(function ($document): ?array {
                $disk = Storage::disk('local');
                if (! $disk->exists($document->storage_path)) {
                    return null;
                }

                return [
                    'name' => basename($document->original_name),
                    'content' => base64_encode($disk->get($document->storage_path)),
                ];
            })
            ->filter()
            ->values()
            ->all();

        $payload = [
            'sender' => [
                'name' => config('ordo.brevo.sender_name'),
                'email' => $senderEmail,
            ],
            'to' => [[
                'email' => $recipient->email,
                'name' => $recipient->name ?: $recipient->email,
            ]],
            'subject' => "Transmittal {$transmittal->tracking_number}: {$transmittal->subject}",
            'htmlContent' => view('emails.transmittal-notice', [
                'recipient' => $recipient,
                'transmittal' => $transmittal,
                'acknowledgmentUrl' => $url,
            ])->render(),
            'headers' => [
                'X-Entity-Ref-ID' => (string) Str::uuid(),
            ],
        ];
        if ($attachments !== []) {
            $payload['attachment'] = $attachments;
        }

        $response = $this->client($apiKey)->post('https://api.brevo.com/v3/smtp/email', $payload)->throw();

        return (string) ($response->json('messageId') ?? '');
    }

    /**
     * Send direct transmittal document email with PDF and attachments via Brevo.
     */
    public function sendTransmittalDocument(
        string $recipientEmail,
        string $subject,
        string $htmlContent,
        string $pdfOutput,
        string $pdfFileName,
        array $fileAttachments = []
    ): string {
        $apiKey = config('ordo.brevo.api_key');
        $senderEmail = config('ordo.brevo.sender_email');

        if (! is_string($apiKey) || $apiKey === '' || ! is_string($senderEmail) || $senderEmail === '') {
            throw new RuntimeException('Brevo is not configured. Set BREVO_API_KEY and BREVO_SENDER_EMAIL.');
        }

        $attachments = [
            [
                'name' => $pdfFileName,
                'content' => base64_encode($pdfOutput),
            ],
        ];

        foreach ($fileAttachments as $file) {
            if (isset($file['path']) && file_exists($file['path'])) {
                $attachments[] = [
                    'name' => $file['name'] ?? basename($file['path']),
                    'content' => base64_encode((string) file_get_contents($file['path'])),
                ];
            }
        }

        $payload = [
            'sender' => [
                'name' => config('ordo.brevo.sender_name') ?: 'JK&C Transmittal Notice',
                'email' => $senderEmail,
            ],
            'to' => [[
                'email' => $recipientEmail,
            ]],
            'replyTo' => [
                'name' => config('ordo.brevo.sender_name') ?: 'JK&C Support',
                'email' => $senderEmail,
            ],
            'subject' => $subject,
            'htmlContent' => $htmlContent,
            'headers' => [
                'X-Entity-Ref-ID' => (string) Str::uuid(),
                'Precedence' => 'bulk',
                'Auto-Submitted' => 'auto-generated',
            ],
            'attachment' => $attachments,
        ];

        $response = $this->client($apiKey)->post('https://api.brevo.com/v3/smtp/email', $payload)->throw();

        return (string) ($response->json('messageId') ?? '');
    }

    private function client(string $apiKey): PendingRequest
    {
        $request = Http::acceptJson()
            ->asJson()
            ->withHeaders(['api-key' => $apiKey])
            ->connectTimeout(5)
            ->timeout(20);

        if (app()->environment('local')) {
            $request->withoutVerifying();
        }

        return $request;
    }
}
