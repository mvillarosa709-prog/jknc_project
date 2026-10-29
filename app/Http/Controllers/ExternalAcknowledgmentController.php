<?php

namespace App\Http\Controllers;

use App\Models\ExternalAcknowledgment;
use App\Models\ProofOfTransmission;
use App\Models\TransmittalHistory;
use App\Models\TransmittalReceipt;
use App\Models\TransmittalRecipient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ExternalAcknowledgmentController extends Controller
{
    public function show(string $token): View
    {
        $recipient = $this->recipientFor($token);
        $recipient->transmittal->load(['documents', 'items.document', 'recipients']);
        $sentProof = $recipient->proofs()
            ->where('status', 'sent')
            ->latest('transmitted_at')
            ->first();

        if ($sentProof && ! $recipient->receipt) {
            Cache::lock("ordo:acknowledgment:view:{$sentProof->transaction_reference}", 30)
                ->block(5, function () use ($recipient, $sentProof): void {
                    DB::transaction(function () use ($recipient, $sentProof): void {
                        $alreadyViewed = $recipient->proofs()
                            ->where('transaction_reference', $sentProof->transaction_reference)
                            ->where('status', 'viewed')
                            ->exists();

                        if ($alreadyViewed) {
                            return;
                        }

                        $viewedAt = now();
                        $recipient->proofs()->create([
                            'transmittal_id' => $recipient->transmittal_id,
                            'channel' => 'email',
                            'status' => 'viewed',
                            'transaction_reference' => $sentProof->transaction_reference,
                            'transmitted_at' => $sentProof->transmitted_at,
                            'provider_metadata' => ['viewed_at' => $viewedAt->toIso8601String()],
                        ]);

                        ExternalAcknowledgment::query()->create([
                            'transmittal_id' => $recipient->transmittal_id,
                            'recipient_id' => $recipient->id,
                            'event' => 'viewed',
                            'details' => ['transaction_reference' => $sentProof->transaction_reference],
                            'occurred_at' => $viewedAt,
                        ]);

                        TransmittalHistory::query()->create([
                            'transmittal_id' => $recipient->transmittal_id,
                            'action' => 'recipient_viewed',
                            'description' => "Acknowledgment page viewed by {$recipient->email}.",
                            'metadata' => ['transaction_reference' => $sentProof->transaction_reference],
                            'created_at' => $viewedAt,
                        ]);
                    });
                });
        }

        return view('acknowledgments.show', compact('recipient'));
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $validated = $request->validate([
            'signed_name' => ['required', 'string', 'max:160'],
            'comments' => ['nullable', 'string', 'max:2000'],
        ]);

        $recipient = $this->recipientFor($token);
        $clientIp = (string) $request->ip();
        $autoLocation = $this->resolveLocationFromIp($clientIp);

        Cache::lock("ordo:acknowledgment:{$recipient->id}")->block(10, function () use ($request, $recipient, $validated, $autoLocation, $clientIp): void {
            DB::transaction(function () use ($request, $recipient, $validated, $autoLocation, $clientIp): void {
                $locked = TransmittalRecipient::query()
                    ->with('transmittal')
                    ->lockForUpdate()
                    ->findOrFail($recipient->id);

                if ($locked->receipt()->exists()) {
                    abort(409, 'This acknowledgment has already been submitted.');
                }

                $receivedAt = now();
                $receiptCode = 'ORDO-TRR-'.strtoupper(Str::random(18));
                $nextReceiptId = ((int) TransmittalReceipt::query()->max('id')) + 1;
                $receiptNo = 'TRR-' . str_pad((string) $nextReceiptId, 5, '0', STR_PAD_LEFT);
                $receipt = TransmittalReceipt::query()->create([
                    'transmittal_id' => $locked->transmittal_id,
                    'recipient_id' => $locked->id,
                    'receipt_no' => $receiptNo,
                    'receipt_date' => $receivedAt->toDateString(),
                    'signed_name' => $validated['signed_name'],
                    'received_by' => $validated['signed_name'],
                    'comments' => $validated['comments'] ?? null,
                    'receipt_code' => $receiptCode,
                    'snapshot' => [
                        'tracking_number' => $locked->transmittal->tracking_number,
                        'subject' => $locked->transmittal->subject,
                        'recipient_email' => $locked->email,
                        'signed_name' => $validated['signed_name'],
                        'location' => $autoLocation,
                        'comments' => $validated['comments'] ?? null,
                    ],
                    'received_at' => $receivedAt,
                    'ip_address' => $clientIp,
                    'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                ]);

                ExternalAcknowledgment::query()->create([
                    'transmittal_id' => $locked->transmittal_id,
                    'recipient_id' => $locked->id,
                    'receipt_id' => $receipt->id,
                    'event' => 'acknowledged',
                    'details' => [
                        'receipt_code' => $receipt->receipt_code,
                        'signed_name' => $validated['signed_name'],
                        'location' => $autoLocation,
                        'ip_address' => $clientIp,
                        'device' => substr((string) $request->userAgent(), 0, 1000),
                    ],
                    'occurred_at' => $receivedAt,
                ]);

                $sentProof = $locked->proofs()->where('status', 'sent')->latest('transmitted_at')->first();
                if ($sentProof) {
                    ProofOfTransmission::query()->create([
                        'transmittal_id' => $locked->transmittal_id,
                        'recipient_id' => $locked->id,
                        'channel' => 'email',
                        'status' => 'acknowledged',
                        'transaction_reference' => $sentProof->transaction_reference,
                        'acknowledged_at' => $receivedAt,
                        'transmitted_at' => $sentProof->transmitted_at,
                        'provider_metadata' => [
                            'receipt_code' => $receipt->receipt_code,
                            'acknowledged_by' => $validated['signed_name'],
                            'location' => $autoLocation,
                            'device' => substr((string) $request->userAgent(), 0, 1000),
                            'ip_address' => $request->ip(),
                            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                        ],
                    ]);
                }

                TransmittalHistory::query()->create([
                    'transmittal_id' => $locked->transmittal_id,
                    'actor_id' => null,
                    'action' => 'acknowledged',
                    'description' => "Acknowledgment received from {$locked->email}.",
                    'metadata' => ['receipt_code' => $receipt->receipt_code],
                    'created_at' => $receivedAt,
                ]);

                if ($locked->transmittal->recipients()->whereDoesntHave('receipt')->doesntExist()) {
                    $locked->transmittal->forceFill(['status' => 'acknowledged'])->save();
                }
            });
        });

        return redirect()->route('acknowledgments.show', ['token' => $token])
            ->with('acknowledged', 'Your electronic acknowledgment has been recorded.');
    }

    private function recipientFor(string $token): TransmittalRecipient
    {
        abort_if(strlen($token) < 32 || strlen($token) > 100, 404);

        $recipient = TransmittalRecipient::query()
            ->with(['transmittal', 'receipt'])
            ->where('acknowledgment_token_hash', hash('sha256', $token))
            ->where('acknowledgment_expires_at', '>', now())
            ->whereNull('acknowledgment_revoked_at')
            ->first();

        abort_unless($recipient && $recipient->sent_at, 404);

        return $recipient;
    }

    private function resolveLocationFromIp(string $ip): string
    {
        if (in_array($ip, ['127.0.0.1', '::1', 'localhost'], true) || str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.')) {
            return 'Localhost / Internal Network';
        }

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(2)
                ->get("http://ip-api.com/json/{$ip}?fields=status,city,regionName,country");

            if ($response->successful()) {
                $data = $response->json();
                if (($data['status'] ?? '') === 'success') {
                    $parts = array_filter([
                        $data['city'] ?? null,
                        $data['regionName'] ?? null,
                        $data['country'] ?? null,
                    ]);
                    if (! empty($parts)) {
                        return implode(', ', $parts);
                    }
                }
            }
        } catch (\Throwable $e) {
            // Silently fallback if external lookup is unreachable
        }

        return "Auto-detected IP ({$ip})";
    }
}
