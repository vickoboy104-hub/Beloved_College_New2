<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cms_pages')) {
            Schema::create('cms_pages', function (Blueprint $table): void {
                $table->id();
                $table->string('slug')->unique();
                $table->string('title');
                $table->string('eyebrow')->nullable();
                $table->string('headline')->nullable();
                $table->text('summary')->nullable();
                $table->longText('body')->nullable();
                $table->json('sections')->nullable();
                $table->string('seo_title')->nullable();
                $table->text('seo_description')->nullable();
                $table->boolean('is_published')->default(false)->index();
                $table->timestamp('published_at')->nullable()->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('website_media')) {
            Schema::create('website_media', function (Blueprint $table): void {
                $table->id();
                $table->string('collection')->index();
                $table->string('title')->nullable();
                $table->string('alt_text')->nullable();
                $table->text('caption')->nullable();
                $table->string('media_type')->default('image')->index();
                $table->string('path')->nullable();
                $table->string('external_url', 1500)->nullable();
                $table->unsignedInteger('sort_order')->default(0)->index();
                $table->boolean('is_published')->default(true)->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('testimonials')) {
            Schema::create('testimonials', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('role')->nullable();
                $table->text('quote');
                $table->string('photo_path')->nullable();
                $table->unsignedInteger('sort_order')->default(0)->index();
                $table->boolean('is_published')->default(true)->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('newsletter_subscribers')) {
            Schema::create('newsletter_subscribers', function (Blueprint $table): void {
                $table->id();
                $table->string('email')->unique();
                $table->string('name')->nullable();
                $table->boolean('consent')->default(false)->index();
                $table->string('consent_version')->nullable();
                $table->timestamp('consented_at')->nullable();
                $table->timestamp('unsubscribed_at')->nullable()->index();
                $table->string('source')->default('public_website');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('theme_revisions')) {
            Schema::create('theme_revisions', function (Blueprint $table): void {
                $table->id();
                $table->string('mode')->index();
                $table->json('tokens');
                $table->text('notes')->nullable();
                $table->boolean('is_published')->default(false)->index();
                $table->timestamp('published_at')->nullable()->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Public content, subscriptions, media history and theme revisions are intentionally preserved.
    }
};
