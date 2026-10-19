<?php

namespace Tests\Feature;

use App\Models\AcademicUnit;
use App\Models\Institution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;




class AcademicUnitTest extends TestCase
{
    use RefreshDatabase;

    public function test_puede_crear_unidad_academica_con_todos_sus_campos(): void
    {
        $institution = Institution::create([
            'name' => 'Instituto Tecnológico Demo',
            'code' => 'ITD',
            'type' => 'institute',
        ]);

        $response = $this->postJson('/api/academic-units', [
            'institution_id' => $institution->id,
            'name' => 'Programación I',
            'code' => 'PROG1',
            'description' => 'Fundamentos de programación',
            'unit_type' => 'course',
            'credits' => 4,
            'hours' => 64,
            'is_active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Programación I')
            ->assertJsonPath('data.code', 'PROG1')
            ->assertJsonPath('data.unit_type', 'course')
            ->assertJsonPath('data.credits', 4)
            ->assertJsonPath('data.hours', 64);

        $this->assertDatabaseHas('academic_units', [
            'institution_id' => $institution->id,
            'name' => 'Programación I',
            'code' => 'PROG1',
            'unit_type' => 'course',
            'credits' => 4,
            'hours' => 64,
        ]);
    }
    
public function test_no_permite_unidades_academicas_duplicadas_en_la_misma_institucion(): void
{
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD',
        'type' => 'institute',
    ]);

    AcademicUnit::create([
        'institution_id' => $institution->id,
        'name' => 'Programación I',
        'code' => 'PROG1',
        'unit_type' => 'course',
        'credits' => 4,
        'hours' => 64,
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/academic-units', [
        'institution_id' => $institution->id,
        'name' => 'Programación I',
        'code' => 'PROG2',
        'unit_type' => 'course',
        'credits' => 3,
        'hours' => 48,
        'is_active' => true,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);

    $this->assertDatabaseCount('academic_units', 1);
}

public function test_permite_mismo_nombre_de_unidad_en_instituciones_diferentes(): void
{
    $institution1 = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD',
        'type' => 'institute',
    ]);

    $institution2 = Institution::create([
        'name' => 'Universidad Demo',
        'code' => 'UD',
        'type' => 'university',
    ]);

    AcademicUnit::create([
        'institution_id' => $institution1->id,
        'name' => 'Programación I',
        'code' => 'PROG1',
        'unit_type' => 'course',
        'credits' => 4,
        'hours' => 64,
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/academic-units', [
        'institution_id' => $institution2->id,
        'name' => 'Programación I',
        'code' => 'PROG1',
        'unit_type' => 'course',
        'credits' => 3,
        'hours' => 48,
        'is_active' => true,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.institution_id', $institution2->id)
        ->assertJsonPath('data.name', 'Programación I');

    $this->assertDatabaseCount('academic_units', 2);

    $this->assertDatabaseHas('academic_units', [
        'institution_id' => $institution1->id,
        'name' => 'Programación I',
    ]);

    $this->assertDatabaseHas('academic_units', [
        'institution_id' => $institution2->id,
        'name' => 'Programación I',
    ]);
}

public function test_no_permite_crear_unidad_sin_campos_obligatorios(): void
{
    $response = $this->postJson('/api/academic-units', [
        'code' => 'PROG1',
        'description' => 'Fundamentos de programación',
        'unit_type' => 'course',
        'credits' => 4,
        'hours' => 64,
        'is_active' => true,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'institution_id',
            'name',
        ]);

    $this->assertDatabaseCount('academic_units', 0);
}

public function test_no_permite_crear_unidad_con_institucion_inexistente(): void
{
    $response = $this->postJson('/api/academic-units', [
        'institution_id' => 99999,
        'name' => 'Programación I',
        'code' => 'PROG1',
        'description' => 'Fundamentos de programación',
        'unit_type' => 'course',
        'credits' => 4,
        'hours' => 64,
        'is_active' => true,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'institution_id',
        ]);

    $this->assertDatabaseCount('academic_units', 0);
}

public function test_puede_consultar_unidad_academica_con_su_institucion(): void
{
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD',
        'type' => 'institute',
    ]);

    $unit = AcademicUnit::create([
        'institution_id' => $institution->id,
        'name' => 'Programación I',
        'code' => 'PROG1',
        'description' => 'Fundamentos de programación',
        'unit_type' => 'course',
        'credits' => 4,
        'hours' => 64,
        'is_active' => true,
    ]);

    $response = $this->getJson(
        "/api/academic-units/{$unit->id}"
    );

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $unit->id)
        ->assertJsonPath('data.name', 'Programación I')
        ->assertJsonPath('data.code', 'PROG1')
        ->assertJsonPath('data.credits', 4)
        ->assertJsonPath('data.hours', 64)
        ->assertJsonPath('data.institution.id', $institution->id)
        ->assertJsonPath(
            'data.institution.name',
            'Instituto Tecnológico Demo'
        );
}

