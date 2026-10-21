<?php

namespace Tests\Feature;

use App\Models\AcademicLevel;
use App\Models\AcademicPlan;
use App\Models\AcademicProgram;
use App\Models\Institution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AcademicLevelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Crea las dependencias reales de un nivel sin repetir la preparación
     * de institución, programa y plan en cada prueba.
     */
    private function createPlan(
        string $planName = 'Plan Curricular 2026',
        ?AcademicProgram $program = null
    ): AcademicPlan {
        if ($program === null) {
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
        }

        return AcademicPlan::create([
            'academic_program_id' => $program->id,
            'name' => $planName,
            'version' => '1.0',
            'is_active' => true,
        ]);
    }

    private function createLevel(
        AcademicPlan $plan,
        string $name = 'Primer Ciclo',
        int $order = 1
    ): AcademicLevel {
        return AcademicLevel::create([
            'academic_plan_id' => $plan->id,
            'name' => $name,
            'order' => $order,
            'is_active' => true,
        ]);
    }

    public function test_puede_crear_nivel_academico(): void
    {
        $plan = $this->createPlan();

        $response = $this->postJson('/api/academic-levels', [
            'academic_plan_id' => $plan->id,
            'name' => 'Primer Ciclo',
            'order' => 1,
            'is_active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.academic_plan_id', $plan->id)
            ->assertJsonPath('data.name', 'Primer Ciclo')
            ->assertJsonPath('data.order', 1)
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('academic_levels', [
            'academic_plan_id' => $plan->id,
            'name' => 'Primer Ciclo',
            'order' => 1,
            'is_active' => 1,
        ]);

        $this->assertDatabaseCount('academic_levels', 1);
    }

    public function test_crea_nivel_activo_por_defecto(): void
    {
        $plan = $this->createPlan();

        $response = $this->postJson('/api/academic-levels', [
            'academic_plan_id' => $plan->id,
            'name' => 'Primer Ciclo',
            'order' => 1,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('academic_levels', [
            'academic_plan_id' => $plan->id,
            'name' => 'Primer Ciclo',
            'is_active' => 1,
        ]);
    }

    public function test_no_permite_nombres_duplicados_en_el_mismo_plan(): void
    {
        $plan = $this->createPlan();
        $this->createLevel($plan);

        $response = $this->postJson('/api/academic-levels', [
            'academic_plan_id' => $plan->id,
            'name' => 'Primer Ciclo',
            'order' => 2,
            'is_active' => true,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        $this->assertDatabaseCount('academic_levels', 1);
    }

    public function test_no_permite_orden_duplicado_en_el_mismo_plan(): void
    {
        $plan = $this->createPlan();
        $this->createLevel($plan);

        $response = $this->postJson('/api/academic-levels', [
            'academic_plan_id' => $plan->id,
            'name' => 'Segundo Ciclo',
            'order' => 1,
            'is_active' => true,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['order']);

        $this->assertDatabaseCount('academic_levels', 1);
    }

    public function test_permite_mismo_nombre_y_orden_en_planes_diferentes(): void
    {
        $plan1 = $this->createPlan();
        $plan2 = $this->createPlan('Plan Curricular 2027', $plan1->academicProgram);

        $this->postJson('/api/academic-levels', [
            'academic_plan_id' => $plan1->id,
            'name' => 'Primer Ciclo',
            'order' => 1,
        ])->assertStatus(201);

        $this->postJson('/api/academic-levels', [
            'academic_plan_id' => $plan2->id,
            'name' => 'Primer Ciclo',
            'order' => 1,
        ])->assertStatus(201);

        $this->assertDatabaseHas('academic_levels', [
            'academic_plan_id' => $plan1->id,
            'name' => 'Primer Ciclo',
            'order' => 1,
        ]);

        $this->assertDatabaseHas('academic_levels', [
            'academic_plan_id' => $plan2->id,
            'name' => 'Primer Ciclo',
            'order' => 1,
        ]);

        $this->assertDatabaseCount('academic_levels', 2);
    }

    public function test_no_permite_crear_nivel_sin_campos_obligatorios(): void
    {
        $response = $this->postJson('/api/academic-levels', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'academic_plan_id',
                'name',
                'order',
            ]);

        $this->assertDatabaseCount('academic_levels', 0);
    }

    public function test_no_permite_crear_nivel_con_plan_inexistente(): void
    {
        $response = $this->postJson('/api/academic-levels', [
            'academic_plan_id' => 99999,
            'name' => 'Primer Ciclo',
            'order' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['academic_plan_id']);

        $this->assertDatabaseCount('academic_levels', 0);
    }

    public function test_no_permite_orden_menor_a_uno(): void
    {
        $plan = $this->createPlan();

        $response = $this->postJson('/api/academic-levels', [
            'academic_plan_id' => $plan->id,
            'name' => 'Primer Ciclo',
            'order' => 0,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['order']);

        $this->assertDatabaseCount('academic_levels', 0);
    }

    public function test_no_permite_orden_que_no_sea_entero(): void
    {
        $plan = $this->createPlan();

        $response = $this->postJson('/api/academic-levels', [
            'academic_plan_id' => $plan->id,
            'name' => 'Primer Ciclo',
            'order' => 'abc',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['order']);

        $this->assertDatabaseCount('academic_levels', 0);
    }

    public function test_no_permite_nombre_mayor_a_255_caracteres(): void
    {
        $plan = $this->createPlan();

        $response = $this->postJson('/api/academic-levels', [
            'academic_plan_id' => $plan->id,
            'name' => str_repeat('A', 256),
            'order' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        $this->assertDatabaseCount('academic_levels', 0);
    }

    public function test_no_permite_estado_invalido_al_crear(): void
    {
        $plan = $this->createPlan();

        $response = $this->postJson('/api/academic-levels', [
            'academic_plan_id' => $plan->id,
            'name' => 'Primer Ciclo',
            'order' => 1,
            'is_active' => 'invalido',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['is_active']);

        $this->assertDatabaseCount('academic_levels', 0);
    }

    public function test_puede_consultar_nivel_con_su_plan_academico(): void
    {
        $plan = $this->createPlan();
        $level = $this->createLevel($plan);

        $response = $this->getJson("/api/academic-levels/{$level->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $level->id)
            ->assertJsonPath('data.name', 'Primer Ciclo')
            ->assertJsonPath('data.academic_plan_id', $plan->id)
            ->assertJsonPath('data.academic_plan.id', $plan->id)
            ->assertJsonPath('data.academic_plan.name', 'Plan Curricular 2026');
    }

    public function test_puede_listar_niveles_con_sus_planes(): void
    {
        $plan1 = $this->createPlan();
        $plan2 = $this->createPlan('Plan Curricular 2027', $plan1->academicProgram);
        $level1 = $this->createLevel($plan1, 'Primer Ciclo', 1);
        $level2 = $this->createLevel($plan2, 'Segundo Ciclo', 2);

        $response = $this->getJson('/api/academic-levels');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');

        $levels = collect($response->json('data'))->keyBy('id');

        $this->assertSame($plan1->id, $levels[$level1->id]['academic_plan']['id']);
        $this->assertSame($plan2->id, $levels[$level2->id]['academic_plan']['id']);
        $this->assertSame('Primer Ciclo', $levels[$level1->id]['name']);
        $this->assertSame('Segundo Ciclo', $levels[$level2->id]['name']);

        $this->assertDatabaseCount('academic_levels', 2);
    }

    public function test_puede_actualizar_nivel_academico(): void
    {
        $plan = $this->createPlan();
        $level = $this->createLevel($plan);

        $response = $this->putJson("/api/academic-levels/{$level->id}", [
            'academic_plan_id' => $plan->id,
            'name' => 'Segundo Ciclo',
            'order' => 2,
            'is_active' => false,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $level->id)
            ->assertJsonPath('data.name', 'Segundo Ciclo')
            ->assertJsonPath('data.order', 2)
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('academic_levels', [
            'id' => $level->id,
            'academic_plan_id' => $plan->id,
            'name' => 'Segundo Ciclo',
            'order' => 2,
            'is_active' => 0,
        ]);

        $this->assertDatabaseCount('academic_levels', 1);
    }

    public function test_permite_actualizar_nivel_sin_cambiar_su_nombre_ni_orden(): void
    {
        $plan = $this->createPlan();
        $level = $this->createLevel($plan);

        $response = $this->putJson("/api/academic-levels/{$level->id}", [
            'academic_plan_id' => $plan->id,
            'name' => 'Primer Ciclo',
            'order' => 1,
            'is_active' => false,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Primer Ciclo')
            ->assertJsonPath('data.order', 1)
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('academic_levels', [
            'id' => $level->id,
            'name' => 'Primer Ciclo',
            'order' => 1,
            'is_active' => 0,
        ]);
    }

    public function test_no_permite_nombre_duplicado_al_actualizar(): void
    {
        $plan = $this->createPlan();
        $level1 = $this->createLevel($plan, 'Primer Ciclo', 1);
        $level2 = $this->createLevel($plan, 'Segundo Ciclo', 2);

        $response = $this->putJson("/api/academic-levels/{$level2->id}", [
            'academic_plan_id' => $plan->id,
            'name' => 'Primer Ciclo',
            'order' => 2,
            'is_active' => false,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        $this->assertDatabaseHas('academic_levels', [
            'id' => $level1->id,
            'name' => 'Primer Ciclo',
            'order' => 1,
        ]);
        $this->assertDatabaseHas('academic_levels', [
            'id' => $level2->id,
            'name' => 'Segundo Ciclo',
            'order' => 2,
            'is_active' => 1,
        ]);
        $this->assertDatabaseCount('academic_levels', 2);
    }

    public function test_no_permite_orden_duplicado_al_actualizar(): void
    {
        $plan = $this->createPlan();
        $level1 = $this->createLevel($plan, 'Primer Ciclo', 1);
        $level2 = $this->createLevel($plan, 'Segundo Ciclo', 2);

        $response = $this->putJson("/api/academic-levels/{$level2->id}", [
            'academic_plan_id' => $plan->id,
            'name' => 'Segundo Ciclo',
            'order' => 1,
            'is_active' => false,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['order']);

        $this->assertDatabaseHas('academic_levels', [
            'id' => $level1->id,
            'order' => 1,
        ]);
        $this->assertDatabaseHas('academic_levels', [
            'id' => $level2->id,
            'order' => 2,
            'is_active' => 1,
        ]);
        $this->assertDatabaseCount('academic_levels', 2);
    }

    public function test_no_permite_actualizar_sin_campos_obligatorios(): void
    {
        $plan = $this->createPlan();
        $level = $this->createLevel($plan);

        $response = $this->putJson("/api/academic-levels/{$level->id}", [
            'is_active' => false,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'academic_plan_id',
                'name',
                'order',
            ]);

        $this->assertDatabaseHas('academic_levels', [
            'id' => $level->id,
            'name' => 'Primer Ciclo',
            'order' => 1,
            'is_active' => 1,
        ]);
    }

    public function test_no_permite_actualizar_con_plan_inexistente(): void
    {
        $plan = $this->createPlan();
        $level = $this->createLevel($plan);

        $response = $this->putJson("/api/academic-levels/{$level->id}", [
            'academic_plan_id' => 99999,
            'name' => 'Segundo Ciclo',
            'order' => 2,
            'is_active' => false,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['academic_plan_id']);

        $this->assertDatabaseHas('academic_levels', [
            'id' => $level->id,
            'academic_plan_id' => $plan->id,
            'name' => 'Primer Ciclo',
            'order' => 1,
            'is_active' => 1,
        ]);
    }

    public function test_no_permite_orden_invalido_al_actualizar(): void
    {
        $plan = $this->createPlan();
        $level = $this->createLevel($plan);

        $response = $this->putJson("/api/academic-levels/{$level->id}", [
            'academic_plan_id' => $plan->id,
            'name' => 'Segundo Ciclo',
            'order' => 0,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['order']);

        $this->assertDatabaseHas('academic_levels', [
            'id' => $level->id,
            'name' => 'Primer Ciclo',
            'order' => 1,
        ]);
    }

    public function test_devuelve_404_al_consultar_nivel_inexistente(): void
    {
        $this->getJson('/api/academic-levels/99999')->assertStatus(404);

        $this->assertDatabaseCount('academic_levels', 0);
    }

    public function test_devuelve_404_al_actualizar_nivel_inexistente(): void
    {
        $plan = $this->createPlan();

        $response = $this->putJson('/api/academic-levels/99999', [
            'academic_plan_id' => $plan->id,
            'name' => 'Primer Ciclo',
            'order' => 1,
            'is_active' => true,
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseCount('academic_levels', 0);
    }

    public function test_puede_eliminar_nivel_sin_cursos_asociados(): void
    {
        $plan = $this->createPlan();
        $level = $this->createLevel($plan);

        $response = $this->deleteJson("/api/academic-levels/{$level->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Nivel académico eliminado correctamente');

        $this->assertDatabaseMissing('academic_levels', ['id' => $level->id]);
        $this->assertDatabaseHas('academic_plans', ['id' => $plan->id]);
        $this->assertDatabaseCount('academic_levels', 0);
    }

    public function test_devuelve_404_al_eliminar_nivel_inexistente(): void
    {
        $this->deleteJson('/api/academic-levels/99999')->assertStatus(404);

        $this->assertDatabaseCount('academic_levels', 0);
    }

    public function test_no_permite_eliminar_nivel_con_cursos_en_malla_curricular(): void
    {
        $plan = $this->createPlan();
        $level = $this->createLevel($plan);
        $institutionId = $plan->academicProgram->institution_id;

        $unitId = DB::table('academic_units')->insertGetId([
            'institution_id' => $institutionId,
            'name' => 'Programación I',
            'code' => 'PROG1',
            'is_active' => true,
        ]);

        $itemId = DB::table('academic_curriculum_items')->insertGetId([
            'academic_plan_id' => $plan->id,
            'academic_level_id' => $level->id,
            'academic_unit_id' => $unitId,
            'credits' => 4,
            'hours' => 64,
            'is_required' => true,
            'position' => 1,
        ]);

        $response = $this->deleteJson("/api/academic-levels/{$level->id}");

        $response->assertStatus(409)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('academic_levels', [
            'id' => $level->id,
            'academic_plan_id' => $plan->id,
        ]);

        $this->assertDatabaseHas('academic_curriculum_items', [
            'id' => $itemId,
            'academic_plan_id' => $plan->id,
            'academic_level_id' => $level->id,
            'academic_unit_id' => $unitId,
        ]);

        $this->assertDatabaseHas('academic_units', ['id' => $unitId]);
        $this->assertDatabaseCount('academic_levels', 1);
        $this->assertDatabaseCount('academic_curriculum_items', 1);
    }
}
