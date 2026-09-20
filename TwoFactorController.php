<?php

namespace App\Http\Controllers\Auth;

use App\Support\SecurityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController
{
    /**
     * Generate a secret and return the otpauth:// URI for the QR code.
     * Nothing is "enabled" yet — see confirm(). This ordering matters:
     * if you enable on generate, a user who scans nothing gets locked out.
     */
    public function enable(Request $request, Google2FA $google2fa): JsonResponse
    {
        $user = $request->user();
        $secret = $google2fa->generateSecretKey();

        $user->forceFill([
            'two_factor_secret'         => $secret,
            'two_factor_recovery_codes' => json_encode($this->generateRecoveryCodes()),
            'two_factor_confirmed_at'   => null,
        ])->save();

        $uri = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        return response()->json([
            'secret'         => $secret,      // for manual entry
            'otpauth_uri'    => $uri,         // render as QR on the client
            'recovery_codes' => $user->recoveryCodes(),
        ]);
    }

    /**
     * The user proves the authenticator app actually works before we
     * start demanding codes at login.
     */
    public function confirm(Request $request, Google2FA $google2fa): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'size:6']]);
        $user = $request->user();

        if (! $user->two_factor_secret) {
            throw ValidationException::withMessages([
                'code' => 'Start the setup again — no pending secret was found.',
            ]);
        }

        if (! $google2fa->verifyKey($user->two_factor_secret, $data['code'], 1)) {
            throw ValidationException::withMessages([
                'code' => 'That code is not valid. Check your device clock and try again.',
            ]);
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        SecurityLog::record('two_factor.enabled', $user);

        return response()->json(['message' => 'Two-step verification is on.']);
    }

    /**
     * Disabling requires the password again — otherwise a hijacked session
     * can quietly remove the protection.
     */
    public function disable(Request $request): JsonResponse
    {
        $data = $request->validate(['password' => ['required', 'string']]);
        $user = $request->user();

        if (! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'That password is not correct.',
            ]);
        }

        if ($user->requiresTwoFactor()) {
            throw ValidationException::withMessages([
                'password' => 'Two-step verification is mandatory for this role.',
            ]);
        }

        $user->forceFill([
            'two_factor_secret'         => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at'   => null,
        ])->save();

        SecurityLog::record('two_factor.disabled', $user);

        return response()->json(['message' => 'Two-step verification is off.']);
    }

    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();
        $codes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => json_encode($codes),
        ])->save();

        SecurityLog::record('two_factor.recovery_codes_regenerated', $user);

        return response()->json(['recovery_codes' => $codes]);
    }

    private function generateRecoveryCodes(int $count = 8): array
    {
        return collect(range(1, $count))
            ->map(fn () => Str::lower(Str::random(5) . '-' . Str::random(5)))
            ->all();
    }
}
