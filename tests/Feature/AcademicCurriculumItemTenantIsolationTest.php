<?php

namespace Tests\Feature;

use App\Models\AcademicCurriculumItem;
use App\Models\AcademicLevel;
use App\Models\AcademicPlan;
use App\Models\AcademicProgram;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AcademicCurriculumItemTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/academic-curriculum-items';

    /**
     * Institución, programa, plan, nivel y unidad totalmente coherentes.
     */
    private function context(string $suffix = 'A', bool $active = true): array
    {
        $institution = Institution::create([
            'name' => 'Institución de prueba '.$suffix,
            'code' => 'IT-'.$suffix,
            'type' => 'institute',
            'is_active' => $active,
        ]);

        $program = AcademicProgram::create([
            'institution_id' => $institution->id,
            'name' => 'Programa '.$suffix,
            'code' => 'PR-'.$suffix,
            'is_active' => true,
        ]);

        $plan = AcademicPlan::create([
            'academic_program_id' => $program->id,
            'name' => 'Plan '.$suffix,
            'version' => '1.0',
            'is_active' => true,
        ]);

        $level = AcademicLevel::create([
            'academic_plan_id' => $plan->id,
            'name' => 'Primer Ciclo',
            'order' => 1,
            'is_active' => true,
        ]);

        $unitId = DB::table('academic_units')->insertGetId([
            'institution_id' => $institution->id,
            'name' => 'Programación '.$suffix,
            'code' => 'UNIT-'.$suffix,
            'credits' => 4,
            'hours' => 64,
            'is_active' => true,
        ]);

        return compact('institution', 'program', 'plan', 'level', 'unitId');
    }

    private function userIn(Institution $institution, bool $active = true): User
    {
        $user = User::factory()->create();
        $user->institutions()->attach($institution->id, ['is_active' => $active, 'role' => 'institution_admin']);

        return $user;
    }

    private function headers(Institution $institution): array
    {
        return ['X-Institution-Id' => (string) $institution->id];
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

    private function item(array $context): AcademicCurriculumItem
    {
        return AcademicCurriculumItem::create($this->payload($context));
    }

    public function test_unauthenticated_user_cannot_list_curriculum_items(): void
    {
        $a = $this->context();
        $this->getJson(self::ENDPOINT, $this->headers($a['institution']))
            ->assertUnauthorized();
    }

    public function test_authenticated_user_must_select_an_institution(): void
    {
        $a = $this->context();
        $this->actingAs($this->userIn($a['institution']));
        $this->getJson(self::ENDPOINT)->assertUnprocessable();
    }

    public function test_user_without_membership_cannot_list_curriculum_items(): void
    {
        $a = $this->context();
        $this->actingAs(User::factory()->create());
        $this->getJson(self::ENDPOINT, $this->headers($a['institution']))
            ->assertForbidden();
    }

    public function test_inactive_membership_cannot_access_curriculum_items(): void
    {
        $a = $this->context();
        $this->actingAs($this->userIn($a['institution'], false));
        $this->getJson(self::ENDPOINT, $this->headers($a['institution']))
            ->assertForbidden();
    }

    public function test_inactive_institution_cannot_be_selected(): void
    {
        $a = $this->context('A', false);
        $this->actingAs($this->userIn($a['institution']));
        $this->getJson(self::ENDPOINT, $this->headers($a['institution']))
            ->assertForbidden();
    }

    public function test_index_returns_only_items_of_selected_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $own = $this->item($a);
        $this->item($b);
        $this->actingAs($this->userIn($a['institution']));

        $this->getJson(self::ENDPOINT, $this->headers($a['institution']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);
    }

    public function test_cannot_show_item_of_another_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $foreign = $this->item($b);
        $this->actingAs($this->userIn($a['institution']));

        $this->getJson(self::ENDPOINT.'/'.$foreign->id, $this->headers($a['institution']))
            ->assertNotFound();
    }

    public function test_cannot_create_item_in_plan_of_another_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $this->actingAs($this->userIn($a['institution']));

        $this->postJson(self::ENDPOINT, $this->payload($b), $this->headers($a['institution']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('academic_plan_id');

        $this->assertDatabaseCount('academic_curriculum_items', 0);
    }

    public function test_cannot_create_item_with_level_of_another_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $this->actingAs($this->userIn($a['institution']));

        $this->postJson(
            self::ENDPOINT,
            $this->payload($a, ['academic_level_id' => $b['level']->id]),
            $this->headers($a['institution'])
        )->assertUnprocessable()->assertJsonValidationErrors('academic_level_id');

        $this->assertDatabaseCount('academic_curriculum_items', 0);
    }

    public function test_cannot_create_item_with_unit_of_another_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $this->actingAs($this->userIn($a['institution']));

        $this->postJson(
            self::ENDPOINT,
            $this->payload($a, ['academic_unit_id' => $b['unitId']]),
            $this->headers($a['institution'])
        )->assertUnprocessable()->assertJsonValidationErrors('academic_unit_id');

        $this->assertDatabaseCount('academic_curriculum_items', 0);
    }

    public function test_cannot_update_item_of_another_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $foreign = $this->item($b);
        $this->actingAs($this->userIn($a['institution']));

        $this->putJson(self::ENDPOINT.'/'.$foreign->id, $this->payload($a), $this->headers($a['institution']))
            ->assertNotFound();

        $this->assertDatabaseHas('academic_curriculum_items', [
            'id' => $foreign->id,
            'academic_plan_id' => $b['plan']->id,
            'academic_level_id' => $b['level']->id,
            'academic_unit_id' => $b['unitId'],
        ]);
    }

    public function test_cannot_delete_item_of_another_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $foreign = $this->item($b);
        $this->actingAs($this->userIn($a['institution']));

        $this->deleteJson(self::ENDPOINT.'/'.$foreign->id, [], $this->headers($a['institution']))
            ->assertNotFound();
        $this->assertDatabaseHas('academic_curriculum_items', ['id' => $foreign->id]);
    }

    public function test_cannot_move_own_item_to_plan_and_level_of_another_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $own = $this->item($a);
        $this->actingAs($this->userIn($a['institution']));

        $this->putJson(
            self::ENDPOINT.'/'.$own->id,
            $this->payload($a, [
                'academic_plan_id' => $b['plan']->id,
                'academic_level_id' => $b['level']->id,
                'academic_unit_id' => $b['unitId'],
            ]),
            $this->headers($a['institution'])
        )->assertUnprocessable()->assertJsonValidationErrors('academic_plan_id');

        $this->assertDatabaseHas('academic_curriculum_items', [
            'id' => $own->id,
            'academic_plan_id' => $a['plan']->id,
        ]);
    }

    public function test_cannot_move_own_item_to_level_of_another_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $own = $this->item($a);
        $this->actingAs($this->userIn($a['institution']));

        $this->putJson(
            self::ENDPOINT.'/'.$own->id,
            $this->payload($a, ['academic_level_id' => $b['level']->id]),
            $this->headers($a['institution'])
        )->assertUnprocessable()->assertJsonValidationErrors('academic_level_id');

        $this->assertDatabaseHas('academic_curriculum_items', [
            'id' => $own->id,
            'academic_level_id' => $a['level']->id,
        ]);
    }

    public function test_cannot_move_own_item_to_unit_of_another_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $own = $this->item($a);
        $this->actingAs($this->userIn($a['institution']));

        $this->putJson(
            self::ENDPOINT.'/'.$own->id,
            $this->payload($a, ['academic_unit_id' => $b['unitId']]),
            $this->headers($a['institution'])
        )->assertUnprocessable()->assertJsonValidationErrors('academic_unit_id');

        $this->assertDatabaseHas('academic_curriculum_items', [
            'id' => $own->id,
            'academic_unit_id' => $a['unitId'],
        ]);
    }

    public function test_authorized_user_can_create_show_update_and_delete_own_item(): void
    {
        $a = $this->context();
        $this->actingAs($this->userIn($a['institution']));
        $headers = $this->headers($a['institution']);

        $created = $this->postJson(self::ENDPOINT, $this->payload($a), $headers)
            ->assertCreated()
            ->assertJsonPath('data.academic_plan_id', $a['plan']->id)
            ->assertJsonPath('data.academic_level_id', $a['level']->id)
            ->assertJsonPath('data.academic_unit_id', $a['unitId']);
        $id = $created->json('data.id');

        $this->getJson(self::ENDPOINT.'/'.$id, $headers)
            ->assertOk()->assertJsonPath('data.id', $id);

        $this->putJson(
            self::ENDPOINT.'/'.$id,
            $this->payload($a, ['credits' => 5, 'hours' => 80]),
            $headers
        )->assertOk()->assertJsonPath('data.credits', 5);

        $this->deleteJson(self::ENDPOINT.'/'.$id, [], $headers)->assertOk();
        $this->assertDatabaseMissing('academic_curriculum_items', ['id' => $id]);
    }

    public function test_user_with_two_memberships_still_cannot_mix_curriculum_data(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $itemA = $this->item($a);
        $itemB = $this->item($b);
        $user = $this->userIn($a['institution']);
        $user->institutions()->attach($b['institution']->id, ['is_active' => true, 'role' => 'institution_admin']);
        $this->actingAs($user);

        $this->getJson(self::ENDPOINT, $this->headers($a['institution']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $itemA->id);

        $this->getJson(self::ENDPOINT.'/'.$itemB->id, $this->headers($a['institution']))
            ->assertNotFound();

        $this->getJson(self::ENDPOINT, $this->headers($b['institution']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $itemB->id);
    }

    public function test_can_move_item_between_plans_of_same_institution(): void
    {
        $a = $this->context();
        $otherPlan = AcademicPlan::create([
            'academic_program_id' => $a['program']->id,
            'name' => 'Plan alternativo',
            'version' => '2.0',
            'is_active' => true,
        ]);
        $otherLevel = AcademicLevel::create([
            'academic_plan_id' => $otherPlan->id,
            'name' => 'Primer Ciclo',
            'order' => 1,
            'is_active' => true,
        ]);
        $own = $this->item($a);
        $this->actingAs($this->userIn($a['institution']));

        $this->putJson(
            self::ENDPOINT.'/'.$own->id,
            $this->payload($a, [
                'academic_plan_id' => $otherPlan->id,
                'academic_level_id' => $otherLevel->id,
            ]),
            $this->headers($a['institution'])
        )->assertOk()
            ->assertJsonPath('data.academic_plan_id', $otherPlan->id)
            ->assertJsonPath('data.academic_level_id', $otherLevel->id);

        $this->assertDatabaseHas('academic_curriculum_items', [
            'id' => $own->id,
            'academic_plan_id' => $otherPlan->id,
            'academic_level_id' => $otherLevel->id,
        ]);
    }
}
