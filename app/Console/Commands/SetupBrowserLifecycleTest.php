<?php

namespace App\Console\Commands;

use App\Models\Transmittal;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SetupBrowserLifecycleTest extends Command
{
    protected $signature = 'ordo:browser-test:setup';

    protected $description = 'Create disposable local browser lifecycle test data';

    public function handle(): int
    {
        if (! $this->isSafeEnvironment()) {
            $this->components->error('Browser lifecycle test data may only be created in local/testing environments using SQLite.');

            return self::FAILURE;
        }

        $tag = Str::lower(Str::random(8));
        $email = "browser-admin+{$tag}@example.test";
        $password = $this->secret('Test administrator password (12 characters minimum)');
        $confirmation = $this->secret('Confirm test administrator password');

        if (! is_string($password) || mb_strlen($password) < 12 || $password !== $confirmation) {
            $this->components->error('Passwords must match and contain at least 12 characters.');

            return self::FAILURE;
        }

        $admin = DB::transaction(function () use ($tag, $email, $password): User {
            $admin = User::query()->create([
                'name' => "ORDO Browser Lifecycle Admin {$tag}",
                'email' => $email,
                'password' => $password,
                'transmittal_role' => 'administrator',
            ]);

            $restore = $this->createTransmittal($admin, $tag, 'RESTORE', 'Disposable soft-delete and restore test record.');
            $this->attachTestDocument($restore, $admin, $tag);

            $permanent = $this->createTransmittal($admin, $tag, 'PERMANENT', 'Disposable eligible permanent-delete test record.');
            $this->attachTestDocument($permanent, $admin, $tag);

            $protected = $this->createTransmittal($admin, $tag, 'PROTECTED', 'Disposable permanent-delete evidence-protection test record.');
            $recipient = $protected->recipients()->create([
                'name' => 'Browser Test Recipient',
                'email' => "recipient+{$tag}@example.test",
                'organization' => 'ORDO Browser Lifecycle Test',
            ]);
            $this->attachTestDocument($protected, $admin, $tag);
            $receipt = $protected->receipts()->create([
                'recipient_id' => $recipient->id,
                'signed_name' => 'Browser Test Recipient',
                'comments' => 'Disposable browser lifecycle test receipt.',
                'receipt_code' => "BROWSER-TEST-{$tag}-TRR",
                'snapshot' => ['browser_test_tag' => $tag],
                'received_at' => now(),
            ]);
            $protected->acknowledgments()->create([
                'recipient_id' => $recipient->id,
                'receipt_id' => $receipt->id,
                'event' => 'acknowledged',
                'details' => ['browser_test_tag' => $tag],
                'occurred_at' => now(),
            ]);
            $protected->proofs()->create([
                'recipient_id' => $recipient->id,
                'sent_by' => $admin->id,
                'channel' => 'manual',
                'status' => 'uploaded',
                'provider_message_id' => "BROWSER-TEST-{$tag}-PROOF",
                'provider_metadata' => ['browser_test_tag' => $tag],
            ]);

            return $admin;
        });

        $this->components->info("Disposable browser-test records created. Tag: {$tag}");
        $this->line("Administrator: {$admin->email}");
        $this->line('Tracking numbers:');
        $this->line(" - BROWSER-TEST-{$tag}-RESTORE");
        $this->line(" - BROWSER-TEST-{$tag}-PERMANENT");
        $this->line(" - BROWSER-TEST-{$tag}-PROTECTED");
        $this->newLine();
        $this->warn('The administrator password was not displayed. Save the tag for the matching cleanup command.');

        return self::SUCCESS;
    }

    private function createTransmittal(User $admin, string $tag, string $suffix, string $purpose): Transmittal
    {
        $transmittal = Transmittal::query()->create([
            'tracking_number' => "BROWSER-TEST-{$tag}-{$suffix}",
            'subject' => "ORDO browser lifecycle {$suffix} {$tag}",
            'purpose' => $purpose,
            'confidentiality' => 'standard',
            'status' => 'draft',
            'prepared_by' => $admin->id,
            'prepared_by_name' => $admin->name,
            'prepared_at' => now(),
            'direction' => 'send',
            'transmittal_date' => now()->toDateString(),
            'origin_office' => 'ORDO Browser Lifecycle Test',
            'delivery_method' => 'electronic',
            'delivery_type' => 'electronic',
            'delivery_actions' => ['email'],
        ]);

        $transmittal->recipients()->create([
            'name' => 'Browser Test Recipient',
            'email' => "recipient+{$tag}@example.test",
            'organization' => 'ORDO Browser Lifecycle Test',
        ]);

        return $transmittal;
    }

    private function attachTestDocument(Transmittal $transmittal, User $admin, string $tag): void
    {
        $contents = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";
        $path = "transmittals/{$transmittal->id}/browser-test-{$tag}.pdf";
        $disk = Storage::disk('local');

        if (! $disk->put($path, $contents)) {
            throw new \RuntimeException('Unable to write the private browser-test attachment.');
        }

        $transmittal->documents()->create([
            'uploaded_by' => $admin->id,
            'category' => 'document',
            'original_name' => "browser-test-{$tag}.pdf",
            'storage_path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($contents),
            'sha256' => hash('sha256', $contents),
        ]);
    }

    private function isSafeEnvironment(): bool
    {
        return app()->environment(['local', 'testing'])
            && config('database.connections.'.config('database.default').'.driver') === 'sqlite';
    }
}
