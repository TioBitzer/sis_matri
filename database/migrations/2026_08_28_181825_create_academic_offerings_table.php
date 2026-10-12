<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_offerings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('academic_unit_id')
                ->constrained('academic_units')
                ->cascadeOnDelete();

            $table->foreignId('academic_period_id')
                ->constrained('academic_periods')
                ->cascadeOnDelete();

            $table->foreignId('campus_id')
                ->nullable()
                ->constrained('campuses')
                ->nullOnDelete();

            $table->string('section')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(
                ['academic_unit_id', 'academic_period_id', 'section'],
                'offering_unit_period_section_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_offerings');
    }
};