<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $columns = array_flip(Schema::getColumnListing('users'));

        Schema::table('users', function (Blueprint $table) use ($columns): void {
            if (! isset($columns['first_name'])) {
                $table->string('first_name')->nullable()->after('name');
            }

            if (! isset($columns['middle_name'])) {
                $table->string('middle_name')->nullable()->after('first_name');
            }

            if (! isset($columns['last_name'])) {
                $table->string('last_name')->nullable()->after('middle_name');
            }

            if (! isset($columns['role'])) {
                $table->string('role')->default('student')->index()->after('password');
            }

            if (! isset($columns['phone'])) {
                $table->string('phone')->nullable()->after('role');
            }

            if (! isset($columns['status'])) {
                $table->string('status')->default('active')->index()->after('phone');
            }

            if (! isset($columns['avatar_url'])) {
                $table->string('avatar_url')->nullable()->after('status');
            }

            if (! isset($columns['avatar_path'])) {
                $table->string('avatar_path')->nullable()->after('avatar_url');
            }

            if (! isset($columns['last_seen_at'])) {
                $table->timestamp('last_seen_at')->nullable()->after('avatar_path');
            }

            if (! isset($columns['must_change_password'])) {
                $table->boolean('must_change_password')->default(false)->after('last_seen_at');
            }

            if (! isset($columns['preferred_theme'])) {
                $table->string('preferred_theme')->nullable()->after('must_change_password');
            }
        });
    }

    public function down(): void
    {
        // Compatibility migrations are intentionally non-destructive.
    }
};
