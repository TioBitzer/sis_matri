<?php

namespace Tests\Feature;

use App\Models\AcademicLevel;
use App\Models\AcademicPlan;
use App\Models\AcademicProgram;
use App\Models\Institution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AcademicCurriculumItemTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Crea institución, programa, plan, nivel y unidad para cada prueba.
     * No depende de datos sembrados en la base de datos real.
     */
    private function makeContext(string $suffix = 'A'): array
    {
        $institution = Institution::create([
            'name' => 'Instituto Demo ' . $suffix,
            'code' => 'IT' . $suffix,
            'type' => 'institute',
        ]);

        $program = AcademicProgram::create([
            'institution_id' => $institution->id,
            'name' => 'Desarrollo de Software ' . $suffix,
            'code' => 'DS' . $suffix,
            'description' => 'Carrera de programación',
            'is_active' => true,
        ]);

        $plan = AcademicPlan::create([
            'academic_program_id' => $program->id,
            'name' => 'Plan Curricular ' . $suffix,
            'version' => '1.0',
            'is_active' => true,
        ]);

        $level = AcademicLevel::create([
            'academic_plan_id' => $plan->id,
            'name' => 'Primer Ciclo',
            'order' => 1,
            'is_active' => true,
        ]);

        $unitId = $this->makeUnit($institution->id, 'PROG' . $suffix);

        return compact('institution', 'program', 'plan', 'level', 'unitId');
    }

    private function makeUnit(int $institutionId, string $code): int
    {
        return DB::table('academic_units')->insertGetId([
            'institution_id' => $institutionId,
            'name' => 'Curso ' . $code,
            'code' => $code,
            'credits' => 4,
            'hours' => 64,
            'is_active' => true,
        ]);
    }

    private function makeLevel(AcademicPlan $plan, string $name, int $order): AcademicLevel
    {
        return AcademicLevel::create([
            'academic_plan_id' => $plan->id,
            'name' => $name,
            'order' => $order,
            'is_active' => true,
        ]);
    }

    private function payload(array $context, array $changes = []): array
    {
        return array_replace([
            'academic_plan_id' => $context['plan']->id,
            'academic_level_id' => $context['level']->id,
            'academic_unit_id' => $context['unitId'],
            'credits' => 4,
            'hours' => 64,
            'is_required' => true,
            'position' => 1,
        ], $changes);
    }

    private function createItem(array $context, array $changes = []): int
    {
        $response = $this->postJson(
            '/api/academic-curriculum-items',
            $this->payload($context, $changes)
        );

        $response->assertStatus(201);

        return (int) $response->json('data.id');
    }

    public function test_puede_crear_elemento_curricular_con_todos_sus_campos(): void
    {
        $context = $this->makeContext();
        $response = $this->postJson('/api/academic-curriculum-items', $this->payload($context));

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.academic_plan_id', $context['plan']->id)
            ->assertJsonPath('data.academic_level_id', $context['level']->id)
            ->assertJsonPath('data.academic_unit_id', $context['unitId'])
            ->assertJsonPath('data.credits', 4)
            ->assertJsonPath('data.hours', 64)
            ->assertJsonPath('data.is_required', true)
            ->assertJsonPath('data.position', 1)
            ->assertJsonPath('data.academic_plan.id', $context['plan']->id)
            ->assertJsonPath('data.academic_level.id', $context['level']->id)
            ->assertJsonPath('data.academic_unit.id', $context['unitId']);

        $this->assertDatabaseHas('academic_curriculum_items', [
            'academic_plan_id' => $context['plan']->id,
            'academic_level_id' => $context['level']->id,
            'academic_unit_id' => $context['unitId'],
            'credits' => 4,
            'hours' => 64,
            'is_required' => 1,
            'position' => 1,
        ]);

        $this->assertDatabaseCount('academic_curriculum_items', 1);
    }

    public function test_copia_creditos_y_horas_de_la_unidad_si_no_se_envian(): void
    {
        $context = $this->makeContext();
        $payload = $this->payload($context);
        unset($payload['credits'], $payload['hours']);

        $response = $this->postJson('/api/academic-curriculum-items', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.credits', 4)
            ->assertJsonPath('data.hours', 64);

        $this->assertDatabaseHas('academic_curriculum_items', [
            'academic_unit_id' => $context['unitId'],
            'credits' => 4,
            'hours' => 64,
        ]);
    }

    public function test_crea_elemento_obligatorio_por_defecto(): void
    {
        $context = $this->makeContext();
        $payload = $this->payload($context);
        unset($payload['is_required']);

        $response = $this->postJson('/api/academic-curriculum-items', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_required', true);

        $this->assertDatabaseHas('academic_curriculum_items', [
            'academic_unit_id' => $context['unitId'],
            'is_required' => 1,
        ]);
    }

    public function test_no_permite_unidad_duplicada_en_el_mismo_plan_y_nivel(): void
    {
        $context = $this->makeContext();
        $this->createItem($context);

        $response = $this->postJson(
            '/api/academic-curriculum-items',
            $this->payload($context, ['position' => 2])
        );

        $response->assertStatus(422)->assertJsonValidationErrors(['academic_unit_id']);
        $this->assertDatabaseCount('academic_curriculum_items', 1);
    }

    public function test_permite_la_misma_unidad_en_niveles_diferentes(): void
    {
        $context = $this->makeContext();
        $this->createItem($context);
        $anotherLevel = $this->makeLevel($context['plan'], 'Segundo Ciclo', 2);

        $this->postJson('/api/academic-curriculum-items', $this->payload($context, [
            'academic_level_id' => $anotherLevel->id,
        ]))->assertStatus(201);

        $this->assertDatabaseCount('academic_curriculum_items', 2);
    }

    public function test_permite_la_misma_unidad_en_planes_diferentes_de_una_institucion(): void
    {
        $context = $this->makeContext();
        $this->createItem($context);

        $anotherPlan = AcademicPlan::create([
            'academic_program_id' => $context['program']->id,
            'name' => 'Plan Curricular 2027',
            'version' => '2.0',
            'is_active' => true,
        ]);
        $anotherLevel = $this->makeLevel($anotherPlan, 'Primer Ciclo', 1);

        $this->postJson('/api/academic-curriculum-items', $this->payload($context, [
            'academic_plan_id' => $anotherPlan->id,
            'academic_level_id' => $anotherLevel->id,
        ]))->assertStatus(201);

        $this->assertDatabaseCount('academic_curriculum_items', 2);
    }

    public function test_no_permite_crear_sin_campos_obligatorios(): void
    {
        $this->postJson('/api/academic-curriculum-items', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'academic_plan_id',
                'academic_level_id',
                'academic_unit_id',
            ]);

        $this->assertDatabaseCount('academic_curriculum_items', 0);
    }

    public function test_no_permite_crear_con_plan_inexistente(): void
    {
        $context = $this->makeContext();
        $this->postJson('/api/academic-curriculum-items', $this->payload($context, [
            'academic_plan_id' => 99999,
        ]))->assertStatus(422)->assertJsonValidationErrors(['academic_plan_id']);
        $this->assertDatabaseCount('academic_curriculum_items', 0);
    }

    public function test_no_permite_crear_con_nivel_inexistente(): void
    {
        $context = $this->makeContext();
        $this->postJson('/api/academic-curriculum-items', $this->payload($context, [
            'academic_level_id' => 99999,
        ]))->assertStatus(422)->assertJsonValidationErrors(['academic_level_id']);
        $this->assertDatabaseCount('academic_curriculum_items', 0);
    }

    public function test_no_permite_crear_con_unidad_inexistente(): void
    {
        $context = $this->makeContext();
        $this->postJson('/api/academic-curriculum-items', $this->payload($context, [
            'academic_unit_id' => 99999,
        ]))->assertStatus(422)->assertJsonValidationErrors(['academic_unit_id']);
        $this->assertDatabaseCount('academic_curriculum_items', 0);
    }

    public function test_no_permite_asociar_nivel_de_otro_plan(): void
    {
        $context = $this->makeContext('A');
        $other = $this->makeContext('B');

        $this->postJson('/api/academic-curriculum-items', $this->payload($context, [
            'academic_level_id' => $other['level']->id,
        ]))->assertStatus(422)->assertJsonValidationErrors(['academic_level_id']);
        $this->assertDatabaseCount('academic_curriculum_items', 0);
    }

    public function test_no_permite_curso_de_otra_institucion(): void
    {
        $context = $this->makeContext('A');
        $other = $this->makeContext('B');

        $this->postJson('/api/academic-curriculum-items', $this->payload($context, [
            'academic_unit_id' => $other['unitId'],
        ]))->assertStatus(422)->assertJsonValidationErrors(['academic_unit_id']);
        $this->assertDatabaseCount('academic_curriculum_items', 0);
    }

    public function test_no_permite_creditos_negativos(): void
    {
        $context = $this->makeContext();
        $this->postJson('/api/academic-curriculum-items', $this->payload($context, [
            'credits' => -1,
        ]))->assertStatus(422)->assertJsonValidationErrors(['credits']);
        $this->assertDatabaseCount('academic_curriculum_items', 0);
    }

    public function test_no_permite_horas_negativas(): void
    {
        $context = $this->makeContext();
        $this->postJson('/api/academic-curriculum-items', $this->payload($context, [
            'hours' => -1,
        ]))->assertStatus(422)->assertJsonValidationErrors(['hours']);
        $this->assertDatabaseCount('academic_curriculum_items', 0);
    }

    public function test_no_permite_creditos_que_no_sean_enteros(): void
    {
        $context = $this->makeContext();
        $this->postJson('/api/academic-curriculum-items', $this->payload($context, [
            'credits' => 2.5,
        ]))->assertStatus(422)->assertJsonValidationErrors(['credits']);
    }

    public function test_no_permite_horas_que_no_sean_enteras(): void
    {
        $context = $this->makeContext();
        $this->postJson('/api/academic-curriculum-items', $this->payload($context, [
            'hours' => 3.5,
        ]))->assertStatus(422)->assertJsonValidationErrors(['hours']);
    }

    public function test_no_permite_posicion_menor_a_uno(): void
    {
        $context = $this->makeContext();
        $this->postJson('/api/academic-curriculum-items', $this->payload($context, [
            'position' => 0,
        ]))->assertStatus(422)->assertJsonValidationErrors(['position']);
    }

    public function test_no_permite_posicion_que_no_sea_entera(): void
    {
        $context = $this->makeContext();
        $this->postJson('/api/academic-curriculum-items', $this->payload($context, [
            'position' => 1.7,
        ]))->assertStatus(422)->assertJsonValidationErrors(['position']);
    }

    public function test_no_permite_estado_de_obligatoriedad_invalido(): void
    {
        $context = $this->makeContext();
        $this->postJson('/api/academic-curriculum-items', $this->payload($context, [
            'is_required' => 'no-es-booleano',
        ]))->assertStatus(422)->assertJsonValidationErrors(['is_required']);
    }

    public function test_puede_consultar_item_con_sus_tres_relaciones(): void
    {
        $context = $this->makeContext();
        $itemId = $this->createItem($context);

        $this->getJson("/api/academic-curriculum-items/{$itemId}")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $itemId)
            ->assertJsonPath('data.academic_plan.id', $context['plan']->id)
            ->assertJsonPath('data.academic_level.id', $context['level']->id)
            ->assertJsonPath('data.academic_unit.id', $context['unitId']);
    }

    public function test_puede_listar_items_con_sus_relaciones(): void
    {
        $context = $this->makeContext();
        $this->createItem($context);
        $anotherUnitId = $this->makeUnit($context['institution']->id, 'BDA');
        $this->createItem($context, ['academic_unit_id' => $anotherUnitId, 'position' => 2]);

        $response = $this->getJson('/api/academic-curriculum-items');
        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');

        foreach ($response->json('data') as $item) {
            $this->assertArrayHasKey('academic_plan', $item);
            $this->assertArrayHasKey('academic_level', $item);
            $this->assertArrayHasKey('academic_unit', $item);
        }
    }

    public function test_puede_actualizar_elemento_curricular(): void
    {
        $context = $this->makeContext();
        $itemId = $this->createItem($context);

        $this->putJson("/api/academic-curriculum-items/{$itemId}", $this->payload($context, [
            'credits' => 5,
            'hours' => 80,
            'is_required' => false,
            'position' => 2,
        ]))->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $itemId)
            ->assertJsonPath('data.credits', 5)
            ->assertJsonPath('data.hours', 80)
            ->assertJsonPath('data.is_required', false)
            ->assertJsonPath('data.position', 2);

        $this->assertDatabaseHas('academic_curriculum_items', [
            'id' => $itemId,
            'credits' => 5,
            'hours' => 80,
            'is_required' => 0,
            'position' => 2,
        ]);
        $this->assertDatabaseCount('academic_curriculum_items', 1);
    }

    public function test_permite_actualizar_conservando_plan_nivel_y_unidad(): void
    {
        $context = $this->makeContext();
        $itemId = $this->createItem($context);

        $this->putJson("/api/academic-curriculum-items/{$itemId}", $this->payload($context))
            ->assertStatus(200)
            ->assertJsonPath('data.id', $itemId);

        $this->assertDatabaseCount('academic_curriculum_items', 1);
    }

    public function test_no_permite_duplicar_unidad_al_actualizar(): void
    {
        $context = $this->makeContext();
        $originalId = $this->createItem($context);
        $anotherUnitId = $this->makeUnit($context['institution']->id, 'BDA');
        $secondId = $this->createItem($context, ['academic_unit_id' => $anotherUnitId, 'position' => 2]);

        $this->putJson("/api/academic-curriculum-items/{$secondId}", $this->payload($context))
            ->assertStatus(422)->assertJsonValidationErrors(['academic_unit_id']);

        $this->assertDatabaseHas('academic_curriculum_items', [
            'id' => $originalId,
            'academic_unit_id' => $context['unitId'],
        ]);
        $this->assertDatabaseHas('academic_curriculum_items', [
            'id' => $secondId,
            'academic_unit_id' => $anotherUnitId,
        ]);
    }

    public function test_no_permite_asignar_nivel_de_otro_plan_al_actualizar(): void
    {
        $context = $this->makeContext('A');
        $other = $this->makeContext('B');
        $itemId = $this->createItem($context);

        $this->putJson("/api/academic-curriculum-items/{$itemId}", $this->payload($context, [
            'academic_level_id' => $other['level']->id,
        ]))->assertStatus(422)->assertJsonValidationErrors(['academic_level_id']);

        $this->assertDatabaseHas('academic_curriculum_items', [
            'id' => $itemId,
            'academic_level_id' => $context['level']->id,
        ]);
    }

    public function test_no_permite_curso_de_otra_institucion_al_actualizar(): void
    {
        $context = $this->makeContext('A');
        $other = $this->makeContext('B');
        $itemId = $this->createItem($context);

        $this->putJson("/api/academic-curriculum-items/{$itemId}", $this->payload($context, [
            'academic_unit_id' => $other['unitId'],
        ]))->assertStatus(422)->assertJsonValidationErrors(['academic_unit_id']);

        $this->assertDatabaseHas('academic_curriculum_items', [
            'id' => $itemId,
            'academic_unit_id' => $context['unitId'],
        ]);
    }

    public function test_no_permite_valores_negativos_al_actualizar(): void
    {
        $context = $this->makeContext();
        $itemId = $this->createItem($context);

        $this->putJson("/api/academic-curriculum-items/{$itemId}", $this->payload($context, [
            'credits' => -1,
            'hours' => -10,
            'position' => 0,
        ]))->assertStatus(422)->assertJsonValidationErrors(['credits', 'hours', 'position']);

        $this->assertDatabaseHas('academic_curriculum_items', [
            'id' => $itemId,
            'credits' => 4,
            'hours' => 64,
            'position' => 1,
        ]);
    }

    public function test_no_permite_actualizar_sin_campos_obligatorios(): void
    {
        $context = $this->makeContext();
        $itemId = $this->createItem($context);

        $this->putJson("/api/academic-curriculum-items/{$itemId}", [
            'position' => 2,
        ])->assertStatus(422)->assertJsonValidationErrors([
            'academic_plan_id',
            'academic_level_id',
            'academic_unit_id',
        ]);

        $this->assertDatabaseHas('academic_curriculum_items', [
            'id' => $itemId,
            'position' => 1,
        ]);
    }

    public function test_puede_cambiar_item_a_otro_plan_de_la_misma_institucion(): void
    {
        $context = $this->makeContext();
        $itemId = $this->createItem($context);
        $anotherPlan = AcademicPlan::create([
            'academic_program_id' => $context['program']->id,
            'name' => 'Plan Curricular 2027',
            'version' => '2.0',
            'is_active' => true,
        ]);
        $anotherLevel = $this->makeLevel($anotherPlan, 'Primer Ciclo', 1);

        $this->putJson("/api/academic-curriculum-items/{$itemId}", $this->payload($context, [
            'academic_plan_id' => $anotherPlan->id,
            'academic_level_id' => $anotherLevel->id,
        ]))->assertStatus(200)
            ->assertJsonPath('data.academic_plan_id', $anotherPlan->id)
            ->assertJsonPath('data.academic_level_id', $anotherLevel->id);

        $this->assertDatabaseCount('academic_curriculum_items', 1);
    }

    public function test_devuelve_404_al_consultar_item_inexistente(): void
    {
        $this->getJson('/api/academic-curriculum-items/99999')->assertStatus(404);
    }

    public function test_devuelve_404_al_actualizar_item_inexistente(): void
    {
        $context = $this->makeContext();
        $this->putJson('/api/academic-curriculum-items/99999', $this->payload($context))
            ->assertStatus(404);
        $this->assertDatabaseCount('academic_curriculum_items', 0);
    }

    public function test_puede_eliminar_item_sin_eliminar_plan_nivel_ni_curso(): void
    {
        $context = $this->makeContext();
        $itemId = $this->createItem($context);

        $this->deleteJson("/api/academic-curriculum-items/{$itemId}")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Elemento curricular eliminado correctamente.');

        $this->assertDatabaseMissing('academic_curriculum_items', ['id' => $itemId]);
        $this->assertDatabaseHas('academic_plans', ['id' => $context['plan']->id]);
        $this->assertDatabaseHas('academic_levels', ['id' => $context['level']->id]);
        $this->assertDatabaseHas('academic_units', ['id' => $context['unitId']]);
    }

    public function test_devuelve_404_al_eliminar_item_inexistente(): void
    {
        $this->deleteJson('/api/academic-curriculum-items/99999')->assertStatus(404);
        $this->assertDatabaseCount('academic_curriculum_items', 0);
    }
}
