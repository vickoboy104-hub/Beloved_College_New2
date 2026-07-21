<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable()->index();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('announcements')) {
            $columns = [
                'priority',
                'audience_mode',
                'role_targets',
                'class_ids',
                'user_ids',
                'portal_enabled',
                'email_enabled',
                'starts_at',
                'expires_at',
                'dispatched_at',
                'status',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('announcements', $column)) {
                    continue;
                }

                Schema::table('announcements', function (Blueprint $table) use ($column): void {
                    match ($column) {
                        'priority' => $table->string('priority')->default('normal')->index(),
                        'audience_mode' => $table->string('audience_mode')->default('all')->index(),
                        'role_targets' => $table->json('role_targets')->nullable(),
                        'class_ids' => $table->json('class_ids')->nullable(),
                        'user_ids' => $table->json('user_ids')->nullable(),
                        'portal_enabled' => $table->boolean('portal_enabled')->default(true),
                        'email_enabled' => $table->boolean('email_enabled')->default(false),
                        'starts_at' => $table->timestamp('starts_at')->nullable()->index(),
                        'expires_at' => $table->timestamp('expires_at')->nullable()->index(),
                        'dispatched_at' => $table->timestamp('dispatched_at')->nullable()->index(),
                        'status' => $table->string('status')->default('draft')->index(),
                    };
                });
            }
        }

        if (! Schema::hasTable('announcement_deliveries')) {
            Schema::create('announcement_deliveries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->json('channels')->nullable();
                $table->string('status')->default('queued')->index();
                $table->timestamp('queued_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->text('failure_reason')->nullable();
                $table->timestamps();

                $table->unique(['announcement_id', 'user_id']);
                $table->index(['announcement_id', 'status']);
            });
        }

        if (! Schema::hasTable('system_heartbeats')) {
            Schema::create('system_heartbeats', function (Blueprint $table): void {
                $table->id();
                $table->string('service')->unique();
                $table->string('status')->default('healthy')->index();
                $table->timestamp('last_seen_at')->nullable()->index();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Notification history, delivery records and operational heartbeats are preserved.
    }
};
