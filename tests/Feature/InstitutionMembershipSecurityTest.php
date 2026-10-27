<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InstitutionMembershipSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function crearInstitucion(string $codigo): Institution
    {
        return Institution::create([
            'name' => 'Institución ' . $codigo,
            'code' => $codigo,
            'type' => 'institute',
            'description' => 'Prueba de aislamiento de membresías',
            'is_active' => true,
        ]);
    }

    private function crearPersona(Institution $institucion, string $documento): Person
    {
        return Person::create([
            'institution_id' => $institucion->id,
            'document_type' => 'DNI',
            'document_number' => $documento,
            'first_name' => 'Persona',
            'last_name' => 'Prueba',
            'email' => 'persona'.$documento.'@example.test',
        ]);
    }

    public function test_rechaza_vincular_persona_de_otra_institucion_desde_usuario(): void
    {
        $usuario = User::factory()->create();
        $institucionA = $this->crearInstitucion('INST-A');
        $institucionB = $this->crearInstitucion('INST-B');
        $personaB = $this->crearPersona($institucionB, '70000001');

        $this->expectException(ValidationException::class);

        $usuario->institutions()->attach($institucionA->id, [
            'person_id' => $personaB->id,
        ]);
    }

    public function test_rechaza_vincular_persona_de_otra_institucion_desde_institucion(): void
    {
        $usuario = User::factory()->create();
        $institucionA = $this->crearInstitucion('INST-A');
        $institucionB = $this->crearInstitucion('INST-B');
        $personaB = $this->crearPersona($institucionB, '70000002');

        $this->expectException(ValidationException::class);

        $institucionA->users()->attach($usuario->id, [
            'person_id' => $personaB->id,
        ]);
    }

    public function test_rechaza_actualizar_membresia_con_persona_de_otra_institucion(): void
    {
        $usuario = User::factory()->create();
        $institucionA = $this->crearInstitucion('INST-A');
        $institucionB = $this->crearInstitucion('INST-B');
        $personaA = $this->crearPersona($institucionA, '70000003');
        $personaB = $this->crearPersona($institucionB, '70000004');

        $usuario->institutions()->attach($institucionA->id, [
            'person_id' => $personaA->id,
        ]);

        try {
            $usuario->institutions()->updateExistingPivot($institucionA->id, [
                'person_id' => $personaB->id,
            ]);
            $this->fail('Se permitió asociar una persona de otra institución.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('person_id', $exception->errors());
        }

        $this->assertDatabaseHas('institution_user', [
            'user_id' => $usuario->id,
            'institution_id' => $institucionA->id,
            'person_id' => $personaA->id,
        ]);
    }

    public function test_permite_vincular_persona_de_la_misma_institucion(): void
    {
        $usuario = User::factory()->create();
        $institucion = $this->crearInstitucion('INST-A');
        $persona = $this->crearPersona($institucion, '70000005');

        $usuario->institutions()->attach($institucion->id, [
            'person_id' => $persona->id,
        ]);

        $this->assertDatabaseHas('institution_user', [
            'user_id' => $usuario->id,
            'institution_id' => $institucion->id,
            'person_id' => $persona->id,
        ]);
    }

    public function test_permite_crear_membresia_sin_persona_asociada(): void
    {
        $usuario = User::factory()->create();
        $institucion = $this->crearInstitucion('INST-A');

        $usuario->institutions()->attach($institucion->id);

        $this->assertDatabaseHas('institution_user', [
            'user_id' => $usuario->id,
            'institution_id' => $institucion->id,
            'person_id' => null,
        ]);
    }
}
