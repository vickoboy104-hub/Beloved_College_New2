<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['users', 'students', 'staff_profiles'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            $columns = array_flip(Schema::getColumnListing($tableName));

            Schema::table($tableName, function (Blueprint $table) use ($columns): void {
                if (! isset($columns['archived_at'])) {
                    $table->timestamp('archived_at')->nullable()->index();
                }

                if (! isset($columns['archived_by'])) {
                    $table->unsignedBigInteger('archived_by')->nullable()->index();
                }

                if (! isset($columns['archive_reason'])) {
                    $table->string('archive_reason', 500)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // Archival history is intentionally retained.
    }
};
