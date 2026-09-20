<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admins and bursars cannot use the system until they have enrolled in 2FA.
 * They can still reach the setup and logout endpoints, so this is a gate,
 * not a trap.
 *
 * Register in bootstrap/app.php:
 *   $middleware->alias(['two-factor' => RequireTwoFactor::class]);
 */
class RequireTwoFactor
{
    private const ALLOWED = [
        'api/auth/me',
        'api/auth/logout',
        'api/auth/two-factor',
        'api/auth/two-factor/confirm',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->requiresTwoFactor() || $user->hasTwoFactorEnabled()) {
            return $next($request);
        }

        if (in_array($request->path(), self::ALLOWED, true)) {
            return $next($request);
        }

        return response()->json([
            'message'  => 'Set up two-step verification to continue.',
            'code'     => 'two_factor_setup_required',
        ], 403);
    }
}
