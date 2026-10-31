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
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('reference', 24)->unique();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignUlid('contact_message_id')->nullable()->constrained('contact_messages')->nullOnDelete();
            $table->foreignUlid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('requester_name');
            $table->string('requester_email');
            $table->string('subject');
            $table->string('category', 20)->default('general');
            $table->string('priority', 20)->default('normal');
            $table->string('status', 20)->default('open');
            $table->string('source', 20)->default('web');
            $table->text('body');
            $table->timestamp('last_reply_at')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['organization_id', 'status']);
            $table->index(['assigned_to', 'status']);
            $table->index('requester_email');
        });

        Schema::create('support_ticket_replies', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_type', 20)->default('customer');
            $table->string('author_name')->nullable();
            $table->text('body');
            $table->boolean('is_internal')->default(false);
            $table->boolean('ai_generated')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['support_ticket_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_ticket_replies');
        Schema::dropIfExists('support_tickets');
    }
};
