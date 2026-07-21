<?php

use App\Http\Controllers\ThemePreferenceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'password.changed', 'last.seen'])
    ->put('/theme-preference', [ThemePreferenceController::class, 'update'])
    ->name('theme-preference.update');
