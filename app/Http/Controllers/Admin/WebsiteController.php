<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\CmsPage;
use App\Models\ContactMessage;
use App\Models\NewsletterSubscriber;
use App\Models\Setting;
use App\Models\Testimonial;
use App\Models\WebsiteMedia;
use App\Services\Website\PublicWebsiteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\View\View;

class WebsiteController extends Controller
{
    public function index(Request $request, PublicWebsiteService $website): View
    {
        $sections = ['overview', 'pages', 'media', 'news', 'testimonials', 'messages', 'newsletter', 'settings'];
        $activeSection = in_array($request->query('section'), $sections, true)
            ? $request->query('section')
            : 'overview';
        $pages = collect(array_keys($website->pageDefaults()))
            ->mapWithKeys(fn (string $slug) => [$slug => $website->page($slug, false)]);

        return view('admin.website.index', [
            'activeSection' => $activeSection,
            'pages' => $pages,
            'mediaItems' => WebsiteMedia::query()->with('creator')->orderBy('collection')->orderBy('sort_order')->get(),
            'announcements' => Announcement::query()->with('author')->latest()->get(),
            'testimonials' => Testimonial::query()->with('creator')->orderBy('sort_order')->latest()->get(),
            'messages' => ContactMessage::query()->latest()->paginate(30, ['*'], 'messages_page'),
            'subscribers' => NewsletterSubscriber::query()->latest('consented_at')->paginate(30, ['*'], 'subscribers_page'),
            'settings' => Setting::forAdminForm(),
            'counts' => [
                'published_pages' => CmsPage::query()->where('is_published', true)->count(),
                'published_news' => Announcement::query()->where('is_published', true)->count(),
                'public_media' => WebsiteMedia::query()->where('is_published', true)->count(),
                'new_messages' => ContactMessage::query()->where('status', 'new')->count(),
                'active_subscribers' => NewsletterSubscriber::query()->where('consent', true)->whereNull('unsubscribed_at')->count(),
            ],
        ]);
    }

    public function savePage(
        Request $request,
        string $slug,
        PublicWebsiteService $website,
    ): RedirectResponse {
        abort_unless(in_array($slug, ['home', 'about', 'admissions', 'contact'], true), 404);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'eyebrow' => ['nullable', 'string', 'max:255'],
            'headline' => ['nullable', 'string', 'max:500'],
            'summary' => ['nullable', 'string', 'max:3000'],
            'body' => ['nullable', 'string', 'max:30000'],
            'sections' => ['nullable', 'array'],
            'sections.*' => ['nullable'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'is_published' => ['nullable', 'boolean'],
        ]);
        $website->savePage($request->user(), $slug, [
            ...$data,
            'is_published' => $request->boolean('is_published'),
        ]);

