<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            foreach (['password_changed_at', 'last_login_at'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    continue;
                }

                Schema::table('users', function (Blueprint $table) use ($column): void {
                    $table->timestamp($column)->nullable()->index();
                });
            }

            if (! Schema::hasColumn('users', 'last_login_ip')) {
                Schema::table('users', function (Blueprint $table): void {
                    $table->string('last_login_ip', 45)->nullable();
                });
            }
        }

        if (! Schema::hasTable('security_events')) {
            Schema::create('security_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('event')->index();
                $table->string('severity')->default('info')->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('occurred_at')->index();
                $table->timestamps();

                $table->index(['user_id', 'occurred_at']);
            });
        }
    }

    public function down(): void
    {
        // Security history and identity timestamps are intentionally preserved.
    }
};
