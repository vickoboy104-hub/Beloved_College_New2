<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\StaffProfile;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_sign_in_with_email(): void
    {
        $user = User::factory()->role(UserRole::Student)->create();

        $response = $this->post($this->webUrl('/login'), [
            'login' => $user->email,
            'password' => 'password',
            'audience' => 'generic',
        ]);

        $response->assertRedirect(route('web.dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
    }

    public function test_student_can_sign_in_with_admission_number(): void
    {
        $user = User::factory()->role(UserRole::Student)->create(['email' => null]);
        Student::query()->create([
            'user_id' => $user->id,
            'admission_no' => 'BC/2026/001',
            'student_id_no' => 'STU-001',
            'status' => 'active',
        ]);

        $response = $this->post($this->appUrl('/login'), [
            'login' => 'BC/2026/001',
            'password' => 'password',
            'audience' => 'student',
        ]);

        $response->assertRedirect(route('app.dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
    }

    public function test_student_can_sign_in_with_student_id(): void
    {
        $user = User::factory()->role(UserRole::Student)->create(['email' => null]);
        Student::query()->create([
            'user_id' => $user->id,
            'admission_no' => 'BC/2026/002',
            'student_id_no' => 'STU-002',
            'status' => 'active',
        ]);

        $this->post($this->appUrl('/login'), [
            'login' => 'STU-002',
            'password' => 'password',
            'audience' => 'student',
        ])->assertRedirect(route('app.dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_staff_can_sign_in_with_employee_number(): void
    {
        $user = User::factory()->role(UserRole::Teacher)->create();
        StaffProfile::query()->create([
            'user_id' => $user->id,
            'employee_no' => 'EMP-2026-001',
            'status' => 'active',
        ]);

        $this->post($this->webUrl('/login'), [
            'login' => 'EMP-2026-001',
            'password' => 'password',
            'audience' => 'staff',
        ])->assertRedirect(route('web.dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_student_login_rejects_staff_accounts(): void
    {
        $user = User::factory()->role(UserRole::Teacher)->create();

        $this->from($this->appUrl('/login/student'))
            ->post($this->appUrl('/login'), [
                'login' => $user->email,
                'password' => 'password',
                'audience' => 'student',
            ])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_inactive_account_cannot_sign_in(): void
    {
        $user = User::factory()->role(UserRole::Student)->inactive()->create();

        $this->from($this->appUrl('/login/student'))
            ->post($this->appUrl('/login'), [
                'login' => $user->email,
                'password' => 'password',
                'audience' => 'student',
            ])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
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
