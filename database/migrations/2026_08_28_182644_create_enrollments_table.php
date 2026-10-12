<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();

            $table->foreignId('academic_period_id')
                ->constrained('academic_periods')
                ->restrictOnDelete();

            $table->foreignId('academic_record_id')
                ->constrained('academic_records')
                ->restrictOnDelete();

            $table->date('enrollment_date');
            $table->string('status')->default('active');

            $table->timestamps();

            $table->unique(
                ['student_id', 'academic_period_id'],
                'student_period_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};