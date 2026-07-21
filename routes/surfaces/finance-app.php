<?php

use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'password.changed', 'last.seen', 'permission:finance.pay_invoices'])
    ->prefix('payments')
    ->name('payments.')
    ->group(function (): void {
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
