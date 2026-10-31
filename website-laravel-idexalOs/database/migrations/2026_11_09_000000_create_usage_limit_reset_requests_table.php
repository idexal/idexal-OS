<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A member asking an operator for more spend in the current period.
 *
 * The plan can carry a monthly cost allowance, and the desktop shows it against
 * the period's spend. When the allowance is gone the member needs a way to ask
 * for more; without a row, the ask existed only in their head. An approved
 * request becomes an extension for that period, so the allowance the desktop
 * reads is the plan's base plus every extension granted for the same period.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_limit_reset_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('bucket_id', 64);
            // The period the member asked about, so an approval extends that
            // period and never a later one.
            $table->string('period', 7);
            $table->text('reason');
            $table->string('status', 16)->default('pending');
            $table->unsignedBigInteger('extension_micro_usd')->nullable();
            $table->foreignUlid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            // One open ask per member and bucket: asking again updates the row
            // rather than queueing a second line for the same person.
            $table->index(['organization_id', 'bucket_id', 'user_id', 'status'], 'usage_reset_lookup');
            $table->index(['organization_id', 'status'], 'usage_reset_org_status');
            $table->index(['organization_id', 'period', 'status'], 'usage_reset_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_limit_reset_requests');
    }
};
