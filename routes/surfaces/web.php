<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'surfaces.status', [
    'heading' => 'Beloved College Full Web Portal',
    'summary' => 'The complete administration, finance, teaching, student and parent workspaces will live on this surface.',
])->name('home');
