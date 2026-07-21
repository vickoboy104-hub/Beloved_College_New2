<?php

namespace Tests\Feature\Migration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublicCmsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_cms_newsletter_media_and_theme_tables_exist(): void
    {
        foreach ([
            'cms_pages' => ['slug', 'title', 'sections', 'is_published', 'published_at'],
            'website_media' => ['collection', 'media_type', 'path', 'external_url', 'sort_order', 'is_published'],
            'testimonials' => ['name', 'quote', 'photo_path', 'sort_order', 'is_published'],
            'newsletter_subscribers' => ['email', 'consent', 'consent_version', 'consented_at', 'unsubscribed_at'],
            'theme_revisions' => ['mode', 'tokens', 'is_published', 'published_at', 'created_by'],
        ] as $table => $columns) {
            $this->assertTrue(Schema::hasTable($table), $table.' table is missing.');
            $this->assertTrue(Schema::hasColumns($table, $columns), $table.' is missing required columns.');
        }
    }
}
