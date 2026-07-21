<?php

namespace Tests\Feature;

use App\Enums\PortalSurface;
use Tests\TestCase;

class PortalSurfaceRoutingTest extends TestCase
{
    public function test_public_host_resolves_to_the_public_surface(): void
    {
        $response = $this
            ->withServerVariables(['HTTP_HOST' => config('platform.hosts.public')])
            ->get('/');

        $response
            ->assertOk()
            ->assertViewIs('surfaces.status')
            ->assertViewHas('portalSurface', PortalSurface::PublicSite)
            ->assertSee('data-portal-surface="public"', escape: false);
    }

    public function test_web_portal_host_resolves_to_the_full_web_surface(): void
    {
        $response = $this
            ->withServerVariables(['HTTP_HOST' => config('platform.hosts.web')])
            ->get('/');

        $response
            ->assertOk()
            ->assertViewIs('surfaces.status')
            ->assertViewHas('portalSurface', PortalSurface::WebPortal)
            ->assertSee('data-portal-surface="web"', escape: false);
    }

    public function test_app_portal_host_resolves_to_the_mobile_surface(): void
    {
        $response = $this
            ->withServerVariables(['HTTP_HOST' => config('platform.hosts.app')])
            ->get('/');

        $response
            ->assertOk()
            ->assertViewIs('surfaces.status')
            ->assertViewHas('portalSurface', PortalSurface::AppPortal)
            ->assertSee('data-portal-surface="app"', escape: false);
    }
}
