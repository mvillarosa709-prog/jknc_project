<?php

namespace App\Support;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Cache;

class ActivityDuplicateGuard
{
    public function create(string $type, array $businessKey, Closure $findExisting, Closure $create): Model
    {
        $fingerprint = hash('sha256', json_encode([
            'type' => $type,
            'key' => $this->normalize($businessKey),
        ], JSON_THROW_ON_ERROR));

        try {
            return Cache::lock('activity-create:'.$fingerprint, 15)->block(5, function () use ($findExisting, $create) {
                if ($findExisting()) {
                    $this->conflict();
                }

                return $create();
            });
        } catch (LockTimeoutException) {
            $this->conflict();
        }
    }

    private function normalize(mixed $value): mixed
    {
        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
        }

        return is_string($value) ? mb_strtolower(trim($value)) : $value;
    }

    private function conflict(): never
    {
        throw new HttpResponseException(response()->json([
            'message' => 'This record already exists.',
        ], 409));
    }
}
