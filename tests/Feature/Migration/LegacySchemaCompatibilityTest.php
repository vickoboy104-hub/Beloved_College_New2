<?php

namespace Tests\Feature\Migration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacySchemaCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_verified_legacy_tables_exist(): void
    {
        $tables = [
            'users',
            'students',
            'staff_profiles',
            'academic_sessions',
            'terms',
            'school_classes',
            'subjects',
            'teacher_subject_assignments',
            'lessons',
            'assignments',
            'assignment_submissions',
            'assessments',
            'assessment_results',
            'attendance_records',
            'student_term_reports',
            'student_promotions',
            'cbt_questions',
            'cbt_question_options',
            'cbt_attempts',
            'cbt_answers',
            'fee_items',
            'fee_invoices',
            'payments',
            'announcements',
            'settings',
            'contact_messages',
            'audit_logs',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected table [{$table}] was not created.");
        }
    }

    public function test_complete_student_history_columns_are_available(): void
    {
        $this->assertTrue(Schema::hasColumns('students', [
            'user_id',
            'parent_user_id',
            'admission_no',
            'student_id_no',
            'school_class_id',
            'academic_session_id',
            'boarding_status',
            'house',
            'gender',
            'date_of_birth',
            'place_of_birth',
            'nationality',
            'lga',
            'blood_group',
            'state_of_origin',
            'religion',
            'guardian_name',
            'guardian_phone',
            'parents_occupation',
            'office_residence_phone',
            'address',
            'previous_school',
            'previous_class',
            'medical_notes',
            'physical_notes',
            'doctor_name',
            'doctor_address',
            'doctor_phone',
            'enrolled_at',
            'status',
        ]));
    }

    public function test_finance_and_report_fields_are_preserved(): void
    {
        $this->assertTrue(Schema::hasColumns('payments', [
            'fee_invoice_id',
            'student_id',
            'provider',
            'reference',
            'receipt_no',
            'gateway_reference',
            'amount',
            'currency',
            'status',
            'channel',
            'paid_at',
            'recorded_by',
            'note',
            'payload',
        ]));

        $this->assertTrue(Schema::hasColumns('student_term_reports', [
            'character_traits',
            'practical_skills',
            'class_teacher_remark',
            'guidance_remark',
            'principal_remark',
            'house_master_remark',
            'portal_enabled',
            'checker_enabled',
            'checker_pin_hash',
            'approved_by',
            'published_by',
        ]));
    }
}
