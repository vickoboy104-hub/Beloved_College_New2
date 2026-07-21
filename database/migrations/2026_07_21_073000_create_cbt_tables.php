<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbt_questions')) {
            Schema::create('cbt_questions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
                $table->string('question_type')->index();
                $table->longText('prompt');
                $table->decimal('points', 10, 2)->default(1);
                $table->json('image_paths')->nullable();
                $table->string('video_path')->nullable();
                $table->string('video_url', 1000)->nullable();
                $table->string('resource_link', 1000)->nullable();
                $table->longText('theory_sample_answer')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cbt_question_options')) {
            Schema::create('cbt_question_options', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('cbt_question_id')->constrained()->cascadeOnDelete();
                $table->text('option_text');
                $table->boolean('is_correct')->default(false)->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cbt_attempts')) {
            Schema::create('cbt_attempts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
                $table->foreignId('student_id')->constrained()->cascadeOnDelete();
                $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status')->default('in_progress')->index();
                $table->timestamp('started_at');
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamp('submitted_at')->nullable();
                $table->decimal('objective_score', 10, 2)->default(0);
                $table->decimal('theory_score', 10, 2)->default(0);
                $table->decimal('total_score', 10, 2)->default(0);
                $table->timestamps();

                $table->unique(['assessment_id', 'student_id']);
            });
        }

        if (! Schema::hasTable('cbt_answers')) {
            Schema::create('cbt_answers', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('cbt_attempt_id')->constrained()->cascadeOnDelete();
                $table->foreignId('cbt_question_id')->constrained()->cascadeOnDelete();
                $table->foreignId('selected_option_id')->nullable()->constrained('cbt_question_options')->nullOnDelete();
                $table->longText('answer_text')->nullable();
                $table->boolean('is_correct')->nullable();
                $table->decimal('awarded_score', 10, 2)->nullable();
                $table->text('feedback')->nullable();
                $table->timestamp('graded_at')->nullable();
                $table->timestamps();

                $table->unique(['cbt_attempt_id', 'cbt_question_id']);
            });
        }
    }

    public function down(): void
    {
        // CBT attempts and answers are historical academic records.
    }
};
