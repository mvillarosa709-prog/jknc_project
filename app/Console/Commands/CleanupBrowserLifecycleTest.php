<?php

namespace App\Console\Commands;

use App\Models\Transmittal;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CleanupBrowserLifecycleTest extends Command
{
    protected $signature = 'ordo:browser-test:cleanup {tag : The eight-character tag printed by the setup command}';

    protected $description = 'Remove only matching disposable local browser lifecycle test data';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])
            || config('database.connections.'.config('database.default').'.driver') !== 'sqlite') {
            $this->components->error('Browser lifecycle test data may only be cleaned in local/testing environments using SQLite.');

            return self::FAILURE;
        }

        $tag = $this->argument('tag');
        if (! is_string($tag) || ! preg_match('/^[a-z0-9]{8}$/', $tag)) {
            $this->components->error('Provide the exact eight-character tag printed by the setup command.');

            return self::FAILURE;
        }

        $prefix = "BROWSER-TEST-{$tag}-";
        $trackingNumbers = [
            "{$prefix}RESTORE",
            "{$prefix}PERMANENT",
            "{$prefix}PROTECTED",
        ];
        $email = "browser-admin+{$tag}@example.test";
        $expectedName = "ORDO Browser Lifecycle Admin {$tag}";
        $admin = User::query()
            ->where('email', $email)
            ->where('name', $expectedName)
            ->where('transmittal_role', 'administrator')
            ->first();
        if ($admin === null) {
            $this->components->error('The matching unchanged test administrator was not found; nothing was removed.');

            return self::FAILURE;
        }

        $transmittals = Transmittal::withTrashed()
            ->whereIn('tracking_number', $trackingNumbers)
            ->where('prepared_by', $admin->id)
            ->get();
        $archives = DB::table('transmittal_lifecycle_archives')
            ->whereIn('tracking_number', $trackingNumbers)
            ->exists();
        if ($transmittals->isEmpty() && ! $archives) {
            $this->components->error('No disposable browser-test transmittals match that tag; nothing was removed.');

            return self::FAILURE;
        }
        if ($transmittals->contains(fn (Transmittal $transmittal): bool => $transmittal->approved_snapshot !== null)) {
            $this->components->error('A matching fixture has an approval snapshot; cleanup stopped without removing files or records.');

            return self::FAILURE;
        }

        $disk = Storage::disk('local');
        $paths = $transmittals->flatMap(function (Transmittal $transmittal) use ($tag): array {
            $filePrefix = "transmittals/{$transmittal->id}/browser-test-{$tag}";

            return $transmittal->documents()
                ->pluck('storage_path')
                ->filter(fn (string $path): bool => str_starts_with($path, $filePrefix))
                ->all();
        })->unique()->values();

        foreach ($paths as $path) {
            if ($disk->exists($path) && ! $disk->delete($path)) {
                $this->components->error("Could not remove test attachment {$path}; database records were retained.");

                return self::FAILURE;
            }
        }

        DB::transaction(function () use ($transmittals, $tag, $admin): void {
            foreach ($transmittals as $transmittal) {
                $transmittal->proofs()->delete();
                $transmittal->receipts()->delete();
                $transmittal->acknowledgments()->delete();
                $transmittal->forceDelete();
            }

            DB::table('transmittal_lifecycle_archives')
                ->whereIn('tracking_number', [
                    "BROWSER-TEST-{$tag}-RESTORE",
                    "BROWSER-TEST-{$tag}-PERMANENT",
                    "BROWSER-TEST-{$tag}-PROTECTED",
                ])
                ->delete();

            $admin->delete();
        });

        $this->components->info("Removed disposable browser-test data for tag {$tag}.");

        return self::SUCCESS;
    }
}
