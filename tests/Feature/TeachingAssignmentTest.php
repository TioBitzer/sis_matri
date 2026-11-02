<?php

namespace Tests\Feature;

use App\Models\AcademicOffering;
use App\Models\Institution;
use App\Models\Person;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TeachingAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/teaching-assignments';

    private ?int $selectedInstitutionId = null;

    /** Datos de una institución coherentes con las migraciones del proyecto. */
    private function context(string $suffix = 'A'): array
    {
        $institution = Institution::create([
            'name' => "Instituto {$suffix}",
            'code' => "INST{$suffix}",
            'type' => 'institute',
        ]);

        if ($this->selectedInstitutionId === null) {
            $this->selectedInstitutionId = $institution->id;
            $user = User::factory()->create();
            $user->institutions()->attach($institution->id, ['is_active' => true, 'role' => 'institution_admin']);
            $this->actingAs($user);
            $this->withHeader('X-Institution-Id', (string) $institution->id);
        }

        $unitId = DB::table('academic_units')->insertGetId([
            'institution_id' => $institution->id,
            'name' => "Programación {$suffix}",
            'code' => "PROG{$suffix}",
            'is_active' => true,
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

        return compact('institution', 'unitId', 'periodId', 'campusId');
    }

    private function teacher(array $context, string $suffix = '01', bool $active = true): Teacher
    {
        $institution = $context['institution'];
        $person = Person::create([
            'institution_id' => $institution->id,
            'first_name' => "Docente {$suffix}",
            'last_name' => 'Prueba',
            'document_type' => 'DNI',
            'document_number' => "7000{$institution->id}{$suffix}",
        ]);

        return Teacher::create([
            'person_id' => $person->id,
            'institution_id' => $institution->id,
            'employee_code' => "DOC{$institution->id}{$suffix}",
            'specialization' => 'Programación',
            'is_active' => $active,
        ]);
    }

    private function offering(array $context, string $section = 'A', bool $active = true): AcademicOffering
    {
        return AcademicOffering::create([
            'academic_unit_id' => $context['unitId'],
            'academic_period_id' => $context['periodId'],
            'campus_id' => $context['campusId'],
            'section' => $section,
            'capacity' => 30,
            'is_active' => $active,
        ]);
    }

    private function payload(Teacher $teacher, AcademicOffering $offering, array $changes = []): array
    {
        return array_replace([
            'teacher_id' => $teacher->id,
            'academic_offering_id' => $offering->id,
            'role' => 'Titular',
        ], $changes);
    }

    private function assignment(Teacher $teacher, AcademicOffering $offering, ?string $role = 'Titular'): TeachingAssignment
    {
        return TeachingAssignment::create([
            'teacher_id' => $teacher->id,
            'academic_offering_id' => $offering->id,
            'role' => $role,
        ]);
    }

    public function test_puede_crear_asignacion_con_todos_sus_campos(): void
    {
        $context = $this->context();
        $teacher = $this->teacher($context);
        $offering = $this->offering($context);

        $this->postJson(self::ENDPOINT, $this->payload($teacher, $offering))
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.teacher_id', $teacher->id)
            ->assertJsonPath('data.academic_offering_id', $offering->id)
            ->assertJsonPath('data.role', 'Titular')
            ->assertJsonPath('data.teacher.person.id', $teacher->person_id)
            ->assertJsonPath('data.academic_offering.academic_unit.id', $context['unitId'])
            ->assertJsonPath('data.academic_offering.academic_period.id', $context['periodId'])
            ->assertJsonPath('data.academic_offering.campus.id', $context['campusId']);
        $this->assertDatabaseCount('teaching_assignments', 1);
    }

    public function test_puede_crear_asignacion_sin_rol(): void
    {
        $context = $this->context();
        $payload = $this->payload($this->teacher($context), $this->offering($context));
        unset($payload['role']);

        $this->postJson(self::ENDPOINT, $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.role', null);
    }

    public function test_permite_rol_nulo(): void
    {
        $context = $this->context();
        $this->postJson(self::ENDPOINT, $this->payload(
            $this->teacher($context), $this->offering($context), ['role' => null]
        ))->assertStatus(201)->assertJsonPath('data.role', null);
    }

    public function test_lista_asignaciones_con_relaciones(): void
    {
        $context = $this->context();
        $offering = $this->offering($context);
        $first = $this->teacher($context, '01');
        $second = $this->teacher($context, '02');
        $this->assignment($first, $offering);
        $this->assignment($second, $offering, 'Auxiliar');

        $this->getJson(self::ENDPOINT)
            ->assertStatus(200)->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.teacher.person.id', $first->person_id)
            ->assertJsonPath('data.1.academic_offering.academic_unit.id', $context['unitId']);
    }

    public function test_lista_vacia_si_no_existen_asignaciones(): void
    {
        $this->context();
        $this->getJson(self::ENDPOINT)->assertStatus(200)
            ->assertJsonPath('success', true)->assertJsonCount(0, 'data');
    }

    public function test_puede_consultar_asignacion_con_relaciones(): void
    {
        $context = $this->context();
        $teacher = $this->teacher($context);
        $assignment = $this->assignment($teacher, $this->offering($context));

        $this->getJson(self::ENDPOINT . "/{$assignment->id}")
            ->assertStatus(200)->assertJsonPath('data.id', $assignment->id)
            ->assertJsonPath('data.teacher.person.id', $teacher->person_id)
            ->assertJsonPath('data.academic_offering.campus.id', $context['campusId']);
    }

    public function test_no_permite_crear_sin_campos_obligatorios(): void
    {
        $this->context();
        $this->postJson(self::ENDPOINT, [])
            ->assertStatus(422)->assertJsonValidationErrors(['teacher_id', 'academic_offering_id']);
        $this->assertDatabaseCount('teaching_assignments', 0);
    }

    public function test_no_permite_docente_inexistente(): void
    {
        $context = $this->context();
        $this->postJson(self::ENDPOINT, $this->payload(
            $this->teacher($context), $this->offering($context), ['teacher_id' => 99999]
        ))->assertStatus(422)->assertJsonValidationErrors('teacher_id');
    }

    public function test_no_permite_oferta_inexistente(): void
    {
        $context = $this->context();
        $this->postJson(self::ENDPOINT, $this->payload(
            $this->teacher($context), $this->offering($context), ['academic_offering_id' => 99999]
        ))->assertStatus(422)->assertJsonValidationErrors('academic_offering_id');
    }

    public function test_no_permite_docente_no_numerico(): void
    {
        $context = $this->context();
        $this->postJson(self::ENDPOINT, $this->payload(
            $this->teacher($context), $this->offering($context), ['teacher_id' => 'abc']
        ))->assertStatus(422)->assertJsonValidationErrors('teacher_id');
    }

    public function test_no_permite_oferta_no_numerica(): void
    {
        $context = $this->context();
        $this->postJson(self::ENDPOINT, $this->payload(
            $this->teacher($context), $this->offering($context), ['academic_offering_id' => 'abc']
        ))->assertStatus(422)->assertJsonValidationErrors('academic_offering_id');
    }

    public function test_no_permite_docente_de_otra_institucion(): void
    {
        $first = $this->context('A');
        $second = $this->context('B');
        $this->postJson(self::ENDPOINT, $this->payload(
            $this->teacher($first), $this->offering($second)
        ))->assertStatus(422)->assertJsonValidationErrors('academic_offering_id');
        $this->assertDatabaseCount('teaching_assignments', 0);
    }

    public function test_no_permite_oferta_con_periodo_de_otra_institucion(): void
    {
        $first = $this->context('A');
        $second = $this->context('B');
        $offering = $this->offering($first);
        $offering->update(['academic_period_id' => $second['periodId']]);

        $this->postJson(self::ENDPOINT, $this->payload($this->teacher($first), $offering))
            ->assertStatus(422)->assertJsonValidationErrors('academic_offering_id');
    }

    public function test_no_permite_oferta_con_sede_de_otra_institucion(): void
    {
        $first = $this->context('A');
        $second = $this->context('B');
        $offering = $this->offering($first);
        $offering->update(['campus_id' => $second['campusId']]);

        $this->postJson(self::ENDPOINT, $this->payload($this->teacher($first), $offering))
            ->assertStatus(422)->assertJsonValidationErrors('academic_offering_id');
    }

    public function test_permite_asignar_docentes_distintos_a_misma_oferta(): void
    {
        $context = $this->context();
        $offering = $this->offering($context);
        $this->postJson(self::ENDPOINT, $this->payload($this->teacher($context, '01'), $offering))
            ->assertStatus(201);
        $this->postJson(self::ENDPOINT, $this->payload($this->teacher($context, '02'), $offering))
            ->assertStatus(201);
        $this->assertDatabaseCount('teaching_assignments', 2);
    }

    public function test_permite_mismo_docente_en_ofertas_diferentes(): void
    {
        $context = $this->context();
        $teacher = $this->teacher($context);
        $this->postJson(self::ENDPOINT, $this->payload($teacher, $this->offering($context, 'A')))
            ->assertStatus(201);
        $this->postJson(self::ENDPOINT, $this->payload($teacher, $this->offering($context, 'B')))
            ->assertStatus(201);
        $this->assertDatabaseCount('teaching_assignments', 2);
    }

    public function test_no_permite_asignacion_duplicada(): void
    {
        $context = $this->context();
        $teacher = $this->teacher($context);
        $offering = $this->offering($context);
        $this->assignment($teacher, $offering);

        $this->postJson(self::ENDPOINT, $this->payload($teacher, $offering))
            ->assertStatus(422)->assertJsonValidationErrors('teacher_id');
        $this->assertDatabaseCount('teaching_assignments', 1);
    }

    public function test_no_permite_duplicado_aunque_cambie_el_rol(): void
    {
        $context = $this->context();
        $teacher = $this->teacher($context);
        $offering = $this->offering($context);
        $this->assignment($teacher, $offering);

        $this->postJson(self::ENDPOINT, $this->payload($teacher, $offering, ['role' => 'Auxiliar']))
            ->assertStatus(422)->assertJsonValidationErrors('teacher_id');
    }

    public function test_no_permite_docente_inactivo_en_nueva_asignacion(): void
    {
        $context = $this->context();
        $this->postJson(self::ENDPOINT, $this->payload(
            $this->teacher($context, '01', false), $this->offering($context)
        ))->assertStatus(422)->assertJsonValidationErrors('teacher_id');
    }

    public function test_no_permite_oferta_inactiva_en_nueva_asignacion(): void
    {
        $context = $this->context();
        $this->postJson(self::ENDPOINT, $this->payload(
            $this->teacher($context), $this->offering($context, 'A', false)
        ))->assertStatus(422)->assertJsonValidationErrors('academic_offering_id');
    }

    public function test_no_permite_rol_mayor_a_cien_caracteres(): void
    {
        $context = $this->context();
        $this->postJson(self::ENDPOINT, $this->payload(
            $this->teacher($context), $this->offering($context), ['role' => str_repeat('R', 101)]
        ))->assertStatus(422)->assertJsonValidationErrors('role');
    }

    public function test_no_permite_rol_que_no_sea_texto(): void
    {
        $context = $this->context();
        $this->postJson(self::ENDPOINT, $this->payload(
            $this->teacher($context), $this->offering($context), ['role' => ['Titular']]
        ))->assertStatus(422)->assertJsonValidationErrors('role');
    }

    public function test_permite_rol_de_cien_caracteres(): void
    {
        $context = $this->context();
        $this->postJson(self::ENDPOINT, $this->payload(
            $this->teacher($context), $this->offering($context), ['role' => str_repeat('R', 100)]
        ))->assertStatus(201);
    }

    public function test_puede_actualizar_rol(): void
    {
        $context = $this->context();
        $teacher = $this->teacher($context);
        $offering = $this->offering($context);
        $assignment = $this->assignment($teacher, $offering);

        $this->putJson(self::ENDPOINT . "/{$assignment->id}", $this->payload($teacher, $offering, [
            'role' => 'Auxiliar',
        ]))->assertStatus(200)
            ->assertJsonPath('data.role', 'Auxiliar')
            ->assertJsonPath('data.teacher.person.id', $teacher->person_id);
        $this->assertDatabaseHas('teaching_assignments', ['id' => $assignment->id, 'role' => 'Auxiliar']);
    }

    public function test_permite_actualizar_conservando_docente_y_oferta(): void
    {
        $context = $this->context();
        $teacher = $this->teacher($context);
        $offering = $this->offering($context);
        $assignment = $this->assignment($teacher, $offering);

        $this->putJson(self::ENDPOINT . "/{$assignment->id}", $this->payload($teacher, $offering))
            ->assertStatus(200)->assertJsonPath('data.id', $assignment->id);
    }

    public function test_puede_cambiar_el_docente_en_misma_institucion(): void
    {
        $context = $this->context();
        $first = $this->teacher($context, '01');
        $second = $this->teacher($context, '02');
        $offering = $this->offering($context);
        $assignment = $this->assignment($first, $offering);

        $this->putJson(self::ENDPOINT . "/{$assignment->id}", $this->payload($second, $offering))
            ->assertStatus(200)->assertJsonPath('data.teacher_id', $second->id);
    }

    public function test_puede_cambiar_la_oferta_en_misma_institucion(): void
    {
        $context = $this->context();
        $teacher = $this->teacher($context);
        $first = $this->offering($context, 'A');
        $second = $this->offering($context, 'B');
        $assignment = $this->assignment($teacher, $first);

        $this->putJson(self::ENDPOINT . "/{$assignment->id}", $this->payload($teacher, $second))
            ->assertStatus(200)->assertJsonPath('data.academic_offering_id', $second->id);
    }

    public function test_no_permite_mover_docente_y_oferta_juntos_a_otra_institucion(): void
    {
        $first = $this->context('A');
        $second = $this->context('B');
        $originalTeacher = $this->teacher($first);
        $originalOffering = $this->offering($first);
        $assignment = $this->assignment($originalTeacher, $originalOffering);
        $newTeacher = $this->teacher($second);
        $newOffering = $this->offering($second);

        $this->putJson(self::ENDPOINT . "/{$assignment->id}", $this->payload($newTeacher, $newOffering))
            ->assertStatus(422)->assertJsonValidationErrors('teacher_id');
        $this->assertDatabaseHas('teaching_assignments', [
            'id' => $assignment->id,
            'teacher_id' => $originalTeacher->id,
            'academic_offering_id' => $originalOffering->id,
        ]);
    }

    public function test_no_permite_duplicar_al_actualizar(): void
    {
        $context = $this->context();
        $teacher = $this->teacher($context);
        $first = $this->offering($context, 'A');
        $second = $this->offering($context, 'B');
        $this->assignment($teacher, $first);
        $assignment = $this->assignment($teacher, $second);

        $this->putJson(self::ENDPOINT . "/{$assignment->id}", $this->payload($teacher, $first))
            ->assertStatus(422)->assertJsonValidationErrors('teacher_id');
        $this->assertDatabaseCount('teaching_assignments', 2);
    }

    public function test_no_permite_actualizar_con_docente_de_otra_institucion(): void
    {
        $first = $this->context('A');
        $second = $this->context('B');
        $offering = $this->offering($first);
        $assignment = $this->assignment($this->teacher($first), $offering);

        $this->putJson(self::ENDPOINT . "/{$assignment->id}", $this->payload($this->teacher($second), $offering))
            ->assertStatus(422)->assertJsonValidationErrors('academic_offering_id');
    }

    public function test_no_permite_actualizar_sin_campos_obligatorios(): void
    {
        $context = $this->context();
        $assignment = $this->assignment($this->teacher($context), $this->offering($context));

        $this->putJson(self::ENDPOINT . "/{$assignment->id}", [])
            ->assertStatus(422)->assertJsonValidationErrors(['teacher_id', 'academic_offering_id']);
    }

    public function test_no_permite_actualizar_con_docente_inexistente(): void
    {
        $context = $this->context();
        $teacher = $this->teacher($context);
        $offering = $this->offering($context);
        $assignment = $this->assignment($teacher, $offering);

        $this->putJson(self::ENDPOINT . "/{$assignment->id}", $this->payload($teacher, $offering, [
            'teacher_id' => 99999,
        ]))->assertStatus(422)->assertJsonValidationErrors('teacher_id');
    }

    public function test_no_permite_actualizar_con_oferta_inexistente(): void
    {
        $context = $this->context();
        $teacher = $this->teacher($context);
        $offering = $this->offering($context);
        $assignment = $this->assignment($teacher, $offering);

        $this->putJson(self::ENDPOINT . "/{$assignment->id}", $this->payload($teacher, $offering, [
            'academic_offering_id' => 99999,
        ]))->assertStatus(422)->assertJsonValidationErrors('academic_offering_id');
    }

    public function test_no_permite_actualizar_a_docente_inactivo(): void
    {
        $context = $this->context();
        $original = $this->teacher($context, '01');
        $inactive = $this->teacher($context, '02', false);
        $offering = $this->offering($context);
        $assignment = $this->assignment($original, $offering);

        $this->putJson(self::ENDPOINT . "/{$assignment->id}", $this->payload($inactive, $offering))
            ->assertStatus(422)->assertJsonValidationErrors('teacher_id');
    }

    public function test_no_permite_actualizar_a_oferta_inactiva(): void
    {
        $context = $this->context();
        $teacher = $this->teacher($context);
        $first = $this->offering($context, 'A');
        $inactive = $this->offering($context, 'B', false);
        $assignment = $this->assignment($teacher, $first);

        $this->putJson(self::ENDPOINT . "/{$assignment->id}", $this->payload($teacher, $inactive))
            ->assertStatus(422)->assertJsonValidationErrors('academic_offering_id');
    }

    public function test_permite_actualizar_rol_si_docente_fue_desactivado(): void
    {
        $context = $this->context();
        $teacher = $this->teacher($context);
        $offering = $this->offering($context);
        $assignment = $this->assignment($teacher, $offering);
        $teacher->update(['is_active' => false]);

        $this->putJson(self::ENDPOINT . "/{$assignment->id}", $this->payload($teacher, $offering, [
            'role' => 'Histórico',
        ]))->assertStatus(200)->assertJsonPath('data.role', 'Histórico');
    }

    public function test_permite_actualizar_rol_si_oferta_fue_desactivada(): void
    {
        $context = $this->context();
        $teacher = $this->teacher($context);
        $offering = $this->offering($context);
        $assignment = $this->assignment($teacher, $offering);
        $offering->update(['is_active' => false]);

        $this->putJson(self::ENDPOINT . "/{$assignment->id}", $this->payload($teacher, $offering, [
            'role' => 'Histórico',
        ]))->assertStatus(200)->assertJsonPath('data.role', 'Histórico');
    }

    public function test_puede_actualizar_rol_a_nulo(): void
    {
        $context = $this->context();
        $teacher = $this->teacher($context);
        $offering = $this->offering($context);
        $assignment = $this->assignment($teacher, $offering);

        $this->putJson(self::ENDPOINT . "/{$assignment->id}", $this->payload($teacher, $offering, [
            'role' => null,
        ]))->assertStatus(200)->assertJsonPath('data.role', null);
    }

    public function test_no_permite_rol_mayor_a_cien_al_actualizar(): void
    {
        $context = $this->context();
        $teacher = $this->teacher($context);
        $offering = $this->offering($context);
        $assignment = $this->assignment($teacher, $offering);

        $this->putJson(self::ENDPOINT . "/{$assignment->id}", $this->payload($teacher, $offering, [
            'role' => str_repeat('R', 101),
        ]))->assertStatus(422)->assertJsonValidationErrors('role');
    }

    public function test_devuelve_404_al_consultar_asignacion_inexistente(): void
    {
        $this->context();
        $this->getJson(self::ENDPOINT . '/99999')->assertStatus(404);
    }

    public function test_devuelve_404_al_actualizar_asignacion_inexistente(): void
    {
        $this->context();
        $this->putJson(self::ENDPOINT . '/99999', [])->assertStatus(404);
    }

    public function test_devuelve_404_al_eliminar_asignacion_inexistente(): void
    {
        $this->context();
        $this->deleteJson(self::ENDPOINT . '/99999')->assertStatus(404);
    }

    public function test_puede_eliminar_asignacion(): void
    {
        $context = $this->context();
        $assignment = $this->assignment($this->teacher($context), $this->offering($context));

        $this->deleteJson(self::ENDPOINT . "/{$assignment->id}")
            ->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseMissing('teaching_assignments', ['id' => $assignment->id]);
    }

    public function test_eliminar_asignacion_no_elimina_docente_ni_oferta(): void
    {
        $context = $this->context();
        $teacher = $this->teacher($context);
        $offering = $this->offering($context);
        $assignment = $this->assignment($teacher, $offering);

        $this->deleteJson(self::ENDPOINT . "/{$assignment->id}")->assertStatus(200);
        $this->assertDatabaseHas('teachers', ['id' => $teacher->id]);
        $this->assertDatabaseHas('academic_offerings', ['id' => $offering->id]);
    }

    public function test_eliminar_asignacion_no_elimina_otra(): void
    {
        $context = $this->context();
        $offering = $this->offering($context);
        $first = $this->assignment($this->teacher($context, '01'), $offering);
        $second = $this->assignment($this->teacher($context, '02'), $offering);

        $this->deleteJson(self::ENDPOINT . "/{$first->id}")->assertStatus(200);
        $this->assertDatabaseMissing('teaching_assignments', ['id' => $first->id]);
        $this->assertDatabaseHas('teaching_assignments', ['id' => $second->id]);
    }
}
