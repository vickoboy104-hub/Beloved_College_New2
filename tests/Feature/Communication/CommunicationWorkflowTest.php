<?php

namespace Tests\Feature\Communication;

use App\Enums\AttendanceStatus;
use App\Enums\UserRole;
use App\Models\AnnouncementDelivery;
use App\Models\AttendanceRecord;
use App\Models\SchoolClass;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Services\Communication\CommunicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CommunicationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Setting::flushCache();

        parent::tearDown();
    }

    public function test_class_target_dispatches_to_student_and_linked_parent_once(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $class = SchoolClass::query()->create([
            'name' => 'JSS 2',
            'section' => 'A',
            'slug' => 'jss-2-a-communication',
        ]);
        $parent = User::factory()->role(UserRole::Parent)->create();
        $studentUser = User::factory()->role(UserRole::Student)->create();
        $unrelated = User::factory()->role(UserRole::Student)->create();
        Student::query()->create([
            'user_id' => $studentUser->id,
            'parent_user_id' => $parent->id,
            'admission_no' => 'ADM-COMM-001',
            'school_class_id' => $class->id,
            'status' => 'active',
        ]);
        $service = app(CommunicationService::class);
        $announcement = $service->createAnnouncement($admin, [
            'title' => 'JSS 2 Parent Meeting',
            'body' => 'Students and Parents in JSS 2 should attend the scheduled meeting.',
            'priority' => 'high',
            'audience_mode' => 'targeted',
            'class_ids' => [$class->id],
            'portal_enabled' => true,
            'email_enabled' => false,
            'dispatch_now' => true,
        ]);

        $this->assertSame('dispatched', $announcement->status);
        $this->assertSame(2, AnnouncementDelivery::query()->where('announcement_id', $announcement->id)->count());
        $this->assertSame(1, $studentUser->notifications()->count());
        $this->assertSame(1, $parent->notifications()->count());
        $this->assertSame(0, $unrelated->notifications()->count());
        $this->assertSame(0, $service->dispatch($announcement));
        $this->assertSame(2, AnnouncementDelivery::query()->where('announcement_id', $announcement->id)->count());
    }

    public function test_due_scheduled_role_announcement_is_dispatched_by_command(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $teacher = User::factory()->role(UserRole::Teacher)->create();
        $student = User::factory()->role(UserRole::Student)->create();
        $announcement = app(CommunicationService::class)->createAnnouncement($admin, [
            'title' => 'Teacher Briefing',
            'body' => 'All teachers should review the new reporting procedure.',
            'priority' => 'normal',
            'audience_mode' => 'targeted',
            'role_targets' => ['teacher'],
            'portal_enabled' => true,
            'email_enabled' => false,
            'starts_at' => now()->subMinute(),
            'dispatch_now' => false,
        ]);

        $this->assertSame('scheduled', $announcement->status);
        Artisan::call('communications:dispatch-scheduled');

        $this->assertSame('dispatched', $announcement->fresh()->status);
        $this->assertSame(1, $teacher->notifications()->count());
        $this->assertSame(0, $student->notifications()->count());
    }

    public function test_parent_absence_alert_is_idempotent(): void
    {
        Setting::setMany([
            'absence_notifications_enabled' => '1',
            'absence_email_enabled' => '0',
        ], 'communication');
        $teacher = User::factory()->role(UserRole::Teacher)->create();
        $parent = User::factory()->role(UserRole::Parent)->create();
        $studentUser = User::factory()->role(UserRole::Student)->create();
        $class = SchoolClass::query()->create([
            'name' => 'JSS 1',
            'section' => 'B',
            'slug' => 'jss-1-b-absence',
        ]);
        $student = Student::query()->create([
            'user_id' => $studentUser->id,
            'parent_user_id' => $parent->id,
            'admission_no' => 'ADM-ABS-001',
            'school_class_id' => $class->id,
            'status' => 'active',
        ]);
        $record = AttendanceRecord::query()->create([
            'school_class_id' => $class->id,
            'student_id' => $student->id,
            'taken_by' => $teacher->id,
            'attendance_date' => now()->toDateString(),
            'status' => AttendanceStatus::Absent,
            'note' => 'No prior notice received.',
        ]);
        $service = app(CommunicationService::class);

        $this->assertTrue($service->notifyAbsence($record));
        $this->assertNotNull($record->fresh()->absence_notified_at);
        $this->assertSame(1, $parent->notifications()->count());
        $this->assertFalse($service->notifyAbsence($record->fresh()));
        $this->assertSame(1, $parent->notifications()->count());
    }
}
