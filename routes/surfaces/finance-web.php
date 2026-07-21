<?php

use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\PaymentGatewaySettingsController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'password.changed', 'last.seen'])->group(function (): void {
    Route::prefix('payments')->name('payments.')->middleware('permission:finance.pay_invoices')->group(function (): void {
        Route::get('/', [PaymentController::class, 'index'])
            ->middleware('role:student,parent')
            ->name('index');
        Route::post('/invoices/{invoice}/checkout/{provider}', [PaymentController::class, 'checkout'])
            ->where('provider', 'paystack|flutterwave|monnify|palmpay')
            ->name('checkout');
        Route::post('/checkout/{provider}', [PaymentController::class, 'checkoutSelection'])
            ->where('provider', 'paystack|flutterwave|monnify|palmpay')
            ->name('checkout-selection');
        Route::get('/{payment}/receipt', [PaymentController::class, 'receipt'])->name('receipt');
    });

    Route::prefix('admin/finance')->name('admin.finance.')->middleware('permission:finance.manage')->group(function (): void {
        Route::get('/', [FinanceController::class, 'index'])->name('index');
        Route::post('/fee-items', [FinanceController::class, 'storeFeeItem'])->name('fee-items.store');
        Route::delete('/fee-items/{feeItem}', [FinanceController::class, 'destroyFeeItem'])->name('fee-items.destroy');
        Route::post('/invoices', [FinanceController::class, 'generateInvoices'])->name('invoices.store');
        Route::get('/printable-fee-list', [FinanceController::class, 'printableFeeList'])->name('printable-fee-list');
        Route::get('/receipts/{payment}', [FinanceController::class, 'receipt'])->name('receipt');

        Route::post('/manual-payments', [FinanceController::class, 'recordManualPayment'])
            ->middleware('permission:finance.record_payments')
            ->name('manual-payments.store');

        Route::middleware('permission:finance.configure_gateways')->group(function (): void {
            Route::get('/gateways', [PaymentGatewaySettingsController::class, 'index'])->name('gateways.index');
            Route::put('/gateways', [PaymentGatewaySettingsController::class, 'update'])->name('gateways.update');
        });
    });
});
