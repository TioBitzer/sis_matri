<?php

namespace Tests\Feature;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;




class InstitutionTest extends TestCase
{
    use RefreshDatabase;

    private function asPlatformAdmin(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['is_platform_admin' => true])->save();
        $this->actingAs($user);
    }

    private function asInstitutionMember(Institution $institution): void
    {
        $user = User::factory()->create();
        $user->institutions()->attach($institution->id, ['is_active' => true]);
        $this->actingAs($user);
        $this->withHeaders(['X-Institution-Id' => (string) $institution->id]);
    }

    public function test_can_create_an_institution(): void
    {
        $this->asPlatformAdmin();
        $response = $this->postJson('/api/institutions', [
            'name' => 'Instituto Tecnológico Demo',
            'code' => 'ITD001',
            'type' => 'institute',
            'description' => 'Instituto de educación superior',
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Instituto Tecnológico Demo')
            ->assertJsonPath('data.code', 'ITD001')
            ->assertJsonPath('data.type', 'institute');

        $this->assertDatabaseHas('institutions', [
            'name' => 'Instituto Tecnológico Demo',
            'code' => 'ITD001',
            'type' => 'institute',
        ]);
    }
    
public function test_rejects_duplicate_institution_code(): void
{
        $this->asPlatformAdmin();
    // Registrar la primera institución
    Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD001',
        'type' => 'institute',
        'is_active' => true,
    ]);

    // Intentar registrar otra institución con el mismo código
    $response = $this->postJson('/api/institutions', [
        'name' => 'Universidad de Prueba',
        'code' => 'ITD001',
        'type' => 'university',
        'is_active' => true,
    ]);

    // La API debe rechazar el código duplicado
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);

    // Solo debe existir la primera institución
    $this->assertDatabaseCount('institutions', 1);
}

public function test_rejects_missing_required_institution_fields(): void
{
        $this->asPlatformAdmin();
    // Intentar crear una institución sin enviar datos
    $response = $this->postJson('/api/institutions', []);

    // Laravel debe rechazar la solicitud
    $response->assertUnprocessable()
        ->assertJsonValidationErrors([
            'name',
            'code',
            'type',
        ]);

    // No debe crearse ninguna institución
    $this->assertDatabaseCount('institutions', 0);
}

public function test_can_show_an_institution(): void
{
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD001',
        'type' => 'institute',
        'description' => 'Instituto de educación superior',
        'is_active' => true,
    ]);

    $this->asInstitutionMember($institution);

    $response = $this->getJson(
        '/api/institutions/' . $institution->id
    );

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $institution->id)
        ->assertJsonPath('data.name', 'Instituto Tecnológico Demo')
        ->assertJsonPath('data.code', 'ITD001')
        ->assertJsonPath('data.type', 'institute');
}

public function test_can_list_institutions(): void
{
    $selected = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD001',
        'type' => 'institute',
        'is_active' => true,
    ]);

    Institution::create([
        'name' => 'Universidad de Prueba',
        'code' => 'UNI001',
        'type' => 'university',
        'is_active' => true,
    ]);

    $this->asInstitutionMember($selected);

    $response = $this->getJson('/api/institutions');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['code' => 'ITD001'])
        ->assertJsonMissing(['code' => 'UNI001']);
}

public function test_can_update_an_institution(): void
{
        $this->asPlatformAdmin();
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD001',
        'type' => 'institute',
        'is_active' => true,
    ]);

    $response = $this->putJson(
        '/api/institutions/' . $institution->id,
        [
            'name' => 'Instituto Tecnológico Actualizado',
            'code' => 'ITD002',
            'type' => 'institute',
            'description' => 'Institución actualizada',
            'is_active' => false,
        ]
    );

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Instituto Tecnológico Actualizado')
        ->assertJsonPath('data.code', 'ITD002')
        ->assertJsonPath('data.is_active', false);

    $this->assertDatabaseHas('institutions', [
        'id' => $institution->id,
        'name' => 'Instituto Tecnológico Actualizado',
        'code' => 'ITD002',
        'is_active' => false,
    ]);
}

public function test_rejects_duplicate_institution_code_on_update(): void
{
        $this->asPlatformAdmin();
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

    // Intentar asignar a la segunda institución
    // el código de la primera.
    $response = $this->putJson(
        '/api/institutions/' . $institution2->id,
        [
            'name' => 'Universidad de Prueba',
            'code' => 'ITD001',
            'type' => 'university',
            'is_active' => true,
        ]
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);

    // La segunda institución debe conservar su código.
    $this->assertDatabaseHas('institutions', [
        'id' => $institution2->id,
        'code' => 'UNI001',
    ]);

    $this->assertDatabaseCount('institutions', 2);
}

public function test_can_update_institution_without_changing_own_code(): void
{
        $this->asPlatformAdmin();
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD001',
        'type' => 'institute',
        'is_active' => true,
    ]);

    // Actualizar la institución conservando su código
    $response = $this->putJson(
        '/api/institutions/' . $institution->id,
        [
            'name' => 'Instituto Tecnológico Actualizado',
            'code' => 'ITD001',
            'type' => 'institute',
            'is_active' => false,
        ]
    );

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Instituto Tecnológico Actualizado')
        ->assertJsonPath('data.code', 'ITD001')
        ->assertJsonPath('data.is_active', false);

    $this->assertDatabaseHas('institutions', [
        'id' => $institution->id,
        'name' => 'Instituto Tecnológico Actualizado',
        'code' => 'ITD001',
        'is_active' => false,
    ]);
}

public function test_can_delete_an_institution(): void
{
        $this->asPlatformAdmin();
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD001',
        'type' => 'institute',
        'is_active' => true,
    ]);

    // Eliminar la institución
    $response = $this->deleteJson(
        '/api/institutions/' . $institution->id
    );

    // Verificar la respuesta
    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath(
            'message',
            'Institución eliminada correctamente'
        );

    // Verificar que ya no existe en la base de datos
    $this->assertDatabaseMissing('institutions', [
        'id' => $institution->id,
    ]);
}

public function test_returns_404_when_institution_does_not_exist(): void
{
    // Consultar una institución inexistente
    $selected = Institution::create([
        'name' => 'Institución autorizada',
        'code' => 'FIXTURE-INST-404',
        'type' => 'institute',
        'is_active' => true,
    ]);
    $this->asInstitutionMember($selected);

    $response = $this->getJson('/api/institutions/999999');

    // Laravel debe devolver HTTP 404
    $response->assertNotFound();
}

}
