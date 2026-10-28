<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\AcademicProgram;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;



class AcademicPlanTest extends TestCase
{
    use RefreshDatabase;

    private ?User $usuarioTenant = null;
    private ?int $institucionTenantId = null;

    /**
     * Prepara el contexto de una petición autorizada a la institución del test.
     * Se ejecuta después de crear los fixtures, no en setUp(), porque los tests
     * crean su institución dentro de cada método.
     */
    private function prepararTenantParaPlan(): void
    {
        $institution = Institution::query()->orderBy('id')->first();

        if ($institution === null) {
            $institution = Institution::create([
                'name' => 'Institución de prueba para planes',
                'code' => 'TEST-PLAN',
                'type' => 'institute',
                'is_active' => true,
            ]);
        }

        if ($this->usuarioTenant === null) {
            $this->usuarioTenant = User::factory()->create();
        }

        if ($this->institucionTenantId !== $institution->id) {
            $this->usuarioTenant->institutions()->syncWithoutDetaching([
                $institution->id => ['is_active' => true],
            ]);
            $this->institucionTenantId = $institution->id;
        }

        $this->actingAs($this->usuarioTenant);
        $this->withHeader('X-Institution-Id', (string) $institution->id);
    }

    public function getJson($uri, array $headers = [], $options = 0)
    {
        $this->prepararTenantParaPlan();

        return parent::getJson($uri, $headers, $options);
    }

    public function postJson($uri, array $data = [], array $headers = [], $options = 0)
    {
        $this->prepararTenantParaPlan();

        return parent::postJson($uri, $data, $headers, $options);
    }

    public function putJson($uri, array $data = [], array $headers = [], $options = 0)
    {
        $this->prepararTenantParaPlan();

        return parent::putJson($uri, $data, $headers, $options);
    }

    public function deleteJson($uri, array $data = [], array $headers = [], $options = 0)
    {
        $this->prepararTenantParaPlan();

        return parent::deleteJson($uri, $data, $headers, $options);
    }



