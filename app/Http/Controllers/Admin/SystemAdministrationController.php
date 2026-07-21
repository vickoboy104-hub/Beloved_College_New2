<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SecurityEvent;
use App\Models\Setting;
use App\Models\SystemHeartbeat;
use App\Models\User;
use App\Notifications\SystemTestEmailNotification;
use App\Services\System\MailConfigurationService;
use App\Services\System\SystemHealthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class SystemAdministrationController extends Controller
{
    public function index(
        Request $request,
        SystemHealthService $health,
        MailConfigurationService $mail,
    ): View {
        $sections = ['health', 'audit', 'jobs', 'mail', 'identity', 'settings'];
        $activeSection = in_array($request->query('section'), $sections, true)
            ? $request->query('section')
            : 'health';
        $auditQuery = AuditLog::query()->with('user')->latest();

        if ($request->filled('audit_user_id')) {
            $auditQuery->where('user_id', $request->integer('audit_user_id'));
        }

        if ($request->filled('audit_method')) {
            $auditQuery->where('method', strtoupper((string) $request->query('audit_method')));
        }

        if ($request->filled('audit_action')) {
            $auditQuery->where('action', 'like', '%'.$request->query('audit_action').'%');
        }

        if ($request->filled('audit_route')) {
            $auditQuery->where('route', 'like', '%'.$request->query('audit_route').'%');
        }

        if ($request->filled('audit_status')) {
            $auditQuery->where('status_code', $request->integer('audit_status'));
        }

        if ($request->filled('audit_from')) {
            $auditQuery->whereDate('created_at', '>=', $request->query('audit_from'));
        }

        if ($request->filled('audit_to')) {
            $auditQuery->whereDate('created_at', '<=', $request->query('audit_to'));
        }

        return view('admin.system.index', [
            'activeSection' => $activeSection,
            'health' => $health->report(),
            'auditLogs' => $auditQuery->paginate(50, ['*'], 'audit_page')->withQueryString(),
            'auditUsers' => User::query()->whereHas('auditLogs')->orderBy('name')->get(),
            'pendingJobs' => DB::table('jobs')->orderBy('created_at')->limit(100)->get(),
            'failedJobs' => DB::table('failed_jobs')->latest('failed_at')->limit(100)->get(),
            'heartbeats' => SystemHeartbeat::query()->orderBy('service')->get(),
            'mailStatus' => $mail->status(),
            'settings' => Setting::forAdminForm(),
            'securityEvents' => SecurityEvent::query()
                ->with('user')
                ->latest('occurred_at')
                ->paginate(50, ['*'], 'security_page')
                ->withQueryString(),
            'identityStats' => [
                'users' => User::query()->count(),
                'with_email' => User::query()->whereNotNull('email')->count(),
                'verified' => User::query()->whereNotNull('email_verified_at')->count(),
                'unverified' => User::query()->whereNotNull('email')->whereNull('email_verified_at')->count(),
                'without_email' => User::query()->whereNull('email')->count(),
                'active_sessions' => DB::table('sessions')->whereNotNull('user_id')->count(),
            ],
        ]);
    }

    public function updateMail(Request $request, MailConfigurationService $mail): RedirectResponse
    {
        $data = $request->validate([
            'mail_mailer' => ['required', Rule::in(['smtp', 'log', 'array'])],
            'mail_host' => ['nullable', 'string', 'max:255', 'required_if:mail_mailer,smtp'],
            'mail_port' => ['nullable', 'integer', 'min:1', 'max:65535', 'required_if:mail_mailer,smtp'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:1000'],
            'mail_scheme' => ['nullable', Rule::in(['smtp', 'smtps'])],
            'mail_timeout' => ['nullable', 'integer', 'min:5', 'max:120'],
            'mail_from_address' => ['required', 'email', 'max:255'],
            'mail_from_name' => ['required', 'string', 'max:255'],
        ]);

        Setting::setMany($data, 'mail');
        $mail->apply();

        return back()->with('status', 'Mail configuration saved. Blank password values preserve the existing encrypted password.');
    }

    public function testMail(
        Request $request,
        MailConfigurationService $mail,
    ): RedirectResponse {
        $data = $request->validate([
            'test_email' => ['required', 'email', 'max:255'],
        ]);

        try {
            $mail->apply();
            Notification::route('mail', $data['test_email'])
                ->notify(new SystemTestEmailNotification(
                    $request->user()->fullName(),
                    app()->environment(),
                ));
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'test_email' => 'Test delivery failed: '.$exception->getMessage(),
            ]);
        }

        return back()->with('status', 'The mail transport accepted the test message for '.$data['test_email'].'.');
    }

    public function retryFailedJob(string $uuid): RedirectResponse
    {
        abort_unless(DB::table('failed_jobs')->where('uuid', $uuid)->exists(), 404);
        Artisan::call('queue:retry', ['id' => [$uuid]]);

        return back()->with('status', 'Failed job queued for retry.');
    }

    public function destroyFailedJob(string $uuid): RedirectResponse
    {
        $deleted = DB::table('failed_jobs')->where('uuid', $uuid)->delete();
        abort_unless($deleted > 0, 404);

        return back()->with('status', 'Failed job record deleted.');
    }

    public function updateIdentitySettings(Request $request): RedirectResponse
    {
        $request->validate([
            'email_verification_required' => ['nullable', 'boolean'],
            'security_email_alerts_enabled' => ['nullable', 'boolean'],
            'security_login_alerts_enabled' => ['nullable', 'boolean'],
        ]);
        Setting::setMany([
            'email_verification_required' => $request->boolean('email_verification_required') ? '1' : '0',
            'security_email_alerts_enabled' => $request->boolean('security_email_alerts_enabled') ? '1' : '0',
            'security_login_alerts_enabled' => $request->boolean('security_login_alerts_enabled') ? '1' : '0',
        ], 'identity');

        return back()->with('status', 'Identity security policy updated.');
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'maintenance_contact' => ['nullable', 'email', 'max:255'],
            'operations_timezone' => ['required', 'timezone'],
            'queue_warning_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'scheduler_warning_minutes' => ['required', 'integer', 'min:2', 'max:60'],
            'audit_retention_days' => ['required', 'integer', 'min:30', 'max:3650'],
        ]);
        Setting::setMany($data, 'system');

        return back()->with('status', 'Operational settings updated.');
    }
}
