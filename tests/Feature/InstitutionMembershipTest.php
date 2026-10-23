<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;


class InstitutionMembershipTest extends TestCase
{
    use RefreshDatabase;

    private function crearInstitucion(string $codigo): Institution
    {
        return Institution::create([
            'name' => 'Institución ' . $codigo,
            'code' => $codigo,
            'type' => 'institute',
            'description' => 'Institución para pruebas de membresías',
            'is_active' => true,
        ]);
    }

    public function test_puede_vincular_un_usuario_a_una_institucion(): void
    {
        $usuario = User::factory()->create();
        $institucion = $this->crearInstitucion('INST-A');

        $usuario->institutions()->attach($institucion->id);

        $this->assertDatabaseHas('institution_user', [
            'user_id' => $usuario->id,
            'institution_id' => $institucion->id,
            'is_active' => true,
        ]);
        $this->assertTrue($usuario->fresh()->institutions->contains('id', $institucion->id));
    }

    public function test_puede_consultar_los_usuarios_de_una_institucion(): void
    {
        $institucion = $this->crearInstitucion('INST-A');
        $usuario = User::factory()->create();

        $institucion->users()->attach($usuario->id);

        $this->assertTrue($institucion->fresh()->users->contains('id', $usuario->id));
    }

    public function test_un_usuario_puede_pertenecer_a_varias_instituciones(): void
    {
        $usuario = User::factory()->create();
        $primera = $this->crearInstitucion('INST-A');
        $segunda = $this->crearInstitucion('INST-B');

        $usuario->institutions()->attach([$primera->id, $segunda->id]);

        $this->assertCount(2, $usuario->fresh()->institutions);
        $this->assertDatabaseHas('institution_user', [
            'user_id' => $usuario->id,
            'institution_id' => $primera->id,
        ]);
        $this->assertDatabaseHas('institution_user', [
            'user_id' => $usuario->id,
            'institution_id' => $segunda->id,
        ]);
    }

    public function test_una_institucion_puede_tener_varios_usuarios(): void
    {
        $institucion = $this->crearInstitucion('INST-A');
        $primero = User::factory()->create();
        $segundo = User::factory()->create();

        $institucion->users()->attach([$primero->id, $segundo->id]);

        $this->assertCount(2, $institucion->fresh()->users);
    }

    public function test_no_permite_duplicar_la_membresia_de_un_usuario_en_la_misma_institucion(): void
    {
        $usuario = User::factory()->create();
        $institucion = $this->crearInstitucion('INST-A');

        $usuario->institutions()->attach($institucion->id);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $usuario->institutions()->attach($institucion->id);
    }

    public function test_la_membresia_es_activa_por_defecto(): void
    {
        $usuario = User::factory()->create();
        $institucion = $this->crearInstitucion('INST-A');

        $usuario->institutions()->attach($institucion->id);

        $this->assertDatabaseHas('institution_user', [
            'user_id' => $usuario->id,
            'institution_id' => $institucion->id,
            'is_active' => 1,
        ]);
    }

    public function test_puede_desactivar_una_membresia_sin_eliminarla(): void
    {
        $usuario = User::factory()->create();
        $institucion = $this->crearInstitucion('INST-A');
        $usuario->institutions()->attach($institucion->id);

        $usuario->institutions()->updateExistingPivot($institucion->id, [
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('institution_user', [
            'user_id' => $usuario->id,
            'institution_id' => $institucion->id,
            'is_active' => 0,
        ]);
        $this->assertCount(1, $usuario->fresh()->institutions);
    }

    public function test_puede_reactivar_una_membresia(): void
    {
        $usuario = User::factory()->create();
        $institucion = $this->crearInstitucion('INST-A');
        $usuario->institutions()->attach($institucion->id, ['is_active' => false]);

        $usuario->institutions()->updateExistingPivot($institucion->id, [
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('institution_user', [
            'user_id' => $usuario->id,
            'institution_id' => $institucion->id,
            'is_active' => 1,
        ]);
    }

    public function test_puede_desvincular_un_usuario_sin_eliminar_su_cuenta_ni_la_institucion(): void
    {
        $usuario = User::factory()->create();
        $institucion = $this->crearInstitucion('INST-A');
        $usuario->institutions()->attach($institucion->id);

        $usuario->institutions()->detach($institucion->id);

        $this->assertDatabaseMissing('institution_user', [
            'user_id' => $usuario->id,
            'institution_id' => $institucion->id,
        ]);
        $this->assertDatabaseHas('users', ['id' => $usuario->id]);
        $this->assertDatabaseHas('institutions', ['id' => $institucion->id]);
    }

    public function test_desvincular_una_institucion_conserva_las_otras_membresias(): void
    {
        $usuario = User::factory()->create();
        $primera = $this->crearInstitucion('INST-A');
        $segunda = $this->crearInstitucion('INST-B');
        $usuario->institutions()->attach([$primera->id, $segunda->id]);

        $usuario->institutions()->detach($primera->id);

        $this->assertCount(1, $usuario->fresh()->institutions);
        $this->assertDatabaseHas('institution_user', [
            'user_id' => $usuario->id,
            'institution_id' => $segunda->id,
        ]);
    }

    public function test_eliminar_usuario_elimina_sus_membresias(): void
    {
        $usuario = User::factory()->create();
        $institucion = $this->crearInstitucion('INST-A');
        $usuario->institutions()->attach($institucion->id);

        $usuario->delete();

        $this->assertDatabaseMissing('institution_user', [
            'user_id' => $usuario->id,
            'institution_id' => $institucion->id,
        ]);
        $this->assertDatabaseHas('institutions', ['id' => $institucion->id]);
    }

    public function test_eliminar_institucion_elimina_sus_membresias(): void
    {
        $usuario = User::factory()->create();
        $institucion = $this->crearInstitucion('INST-A');
        $usuario->institutions()->attach($institucion->id);

        $institucion->delete();

        $this->assertDatabaseMissing('institution_user', [
            'user_id' => $usuario->id,
            'institution_id' => $institucion->id,
        ]);
        $this->assertDatabaseHas('users', ['id' => $usuario->id]);
    }

    public function test_puede_vincular_una_persona_de_la_institucion_a_la_membresia(): void
    {
        $usuario = User::factory()->create();
        $institucion = $this->crearInstitucion('INST-A');
        $persona = Person::create([
            'institution_id' => $institucion->id,
            'document_type' => 'DNI',
            'document_number' => '12345678',
            'first_name' => 'Persona',
            'last_name' => 'Prueba',
            'email' => 'persona@example.com',
        ]);

        $usuario->institutions()->attach($institucion->id, [
            'person_id' => $persona->id,
        ]);

        $this->assertSame(
            $persona->id,
            (int) $usuario->fresh()->institutions->first()->pivot->person_id
        );
    }

    public function test_eliminar_persona_conserva_la_membresia_y_deja_person_id_en_null(): void
    {
        $usuario = User::factory()->create();
        $institucion = $this->crearInstitucion('INST-A');
        $persona = Person::create([
            'institution_id' => $institucion->id,
            'document_type' => 'DNI',
            'document_number' => '87654321',
            'first_name' => 'Otra',
            'last_name' => 'Persona',
            'email' => 'otra@example.com',
        ]);
        $usuario->institutions()->attach($institucion->id, [
            'person_id' => $persona->id,
        ]);

        $persona->delete();

        $this->assertDatabaseHas('institution_user', [
            'user_id' => $usuario->id,
            'institution_id' => $institucion->id,
            'person_id' => null,
        ]);
    }
}
