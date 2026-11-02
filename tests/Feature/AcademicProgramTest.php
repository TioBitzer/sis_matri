<?php

namespace Tests\Feature;

use App\Models\AcademicProgram;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicProgramTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Autentica un miembro activo y fija el contexto institucional autorizado.
     */
    private function autenticarEnInstitucion(Institution $institution): void
    {
        $user = User::factory()->create();
        $user->institutions()->attach($institution->id, ['is_active' => true, 'role' => 'institution_admin']);

        $this->actingAs($user);
        $this->withHeader('X-Institution-Id', (string) $institution->id);
    }

    /** Crea una institución autorizada para las pruebas de validación y 404. */
    private function crearInstitucionAutorizada(): Institution
    {
        return Institution::create([
            'name' => 'Institución autorizada',
            'code' => 'AUTH',
            'type' => 'institute',
            'is_active' => true,
        ]);
    }

    public function test_puede_crear_programa_academico_con_todos_sus_campos(): void
    {
        $institution = Institution::create([
            'name' => 'Instituto Tecnológico Demo',
            'code' => 'ITD',
            'type' => 'institute',
            'is_active' => true,
        ]);

        $this->autenticarEnInstitucion($institution);

        $response = $this->postJson('/api/academic-programs', [
            'institution_id' => $institution->id,
            'name' => 'Desarrollo de Software',
            'code' => 'DS',
            'description' => 'Programa de desarrollo de aplicaciones',
            'is_active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.institution_id', $institution->id)
            ->assertJsonPath('data.name', 'Desarrollo de Software')
            ->assertJsonPath('data.code', 'DS')
            ->assertJsonPath('data.description', 'Programa de desarrollo de aplicaciones')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('academic_programs', [
            'institution_id' => $institution->id,
            'name' => 'Desarrollo de Software',
            'code' => 'DS',
            'is_active' => 1,
        ]);

        $this->assertDatabaseCount('academic_programs', 1);
    }
    
public function test_no_permite_programas_duplicados_en_la_misma_institucion(): void
{
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD',
        'type' => 'institute',
        'is_active' => true,
    ]);

    AcademicProgram::create([
        'institution_id' => $institution->id,
        'name' => 'Desarrollo de Software',
        'code' => 'DS',
        'description' => 'Carrera de programación',
        'is_active' => true,
    ]);

    $this->autenticarEnInstitucion($institution);

    $response = $this->postJson('/api/academic-programs', [
        'institution_id' => $institution->id,
        'name' => 'Desarrollo de Software',
        'code' => 'DS2',
        'description' => 'Otro programa con el mismo nombre',
        'is_active' => true,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);

    $this->assertDatabaseCount('academic_programs', 1);

    $this->assertDatabaseHas('academic_programs', [
        'institution_id' => $institution->id,
        'name' => 'Desarrollo de Software',
        'code' => 'DS',
    ]);
}

