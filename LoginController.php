<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Support\SecurityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class LoginController
{
    /**
     * Step 1: verify the password.
     *
     * If the account has 2FA, we do NOT log the user in. We park their id in
     * the session and return {two_factor: true}. The session is useless to an
     * attacker who only has the password — Auth::check() is still false.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login'    => ['required', 'string'],   // email OR username
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ]);

        $throttleKey = strtolower($data['login']) . '|' . $request->ip();
        $this->assertNotThrottled($throttleKey);

        $user = User::where('email', $data['login'])
            ->orWhere('username', $data['login'])
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($throttleKey, 900);
            $user?->registerFailedLogin();

            SecurityLog::record('login.failed', $user, $data['login'], [
                'reason' => $user ? 'bad_password' : 'unknown_account',
            ]);

            // Deliberately identical message either way — never reveal
            // whether an account exists.
            throw ValidationException::withMessages([
                'login' => 'Those details don\'t match our records.',
            ]);
        }

        if ($user->isLocked()) {
            SecurityLog::record('login.locked', $user);

            throw ValidationException::withMessages([
                'login' => 'This account is locked until '
                    . $user->locked_until->format('H:i')
                    . '. Contact the system administrator.',
            ]);
        }

        if (! $user->is_active) {
            SecurityLog::record('login.failed', $user, null, ['reason' => 'inactive']);

            throw ValidationException::withMessages([
                'login' => 'This account has been deactivated.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put([
                'login.id'       => $user->id,
                'login.remember' => $data['remember'] ?? false,
                'login.at'       => now()->timestamp,
            ]);

            SecurityLog::record('two_factor.challenged', $user);

            return response()->json(['two_factor' => true]);
        }

        return $this->completeLogin($request, $user, $data['remember'] ?? false);
    }

    /**
     * Step 2: verify the TOTP code or a recovery code.
     */
    public function twoFactorChallenge(Request $request, Google2FA $google2fa): JsonResponse
    {
        $data = $request->validate([
            'code'          => ['nullable', 'string', 'size:6'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $userId = $request->session()->get('login.id');
        $startedAt = $request->session()->get('login.at', 0);

        // The challenge expires; a half-finished login can't sit open all day.
        if (! $userId || now()->timestamp - $startedAt > 300) {
            $request->session()->forget(['login.id', 'login.remember', 'login.at']);

            throw ValidationException::withMessages([
                'code' => 'This login attempt expired. Please sign in again.',
            ]);
        }

        $this->assertNotThrottled('2fa|' . $userId, 5);
        $user = User::findOrFail($userId);

        $passed = false;

        if (! empty($data['code'])) {
            // Window of 1 step (±30s) tolerates clock drift without
            // widening the attack surface much.
            $passed = $google2fa->verifyKey($user->two_factor_secret, $data['code'], 1);
        } elseif (! empty($data['recovery_code'])) {
            $passed = $user->consumeRecoveryCode(trim($data['recovery_code']));
        }

        if (! $passed) {
            RateLimiter::hit('2fa|' . $userId, 900);
            SecurityLog::record('two_factor.failed', $user);

            throw ValidationException::withMessages([
                'code' => 'That code is not valid.',
            ]);
        }

        RateLimiter::clear('2fa|' . $userId);
        $remember = (bool) $request->session()->get('login.remember');
        $request->session()->forget(['login.id', 'login.remember', 'login.at']);

        return $this->completeLogin($request, $user, $remember);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        SecurityLog::record('logout', $user);

        return response()->json(['message' => 'Signed out.']);
    }

    /**
     * The logged-in user plus everything the frontend needs to render nav.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id'                   => $user->id,
            'name'                 => $user->name,
            'email'                => $user->email,
            'username'             => $user->username,
            'roles'                => $user->getRoleNames(),
            'permissions'          => $user->getAllPermissions()->pluck('name'),
            'two_factor_enabled'   => $user->hasTwoFactorEnabled(),
            'two_factor_required'  => $user->requiresTwoFactor(),
            'must_change_password' => $user->must_change_password,
            'last_login_at'        => $user->last_login_at,
        ]);
    }

    // -----------------------------------------------------------------

    private function completeLogin(Request $request, User $user, bool $remember): JsonResponse
    {
        Auth::guard('web')->login($user, $remember);

        // Rotating the session id on privilege change stops session fixation.
        $request->session()->regenerate();

        $user->registerSuccessfulLogin($request->ip());
        SecurityLog::record('login.success', $user);

        return response()->json(['two_factor' => false]);
    }

    private function assertNotThrottled(string $key, int $max = 5): void
    {
        if (! RateLimiter::tooManyAttempts($key, $max)) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'login' => 'Too many attempts. Try again in '
                . ceil($seconds / 60) . ' minute(s).',
        ]);
    }
}
