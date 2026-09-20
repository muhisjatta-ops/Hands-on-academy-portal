<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'username', 'name', 'email', 'phone', 'password', 'is_active',
    ];

    protected $hidden = [
        'password', 'remember_token',
        'two_factor_secret', 'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'password'                 => 'hashed',
            'email_verified_at'        => 'datetime',
            'two_factor_confirmed_at'  => 'datetime',
            'two_factor_secret'        => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted',
            'password_changed_at'      => 'datetime',
            'last_login_at'            => 'datetime',
            'locked_until'             => 'datetime',
            'is_active'                => 'boolean',
            'must_change_password'     => 'boolean',
        ];
    }

    // ---------------------------------------------------------------
    // Two-factor
    // ---------------------------------------------------------------

    public function hasTwoFactorEnabled(): bool
    {
        return ! is_null($this->two_factor_secret)
            && ! is_null($this->two_factor_confirmed_at);
    }

    /**
     * Admins must use 2FA. Enforced by the RequireTwoFactor middleware,
     * which lets them reach only the setup endpoints until they enrol.
     */
    public function requiresTwoFactor(): bool
    {
        return $this->hasAnyRole(['super-admin', 'admin', 'bursar']);
    }

    public function recoveryCodes(): array
    {
        return json_decode($this->two_factor_recovery_codes ?? '[]', true) ?: [];
    }

    public function consumeRecoveryCode(string $code): bool
    {
        $codes = $this->recoveryCodes();
        $index = array_search($code, $codes, true);

        if ($index === false) {
            return false;
        }

        unset($codes[$index]);
        $this->forceFill([
            'two_factor_recovery_codes' => json_encode(array_values($codes)),
        ])->save();

        return true;
    }

    // ---------------------------------------------------------------
    // Lockout
    // ---------------------------------------------------------------

    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    public function registerFailedLogin(int $threshold = 5, int $minutes = 15): void
    {
        $attempts = $this->failed_login_attempts + 1;

        $this->forceFill([
            'failed_login_attempts' => $attempts,
            'locked_until' => $attempts >= $threshold
                ? Carbon::now()->addMinutes($minutes)
                : $this->locked_until,
        ])->save();
    }

    public function registerSuccessfulLogin(?string $ip): void
    {
        $this->forceFill([
            'failed_login_attempts' => 0,
            'locked_until'          => null,
            'last_login_at'         => Carbon::now(),
            'last_login_ip'         => $ip,
        ])->save();
    }

    // ---------------------------------------------------------------
    // Links to the domain (built out in later phases)
    // ---------------------------------------------------------------

    public function student()
    {
        return $this->hasOne(Student::class);
    }

    public function guardian()
    {
        return $this->hasOne(Guardian::class);
    }
}