public function test_puede_listar_unidades_academicas_con_sus_instituciones(): void
{
    $institution1 = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD',
        'type' => 'institute',
    ]);

    $institution2 = Institution::create([
        'name' => 'Universidad Demo',
        'code' => 'UD',
        'type' => 'university',
    ]);

    AcademicUnit::create([
        'institution_id' => $institution1->id,
        'name' => 'Programación I',
        'code' => 'PROG1',
        'unit_type' => 'course',
        'credits' => 4,
        'hours' => 64,
        'is_active' => true,
    ]);

    AcademicUnit::create([
        'institution_id' => $institution2->id,
        'name' => 'Base de Datos I',
        'code' => 'BD1',
        'unit_type' => 'course',
        'credits' => 3,
        'hours' => 48,
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/academic-units');

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment([
            'name' => 'Programación I',
            'code' => 'PROG1',
        ])
        ->assertJsonFragment([
            'name' => 'Base de Datos I',
            'code' => 'BD1',
        ]);

    $units = $response->json('data');

    $this->assertEqualsCanonicalizing(
        [$institution1->id, $institution2->id],
        array_column($units, 'institution_id')
    );

    foreach ($units as $unit) {
        $this->assertArrayHasKey('institution', $unit);
        $this->assertEquals(
            $unit['institution_id'],
            $unit['institution']['id']
        );
    }

    $this->assertDatabaseCount('academic_units', 2);
}

public function test_puede_actualizar_una_unidad_academica(): void
{
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD',
        'type' => 'institute',
    ]);

    $unit = AcademicUnit::create([
        'institution_id' => $institution->id,
        'name' => 'Programación I',
        'code' => 'PROG1',
        'description' => 'Fundamentos de programación',
        'unit_type' => 'course',
        'credits' => 4,
        'hours' => 64,
        'is_active' => true,
    ]);

    $response = $this->putJson(
        "/api/academic-units/{$unit->id}",
        [
            'institution_id' => $institution->id,
            'name' => 'Programación Avanzada',
            'code' => 'PROG2',
            'description' => 'Programación orientada a objetos',
            'unit_type' => 'course',
            'credits' => 5,
            'hours' => 80,
            'is_active' => false,
        ]
    );

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Programación Avanzada')
        ->assertJsonPath('data.code', 'PROG2')
        ->assertJsonPath('data.credits', 5)
        ->assertJsonPath('data.hours', 80)
        ->assertJsonPath('data.is_active', false);

    $this->assertDatabaseHas('academic_units', [
        'id' => $unit->id,
        'institution_id' => $institution->id,
        'name' => 'Programación Avanzada',
        'code' => 'PROG2',
        'unit_type' => 'course',
        'credits' => 5,
        'hours' => 80,
        'is_active' => 0,
    ]);
}

public function test_no_permite_nombre_duplicado_al_actualizar_unidad(): void
{
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD',
        'type' => 'institute',
    ]);

    $unit1 = AcademicUnit::create([
        'institution_id' => $institution->id,
        'name' => 'Programación I',
        'code' => 'PROG1',
        'unit_type' => 'course',
        'credits' => 4,
        'hours' => 64,
        'is_active' => true,
    ]);

    $unit2 = AcademicUnit::create([
        'institution_id' => $institution->id,
        'name' => 'Base de Datos I',
        'code' => 'BD1',
        'unit_type' => 'course',
        'credits' => 3,
        'hours' => 48,
        'is_active' => true,
    ]);

    $response = $this->putJson(
        "/api/academic-units/{$unit2->id}",
        [
            'institution_id' => $institution->id,
            'name' => 'Programación I',
            'code' => 'BD1',
            'unit_type' => 'course',
            'credits' => 3,
            'hours' => 48,
            'is_active' => true,
        ]
    );

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);

    $this->assertDatabaseHas('academic_units', [
        'id' => $unit2->id,
        'name' => 'Base de Datos I',
    ]);

    $this->assertDatabaseHas('academic_units', [
        'id' => $unit1->id,
        'name' => 'Programación I',
    ]);

    $this->assertDatabaseCount('academic_units', 2);
}

