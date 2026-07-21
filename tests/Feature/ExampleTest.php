<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_returns_the_public_website(): void
    {
        $this->get('http://'.config('platform.hosts.public').'/')
            ->assertOk()
            ->assertViewIs('public.home')
            ->assertSee('Learning with purpose');
    }
}
