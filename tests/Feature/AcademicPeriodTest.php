<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;




class AcademicPeriodTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Solo para las pruebas funcionales heredadas; las 18 pruebas de aislamiento
     * mantienen sus solicitudes deliberadamente no autorizadas sin alteraciones.
     */
    private function academicPeriodTenantTestHeaders($uri, array $data = []): array
    {
        $institution = null;

        if (isset($data['institution_id'])) {
            $institution = Institution::find($data['institution_id']);
        }

        if ($institution === null && preg_match('~^/api/academic-periods/(\d+)$~', $uri, $m)) {
            $period = AcademicPeriod::find((int) $m[1]);
            if ($period !== null) {
                $institution = Institution::find($period->institution_id);
            }
        }

        if ($institution === null) {
            $institution = Institution::query()->orderBy('id')->first();
        }

        if ($institution === null) {
            $institution = Institution::create([
                'name' => 'Institución de pruebas AcademicPeriod',
                'code' => 'APER-TEST',
                'type' => 'institute',
                'is_active' => true,
            ]);
        }

        if (! $institution->is_active) {
            $institution->update(['is_active' => true]);
        }

        $user = User::factory()->create();
        $user->institutions()->attach($institution->id, ['is_active' => true]);
        $this->actingAs($user);

        return ['X-Institution-Id' => (string) $institution->id];
    }

    public function getJson($uri, array $headers = [], $options = 0)
    {
        return parent::getJson($uri, array_merge($this->academicPeriodTenantTestHeaders($uri), $headers), $options);
    }

    public function postJson($uri, array $data = [], array $headers = [], $options = 0)
    {
        return parent::postJson($uri, $data, array_merge($this->academicPeriodTenantTestHeaders($uri, $data), $headers), $options);
    }

    public function putJson($uri, array $data = [], array $headers = [], $options = 0)
    {
        return parent::putJson($uri, $data, array_merge($this->academicPeriodTenantTestHeaders($uri, $data), $headers), $options);
    }

    public function deleteJson($uri, array $data = [], array $headers = [], $options = 0)
    {
        return parent::deleteJson($uri, $data, array_merge($this->academicPeriodTenantTestHeaders($uri, $data), $headers), $options);
    }

    public function test_puede_crear_un_periodo_academico_con_codigo(): void
    {
        $institution = Institution::create([
            'name' => 'Instituto Tecnológico Demo',
            'code' => 'ITD',
            'type' => 'institute',
        ]);

        $response = $this->postJson('/api/academic-periods', [
            'institution_id' => $institution->id,
            'name' => 'Segundo semestre 2026',
            'code' => '2026-II',
            'start_date' => '2026-08-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', '2026-II');

        $this->assertDatabaseHas('academic_periods', [
            'institution_id' => $institution->id,
            'name' => 'Segundo semestre 2026',
            'code' => '2026-II',
        ]);
    }
    
public function test_no_puede_crear_periodos_duplicados_en_la_misma_institucion(): void
{
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD',
        'type' => 'institute',
    ]);

    AcademicPeriod::create([
        'institution_id' => $institution->id,
        'name' => 'Segundo semestre 2026',
        'code' => '2026-II',
        'start_date' => '2026-08-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/academic-periods', [
        'institution_id' => $institution->id,
        'name' => 'Segundo semestre 2026',
        'code' => '2026-II',
        'start_date' => '2026-08-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);

    $this->assertDatabaseCount('academic_periods', 1);
}

public function test_permite_mismo_nombre_de_periodo_en_instituciones_diferentes(): void
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

    AcademicPeriod::create([
        'institution_id' => $institution1->id,
        'name' => '2026-II',
        'code' => '2026-II',
        'start_date' => '2026-08-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/academic-periods', [
        'institution_id' => $institution2->id,
        'name' => '2026-II',
        'code' => '2026-II',
        'start_date' => '2026-08-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true);

    $this->assertDatabaseCount('academic_periods', 2);

    $this->assertDatabaseHas('academic_periods', [
        'institution_id' => $institution2->id,
        'name' => '2026-II',
    ]);
}

public function test_no_permite_fecha_fin_anterior_a_fecha_inicio(): void
{
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD',
        'type' => 'institute',
    ]);

    $response = $this->postJson('/api/academic-periods', [
        'institution_id' => $institution->id,
        'name' => 'Segundo semestre 2026',
        'code' => '2026-II',
        'start_date' => '2026-08-01',
        'end_date' => '2026-07-01',
        'is_active' => true,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['end_date']);

    $this->assertDatabaseCount('academic_periods', 0);
}

public function test_no_permite_crear_periodo_sin_campos_obligatorios(): void
{
    $response = $this->postJson('/api/academic-periods', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'institution_id',
            'name',
            'start_date',
            'end_date',
        ]);

    $this->assertDatabaseCount('academic_periods', 0);
}

public function test_no_permite_crear_periodo_con_institucion_inexistente(): void
{
    $response = $this->postJson('/api/academic-periods', [
        'institution_id' => 99999,
        'name' => 'Segundo semestre 2026',
        'code' => '2026-II',
        'start_date' => '2026-08-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['institution_id']);

    $this->assertDatabaseCount('academic_periods', 0);
}

public function test_puede_consultar_periodo_academico_con_su_institucion(): void
{
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD',
        'type' => 'institute',
    ]);

    $period = AcademicPeriod::create([
        'institution_id' => $institution->id,
        'name' => 'Segundo semestre 2026',
        'code' => '2026-II',
        'start_date' => '2026-08-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
    ]);

    $response = $this->getJson(
        "/api/academic-periods/{$period->id}"
    );

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $period->id)
        ->assertJsonPath('data.code', '2026-II')
        ->assertJsonPath(
            'data.institution.name',
            'Instituto Tecnológico Demo'
        );
}

public function test_puede_listar_periodos_academicos_con_sus_instituciones(): void
{
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD',
        'type' => 'institute',
    ]);

    AcademicPeriod::create([
        'institution_id' => $institution->id,
        'name' => 'Primer semestre 2026',
        'code' => '2026-I',
        'start_date' => '2026-01-01',
        'end_date' => '2026-06-30',
        'is_active' => true,
    ]);

    AcademicPeriod::create([
        'institution_id' => $institution->id,
        'name' => 'Segundo semestre 2026',
        'code' => '2026-II',
        'start_date' => '2026-08-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/academic-periods');

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment([
            'code' => '2026-I',
        ])
        ->assertJsonFragment([
            'code' => '2026-II',
        ])
        ->assertJsonPath(
            'data.0.institution.name',
            'Instituto Tecnológico Demo'
        )
        ->assertJsonPath(
            'data.1.institution.name',
            'Instituto Tecnológico Demo'
        );
}


