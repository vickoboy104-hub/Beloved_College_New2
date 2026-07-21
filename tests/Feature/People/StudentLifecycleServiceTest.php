<?php

namespace Tests\Feature\People;

use App\Enums\UserRole;
use App\Models\AcademicSession;
use App\Models\FeeItem;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\People\StudentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StudentLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_student_parent_credentials_and_mandatory_invoice_atomically(): void
    {
        $session = AcademicSession::query()->create([
            'name' => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-07-31',
            'promotion_pass_mark' => 40,
            'is_current' => true,
        ]);
        $class = SchoolClass::query()->create([
            'name' => 'JSS 1',
            'slug' => 'jss-1-a',
            'section' => 'A',
        ]);
        $fee = FeeItem::query()->create([
            'name' => 'Registration Fee',
            'academic_session_id' => $session->id,
            'school_class_id' => $class->id,
            'amount' => 25000,
            'is_mandatory' => true,
        ]);

        $result = app(StudentService::class)->create([
            'first_name' => 'Ada',
            'middle_name' => 'Grace',
            'last_name' => 'Okafor',
            'email' => null,
            'phone' => '08010000001',
            'school_class_id' => $class->id,
            'parent_name' => 'Mrs Okafor',
            'parent_email' => 'parent@example.com',
            'parent_phone' => '08010000002',
            'gender' => 'female',
            'medical_notes' => 'No known allergies.',
        ]);

        $student = $result->student->fresh([
            'user',
            'parent',
            'feeInvoices.feeItem',
        ]);

        $this->assertSame('Ada Grace Okafor', $student->user->fullName());
        $this->assertSame($session->id, $student->academic_session_id);
        $this->assertSame($class->id, $student->school_class_id);
        $this->assertSame(UserRole::Student, $student->user->role);
        $this->assertTrue($student->user->must_change_password);
        $this->assertSame(UserRole::Parent, $student->parent->role);
        $this->assertTrue($student->parent->must_change_password);
        $this->assertCount(2, $result->credentials);
        $this->assertSame('student', $result->credentials[0]->audience);
        $this->assertSame('parent', $result->credentials[1]->audience);
        $this->assertCount(1, $student->feeInvoices);
        $this->assertSame($fee->id, $student->feeInvoices->first()->fee_item_id);
        $this->assertSame('25000.00', $student->feeInvoices->first()->balance);
        $this->assertFalse(Schema::hasColumn('users', 'temp_password_plaintext'));
    }

    public function test_student_archive_and_restore_preserve_the_record(): void
    {
        AcademicSession::query()->create([
            'name' => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-07-31',
            'promotion_pass_mark' => 40,
            'is_current' => true,
        ]);
        $actor = User::factory()->role(UserRole::Admin)->create();
        $student = app(StudentService::class)->create([
            'first_name' => 'Tunde',
            'last_name' => 'Adebayo',
        ])->student;

        app(StudentService::class)->archive($student, $actor, 'Transferred to another school.');

        $student->refresh();
        $this->assertTrue($student->isArchived());
        $this->assertTrue($student->user->isArchived());
        $this->assertSame('inactive', $student->status);
        $this->assertDatabaseHas('students', ['id' => $student->id]);

        app(StudentService::class)->restore($student);

        $student->refresh();
        $this->assertFalse($student->isArchived());
        $this->assertFalse($student->user->isArchived());
        $this->assertSame('active', $student->status);
    }
}
