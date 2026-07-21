<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fee_items')) {
            Schema::create('fee_items', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->foreignId('academic_session_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('school_class_id')->nullable()->constrained()->nullOnDelete();
                $table->decimal('amount', 14, 2);
                $table->date('due_date')->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_mandatory')->default(false)->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('fee_invoices')) {
            Schema::create('fee_invoices', function (Blueprint $table): void {
                $table->id();
                $table->string('invoice_no')->unique();
                $table->foreignId('student_id')->constrained()->cascadeOnDelete();
                $table->foreignId('fee_item_id')->constrained()->cascadeOnDelete();
                $table->decimal('amount_due', 14, 2);
                $table->decimal('amount_paid', 14, 2)->default(0);
                $table->decimal('balance', 14, 2);
                $table->date('due_date')->nullable()->index();
                $table->string('status')->default('unpaid')->index();
                $table->timestamp('issued_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['student_id', 'fee_item_id']);
            });
        }

        if (! Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('fee_invoice_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('student_id')->constrained()->cascadeOnDelete();
                $table->string('provider')->index();
                $table->string('reference')->unique();
                $table->string('receipt_no')->nullable()->index();
                $table->string('gateway_reference')->nullable()->index();
                $table->decimal('amount', 14, 2);
                $table->string('currency', 3)->default('NGN');
                $table->string('status')->default('initialized')->index();
                $table->string('channel')->nullable();
                $table->timestamp('paid_at')->nullable()->index();
                $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('note')->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('announcements')) {
            Schema::create('announcements', function (Blueprint $table): void {
                $table->id();
                $table->string('title');
                $table->string('slug')->unique();
                $table->text('excerpt')->nullable();
                $table->longText('body')->nullable();
                $table->string('category')->nullable()->index();
                $table->boolean('is_published')->default(false)->index();
                $table->timestamp('published_at')->nullable()->index();
                $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table): void {
                $table->id();
                $table->string('group')->default('school')->index();
                $table->string('key')->unique();
                $table->longText('value')->nullable();
                $table->string('type')->default('string');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('contact_messages')) {
            Schema::create('contact_messages', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('subject')->nullable();
                $table->longText('message');
                $table->string('status')->default('new')->index();
                $table->timestamp('responded_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('route')->nullable()->index();
                $table->string('method', 12);
                $table->string('path', 1000);
                $table->string('action')->nullable()->index();
                $table->string('subject_type')->nullable()->index();
                $table->unsignedBigInteger('subject_id')->nullable()->index();
                $table->unsignedSmallInteger('status_code')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Financial, communication and audit history is intentionally retained.
    }
};
