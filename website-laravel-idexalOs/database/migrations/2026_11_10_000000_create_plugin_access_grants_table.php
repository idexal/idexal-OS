<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who inside a workspace may use an installed plugin.
 *
 * The desktop's Library has always had an access view, and it asked for
 * `GET/POST /v1/plugins/{id}/access` and `DELETE /v1/plugins/{id}/access/{id}`
 * — endpoints this platform never served, so the screen was dead. A grant is
 * the record those calls write, and it is what the library actually reads: a
 * plugin with no grants is available to the whole workspace (the behaviour
 * before this table existed), an org-wide grant keeps it that way explicitly,
 * and a per-member grant narrows it to the people named.
 *
 * This platform has no teams, so the desktop's `teamId` target is refused
 * rather than silently stored as something else.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugin_access_grants', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('plugin_slug', 190);
            // Null means the whole workspace; otherwise one membership.
            $table->foreignUlid('membership_id')->nullable()->constrained('organization_user')->cascadeOnDelete();
            $table->boolean('org_wide')->default(false);
            $table->string('role', 20)->default('viewer');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One grant per workspace, plugin and membership. MySQL lets a
            // unique index hold several NULLs, so the org-wide row is
            // de-duplicated in code as well.
            $table->unique(['organization_id', 'plugin_slug', 'membership_id'], 'plugin_access_member_unique');
            $table->index(['organization_id', 'plugin_slug'], 'plugin_access_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_access_grants');
    }
};
