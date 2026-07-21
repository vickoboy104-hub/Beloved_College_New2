<?php

use App\Http\Controllers\Admin\ThemeController;
use App\Http\Controllers\Admin\WebsiteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'password.changed', 'last.seen'])
    ->prefix('admin/website')
    ->name('admin.website.')
    ->group(function (): void {
        Route::middleware('permission:website.manage_content')->group(function (): void {
            Route::get('/', [WebsiteController::class, 'index'])->name('index');
            Route::put('/pages/{slug}', [WebsiteController::class, 'savePage'])->name('pages.update');
            Route::post('/media', [WebsiteController::class, 'storeMedia'])->name('media.store');
            Route::delete('/media/{media}', [WebsiteController::class, 'destroyMedia'])->name('media.destroy');
            Route::post('/news', [WebsiteController::class, 'storeAnnouncement'])->name('news.store');
            Route::put('/news/{announcement}', [WebsiteController::class, 'updateAnnouncement'])->name('news.update');
            Route::delete('/news/{announcement}', [WebsiteController::class, 'destroyAnnouncement'])->name('news.destroy');
            Route::post('/testimonials', [WebsiteController::class, 'storeTestimonial'])->name('testimonials.store');
            Route::delete('/testimonials/{testimonial}', [WebsiteController::class, 'destroyTestimonial'])->name('testimonials.destroy');
            Route::patch('/messages/{message}', [WebsiteController::class, 'updateMessage'])->name('messages.update');
            Route::patch('/newsletter/{subscriber}/unsubscribe', [WebsiteController::class, 'unsubscribe'])->name('newsletter.unsubscribe');
            Route::put('/settings', [WebsiteController::class, 'updateSettings'])->name('settings.update');
        });

        Route::middleware('permission:website.manage_themes')->prefix('themes')->name('themes.')->group(function (): void {
            Route::get('/', [ThemeController::class, 'index'])->name('index');
            Route::post('/{mode}/drafts', [ThemeController::class, 'saveDraft'])->name('drafts.store');
            Route::post('/{mode}/publish', [ThemeController::class, 'publish'])->name('publish');
            Route::post('/revisions/{revision}/rollback', [ThemeController::class, 'rollback'])->name('rollback');
            Route::put('/preferences', [ThemeController::class, 'preferences'])->name('preferences');
            Route::get('/{mode}/preview', [ThemeController::class, 'preview'])->name('preview');
        });
    });
