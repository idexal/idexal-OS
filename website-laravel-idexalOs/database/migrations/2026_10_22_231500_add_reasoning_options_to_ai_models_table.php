<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('ai_models', 'reasoning_options')) {
            return;
        }

        Schema::table('ai_models', function (Blueprint $table): void {
            $table->json('reasoning_options')->nullable()->after('capabilities');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('ai_models', 'reasoning_options')) {
            return;
        }

        Schema::table('ai_models', function (Blueprint $table): void {
            $table->dropColumn('reasoning_options');
        });
    }
};
