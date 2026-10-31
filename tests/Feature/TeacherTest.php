<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\Person;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TeacherTest extends TestCase
{
    use RefreshDatabase;

    private ?User $authorizedUser = null;

    private function institution(string $suffix = 'A'): Institution
    {
        $institution = Institution::create([
            'name' => "Instituto {$suffix}",
            'code' => "INST{$suffix}",
            'type' => 'institute',
        ]);

        if ($this->authorizedUser === null) {
            $this->authorizedUser = User::factory()->create();
            $this->actingAs($this->authorizedUser);
            $this->withHeader('X-Institution-Id', (string) $institution->id);
        }

        $this->authorizedUser->institutions()->attach($institution->id, ['is_active' => true, 'role' => 'institution_admin']);

        return $institution;
    }

    private function person(Institution $institution, string $suffix = '01'): Person
    {
        return Person::create([
            'institution_id' => $institution->id,
            'first_name' => "Docente {$suffix}",
            'last_name' => 'Prueba',
            'document_type' => 'DNI',
            'document_number' => "7000{$institution->id}{$suffix}",
        ]);
    }

    private function payload(Person $person, array $changes = []): array
    {
        return array_replace([
            'person_id' => $person->id,
            'institution_id' => $person->institution_id,
            'employee_code' => "DOC{$person->id}",
            'specialization' => 'Programación',
            'is_active' => true,
        ], $changes);
    }

    private function teacher(Person $person, array $changes = []): Teacher
    {
        return Teacher::create($this->payload($person, $changes));
    }

    /** Crea una asignación real con las tablas existentes del proyecto. */
    private function assignment(Teacher $teacher): int
    {
        $institutionId = $teacher->institution_id;
        $unitId = DB::table('academic_units')->insertGetId([
            'institution_id' => $institutionId,
            'name' => 'Programación I',
            'code' => "PROG{$teacher->id}",
            'is_active' => true,
        ]);
        $periodId = DB::table('academic_periods')->insertGetId([
            'institution_id' => $institutionId,
            'name' => 'Periodo 2026-I',
            'code' => "2026-{$teacher->id}",
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
        ]);
        $offeringId = DB::table('academic_offerings')->insertGetId([
            'academic_unit_id' => $unitId,
            'academic_period_id' => $periodId,
            'section' => 'A',
            'capacity' => 30,
            'is_active' => true,
        ]);

        return DB::table('teaching_assignments')->insertGetId([
            'teacher_id' => $teacher->id,
            'academic_offering_id' => $offeringId,
            'role' => 'Titular',
        ]);
    }

    public function test_puede_crear_docente_con_todos_sus_campos(): void
    {
        $institution = $this->institution();
        $person = $this->person($institution);

        $response = $this->postJson('/api/teachers', $this->payload($person));

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.person_id', $person->id)
            ->assertJsonPath('data.institution_id', $institution->id)
            ->assertJsonPath('data.employee_code', "DOC{$person->id}")
            ->assertJsonPath('data.specialization', 'Programación')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.person.id', $person->id)
            ->assertJsonPath('data.institution.id', $institution->id);

        $this->assertDatabaseCount('teachers', 1);
    }

    public function test_crea_docente_activo_por_defecto(): void
    {
        $person = $this->person($this->institution());
        $payload = $this->payload($person);
        unset($payload['is_active']);

        $this->postJson('/api/teachers', $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.is_active', true);
        $this->assertDatabaseHas('teachers', ['person_id' => $person->id, 'is_active' => 1]);
    }

    public function test_puede_crear_docente_inactivo(): void
    {
        $person = $this->person($this->institution());
        $this->postJson('/api/teachers', $this->payload($person, ['is_active' => false]))
            ->assertStatus(201)->assertJsonPath('data.is_active', false);
        $this->assertDatabaseHas('teachers', ['person_id' => $person->id, 'is_active' => 0]);
    }

    public function test_puede_crear_docente_sin_campos_opcionales(): void
    {
        $person = $this->person($this->institution());
        $payload = $this->payload($person);
        unset($payload['employee_code'], $payload['specialization'], $payload['is_active']);

        $this->postJson('/api/teachers', $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.employee_code', null)
            ->assertJsonPath('data.specialization', null)
            ->assertJsonPath('data.is_active', true);
    }

    public function test_permite_campos_opcionales_nulos(): void
    {
        $person = $this->person($this->institution());
        $this->postJson('/api/teachers', $this->payload($person, [
            'employee_code' => null,
            'specialization' => null,
        ]))->assertStatus(201)->assertJsonPath('data.employee_code', null);
    }

    public function test_puede_listar_docentes_con_persona_e_institucion(): void
    {
        $institution = $this->institution();
        $this->teacher($this->person($institution, '01'));
        $this->teacher($this->person($institution, '02'));

        $this->getJson('/api/teachers')->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.institution.id', $institution->id)
            ->assertJsonPath('data.1.person.first_name', 'Docente 02');
    }

    public function test_lista_vacia_si_no_existen_docentes(): void
    {
        $this->institution();
        $this->getJson('/api/teachers')->assertStatus(200)
            ->assertJsonPath('success', true)->assertJsonCount(0, 'data');
    }

    public function test_puede_consultar_docente_con_sus_relaciones(): void
    {
        $institution = $this->institution();
        $teacher = $this->teacher($this->person($institution));

        $this->getJson("/api/teachers/{$teacher->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $teacher->id)
            ->assertJsonPath('data.person.id', $teacher->person_id)
            ->assertJsonPath('data.institution.id', $institution->id);
    }

    public function test_no_permite_crear_sin_campos_obligatorios(): void
{
    $this->institution(); // Inicializa usuario y membresía autorizada

    $this->postJson('/api/teachers', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['person_id', 'institution_id']);

    $this->assertDatabaseCount('teachers', 0);
}

    public function test_no_permite_persona_inexistente(): void
    {
        $institution = $this->institution();
        $this->postJson('/api/teachers', [
            'person_id' => 99999,
            'institution_id' => $institution->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['person_id']);
    }

    public function test_no_permite_institucion_inexistente(): void
    {
        $person = $this->person($this->institution());
        $this->postJson('/api/teachers', $this->payload($person, [
            'institution_id' => 99999,
        ]))->assertStatus(422)->assertJsonValidationErrors(['institution_id']);
    }

    public function test_no_permite_persona_no_numerica(): void
    {
        $person = $this->person($this->institution());
        $this->postJson('/api/teachers', $this->payload($person, [
            'person_id' => 'abc',
        ]))->assertStatus(422)->assertJsonValidationErrors(['person_id']);
    }

    public function test_no_permite_institucion_no_numerica(): void
    {
        $person = $this->person($this->institution());
        $this->postJson('/api/teachers', $this->payload($person, [
            'institution_id' => 'abc',
        ]))->assertStatus(422)->assertJsonValidationErrors(['institution_id']);
    }

    public function test_no_permite_persona_de_otra_institucion(): void
    {
        $first = $this->institution('A');
        $second = $this->institution('B');
        $person = $this->person($first);

        $this->postJson('/api/teachers', $this->payload($person, [
            'institution_id' => $second->id,
        ]))->assertStatus(422)->assertJsonValidationErrors(['person_id']);
        $this->assertDatabaseCount('teachers', 0);
    }

    public function test_no_permite_registrar_dos_veces_a_la_misma_persona(): void
    {
        $person = $this->person($this->institution());
        $this->teacher($person);

        $this->postJson('/api/teachers', $this->payload($person, [
            'employee_code' => 'OTRO',
        ]))->assertStatus(422)->assertJsonValidationErrors(['person_id']);
        $this->assertDatabaseCount('teachers', 1);
    }

    public function test_permite_docentes_distintos_en_la_misma_institucion(): void
    {
        $institution = $this->institution();
        $this->postJson('/api/teachers', $this->payload($this->person($institution, '01')))
            ->assertStatus(201);
        $this->postJson('/api/teachers', $this->payload($this->person($institution, '02')))
            ->assertStatus(201);
        $this->assertDatabaseCount('teachers', 2);
    }

    public function test_no_permite_codigo_duplicado_en_la_misma_institucion(): void
    {
        $institution = $this->institution();
        $this->teacher($this->person($institution, '01'), ['employee_code' => 'PROF001']);

        $this->postJson('/api/teachers', $this->payload(
            $this->person($institution, '02'), ['employee_code' => 'PROF001']
        ))->assertStatus(422)->assertJsonValidationErrors(['employee_code']);
        $this->assertDatabaseCount('teachers', 1);
    }

    public function test_permite_mismo_codigo_en_instituciones_diferentes(): void
    {
        $first = $this->institution('A');
        $second = $this->institution('B');
        $this->teacher($this->person($first), ['employee_code' => 'PROF001']);

        $this->withHeader('X-Institution-Id', (string) $second->id);

        $this->postJson('/api/teachers', $this->payload(
            $this->person($second), ['employee_code' => 'PROF001']
        ))->assertStatus(201);
        $this->assertDatabaseCount('teachers', 2);
    }

    public function test_no_permite_codigo_superior_a_50_caracteres(): void
    {
        $person = $this->person($this->institution());
        $this->postJson('/api/teachers', $this->payload($person, [
            'employee_code' => str_repeat('X', 51),
        ]))->assertStatus(422)->assertJsonValidationErrors(['employee_code']);
    }

    public function test_no_permite_codigo_que_no_sea_texto(): void
    {
        $person = $this->person($this->institution());
        $this->postJson('/api/teachers', $this->payload($person, [
            'employee_code' => ['COD', '001'],
        ]))->assertStatus(422)->assertJsonValidationErrors(['employee_code']);
    }

    public function test_no_permite_especializacion_superior_a_255_caracteres(): void
    {
        $person = $this->person($this->institution());
        $this->postJson('/api/teachers', $this->payload($person, [
            'specialization' => str_repeat('X', 256),
        ]))->assertStatus(422)->assertJsonValidationErrors(['specialization']);
    }

    public function test_no_permite_estado_invalido(): void
    {
        $person = $this->person($this->institution());
        $this->postJson('/api/teachers', $this->payload($person, [
            'is_active' => 'desconocido',
        ]))->assertStatus(422)->assertJsonValidationErrors(['is_active']);
    }

    public function test_puede_actualizar_docente(): void
    {
        $person = $this->person($this->institution());
        $teacher = $this->teacher($person);

        $this->putJson("/api/teachers/{$teacher->id}", $this->payload($person, [
            'employee_code' => 'PROF2026',
            'specialization' => 'Redes',
            'is_active' => false,
        ]))->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.employee_code', 'PROF2026')
            ->assertJsonPath('data.specialization', 'Redes')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('teachers', [
            'id' => $teacher->id,
            'person_id' => $person->id,
            'employee_code' => 'PROF2026',
            'specialization' => 'Redes',
            'is_active' => 0,
        ]);
    }

    public function test_permite_actualizar_conservando_persona_y_codigo(): void
    {
        $person = $this->person($this->institution());
        $teacher = $this->teacher($person);

        $this->putJson("/api/teachers/{$teacher->id}", $this->payload($person, [
            'specialization' => 'Bases de datos',
        ]))->assertStatus(200)
            ->assertJsonPath('data.id', $teacher->id)
            ->assertJsonPath('data.specialization', 'Bases de datos');
        $this->assertDatabaseCount('teachers', 1);
    }

    public function test_puede_actualizar_solo_especializacion_y_conservar_estado(): void
    {
        $person = $this->person($this->institution());
        $teacher = $this->teacher($person, ['is_active' => false]);
        $payload = $this->payload($person, ['specialization' => 'Diseño de software']);
        unset($payload['is_active']);

        $this->putJson("/api/teachers/{$teacher->id}", $payload)
            ->assertStatus(200)
            ->assertJsonPath('data.specialization', 'Diseño de software')
            ->assertJsonPath('data.is_active', false);
    }

    public function test_no_permite_actualizar_sin_campos_obligatorios(): void
    {
        $person = $this->person($this->institution());
        $teacher = $this->teacher($person);
        $this->putJson("/api/teachers/{$teacher->id}", [
            'specialization' => 'Redes',
        ])->assertStatus(422)->assertJsonValidationErrors(['person_id', 'institution_id']);
        $this->assertDatabaseHas('teachers', ['id' => $teacher->id, 'specialization' => 'Programación']);
    }

    public function test_no_permite_actualizar_con_persona_inexistente(): void
    {
        $person = $this->person($this->institution());
        $teacher = $this->teacher($person);
        $this->putJson("/api/teachers/{$teacher->id}", $this->payload($person, [
            'person_id' => 99999,
        ]))->assertStatus(422)->assertJsonValidationErrors(['person_id']);
    }

    public function test_no_permite_actualizar_con_institucion_inexistente(): void
    {
        $person = $this->person($this->institution());
        $teacher = $this->teacher($person);
        $this->putJson("/api/teachers/{$teacher->id}", $this->payload($person, [
            'institution_id' => 99999,
        ]))->assertStatus(422)->assertJsonValidationErrors(['institution_id']);
    }

    public function test_no_permite_cambiar_a_persona_de_otra_institucion(): void
    {
        $first = $this->institution('A');
        $second = $this->institution('B');
        $teacherPerson = $this->person($first);
        $teacher = $this->teacher($teacherPerson);
        $otherPerson = $this->person($second);

        $this->putJson("/api/teachers/{$teacher->id}", $this->payload($teacherPerson, [
            'person_id' => $otherPerson->id,
        ]))->assertStatus(422)->assertJsonValidationErrors(['person_id']);
    }

    public function test_no_permite_persona_duplicada_al_actualizar(): void
    {
        $institution = $this->institution();
        $firstPerson = $this->person($institution, '01');
        $secondPerson = $this->person($institution, '02');
        $this->teacher($firstPerson);
        $other = $this->teacher($secondPerson);

        $this->putJson("/api/teachers/{$other->id}", $this->payload($secondPerson, [
            'person_id' => $firstPerson->id,
            'employee_code' => 'CODIGONUEVO',
        ]))->assertStatus(422)->assertJsonValidationErrors(['person_id']);
        $this->assertDatabaseHas('teachers', ['id' => $other->id, 'person_id' => $secondPerson->id]);
    }

    public function test_no_permite_codigo_duplicado_al_actualizar(): void
    {
        $institution = $this->institution();
        $first = $this->teacher($this->person($institution, '01'), ['employee_code' => 'PROF001']);
        $second = $this->teacher($this->person($institution, '02'), ['employee_code' => 'PROF002']);

        $this->putJson("/api/teachers/{$second->id}", $this->payload($second->person, [
            'employee_code' => $first->employee_code,
        ]))->assertStatus(422)->assertJsonValidationErrors(['employee_code']);
        $this->assertDatabaseHas('teachers', ['id' => $second->id, 'employee_code' => 'PROF002']);
    }

    public function test_permite_actualizar_codigo_a_nulo(): void
    {
        $person = $this->person($this->institution());
        $teacher = $this->teacher($person);

        $this->putJson("/api/teachers/{$teacher->id}", $this->payload($person, [
            'employee_code' => null,
        ]))->assertStatus(200)->assertJsonPath('data.employee_code', null);
    }

    public function test_permite_cambiar_persona_si_no_tiene_asignaciones(): void
    {
        $institution = $this->institution();
        $oldPerson = $this->person($institution, '01');
        $newPerson = $this->person($institution, '02');
        $teacher = $this->teacher($oldPerson);

        $this->putJson("/api/teachers/{$teacher->id}", $this->payload($newPerson, [
            'employee_code' => 'PROF999',
        ]))->assertStatus(200)->assertJsonPath('data.person_id', $newPerson->id);
        $this->assertDatabaseHas('teachers', ['id' => $teacher->id, 'person_id' => $newPerson->id]);
    }

    public function test_no_permite_cambiar_institucion_con_nueva_persona_sin_asignaciones(): void
    {
        $first = $this->institution('A');
        $second = $this->institution('B');
        $teacher = $this->teacher($this->person($first));
        $newPerson = $this->person($second);

        $this->putJson("/api/teachers/{$teacher->id}", $this->payload($newPerson))
            ->assertStatus(422)->assertJsonValidationErrors(['institution_id']);

        $this->assertDatabaseHas('teachers', [
            'id' => $teacher->id, 'institution_id' => $first->id,
        ]);
    }

    public function test_devuelve_404_al_consultar_docente_inexistente(): void
    {
        $this->institution();
        $this->getJson('/api/teachers/99999')->assertStatus(404);
    }

    public function test_devuelve_404_al_actualizar_docente_inexistente(): void
    {
        $person = $this->person($this->institution());
        $this->putJson('/api/teachers/99999', $this->payload($person))->assertStatus(404);
    }

    public function test_devuelve_404_al_eliminar_docente_inexistente(): void
    {
        $this->institution();
        $this->deleteJson('/api/teachers/99999')->assertStatus(404);
    }

    public function test_puede_eliminar_docente_sin_asignaciones(): void
    {
        $person = $this->person($this->institution());
        $teacher = $this->teacher($person);

        $this->deleteJson("/api/teachers/{$teacher->id}")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Profesor eliminado correctamente');

        $this->assertDatabaseMissing('teachers', ['id' => $teacher->id]);
        $this->assertDatabaseHas('people', ['id' => $person->id]);
    }

    public function test_eliminar_un_docente_no_elimina_a_los_demas(): void
    {
        $institution = $this->institution();
        $first = $this->teacher($this->person($institution, '01'));
        $second = $this->teacher($this->person($institution, '02'));

        $this->deleteJson("/api/teachers/{$first->id}")->assertStatus(200);
        $this->assertDatabaseCount('teachers', 1);
        $this->assertDatabaseHas('teachers', ['id' => $second->id]);
    }

    public function test_no_permite_eliminar_docente_con_asignaciones(): void
    {
        $teacher = $this->teacher($this->person($this->institution()));
        $assignmentId = $this->assignment($teacher);

        $this->deleteJson("/api/teachers/{$teacher->id}")
            ->assertStatus(409)->assertJsonPath('success', false);

        $this->assertDatabaseHas('teachers', ['id' => $teacher->id]);
        $this->assertDatabaseHas('teaching_assignments', ['id' => $assignmentId, 'teacher_id' => $teacher->id]);
    }

    public function test_no_permite_cambiar_persona_de_docente_con_asignaciones(): void
    {
        $institution = $this->institution();
        $person = $this->person($institution, '01');
        $teacher = $this->teacher($person);
        $newPerson = $this->person($institution, '02');
        $this->assignment($teacher);

        $this->putJson("/api/teachers/{$teacher->id}", $this->payload($newPerson, [
            'employee_code' => 'PROFNUEVO',
        ]))->assertStatus(409)->assertJsonPath('success', false);

        $this->assertDatabaseHas('teachers', ['id' => $teacher->id, 'person_id' => $person->id]);
        $this->assertDatabaseCount('teaching_assignments', 1);
    }

    public function test_no_permite_cambiar_institucion_de_docente_con_asignaciones(): void
    {
        $first = $this->institution('A');
        $second = $this->institution('B');
        $person = $this->person($first);
        $teacher = $this->teacher($person);
        $newPerson = $this->person($second);
        $this->assignment($teacher);

        $this->putJson("/api/teachers/{$teacher->id}", $this->payload($newPerson, [
            'employee_code' => 'PROFOTRO',
        ]))->assertStatus(422)->assertJsonValidationErrors(['institution_id']);

        $this->assertDatabaseHas('teachers', ['id' => $teacher->id, 'institution_id' => $first->id]);
    }

    public function test_permite_actualizar_especializacion_con_asignaciones(): void
    {
        $person = $this->person($this->institution());
        $teacher = $this->teacher($person);
        $assignmentId = $this->assignment($teacher);

        $this->putJson("/api/teachers/{$teacher->id}", $this->payload($person, [
            'specialization' => 'Administración de bases de datos',
            'is_active' => false,
        ]))->assertStatus(200)
            ->assertJsonPath('data.specialization', 'Administración de bases de datos')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('teaching_assignments', [
            'id' => $assignmentId,
            'teacher_id' => $teacher->id,
        ]);
    }

    public function test_una_persona_puede_ser_docente_y_estudiante(): void
    {
        $person = $this->person($this->institution());
        DB::table('students')->insert([
            'person_id' => $person->id,
            'student_code' => 'ESTDOC001',
            'status' => 'active',
        ]);

        $this->postJson('/api/teachers', $this->payload($person))->assertStatus(201);
        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseCount('teachers', 1);
    }
}