    public function test_puede_crear_plan_academico_con_todos_sus_campos(): void
    {
        $institution = Institution::create([
            'name' => 'Instituto Tecnológico Demo',
            'code' => 'ITD',
            'type' => 'institute',
        ]);

        $program = AcademicProgram::create([
            'institution_id' => $institution->id,
            'name' => 'Desarrollo de Software',
            'code' => 'DS',
            'description' => 'Carrera de programación',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/academic-plans', [
            'academic_program_id' => $program->id,
            'name' => 'Plan Curricular 2026',
            'version' => '1.0',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.academic_program_id', $program->id)
            ->assertJsonPath('data.name', 'Plan Curricular 2026')
            ->assertJsonPath('data.version', '1.0')
            ->assertJsonPath(
                'data.start_date',
                '2026-01-01T00:00:00.000000Z'
            )
            ->assertJsonPath(
                'data.end_date',
                '2026-12-31T00:00:00.000000Z'
            )
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('academic_plans', [
            'academic_program_id' => $program->id,
            'name' => 'Plan Curricular 2026',
            'version' => '1.0',
            'is_active' => 1,
        ]);

        $plan = DB::table('academic_plans')
            ->where('academic_program_id', $program->id)
            ->first();

        $this->assertNotNull($plan);

        $this->assertSame(
            '2026-01-01',
            substr($plan->start_date, 0, 10)
        );

        $this->assertSame(
            '2026-12-31',
            substr($plan->end_date, 0, 10)
        );

        $this->assertDatabaseCount('academic_plans', 1);
    }

    public function test_no_permite_planes_duplicados_en_el_mismo_programa(): void
    {
        $institution = Institution::create([
            'name' => 'Instituto Tecnológico Demo',
            'code' => 'ITD',
            'type' => 'institute',
        ]);

        $program = AcademicProgram::create([
            'institution_id' => $institution->id,
            'name' => 'Desarrollo de Software',
            'code' => 'DS',
            'description' => 'Carrera de programación',
            'is_active' => true,
        ]);

        $planData = [
            'academic_program_id' => $program->id,
            'name' => 'Plan Curricular 2026',
            'version' => '1.0',
            'is_active' => true,
        ];

        $this->postJson('/api/academic-plans', $planData)
            ->assertStatus(201);

        $response = $this->postJson(
            '/api/academic-plans',
            $planData
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        $this->assertDatabaseHas('academic_plans', [
            'academic_program_id' => $program->id,
            'name' => 'Plan Curricular 2026',
            'version' => '1.0',
        ]);

        $this->assertDatabaseCount('academic_plans', 1);
    }
    
    public function test_permite_mismo_nombre_de_plan_en_programas_diferentes(): void
    {
        $institution = Institution::create([
            'name' => 'Instituto Tecnológico Demo',
            'code' => 'ITD',
            'type' => 'institute',
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

        $response1 = $this->postJson('/api/academic-plans', [
            'academic_program_id' => $program1->id,
            'name' => 'Plan Curricular 2026',
            'version' => '1.0',
            'is_active' => true,
        ]);

        $response1->assertStatus(201)
            ->assertJsonPath('success', true);

        $response2 = $this->postJson('/api/academic-plans', [
            'academic_program_id' => $program2->id,
            'name' => 'Plan Curricular 2026',
            'version' => '1.0',
            'is_active' => true,
        ]);

        $response2->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.academic_program_id', $program2->id)
            ->assertJsonPath('data.name', 'Plan Curricular 2026');

        $this->assertDatabaseHas('academic_plans', [
            'academic_program_id' => $program1->id,
            'name' => 'Plan Curricular 2026',
        ]);

        $this->assertDatabaseHas('academic_plans', [
            'academic_program_id' => $program2->id,
            'name' => 'Plan Curricular 2026',
        ]);

        $this->assertDatabaseCount('academic_plans', 2);
    }

    public function test_no_permite_crear_plan_sin_campos_obligatorios(): void
    {
        $response = $this->postJson('/api/academic-plans', [
            'is_active' => true,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'academic_program_id',
                'name',
                'version',
            ]);

        $this->assertDatabaseCount('academic_plans', 0);
    }

    public function test_no_permite_crear_plan_con_programa_inexistente(): void
    {
        $response = $this->postJson('/api/academic-plans', [
            'academic_program_id' => 99999,
            'name' => 'Plan Curricular 2026',
            'version' => '1.0',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'academic_program_id',
            ]);

        $this->assertDatabaseCount('academic_plans', 0);
    }

    public function test_no_permite_fecha_fin_anterior_a_fecha_inicio(): void
    {
        $institution = Institution::create([
            'name' => 'Instituto Tecnológico Demo',
            'code' => 'ITD',
            'type' => 'institute',
        ]);

        $program = AcademicProgram::create([
            'institution_id' => $institution->id,
            'name' => 'Desarrollo de Software',
            'code' => 'DS',
            'description' => 'Carrera de programación',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/academic-plans', [
            'academic_program_id' => $program->id,
            'name' => 'Plan Curricular 2026',
            'version' => '1.0',
            'start_date' => '2026-08-01',
            'end_date' => '2026-01-01',
            'is_active' => true,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'end_date',
            ]);

        $this->assertDatabaseCount('academic_plans', 0);
    }

    public function test_puede_consultar_plan_academico_con_su_programa(): void
    {
        $institution = Institution::create([
            'name' => 'Instituto Tecnológico Demo',
            'code' => 'ITD',
            'type' => 'institute',
        ]);

        $program = AcademicProgram::create([
            'institution_id' => $institution->id,
            'name' => 'Desarrollo de Software',
            'code' => 'DS',
            'description' => 'Carrera de programación',
            'is_active' => true,
        ]);

        $created = $this->postJson('/api/academic-plans', [
            'academic_program_id' => $program->id,
            'name' => 'Plan Curricular 2026',
            'version' => '1.0',
            'is_active' => true,
        ]);

        $created->assertStatus(201);

        $planId = $created->json('data.id');

        $response = $this->getJson(
            "/api/academic-plans/{$planId}"
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $planId)
            ->assertJsonPath('data.name', 'Plan Curricular 2026')
            ->assertJsonPath('data.version', '1.0')
            ->assertJsonPath('data.academic_program_id', $program->id)
            ->assertJsonPath('data.academic_program.id', $program->id)
            ->assertJsonPath(
                'data.academic_program.name',
                'Desarrollo de Software'
            )
            ->assertJsonPath('data.academic_program.code', 'DS');

        $this->assertDatabaseCount('academic_plans', 1);
    }

    public function test_puede_listar_planes_academicos_con_sus_programas(): void
    {
        $institution = Institution::create([
            'name' => 'Instituto Tecnológico Demo',
            'code' => 'ITD',
            'type' => 'institute',
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

        $this->postJson('/api/academic-plans', [
            'academic_program_id' => $program1->id,
            'name' => 'Plan Curricular 2026',
            'version' => '1.0',
            'is_active' => true,
        ])->assertStatus(201);

        $this->postJson('/api/academic-plans', [
            'academic_program_id' => $program2->id,
            'name' => 'Plan Curricular 2027',
            'version' => '1.0',
            'is_active' => true,
        ])->assertStatus(201);

        $response = $this->getJson('/api/academic-plans');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment([
                'name' => 'Plan Curricular 2026',
            ])
            ->assertJsonFragment([
                'name' => 'Plan Curricular 2027',
            ]);

        $plans = $response->json('data');

        $expectedPrograms = [
            $program1->id => 'Desarrollo de Software',
            $program2->id => 'Administración de Empresas',
        ];

        foreach ($plans as $plan) {
            $this->assertArrayHasKey('academic_program', $plan);

            $this->assertEquals(
                $plan['academic_program_id'],
                $plan['academic_program']['id']
            );

            $this->assertEquals(
                $expectedPrograms[$plan['academic_program_id']],
                $plan['academic_program']['name']
            );
        }

        $this->assertDatabaseCount('academic_plans', 2);
    }

    public function test_puede_actualizar_un_plan_academico(): void
    {
        $institution = Institution::create([
            'name' => 'Instituto Tecnológico Demo',
            'code' => 'ITD',
            'type' => 'institute',
        ]);

        $program = AcademicProgram::create([
            'institution_id' => $institution->id,
            'name' => 'Desarrollo de Software',
            'code' => 'DS',
            'description' => 'Carrera de programación',
            'is_active' => true,
        ]);

        $created = $this->postJson('/api/academic-plans', [
            'academic_program_id' => $program->id,
            'name' => 'Plan Curricular 2026',
            'version' => '1.0',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);

        $created->assertStatus(201);

        $planId = $created->json('data.id');

        $response = $this->putJson(
            "/api/academic-plans/{$planId}",
            [
                'academic_program_id' => $program->id,
                'name' => 'Plan Curricular 2027',
                'version' => '2.0',
                'start_date' => '2027-01-01',
                'end_date' => '2027-12-31',
                'is_active' => false,
            ]
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $planId)
            ->assertJsonPath('data.academic_program_id', $program->id)
            ->assertJsonPath('data.name', 'Plan Curricular 2027')
            ->assertJsonPath('data.version', '2.0')
            ->assertJsonPath('data.is_active', false);

        $this->assertSame(
            '2027-01-01',
            substr($response->json('data.start_date'), 0, 10)
        );

        $this->assertSame(
            '2027-12-31',
            substr($response->json('data.end_date'), 0, 10)
        );

        $this->assertDatabaseHas('academic_plans', [
            'id' => $planId,
            'academic_program_id' => $program->id,
            'name' => 'Plan Curricular 2027',
            'version' => '2.0',
            'is_active' => 0,
        ]);

        $plan = \Illuminate\Support\Facades\DB::table('academic_plans')
            ->find($planId);

        $this->assertNotNull($plan);

        $this->assertSame(
            '2027-01-01',
            substr($plan->start_date, 0, 10)
        );

        $this->assertSame(
            '2027-12-31',
            substr($plan->end_date, 0, 10)
        );

        $this->assertDatabaseCount('academic_plans', 1);
    }

    public function test_no_permite_nombre_duplicado_al_actualizar_plan(): void
    {
        $institution = Institution::create([
            'name' => 'Instituto Tecnológico Demo',
            'code' => 'ITD',
            'type' => 'institute',
        ]);

        $program = AcademicProgram::create([
            'institution_id' => $institution->id,
            'name' => 'Desarrollo de Software',
            'code' => 'DS',
            'description' => 'Carrera de programación',
            'is_active' => true,
        ]);

        $plan1 = $this->postJson('/api/academic-plans', [
            'academic_program_id' => $program->id,
            'name' => 'Plan Curricular 2026',
            'version' => '1.0',
            'is_active' => true,
        ]);

        $plan1->assertStatus(201);

        $plan2 = $this->postJson('/api/academic-plans', [
            'academic_program_id' => $program->id,
            'name' => 'Plan Curricular 2027',
            'version' => '1.0',
            'is_active' => true,
        ]);

        $plan2->assertStatus(201);

        $plan1Id = $plan1->json('data.id');
        $plan2Id = $plan2->json('data.id');

        $response = $this->putJson(
            "/api/academic-plans/{$plan2Id}",
            [
                'academic_program_id' => $program->id,
                'name' => 'Plan Curricular 2026',
                'version' => '2.0',
                'is_active' => false,
            ]
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        $this->assertDatabaseHas('academic_plans', [
            'id' => $plan1Id,
            'name' => 'Plan Curricular 2026',
            'version' => '1.0',
        ]);

        $this->assertDatabaseHas('academic_plans', [
            'id' => $plan2Id,
            'name' => 'Plan Curricular 2027',
            'version' => '1.0',
            'is_active' => 1,
        ]);

        $this->assertDatabaseCount('academic_plans', 2);
    }


    public function test_permite_actualizar_plan_conservando_su_mismo_nombre(): void
    {
        $institution = Institution::create([
            'name' => 'Instituto Tecnológico Demo',
            'code' => 'ITD',
            'type' => 'institute',
        ]);

        $program = AcademicProgram::create([
            'institution_id' => $institution->id,
            'name' => 'Desarrollo de Software',
            'code' => 'DS',
            'description' => 'Carrera de programación',
            'is_active' => true,
        ]);

        $created = $this->postJson('/api/academic-plans', [
            'academic_program_id' => $program->id,
            'name' => 'Plan Curricular 2026',
            'version' => '1.0',
            'is_active' => true,
        ]);

        $created->assertStatus(201);

        $planId = $created->json('data.id');

        $response = $this->putJson(
            "/api/academic-plans/{$planId}",
            [
                'academic_program_id' => $program->id,
                'name' => 'Plan Curricular 2026',
                'version' => '1.1',
                'is_active' => false,
            ]
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $planId)
            ->assertJsonPath('data.name', 'Plan Curricular 2026')
            ->assertJsonPath('data.version', '1.1')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('academic_plans', [
            'id' => $planId,
            'academic_program_id' => $program->id,
            'name' => 'Plan Curricular 2026',
            'version' => '1.1',
            'is_active' => 0,
        ]);

        $this->assertDatabaseCount('academic_plans', 1);
    }
    
public function test_no_permite_actualizar_plan_sin_campos_obligatorios(): void
{
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD',
        'type' => 'institute',
    ]);

    $program = AcademicProgram::create([
        'institution_id' => $institution->id,
        'name' => 'Desarrollo de Software',
        'code' => 'DS',
        'description' => 'Carrera de programación',
        'is_active' => true,
    ]);

    $created = $this->postJson('/api/academic-plans', [
        'academic_program_id' => $program->id,
        'name' => 'Plan Curricular 2026',
        'version' => '1.0',
        'is_active' => true,
    ]);

    $created->assertStatus(201);

    $planId = $created->json('data.id');

    $response = $this->putJson(
        "/api/academic-plans/{$planId}",
        [
            'is_active' => false,
        ]
    );

    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'academic_program_id',
            'name',
            'version',
        ]);

    $this->assertDatabaseHas('academic_plans', [
        'id' => $planId,
        'academic_program_id' => $program->id,
        'name' => 'Plan Curricular 2026',
        'version' => '1.0',
        'is_active' => 1,
    ]);

    $this->assertDatabaseCount('academic_plans', 1);
}

public function test_no_permite_actualizar_plan_con_programa_inexistente(): void
{
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD',
        'type' => 'institute',
    ]);

    $program = AcademicProgram::create([
        'institution_id' => $institution->id,
        'name' => 'Desarrollo de Software',
        'code' => 'DS',
        'description' => 'Carrera de programación',
        'is_active' => true,
    ]);

    $created = $this->postJson('/api/academic-plans', [
        'academic_program_id' => $program->id,
        'name' => 'Plan Curricular 2026',
        'version' => '1.0',
        'is_active' => true,
    ]);

    $created->assertStatus(201);

    $planId = $created->json('data.id');

    $response = $this->putJson(
        "/api/academic-plans/{$planId}",
        [
            'academic_program_id' => 99999,
            'name' => 'Plan Curricular 2027',
            'version' => '2.0',
            'is_active' => false,
        ]
    );

    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'academic_program_id',
        ]);

    $this->assertDatabaseHas('academic_plans', [
        'id' => $planId,
        'academic_program_id' => $program->id,
        'name' => 'Plan Curricular 2026',
        'version' => '1.0',
        'is_active' => 1,
    ]);

    $this->assertDatabaseCount('academic_plans', 1);
}

public function test_no_permite_fecha_fin_anterior_al_inicio_al_actualizar_plan(): void
{
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD',
        'type' => 'institute',
    ]);

