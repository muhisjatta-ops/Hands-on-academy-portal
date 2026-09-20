<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class SecurityLog
{
    public static function record(
        string $event,
        ?User $user = null,
        ?string $identifier = null,
        array $context = []
    ): void {
        DB::table('security_events')->insert([
            'user_id'    => $user?->id,
            'identifier' => $identifier ?? $user?->email,
            'event'      => $event,
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 500),
            'context'    => $context ? json_encode($context) : null,
            'created_at' => now(),
        ]);
    }
}
