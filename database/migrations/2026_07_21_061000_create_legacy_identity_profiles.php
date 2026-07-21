<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('students')) {
            Schema::create('students', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->foreignId('parent_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('admission_no')->unique();
                $table->string('student_id_no')->nullable()->unique();
                $table->unsignedBigInteger('school_class_id')->nullable()->index();
                $table->unsignedBigInteger('academic_session_id')->nullable()->index();
                $table->string('status')->default('active')->index();
                $table->date('enrolled_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('staff_profiles')) {
            Schema::create('staff_profiles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->string('employee_no')->unique();
                $table->string('department')->nullable()->index();
                $table->string('designation')->nullable();
                $table->string('qualification')->nullable();
                $table->date('hire_date')->nullable();
                $table->decimal('salary', 14, 2)->nullable();
                $table->string('status')->default('active')->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Existing production identity tables must never be dropped by rollback.
    }
};
