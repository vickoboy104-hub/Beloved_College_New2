<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'surfaces.status', [
    'heading' => 'Beloved College Public Website',
    'summary' => 'Public school information, admissions, contact, announcements and result-checker services will live on this surface.',
])->name('home');
