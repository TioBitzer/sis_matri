<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teaching_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('teacher_id')
                ->constrained('teachers')
                ->cascadeOnDelete();

            $table->foreignId('academic_offering_id')
                ->constrained('academic_offerings')
                ->cascadeOnDelete();

            $table->string('role')->nullable();
            $table->timestamps();

            $table->unique(
                ['teacher_id', 'academic_offering_id'],
                'teacher_offering_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_assignments');
    }
};