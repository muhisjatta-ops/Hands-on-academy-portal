<?php

/*
|--------------------------------------------------------------------------
| Phase 2-4 routes
|--------------------------------------------------------------------------
| Paste this INSIDE the `Route::middleware('two-factor')->group(...)` block
| in routes/api.php, replacing the "--- Phase 2+ goes here ---" comment.
|
| Every route carries its own permission. That is the point: authentication
| proved who you are, and these say what you may do. Never rely on the
| frontend hiding a button.
*/

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FeeController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\StudentController;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', DashboardController::class);
Route::get('/lookups', [SetupController::class, 'lookups']);

/* ---------------------------- students ---------------------------- */
Route::middleware('permission:students.view')->group(function () {
    Route::get('/students', [StudentController::class, 'index']);
    Route::get('/students/{student}', [StudentController::class, 'show']);
});

Route::post('/students', [StudentController::class, 'store'])
    ->middleware('permission:students.create');

Route::middleware('permission:students.update')->group(function () {
    Route::patch('/students/{student}', [StudentController::class, 'update']);
    Route::post('/students/{student}/transfer', [StudentController::class, 'transfer']);
});

Route::delete('/students/{student}', [StudentController::class, 'destroy'])
    ->middleware('permission:students.delete');

/* ------------------------------ fees ------------------------------ */
Route::middleware('permission:fees.view')->group(function () {
    Route::get('/fees/structure', [FeeController::class, 'structure']);
    Route::get('/fees/ledger', [FeeController::class, 'ledger']);
    Route::get('/fees/invoices/{invoice}', [FeeController::class, 'invoice']);
    Route::get('/students/{student}/statement', [PaymentController::class, 'statement']);
    Route::get('/payments', [PaymentController::class, 'index']);
});

Route::middleware('permission:fees.structure_manage')->group(function () {
    Route::post('/fees/structure', [FeeController::class, 'storeStructure']);
    Route::delete('/fees/structure/{feeStructure}', [FeeController::class, 'destroyStructure']);
    Route::post('/fees/categories', [FeeController::class, 'storeCategory']);
});

Route::post('/fees/invoices/generate', [FeeController::class, 'generateInvoices'])
    ->middleware('permission:fees.invoice_generate');

Route::post('/payments', [PaymentController::class, 'store'])
    ->middleware('permission:fees.record_payment');

// Reversal is separately permissioned: recording money is routine,
// unrecording it is not.
Route::post('/payments/{payment}/reverse', [PaymentController::class, 'reverse'])
    ->middleware('permission:fees.void_payment');

/* ----------------------------- grades ----------------------------- */
Route::get('/grades/sheet', [GradeController::class, 'sheet'])
    ->middleware('permission:scores.enter|scores.publish');

Route::middleware('permission:assessments.manage')->group(function () {
    Route::post('/grades/assessments', [GradeController::class, 'storeAssessments']);
});

Route::post('/grades/scores', [GradeController::class, 'storeScores'])
    ->middleware('permission:scores.enter');

Route::get('/students/{student}/report-card', [GradeController::class, 'reportCard'])
    ->middleware('permission:reports.generate|students.view');

/* --------------------------- attendance --------------------------- */
Route::get('/attendance/register', [AttendanceController::class, 'register'])
    ->middleware('permission:attendance.view');

Route::post('/attendance', [AttendanceController::class, 'store'])
    ->middleware('permission:attendance.record');

Route::get('/attendance/absentees', [AttendanceController::class, 'absentees'])
    ->middleware('permission:attendance.view');

/* ------------------------------ setup ----------------------------- */
Route::middleware('permission:settings.manage')->group(function () {
    Route::post('/setup/academic-years', [SetupController::class, 'storeAcademicYear']);
    Route::post('/setup/current-term', [SetupController::class, 'setCurrentTerm']);
    Route::post('/setup/class-rooms', [SetupController::class, 'storeClassRoom']);
    Route::post('/setup/subjects', [SetupController::class, 'storeSubject']);
});
