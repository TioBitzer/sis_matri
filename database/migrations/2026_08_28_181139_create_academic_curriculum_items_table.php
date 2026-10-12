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
        Schema::create('academic_curriculum_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('academic_plan_id')
                ->constrained('academic_plans')
                ->cascadeOnDelete();

            $table->foreignId('academic_level_id')
                ->constrained('academic_levels')
                ->cascadeOnDelete();

            $table->foreignId('academic_unit_id')
                ->constrained('academic_units')
                ->cascadeOnDelete();

            $table->unsignedInteger('credits')->nullable();
            $table->unsignedInteger('hours')->nullable();
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('position')->nullable();

            $table->timestamps();

           $table->unique(
    [
        'academic_plan_id',
        'academic_level_id',
        'academic_unit_id'
    ],
    'curriculum_item_unique'
);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_curriculum_items');
    }
};