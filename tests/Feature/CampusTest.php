<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;



class CampusTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Autentica un usuario miembro de la institución y selecciona su contexto.
     * Las pruebas CRUD verifican reglas de negocio, no accesos anónimos.
     */
    private function authorizeFor(Institution $institution): void
    {
        $user = User::factory()->create();
        $user->institutions()->attach($institution->id, ['is_active' => true]);

        $this->actingAs($user);
        $this->withHeader('X-Institution-Id', (string) $institution->id);
    }


    public function test_can_create_a_campus(): void
    {
        // Crear una institución para asociar la sede
        $institution = Institution::create([
            'name' => 'Instituto Tecnológico Demo',
            'code' => 'ITD001',
            'type' => 'institute',
            'is_active' => true,
        ]);

        $this->authorizeFor($institution);

        // Registrar una sede mediante la API
        $response = $this->postJson('/api/campuses', [
            'institution_id' => $institution->id,
            'name' => 'Sede Central',
            'code' => 'SC001',
            'address' => 'Av. Principal 123',
            'is_active' => true,
        ]);

        // Verificar la respuesta de la API
        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Sede Central')
            ->assertJsonPath('data.code', 'SC001')
            ->assertJsonPath('data.institution_id', $institution->id);

        // Verificar que la sede se guardó correctamente
        $this->assertDatabaseHas('campuses', [
            'institution_id' => $institution->id,
            'name' => 'Sede Central',
            'code' => 'SC001',
        ]);
    }
    
public function test_rejects_duplicate_campus_name_in_same_institution(): void
{
    // Crear una institución
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD001',
        'type' => 'institute',
        'is_active' => true,
    ]);

        $this->authorizeFor($institution);

    // Registrar la primera sede
    $this->postJson('/api/campuses', [
        'institution_id' => $institution->id,
        'name' => 'Sede Central',
        'code' => 'SC001',
        'is_active' => true,
    ])->assertCreated();

    // Intentar registrar otra sede con el mismo nombre
    $response = $this->postJson('/api/campuses', [
        'institution_id' => $institution->id,
        'name' => 'Sede Central',
        'code' => 'SC002',
        'is_active' => true,
    ]);

    // La API debe rechazar el nombre duplicado
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);

    // Solo debe existir una sede
    $this->assertDatabaseCount('campuses', 1);
}

public function test_allows_same_campus_name_in_different_institutions(): void
{
    // Crear dos instituciones diferentes
    $institution1 = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD001',
        'type' => 'institute',
        'is_active' => true,
    ]);

    $institution2 = Institution::create([
        'name' => 'Universidad de Prueba',
        'code' => 'UNI001',
        'type' => 'university',
        'is_active' => true,
    ]);

    // Cada solicitud se hace como miembro de la institución correspondiente.
    $this->authorizeFor($institution1);

    // Primera institución registra su sede
    $this->postJson('/api/campuses', [
        'institution_id' => $institution1->id,
        'name' => 'Sede Central',
        'code' => 'SC001',
        'is_active' => true,
    ])->assertCreated();

    // Segunda institución registra una sede con el mismo nombre
    $this->authorizeFor($institution2);
    $response = $this->postJson('/api/campuses', [
        'institution_id' => $institution2->id,
        'name' => 'Sede Central',
        'code' => 'SC002',
        'is_active' => true,
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.institution_id', $institution2->id);

    // Ambas sedes deben existir
    $this->assertDatabaseCount('campuses', 2);
}

public function test_rejects_missing_required_campus_fields(): void
{
    // Usuario autorizado en una institución real para llegar al controlador.
    $institution = Institution::create([
        'name' => 'Instituto de prueba',
        'code' => 'ITD-CTX',
        'type' => 'institute',
        'is_active' => true,
    ]);
    $this->authorizeFor($institution);

    // Intentar crear una sede sin enviar datos
    $response = $this->postJson('/api/campuses', []);

    // La API debe rechazar los campos obligatorios
    $response->assertUnprocessable()
        ->assertJsonValidationErrors([
            'institution_id',
            'name',
        ]);

    // No debe registrarse ninguna sede
    $this->assertDatabaseCount('campuses', 0);
}

public function test_rejects_nonexistent_institution(): void
{
    // Usuario autorizado en una institución real para llegar al controlador.
    $institution = Institution::create([
        'name' => 'Instituto de prueba',
        'code' => 'ITD-CTX',
        'type' => 'institute',
        'is_active' => true,
    ]);
    $this->authorizeFor($institution);

    // Intentar registrar una sede con una institución inexistente
    $response = $this->postJson('/api/campuses', [
        'institution_id' => 999999,
        'name' => 'Sede Central',
        'code' => 'SC001',
        'is_active' => true,
    ]);

    // La API debe rechazar la institución inexistente
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['institution_id']);

    // No debe crearse ninguna sede
    $this->assertDatabaseCount('campuses', 0);
}

public function test_can_show_a_campus_with_its_institution(): void
{
    // Crear una institución
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD001',
        'type' => 'institute',
        'is_active' => true,
    ]);

        $this->authorizeFor($institution);

    // Crear una sede
    $campus = \App\Models\Campus::create([
        'institution_id' => $institution->id,
        'name' => 'Sede Central',
        'code' => 'SC001',
        'address' => 'Av. Principal 123',
        'is_active' => true,
    ]);

    // Consultar la sede mediante la API
    $response = $this->getJson('/api/campuses/' . $campus->id);

    // Verificar los datos de la sede y su institución
    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $campus->id)
        ->assertJsonPath('data.name', 'Sede Central')
        ->assertJsonPath('data.institution.id', $institution->id)
        ->assertJsonPath(
            'data.institution.name',
            'Instituto Tecnológico Demo'
        );
}

