@php
    $surface = $surface ?? $portalSurface;
    $theme = auth()->user()->effectiveTheme();
    $surfacePrefix = $surface === \App\Enums\PortalSurface::AppPortal ? 'app' : 'web';
    $user = auth()->user();
    $navItems = [
        [
            'label' => 'Dashboard',
            'route' => $surfacePrefix.'.dashboard',
            'active' => $surfacePrefix.'.dashboard',
            'visible' => true,
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
            'label' => 'Teacher Access',
            'route' => 'web.admin.teacher-access.index',
            'active' => 'web.admin.teacher-access.*',
            'visible' => $surfacePrefix === 'web' && $user->hasPermission('academics.manage_teacher_access'),
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $theme->value }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title') | {{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="portal-page" data-portal-surface="{{ $surface->value }}">
        <div class="portal-shell">
            <aside class="portal-sidebar">
                <a class="portal-brand" href="{{ route($surfacePrefix.'.dashboard') }}">
                    <span class="brand-mark">BC</span>
                    <span>
                        <strong>Beloved College</strong>
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
                    <span class="theme-indicator">{{ $theme->label() }} theme</span>
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
