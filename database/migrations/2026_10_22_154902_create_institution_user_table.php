<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_user', function (Blueprint $table) {
            $table->id();

            // Usuario que tendrá acceso a la institución.
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Institución a la que pertenece el usuario.
            $table->foreignId('institution_id')
                ->constrained('institutions')
                ->cascadeOnDelete();

            // Persona asociada dentro de la institución (opcional).
            $table->foreignId('person_id')
                ->nullable()
                ->constrained('people')
                ->nullOnDelete();

            // Permite activar o suspender el acceso institucional.
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Un usuario solo puede tener una membresía por institución.
            $table->unique(
                ['user_id', 'institution_id'],
                'institution_user_unique'
            );

            // Facilita buscar membresías activas.
            $table->index(
                ['institution_id', 'is_active'],
                'institution_user_active_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_user');
    }
};