public function test_permite_mismo_nombre_de_programa_en_instituciones_diferentes(): void
{
    $institution1 = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD',
        'type' => 'institute',
        'is_active' => true,
    ]);

    $institution2 = Institution::create([
        'name' => 'Universidad Demo',
        'code' => 'UD',
        'type' => 'university',
        'is_active' => true,
    ]);

    AcademicProgram::create([
        'institution_id' => $institution1->id,
        'name' => 'Desarrollo de Software',
        'code' => 'DS',
        'description' => 'Programa del primer instituto',
        'is_active' => true,
    ]);

    $this->autenticarEnInstitucion($institution2);

    $response = $this->postJson('/api/academic-programs', [
        'institution_id' => $institution2->id,
        'name' => 'Desarrollo de Software',
        'code' => 'DS',
        'description' => 'Programa de la segunda institución',
        'is_active' => true,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.institution_id', $institution2->id)
        ->assertJsonPath('data.name', 'Desarrollo de Software');

    $this->assertDatabaseCount('academic_programs', 2);

    $this->assertDatabaseHas('academic_programs', [
        'institution_id' => $institution1->id,
        'name' => 'Desarrollo de Software',
    ]);

    $this->assertDatabaseHas('academic_programs', [
        'institution_id' => $institution2->id,
        'name' => 'Desarrollo de Software',
    ]);
}

public function test_no_permite_crear_programa_sin_campos_obligatorios(): void
{
    $institution = $this->crearInstitucionAutorizada();
    $this->autenticarEnInstitucion($institution);

    $response = $this->postJson('/api/academic-programs', [
        'description' => 'Programa sin datos obligatorios',
        'is_active' => true,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'institution_id',
            'name',
            'code',
        ]);

    $this->assertDatabaseCount('academic_programs', 0);
}

public function test_no_permite_crear_programa_con_institucion_inexistente(): void
{
    $institution = $this->crearInstitucionAutorizada();
    $this->autenticarEnInstitucion($institution);

    $response = $this->postJson('/api/academic-programs', [
        'institution_id' => 99999,
        'name' => 'Desarrollo de Software',
        'code' => 'DS',
        'description' => 'Programa de desarrollo de aplicaciones',
        'is_active' => true,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'institution_id',
        ]);

    $this->assertDatabaseCount('academic_programs', 0);
}

public function test_puede_consultar_programa_academico_con_su_institucion(): void
{
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD',
        'type' => 'institute',
        'is_active' => true,
    ]);

    $program = AcademicProgram::create([
        'institution_id' => $institution->id,
        'name' => 'Desarrollo de Software',
        'code' => 'DS',
        'description' => 'Programa de desarrollo de aplicaciones',
        'is_active' => true,
    ]);

    $this->autenticarEnInstitucion($institution);

    $response = $this->getJson(
        "/api/academic-programs/{$program->id}"
    );

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $program->id)
        ->assertJsonPath('data.name', 'Desarrollo de Software')
        ->assertJsonPath('data.code', 'DS')
        ->assertJsonPath('data.institution_id', $institution->id)
        ->assertJsonPath('data.institution.id', $institution->id)
        ->assertJsonPath(
            'data.institution.name',
            'Instituto Tecnológico Demo'
        );

    $this->assertDatabaseCount('academic_programs', 1);
}

public function test_puede_listar_programas_academicos_con_sus_instituciones(): void
{
    $institution1 = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD',
        'type' => 'institute',
        'is_active' => true,
    ]);

    $institution2 = Institution::create([
        'name' => 'Universidad Demo',
        'code' => 'UD',
        'type' => 'university',
        'is_active' => true,
    ]);

    AcademicProgram::create([
        'institution_id' => $institution1->id,
        'name' => 'Desarrollo de Software',
        'code' => 'DS',
        'description' => 'Carrera de programación',
        'is_active' => true,
    ]);

    AcademicProgram::create([
        'institution_id' => $institution2->id,
        'name' => 'Administración de Empresas',
        'code' => 'AE',
        'description' => 'Carrera de administración',
        'is_active' => true,
    ]);

    // El listado debe limitarse a la institución del encabezado, no a todas.
    $this->autenticarEnInstitucion($institution1);
    $response = $this->getJson('/api/academic-programs');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.institution_id', $institution1->id)
        ->assertJsonPath('data.0.name', 'Desarrollo de Software')
        ->assertJsonMissing(['name' => 'Administración de Empresas']);

    $this->assertEquals($institution1->id, $response->json('data.0.institution.id'));

    // Un miembro de otra institución solo ve los programas de la suya.
    $this->autenticarEnInstitucion($institution2);
    $response = $this->getJson('/api/academic-programs');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.institution_id', $institution2->id)
        ->assertJsonPath('data.0.name', 'Administración de Empresas')
        ->assertJsonMissing(['name' => 'Desarrollo de Software']);

    $this->assertEquals($institution2->id, $response->json('data.0.institution.id'));
    $this->assertDatabaseCount('academic_programs', 2);
}

