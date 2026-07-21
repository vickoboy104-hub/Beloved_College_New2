<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Portal subdomains
|--------------------------------------------------------------------------
|
| Explicit domain routes are registered before the root public routes. The
| three surfaces share one Laravel backend, database and authorization model.
|
*/
Route::domain((string) config('platform.hosts.web'))
    ->middleware('surface:web')
    ->name('web.')
    ->group(base_path('routes/surfaces/web.php'));
Route::domain((string) config('platform.hosts.web'))
    ->middleware('surface:web')
    ->name('web.')
    ->group(base_path('routes/surfaces/finance-web.php'));
Route::domain((string) config('platform.hosts.web'))
    ->middleware('surface:web')
    ->name('web.')
    ->group(base_path('routes/surfaces/website-admin.php'));
Route::domain((string) config('platform.hosts.web'))
    ->middleware('surface:web')
    ->name('web.')
    ->group(base_path('routes/surfaces/theme-preferences.php'));

Route::domain((string) config('platform.hosts.app'))
    ->middleware('surface:app')
    ->name('app.')
    ->group(base_path('routes/surfaces/app.php'));
Route::domain((string) config('platform.hosts.app'))
    ->middleware('surface:app')
    ->name('app.')
    ->group(base_path('routes/surfaces/finance-app.php'));
Route::domain((string) config('platform.hosts.app'))
    ->middleware('surface:app')
    ->name('app.')
    ->group(base_path('routes/surfaces/theme-preferences.php'));

Route::middleware('surface:public')
    ->name('public.')
    ->group(base_path('routes/surfaces/public.php'));
Route::middleware('surface:public')
    ->name('public.')
    ->group(base_path('routes/surfaces/finance-public.php'));