public function test_puede_actualizar_un_periodo_academico(): void
{
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD',
        'type' => 'institute',
    ]);

    $period = AcademicPeriod::create([
        'institution_id' => $institution->id,
        'name' => 'Primer semestre 2026',
        'code' => '2026-I',
        'start_date' => '2026-01-01',
        'end_date' => '2026-06-30',
        'is_active' => true,
    ]);

    $response = $this->putJson(
        "/api/academic-periods/{$period->id}",
        [
            'institution_id' => $institution->id,
            'name' => 'Segundo semestre 2026',
            'code' => '2026-II',
            'start_date' => '2026-08-01',
            'end_date' => '2026-12-31',
            'is_active' => false,
        ]
    );

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Segundo semestre 2026')
        ->assertJsonPath('data.code', '2026-II')
        ->assertJsonPath('data.is_active', false);

    $this->assertDatabaseHas('academic_periods', [
        'id' => $period->id,
        'institution_id' => $institution->id,
        'name' => 'Segundo semestre 2026',
        'code' => '2026-II',
        'is_active' => false,
    ]);

    $period->refresh();

    $this->assertEquals(
        '2026-08-01',
        $period->start_date->format('Y-m-d')
    );

    $this->assertEquals(
        '2026-12-31',
        $period->end_date->format('Y-m-d')
    );
}

public function test_no_permite_nombre_duplicado_al_actualizar_periodo(): void
{
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD',
        'type' => 'institute',
    ]);

    AcademicPeriod::create([
        'institution_id' => $institution->id,
        'name' => 'Primer semestre 2026',
        'code' => '2026-I',
        'start_date' => '2026-01-01',
        'end_date' => '2026-06-30',
        'is_active' => true,
    ]);

    $period = AcademicPeriod::create([
        'institution_id' => $institution->id,
        'name' => 'Segundo semestre 2026',
        'code' => '2026-II',
        'start_date' => '2026-08-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
    ]);

    $response = $this->putJson(
        "/api/academic-periods/{$period->id}",
        [
            'institution_id' => $institution->id,
            'name' => 'Primer semestre 2026',
            'code' => '2026-II',
            'start_date' => '2026-08-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]
    );

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);

    $this->assertDatabaseHas('academic_periods', [
        'id' => $period->id,
        'name' => 'Segundo semestre 2026',
    ]);

    $this->assertDatabaseCount('academic_periods', 2);
}


public function test_permite_actualizar_periodo_conservando_su_nombre(): void
{
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD',
        'type' => 'institute',
    ]);

    $period = AcademicPeriod::create([
        'institution_id' => $institution->id,
        'name' => 'Segundo semestre 2026',
        'code' => '2026-II',
        'start_date' => '2026-08-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
    ]);

    $response = $this->putJson(
        "/api/academic-periods/{$period->id}",
        [
            'institution_id' => $institution->id,
            'name' => 'Segundo semestre 2026',
            'code' => '2026-II-A',
            'start_date' => '2026-08-15',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]
    );

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Segundo semestre 2026')
        ->assertJsonPath('data.code', '2026-II-A');

    $period->refresh();

    $this->assertEquals(
        '2026-08-15',
        $period->start_date->format('Y-m-d')
    );

    $this->assertDatabaseHas('academic_periods', [
        'id' => $period->id,
        'name' => 'Segundo semestre 2026',
        'code' => '2026-II-A',
    ]);
}

public function test_puede_eliminar_periodo_sin_eliminar_institucion(): void
{
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD',
        'type' => 'institute',
    ]);

    $period = AcademicPeriod::create([
        'institution_id' => $institution->id,
        'name' => 'Segundo semestre 2026',
        'code' => '2026-II',
        'start_date' => '2026-08-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
    ]);

    $response = $this->deleteJson(
        "/api/academic-periods/{$period->id}"
    );

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath(
            'message',
            'Período académico eliminado correctamente'
        );

    $this->assertDatabaseMissing('academic_periods', [
        'id' => $period->id,
    ]);

    $this->assertDatabaseHas('institutions', [
        'id' => $institution->id,
        'name' => 'Instituto Tecnológico Demo',
    ]);
}

public function test_devuelve_404_si_periodo_academico_no_existe(): void
{
    $response = $this->getJson('/api/academic-periods/99999');

    $response->assertStatus(404);

    $this->assertDatabaseCount('academic_periods', 0);
}

}
