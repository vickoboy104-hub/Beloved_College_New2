<?php

namespace Tests\Feature\Academics;

use App\Enums\UserRole;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use App\Services\Academics\TeacherAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherAccessWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_access_can_be_assigned_revoked_and_restored(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $teacher = User::factory()->role(UserRole::Teacher)->create();
        $class = SchoolClass::query()->create([
            'name' => 'SSS 1',
            'slug' => 'sss-1-a',
            'section' => 'A',
        ]);
        $subject = Subject::query()->create([
            'name' => 'Economics',
            'code' => 'ECO',
        ]);
        $service = app(TeacherAccessService::class);

        $assignment = $service->assign($admin, $teacher, $class->id, $subject->id);

        $this->assertTrue($service->canTeach($teacher, $class->id, $subject->id));
        $this->assertTrue($service->canManageClass($teacher, $class->id));
        $this->assertSame([$class->id => [$subject->id]], $service->classSubjectMap($teacher));

        $service->revoke($admin, $assignment);
        $this->assertFalse($service->canTeach($teacher, $class->id, $subject->id));

        $service->restore($admin, $assignment->fresh());
        $this->assertTrue($service->canTeach($teacher, $class->id, $subject->id));
    }

    public function test_principal_has_privileged_academic_visibility_without_assignments(): void
    {
        $principal = User::factory()->role(UserRole::Principal)->create();
        $class = SchoolClass::query()->create([
            'name' => 'JSS 2',
            'slug' => 'jss-2-b',
            'section' => 'B',
        ]);
        $subject = Subject::query()->create([
            'name' => 'English Language',
            'code' => 'ENG',
        ]);

        $service = app(TeacherAccessService::class);

        $this->assertTrue($service->canTeach($principal, $class->id, $subject->id));
        $this->assertNull($service->classIds($principal));
        $this->assertNull($service->subjectIds($principal));
    }
}
