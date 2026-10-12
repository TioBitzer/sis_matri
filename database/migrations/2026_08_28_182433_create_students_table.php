<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();

            $table->foreignId('person_id')
                ->unique()
                ->constrained('people')
                ->cascadeOnDelete();

            $table->string('student_code');
            $table->date('admission_date')->nullable();
            $table->string('status')->default('active');

            $table->timestamps();

            $table->unique('student_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};