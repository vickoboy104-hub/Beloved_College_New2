<?php

namespace Tests\Feature\Media;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivateProfileMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_linked_parent_can_view_student_private_avatar(): void
    {
        $parent = User::factory()->role(UserRole::Parent)->create();
        $studentUser = User::factory()->role(UserRole::Student)->create([
            'avatar_path' => 'profile-media/student.jpg',
        ]);
        Student::query()->create([
            'user_id' => $studentUser->id,
            'parent_user_id' => $parent->id,
            'admission_no' => 'ADM-26-MEDIA1',
            'status' => 'active',
        ]);
        Storage::disk('local')->put('profile-media/student.jpg', 'private-image-content');

        $this->actingAs($parent)
            ->get($this->appUrl("/private-media/users/{$studentUser->id}/avatar"))
            ->assertOk()
            ->assertHeader('Cache-Control', 'private, max-age=300');
    }

    public function test_unassigned_teacher_cannot_view_student_private_avatar(): void
    {
        $teacher = User::factory()->role(UserRole::Teacher)->create();
        $studentUser = User::factory()->role(UserRole::Student)->create([
            'avatar_path' => 'profile-media/student.jpg',
        ]);
        Student::query()->create([
            'user_id' => $studentUser->id,
            'admission_no' => 'ADM-26-MEDIA2',
            'status' => 'active',
        ]);
        Storage::disk('local')->put('profile-media/student.jpg', 'private-image-content');

        $this->actingAs($teacher)
            ->get($this->webUrl("/private-media/users/{$studentUser->id}/avatar"))
            ->assertForbidden();
    }

    public function test_admin_can_view_student_private_avatar(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $studentUser = User::factory()->role(UserRole::Student)->create([
            'avatar_path' => 'profile-media/student.jpg',
        ]);
        Student::query()->create([
            'user_id' => $studentUser->id,
            'admission_no' => 'ADM-26-MEDIA3',
            'status' => 'active',
        ]);
        Storage::disk('local')->put('profile-media/student.jpg', 'private-image-content');

        $this->actingAs($admin)
            ->get($this->webUrl("/private-media/users/{$studentUser->id}/avatar"))
            ->assertOk();
    }

    private function webUrl(string $path): string
    {
        return 'http://'.config('platform.hosts.web').$path;
    }

    private function appUrl(string $path): string
    {
        return 'http://'.config('platform.hosts.app').$path;
    }
}
