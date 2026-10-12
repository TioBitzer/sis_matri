<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollment_details', function (Blueprint $table) {
            $table->id();

            $table->foreignId('enrollment_id')
                ->constrained('enrollments')
                ->cascadeOnDelete();

            $table->foreignId('academic_offering_id')
                ->constrained('academic_offerings')
                ->restrictOnDelete();

            $table->string('status')->default('enrolled');

            $table->timestamps();

            $table->unique(
                ['enrollment_id', 'academic_offering_id'],
                'enrollment_offering_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_details');
    }
};