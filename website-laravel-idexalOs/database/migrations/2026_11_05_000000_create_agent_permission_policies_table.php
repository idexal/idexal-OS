<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One agent-permission policy per organization: what agents may do on the
 * computers of that organization's members.
 *
 * A missing row is not a disabled feature, it is the default policy — every
 * permission allows, so members' own desktop settings apply and nothing that
 * worked before this table existed stops working.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_permission_policies', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('settings');
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_permission_policies');
    }
};
