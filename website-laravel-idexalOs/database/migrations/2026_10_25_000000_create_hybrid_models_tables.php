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
        if (Schema::hasTable('hybrid_models')) {
            return;
        }

        Schema::create('hybrid_models', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->enum('strategy', ['round-robin', 'task-routing', 'weighted', 'fallback']);
            $table->json('config')->nullable(); // Additional configuration
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'created_at']);
        });

        Schema::create('hybrid_model_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hybrid_model_id')->constrained('hybrid_models')->cascadeOnDelete();
            $table->string('name', 255);
            $table->json('model_ids'); // Array of model IDs in this group
            $table->json('provider_ids')->nullable(); // Array of provider IDs
            $table->json('weights')->nullable(); // Model weights for weighted strategy
            $table->enum('role', ['coding', 'search', 'image', 'text', 'multimodal'])->nullable(); // For task-routing
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('hybrid_model_provider_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hybrid_model_id')->constrained('hybrid_models')->cascadeOnDelete();
            // Membership ids are ULIDs (organization_user.id) and teams are not a
            // first-class table in this schema, so these are plain nullable
            // references without foreign-key constraints.
            $table->ulid('org_membership_id')->nullable();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->enum('access_type', ['member', 'team', 'all_members'])->default('member');
            $table->timestamps();

            $table->unique(['hybrid_model_id', 'org_membership_id', 'access_type'], 'hybrid_provider_access_member_unique');
            $table->unique(['hybrid_model_id', 'team_id', 'access_type'], 'hybrid_provider_access_team_unique');
        });

        Schema::create('hybrid_model_health_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hybrid_model_id')->constrained('hybrid_models')->cascadeOnDelete();
            $table->enum('health_status', ['healthy', 'degraded', 'unhealthy', 'unknown']);
            $table->integer('average_latency_ms')->default(0);
            $table->decimal('success_rate', 5, 2)->default(100.00);
            $table->integer('consecutive_failures')->default(0);
            $table->timestamp('last_check_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('health_status');
            $table->index('last_check_at');
        });

        Schema::create('model_fallback_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hybrid_model_id')->constrained('hybrid_models')->cascadeOnDelete();
            $table->string('original_model_id', 255);
            $table->string('fallback_model_id', 255);
            $table->enum('reason', ['timeout', 'error', 'rate_limit', 'capacity', 'health_check_failed']);
            $table->integer('retry_count')->default(0);
            $table->integer('latency_ms')->default(0);
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['reason', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('model_fallback_events');
        Schema::dropIfExists('hybrid_model_health_logs');
        Schema::dropIfExists('hybrid_model_provider_access');
        Schema::dropIfExists('hybrid_model_groups');
        Schema::dropIfExists('hybrid_models');
    }
};