        return back()->with('status', str($slug)->headline().' page saved successfully.');
    }

    public function storeMedia(Request $request, PublicWebsiteService $website): RedirectResponse
    {
        $data = $request->validate([
            'collection' => ['required', Rule::in(['hero', 'gallery', 'campus'])],
            'title' => ['nullable', 'string', 'max:255'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:2000'],
            'media_type' => ['required', Rule::in(['image', 'video'])],
            'file' => ['nullable', File::types(['jpg', 'jpeg', 'png', 'webp', 'mp4', 'webm'])->max(50 * 1024)],
            'external_url' => ['nullable', 'url', 'max:1500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        if (! $request->hasFile('file') && blank($data['external_url'] ?? null)) {
            return back()->withErrors(['file' => 'Upload a media file or provide an external media URL.'])->withInput();
        }

        $website->storeMedia(
            $request->user(),
            [
                ...$data,
                'is_published' => $request->boolean('is_published', true),
            ],
            $request->file('file'),
        );

        return back()->with('status', 'Website media added successfully.');
    }

    public function destroyMedia(WebsiteMedia $media, PublicWebsiteService $website): RedirectResponse
    {
        $website->deleteMedia($media);

        return back()->with('status', 'Website media removed successfully.');
    }

    public function storeAnnouncement(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('announcements', 'slug')],
            'excerpt' => ['nullable', 'string', 'max:2000'],
            'body' => ['nullable', 'string', 'max:30000'],
            'category' => ['nullable', 'string', 'max:100'],
            'is_published' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ]);
        $published = $request->boolean('is_published');

        Announcement::query()->create([
            ...$data,
            'slug' => $this->uniqueAnnouncementSlug($data['slug'] ?? $data['title']),
            'is_published' => $published,
            'published_at' => $published ? ($data['published_at'] ?? now()) : null,
            'author_id' => $request->user()->id,
        ]);

        return back()->with('status', 'News item created successfully.');
    }

    public function updateAnnouncement(Request $request, Announcement $announcement): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('announcements', 'slug')->ignore($announcement)],
            'excerpt' => ['nullable', 'string', 'max:2000'],
            'body' => ['nullable', 'string', 'max:30000'],
            'category' => ['nullable', 'string', 'max:100'],
            'is_published' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ]);
        $published = $request->boolean('is_published');
        $announcement->update([
            ...$data,
            'slug' => Str::slug($data['slug']),
            'is_published' => $published,
            'published_at' => $published ? ($data['published_at'] ?? $announcement->published_at ?? now()) : null,
        ]);

        return back()->with('status', 'News item updated successfully.');
    }

    public function destroyAnnouncement(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        return back()->with('status', 'News item deleted successfully.');
    }

    public function storeTestimonial(Request $request, PublicWebsiteService $website): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'role' => ['nullable', 'string', 'max:150'],
            'quote' => ['required', 'string', 'max:3000'],
            'photo' => ['nullable', File::image()->max(8 * 1024)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_published' => ['nullable', 'boolean'],
        ]);
        $website->storeTestimonial(
            $request->user(),
            [
                ...$data,
                'is_published' => $request->boolean('is_published', true),
            ],
            $request->file('photo'),
        );

        return back()->with('status', 'Testimonial added successfully.');
    }

    public function destroyTestimonial(Testimonial $testimonial, PublicWebsiteService $website): RedirectResponse
    {
        $website->deleteTestimonial($testimonial);

        return back()->with('status', 'Testimonial removed successfully.');
    }

    public function updateMessage(Request $request, ContactMessage $message): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['new', 'read', 'responded', 'archived'])],
        ]);
        $message->update([
            'status' => $data['status'],
            'responded_at' => $data['status'] === 'responded' ? now() : $message->responded_at,
        ]);

        return back()->with('status', 'Contact-message status updated.');
    }

    public function unsubscribe(NewsletterSubscriber $subscriber): RedirectResponse
    {
        $subscriber->update([
            'consent' => false,
            'unsubscribed_at' => now(),
        ]);

        return back()->with('status', 'Subscriber marked as unsubscribed.');
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'school_name' => ['required', 'string', 'max:255'],
            'school_short_name' => ['nullable', 'string', 'max:100'],
            'school_tagline' => ['nullable', 'string', 'max:500'],
            'school_email' => ['nullable', 'email', 'max:255'],
            'contact_recipient_email' => ['nullable', 'email', 'max:255'],
            'school_phone' => ['nullable', 'string', 'max:100'],
            'school_whatsapp' => ['nullable', 'string', 'max:100'],
            'school_address' => ['nullable', 'string', 'max:1000'],
            'facebook_url' => ['nullable', 'url', 'max:1000'],
            'instagram_url' => ['nullable', 'url', 'max:1000'],
            'youtube_url' => ['nullable', 'url', 'max:1000'],
            'x_url' => ['nullable', 'url', 'max:1000'],
            'campus_video_url' => ['nullable', 'url', 'max:1500'],
            'admissions_cta_label' => ['nullable', 'string', 'max:100'],
            'admissions_cta_url' => ['nullable', 'url', 'max:1500'],
            'hero_autoplay_seconds' => ['nullable', 'integer', 'min:3', 'max:30'],
            'show_live_statistics' => ['nullable', 'boolean'],
        ]);
        Setting::setMany([
            ...$data,
            'show_live_statistics' => $request->boolean('show_live_statistics', true) ? '1' : '0',
        ], 'website');

        return back()->with('status', 'Public website settings updated successfully.');
    }

    private function uniqueAnnouncementSlug(string $value): string
    {
        $base = Str::slug($value) ?: 'news';
        $slug = $base;
        $counter = 2;

        while (Announcement::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