public function test_puede_actualizar_un_programa_academico(): void
{
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD',
        'type' => 'institute',
        'is_active' => true,
    ]);

    $program = AcademicProgram::create([
        'institution_id' => $institution->id,
        'name' => 'Desarrollo de Software',
        'code' => 'DS',
        'description' => 'Programa de desarrollo de aplicaciones',
        'is_active' => true,
    ]);

    $this->autenticarEnInstitucion($institution);

    $response = $this->putJson(
        "/api/academic-programs/{$program->id}",
        [
            'institution_id' => $institution->id,
            'name' => 'Ingeniería de Software',
            'code' => 'IS',
            'description' => 'Programa actualizado de ingeniería',
            'is_active' => false,
        ]
    );

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $program->id)
        ->assertJsonPath('data.name', 'Ingeniería de Software')
        ->assertJsonPath('data.code', 'IS')
        ->assertJsonPath(
            'data.description',
            'Programa actualizado de ingeniería'
        )
        ->assertJsonPath('data.is_active', false);

    $this->assertDatabaseHas('academic_programs', [
        'id' => $program->id,
        'institution_id' => $institution->id,
        'name' => 'Ingeniería de Software',
        'code' => 'IS',
        'description' => 'Programa actualizado de ingeniería',
        'is_active' => 0,
    ]);

    $this->assertDatabaseCount('academic_programs', 1);
}

public function test_no_permite_nombre_duplicado_al_actualizar_programa(): void
{
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD',
        'type' => 'institute',
        'is_active' => true,
    ]);

    $program1 = AcademicProgram::create([
        'institution_id' => $institution->id,
        'name' => 'Desarrollo de Software',
        'code' => 'DS',
        'description' => 'Carrera de programación',
        'is_active' => true,
    ]);

    $program2 = AcademicProgram::create([
        'institution_id' => $institution->id,
        'name' => 'Administración de Empresas',
        'code' => 'AE',
        'description' => 'Carrera de administración',
        'is_active' => true,
    ]);

    $this->autenticarEnInstitucion($institution);

    $response = $this->putJson(
        "/api/academic-programs/{$program2->id}",
        [
            'institution_id' => $institution->id,
            'name' => 'Desarrollo de Software',
            'code' => 'AE',
            'description' => 'Carrera de administración',
            'is_active' => true,
        ]
    );

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);

    $this->assertDatabaseHas('academic_programs', [
        'id' => $program1->id,
        'name' => 'Desarrollo de Software',
    ]);

    $this->assertDatabaseHas('academic_programs', [
        'id' => $program2->id,
        'name' => 'Administración de Empresas',
    ]);

    $this->assertDatabaseCount('academic_programs', 2);
}

public function test_permite_actualizar_programa_conservando_su_mismo_nombre(): void
{
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD',
        'type' => 'institute',
        'is_active' => true,
    ]);

    $program = AcademicProgram::create([
        'institution_id' => $institution->id,
        'name' => 'Desarrollo de Software',
        'code' => 'DS',
        'description' => 'Programa de desarrollo de aplicaciones',
        'is_active' => true,
    ]);

    $this->autenticarEnInstitucion($institution);

    $response = $this->putJson(
        "/api/academic-programs/{$program->id}",
        [
            'institution_id' => $institution->id,
            'name' => 'Desarrollo de Software',
            'code' => 'DS2026',
            'description' => 'Programa actualizado de desarrollo web y móvil',
            'is_active' => true,
        ]
    );

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $program->id)
        ->assertJsonPath('data.name', 'Desarrollo de Software')
        ->assertJsonPath('data.code', 'DS2026')
        ->assertJsonPath(
            'data.description',
            'Programa actualizado de desarrollo web y móvil'
        );

    $this->assertDatabaseHas('academic_programs', [
        'id' => $program->id,
        'institution_id' => $institution->id,
        'name' => 'Desarrollo de Software',
        'code' => 'DS2026',
        'description' => 'Programa actualizado de desarrollo web y móvil',
    ]);

    $this->assertDatabaseCount('academic_programs', 1);
}

