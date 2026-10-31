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
        Schema::create('ai_providers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug', 80)->unique();
            $table->string('driver', 40);
            $table->string('base_url');
            $table->text('api_key')->nullable();
            $table->json('headers')->nullable();
            $table->boolean('is_active')->default(false);
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('supports_streaming')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'priority']);
        });

        Schema::create('ai_models', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('ai_provider_id')->constrained()->cascadeOnDelete();
            $table->string('model_id');
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->unsignedInteger('context_window')->nullable();
            $table->unsignedInteger('max_output_tokens')->nullable();
            $table->decimal('input_price', 14, 6)->default(0);
            $table->decimal('output_price', 14, 6)->default(0);
            $table->json('capabilities')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['ai_provider_id', 'model_id']);
            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('api_keys', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('key_prefix', 24);
            $table->string('key_hash', 64)->unique();
            $table->json('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'revoked_at']);
            $table->index(['user_id', 'revoked_at']);
        });

        Schema::create('desktop_handoffs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('grant_hash', 64)->unique();
            $table->string('device_name')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'consumed_at']);
            $table->index('expires_at');
        });

        Schema::create('desktop_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('api_key_id')->nullable()->constrained()->nullOnDelete();
            $table->string('device_name');
            $table->string('platform', 40)->nullable();
            $table->string('app_version', 40)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at']);
            $table->index(['organization_id', 'revoked_at']);
        });

        Schema::create('gateway_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('request_id', 40)->unique();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('api_key_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('ai_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('ai_model_id')->nullable()->constrained()->nullOnDelete();
            $table->string('endpoint', 60);
            $table->string('model', 160);
            $table->unsignedSmallInteger('status_code')->default(200);
            $table->unsignedBigInteger('prompt_tokens')->default(0);
            $table->unsignedBigInteger('completion_tokens')->default(0);
            $table->unsignedBigInteger('total_tokens')->default(0);
            $table->decimal('cost', 14, 8)->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->boolean('is_stream')->default(false);
            $table->text('error')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['organization_id', 'created_at']);
            $table->index(['ai_provider_id', 'created_at']);
            $table->index(['status_code', 'created_at']);
        });

        Schema::create('usage_counters', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7);
            $table->unsignedBigInteger('requests')->default(0);
            $table->unsignedBigInteger('prompt_tokens')->default(0);
            $table->unsignedBigInteger('completion_tokens')->default(0);
            $table->unsignedBigInteger('total_tokens')->default(0);
            $table->decimal('cost', 14, 8)->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'period']);
            $table->index('period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usage_counters');
        Schema::dropIfExists('gateway_requests');
        Schema::dropIfExists('desktop_sessions');
        Schema::dropIfExists('desktop_handoffs');
        Schema::dropIfExists('api_keys');
        Schema::dropIfExists('ai_models');
        Schema::dropIfExists('ai_providers');
    }
};
