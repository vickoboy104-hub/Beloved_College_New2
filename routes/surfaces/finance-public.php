<?php

use App\Http\Controllers\PaymentController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/payments/callback/{provider}', [PaymentController::class, 'callback'])
    ->where('provider', 'paystack|flutterwave|monnify|palmpay')
    ->middleware('throttle:60,1')
    ->name('payments.callback');

Route::post('/webhooks/paystack', [WebhookController::class, 'paystack'])
    ->middleware('throttle:180,1')
    ->name('webhooks.paystack');
Route::post('/webhooks/flutterwave', [WebhookController::class, 'flutterwave'])
    ->middleware('throttle:180,1')
    ->name('webhooks.flutterwave');
Route::post('/webhooks/monnify', [WebhookController::class, 'monnify'])
    ->middleware('throttle:180,1')
    ->name('webhooks.monnify');
Route::post('/webhooks/palmpay', [WebhookController::class, 'palmpay'])
    ->middleware('throttle:60,1')
    ->name('webhooks.palmpay');
