<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What each installed connector may do, approved by an operator, and the
 * revision that approval was made at.
 *
 * A missing row is not a disabled feature, it is "nothing granted yet": a
 * connector that declares scopes in the marketplace does nothing until an
 * operator approves a set of them. The second table holds one integer per
 * organization so a change can be ordered — a desktop that reconnects must
 * never let an older permission set replace a newer, revoked one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connector_grants', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('connector_slug', 190);
            $table->json('scopes');
            $table->unsignedInteger('revision')->default(1);
            $table->string('status', 16)->default('active');
            $table->string('reason')->nullable();
            $table->timestamp('lease_expires_at')->nullable();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'connector_slug']);
        });

        Schema::create('connector_grant_revisions', function (Blueprint $table): void {
            $table->ulid('organization_id')->primary();
            $table->unsignedBigInteger('revision')->default(0);
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connector_grant_revisions');
        Schema::dropIfExists('connector_grants');
    }
};
