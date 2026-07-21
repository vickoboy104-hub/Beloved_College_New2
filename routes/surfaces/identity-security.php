<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\SecurityController;
use Illuminate\Support\Facades\Route;

Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('password.email');
Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
Route::post('/reset-password', [NewPasswordController::class, 'store'])
    ->middleware('throttle:8,1')
    ->name('password.update');

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:4,1')
        ->name('verification.send');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:8,1'])
        ->name('verification.verify');

    Route::prefix('security')
        ->name('security.')
        ->middleware(['password.changed', 'last.seen'])
        ->group(function (): void {
            Route::get('/', [SecurityController::class, 'index'])->name('index');
            Route::put('/password', [SecurityController::class, 'updatePassword'])
                ->middleware('throttle:6,1')
                ->name('password.update');
            Route::delete('/sessions/{session}', [SecurityController::class, 'revokeSession'])->name('sessions.destroy');
            Route::delete('/sessions', [SecurityController::class, 'revokeOtherSessions'])
                ->middleware('throttle:6,1')
                ->name('sessions.destroy-others');
        });
});
