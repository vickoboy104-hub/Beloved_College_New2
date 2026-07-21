<?php

namespace Tests\Feature\Website;

use App\Enums\UserRole;
use App\Models\CmsPage;
use App\Models\Setting;
use App\Models\User;
use App\Models\WebsiteMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WebsiteCmsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Setting::flushCache();

        parent::tearDown();
    }

    public function test_admin_can_manage_website_but_principal_cannot(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $principal = User::factory()->role(UserRole::Principal)->create();

        $this->actingAs($admin)
            ->get($this->webUrl('/admin/website'))
            ->assertOk()
            ->assertSee('Website CMS');

        $this->actingAs($principal)
            ->get($this->webUrl('/admin/website'))
            ->assertForbidden();

        $this->actingAs($principal)
            ->get($this->webUrl('/admin/website/themes'))
            ->assertForbidden();
    }

    public function test_admin_can_publish_page_content_visible_on_public_site(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        $this->actingAs($admin)
            ->put($this->webUrl('/admin/website/pages/about'), [
                'title' => 'About Our College',
                'eyebrow' => 'Our Story',
                'headline' => 'A distinct education for responsible leaders.',
                'summary' => 'A public summary controlled by the CMS.',
                'body' => 'This body was published from the new Laravel CMS.',
                'sections' => [
                    'values' => [
                        ['title' => 'Integrity', 'text' => 'We do the right thing.'],
                    ],
                ],
                'seo_title' => 'About Our College',
                'seo_description' => 'CMS-managed About page.',
                'is_published' => '1',
            ])->assertSessionHasNoErrors();

        $page = CmsPage::query()->where('slug', 'about')->firstOrFail();
        $this->assertTrue($page->is_published);
        $this->assertNotNull($page->published_at);

        $this->get($this->publicUrl('/about'))
            ->assertOk()
            ->assertSee('A distinct education for responsible leaders')
            ->assertSee('This body was published from the new Laravel CMS')
            ->assertSee('Integrity');
    }

    public function test_published_uploaded_media_is_served_and_hidden_media_is_not(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        $this->actingAs($admin)
            ->post($this->webUrl('/admin/website/media'), [
                'collection' => 'gallery',
                'media_type' => 'image',
                'title' => 'Science Laboratory',
                'alt_text' => 'Students carrying out a supervised science experiment',
                'caption' => 'Practical science learning.',
                'sort_order' => 1,
                'is_published' => '1',
                'file' => UploadedFile::fake()->image('science-lab.jpg', 1200, 800),
            ])->assertSessionHasNoErrors();

        $media = WebsiteMedia::query()->firstOrFail();
        Storage::disk('local')->assertExists($media->path);

        $this->get($this->publicUrl('/media/'.$media->id))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $media->update(['is_published' => false]);
        $this->get($this->publicUrl('/media/'.$media->id))->assertNotFound();
    }

    public function test_admin_can_update_public_school_identity_settings(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        $this->actingAs($admin)
            ->put($this->webUrl('/admin/website/settings'), [
                'school_name' => 'Beloved College International',
                'school_short_name' => 'BCI',
                'school_tagline' => 'Knowledge, character and service',
                'school_email' => 'hello@beloved.example',
                'school_phone' => '+234 800 000 0000',
                'school_address' => 'Beloved College Campus, Nigeria',
                'admissions_cta_label' => 'Begin Admission Enquiry',
                'admissions_cta_url' => $this->publicUrl('/admissions'),
                'hero_autoplay_seconds' => 8,
                'show_live_statistics' => '1',
            ])->assertSessionHasNoErrors();

        $this->assertSame('Beloved College International', Setting::getValue('school_name'));
        $this->get($this->publicUrl('/'))
            ->assertOk()
            ->assertSee('Beloved College International')
            ->assertSee('Begin Admission Enquiry');
    }

    private function webUrl(string $path): string
    {
        return 'http://'.config('platform.hosts.web').$path;
    }

    private function publicUrl(string $path): string
    {
        return 'http://'.config('platform.hosts.public').$path;
    }
}
