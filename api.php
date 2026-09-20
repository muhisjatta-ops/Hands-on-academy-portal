<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Auth\TwoFactorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
| These sit behind the 'web' middleware group (session + CSRF) because
| Sanctum's SPA mode is cookie-based. In bootstrap/app.php add:
|
|   ->withRouting(
|       api: __DIR__.'/../routes/api.php',
|       apiPrefix: 'api',
|   )
|   ->withMiddleware(function (Middleware $middleware) {
|       $middleware->statefulApi();   // <- enables cookie auth for /api/*
|   })
*/

Route::post('/auth/login', [LoginController::class, 'store'])
    ->middleware('throttle:10,1');

Route::post('/auth/two-factor-challenge', [LoginController::class, 'twoFactorChallenge'])
    ->middleware('throttle:10,1');

Route::post('/auth/forgot-password', [PasswordResetController::class, 'sendLink'])
    ->middleware('throttle:5,10');

Route::post('/auth/reset-password', [PasswordResetController::class, 'reset'])
    ->middleware('throttle:10,10');

/*
|--------------------------------------------------------------------------
| Authenticated
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/auth/me', [LoginController::class, 'me']);
    Route::post('/auth/logout', [LoginController::class, 'destroy']);

    // 2FA enrolment — reachable even when the two-factor gate is closed.
    Route::post('/auth/two-factor', [TwoFactorController::class, 'enable']);
    Route::post('/auth/two-factor/confirm', [TwoFactorController::class, 'confirm']);
    Route::delete('/auth/two-factor', [TwoFactorController::class, 'disable']);
    Route::post('/auth/two-factor/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes']);

    // Everything past this point needs 2FA satisfied for privileged roles.
    Route::middleware('two-factor')->group(function () {

        Route::get('/auth/sessions', [SessionController::class, 'index']);
        Route::delete('/auth/sessions/others', [SessionController::class, 'destroyOthers']);
        Route::delete('/auth/sessions/{id}', [SessionController::class, 'destroy']);

        // --- Phase 2+ goes here ---
        // Route::apiResource('students', StudentController::class)
        //     ->middleware('permission:students.view');
    });
});
