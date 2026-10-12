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
        Schema::create('academic_levels', function (Blueprint $table) {
            $table->id();

            $table->foreignId('academic_plan_id')
                ->constrained('academic_plans')
                ->cascadeOnDelete();

            $table->string('name');
            $table->unsignedInteger('order');
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique([
                'academic_plan_id',
                'name'
            ]);

            $table->unique([
                'academic_plan_id',
                'order'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_levels');
    }
};