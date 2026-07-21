@php
    $surface = $surface ?? $portalSurface;
    $user = auth()->user();
    $themeService = app(\App\Services\Website\ThemeService::class);
    $theme = $user->effectiveTheme();
    $themeTokens = $themeService->tokens($theme);
    $surfacePrefix = $surface === \App\Enums\PortalSurface::AppPortal ? 'app' : 'web';
    $settings = \App\Models\Setting::publicSettings();
    $schoolName = $settings['school_name'] ?? 'Beloved College';
    $isTeacherWorkspaceUser = $user->hasAnyRole('super_admin', 'admin', 'principal', 'teacher');
    $isStudentPortalUser = $user->hasAnyRole('student', 'parent');
    $isReportAdministrator = $surfacePrefix === 'web' && $user->hasAnyRole('super_admin', 'admin', 'principal');
    $isFinanceUser = $surfacePrefix === 'web' && $user->hasPermission('finance.manage');
    $isWebsiteManager = $surfacePrefix === 'web' && $user->hasPermission('website.manage_content');
    $navItems = [
        [
            'label' => 'Dashboard',
            'route' => $surfacePrefix.'.dashboard',
            'active' => $surfacePrefix.'.dashboard',
            'visible' => true,
        ],
        [
            'label' => 'My Portal',
            'route' => $surfacePrefix.'.portal.index',
            'active' => $surfacePrefix.'.portal.*',
            'visible' => $isStudentPortalUser,
        ],
        [
            'label' => 'Payments',
            'route' => $surfacePrefix.'.payments.index',
            'active' => $surfacePrefix.'.payments.*',
            'visible' => $isStudentPortalUser && $user->hasPermission('finance.pay_invoices'),
        ],
        [
            'label' => 'Teaching',
            'route' => $surfacePrefix.'.teacher.learning.index',
            'active' => $surfacePrefix.'.teacher.learning.*',
            'visible' => $isTeacherWorkspaceUser,
        ],
        [
            'label' => 'CBT',
            'route' => $surfacePrefix.'.teacher.cbt.index',
            'active' => $surfacePrefix.'.teacher.cbt.*',
            'visible' => $isTeacherWorkspaceUser,
        ],
        [
            'label' => 'Students',
            'route' => 'web.admin.students.index',
            'active' => 'web.admin.students.*',
            'visible' => $surfacePrefix === 'web' && $user->hasPermission('people.manage_students'),
        ],
        [
            'label' => 'Staff',
            'route' => 'web.admin.staff.index',
            'active' => 'web.admin.staff.*',
            'visible' => $surfacePrefix === 'web' && $user->hasPermission('people.manage_staff'),
        ],
        [
            'label' => 'Academics',
            'route' => 'web.admin.academics.index',
            'active' => 'web.admin.academics.*',
            'visible' => $surfacePrefix === 'web' && $user->hasPermission('academics.manage_structure'),
        ],
        [
            'label' => 'Reports',
            'route' => 'web.admin.reports.index',
            'active' => 'web.admin.reports.*',
            'visible' => $isReportAdministrator,
        ],
        [
            'label' => 'Finance',
            'route' => 'web.admin.finance.index',
            'active' => 'web.admin.finance.*',
            'visible' => $isFinanceUser,
        ],
        [
            'label' => 'Website',
            'route' => 'web.admin.website.index',
            'active' => 'web.admin.website.*',
            'visible' => $isWebsiteManager,
        ],
        [
            'label' => 'Teacher Access',
            'route' => 'web.admin.teacher-access.index',
            'active' => 'web.admin.teacher-access.*',
            'visible' => $surfacePrefix === 'web' && $user->hasPermission('academics.manage_teacher_access'),
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $theme->value }}" style="{{ $themeService->cssVariables($themeTokens) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title') | {{ $schoolName }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="portal-page" data-portal-surface="{{ $surface->value }}">
        <div class="portal-shell">
            <aside class="portal-sidebar">
                <a class="portal-brand" href="{{ route($surfacePrefix.'.dashboard') }}">
                    <span class="brand-mark">BC</span>
                    <span>
                        <strong>{{ $schoolName }}</strong>
                        <small>{{ $surface->label() }}</small>
                    </span>
                </a>

                <nav class="portal-navigation" aria-label="Primary navigation">
                    @foreach ($navItems as $item)
                        @continue(! $item['visible'])
                        <a
                            @class(['is-active' => request()->routeIs($item['active'])])
                            href="{{ route($item['route']) }}"
                            @if (request()->routeIs($item['active'])) aria-current="page" @endif
                        >{{ $item['label'] }}</a>
                    @endforeach
                </nav>

                <div class="portal-user-summary">
                    <strong>{{ $user->fullName() }}</strong>
                    <span>{{ $user->roleLabel() }}</span>
                    <form method="POST" action="{{ route($surfacePrefix.'.logout') }}">
                        @csrf
                        <button class="text-button" type="submit">Sign out</button>
                    </form>
                </div>
            </aside>

            <div class="portal-workspace">
                <header class="portal-topbar">
                    <div>
                        <p class="eyebrow">{{ $user->roleLabel() }}</p>
                        <strong>{{ $surface->label() }}</strong>
                    </div>
                    <div class="portal-theme-controls">
                        @if ($themeService->userSelectionAllowed())
                            <form method="POST" action="{{ route($surfacePrefix.'.theme-preference.update') }}">
                                @csrf
                                @method('PUT')
                                <label><span class="sr-only">Theme</span><select name="preferred_theme" onchange="this.form.submit()"><option value="classic" @selected($theme === \App\Enums\ThemeMode::Classic)>Classic</option><option value="dark" @selected($theme === \App\Enums\ThemeMode::Dark)>Dark</option></select></label>
                            </form>
                        @else
                            <span class="theme-indicator">{{ $theme->label() }} theme</span>
                        @endif
                    </div>
                </header>

                <main class="portal-main">
                    @if (session('status'))
                        <div class="notice notice-success" role="status">{{ session('status') }}</div>
                    @endif

                    @if ($errors->any())
                        <div class="notice notice-error" role="alert">
                            <strong>Please review the highlighted information.</strong>
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (session('generated_credentials'))
                        <section class="credential-notice" aria-labelledby="credential-heading">
                            <div>
                                <p class="eyebrow">Display once</p>
                                <h2 id="credential-heading">Temporary login credentials</h2>
                                <p>Copy these credentials now. The temporary passwords are not retained in plaintext.</p>
                            </div>
                            <div class="credential-list">
                                @foreach (session('generated_credentials') as $credential)
                                    <article>
                                        <strong>{{ $credential['name'] }}</strong>
                                        <span>{{ str($credential['audience'])->headline() }}</span>
                                        <code>{{ $credential['identifier'] }}</code>
                                        <code>{{ $credential['temporary_password'] }}</code>
                                    </article>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    @if (session('generated_result_pin'))
                        @php $resultPin = session('generated_result_pin'); @endphp
                        <section class="credential-notice result-pin-notice" aria-labelledby="result-pin-heading">
                            <div>
                                <p class="eyebrow">Display once</p>
                                <h2 id="result-pin-heading">Public result-checker PIN</h2>
                                <p>Copy this PIN now. Only its secure hash is retained after this page.</p>
                            </div>
                            <div class="credential-list">
                                <article>
                                    <strong>{{ $resultPin['student'] }}</strong>
                                    <span>{{ $resultPin['term'] }}</span>
                                    <code>{{ $resultPin['admission_no'] }}</code>
                                    <code>{{ $resultPin['pin'] }}</code>
                                </article>
                            </div>
                        </section>
                    @endif

                    @yield('content')
                </main>
            </div>
        </div>

        <nav class="portal-mobile-nav" aria-label="Mobile navigation">
            @foreach ($navItems as $item)
                @continue(! $item['visible'])
                <a
                    @class(['is-active' => request()->routeIs($item['active'])])
                    href="{{ route($item['route']) }}"
                    @if (request()->routeIs($item['active'])) aria-current="page" @endif
                >{{ $item['label'] }}</a>
            @endforeach
        </nav>
    </body>
</html>
