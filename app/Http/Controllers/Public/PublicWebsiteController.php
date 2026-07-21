<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Testimonial;
use App\Models\WebsiteMedia;
use App\Services\Website\PublicWebsiteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicWebsiteController extends Controller
{
    public function home(PublicWebsiteService $website): View
    {
        return view('public.home', $website->homepage());
    }

    public function about(PublicWebsiteService $website): View
    {
        return view('public.page', [
            'page' => $website->page('about'),
            'settings' => \App\Models\Setting::publicSettings(),
        ]);
    }

    public function admissions(PublicWebsiteService $website): View
    {
        return view('public.page', [
            'page' => $website->page('admissions'),
            'settings' => \App\Models\Setting::publicSettings(),
        ]);
    }

    public function contact(PublicWebsiteService $website): View
    {
        return view('public.contact', [
            'page' => $website->page('contact'),
            'settings' => \App\Models\Setting::publicSettings(),
        ]);
    }

    public function storeContact(Request $request, PublicWebsiteService $website): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:255', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:50', 'required_without:email'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:10000'],
        ]);
        $website->storeContactMessage($data);

        return back()->with('status', 'Thank you. Your message has been received by the school office.');
    }

    public function news(PublicWebsiteService $website): View
    {
        return view('public.news.index', [
            'announcements' => Announcement::query()
                ->with('author')
                ->where('is_published', true)
                ->where(fn ($query) => $query->whereNull('published_at')->orWhere('published_at', '<=', now()))
                ->orderByDesc('published_at')
                ->latest()
                ->paginate(12),
            'settings' => \App\Models\Setting::publicSettings(),
        ]);
    }

    public function article(string $slug): View
    {
        $announcement = Announcement::query()
            ->with('author')
            ->where('slug', $slug)
            ->where('is_published', true)
            ->where(fn ($query) => $query->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->firstOrFail();

        return view('public.news.show', [
            'announcement' => $announcement,
            'settings' => \App\Models\Setting::publicSettings(),
        ]);
    }

    public function gallery(PublicWebsiteService $website): View
    {
        return view('public.gallery', [
            'gallery' => $website->media('gallery'),
            'settings' => \App\Models\Setting::publicSettings(),
        ]);
    }

    public function subscribe(Request $request, PublicWebsiteService $website): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
            'consent' => ['accepted'],
        ]);
        $website->subscribe($data);

        return back()->with('status', 'Subscription confirmed. You can ask the school office to remove your email at any time.');
    }

    public function media(WebsiteMedia $media): StreamedResponse|BinaryFileResponse
    {
        abort_unless($media->is_published && filled($media->path), 404);
        abort_unless(Storage::disk('local')->exists($media->path), 404);

        return Storage::disk('local')->response(
            $media->path,
            basename($media->path),
            [
                'Cache-Control' => 'public, max-age=86400',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    public function testimonialPhoto(Testimonial $testimonial): StreamedResponse|BinaryFileResponse
    {
        abort_unless($testimonial->is_published && filled($testimonial->photo_path), 404);
        abort_unless(Storage::disk('local')->exists($testimonial->photo_path), 404);

        return Storage::disk('local')->response(
            $testimonial->photo_path,
            basename($testimonial->photo_path),
            [
                'Cache-Control' => 'public, max-age=86400',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
