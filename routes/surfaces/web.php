<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\Portal\DashboardController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'surfaces.status', [
    'heading' => 'Beloved College Full Web Portal',
    'summary' => 'The complete administration, finance, teaching, student and parent workspaces will live on this surface.',
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

    Route::get('/dashboard', DashboardController::class)
        ->middleware(['password.changed', 'last.seen'])
        ->name('dashboard');
});
