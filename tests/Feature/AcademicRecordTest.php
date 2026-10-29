<?php

namespace Tests\Feature;

use App\Models\AcademicPlan;
use App\Models\AcademicProgram;
use App\Models\AcademicRecord;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AcademicRecordTest extends TestCase
{
    use RefreshDatabase;

    private ?int $selectedRecordInstitutionId = null;

    /** Datos independientes para cada caso, compatibles con SQLite en memoria. */
    private function context(string $suffix = 'A'): array
    {
        $institution = Institution::create([
            'name' => "Instituto Demo {$suffix}",
            'code' => "IT{$suffix}",
            'type' => 'institute',
        ]);

        // Solo el primer contexto define la institución de la solicitud.
        // Los demás contextos representan datos de otras instituciones.
        if ($this->selectedRecordInstitutionId === null) {
            $user = User::factory()->create();
            $user->institutions()->attach($institution->id, ['is_active' => true]);
            $this->actingAs($user);
            $this->withHeader('X-Institution-Id', (string) $institution->id);
            $this->selectedRecordInstitutionId = (int) $institution->id;
        }

        $program = AcademicProgram::create([
            'institution_id' => $institution->id,
            'name' => "Desarrollo de Software {$suffix}",
            'code' => "DS{$suffix}",
            'description' => 'Carrera de desarrollo',
            'is_active' => true,
        ]);

        $plan = AcademicPlan::create([
            'academic_program_id' => $program->id,
            'name' => "Plan Curricular {$suffix}",
            'version' => '1.0',
            'is_active' => true,
        ]);

        $studentId = $this->makeStudent($institution, $suffix);

        return compact('institution', 'program', 'plan', 'studentId');
    }

    private function makeStudent(Institution $institution, string $token): int
    {
        $personId = DB::table('people')->insertGetId([
            'institution_id' => $institution->id,
            'first_name' => 'Carlos',
            'last_name' => "Prueba {$token}",
            'document_number' => "DOC{$token}",
        ]);

        return DB::table('students')->insertGetId([
            'person_id' => $personId,
            'student_code' => "EST{$token}",
            'status' => 'active',
        ]);
    }

    private function makeOtherProgram(array $context, string $suffix = 'OTRO'): array
    {
        $program = AcademicProgram::create([
            'institution_id' => $context['institution']->id,
            'name' => "Administración {$suffix}",
            'code' => "AD{$suffix}",
            'is_active' => true,
        ]);
        $plan = AcademicPlan::create([
            'academic_program_id' => $program->id,
            'name' => "Plan {$suffix}",
            'version' => '1.0',
            'is_active' => true,
        ]);

        return compact('program', 'plan');
    }

    private function makeOtherPlan(array $context, string $suffix = '2027'): AcademicPlan
    {
        return AcademicPlan::create([
            'academic_program_id' => $context['program']->id,
            'name' => "Plan Curricular {$suffix}",
            'version' => '2.0',
            'is_active' => true,
        ]);
    }

    private function payload(array $context, array $changes = []): array
    {
        return array_replace([
            'student_id' => $context['studentId'],
            'academic_program_id' => $context['program']->id,
            'academic_plan_id' => $context['plan']->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
        ], $changes);
    }

    private function makeRecord(array $context, array $changes = []): AcademicRecord
    {
        return AcademicRecord::create($this->payload($context, $changes));
    }

    /** Registra una matrícula real dependiente del historial. */
    private function makeEnrollment(array $context, AcademicRecord $record): int
    {
        $periodId = DB::table('academic_periods')->insertGetId([
            'institution_id' => $context['institution']->id,
            'name' => 'Periodo 2026-I',
            'code' => '2026-I',
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
        ]);

        return DB::table('enrollments')->insertGetId([
            'student_id' => $record->student_id,
            'academic_period_id' => $periodId,
            'academic_record_id' => $record->id,
            'enrollment_date' => '2026-03-01',
            'status' => 'active',
        ]);
    }

    public function test_puede_crear_historial_academico_con_todos_sus_campos(): void
    {
        $context = $this->context();
        $response = $this->postJson('/api/academic-records', $this->payload($context));

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.student_id', $context['studentId'])
            ->assertJsonPath('data.academic_program_id', $context['program']->id)
            ->assertJsonPath('data.academic_plan_id', $context['plan']->id)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.student.person.institution_id', $context['institution']->id)
            ->assertJsonPath('data.academic_program.id', $context['program']->id)
            ->assertJsonPath('data.academic_plan.id', $context['plan']->id);

        $recordId = $response->json('data.id');
        $this->assertSame('2026-01-01', substr($response->json('data.start_date'), 0, 10));
        $this->assertSame('2026-12-31', substr($response->json('data.end_date'), 0, 10));
        $this->assertDatabaseHas('academic_records', [
            'id' => $recordId,
            'student_id' => $context['studentId'],
            'academic_program_id' => $context['program']->id,
            'academic_plan_id' => $context['plan']->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseCount('academic_records', 1);
    }

    public function test_permite_crear_historial_sin_fecha_fin(): void
    {
        $context = $this->context();
        $payload = $this->payload($context);
        unset($payload['end_date']);

        $this->postJson('/api/academic-records', $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.end_date', null);
        $this->assertDatabaseCount('academic_records', 1);
    }

    public function test_permite_crear_historial_con_estado_completado(): void
    {
        $context = $this->context();
        $this->postJson('/api/academic-records', $this->payload($context, ['status' => 'completed']))
            ->assertStatus(201)->assertJsonPath('data.status', 'completed');
    }

    public function test_puede_consultar_historial_con_sus_relaciones(): void
    {
        $context = $this->context();
        $record = $this->makeRecord($context);

        $this->getJson("/api/academic-records/{$record->id}")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $record->id)
            ->assertJsonPath('data.student.id', $context['studentId'])
            ->assertJsonPath('data.student.person.institution_id', $context['institution']->id)
            ->assertJsonPath('data.academic_program.id', $context['program']->id)
            ->assertJsonPath('data.academic_plan.id', $context['plan']->id);
    }

    public function test_puede_listar_historiales_academicos_con_relaciones(): void
    {
        $context = $this->context();
        $this->makeRecord($context);
        $secondStudent = $this->makeStudent($context['institution'], 'A2');
        $this->makeRecord($context, ['student_id' => $secondStudent]);

        $this->getJson('/api/academic-records')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.academic_program.id', $context['program']->id)
            ->assertJsonPath('data.1.student.id', $secondStudent);
    }

    public function test_lista_vacia_si_no_hay_historiales(): void
    {
        $this->context(); // Inicializa autenticación y la institución seleccionada.
        $this->getJson('/api/academic-records')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_no_permite_crear_sin_campos_obligatorios(): void
    {
        $this->context(); // Inicializa autenticación y la institución seleccionada.
        $this->postJson('/api/academic-records', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'student_id', 'academic_program_id', 'academic_plan_id', 'start_date', 'status',
            ]);
        $this->assertDatabaseCount('academic_records', 0);
    }

    public function test_no_permite_estudiante_inexistente(): void
    {
        $context = $this->context();
        $this->postJson('/api/academic-records', $this->payload($context, ['student_id' => 99999]))
            ->assertStatus(422)->assertJsonValidationErrors('student_id');
    }

    public function test_no_permite_programa_inexistente(): void
    {
        $context = $this->context();
        $this->postJson('/api/academic-records', $this->payload($context, ['academic_program_id' => 99999]))
            ->assertStatus(422)->assertJsonValidationErrors('academic_program_id');
    }

    public function test_no_permite_plan_inexistente(): void
    {
        $context = $this->context();
        $this->postJson('/api/academic-records', $this->payload($context, ['academic_plan_id' => 99999]))
            ->assertStatus(422)->assertJsonValidationErrors('academic_plan_id');
    }

    public function test_no_permite_plan_de_otro_programa_en_la_misma_institucion(): void
    {
        $context = $this->context();
        $other = $this->makeOtherProgram($context);
        $this->postJson('/api/academic-records', $this->payload($context, ['academic_plan_id' => $other['plan']->id]))
            ->assertStatus(422)->assertJsonValidationErrors('academic_plan_id');
        $this->assertDatabaseCount('academic_records', 0);
    }

    public function test_no_permite_estudiante_de_otra_institucion(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $this->postJson('/api/academic-records', $this->payload($a, ['student_id' => $b['studentId']]))
            ->assertStatus(422)->assertJsonValidationErrors('student_id');
        $this->assertDatabaseCount('academic_records', 0);
    }

    public function test_no_permite_programa_de_otra_institucion(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $this->postJson('/api/academic-records', $this->payload($a, [
            'academic_program_id' => $b['program']->id,
            'academic_plan_id' => $b['plan']->id,
        ]))->assertStatus(422)->assertJsonValidationErrors('student_id');
    }

    public function test_permite_otro_programa_de_la_misma_institucion(): void
    {
        $context = $this->context();
        $other = $this->makeOtherProgram($context);
        $this->postJson('/api/academic-records', $this->payload($context, [
            'academic_program_id' => $other['program']->id,
            'academic_plan_id' => $other['plan']->id,
        ]))->assertStatus(201)
            ->assertJsonPath('data.academic_program_id', $other['program']->id);
    }

    public function test_no_permite_fecha_fin_anterior_al_inicio(): void
    {
        $context = $this->context();
        $this->postJson('/api/academic-records', $this->payload($context, [
            'start_date' => '2026-09-01', 'end_date' => '2026-01-01',
        ]))->assertStatus(422)->assertJsonValidationErrors('end_date');
    }

    public function test_permite_fecha_fin_igual_a_inicio(): void
    {
        $context = $this->context();
        $this->postJson('/api/academic-records', $this->payload($context, [
            'start_date' => '2026-01-01', 'end_date' => '2026-01-01',
        ]))->assertStatus(201);
    }

    public function test_no_permite_fecha_inicio_invalida(): void
    {
        $context = $this->context();
        $this->postJson('/api/academic-records', $this->payload($context, ['start_date' => 'fecha-falsa']))
            ->assertStatus(422)->assertJsonValidationErrors('start_date');
    }

    public function test_no_permite_fecha_fin_invalida(): void
    {
        $context = $this->context();
        $this->postJson('/api/academic-records', $this->payload($context, ['end_date' => 'fecha-falsa']))
            ->assertStatus(422)->assertJsonValidationErrors('end_date');
    }

    public function test_no_permite_estado_desconocido(): void
    {
        $context = $this->context();
        $this->postJson('/api/academic-records', $this->payload($context, ['status' => 'inventado']))
            ->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_no_permite_estado_mayor_a_50_caracteres(): void
    {
        $context = $this->context();
        $this->postJson('/api/academic-records', $this->payload($context, ['status' => str_repeat('a', 51)]))
            ->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_puede_actualizar_historial_academico(): void
    {
        $context = $this->context();
        $record = $this->makeRecord($context);
        $changes = $this->payload($context, [
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
            'status' => 'completed',
        ]);

        $response = $this->putJson("/api/academic-records/{$record->id}", $changes);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $record->id)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.academic_plan.id', $context['plan']->id);
        $this->assertSame('2027-01-01', substr($response->json('data.start_date'), 0, 10));
        $this->assertSame('2027-12-31', substr($response->json('data.end_date'), 0, 10));
        $this->assertDatabaseHas('academic_records', ['id' => $record->id, 'status' => 'completed']);
    }

    public function test_permite_actualizar_conservando_estudiante_programa_y_plan(): void
    {
        $context = $this->context();
        $record = $this->makeRecord($context);
        $this->putJson("/api/academic-records/{$record->id}", $this->payload($context, [
            'status' => 'suspended',
        ]))->assertStatus(200)->assertJsonPath('data.status', 'suspended');
        $this->assertDatabaseCount('academic_records', 1);
    }

    public function test_no_permite_actualizar_sin_campos_obligatorios(): void
    {
        $context = $this->context();
        $record = $this->makeRecord($context);
        $this->putJson("/api/academic-records/{$record->id}", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'student_id', 'academic_program_id', 'academic_plan_id', 'start_date', 'status',
            ]);
        $this->assertDatabaseHas('academic_records', ['id' => $record->id, 'status' => 'active']);
    }

    public function test_no_permite_plan_de_otro_programa_al_actualizar(): void
    {
        $context = $this->context();
        $record = $this->makeRecord($context);
        $other = $this->makeOtherProgram($context);
        $this->putJson("/api/academic-records/{$record->id}", $this->payload($context, [
            'academic_plan_id' => $other['plan']->id,
        ]))->assertStatus(422)->assertJsonValidationErrors('academic_plan_id');
        $this->assertDatabaseHas('academic_records', ['id' => $record->id, 'academic_plan_id' => $context['plan']->id]);
    }

    public function test_no_permite_estudiante_de_otra_institucion_al_actualizar(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $record = $this->makeRecord($a);
        $this->putJson("/api/academic-records/{$record->id}", $this->payload($a, [
            'student_id' => $b['studentId'],
        ]))->assertStatus(422)->assertJsonValidationErrors('student_id');
        $this->assertDatabaseHas('academic_records', ['id' => $record->id, 'student_id' => $a['studentId']]);
    }

    public function test_no_permite_fecha_fin_anterior_al_inicio_al_actualizar(): void
    {
        $context = $this->context();
        $record = $this->makeRecord($context);
        $this->putJson("/api/academic-records/{$record->id}", $this->payload($context, [
            'start_date' => '2027-05-01',
            'end_date' => '2026-01-01',
        ]))->assertStatus(422)->assertJsonValidationErrors('end_date');
        $this->assertDatabaseHas('academic_records', ['id' => $record->id, 'status' => 'active']);
    }

    public function test_no_permite_estado_invalido_al_actualizar(): void
    {
        $context = $this->context();
        $record = $this->makeRecord($context);
        $this->putJson("/api/academic-records/{$record->id}", $this->payload($context, [
            'status' => 'invalido',
        ]))->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_puede_cambiar_a_otro_plan_del_mismo_programa_sin_matriculas(): void
    {
        $context = $this->context();
        $record = $this->makeRecord($context);
        $plan = $this->makeOtherPlan($context);
        $this->putJson("/api/academic-records/{$record->id}", $this->payload($context, [
            'academic_plan_id' => $plan->id,
        ]))->assertStatus(200)->assertJsonPath('data.academic_plan_id', $plan->id);
    }

    public function test_devuelve_404_al_consultar_historial_inexistente(): void
    {
        $this->context(); // Inicializa autenticación y la institución seleccionada.
        $this->getJson('/api/academic-records/99999')->assertStatus(404);
    }

    public function test_devuelve_404_al_actualizar_historial_inexistente(): void
    {
        $context = $this->context();
        $this->putJson('/api/academic-records/99999', $this->payload($context))->assertStatus(404);
    }

    public function test_devuelve_404_al_eliminar_historial_inexistente(): void
    {
        $this->context(); // Inicializa autenticación y la institución seleccionada.
        $this->deleteJson('/api/academic-records/99999')->assertStatus(404);
    }

    public function test_puede_eliminar_historial_sin_matriculas(): void
    {
        $context = $this->context();
        $record = $this->makeRecord($context);
        $this->deleteJson("/api/academic-records/{$record->id}")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Registro académico eliminado correctamente');
        $this->assertDatabaseMissing('academic_records', ['id' => $record->id]);
        $this->assertDatabaseHas('students', ['id' => $context['studentId']]);
        $this->assertDatabaseHas('academic_plans', ['id' => $context['plan']->id]);
    }

    public function test_no_permite_eliminar_historial_con_matriculas(): void
    {
        $context = $this->context();
        $record = $this->makeRecord($context);
        $enrollmentId = $this->makeEnrollment($context, $record);

        $this->deleteJson("/api/academic-records/{$record->id}")
            ->assertStatus(409)->assertJsonPath('success', false);
        $this->assertDatabaseHas('academic_records', ['id' => $record->id]);
        $this->assertDatabaseHas('enrollments', ['id' => $enrollmentId, 'academic_record_id' => $record->id]);
    }

    public function test_no_permite_cambiar_estudiante_si_tiene_matriculas(): void
    {
        $context = $this->context();
        $record = $this->makeRecord($context);
        $this->makeEnrollment($context, $record);
        $second = $this->makeStudent($context['institution'], 'A2');

        $this->putJson("/api/academic-records/{$record->id}", $this->payload($context, [
            'student_id' => $second,
        ]))->assertStatus(409)->assertJsonPath('success', false);
        $this->assertDatabaseHas('academic_records', ['id' => $record->id, 'student_id' => $context['studentId']]);
    }

    public function test_no_permite_cambiar_programa_si_tiene_matriculas(): void
    {
        $context = $this->context();
        $record = $this->makeRecord($context);
        $this->makeEnrollment($context, $record);
        $other = $this->makeOtherProgram($context);

        $this->putJson("/api/academic-records/{$record->id}", $this->payload($context, [
            'academic_program_id' => $other['program']->id,
            'academic_plan_id' => $other['plan']->id,
        ]))->assertStatus(409)->assertJsonPath('success', false);
        $this->assertDatabaseHas('academic_records', ['id' => $record->id, 'academic_program_id' => $context['program']->id]);
    }

    public function test_no_permite_cambiar_plan_si_tiene_matriculas(): void
    {
        $context = $this->context();
        $record = $this->makeRecord($context);
        $this->makeEnrollment($context, $record);
        $otherPlan = $this->makeOtherPlan($context);

        $this->putJson("/api/academic-records/{$record->id}", $this->payload($context, [
            'academic_plan_id' => $otherPlan->id,
        ]))->assertStatus(409)->assertJsonPath('success', false);
        $this->assertDatabaseHas('academic_records', ['id' => $record->id, 'academic_plan_id' => $context['plan']->id]);
    }

    public function test_permite_actualizar_estado_y_fechas_si_tiene_matriculas(): void
    {
        $context = $this->context();
        $record = $this->makeRecord($context);
        $enrollmentId = $this->makeEnrollment($context, $record);

        $response = $this->putJson("/api/academic-records/{$record->id}", $this->payload($context, [
            'end_date' => '2027-12-31',
            'status' => 'completed',
        ]));
        $response->assertStatus(200)->assertJsonPath('data.status', 'completed');
        $this->assertSame('2027-12-31', substr($response->json('data.end_date'), 0, 10));
        $this->assertDatabaseHas('enrollments', ['id' => $enrollmentId, 'academic_record_id' => $record->id]);
    }
}
