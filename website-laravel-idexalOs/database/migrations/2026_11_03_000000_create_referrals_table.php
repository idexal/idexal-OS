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
        Schema::create('referrals', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('referrer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('referrer_organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignUlid('referred_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('referred_organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('code', 32);
            $table->string('status', 20)->default('pending');
            $table->decimal('reward_amount', 12, 6)->nullable();
            $table->string('reward_currency', 3)->nullable();
            $table->timestamp('qualified_at')->nullable();
            $table->timestamp('rewarded_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // One person can be referred once, no matter how many links they follow.
            $table->unique('referred_user_id');
            $table->index(['referrer_user_id', 'status']);
            $table->index('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
