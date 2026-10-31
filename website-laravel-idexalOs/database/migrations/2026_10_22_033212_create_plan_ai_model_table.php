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
        Schema::create('plan_ai_model', function (Blueprint $table) {
            $table->foreignUlid('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('ai_model_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['plan_id', 'ai_model_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_ai_model');
    }
};
