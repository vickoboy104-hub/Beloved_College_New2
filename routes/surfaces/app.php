<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'surfaces.status', [
    'heading' => 'Beloved College Mobile Portal',
    'summary' => 'The mobile-first and installable portal experience will live on this surface while using the same Laravel backend.',
])->name('home');
