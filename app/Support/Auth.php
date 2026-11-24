<?php

namespace App\Support;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Run a closure as a given user, for the places that resolve permissions from the
 * current user rather than from an argument.
 *
 * The prototype has no sign-in, so the request user is normally null and the
 * policy falls back to config('ordo.default_role'). This swaps the user for the
 * duration of one callback so confidential visibility and role decisions can be
 * exercised without a login flow.
 */
class Auth
{
    public static function as(?Authenticatable $user, callable $callback): mixed
    {
        $previous = auth()->user();

        if ($user === null) {
            auth()->forgetUser();

            try {
                return $callback();
            } finally {
                if ($previous !== null) {
                    auth()->setUser($previous);
                }
            }
        }

        auth()->setUser($user);

        try {
            return $callback();
        } finally {
            if ($previous === null) {
                auth()->forgetUser();
            } else {
                auth()->setUser($previous);
            }
        }
    }
}
