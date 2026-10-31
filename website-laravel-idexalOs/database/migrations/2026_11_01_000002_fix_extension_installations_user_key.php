<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `extension_installations` was the only table in the schema that keyed a
 * user with an integer while users, organizations and every other table use
 * ULIDs. Writing a ULID into a bigint column stores its leading digits, so
 * every account collapsed onto the same `user_id` and the unique constraint
 * then refused the second account's install.
 *
 * The owner of an existing row cannot be recovered from that number, and an
 * installation is one click to recreate, so the corrupt table is replaced
 * instead of migrated. Fresh installs already create the correct shape; this
 * only repairs a database that still has the old columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('extension_installations')) {
            return;
        }

        if (Schema::getColumnType('extension_installations', 'user_id') === 'string') {
            return;
        }

        Schema::drop('extension_installations');

        Schema::create('extension_installations', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('extension_name');
            $table->string('extension_type', 32);
            $table->string('version', 64)->default('1.0.0');
            $table->text('source')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'extension_name', 'extension_type'], 'extension_installations_user_unique');
        });
    }

    public function down(): void
    {
        // Nothing to roll back: the discarded rows never carried usable owners.
    }
};