public function test_can_list_campuses_with_their_institutions(): void
{
    // Crear una institución
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD001',
        'type' => 'institute',
        'is_active' => true,
    ]);

        $this->authorizeFor($institution);

    // Registrar dos sedes
    \App\Models\Campus::create([
        'institution_id' => $institution->id,
        'name' => 'Sede Central',
        'code' => 'SC001',
        'is_active' => true,
    ]);

    \App\Models\Campus::create([
        'institution_id' => $institution->id,
        'name' => 'Sede Norte',
        'code' => 'SN001',
        'is_active' => true,
    ]);

    // Consultar todas las sedes
    $response = $this->getJson('/api/campuses');

    // Verificar el listado y sus relaciones
    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['name' => 'Sede Central'])
        ->assertJsonFragment(['name' => 'Sede Norte'])
        ->assertJsonPath(
            'data.0.institution.name',
            'Instituto Tecnológico Demo'
        )
        ->assertJsonPath(
            'data.1.institution.name',
            'Instituto Tecnológico Demo'
        );
}

public function test_can_update_a_campus(): void
{
    // Crear una institución
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD001',
        'type' => 'institute',
        'is_active' => true,
    ]);

        $this->authorizeFor($institution);

    // Crear una sede
    $campus = \App\Models\Campus::create([
        'institution_id' => $institution->id,
        'name' => 'Sede Central',
        'code' => 'SC001',
        'address' => 'Av. Principal 123',
        'is_active' => true,
    ]);

    // Actualizar la sede mediante la API
    $response = $this->putJson('/api/campuses/' . $campus->id, [
        'institution_id' => $institution->id,
        'name' => 'Sede Central Actualizada',
        'code' => 'SC002',
        'address' => 'Av. Nueva 456',
        'is_active' => false,
    ]);

    // Verificar la respuesta
    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Sede Central Actualizada')
        ->assertJsonPath('data.code', 'SC002')
        ->assertJsonPath('data.is_active', false);

    // Verificar los cambios en la base de datos
    $this->assertDatabaseHas('campuses', [
        'id' => $campus->id,
        'institution_id' => $institution->id,
        'name' => 'Sede Central Actualizada',
        'code' => 'SC002',
        'is_active' => false,
    ]);
}

public function test_rejects_duplicate_campus_name_on_update(): void
{
    // Crear una institución
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD001',
        'type' => 'institute',
        'is_active' => true,
    ]);

        $this->authorizeFor($institution);

    // Crear la primera sede
    $campus1 = \App\Models\Campus::create([
        'institution_id' => $institution->id,
        'name' => 'Sede Central',
        'code' => 'SC001',
        'is_active' => true,
    ]);

    // Crear la segunda sede
    $campus2 = \App\Models\Campus::create([
        'institution_id' => $institution->id,
        'name' => 'Sede Norte',
        'code' => 'SN001',
        'is_active' => true,
    ]);

    // Intentar cambiar el nombre de la segunda sede
    // por el nombre de la primera
    $response = $this->putJson(
        '/api/campuses/' . $campus2->id,
        [
            'institution_id' => $institution->id,
            'name' => 'Sede Central',
            'code' => 'SN001',
            'is_active' => true,
        ]
    );

    // La API debe rechazar el nombre duplicado
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);

    // La segunda sede debe conservar su nombre original
    $this->assertDatabaseHas('campuses', [
        'id' => $campus2->id,
        'name' => 'Sede Norte',
    ]);

    $this->assertDatabaseCount('campuses', 2);
}

public function test_can_delete_a_campus(): void
{
    // Crear una institución
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD001',
        'type' => 'institute',
        'is_active' => true,
    ]);

        $this->authorizeFor($institution);

    // Crear una sede
    $campus = \App\Models\Campus::create([
        'institution_id' => $institution->id,
        'name' => 'Sede Central',
        'code' => 'SC001',
        'is_active' => true,
    ]);

    // Eliminar la sede mediante la API
    $response = $this->deleteJson(
        '/api/campuses/' . $campus->id
    );

    // Verificar la respuesta
    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath(
            'message',
            'Campus eliminado correctamente'
        );

    // Verificar que la sede ya no existe
    $this->assertDatabaseMissing('campuses', [
        'id' => $campus->id,
    ]);

    // La institución debe seguir existiendo
    $this->assertDatabaseHas('institutions', [
        'id' => $institution->id,
    ]);
}

public function test_returns_404_when_campus_does_not_exist(): void
{
    // Usuario autorizado en una institución real para llegar al controlador.
    $institution = Institution::create([
        'name' => 'Instituto de prueba',
        'code' => 'ITD-CTX',
        'type' => 'institute',
        'is_active' => true,
    ]);
    $this->authorizeFor($institution);

    // Intentar consultar una sede inexistente
    $response = $this->getJson('/api/campuses/999999');

    // La API debe responder HTTP 404
    $response->assertNotFound();
}

}
