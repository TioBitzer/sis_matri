<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {

            // Crear un índice para mantener respaldada
            // la clave foránea de student_id
            $table->index(
                'student_id',
                'enrollments_student_id_index'
            );

            // Nueva restricción: una matrícula por expediente y periodo
            $table->unique(
                ['academic_record_id', 'academic_period_id'],
                'enrollment_record_period_unique'
            );
        });

        Schema::table('enrollments', function (Blueprint $table) {

            // Eliminar la restricción anterior
            $table->dropUnique('student_period_unique');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {

            // Restaurar primero la restricción original
            $table->unique(
                ['student_id', 'academic_period_id'],
                'student_period_unique'
            );
        });

        Schema::table('enrollments', function (Blueprint $table) {

            // Eliminar los índices añadidos
            $table->dropUnique('enrollment_record_period_unique');

            $table->dropIndex('enrollments_student_id_index');
        });
    }
};