<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lessons')) {
            Schema::create('lessons', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
                $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
                $table->string('title');
                $table->text('summary')->nullable();
                $table->longText('body')->nullable();
                $table->string('video_url', 1000)->nullable();
                $table->string('video_path')->nullable();
                $table->string('resource_link', 1000)->nullable();
                $table->json('note_images')->nullable();
                $table->timestamp('published_at')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('assignments')) {
            Schema::create('assignments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
                $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
                $table->string('title');
                $table->longText('instructions')->nullable();
                $table->json('attachment_images')->nullable();
                $table->timestamp('due_date')->nullable()->index();
                $table->decimal('total_score', 10, 2)->default(100);
                $table->string('status')->default('published')->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('assignment_submissions')) {
            Schema::create('assignment_submissions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
                $table->foreignId('student_id')->constrained()->cascadeOnDelete();
                $table->longText('content')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->decimal('score', 10, 2)->nullable();
                $table->text('feedback')->nullable();
                $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['assignment_id', 'student_id']);
            });
        }

        if (! Schema::hasTable('assessments')) {
            Schema::create('assessments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('term_id')->constrained()->cascadeOnDelete();
                $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
                $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
                $table->string('title');
                $table->string('type')->index();
                $table->boolean('is_cbt')->default(false)->index();
                $table->decimal('total_score', 10, 2);
                $table->unsignedInteger('cbt_duration_minutes')->nullable();
                $table->timestamp('scheduled_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('cbt_starts_at')->nullable();
                $table->timestamp('cbt_ends_at')->nullable();
                $table->text('cbt_instructions')->nullable();
                $table->boolean('cbt_is_active')->default(false)->index();
                $table->boolean('cbt_show_results')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('assessment_results')) {
            Schema::create('assessment_results', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
                $table->foreignId('student_id')->constrained()->cascadeOnDelete();
                $table->decimal('score', 10, 2);
                $table->string('grade')->nullable();
                $table->string('remark')->nullable();
                $table->timestamps();

                $table->unique(['assessment_id', 'student_id']);
            });
        }

        if (! Schema::hasTable('attendance_records')) {
            Schema::create('attendance_records', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
                $table->foreignId('student_id')->constrained()->cascadeOnDelete();
                $table->foreignId('taken_by')->constrained('users')->cascadeOnDelete();
                $table->date('attendance_date')->index();
                $table->string('status')->index();
                $table->text('note')->nullable();
                $table->timestamps();

                $table->unique(['student_id', 'attendance_date']);
            });
        }

        if (! Schema::hasTable('student_term_reports')) {
            Schema::create('student_term_reports', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('student_id')->constrained()->cascadeOnDelete();
                $table->foreignId('term_id')->constrained()->cascadeOnDelete();
                $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
                $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
                $table->unsignedInteger('days_school_open')->nullable();
                $table->unsignedInteger('days_present')->nullable();
                $table->unsignedInteger('days_absent')->nullable();
                $table->date('next_term_begins_on')->nullable();
                $table->json('character_traits')->nullable();
                $table->json('practical_skills')->nullable();
                $table->text('class_teacher_remark')->nullable();
                $table->text('guidance_remark')->nullable();
                $table->text('principal_remark')->nullable();
                $table->text('house_master_remark')->nullable();
                $table->string('overall_grade')->nullable();
                $table->decimal('average_score', 10, 2)->nullable();
                $table->decimal('total_score', 12, 2)->nullable();
                $table->unsignedInteger('subject_count')->nullable();
                $table->unsignedInteger('class_position')->nullable();
                $table->boolean('portal_enabled')->default(false)->index();
                $table->boolean('checker_enabled')->default(false)->index();
                $table->string('checker_pin_hash')->nullable();
                $table->json('metadata')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();

                $table->unique(['student_id', 'term_id']);
            });
        }

        if (! Schema::hasTable('student_promotions')) {
            Schema::create('student_promotions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('student_id')->constrained()->cascadeOnDelete();
                $table->foreignId('from_academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
                $table->foreignId('to_academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
                $table->foreignId('from_school_class_id')->nullable()->constrained('school_classes')->nullOnDelete();
                $table->foreignId('to_school_class_id')->nullable()->constrained('school_classes')->nullOnDelete();
                $table->string('promotion_status')->index();
                $table->decimal('promotion_threshold', 5, 2)->nullable();
                $table->decimal('overall_percentage', 7, 2)->nullable();
                $table->decimal('subject_total_percentage', 10, 2)->nullable();
                $table->unsignedInteger('subject_count')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['student_id', 'from_academic_session_id', 'to_academic_session_id'], 'student_session_promotion_unique');
            });
        }
    }

    public function down(): void
    {
        // Learning and report history is never automatically deleted.
    }
};