    $program = AcademicProgram::create([
        'institution_id' => $institution->id,
        'name' => 'Desarrollo de Software',
        'code' => 'DS',
        'description' => 'Carrera de programación',
        'is_active' => true,
    ]);

    $created = $this->postJson('/api/academic-plans', [
        'academic_program_id' => $program->id,
        'name' => 'Plan Curricular 2026',
        'version' => '1.0',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_active' => true,
    ]);

    $created->assertStatus(201);

    $planId = $created->json('data.id');

    $response = $this->putJson(
        "/api/academic-plans/{$planId}",
        [
            'academic_program_id' => $program->id,
            'name' => 'Plan Curricular 2026',
            'version' => '2.0',
            'start_date' => '2026-08-01',
            'end_date' => '2026-01-01',
            'is_active' => false,
        ]
    );

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['end_date']);

    $this->assertDatabaseHas('academic_plans', [
        'id' => $planId,
        'academic_program_id' => $program->id,
        'name' => 'Plan Curricular 2026',
        'version' => '1.0',
        'is_active' => 1,
    ]);

    $plan = DB::table('academic_plans')->find($planId);

    $this->assertNotNull($plan);

    $this->assertSame(
        '2026-01-01',
        substr($plan->start_date, 0, 10)
    );

    $this->assertSame(
        '2026-12-31',
        substr($plan->end_date, 0, 10)
    );

    $this->assertDatabaseCount('academic_plans', 1);
}

