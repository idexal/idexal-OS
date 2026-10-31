<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('ai_models', 'hybrid_config')) {
            return;
        }

        Schema::table('ai_models', function (Blueprint $table): void {
            $table->json('hybrid_config')->nullable()->after('reasoning_options');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('ai_models', 'hybrid_config')) {
            return;
        }

        Schema::table('ai_models', function (Blueprint $table): void {
            $table->dropColumn('hybrid_config');
        });
    }
};
