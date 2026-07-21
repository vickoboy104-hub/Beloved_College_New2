<?php

namespace App\Services\Website;

use App\Models\Announcement;
use App\Models\CmsPage;
use App\Models\ContactMessage;
use App\Models\NewsletterSubscriber;
use App\Models\Setting;
use App\Models\StaffProfile;
use App\Models\Student;
use App\Models\Testimonial;
use App\Models\User;
use App\Models\WebsiteMedia;
use App\Notifications\ContactMessageReceived;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class PublicWebsiteService
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function pageDefaults(): array
    {
        return [
            'home' => [
                'title' => 'Beloved College',
                'eyebrow' => 'Welcome to Beloved College',
                'headline' => 'Learning with purpose. Growing with character.',
                'summary' => 'A disciplined and caring school community helping students build knowledge, confidence, creativity and responsible leadership.',
                'body' => 'Beloved College provides a supportive learning environment where academic growth, moral formation and practical skills develop together.',
                'sections' => [
                    'programs' => [
                        ['title' => 'Junior Secondary', 'text' => 'A strong foundation in literacy, numeracy, science, technology, arts and character.'],
                        ['title' => 'Senior Secondary', 'text' => 'Focused preparation across Science, Commercial and Arts pathways.'],
                        ['title' => 'Student Development', 'text' => 'Leadership, creativity, communication, service and responsible citizenship.'],
                    ],
                    'welcome_title' => 'A school community where every learner matters',
                    'welcome_body' => 'We combine clear expectations, attentive teaching and meaningful opportunities so students can discover their strengths and prepare for the future.',
                    'admission_title' => 'Admissions are open',
                    'admission_body' => 'Speak with the school office to learn about available classes, admission requirements and the next steps for your child.',
                ],
                'seo_title' => 'Beloved College | Purposeful Learning and Character',
                'seo_description' => 'Beloved College offers disciplined, caring and future-focused education for Junior and Senior Secondary students.',
            ],
            'about' => [
                'title' => 'About Beloved College',
                'eyebrow' => 'Our School',
                'headline' => 'Education that forms the whole student.',
                'summary' => 'Beloved College exists to develop knowledgeable, confident, disciplined and compassionate young people.',
                'body' => "Our approach brings academic instruction, character formation, creativity and practical responsibility into one learning experience.\n\nStudents are supported by teachers and administrators who value clear standards, individual progress and partnership with families.",
                'sections' => [
                    'values' => [
                        ['title' => 'Excellence', 'text' => 'We pursue careful work, steady improvement and high standards.'],
                        ['title' => 'Character', 'text' => 'Integrity, discipline, respect and responsibility guide school life.'],
                        ['title' => 'Service', 'text' => 'Students learn to contribute meaningfully to their families and communities.'],
                    ],
                ],
                'seo_title' => 'About Beloved College',
                'seo_description' => 'Learn about the mission, values and educational approach of Beloved College.',
            ],
            'admissions' => [
                'title' => 'Admissions',
                'eyebrow' => 'Join Beloved College',
                'headline' => 'A clear path into the right learning programme.',
                'summary' => 'Admissions information for Junior Secondary and Senior Secondary students in Science, Commercial and Arts pathways.',
                'body' => "Contact the school office to confirm current openings, entrance requirements, interview dates and required documents.\n\nThe admissions team will guide each family through the appropriate class placement and registration process.",
                'sections' => [
                    'pathways' => [
                        ['title' => 'Junior Secondary School', 'text' => 'Broad foundational learning for JSS students.'],
                        ['title' => 'Senior Secondary — Science', 'text' => 'Preparation for science, technology, engineering and health-related pathways.'],
                        ['title' => 'Senior Secondary — Commercial', 'text' => 'Business, accounting, economics and entrepreneurship preparation.'],
                        ['title' => 'Senior Secondary — Arts', 'text' => 'Humanities, languages, social sciences and creative development.'],
                    ],
                ],
                'seo_title' => 'Admissions | Beloved College',
                'seo_description' => 'Admission information for Junior and Senior Secondary programmes at Beloved College.',
            ],
            'contact' => [
                'title' => 'Contact Beloved College',
                'eyebrow' => 'Speak with the School',
                'headline' => 'Questions, visits and admissions enquiries are welcome.',
                'summary' => 'Send a message to the school office or use the published telephone, email and WhatsApp details.',
                'body' => 'Messages submitted through this page are stored securely for the school team and may also be delivered to the configured contact email queue.',
                'sections' => [],
                'seo_title' => 'Contact Beloved College',
                'seo_description' => 'Contact the Beloved College school office for admissions, visits and general enquiries.',
            ],
        ];
    }

    public function page(string $slug, bool $publishedOnly = true): CmsPage
    {
        $query = CmsPage::query()->where('slug', $slug);

        if ($publishedOnly) {
            $query->where('is_published', true);
        }

        $page = $query->first();

        if ($page) {
            return $page;
        }

        $defaults = $this->pageDefaults()[$slug] ?? [
            'title' => str($slug)->headline()->toString(),
            'headline' => str($slug)->headline()->toString(),
            'sections' => [],
        ];

        return new CmsPage([
            'slug' => $slug,
            'is_published' => true,
            ...$defaults,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function homepage(): array
    {
        $settings = Setting::publicSettings();
        $liveStatistics = filter_var($settings['show_live_statistics'] ?? true, FILTER_VALIDATE_BOOL);

        return [
            'page' => $this->page('home'),
            'heroMedia' => $this->media('hero'),
            'gallery' => $this->media('gallery'),
            'testimonials' => Testimonial::query()
                ->where('is_published', true)
                ->orderBy('sort_order')
                ->latest()
                ->take(8)
                ->get(),
            'announcements' => $this->publishedAnnouncements(6),
            'stats' => $liveStatistics
                ? [
                    ['label' => 'Students', 'value' => Student::query()->whereNull('archived_at')->count()],
                    ['label' => 'Staff', 'value' => StaffProfile::query()->whereNull('archived_at')->count()],
                    ['label' => 'Programmes', 'value' => $settings['homepage_programme_count'] ?? 4],
                    ['label' => 'Portal Access', 'value' => $settings['homepage_portal_access_label'] ?? '24/7'],
                ]
                : [
                    ['label' => 'Students', 'value' => $settings['homepage_student_count'] ?? '0'],
                    ['label' => 'Staff', 'value' => $settings['homepage_staff_count'] ?? '0'],
                    ['label' => 'Programmes', 'value' => $settings['homepage_programme_count'] ?? '4'],
                    ['label' => 'Portal Access', 'value' => $settings['homepage_portal_access_label'] ?? '24/7'],
                ],
            'settings' => $settings,
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, WebsiteMedia>
     */
    public function media(string $collection)
    {
        return WebsiteMedia::query()
            ->where('collection', $collection)
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->latest()
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Announcement>
     */
    public function publishedAnnouncements(int $limit = 12)
    {
        return Announcement::query()
            ->with('author')
            ->where('is_published', true)
            ->where(function ($query): void {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            ->orderByDesc('published_at')
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function savePage(User $actor, string $slug, array $data): CmsPage
    {
        return DB::transaction(function () use ($actor, $slug, $data): CmsPage {
            $page = CmsPage::query()->firstOrNew(['slug' => $slug]);
            $page->fill([
                'title' => $data['title'],
                'eyebrow' => $data['eyebrow'] ?? null,
                'headline' => $data['headline'] ?? null,
                'summary' => $data['summary'] ?? null,
                'body' => $data['body'] ?? null,
                'sections' => $data['sections'] ?? [],
                'seo_title' => $data['seo_title'] ?? null,
                'seo_description' => $data['seo_description'] ?? null,
                'is_published' => (bool) ($data['is_published'] ?? false),
                'published_at' => ($data['is_published'] ?? false) ? ($page->published_at ?: now()) : null,
                'updated_by' => $actor->id,
            ]);

            if (! $page->exists) {
                $page->created_by = $actor->id;
            }

            $page->save();

            return $page->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function storeMedia(User $actor, array $data, ?UploadedFile $file = null): WebsiteMedia
    {
        $path = $file?->store('public-website/'.$data['collection'], 'local');

        return WebsiteMedia::query()->create([
            'collection' => $data['collection'],
            'title' => $data['title'] ?? null,
            'alt_text' => $data['alt_text'] ?? null,
            'caption' => $data['caption'] ?? null,
            'media_type' => $data['media_type'],
            'path' => $path,
            'external_url' => $data['external_url'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_published' => (bool) ($data['is_published'] ?? true),
            'created_by' => $actor->id,
        ]);
    }

    public function deleteMedia(WebsiteMedia $media): void
    {
        if ($media->path && str_starts_with($media->path, 'public-website/')) {
            Storage::disk('local')->delete($media->path);
        }

        $media->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function storeTestimonial(User $actor, array $data, ?UploadedFile $photo = null): Testimonial
    {
        return Testimonial::query()->create([
            'name' => $data['name'],
            'role' => $data['role'] ?? null,
            'quote' => $data['quote'],
            'photo_path' => $photo?->store('public-website/testimonials', 'local'),
            'sort_order' => $data['sort_order'] ?? 0,
            'is_published' => (bool) ($data['is_published'] ?? true),
            'created_by' => $actor->id,
        ]);
    }

    public function deleteTestimonial(Testimonial $testimonial): void
    {
        if ($testimonial->photo_path && str_starts_with($testimonial->photo_path, 'public-website/')) {
            Storage::disk('local')->delete($testimonial->photo_path);
        }

        $testimonial->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function storeContactMessage(array $data): ContactMessage
    {
        $message = ContactMessage::query()->create([
            ...$data,
            'status' => 'new',
        ]);
        $recipient = Setting::getValue('contact_recipient_email') ?: Setting::getValue('school_email');

        if ($recipient) {
            try {
                Notification::route('mail', $recipient)
                    ->notify(new ContactMessageReceived($message));
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $message;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function subscribe(array $data): NewsletterSubscriber
    {
        return NewsletterSubscriber::query()->updateOrCreate(
            ['email' => Str::lower(trim((string) $data['email']))],
            [
                'name' => $data['name'] ?? null,
                'consent' => true,
                'consent_version' => '2026-07-public-site-v1',
                'consented_at' => now(),
                'unsubscribed_at' => null,
                'source' => 'public_website',
            ],
        );
    }
}
