<?php

use App\Http\Controllers\Admin\CommunicationController;
use App\Http\Controllers\Admin\SystemAdministrationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'password.changed', 'last.seen'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::prefix('communication')
            ->name('communication.')
            ->middleware('permission:communication.manage_announcements')
            ->group(function (): void {
                Route::get('/', [CommunicationController::class, 'index'])->name('index');
                Route::post('/announcements', [CommunicationController::class, 'store'])->name('announcements.store');
                Route::post('/announcements/{announcement}/dispatch', [CommunicationController::class, 'dispatch'])->name('announcements.dispatch');
                Route::patch('/announcements/{announcement}/cancel', [CommunicationController::class, 'cancel'])->name('announcements.cancel');
                Route::put('/settings', [CommunicationController::class, 'settings'])->name('settings.update');
            });

        Route::prefix('system')
            ->name('system.')
            ->middleware('permission:system.manage_settings')
            ->group(function (): void {
                Route::get('/', [SystemAdministrationController::class, 'index'])->name('index');
                Route::put('/mail', [SystemAdministrationController::class, 'updateMail'])->name('mail.update');
                Route::post('/mail/test', [SystemAdministrationController::class, 'testMail'])->name('mail.test');
                Route::post('/failed-jobs/{uuid}/retry', [SystemAdministrationController::class, 'retryFailedJob'])->name('failed-jobs.retry');
                Route::delete('/failed-jobs/{uuid}', [SystemAdministrationController::class, 'destroyFailedJob'])->name('failed-jobs.destroy');
                Route::put('/settings', [SystemAdministrationController::class, 'updateSettings'])->name('settings.update');
            });
    });
