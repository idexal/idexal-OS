<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A member asking an operator for connector access.
 *
 * Approving a connector was an operator-only action, so a member who needed one
 * had no way to say so: they waited, or they asked outside the product. A
 * request is that ask, recorded once per member and connector so a repeated
 * click updates the same row instead of piling up. It is not a grant — the
 * operator still answers on the connectors screen — but it is what puts the
 * ask in the operator's queue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connector_grant_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('connector_slug', 190);
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->json('scopes');
            $table->string('status', 16)->default('open');
            $table->foreignUlid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            // One ask per member and connector. Asking again reopens the row
            // rather than creating a second one, so the queue stays one line
            // per person who wants the connection.
            $table->unique(['organization_id', 'connector_slug', 'user_id'], 'connector_request_unique');
            $table->index(['organization_id', 'status'], 'connector_request_org_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connector_grant_requests');
    }
};
