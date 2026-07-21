<?php

use App\Http\Controllers\Admin\AcademicController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\TeacherAccessController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\Portal\CbtController as PortalCbtController;
use App\Http\Controllers\Portal\DashboardController;
use App\Http\Controllers\Portal\StudentPortalController;
use App\Http\Controllers\PrivateLearningMediaController;
use App\Http\Controllers\PrivateMediaController;
use App\Http\Controllers\Teacher\CbtController as TeacherCbtController;
use App\Http\Controllers\Teacher\LearningController;
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
    Route::get('/password/change', [PasswordChangeController::class, 'edit'])->name('password-change.edit');
    Route::put('/password/change', [PasswordChangeController::class, 'update'])->name('password-change.update');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/private-media/users/{user}/avatar', [PrivateMediaController::class, 'avatar'])
        ->middleware('throttle:120,1')
        ->name('private-media.avatar');
    Route::prefix('private-learning-media')->name('private-learning-media.')->middleware('throttle:180,1')->group(function (): void {
        Route::get('/lessons/{lesson}/video', [PrivateLearningMediaController::class, 'lessonVideo'])->name('lessons.video');
        Route::get('/lessons/{lesson}/images/{index}', [PrivateLearningMediaController::class, 'lessonImage'])->whereNumber('index')->name('lessons.images');
        Route::get('/assignments/{assignment}/prompts/{index}', [PrivateLearningMediaController::class, 'assignmentPrompt'])->whereNumber('index')->name('assignments.prompts');
        Route::get('/submissions/{submission}/files/{index}', [PrivateLearningMediaController::class, 'submission'])->whereNumber('index')->name('submissions.files');
        Route::get('/cbt/questions/{question}/images/{index}', [PrivateLearningMediaController::class, 'cbtImage'])->whereNumber('index')->name('cbt.images');
        Route::get('/cbt/questions/{question}/video', [PrivateLearningMediaController::class, 'cbtVideo'])->name('cbt.video');
    });

    Route::middleware(['password.changed', 'last.seen'])->group(function (): void {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::prefix('portal')->name('portal.')->middleware('role:student,parent')->group(function (): void {
            Route::get('/', [StudentPortalController::class, 'index'])->name('index');
            Route::get('/reports/{report}', [StudentPortalController::class, 'report'])->name('reports.show');
            Route::post('/assignments/{assignment}/submit', [StudentPortalController::class, 'submitAssignment'])
                ->middleware('role:student')
                ->name('assignments.submit');
            Route::get('/cbt/{assessment}', [PortalCbtController::class, 'show'])
                ->middleware('role:student')
                ->name('cbt.show');
            Route::post('/cbt/{assessment}/submit', [PortalCbtController::class, 'submit'])
                ->middleware('role:student')
                ->name('cbt.submit');
            Route::get('/cbt/attempts/{attempt}/result', [PortalCbtController::class, 'result'])->name('cbt.result');
        });

        Route::prefix('teacher')->name('teacher.')->middleware('role:super_admin,admin,principal,teacher')->group(function (): void {
            Route::get('/learning', [LearningController::class, 'index'])->name('learning.index');
            Route::post('/learning/lessons', [LearningController::class, 'storeLesson'])->name('learning.lessons.store');
            Route::post('/learning/assignments', [LearningController::class, 'storeAssignment'])->name('learning.assignments.store');
            Route::post('/learning/assessments', [LearningController::class, 'storeAssessment'])->name('learning.assessments.store');
            Route::post('/learning/results', [LearningController::class, 'recordResult'])->name('learning.results.store');
            Route::post('/learning/attendance', [LearningController::class, 'recordAttendance'])->name('learning.attendance.store');
            Route::patch('/learning/submissions/{submission}/grade', [LearningController::class, 'gradeSubmission'])->name('learning.submissions.grade');

            Route::get('/cbt', [TeacherCbtController::class, 'index'])->name('cbt.index');
            Route::post('/cbt', [TeacherCbtController::class, 'storeAssessment'])->name('cbt.store');
            Route::get('/cbt/{assessment}', [TeacherCbtController::class, 'show'])->name('cbt.show');
            Route::post('/cbt/{assessment}/questions', [TeacherCbtController::class, 'addQuestion'])->name('cbt.questions.store');
            Route::patch('/cbt/questions/{question}', [TeacherCbtController::class, 'updateQuestion'])->name('cbt.questions.update');
            Route::delete('/cbt/questions/{question}', [TeacherCbtController::class, 'deleteQuestion'])->name('cbt.questions.destroy');
            Route::patch('/cbt/answers/{answer}/grade', [TeacherCbtController::class, 'gradeTheory'])->name('cbt.answers.grade');
            Route::middleware('role:super_admin,admin,principal')->group(function (): void {
                Route::patch('/cbt/global-access', [TeacherCbtController::class, 'setGlobalEnabled'])->name('cbt.global-access');
                Route::patch('/cbt/{assessment}/active', [TeacherCbtController::class, 'setAssessmentActive'])->name('cbt.active');
            });
        });

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

            Route::prefix('reports')->name('reports.')->middleware('role:super_admin,admin,principal')->group(function (): void {
                Route::get('/', [ReportController::class, 'index'])->name('index');
                Route::post('/students/{student}/compile', [ReportController::class, 'compile'])->name('compile');
                Route::post('/compile-class', [ReportController::class, 'compileClass'])->name('compile-class');
                Route::get('/{report}', [ReportController::class, 'show'])->name('show');
                Route::patch('/{report}', [ReportController::class, 'update'])->name('update');
                Route::patch('/{report}/publish', [ReportController::class, 'publish'])->name('publish');
                Route::get('/{report}/print', [ReportController::class, 'print'])->name('print');
            });
        });
    });
});
