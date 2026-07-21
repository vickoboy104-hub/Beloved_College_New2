<?php

namespace Tests\Feature\Communication;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Communication\CommunicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_read_and_remove_own_notification(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $student = User::factory()->role(UserRole::Student)->create();
        app(CommunicationService::class)->createAnnouncement($admin, [
            'title' => 'Portal Reminder',
            'body' => 'Please review your portal before Friday.',
            'priority' => 'normal',
            'audience_mode' => 'targeted',
            'user_ids' => [$student->id],
            'portal_enabled' => true,
            'email_enabled' => false,
            'dispatch_now' => true,
        ]);
        $notification = $student->notifications()->firstOrFail();

        $this->actingAs($student)
            ->get($this->appUrl('/notifications'))
            ->assertOk()
            ->assertSee('Portal Reminder')
            ->assertSee('Unread');

        $this->actingAs($student)
            ->patch($this->appUrl('/notifications/'.$notification->id.'/read'))
            ->assertRedirect();
        $this->assertNotNull($notification->fresh()->read_at);

        $this->actingAs($student)
            ->delete($this->appUrl('/notifications/'.$notification->id))
            ->assertRedirect();
        $this->assertSame(0, $student->notifications()->count());
    }

    public function test_user_cannot_modify_another_users_notification(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $first = User::factory()->role(UserRole::Student)->create();
        $second = User::factory()->role(UserRole::Student)->create();
        app(CommunicationService::class)->createAnnouncement($admin, [
            'title' => 'Private Student Notice',
            'body' => 'This notice belongs to one student.',
            'priority' => 'normal',
            'audience_mode' => 'targeted',
            'user_ids' => [$first->id],
            'portal_enabled' => true,
            'email_enabled' => false,
            'dispatch_now' => true,
        ]);
        $notification = $first->notifications()->firstOrFail();

        $this->actingAs($second)
            ->patch($this->appUrl('/notifications/'.$notification->id.'/read'))
            ->assertNotFound();
        $this->actingAs($second)
            ->delete($this->appUrl('/notifications/'.$notification->id))
            ->assertNotFound();
        $this->assertNull($notification->fresh()->read_at);
    }

    private function appUrl(string $path): string
    {
        return 'http://'.config('platform.hosts.app').$path;
    }
}
