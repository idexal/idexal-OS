<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('extension_installations')) {
            return;
        }

        Schema::create('extension_installations', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('extension_name');
            $table->string('extension_type', 32);
            $table->string('version', 64)->default('1.0.0');
            $table->text('source')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'extension_name', 'extension_type'], 'extension_installations_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extension_installations');
    }
};
