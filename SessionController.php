<?php

namespace App\Http\Controllers\Auth;

use App\Support\SecurityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Jenssegers\Agent\Agent;

/**
 * Requires SESSION_DRIVER=database. That single config change is what makes
 * "sign out my other devices" possible at all — file or cookie sessions
 * give you nothing to enumerate or delete.
 */
class SessionController
{
    public function index(Request $request): JsonResponse
    {
        $sessions = DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(function ($session) use ($request) {
                $agent = new Agent();
                $agent->setUserAgent($session->user_agent ?? '');

                return [
                    'id'            => $session->id,
                    'ip_address'    => $session->ip_address,
                    'device'        => $agent->device() ?: 'Unknown device',
                    'platform'      => $agent->platform(),
                    'browser'       => $agent->browser(),
                    'last_active'   => date('c', $session->last_activity),
                    'is_current'    => $session->id === $request->session()->getId(),
                ];
            });

        return response()->json(['sessions' => $sessions]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        if ($id === $request->session()->getId()) {
            throw ValidationException::withMessages([
                'session' => 'Use sign out to end the session you are using.',
            ]);
        }

        DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->where('id', $id)
            ->delete();

        SecurityLog::record('session.revoked', $request->user(), null, [
            'session_id' => $id,
        ]);

        return response()->json(['message' => 'Session ended.']);
    }

    public function destroyOthers(Request $request): JsonResponse
    {
        $data = $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check($data['password'], $request->user()->password)) {
            throw ValidationException::withMessages([
                'password' => 'That password is not correct.',
            ]);
        }

        $deleted = DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        SecurityLog::record('session.revoked_all', $request->user(), null, [
            'count' => $deleted,
        ]);

        return response()->json(['message' => "Ended {$deleted} other session(s)."]);
    }
}
