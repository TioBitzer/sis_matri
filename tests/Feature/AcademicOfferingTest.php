<?php

namespace Tests\Feature;

use App\Models\AcademicOffering;
use App\Models\AcademicPlan;
use App\Models\AcademicProgram;
use App\Models\Institution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AcademicOfferingTest extends TestCase
{
    use RefreshDatabase;

    /** Crea los datos mínimos de una institución para cada prueba. */
    private function context(string $suffix = 'A'): array
    {
        $institution = Institution::create([
            'name' => "Instituto {$suffix}",
            'code' => "I{$suffix}",
            'type' => 'institute',
        ]);

        $unitId = DB::table('academic_units')->insertGetId([
            'institution_id' => $institution->id,
            'name' => "Programación {$suffix}",
            'code' => "PROG{$suffix}",
            'is_active' => 1,
        ]);

        $periodId = DB::table('academic_periods')->insertGetId([
            'institution_id' => $institution->id,
            'name' => "Periodo {$suffix}",
            'code' => "2026-{$suffix}",
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $campusId = DB::table('campuses')->insertGetId([
            'institution_id' => $institution->id,
            'name' => "Sede {$suffix}",
            'code' => "SEDE{$suffix}",
        ]);

        return [
            'institution' => $institution,
            'unit_id' => $unitId,
            'period_id' => $periodId,
            'campus_id' => $campusId,
        ];
    }

    private function payload(array $context, array $changes = []): array
    {
        return array_merge([
            'academic_unit_id' => $context['unit_id'],
            'academic_period_id' => $context['period_id'],
            'campus_id' => $context['campus_id'],
            'section' => 'A',
            'capacity' => 30,
            'is_active' => true,
        ], $changes);
    }

    private function createOffering(array $context, array $changes = []): AcademicOffering
    {
        return AcademicOffering::create($this->payload($context, $changes));
    }

    /** Crea una matrícula real para comprobar capacidad y protección del historial. */
    private function addEnrollmentDetail(array $context, AcademicOffering $offering, string $code = '01', string $status = 'active'): int
    {
        $institution = $context['institution'];
        $program = AcademicProgram::create([
            'institution_id' => $institution->id,
            'name' => "Desarrollo de Software {$code}",
            'code' => "DS{$code}",
            'is_active' => true,
        ]);
        $plan = AcademicPlan::create([
            'academic_program_id' => $program->id,
            'name' => "Plan {$code}",
            'version' => '1.0',
            'is_active' => true,
        ]);
        $personId = DB::table('people')->insertGetId([
            'institution_id' => $institution->id,
            'first_name' => 'Estudiante',
            'last_name' => "Prueba {$code}",
            'document_number' => "7800{$code}",
        ]);
        $studentId = DB::table('students')->insertGetId([
            'person_id' => $personId,
            'student_code' => "EST{$code}",
        ]);
        $recordId = DB::table('academic_records')->insertGetId([
            'student_id' => $studentId,
            'academic_program_id' => $program->id,
            'academic_plan_id' => $plan->id,
            'status' => 'active',
        ]);
        $enrollmentId = DB::table('enrollments')->insertGetId([
            'student_id' => $studentId,
            'academic_period_id' => $context['period_id'],
            'academic_record_id' => $recordId,
            'enrollment_date' => '2026-03-01',
            'status' => 'active',
        ]);

        return DB::table('enrollment_details')->insertGetId([
            'enrollment_id' => $enrollmentId,
            'academic_offering_id' => $offering->id,
            'status' => $status,
        ]);
    }

    public function test_puede_crear_oferta_academica(): void
    {
        $context = $this->context();
        $this->postJson('/api/academic-offerings', $this->payload($context))
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.section', 'A')
            ->assertJsonPath('data.capacity', 30)
            ->assertJsonPath('data.is_active', true);
        $this->assertDatabaseCount('academic_offerings', 1);
    }

    public function test_crea_oferta_activa_por_defecto(): void
    {
        $context = $this->context();
        $payload = $this->payload($context);
        unset($payload['is_active']);
        $this->postJson('/api/academic-offerings', $payload)
            ->assertStatus(201)->assertJsonPath('data.is_active', true);
    }

    public function test_puede_crear_oferta_inactiva(): void
    {
        $context = $this->context();
        $this->postJson('/api/academic-offerings', $this->payload($context, ['is_active' => false]))
            ->assertStatus(201)->assertJsonPath('data.is_active', false);
    }

    public function test_puede_listar_ofertas_con_relaciones(): void
    {
        $context = $this->context();
        $this->createOffering($context);
        $this->getJson('/api/academic-offerings')
            ->assertStatus(200)->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.academic_unit.id', $context['unit_id'])
            ->assertJsonPath('data.0.academic_period.id', $context['period_id'])
            ->assertJsonPath('data.0.campus.id', $context['campus_id']);
    }

    public function test_puede_consultar_oferta_con_relaciones(): void
    {
        $context = $this->context();
        $offering = $this->createOffering($context);
        $this->getJson("/api/academic-offerings/{$offering->id}")
            ->assertStatus(200)->assertJsonPath('data.id', $offering->id)
            ->assertJsonPath('data.academic_unit.id', $context['unit_id']);
    }

    public function test_no_permite_crear_sin_campos_obligatorios(): void
    {
        $this->postJson('/api/academic-offerings', [])
            ->assertStatus(422)->assertJsonValidationErrors([
                'academic_unit_id', 'academic_period_id', 'campus_id', 'section', 'capacity',
            ]);
        $this->assertDatabaseCount('academic_offerings', 0);
    }

    public function test_no_permite_curso_inexistente(): void
    {
        $context = $this->context();
        $this->postJson('/api/academic-offerings', $this->payload($context, ['academic_unit_id' => 99999]))
            ->assertStatus(422)->assertJsonValidationErrors('academic_unit_id');
    }

    public function test_no_permite_periodo_inexistente(): void
    {
        $context = $this->context();
        $this->postJson('/api/academic-offerings', $this->payload($context, ['academic_period_id' => 99999]))
            ->assertStatus(422)->assertJsonValidationErrors('academic_period_id');
    }

    public function test_no_permite_sede_inexistente(): void
    {
        $context = $this->context();
        $this->postJson('/api/academic-offerings', $this->payload($context, ['campus_id' => 99999]))
            ->assertStatus(422)->assertJsonValidationErrors('campus_id');
    }

    public function test_no_permite_periodo_de_otra_institucion(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $this->postJson('/api/academic-offerings', $this->payload($a, ['academic_period_id' => $b['period_id']]))
            ->assertStatus(422)->assertJsonValidationErrors('academic_period_id');
        $this->assertDatabaseCount('academic_offerings', 0);
    }

    public function test_no_permite_sede_de_otra_institucion(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $this->postJson('/api/academic-offerings', $this->payload($a, ['campus_id' => $b['campus_id']]))
            ->assertStatus(422)->assertJsonValidationErrors('campus_id');
    }

    public function test_no_permite_seccion_duplicada_del_mismo_curso_y_periodo(): void
    {
        $context = $this->context();
        $this->createOffering($context);
        $this->postJson('/api/academic-offerings', $this->payload($context))
            ->assertStatus(422)->assertJsonValidationErrors('section');
        $this->assertDatabaseCount('academic_offerings', 1);
    }

    public function test_permite_secciones_diferentes_en_mismo_curso_y_periodo(): void
    {
        $context = $this->context();
        $this->createOffering($context);
        $this->postJson('/api/academic-offerings', $this->payload($context, ['section' => 'B']))
            ->assertStatus(201)->assertJsonPath('data.section', 'B');
        $this->assertDatabaseCount('academic_offerings', 2);
    }

    public function test_permite_misma_seccion_en_cursos_diferentes(): void
    {
        $context = $this->context();
        $this->createOffering($context);
        $anotherUnit = DB::table('academic_units')->insertGetId([
            'institution_id' => $context['institution']->id,
            'name' => 'Base de Datos', 'code' => 'BD1',
        ]);
        $this->postJson('/api/academic-offerings', $this->payload($context, ['academic_unit_id' => $anotherUnit]))
            ->assertStatus(201);
        $this->assertDatabaseCount('academic_offerings', 2);
    }

    public function test_no_permite_capacidad_cero(): void
    {
        $context = $this->context();
        $this->postJson('/api/academic-offerings', $this->payload($context, ['capacity' => 0]))
            ->assertStatus(422)->assertJsonValidationErrors('capacity');
    }

    public function test_no_permite_capacidad_no_entera(): void
    {
        $context = $this->context();
        $this->postJson('/api/academic-offerings', $this->payload($context, ['capacity' => 2.5]))
            ->assertStatus(422)->assertJsonValidationErrors('capacity');
    }

    public function test_no_permite_estado_invalido(): void
    {
        $context = $this->context();
        $this->postJson('/api/academic-offerings', $this->payload($context, ['is_active' => 'quizas']))
            ->assertStatus(422)->assertJsonValidationErrors('is_active');
    }

    public function test_no_permite_seccion_superior_a_cincuenta_caracteres(): void
    {
        $context = $this->context();
        $this->postJson('/api/academic-offerings', $this->payload($context, ['section' => str_repeat('A', 51)]))
            ->assertStatus(422)->assertJsonValidationErrors('section');
    }

    public function test_puede_actualizar_oferta(): void
    {
        $context = $this->context();
        $offering = $this->createOffering($context);
        $this->putJson("/api/academic-offerings/{$offering->id}", $this->payload($context, [
            'section' => 'B', 'capacity' => 40, 'is_active' => false,
        ]))->assertStatus(200)->assertJsonPath('data.section', 'B')
            ->assertJsonPath('data.capacity', 40)->assertJsonPath('data.is_active', false);
        $this->assertDatabaseHas('academic_offerings', [
            'id' => $offering->id, 'section' => 'B', 'capacity' => 40,
        ]);
    }

    public function test_puede_actualizar_conservando_su_misma_seccion(): void
    {
        $context = $this->context();
        $offering = $this->createOffering($context);
        $this->putJson("/api/academic-offerings/{$offering->id}", $this->payload($context, ['capacity' => 35]))
            ->assertStatus(200)->assertJsonPath('data.section', 'A');
    }

    public function test_no_permite_seccion_duplicada_al_actualizar(): void
    {
        $context = $this->context();
        $this->createOffering($context);
        $second = $this->createOffering($context, ['section' => 'B']);
        $this->putJson("/api/academic-offerings/{$second->id}", $this->payload($context))
            ->assertStatus(422)->assertJsonValidationErrors('section');
        $this->assertDatabaseHas('academic_offerings', ['id' => $second->id, 'section' => 'B']);
    }

    public function test_no_permite_cambiar_a_periodo_de_otra_institucion(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $offering = $this->createOffering($a);
        $this->putJson("/api/academic-offerings/{$offering->id}", $this->payload($a, [
            'academic_period_id' => $b['period_id'],
        ]))->assertStatus(422)->assertJsonValidationErrors('academic_period_id');
    }

    public function test_no_permite_actualizar_sin_campos_obligatorios(): void
    {
        $context = $this->context();
        $offering = $this->createOffering($context);
        $this->putJson("/api/academic-offerings/{$offering->id}", [])
            ->assertStatus(422)->assertJsonValidationErrors([
                'academic_unit_id', 'academic_period_id', 'campus_id', 'section', 'capacity',
            ]);
    }

    public function test_puede_eliminar_oferta_sin_dependencias(): void
    {
        $context = $this->context();
        $offering = $this->createOffering($context);
        $this->deleteJson("/api/academic-offerings/{$offering->id}")
            ->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseMissing('academic_offerings', ['id' => $offering->id]);
    }

    public function test_no_permite_eliminar_oferta_con_matriculas(): void
    {
        $context = $this->context();
        $offering = $this->createOffering($context);
        $detailId = $this->addEnrollmentDetail($context, $offering);
        $this->deleteJson("/api/academic-offerings/{$offering->id}")
            ->assertStatus(409)->assertJsonPath('success', false);
        $this->assertDatabaseHas('academic_offerings', ['id' => $offering->id]);
        $this->assertDatabaseHas('enrollment_details', ['id' => $detailId]);
    }

    public function test_no_permite_reducir_capacidad_debajo_de_ocupacion(): void
    {
        $context = $this->context();
        $offering = $this->createOffering($context, ['capacity' => 2]);
        $this->addEnrollmentDetail($context, $offering);
        $this->putJson("/api/academic-offerings/{$offering->id}", $this->payload($context, ['capacity' => 0]))
            ->assertStatus(422)->assertJsonValidationErrors('capacity');
        // Una capacidad positiva puede ser menor a los matriculados cuando hay más de uno.
        $this->addEnrollmentDetail($context, $offering, '02');
        $this->putJson("/api/academic-offerings/{$offering->id}", $this->payload($context, ['capacity' => 1]))
            ->assertStatus(409)->assertJsonPath('success', false);
        $this->assertDatabaseHas('academic_offerings', ['id' => $offering->id, 'capacity' => 2]);
    }

    public function test_permite_capacidad_igual_a_matriculas_activas(): void
    {
        $context = $this->context();
        $offering = $this->createOffering($context, ['capacity' => 10]);
        $this->addEnrollmentDetail($context, $offering);
        $this->putJson("/api/academic-offerings/{$offering->id}", $this->payload($context, ['capacity' => 1]))
            ->assertStatus(200)->assertJsonPath('data.capacity', 1);
    }

    public function test_no_permite_cambiar_curso_con_matriculas_asociadas(): void
    {
        $context = $this->context();
        $offering = $this->createOffering($context);
        $this->addEnrollmentDetail($context, $offering);
        $otherUnit = DB::table('academic_units')->insertGetId([
            'institution_id' => $context['institution']->id,
            'name' => 'Matemática I', 'code' => 'MAT1',
        ]);
        $this->putJson("/api/academic-offerings/{$offering->id}", $this->payload($context, [
            'academic_unit_id' => $otherUnit,
        ]))->assertStatus(409)->assertJsonPath('success', false);
        $this->assertDatabaseHas('academic_offerings', ['id' => $offering->id, 'academic_unit_id' => $context['unit_id']]);
    }

    public function test_devuelve_404_al_consultar_oferta_inexistente(): void
    {
        $this->getJson('/api/academic-offerings/99999')->assertStatus(404);
    }

    public function test_devuelve_404_al_actualizar_oferta_inexistente(): void
    {
        $context = $this->context();
        $this->putJson('/api/academic-offerings/99999', $this->payload($context))->assertStatus(404);
    }

    public function test_devuelve_404_al_eliminar_oferta_inexistente(): void
    {
        $this->deleteJson('/api/academic-offerings/99999')->assertStatus(404);
    }
}
