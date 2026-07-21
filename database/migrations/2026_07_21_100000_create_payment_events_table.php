<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_events')) {
            return;
        }

        Schema::create('payment_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->index();
            $table->string('event_id');
            $table->string('event_type')->nullable()->index();
            $table->string('payment_reference')->nullable()->index();
            $table->string('signature_hash', 128)->nullable();
            $table->string('status')->default('received')->index();
            $table->json('payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['provider', 'event_id']);
        });
    }

    public function down(): void
    {
        // Payment notification history is intentionally preserved.
    }
};
