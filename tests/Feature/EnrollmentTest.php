<?php

namespace Tests\Feature;

use App\Models\AcademicPlan;
use App\Models\AcademicProgram;
use App\Models\AcademicRecord;
use App\Models\Enrollment;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/enrollments';

    /** Seleccionamos la primera institución creada dentro de cada prueba. */
    private bool $tenantIsSelected = false;

    private function context(string $suffix = 'A'): array
    {
        $institution = Institution::create([
            'name' => "Instituto {$suffix}",
            'code' => "INST{$suffix}",
            'type' => 'institute',
        ]);

        if (!$this->tenantIsSelected) {
            $user = User::factory()->create();
            $user->institutions()->attach($institution->id, ['is_active' => true]);
            $this->actingAs($user);
            $this->withHeaders(['X-Institution-Id' => (string) $institution->id]);
            $this->tenantIsSelected = true;
        }

        $program = AcademicProgram::create([
            'institution_id' => $institution->id,
            'name' => "Desarrollo de Software {$suffix}",
            'code' => "DS{$suffix}",
            'is_active' => true,
        ]);

        $plan = AcademicPlan::create([
            'academic_program_id' => $program->id,
            'name' => "Plan Curricular {$suffix}",
            'version' => '1.0',
            'is_active' => true,
        ]);

        $periodId = DB::table('academic_periods')->insertGetId([
            'institution_id' => $institution->id,
            'name' => "Periodo 2026-I {$suffix}",
            'code' => "2026-I-{$suffix}",
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
        ]);

        $studentId = $this->makeStudent($institution, $suffix);
        $record = AcademicRecord::create([
            'student_id' => $studentId,
            'academic_program_id' => $program->id,
            'academic_plan_id' => $plan->id,
            'start_date' => '2026-01-01',
            'status' => 'active',
        ]);

        return compact('institution', 'program', 'plan', 'periodId', 'studentId', 'record');
    }

    private function makeStudent(Institution $institution, string $token): int
    {
        $personId = DB::table('people')->insertGetId([
            'institution_id' => $institution->id,
            'first_name' => 'Alumno',
            'last_name' => "Prueba {$token}",
            'document_type' => 'DNI',
            'document_number' => "DOC{$institution->id}{$token}",
        ]);

        return DB::table('students')->insertGetId([
            'person_id' => $personId,
            'student_code' => "EST{$institution->id}{$token}",
            'status' => 'active',
        ]);
    }

    private function makePeriod(array $context, string $suffix = 'II'): int
    {
        return DB::table('academic_periods')->insertGetId([
            'institution_id' => $context['institution']->id,
            'name' => "Periodo 2026-{$suffix}",
            'code' => "2026-{$suffix}-{$context['institution']->id}",
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
        ]);
    }

    private function makeRecord(array $context, int $studentId, array $changes = []): AcademicRecord
    {
        return AcademicRecord::create(array_replace([
            'student_id' => $studentId,
            'academic_program_id' => $context['program']->id,
            'academic_plan_id' => $context['plan']->id,
            'start_date' => '2026-01-01',
            'status' => 'active',
        ], $changes));
    }

    private function makeSecondProgramRecord(array $context, string $suffix = 'OTRO'): AcademicRecord
    {
        $program = AcademicProgram::create([
            'institution_id' => $context['institution']->id,
            'name' => "Otro Programa {$suffix}",
            'code' => "OT{$suffix}",
            'is_active' => true,
        ]);
        $plan = AcademicPlan::create([
            'academic_program_id' => $program->id,
            'name' => "Plan {$suffix}",
            'version' => '1.0',
            'is_active' => true,
        ]);

        return $this->makeRecord($context, $context['studentId'], [
            'academic_program_id' => $program->id,
            'academic_plan_id' => $plan->id,
        ]);
    }

    private function payload(array $context, array $changes = []): array
    {
        return array_replace([
            'student_id' => $context['studentId'],
            'academic_period_id' => $context['periodId'],
            'academic_record_id' => $context['record']->id,
            'enrollment_date' => '2026-03-01',
            'status' => 'active',
        ], $changes);
    }

    private function enrollment(array $context, array $changes = []): Enrollment
    {
        return Enrollment::create($this->payload($context, $changes));
    }

    private function makeDetail(array $context, Enrollment $enrollment): int
    {
        $unitId = DB::table('academic_units')->insertGetId([
            'institution_id' => $context['institution']->id,
            'name' => 'Programación de Prueba',
            'code' => 'PROG-' . $context['institution']->id,
            'is_active' => true,
        ]);

        $offeringId = DB::table('academic_offerings')->insertGetId([
            'academic_unit_id' => $unitId,
            'academic_period_id' => $enrollment->academic_period_id,
            'section' => 'A',
            'capacity' => 30,
            'is_active' => true,
        ]);

        return DB::table('enrollment_details')->insertGetId([
            'enrollment_id' => $enrollment->id,
            'academic_offering_id' => $offeringId,
            'status' => 'active',
        ]);
    }

    public function test_puede_crear_matricula_con_todos_sus_campos(): void
    {
        $context = $this->context();
        $response = $this->postJson(self::ENDPOINT, $this->payload($context));

        $response->assertStatus(201)->assertJsonPath('success', true)
            ->assertJsonPath('data.student_id', $context['studentId'])
            ->assertJsonPath('data.academic_period_id', $context['periodId'])
            ->assertJsonPath('data.academic_record_id', $context['record']->id)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.student.person.institution_id', $context['institution']->id)
            ->assertJsonPath('data.academic_period.id', $context['periodId'])
            ->assertJsonPath('data.academic_record.academic_program.id', $context['program']->id)
            ->assertJsonPath('data.academic_record.academic_plan.id', $context['plan']->id);

        $this->assertSame('2026-03-01', substr($response->json('data.enrollment_date'), 0, 10));
        $this->assertDatabaseHas('enrollments', [
            'id' => $response->json('data.id'),
            'student_id' => $context['studentId'],
            'academic_period_id' => $context['periodId'],
            'academic_record_id' => $context['record']->id,
        ]);
    }

    public function test_puede_crear_matricula_pendiente(): void
    {
        $c = $this->context();
        $this->postJson(self::ENDPOINT, $this->payload($c, ['status' => 'pending']))
            ->assertStatus(201)->assertJsonPath('data.status', 'pending');
    }

    public function test_puede_crear_matricula_completada(): void
    {
        $c = $this->context();
        $this->postJson(self::ENDPOINT, $this->payload($c, ['status' => 'completed']))
            ->assertStatus(201)->assertJsonPath('data.status', 'completed');
    }

    public function test_puede_crear_matricula_cancelada(): void
    {
        $c = $this->context();
        $this->postJson(self::ENDPOINT, $this->payload($c, ['status' => 'cancelled']))
            ->assertStatus(201)->assertJsonPath('data.status', 'cancelled');
    }

    public function test_puede_listar_matriculas_con_relaciones(): void
    {
        $c = $this->context();
        $this->enrollment($c);
        $second = $this->makeStudent($c['institution'], '02');
        $record2 = $this->makeRecord($c, $second);
        $this->enrollment($c, ['student_id' => $second, 'academic_record_id' => $record2->id]);

        $this->getJson(self::ENDPOINT)->assertStatus(200)->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.student.person.institution_id', $c['institution']->id)
            ->assertJsonPath('data.1.academic_record.academic_plan.id', $c['plan']->id);
    }

    public function test_lista_vacia_si_no_existen_matriculas(): void
    {
        $this->context(); // Autenticación y selección de institución para esta prueba.
        $this->getJson(self::ENDPOINT)->assertStatus(200)
            ->assertJsonPath('success', true)->assertJsonCount(0, 'data');
    }

    public function test_puede_consultar_matricula_con_relaciones(): void
    {
        $c = $this->context();
        $enrollment = $this->enrollment($c);

        $this->getJson(self::ENDPOINT . "/{$enrollment->id}")
            ->assertStatus(200)->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $enrollment->id)
            ->assertJsonPath('data.student.id', $c['studentId'])
            ->assertJsonPath('data.academic_period.id', $c['periodId'])
            ->assertJsonPath('data.academic_record.academic_program.id', $c['program']->id);
    }

    public function test_no_permite_crear_sin_campos_obligatorios(): void
    {
        $this->context(); // Autenticación y selección de institución para esta prueba.
        $this->postJson(self::ENDPOINT, [])->assertStatus(422)
            ->assertJsonValidationErrors([
                'student_id', 'academic_period_id', 'academic_record_id', 'enrollment_date', 'status',
            ]);
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_no_permite_estudiante_inexistente(): void
    {
        $c = $this->context();
        $this->postJson(self::ENDPOINT, $this->payload($c, ['student_id' => 99999]))
            ->assertStatus(422)->assertJsonValidationErrors('student_id');
    }

    public function test_no_permite_periodo_inexistente(): void
    {
        $c = $this->context();
        $this->postJson(self::ENDPOINT, $this->payload($c, ['academic_period_id' => 99999]))
            ->assertStatus(422)->assertJsonValidationErrors('academic_period_id');
    }

    public function test_no_permite_historial_inexistente(): void
    {
        $c = $this->context();
        $this->postJson(self::ENDPOINT, $this->payload($c, ['academic_record_id' => 99999]))
            ->assertStatus(422)->assertJsonValidationErrors('academic_record_id');
    }

    public function test_no_permite_estudiante_no_numerico(): void
    {
        $c = $this->context();
        $this->postJson(self::ENDPOINT, $this->payload($c, ['student_id' => 'abc']))
            ->assertStatus(422)->assertJsonValidationErrors('student_id');
    }

    public function test_no_permite_periodo_no_numerico(): void
    {
        $c = $this->context();
        $this->postJson(self::ENDPOINT, $this->payload($c, ['academic_period_id' => 'abc']))
            ->assertStatus(422)->assertJsonValidationErrors('academic_period_id');
    }

    public function test_no_permite_historial_no_numerico(): void
    {
        $c = $this->context();
        $this->postJson(self::ENDPOINT, $this->payload($c, ['academic_record_id' => 'abc']))
            ->assertStatus(422)->assertJsonValidationErrors('academic_record_id');
    }

    public function test_no_permite_historial_de_otro_estudiante(): void
    {
        $c = $this->context();
        $student2 = $this->makeStudent($c['institution'], '02');
        $record2 = $this->makeRecord($c, $student2);

        $this->postJson(self::ENDPOINT, $this->payload($c, ['academic_record_id' => $record2->id]))
            ->assertStatus(422)->assertJsonValidationErrors('academic_record_id');
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_no_permite_periodo_de_otra_institucion(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');

        $this->postJson(self::ENDPOINT, $this->payload($a, ['academic_period_id' => $b['periodId']]))
            ->assertStatus(422)->assertJsonValidationErrors('academic_period_id');
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_no_permite_historial_de_programa_de_otra_institucion(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        // Un historial inconsistente puede haber sido insertado directamente en la BD.
        $badRecord = $this->makeRecord($a, $a['studentId'], [
            'academic_program_id' => $b['program']->id,
            'academic_plan_id' => $b['plan']->id,
        ]);

        $this->postJson(self::ENDPOINT, $this->payload($a, ['academic_record_id' => $badRecord->id]))
            ->assertStatus(422)->assertJsonValidationErrors('academic_record_id');
    }

    public function test_no_permite_historial_con_plan_de_otro_programa(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $badRecord = $this->makeRecord($a, $a['studentId'], [
            'academic_plan_id' => $b['plan']->id,
        ]);

        $this->postJson(self::ENDPOINT, $this->payload($a, ['academic_record_id' => $badRecord->id]))
            ->assertStatus(422)->assertJsonValidationErrors('academic_record_id');
    }

    public function test_no_permite_matricula_duplicada_por_estudiante_y_periodo(): void
    {
        $c = $this->context();
        $this->enrollment($c);
        $otherRecord = $this->makeSecondProgramRecord($c);

        $this->postJson(self::ENDPOINT, $this->payload($c, [
            'academic_record_id' => $otherRecord->id,
        ]))->assertStatus(422)->assertJsonValidationErrors('academic_period_id');
        $this->assertDatabaseCount('enrollments', 1);
    }

    public function test_no_permite_repetir_mismo_historial_y_periodo(): void
    {
        $c = $this->context();
        $this->enrollment($c);

        $this->postJson(self::ENDPOINT, $this->payload($c))
            ->assertStatus(422)->assertJsonValidationErrors('academic_period_id');
        $this->assertDatabaseCount('enrollments', 1);
    }

    public function test_permite_mismo_estudiante_en_periodos_diferentes(): void
    {
        $c = $this->context();
        $this->enrollment($c);
        $anotherPeriod = $this->makePeriod($c);

        $this->postJson(self::ENDPOINT, $this->payload($c, ['academic_period_id' => $anotherPeriod]))
            ->assertStatus(201)->assertJsonPath('data.academic_period_id', $anotherPeriod);
        $this->assertDatabaseCount('enrollments', 2);
    }

    public function test_permite_estudiantes_diferentes_en_mismo_periodo(): void
    {
        $c = $this->context();
        $this->enrollment($c);
        $student2 = $this->makeStudent($c['institution'], '02');
        $record2 = $this->makeRecord($c, $student2);

        $this->postJson(self::ENDPOINT, $this->payload($c, [
            'student_id' => $student2,
            'academic_record_id' => $record2->id,
        ]))->assertStatus(201)->assertJsonPath('data.student_id', $student2);
        $this->assertDatabaseCount('enrollments', 2);
    }

    public function test_no_permite_fecha_invalida(): void
    {
        $c = $this->context();
        $this->postJson(self::ENDPOINT, $this->payload($c, ['enrollment_date' => 'no-es-fecha']))
            ->assertStatus(422)->assertJsonValidationErrors('enrollment_date');
    }

    public function test_no_permite_estado_desconocido(): void
    {
        $c = $this->context();
        $this->postJson(self::ENDPOINT, $this->payload($c, ['status' => 'inventado']))
            ->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_no_permite_estado_no_textual(): void
    {
        $c = $this->context();
        $this->postJson(self::ENDPOINT, $this->payload($c, ['status' => ['active']]))
            ->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_no_permite_estado_mayor_a_cincuenta_caracteres(): void
    {
        $c = $this->context();
        $this->postJson(self::ENDPOINT, $this->payload($c, ['status' => str_repeat('a', 51)]))
            ->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_puede_actualizar_matricula(): void
    {
        $c = $this->context();
        $enrollment = $this->enrollment($c);

        $this->putJson(self::ENDPOINT . "/{$enrollment->id}", $this->payload($c, [
            'enrollment_date' => '2026-04-15',
            'status' => 'completed',
        ]))->assertStatus(200)->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.academic_record.academic_program.id', $c['program']->id);

        $this->assertDatabaseHas('enrollments', ['id' => $enrollment->id, 'status' => 'completed']);
    }

    public function test_permite_actualizar_conservando_estudiante_periodo_e_historial(): void
    {
        $c = $this->context();
        $enrollment = $this->enrollment($c);

        $this->putJson(self::ENDPOINT . "/{$enrollment->id}", $this->payload($c))
            ->assertStatus(200)->assertJsonPath('data.id', $enrollment->id);
    }

    public function test_permite_cambiar_periodo_sin_cursos_registrados(): void
    {
        $c = $this->context();
        $enrollment = $this->enrollment($c);
        $newPeriod = $this->makePeriod($c);

        $this->putJson(self::ENDPOINT . "/{$enrollment->id}", $this->payload($c, [
            'academic_period_id' => $newPeriod,
        ]))->assertStatus(200)->assertJsonPath('data.academic_period_id', $newPeriod);
    }

    public function test_permite_cambiar_historial_del_mismo_estudiante_sin_cursos(): void
    {
        $c = $this->context();
        $enrollment = $this->enrollment($c);
        $record2 = $this->makeSecondProgramRecord($c);

        $this->putJson(self::ENDPOINT . "/{$enrollment->id}", $this->payload($c, [
            'academic_record_id' => $record2->id,
        ]))->assertStatus(200)->assertJsonPath('data.academic_record_id', $record2->id);
    }

    public function test_permite_cambiar_estudiante_e_historial_juntos_sin_cursos(): void
    {
        $c = $this->context();
        $enrollment = $this->enrollment($c);
        $student2 = $this->makeStudent($c['institution'], '02');
        $record2 = $this->makeRecord($c, $student2);

        $this->putJson(self::ENDPOINT . "/{$enrollment->id}", $this->payload($c, [
            'student_id' => $student2,
            'academic_record_id' => $record2->id,
        ]))->assertStatus(200)->assertJsonPath('data.student_id', $student2)
            ->assertJsonPath('data.academic_record_id', $record2->id);
    }

    public function test_no_permite_actualizar_sin_campos_obligatorios(): void
    {
        $c = $this->context();
        $enrollment = $this->enrollment($c);
        $this->putJson(self::ENDPOINT . "/{$enrollment->id}", [])
            ->assertStatus(422)->assertJsonValidationErrors([
                'student_id', 'academic_period_id', 'academic_record_id', 'enrollment_date', 'status',
            ]);
    }

    public function test_no_permite_duplicado_al_cambiar_periodo(): void
    {
        $c = $this->context();
        $enrollment = $this->enrollment($c);
        $period2 = $this->makePeriod($c);
        $second = $this->enrollment($c, ['academic_period_id' => $period2]);

        $this->putJson(self::ENDPOINT . "/{$second->id}", $this->payload($c))
            ->assertStatus(422)->assertJsonValidationErrors('academic_period_id');
        $this->assertDatabaseCount('enrollments', 2);
    }

    public function test_no_permite_duplicado_al_cambiar_estudiante(): void
    {
        $c = $this->context();
        $first = $this->enrollment($c);
        $student2 = $this->makeStudent($c['institution'], '02');
        $record2 = $this->makeRecord($c, $student2);
        $second = $this->enrollment($c, [
            'student_id' => $student2,
            'academic_record_id' => $record2->id,
        ]);

        $this->putJson(self::ENDPOINT . "/{$second->id}", $this->payload($c))
            ->assertStatus(422)->assertJsonValidationErrors('academic_period_id');
        $this->assertDatabaseHas('enrollments', ['id' => $first->id, 'student_id' => $c['studentId']]);
    }

    public function test_no_permite_historial_de_otro_estudiante_al_actualizar(): void
    {
        $c = $this->context();
        $enrollment = $this->enrollment($c);
        $student2 = $this->makeStudent($c['institution'], '02');
        $record2 = $this->makeRecord($c, $student2);

        $this->putJson(self::ENDPOINT . "/{$enrollment->id}", $this->payload($c, [
            'academic_record_id' => $record2->id,
        ]))->assertStatus(422)->assertJsonValidationErrors('academic_record_id');
    }

    public function test_no_permite_periodo_de_otra_institucion_al_actualizar(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $enrollment = $this->enrollment($a);

        $this->putJson(self::ENDPOINT . "/{$enrollment->id}", $this->payload($a, [
            'academic_period_id' => $b['periodId'],
        ]))->assertStatus(422)->assertJsonValidationErrors('academic_period_id');
    }

    public function test_no_permite_estudiante_inexistente_al_actualizar(): void
    {
        $c = $this->context();
        $enrollment = $this->enrollment($c);
        $this->putJson(self::ENDPOINT . "/{$enrollment->id}", $this->payload($c, ['student_id' => 99999]))
            ->assertStatus(422)->assertJsonValidationErrors('student_id');
    }

    public function test_no_permite_periodo_inexistente_al_actualizar(): void
    {
        $c = $this->context();
        $enrollment = $this->enrollment($c);
        $this->putJson(self::ENDPOINT . "/{$enrollment->id}", $this->payload($c, ['academic_period_id' => 99999]))
            ->assertStatus(422)->assertJsonValidationErrors('academic_period_id');
    }

    public function test_no_permite_historial_inexistente_al_actualizar(): void
    {
        $c = $this->context();
        $enrollment = $this->enrollment($c);
        $this->putJson(self::ENDPOINT . "/{$enrollment->id}", $this->payload($c, ['academic_record_id' => 99999]))
            ->assertStatus(422)->assertJsonValidationErrors('academic_record_id');
    }

    public function test_no_permite_fecha_invalida_al_actualizar(): void
    {
        $c = $this->context();
        $enrollment = $this->enrollment($c);
        $this->putJson(self::ENDPOINT . "/{$enrollment->id}", $this->payload($c, ['enrollment_date' => 'x']))
            ->assertStatus(422)->assertJsonValidationErrors('enrollment_date');
    }

    public function test_no_permite_estado_invalido_al_actualizar(): void
    {
        $c = $this->context();
        $enrollment = $this->enrollment($c);
        $this->putJson(self::ENDPOINT . "/{$enrollment->id}", $this->payload($c, ['status' => 'inventado']))
            ->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_devuelve_404_al_consultar_matricula_inexistente(): void
    {
        $this->context(); // Autenticación y selección de institución para esta prueba.
        $this->getJson(self::ENDPOINT . '/99999')->assertStatus(404);
    }

    public function test_devuelve_404_al_actualizar_matricula_inexistente(): void
    {
        $this->context(); // Autenticación y selección de institución para esta prueba.
        $this->putJson(self::ENDPOINT . '/99999', [])->assertStatus(404);
    }

    public function test_devuelve_404_al_eliminar_matricula_inexistente(): void
    {
        $this->context(); // Autenticación y selección de institución para esta prueba.
        $this->deleteJson(self::ENDPOINT . '/99999')->assertStatus(404);
    }

    public function test_puede_eliminar_matricula_sin_cursos(): void
    {
        $c = $this->context();
        $enrollment = $this->enrollment($c);
        $this->deleteJson(self::ENDPOINT . "/{$enrollment->id}")
            ->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseMissing('enrollments', ['id' => $enrollment->id]);
    }

    public function test_eliminar_matricula_no_elimina_estudiante_periodo_ni_historial(): void
    {
        $c = $this->context();
        $enrollment = $this->enrollment($c);
        $this->deleteJson(self::ENDPOINT . "/{$enrollment->id}")->assertStatus(200);
        $this->assertDatabaseHas('students', ['id' => $c['studentId']]);
        $this->assertDatabaseHas('academic_periods', ['id' => $c['periodId']]);
        $this->assertDatabaseHas('academic_records', ['id' => $c['record']->id]);
    }

    public function test_no_permite_eliminar_matricula_con_cursos_registrados(): void
    {
        $c = $this->context();
        $enrollment = $this->enrollment($c);
        $detailId = $this->makeDetail($c, $enrollment);

        $this->deleteJson(self::ENDPOINT . "/{$enrollment->id}")
            ->assertStatus(409)->assertJsonPath('success', false);
        $this->assertDatabaseHas('enrollments', ['id' => $enrollment->id]);
        $this->assertDatabaseHas('enrollment_details', ['id' => $detailId]);
    }

    public function test_no_permite_cambiar_estudiante_si_tiene_cursos(): void
    {
        $c = $this->context();
        $enrollment = $this->enrollment($c);
        $this->makeDetail($c, $enrollment);
        $student2 = $this->makeStudent($c['institution'], '02');
        $record2 = $this->makeRecord($c, $student2);

        $this->putJson(self::ENDPOINT . "/{$enrollment->id}", $this->payload($c, [
            'student_id' => $student2, 'academic_record_id' => $record2->id,
        ]))->assertStatus(409)->assertJsonPath('success', false);
    }

    public function test_no_permite_cambiar_periodo_si_tiene_cursos(): void
    {
        $c = $this->context();
        $enrollment = $this->enrollment($c);
        $this->makeDetail($c, $enrollment);
        $period2 = $this->makePeriod($c);

        $this->putJson(self::ENDPOINT . "/{$enrollment->id}", $this->payload($c, [
            'academic_period_id' => $period2,
        ]))->assertStatus(409)->assertJsonPath('success', false);
    }

    public function test_no_permite_cambiar_historial_si_tiene_cursos(): void
    {
        $c = $this->context();
        $enrollment = $this->enrollment($c);
        $this->makeDetail($c, $enrollment);
        $record2 = $this->makeSecondProgramRecord($c);

        $this->putJson(self::ENDPOINT . "/{$enrollment->id}", $this->payload($c, [
            'academic_record_id' => $record2->id,
        ]))->assertStatus(409)->assertJsonPath('success', false);
    }

    public function test_permite_actualizar_estado_y_fecha_con_cursos_registrados(): void
    {
        $c = $this->context();
        $enrollment = $this->enrollment($c);
        $detailId = $this->makeDetail($c, $enrollment);

        $this->putJson(self::ENDPOINT . "/{$enrollment->id}", $this->payload($c, [
            'status' => 'cancelled',
            'enrollment_date' => '2026-03-02',
        ]))->assertStatus(200)->assertJsonPath('data.status', 'cancelled');
        $this->assertDatabaseHas('enrollment_details', ['id' => $detailId]);
    }

    public function test_eliminar_una_matricula_no_elimina_otra(): void
    {
        $c = $this->context();
        $first = $this->enrollment($c);
        $student2 = $this->makeStudent($c['institution'], '02');
        $record2 = $this->makeRecord($c, $student2);
        $second = $this->enrollment($c, [
            'student_id' => $student2,
            'academic_record_id' => $record2->id,
        ]);
        $this->deleteJson(self::ENDPOINT . "/{$first->id}")->assertStatus(200);
        $this->assertDatabaseHas('enrollments', ['id' => $second->id]);
    }
}
