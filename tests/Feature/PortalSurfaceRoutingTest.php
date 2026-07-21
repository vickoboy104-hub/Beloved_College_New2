<?php

namespace Tests\Feature;

use App\Enums\PortalSurface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalSurfaceRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_host_resolves_to_the_public_website_surface(): void
    {
        $response = $this->get($this->urlForHost(config('platform.hosts.public')));

        $response
            ->assertOk()
            ->assertViewIs('public.home')
            ->assertViewHas('portalSurface', PortalSurface::PublicSite)
            ->assertSee('Learning with purpose');
    }

    public function test_web_portal_host_resolves_to_the_full_web_surface(): void
    {
        $response = $this->get($this->urlForHost(config('platform.hosts.web')));

        $response
            ->assertOk()
            ->assertViewIs('surfaces.status')
            ->assertViewHas('portalSurface', PortalSurface::WebPortal)
            ->assertSee('data-portal-surface="web"', false);
    }

    public function test_app_portal_host_resolves_to_the_mobile_surface(): void
    {
        $response = $this->get($this->urlForHost(config('platform.hosts.app')));

        $response
            ->assertOk()
            ->assertViewIs('surfaces.status')
            ->assertViewHas('portalSurface', PortalSurface::AppPortal)
            ->assertSee('data-portal-surface="app"', false);
    }

    private function urlForHost(string $host): string
    {
        return "http://{$host}/";
    }
}
