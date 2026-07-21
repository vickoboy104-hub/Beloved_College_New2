<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_permission_overrides')) {
            return;
        }

        Schema::create('user_permission_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('permission')->index();
            $table->boolean('allowed');
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'permission']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permission_overrides');
    }
};
