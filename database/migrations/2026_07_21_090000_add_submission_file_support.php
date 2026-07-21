<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('assignments')) {
            $columns = array_flip(Schema::getColumnListing('assignments'));

            Schema::table('assignments', function (Blueprint $table) use ($columns): void {
                if (! isset($columns['allowed_submission_types'])) {
                    $table->json('allowed_submission_types')->nullable();
                }

                if (! isset($columns['max_submission_files'])) {
                    $table->unsignedTinyInteger('max_submission_files')->default(3);
                }
            });
        }

        if (Schema::hasTable('assignment_submissions')) {
            $columns = array_flip(Schema::getColumnListing('assignment_submissions'));

            Schema::table('assignment_submissions', function (Blueprint $table) use ($columns): void {
                if (! isset($columns['attachment_paths'])) {
                    $table->json('attachment_paths')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // Submitted student files are historical academic records.
    }
};