public function test_no_permite_consultar_plan_academico_inexistente(): void
{
    $response = $this->getJson('/api/academic-plans/99999');

    $response->assertStatus(404);

    $this->assertDatabaseCount('academic_plans', 0);
}

public function test_no_permite_actualizar_plan_academico_inexistente(): void
{
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD',
        'type' => 'institute',
    ]);

    $program = AcademicProgram::create([
        'institution_id' => $institution->id,
        'name' => 'Desarrollo de Software',
        'code' => 'DS',
        'description' => 'Carrera de programación',
        'is_active' => true,
    ]);

    $response = $this->putJson('/api/academic-plans/99999', [
        'academic_program_id' => $program->id,
        'name' => 'Plan Curricular 2026',
        'version' => '2.0',
        'is_active' => true,
    ]);

    $response->assertStatus(404);

    $this->assertDatabaseCount('academic_plans', 0);
}

public function test_puede_eliminar_un_plan_academico(): void
{
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD',
        'type' => 'institute',
    ]);

    $program = AcademicProgram::create([
        'institution_id' => $institution->id,
        'name' => 'Desarrollo de Software',
        'code' => 'DS',
        'description' => 'Carrera de programación',
        'is_active' => true,
    ]);

    $created = $this->postJson('/api/academic-plans', [
        'academic_program_id' => $program->id,
        'name' => 'Plan Curricular 2026',
        'version' => '1.0',
        'is_active' => true,
    ]);

    $created->assertStatus(201);

    $planId = $created->json('data.id');

    $this->assertDatabaseHas('academic_plans', [
        'id' => $planId,
        'name' => 'Plan Curricular 2026',
    ]);

    $response = $this->deleteJson(
        "/api/academic-plans/{$planId}"
    );

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath(
            'message',
            'Plan académico eliminado correctamente'
        );

    $this->assertDatabaseMissing('academic_plans', [
        'id' => $planId,
    ]);

    $this->assertDatabaseCount('academic_plans', 0);

    // El programa académico debe conservarse.
    $this->assertDatabaseHas('academic_programs', [
        'id' => $program->id,
        'name' => 'Desarrollo de Software',
    ]);
}
public function test_no_permite_eliminar_plan_academico_inexistente(): void
{
    $response = $this->deleteJson('/api/academic-plans/99999');

    $response->assertStatus(404);

    $this->assertDatabaseCount('academic_plans', 0);
}

