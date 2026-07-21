<?php

use App\Http\Controllers\Portal\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'password.changed', 'last.seen'])
    ->prefix('notifications')
    ->name('notifications.')
    ->group(function (): void {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::patch('/read-all', [NotificationController::class, 'markAllRead'])->name('read-all');
        Route::patch('/{notification}/read', [NotificationController::class, 'read'])->name('read');
        Route::delete('/{notification}', [NotificationController::class, 'destroy'])->name('destroy');
    });