public function test_devuelve_404_al_consultar_programa_academico_inexistente(): void
{
    $institution = $this->crearInstitucionAutorizada();
    $this->autenticarEnInstitucion($institution);

    $response = $this->getJson('/api/academic-programs/99999');

    $response->assertStatus(404);

    $this->assertDatabaseCount('academic_programs', 0);
}

public function test_no_permite_actualizar_programa_sin_campos_obligatorios(): void
{
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD',
        'type' => 'institute',
        'is_active' => true,
    ]);

    $program = AcademicProgram::create([
        'institution_id' => $institution->id,
        'name' => 'Desarrollo de Software',
        'code' => 'DS',
        'description' => 'Programa de desarrollo de aplicaciones',
        'is_active' => true,
    ]);

    $this->autenticarEnInstitucion($institution);

    $response = $this->putJson(
        "/api/academic-programs/{$program->id}",
        [
            'description' => 'Descripción modificada',
            'is_active' => false,
        ]
    );

    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'institution_id',
            'name',
            'code',
        ]);

    $this->assertDatabaseHas('academic_programs', [
        'id' => $program->id,
        'institution_id' => $institution->id,
        'name' => 'Desarrollo de Software',
        'code' => 'DS',
        'description' => 'Programa de desarrollo de aplicaciones',
        'is_active' => 1,
    ]);

    $this->assertDatabaseCount('academic_programs', 1);
}

public function test_no_permite_actualizar_programa_con_institucion_inexistente(): void
{
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD',
        'type' => 'institute',
        'is_active' => true,
    ]);

    $program = AcademicProgram::create([
        'institution_id' => $institution->id,
        'name' => 'Desarrollo de Software',
        'code' => 'DS',
        'description' => 'Programa de desarrollo de aplicaciones',
        'is_active' => true,
    ]);

    $this->autenticarEnInstitucion($institution);

    $response = $this->putJson(
        "/api/academic-programs/{$program->id}",
        [
            'institution_id' => 99999,
            'name' => 'Ingeniería de Software',
            'code' => 'IS',
            'description' => 'Descripción actualizada',
            'is_active' => true,
        ]
    );

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['institution_id']);

    $this->assertDatabaseHas('academic_programs', [
        'id' => $program->id,
        'institution_id' => $institution->id,
        'name' => 'Desarrollo de Software',
        'code' => 'DS',
        'description' => 'Programa de desarrollo de aplicaciones',
    ]);

    $this->assertDatabaseCount('academic_programs', 1);
}

public function test_devuelve_404_al_actualizar_programa_academico_inexistente(): void
{
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD',
        'type' => 'institute',
        'is_active' => true,
    ]);

    $this->autenticarEnInstitucion($institution);

    $response = $this->putJson(
        '/api/academic-programs/99999',
        [
            'institution_id' => $institution->id,
            'name' => 'Ingeniería de Software',
            'code' => 'IS',
            'description' => 'Programa de ingeniería',
            'is_active' => true,
        ]
    );

    $response->assertStatus(404);

    $this->assertDatabaseCount('academic_programs', 0);
}

public function test_puede_eliminar_un_programa_academico(): void
{
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD',
        'type' => 'institute',
        'is_active' => true,
    ]);

    $program = AcademicProgram::create([
        'institution_id' => $institution->id,
        'name' => 'Desarrollo de Software',
        'code' => 'DS',
        'description' => 'Programa de desarrollo de aplicaciones',
        'is_active' => true,
    ]);

    $this->assertDatabaseHas('academic_programs', [
        'id' => $program->id,
        'name' => 'Desarrollo de Software',
    ]);

    $this->autenticarEnInstitucion($institution);

    $response = $this->deleteJson(
        "/api/academic-programs/{$program->id}"
    );

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath(
            'message',
            'Programa académico eliminado correctamente.'
        );

    $this->assertDatabaseMissing('academic_programs', [
        'id' => $program->id,
    ]);

    $this->assertDatabaseCount('academic_programs', 0);

    $this->assertDatabaseHas('institutions', [
        'id' => $institution->id,
    ]);
}