public function test_no_permite_eliminar_plan_con_historial_academico(): void
{
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD',
        'type' => 'institute',
    ]);

    $program = AcademicProgram::create([
        'institution_id' => $institution->id,
        'name' => 'Desarrollo de Software',
        'code' => 'DS',
        'description' => 'Carrera de programación',
        'is_active' => true,
    ]);

    $created = $this->postJson('/api/academic-plans', [
        'academic_program_id' => $program->id,
        'name' => 'Plan Curricular 2026',
        'version' => '1.0',
        'is_active' => true,
    ]);

    $created->assertStatus(201);

    $planId = $created->json('data.id');

    // Crear una persona.
    $personId = DB::table('people')->insertGetId([
        'institution_id' => $institution->id,
        'first_name' => 'Carlos',
        'last_name' => 'Ramírez',
        'document_number' => '12345678',
    ]);

    // Registrar a la persona como estudiante.
    $studentId = DB::table('students')->insertGetId([
        'person_id' => $personId,
        'student_code' => 'EST-001',
        'status' => 'active',
    ]);

    // Vincular al estudiante con el plan académico.
    $recordId = DB::table('academic_records')->insertGetId([
        'student_id' => $studentId,
        'academic_program_id' => $program->id,
        'academic_plan_id' => $planId,
        'start_date' => '2026-01-01',
        'status' => 'active',
    ]);

    // Intentar eliminar el plan.
    $response = $this->deleteJson(
        "/api/academic-plans/{$planId}"
    );

    // La API debe impedir la eliminación.
    $response->assertStatus(409)
        ->assertJsonPath('success', false);

    // El plan debe permanecer en la base de datos.
    $this->assertDatabaseHas('academic_plans', [
        'id' => $planId,
        'name' => 'Plan Curricular 2026',
    ]);

    // El historial académico tampoco debe desaparecer.
    $this->assertDatabaseHas('academic_records', [
        'id' => $recordId,
        'student_id' => $studentId,
        'academic_plan_id' => $planId,
    ]);

    $this->assertDatabaseCount('academic_plans', 1);
    $this->assertDatabaseCount('academic_records', 1);
}

