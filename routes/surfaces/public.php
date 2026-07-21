<?php

use App\Http\Controllers\Public\ResultCheckerController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'surfaces.status', [
    'heading' => 'Beloved College Public Website',
    'summary' => 'Public school information, admissions, contact, announcements and result-checker services will live on this surface.',
])->name('home');

Route::get('/result-checker', [ResultCheckerController::class, 'index'])->name('result-checker.index');
Route::post('/result-checker', [ResultCheckerController::class, 'lookup'])
    ->middleware('throttle:12,1')
    ->name('result-checker.lookup');