public function test_devuelve_404_al_eliminar_programa_academico_inexistente(): void
{
    $institution = $this->crearInstitucionAutorizada();
    $this->autenticarEnInstitucion($institution);

    $response = $this->deleteJson(
        '/api/academic-programs/99999'
    );

    $response->assertStatus(404);

    $this->assertDatabaseCount('academic_programs', 0);
}

public function test_no_permite_eliminar_programa_con_planes_academicos(): void
{
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD',
        'type' => 'institute',
        'is_active' => true,
    ]);

    $program = AcademicProgram::create([
        'institution_id' => $institution->id,
        'name' => 'Desarrollo de Software',
        'code' => 'DS',
        'description' => 'Programa de desarrollo de aplicaciones',
        'is_active' => true,
    ]);

    $planId = \Illuminate\Support\Facades\DB::table('academic_plans')
        ->insertGetId([
            'academic_program_id' => $program->id,
            'name' => 'Plan Curricular 2026',
            'version' => '1.0',
            'start_date' => '2026-01-01',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    $this->autenticarEnInstitucion($institution);

    $response = $this->deleteJson(
        "/api/academic-programs/{$program->id}"
    );

    $response->assertStatus(409)
        ->assertJsonPath('success', false);

    $this->assertDatabaseHas('academic_programs', [
        'id' => $program->id,
        'name' => 'Desarrollo de Software',
    ]);

    $this->assertDatabaseHas('academic_plans', [
        'id' => $planId,
        'academic_program_id' => $program->id,
        'name' => 'Plan Curricular 2026',
    ]);

    $this->assertDatabaseCount('academic_programs', 1);
    $this->assertDatabaseCount('academic_plans', 1);
}

public function test_no_permite_eliminar_programa_con_historial_academico(): void
{
    $db = \Illuminate\Support\Facades\DB::class;

    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD',
        'type' => 'institute',
        'is_active' => true,
    ]);

    $program = AcademicProgram::create([
        'institution_id' => $institution->id,
        'name' => 'Desarrollo de Software',
        'code' => 'DS',
        'description' => 'Carrera de programación',
        'is_active' => true,
    ]);

    $planId = $db::table('academic_plans')->insertGetId([
        'academic_program_id' => $program->id,
        'name' => 'Plan Curricular 2026',
        'version' => '1.0',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $personId = $db::table('people')->insertGetId([
        'institution_id' => $institution->id,
        'first_name' => 'Juan',
        'last_name' => 'Pérez',
        'document_number' => '12345678',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $studentId = $db::table('students')->insertGetId([
        'person_id' => $personId,
        'student_code' => 'EST001',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $recordId = $db::table('academic_records')->insertGetId([
        'student_id' => $studentId,
        'academic_program_id' => $program->id,
        'academic_plan_id' => $planId,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->autenticarEnInstitucion($institution);

    $response = $this->deleteJson(
        "/api/academic-programs/{$program->id}"
    );

    $response->assertStatus(409)
        ->assertJsonPath('success', false);

    $this->assertDatabaseHas('academic_programs', [
        'id' => $program->id,
    ]);

    $this->assertDatabaseHas('academic_plans', [
        'id' => $planId,
    ]);

    $this->assertDatabaseHas('academic_records', [
        'id' => $recordId,
        'student_id' => $studentId,
        'academic_program_id' => $program->id,
    ]);

    $this->assertDatabaseHas('students', [
        'id' => $studentId,
    ]);
}

}