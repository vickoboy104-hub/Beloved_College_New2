<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\Portal\DashboardController;
use App\Http\Controllers\PrivateMediaController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'surfaces.status', [
    'heading' => 'Beloved College Mobile Portal',
    'summary' => 'The mobile-first and installable portal experience will live on this surface while using the same Laravel backend.',
])->name('home');

Route::get('/login/{audience?}', [AuthenticatedSessionController::class, 'create'])
    ->where('audience', 'generic|student|staff')
    ->name('login');
Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('login.store');

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::get('/password/change', [PasswordChangeController::class, 'edit'])
        ->name('password-change.edit');
    Route::put('/password/change', [PasswordChangeController::class, 'update'])
        ->name('password-change.update');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
    Route::get('/private-media/users/{user}/avatar', [PrivateMediaController::class, 'avatar'])
        ->middleware('throttle:120,1')
        ->name('private-media.avatar');

    Route::get('/dashboard', DashboardController::class)
        ->middleware(['password.changed', 'last.seen'])
        ->name('dashboard');
});
