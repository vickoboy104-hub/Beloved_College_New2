<?php

use App\Http\Controllers\Public\PublicWebsiteController;
use App\Http\Controllers\Public\ResultCheckerController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicWebsiteController::class, 'home'])->name('home');
Route::get('/about', [PublicWebsiteController::class, 'about'])->name('about');
Route::get('/admissions', [PublicWebsiteController::class, 'admissions'])->name('admissions');
Route::get('/contact', [PublicWebsiteController::class, 'contact'])->name('contact');
Route::post('/contact', [PublicWebsiteController::class, 'storeContact'])
    ->middleware('throttle:5,1')
    ->name('contact.store');
Route::get('/news', [PublicWebsiteController::class, 'news'])->name('news.index');
Route::get('/news/{slug}', [PublicWebsiteController::class, 'article'])->name('news.show');
Route::get('/gallery', [PublicWebsiteController::class, 'gallery'])->name('gallery');
Route::post('/newsletter', [PublicWebsiteController::class, 'subscribe'])
    ->middleware('throttle:6,1')
    ->name('newsletter.subscribe');
Route::get('/media/{media}', [PublicWebsiteController::class, 'media'])
    ->middleware('throttle:180,1')
    ->name('media.show');
Route::get('/testimonials/{testimonial}/photo', [PublicWebsiteController::class, 'testimonialPhoto'])
    ->middleware('throttle:180,1')
    ->name('testimonials.photo');

Route::get('/result-checker', [ResultCheckerController::class, 'index'])->name('result-checker.index');
Route::post('/result-checker', [ResultCheckerController::class, 'lookup'])
    ->middleware('throttle:12,1')
    ->name('result-checker.lookup');
