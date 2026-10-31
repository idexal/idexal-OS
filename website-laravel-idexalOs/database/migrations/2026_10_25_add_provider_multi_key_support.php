<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds comprehensive multi-key support for providers with automatic rotation.
     */
    public function up(): void
    {
        if (Schema::hasTable('provider_api_keys')) {
            return;
        }

        // 1. Provider API Keys Table - supports multiple keys per provider
        Schema::create('provider_api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('ai_provider_id')->constrained('ai_providers')->cascadeOnDelete();
            $table->string('key_name', 80)->comment('User-friendly key name like "primary", "backup-1"');
            $table->enum('key_type', ['primary', 'backup'])->default('backup');
            $table->text('api_key_encrypted')->comment('Encrypted API key value');
            $table->string('last_used_at', 32)->nullable()->comment('ISO timestamp or null');
            $table->dateTime('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('rotation_count')->default(0);
            $table->timestamps();

            $table->index(['ai_provider_id', 'is_active']);
            $table->index(['ai_provider_id', 'key_type']);
        });

        // 2. Backup Providers System - defines failover relationships
        Schema::create('backup_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('main_provider_id')->constrained('ai_providers')->cascadeOnDelete();
            $table->foreignUlid('backup_provider_id')->constrained('ai_providers')->cascadeOnDelete();
            $table->unsignedSmallInteger('priority')->default(100)->comment('Lower = higher priority (1-100)');
            $table->string('health_check_url', 2048)->nullable();
            $table->unsignedTinyInteger('max_failures_before_disable')->default(5);
            $table->unsignedSmallInteger('failback_delay_minutes')->default(60);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['main_provider_id', 'backup_provider_id'], 'unique_backup_pair');
            $table->index(['main_provider_id', 'priority']);
        });

        // 3. Provider Health Monitoring Logs
        Schema::create('provider_health_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('ai_provider_id')->constrained('ai_providers')->cascadeOnDelete();
            $table->timestamp('check_time')->useCurrent();
            $table->enum('status', ['healthy', 'degraded', 'unhealthy'])->default('healthy');
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->decimal('error_rate', 5, 4)->nullable()->comment('Percentage 0.0000-1.0000');
            $table->mediumText('quota_remaining')->nullable();
            $table->mediumText('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['ai_provider_id', 'check_time']);
            $table->index('status');
        });

        // 4. Request Routing & Fallback Logging
        Schema::create('request_routing_logs', function (Blueprint $table) {
            $table->id();
            $table->string('request_id', 190)->nullable()->index(); // For debugging
            $table->foreignUlid('ai_model_id')->constrained('ai_models');
            $table->foreignUlid('selected_provider_id')->constrained('ai_providers');
            $table->integer('selected_key_index')->nullable()->comment('Which backup key was used');
            $table->tinyInteger('attempt_number')->default(1);
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->decimal('cost_usd', 10, 6)->nullable();
            $table->string('fallback_reason', 255)->nullable(); // Why we switched providers
            $table->string('previous_provider_id', 80)->nullable(); // Who failed?
            $table->timestamps();

            $table->index(['ai_model_id', 'created_at']);
            $table->index(['selected_provider_id', 'created_at']);
            $table->index('fallback_reason');
        });

        // 5. Update ai_providers table with new fields
        if (Schema::hasColumn('ai_providers', 'supports_auto_key_rotation')) {
            return;
        }

        Schema::table('ai_providers', function (Blueprint $table) {
            $table->boolean('supports_auto_key_rotation')->default(false)->after('supports_streaming');
            $table->integer('max_backup_keys')->default(3)->after('supports_auto_key_rotation');
            $table->integer('key_rotation_interval_hours')->default(720)->after('max_backup_keys')->comment('30 days default');
            $table->boolean('auto_select_backup_provider')->default(true)->after('key_rotation_interval_hours');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_routing_logs');
        Schema::dropIfExists('provider_health_logs');
        Schema::dropIfExists('backup_providers');
        Schema::dropIfExists('provider_api_keys');

        Schema::table('ai_providers', function (Blueprint $table) {
            $table->dropColumn([
                'supports_auto_key_rotation',
                'max_backup_keys',
                'key_rotation_interval_hours',
                'auto_select_backup_provider',
            ]);
        });
    }
};
