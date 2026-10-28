<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class InstitutionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ruta creada solo para estas pruebas. No modifica routes/api.php.
     * Contrato esperado del futuro middleware "institution.access":
     * - Leer X-Institution-Id.
     * - Validar que la membresía del usuario y la institución estén activas.
     * - Guardar el ID autorizado como atributo "institution_id" en Request.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', 'auth:sanctum', 'institution.access'])
            ->get('/api/__test/institution-authorization', function (Request $request) {
                return response()->json([
                    'institution_id' => $request->attributes->get('institution_id'),
                ]);
            });
    }

    private function crearInstitucion(string $codigo, bool $activa = true): Institution
    {
        return Institution::create([
            'name' => 'Institución '.$codigo,
            'code' => $codigo,
            'type' => 'institute',
            'description' => 'Prueba de autorización institucional',
            'is_active' => $activa,
        ]);
    }

    private function vincular(User $usuario, Institution $institucion, bool $activa = true): void
    {
        $usuario->institutions()->attach($institucion->id, [
            'is_active' => $activa,
        ]);
    }

    private function consultar(?string $institucionId = null)
    {
        $headers = $institucionId === null
            ? []
            : ['X-Institution-Id' => $institucionId];

        return $this->withHeaders($headers)
            ->getJson('/api/__test/institution-authorization');
    }

    public function test_rechaza_usuario_no_autenticado(): void
    {
        $institucion = $this->crearInstitucion('INST-A');

        $this->consultar((string) $institucion->id)->assertUnauthorized();
    }

    public function test_rechaza_solicitud_sin_institucion_seleccionada(): void
    {
        $usuario = User::factory()->create();
        $institucion = $this->crearInstitucion('INST-A');
        $this->vincular($usuario, $institucion);

        $this->actingAs($usuario);

        $this->consultar()->assertUnprocessable();
    }

    public function test_rechaza_encabezado_de_institucion_no_numerico(): void
    {
        $usuario = User::factory()->create();
        $this->actingAs($usuario);

        $this->consultar('instituto-falso')->assertUnprocessable();
    }

    public function test_rechaza_usuario_sin_membresia(): void
    {
        $usuario = User::factory()->create();
        $institucion = $this->crearInstitucion('INST-A');
        $this->actingAs($usuario);

        $this->consultar((string) $institucion->id)->assertForbidden();
    }

    public function test_rechaza_institucion_inexistente(): void
    {
        $usuario = User::factory()->create();
        $this->actingAs($usuario);

        // Evitamos distinguir entre instituciones inexistentes y no autorizadas.
        $this->consultar('999999')->assertForbidden();
    }

    public function test_rechaza_membresia_inactiva(): void
    {
        $usuario = User::factory()->create();
        $institucion = $this->crearInstitucion('INST-A');
        $this->vincular($usuario, $institucion, false);
        $this->actingAs($usuario);

        $this->consultar((string) $institucion->id)->assertForbidden();
    }

    public function test_rechaza_institucion_inactiva_aunque_membresia_este_activa(): void
    {
        $usuario = User::factory()->create();
        $institucion = $this->crearInstitucion('INST-A', false);
        $this->vincular($usuario, $institucion);
        $this->actingAs($usuario);

        $this->consultar((string) $institucion->id)->assertForbidden();
    }

    public function test_permite_acceso_a_institucion_con_membresia_activa(): void
    {
        $usuario = User::factory()->create();
        $institucion = $this->crearInstitucion('INST-A');
        $this->vincular($usuario, $institucion);
        $this->actingAs($usuario);

        $this->consultar((string) $institucion->id)
            ->assertOk()
            ->assertJsonPath('institution_id', $institucion->id);
    }

    public function test_no_permite_suplantar_otra_institucion_modificando_el_encabezado(): void
    {
        $usuario = User::factory()->create();
        $autorizada = $this->crearInstitucion('INST-A');
        $ajena = $this->crearInstitucion('INST-B');
        $this->vincular($usuario, $autorizada);
        $this->actingAs($usuario);

        $this->consultar((string) $ajena->id)->assertForbidden();
    }

    public function test_usuario_con_dos_membresias_puede_elegir_cual_esta_activa(): void
    {
        $usuario = User::factory()->create();
        $primera = $this->crearInstitucion('INST-A');
        $segunda = $this->crearInstitucion('INST-B');
        $this->vincular($usuario, $primera);
        $this->vincular($usuario, $segunda);
        $this->actingAs($usuario);

        $this->consultar((string) $primera->id)
            ->assertOk()
            ->assertJsonPath('institution_id', $primera->id);

        $this->consultar((string) $segunda->id)
            ->assertOk()
            ->assertJsonPath('institution_id', $segunda->id);
    }
}