public function test_no_permite_eliminar_plan_con_malla_curricular(): void
{
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD',
        'type' => 'institute',
    ]);

    $program = AcademicProgram::create([
        'institution_id' => $institution->id,
        'name' => 'Desarrollo de Software',
        'code' => 'DS',
        'description' => 'Carrera de programación',
        'is_active' => true,
    ]);

    // Crear el plan académico.
    $created = $this->postJson('/api/academic-plans', [
        'academic_program_id' => $program->id,
        'name' => 'Plan Curricular 2026',
        'version' => '1.0',
        'is_active' => true,
    ]);

    $created->assertStatus(201);

    $planId = $created->json('data.id');

    // Crear el primer nivel académico.
    $levelId = DB::table('academic_levels')->insertGetId([
        'academic_plan_id' => $planId,
        'name' => 'Primer Ciclo',
        'order' => 1,
        'is_active' => true,
    ]);

    // Crear el curso Programación I.
    $unitId = DB::table('academic_units')->insertGetId([
        'institution_id' => $institution->id,
        'name' => 'Programación I',
        'code' => 'PROG1',
        'is_active' => true,
    ]);

    // Asociar el curso al plan y al nivel.
    $itemId = DB::table('academic_curriculum_items')->insertGetId([
        'academic_plan_id' => $planId,
        'academic_level_id' => $levelId,
        'academic_unit_id' => $unitId,
        'credits' => 4,
        'hours' => 64,
        'is_required' => true,
        'position' => 1,
    ]);

    // Intentar eliminar el plan académico.
    $response = $this->deleteJson(
        "/api/academic-plans/{$planId}"
    );

    // La API debe rechazar la eliminación.
    $response->assertStatus(409)
        ->assertJsonPath('success', false);

    // El plan debe seguir existiendo.
    $this->assertDatabaseHas('academic_plans', [
        'id' => $planId,
        'name' => 'Plan Curricular 2026',
    ]);

    // El nivel académico debe conservarse.
    $this->assertDatabaseHas('academic_levels', [
        'id' => $levelId,
        'academic_plan_id' => $planId,
    ]);

    // El curso debe conservarse.
    $this->assertDatabaseHas('academic_units', [
        'id' => $unitId,
        'name' => 'Programación I',
    ]);

    // La malla curricular debe permanecer intacta.
    $this->assertDatabaseHas('academic_curriculum_items', [
        'id' => $itemId,
        'academic_plan_id' => $planId,
        'academic_level_id' => $levelId,
        'academic_unit_id' => $unitId,
    ]);

    $this->assertDatabaseCount('academic_plans', 1);
    $this->assertDatabaseCount('academic_levels', 1);
    $this->assertDatabaseCount('academic_curriculum_items', 1);
}

