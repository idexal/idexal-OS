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
        Schema::create('plans', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug', 60)->unique();
            $table->text('description')->nullable();
            $table->decimal('price_monthly', 10, 2)->default(0);
            $table->decimal('price_yearly', 10, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->json('features')->nullable();
            $table->json('limits')->nullable();
            $table->string('stripe_price_monthly_id')->nullable();
            $table->string('stripe_price_yearly_id')->nullable();
            $table->boolean('is_public')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('plan_id')->constrained()->restrictOnDelete();
            $table->string('status', 24)->default('active');
            $table->string('billing_cycle', 10)->default('monthly');
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->string('stripe_subscription_id')->nullable()->unique();
            $table->string('stripe_status')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['status', 'current_period_end']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40)->unique();
            $table->string('status', 24)->default('open');
            $table->string('currency', 3)->default('USD');
            $table->decimal('subtotal', 14, 6)->default(0);
            $table->decimal('tax', 14, 6)->default(0);
            $table->decimal('discount', 14, 6)->default(0);
            $table->decimal('total', 14, 6)->default(0);
            $table->decimal('amount_paid', 14, 6)->default(0);
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->text('notes')->nullable();
            $table->string('stripe_invoice_id')->nullable()->unique();
            $table->string('stripe_hosted_invoice_url', 2048)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['status', 'due_at']);
            $table->index('issued_at');
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 14, 6)->default(0);
            $table->decimal('amount', 14, 6)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gateway', 24)->default('manual');
            $table->string('gateway_reference')->nullable();
            $table->decimal('amount', 14, 6)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('status', 24)->default('succeeded');
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
            $table->index(['gateway', 'gateway_reference']);
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24)->default('bank_transfer');
            $table->string('brand', 40)->nullable();
            $table->string('last4', 4)->nullable();
            $table->unsignedTinyInteger('exp_month')->nullable();
            $table->unsignedSmallInteger('exp_year')->nullable();
            $table->string('holder_name')->nullable();
            $table->string('gateway', 24)->default('manual');
            $table->string('gateway_reference')->nullable();
            $table->boolean('is_default')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'is_default']);
        });

        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 12);
            $table->decimal('amount', 14, 6);
            $table->decimal('balance_after', 14, 6);
            $table->string('description');
            $table->string('reference_type')->nullable();
            $table->string('reference_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
        });

        Schema::create('number_sequences', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->unsignedBigInteger('value')->default(0);
            $table->timestamps();
        });

        Schema::create('stripe_events', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('type');
            $table->longText('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stripe_events');
        Schema::dropIfExists('number_sequences');
        Schema::dropIfExists('credit_transactions');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};
