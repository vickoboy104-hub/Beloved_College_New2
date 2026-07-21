<?php

use App\Http\Controllers\Admin\AcademicController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\TeacherAccessController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\Portal\DashboardController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'surfaces.status', [
    'heading' => 'Beloved College Full Web Portal',
    'summary' => 'The complete administration, finance, teaching, student and parent workspaces will live on this surface.',
])->name('home');

Route::get('/login/{audience?}', [AuthenticatedSessionController::class, 'create'])
    ->where('audience', 'generic|student|staff')
    ->name('login');
Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('login.store');

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::get('/password/change', [PasswordChangeController::class, 'edit'])
        ->name('password-change.edit');
    Route::put('/password/change', [PasswordChangeController::class, 'update'])
        ->name('password-change.update');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    Route::middleware(['password.changed', 'last.seen'])->group(function (): void {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::prefix('admin')->name('admin.')->group(function (): void {
            Route::middleware('permission:people.manage_students')->group(function (): void {
                Route::get('/students', [StudentController::class, 'index'])->name('students.index');
                Route::post('/students', [StudentController::class, 'store'])->name('students.store');
                Route::get('/students/{student}', [StudentController::class, 'show'])->name('students.show');
                Route::patch('/students/{student}', [StudentController::class, 'update'])->name('students.update');
                Route::post('/students/{student}/temporary-password', [StudentController::class, 'resetPassword'])->name('students.password.reset');
                Route::patch('/students/{student}/archive', [StudentController::class, 'archive'])->name('students.archive');
                Route::patch('/students/{student}/restore', [StudentController::class, 'restore'])->name('students.restore');
            });

            Route::middleware('permission:people.manage_staff')->group(function (): void {
                Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
                Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
                Route::get('/staff/{staff}', [StaffController::class, 'show'])->name('staff.show');
                Route::patch('/staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
                Route::post('/staff/{staff}/temporary-password', [StaffController::class, 'resetPassword'])->name('staff.password.reset');
                Route::patch('/staff/{staff}/archive', [StaffController::class, 'archive'])->name('staff.archive');
                Route::patch('/staff/{staff}/restore', [StaffController::class, 'restore'])->name('staff.restore');
            });

            Route::middleware('permission:academics.manage_teacher_access')->group(function (): void {
                Route::get('/teacher-access', [TeacherAccessController::class, 'index'])->name('teacher-access.index');
                Route::post('/teacher-access', [TeacherAccessController::class, 'store'])->name('teacher-access.store');
                Route::post('/teacher-access/bulk', [TeacherAccessController::class, 'bulk'])->name('teacher-access.bulk');
                Route::patch('/teacher-access/{assignment}/revoke', [TeacherAccessController::class, 'revoke'])->name('teacher-access.revoke');
                Route::patch('/teacher-access/{assignment}/restore', [TeacherAccessController::class, 'restore'])->name('teacher-access.restore');
            });

            Route::middleware('permission:academics.manage_structure')->group(function (): void {
                Route::get('/academics', [AcademicController::class, 'index'])->name('academics.index');
                Route::post('/academics/sessions', [AcademicController::class, 'storeSession'])->name('academics.sessions.store');
                Route::patch('/academics/sessions/{session}/close', [AcademicController::class, 'closeSession'])->name('academics.sessions.close');
                Route::post('/academics/terms', [AcademicController::class, 'storeTerm'])->name('academics.terms.store');
                Route::post('/academics/classes', [AcademicController::class, 'storeClass'])->name('academics.classes.store');
                Route::patch('/academics/classes/{class}', [AcademicController::class, 'updateClass'])->name('academics.classes.update');
                Route::post('/academics/subjects', [AcademicController::class, 'storeSubject'])->name('academics.subjects.store');
                Route::post('/academics/promotions', [AcademicController::class, 'processPromotions'])
                    ->middleware('permission:academics.process_promotions')
                    ->name('academics.promotions.process');
            });
        });
    });
});