public function test_permite_actualizar_unidad_conservando_su_mismo_nombre(): void
{
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD',
        'type' => 'institute',
    ]);

    $unit = AcademicUnit::create([
        'institution_id' => $institution->id,
        'name' => 'Programación I',
        'code' => 'PROG1',
        'unit_type' => 'course',
        'credits' => 4,
        'hours' => 64,
        'is_active' => true,
    ]);

    $response = $this->putJson(
        "/api/academic-units/{$unit->id}",
        [
            'institution_id' => $institution->id,
            'name' => 'Programación I',
            'code' => 'PROG1',
            'unit_type' => 'course',
            'credits' => 5,
            'hours' => 80,
            'is_active' => true,
        ]
    );

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $unit->id)
        ->assertJsonPath('data.name', 'Programación I')
        ->assertJsonPath('data.credits', 5)
        ->assertJsonPath('data.hours', 80);

    $this->assertDatabaseHas('academic_units', [
        'id' => $unit->id,
        'institution_id' => $institution->id,
        'name' => 'Programación I',
        'credits' => 5,
        'hours' => 80,
    ]);

    $this->assertDatabaseCount('academic_units', 1);
}

public function test_puede_eliminar_unidad_academica_sin_eliminar_su_institucion(): void
{
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD',
        'type' => 'institute',
    ]);

    $unit = AcademicUnit::create([
        'institution_id' => $institution->id,
        'name' => 'Programación I',
        'code' => 'PROG1',
        'unit_type' => 'course',
        'credits' => 4,
        'hours' => 64,
        'is_active' => true,
    ]);

    $response = $this->deleteJson(
        "/api/academic-units/{$unit->id}"
    );

    $response->assertStatus(200)
        ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('academic_units', [
        'id' => $unit->id,
    ]);

    $this->assertDatabaseHas('institutions', [
        'id' => $institution->id,
        'name' => 'Instituto Tecnológico Demo',
    ]);

    $this->assertDatabaseCount('academic_units', 0);
    $this->assertDatabaseCount('institutions', 1);
}

public function test_devuelve_404_al_consultar_unidad_academica_inexistente(): void
{
    $response = $this->getJson('/api/academic-units/99999');

    $response->assertStatus(404);

    $this->assertDatabaseCount('academic_units', 0);
}

public function test_no_permite_crear_unidad_con_creditos_y_horas_negativos(): void
{
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD',
        'type' => 'institute',
    ]);

    $response = $this->postJson('/api/academic-units', [
        'institution_id' => $institution->id,
        'name' => 'Programación I',
        'code' => 'PROG1',
        'unit_type' => 'course',
        'credits' => -4,
        'hours' => -64,
        'is_active' => true,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'credits',
            'hours',
        ]);

    $this->assertDatabaseCount('academic_units', 0);
}

public function test_no_permite_actualizar_unidad_con_creditos_y_horas_negativos(): void
{
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD',
        'type' => 'institute',
    ]);

    $unit = AcademicUnit::create([
        'institution_id' => $institution->id,
        'name' => 'Programación I',
        'code' => 'PROG1',
        'unit_type' => 'course',
        'credits' => 4,
        'hours' => 64,
        'is_active' => true,
    ]);

    $response = $this->putJson(
        "/api/academic-units/{$unit->id}",
        [
            'institution_id' => $institution->id,
            'name' => 'Programación I',
            'code' => 'PROG1',
            'unit_type' => 'course',
            'credits' => -5,
            'hours' => -80,
            'is_active' => true,
        ]
    );

    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'credits',
            'hours',
        ]);

    $this->assertDatabaseHas('academic_units', [
        'id' => $unit->id,
        'name' => 'Programación I',
        'credits' => 4,
        'hours' => 64,
    ]);

    $this->assertDatabaseCount('academic_units', 1);
}

}
