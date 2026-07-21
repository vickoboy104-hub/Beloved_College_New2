<?php

namespace Tests\Feature\Website;

use App\Models\Announcement;
use App\Models\ContactMessage;
use App\Models\NewsletterSubscriber;
use App\Models\Setting;
use App\Notifications\ContactMessageReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PublicWebsiteWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Setting::flushCache();

        parent::tearDown();
    }

    public function test_public_pages_render_from_fallback_content(): void
    {
        foreach ([
            '/' => 'Learning with purpose',
            '/about' => 'Education that forms the whole student',
            '/admissions' => 'A clear path into the right learning programme',
            '/contact' => 'Questions, visits and admissions enquiries are welcome',
            '/news' => 'News and announcements',
            '/gallery' => 'School gallery',
            '/result-checker' => 'Check a published result',
        ] as $path => $expected) {
            $this->get($this->publicUrl($path))
                ->assertOk()
                ->assertSee($expected);
        }
    }

    public function test_contact_message_is_stored_and_queued_for_configured_recipient(): void
    {
        Notification::fake();
        Setting::setMany([
            'school_email' => 'school@beloved.example',
            'contact_recipient_email' => 'enquiries@beloved.example',
        ], 'website');

        $this->post($this->publicUrl('/contact'), [
            'name' => 'Ada Parent',
            'email' => 'ada@example.com',
            'phone' => '08030000000',
            'subject' => 'Admission enquiry',
            'message' => 'Please share the admission requirements for the next academic session.',
        ])->assertRedirect();

        $message = ContactMessage::query()->firstOrFail();
        $this->assertSame('new', $message->status);
        $this->assertSame('Admission enquiry', $message->subject);
        Notification::assertSentOnDemand(ContactMessageReceived::class, function (
            ContactMessageReceived $notification,
            array $channels,
            object $notifiable,
        ) use ($message): bool {
            return $notification->contactMessage->is($message)
                && in_array('mail', $channels, true)
                && data_get($notifiable, 'routes.mail') === 'enquiries@beloved.example';
        });
    }

    public function test_newsletter_records_consent_and_resubscription(): void
    {
        $payload = [
            'name' => 'Tolu Parent',
            'email' => 'TOLU@example.com',
            'consent' => '1',
        ];

        $this->post($this->publicUrl('/newsletter'), $payload)->assertRedirect();
        $subscriber = NewsletterSubscriber::query()->firstOrFail();
        $this->assertSame('tolu@example.com', $subscriber->email);
        $this->assertTrue($subscriber->consent);
        $this->assertNotNull($subscriber->consented_at);

        $subscriber->update(['consent' => false, 'unsubscribed_at' => now()]);
        $this->post($this->publicUrl('/newsletter'), $payload)->assertRedirect();

        $subscriber->refresh();
        $this->assertTrue($subscriber->consent);
        $this->assertNull($subscriber->unsubscribed_at);
        $this->assertSame(1, NewsletterSubscriber::query()->count());
    }

    public function test_only_published_due_news_is_public(): void
    {
        Announcement::query()->create([
            'title' => 'Published Open Day',
            'slug' => 'published-open-day',
            'body' => 'Families are invited to visit the school.',
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);
        Announcement::query()->create([
            'title' => 'Future Notice',
            'slug' => 'future-notice',
            'body' => 'This should remain hidden.',
            'is_published' => true,
            'published_at' => now()->addDay(),
        ]);
        Announcement::query()->create([
            'title' => 'Draft Notice',
            'slug' => 'draft-notice',
            'body' => 'This should remain hidden.',
            'is_published' => false,
        ]);

        $this->get($this->publicUrl('/news'))
            ->assertOk()
            ->assertSee('Published Open Day')
            ->assertDontSee('Future Notice')
            ->assertDontSee('Draft Notice');

        $this->get($this->publicUrl('/news/published-open-day'))
            ->assertOk()
            ->assertSee('Families are invited');
        $this->get($this->publicUrl('/news/future-notice'))->assertNotFound();
    }

    private function publicUrl(string $path): string
    {
        return 'http://'.config('platform.hosts.public').$path;
    }
}
