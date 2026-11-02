<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PersonTest extends TestCase
{
    use RefreshDatabase;

    // PERSON_AUTH_LEGACY_TESTS_V1
    private ?User $tenantUser = null;

    private function institution(string $suffix = 'A'): Institution
    {
        $institution = Institution::create([
            'name' => "Instituto Demo {$suffix}",
            'code' => "INST{$suffix}",
            'type' => 'institute',
        ]);

        if ($this->tenantUser === null) {
            $this->tenantUser = User::factory()->create();
            $this->tenantUser->institutions()->attach($institution->id, ['is_active' => true, 'role' => 'institution_admin']);
            $this->actingAs($this->tenantUser);
            $this->selectPersonInstitution($institution);
        } else {
            $this->tenantUser->institutions()->attach($institution->id, ['is_active' => true, 'role' => 'institution_admin']);
        }

        return $institution;
    }

    private function selectPersonInstitution(Institution $institution): void
    {
        $this->withHeader('X-Institution-Id', (string) $institution->id);
    }

    private function payload(Institution $institution, array $changes = []): array
    {
        return array_replace([
            'institution_id' => $institution->id,
            'first_name' => 'Carlos',
            'last_name' => 'Ramírez',
            'document_type' => 'DNI',
            'document_number' => '12345678',
            'email' => 'carlos@example.com',
            'phone' => '987654321',
            'birth_date' => '2000-02-10',
        ], $changes);
    }

    private function person(Institution $institution, array $changes = []): Person
    {
        return Person::create($this->payload($institution, $changes));
    }

    private function student(Person $person, string $code = 'EST001'): int
    {
        return DB::table('students')->insertGetId([
            'person_id' => $person->id,
            'student_code' => $code,
            'status' => 'active',
        ]);
    }

    public function test_puede_crear_persona_con_todos_sus_campos(): void
    {
        $institution = $this->institution();
        $response = $this->postJson('/api/people', $this->payload($institution));

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.institution_id', $institution->id)
            ->assertJsonPath('data.first_name', 'Carlos')
            ->assertJsonPath('data.last_name', 'Ramírez')
            ->assertJsonPath('data.document_type', 'DNI')
            ->assertJsonPath('data.document_number', '12345678')
            ->assertJsonPath('data.email', 'carlos@example.com')
            ->assertJsonPath('data.phone', '987654321')
            ->assertJsonPath('data.institution.id', $institution->id);

        $this->assertSame('2000-02-10', substr($response->json('data.birth_date'), 0, 10));
        $this->assertDatabaseHas('people', [
            'institution_id' => $institution->id,
            'document_number' => '12345678',
            'document_type' => 'DNI',
        ]);
        $this->assertDatabaseCount('people', 1);
    }

    public function test_puede_crear_persona_sin_campos_opcionales(): void
    {
        $institution = $this->institution();
        $payload = $this->payload($institution);
        unset($payload['email'], $payload['phone'], $payload['birth_date']);

        $this->postJson('/api/people', $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.email', null)
            ->assertJsonPath('data.phone', null)
            ->assertJsonPath('data.birth_date', null);
        $this->assertDatabaseCount('people', 1);
    }

    public function test_puede_crear_persona_con_campos_opcionales_nulos(): void
    {
        $institution = $this->institution();
        $this->postJson('/api/people', $this->payload($institution, [
            'email' => null, 'phone' => null, 'birth_date' => null,
        ]))->assertStatus(201);
        $this->assertDatabaseCount('people', 1);
    }

    public function test_puede_listar_personas_con_su_institucion(): void
    {
        $institution = $this->institution();
        $this->person($institution);
        $this->person($institution, ['document_number' => '87654321', 'first_name' => 'Ana']);

        $this->getJson('/api/people')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.institution.id', $institution->id)
            ->assertJsonPath('data.1.first_name', 'Ana');
    }

    public function test_lista_vacia_si_no_existen_personas(): void
    {
        $this->institution();
        $this->getJson('/api/people')->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data');
    }

    public function test_puede_consultar_persona_con_su_institucion(): void
    {
        $institution = $this->institution();
        $person = $this->person($institution);

        $this->getJson("/api/people/{$person->id}")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $person->id)
            ->assertJsonPath('data.institution.id', $institution->id)
            ->assertJsonPath('data.document_number', '12345678');
    }

    public function test_no_permite_crear_persona_sin_campos_obligatorios(): void
    {
        $this->institution();
        $this->postJson('/api/people', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'institution_id', 'first_name', 'last_name',
                'document_type', 'document_number',
            ]);
        $this->assertDatabaseCount('people', 0);
    }

    public function test_no_permite_crear_persona_con_institucion_inexistente(): void
    {
        $institution = $this->institution();
        $this->postJson('/api/people', $this->payload($institution, ['institution_id' => 99999]))
            ->assertStatus(422)->assertJsonValidationErrors('institution_id');
        $this->assertDatabaseCount('people', 0);
    }

    public function test_no_permite_institucion_no_numerica(): void
    {
        $institution = $this->institution();
        $this->postJson('/api/people', $this->payload($institution, ['institution_id' => 'abc']))
            ->assertStatus(422)->assertJsonValidationErrors('institution_id');
    }

    public function test_no_permite_documento_duplicado_en_misma_institucion(): void
    {
        $institution = $this->institution();
        $this->person($institution);

        $this->postJson('/api/people', $this->payload($institution, ['first_name' => 'Ana']))
            ->assertStatus(422)->assertJsonValidationErrors('document_number');
        $this->assertDatabaseCount('people', 1);
    }

    public function test_no_permite_mismo_numero_de_documento_con_otro_tipo_en_una_institucion(): void
    {
        $institution = $this->institution();
        $this->person($institution);
        // La restricción real de BD es (institution_id, document_number).
        $this->postJson('/api/people', $this->payload($institution, ['document_type' => 'CE']))
            ->assertStatus(422)->assertJsonValidationErrors('document_number');
        $this->assertDatabaseCount('people', 1);
    }

    public function test_permite_mismo_documento_en_instituciones_diferentes(): void
    {
        $first = $this->institution('A');
        $second = $this->institution('B');
        $this->person($first);
        $this->selectPersonInstitution($second);

        $this->postJson('/api/people', $this->payload($second))
            ->assertStatus(201)
            ->assertJsonPath('data.institution_id', $second->id);
        $this->assertDatabaseCount('people', 2);
    }

    public function test_no_permite_correo_invalido(): void
    {
        $institution = $this->institution();
        $this->postJson('/api/people', $this->payload($institution, ['email' => 'correo-sin-arroba']))
            ->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_no_permite_correo_mayor_a_255_caracteres(): void
    {
        $institution = $this->institution();
        $this->postJson('/api/people', $this->payload($institution, [
            'email' => str_repeat('a', 250).'@example.com',
        ]))->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_no_permite_nombre_mayor_a_255_caracteres(): void
    {
        $institution = $this->institution();
        $this->postJson('/api/people', $this->payload($institution, ['first_name' => str_repeat('a', 256)]))
            ->assertStatus(422)->assertJsonValidationErrors('first_name');
    }

    public function test_no_permite_apellido_mayor_a_255_caracteres(): void
    {
        $institution = $this->institution();
        $this->postJson('/api/people', $this->payload($institution, ['last_name' => str_repeat('a', 256)]))
            ->assertStatus(422)->assertJsonValidationErrors('last_name');
    }

    public function test_no_permite_tipo_documento_mayor_a_20_caracteres(): void
    {
        $institution = $this->institution();
        $this->postJson('/api/people', $this->payload($institution, ['document_type' => str_repeat('X', 21)]))
            ->assertStatus(422)->assertJsonValidationErrors('document_type');
    }

    public function test_no_permite_numero_documento_mayor_a_50_caracteres(): void
    {
        $institution = $this->institution();
        $this->postJson('/api/people', $this->payload($institution, ['document_number' => str_repeat('1', 51)]))
            ->assertStatus(422)->assertJsonValidationErrors('document_number');
    }

    public function test_no_permite_telefono_mayor_a_20_caracteres(): void
    {
        $institution = $this->institution();
        $this->postJson('/api/people', $this->payload($institution, ['phone' => str_repeat('9', 21)]))
            ->assertStatus(422)->assertJsonValidationErrors('phone');
    }

    public function test_no_permite_fecha_nacimiento_invalida(): void
    {
        $institution = $this->institution();
        $this->postJson('/api/people', $this->payload($institution, ['birth_date' => 'fecha-invalida']))
            ->assertStatus(422)->assertJsonValidationErrors('birth_date');
    }

    public function test_puede_actualizar_datos_de_persona(): void
    {
        $institution = $this->institution();
        $person = $this->person($institution);

        $response = $this->putJson("/api/people/{$person->id}", $this->payload($institution, [
            'first_name' => 'María',
            'last_name' => 'García',
            'document_number' => '23456789',
            'email' => 'maria@example.com',
            'birth_date' => '2001-03-15',
        ]));

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $person->id)
            ->assertJsonPath('data.first_name', 'María')
            ->assertJsonPath('data.document_number', '23456789')
            ->assertJsonPath('data.institution.id', $institution->id);
        $this->assertSame('2001-03-15', substr($response->json('data.birth_date'), 0, 10));
        $this->assertDatabaseHas('people', [
            'id' => $person->id,
            'first_name' => 'María',
            'last_name' => 'García',
            'document_number' => '23456789',
        ]);
    }

    public function test_permite_actualizar_conservando_su_mismo_documento(): void
    {
        $institution = $this->institution();
        $person = $this->person($institution);

        $this->putJson("/api/people/{$person->id}", $this->payload($institution, [
            'first_name' => 'Carlos Alberto',
        ]))->assertStatus(200)
            ->assertJsonPath('data.document_number', '12345678')
            ->assertJsonPath('data.first_name', 'Carlos Alberto');
        $this->assertDatabaseCount('people', 1);
    }

    public function test_no_permite_documento_duplicado_al_actualizar(): void
    {
        $institution = $this->institution();
        $first = $this->person($institution);
        $second = $this->person($institution, ['document_number' => '87654321']);

        $this->putJson("/api/people/{$second->id}", $this->payload($institution, [
            'document_number' => $first->document_number,
        ]))->assertStatus(422)->assertJsonValidationErrors('document_number');
        $this->assertDatabaseHas('people', [
            'id' => $second->id,
            'document_number' => '87654321',
        ]);
    }

    public function test_no_permite_actualizar_sin_campos_obligatorios(): void
    {
        $institution = $this->institution();
        $person = $this->person($institution);

        $this->putJson("/api/people/{$person->id}", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'institution_id', 'first_name', 'last_name',
                'document_type', 'document_number',
            ]);
        $this->assertDatabaseHas('people', ['id' => $person->id, 'document_number' => '12345678']);
    }

    public function test_no_permite_institucion_inexistente_al_actualizar(): void
    {
        $institution = $this->institution();
        $person = $this->person($institution);

        $this->putJson("/api/people/{$person->id}", $this->payload($institution, [
            'institution_id' => 99999,
        ]))->assertStatus(422)->assertJsonValidationErrors('institution_id');
        $this->assertDatabaseHas('people', ['id' => $person->id, 'institution_id' => $institution->id]);
    }

    public function test_no_permite_email_invalido_al_actualizar(): void
    {
        $institution = $this->institution();
        $person = $this->person($institution);

        $this->putJson("/api/people/{$person->id}", $this->payload($institution, [
            'email' => 'no-es-un-email',
        ]))->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_no_permite_cambiar_de_institucion_a_persona_sin_dependencias(): void
    {
        $original = $this->institution('A');
        $target = $this->institution('B');
        $person = $this->person($original);

        $this->putJson("/api/people/{$person->id}", $this->payload($target))
            ->assertStatus(422)->assertJsonValidationErrors('institution_id');
        $this->assertDatabaseHas('people', ['id' => $person->id, 'institution_id' => $original->id]);
    }

    public function test_no_permite_trasladar_persona_a_institucion_con_documento_ya_registrado(): void
    {
        $original = $this->institution('A');
        $target = $this->institution('B');
        $person = $this->person($original);
        $this->person($target);

        $this->putJson("/api/people/{$person->id}", $this->payload($target))
            ->assertStatus(422)->assertJsonValidationErrors('institution_id');
        $this->assertDatabaseHas('people', ['id' => $person->id, 'institution_id' => $original->id]);
    }

    public function test_permite_corregir_fecha_de_nacimiento(): void
    {
        $institution = $this->institution();
        $person = $this->person($institution);

        $response = $this->putJson("/api/people/{$person->id}", $this->payload($institution, [
            'birth_date' => '2002-05-20',
        ]));
        $response->assertStatus(200);
        $this->assertSame('2002-05-20', substr($response->json('data.birth_date'), 0, 10));
    }

    public function test_devuelve_404_al_consultar_persona_inexistente(): void
    {
        $this->institution();
        $this->getJson('/api/people/99999')->assertStatus(404);
    }

    public function test_devuelve_404_al_actualizar_persona_inexistente(): void
    {
        $institution = $this->institution();
        $this->putJson('/api/people/99999', $this->payload($institution))->assertStatus(404);
    }

    public function test_devuelve_404_al_eliminar_persona_inexistente(): void
    {
        $this->institution();
        $this->deleteJson('/api/people/99999')->assertStatus(404);
    }

    public function test_puede_eliminar_persona_sin_dependencias(): void
    {
        $institution = $this->institution();
        $person = $this->person($institution);

        $this->deleteJson("/api/people/{$person->id}")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Persona eliminada correctamente');
        $this->assertDatabaseMissing('people', ['id' => $person->id]);
        $this->assertDatabaseHas('institutions', ['id' => $institution->id]);
    }

    public function test_eliminar_una_persona_no_elimina_a_las_demas(): void
    {
        $institution = $this->institution();
        $first = $this->person($institution);
        $second = $this->person($institution, ['document_number' => '87654321']);

        $this->deleteJson("/api/people/{$first->id}")->assertStatus(200);
        $this->assertDatabaseMissing('people', ['id' => $first->id]);
        $this->assertDatabaseHas('people', ['id' => $second->id]);
        $this->assertDatabaseCount('people', 1);
    }

    public function test_no_permite_eliminar_persona_que_es_estudiante(): void
    {
        $institution = $this->institution();
        $person = $this->person($institution);
        $studentId = $this->student($person);

        $this->deleteJson("/api/people/{$person->id}")
            ->assertStatus(409)->assertJsonPath('success', false);
        $this->assertDatabaseHas('people', ['id' => $person->id]);
        $this->assertDatabaseHas('students', ['id' => $studentId, 'person_id' => $person->id]);
    }

    public function test_no_permite_cambiar_institucion_de_persona_que_es_estudiante(): void
    {
        $original = $this->institution('A');
        $target = $this->institution('B');
        $person = $this->person($original);
        $studentId = $this->student($person);

        $this->putJson("/api/people/{$person->id}", $this->payload($target))
            ->assertStatus(422)->assertJsonValidationErrors('institution_id');
        $this->assertDatabaseHas('people', ['id' => $person->id, 'institution_id' => $original->id]);
        $this->assertDatabaseHas('students', ['id' => $studentId, 'person_id' => $person->id]);
    }

    public function test_permite_actualizar_datos_de_persona_estudiante_en_su_institucion(): void
    {
        $institution = $this->institution();
        $person = $this->person($institution);
        $studentId = $this->student($person);

        $this->putJson("/api/people/{$person->id}", $this->payload($institution, [
            'phone' => '912345678',
        ]))->assertStatus(200)->assertJsonPath('data.phone', '912345678');
        $this->assertDatabaseHas('students', ['id' => $studentId, 'person_id' => $person->id]);
    }
}
