<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('proxy_providers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('driver', 40)->default('generic');
            $table->string('protocol', 20)->default('http');
            $table->string('website_url')->nullable();
            $table->string('docs_url')->nullable();
            $table->text('description')->nullable();
            $table->json('countries')->nullable();
            $table->json('config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(true);
            $table->unsignedInteger('priority')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'is_public', 'priority']);
        });

        Schema::create('plan_proxy_provider', function (Blueprint $table) {
            $table->foreignUlid('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('proxy_provider_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['plan_id', 'proxy_provider_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_proxy_provider');
        Schema::dropIfExists('proxy_providers');
    }
};