public function test_no_permite_eliminar_plan_con_niveles_academicos(): void
{
    $institution = Institution::create([
        'name' => 'Instituto Tecnológico Demo',
        'code' => 'ITD',
        'type' => 'institute',
    ]);

    $program = AcademicProgram::create([
        'institution_id' => $institution->id,
        'name' => 'Desarrollo de Software',
        'code' => 'DS',
        'description' => 'Carrera de programación',
        'is_active' => true,
    ]);

    // Crear el plan académico.
    $created = $this->postJson('/api/academic-plans', [
        'academic_program_id' => $program->id,
        'name' => 'Plan Curricular 2026',
        'version' => '1.0',
        'is_active' => true,
    ]);

    $created->assertStatus(201);

    $planId = $created->json('data.id');

    // Crear un nivel sin cursos asociados.
    $levelId = DB::table('academic_levels')->insertGetId([
        'academic_plan_id' => $planId,
        'name' => 'Primer Ciclo',
        'order' => 1,
        'is_active' => true,
    ]);

    // Confirmar que todavía no existe una malla curricular.
    $this->assertDatabaseCount('academic_curriculum_items', 0);

    // Intentar eliminar el plan.
    $response = $this->deleteJson(
        "/api/academic-plans/{$planId}"
    );

    // La eliminación debe ser rechazada.
    $response->assertStatus(409)
        ->assertJsonPath('success', false);

    // El plan debe permanecer.
    $this->assertDatabaseHas('academic_plans', [
        'id' => $planId,
        'name' => 'Plan Curricular 2026',
    ]);

    // El nivel académico debe conservarse.
    $this->assertDatabaseHas('academic_levels', [
        'id' => $levelId,
        'academic_plan_id' => $planId,
        'name' => 'Primer Ciclo',
        'order' => 1,
    ]);

    $this->assertDatabaseCount('academic_plans', 1);
    $this->assertDatabaseCount('academic_levels', 1);
    $this->assertDatabaseCount('academic_curriculum_items', 0);
}

}
