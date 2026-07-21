<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('academic_sessions')) {
            Schema::create('academic_sessions', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->unique();
                $table->date('start_date');
                $table->date('end_date');
                $table->decimal('promotion_pass_mark', 5, 2)->default(40);
                $table->boolean('is_current')->default(false)->index();
                $table->timestamp('closed_at')->nullable();
                $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('terms')) {
            Schema::create('terms', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('slug');
                $table->date('start_date');
                $table->date('end_date');
                $table->boolean('is_current')->default(false)->index();
                $table->timestamps();

                $table->unique(['academic_session_id', 'slug']);
            });
        }

        if (! Schema::hasTable('school_classes')) {
            Schema::create('school_classes', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('section')->nullable();
                $table->foreignId('class_teacher_id')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedInteger('capacity')->nullable();
                $table->string('room')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('subjects')) {
            Schema::create('subjects', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('code')->nullable()->unique();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('teacher_subject_assignments')) {
            Schema::create('teacher_subject_assignments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
                $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
                $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamp('assigned_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();

                $table->unique(['teacher_id', 'school_class_id', 'subject_id'], 'teacher_class_subject_unique');
            });
        }
    }

    public function down(): void
    {
        // Compatibility tables are not dropped automatically.
    }
};
