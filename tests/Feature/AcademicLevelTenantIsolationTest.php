<?php

namespace Tests\Feature;

use App\Models\AcademicLevel;
use App\Models\AcademicPlan;
use App\Models\AcademicProgram;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicLevelTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/academic-levels';

    private function institution(string $code = 'INST-A', bool $active = true): Institution
    {
        return Institution::create([
            'name' => 'Institución '.$code,
            'code' => $code,
            'type' => 'institute',
            'is_active' => $active,
        ]);
    }

    private function program(Institution $institution, string $name = 'Desarrollo de Software'): AcademicProgram
    {
        return AcademicProgram::create([
            'institution_id' => $institution->id,
            'name' => $name,
            'code' => 'PROG-'.str_replace(' ', '-', $name),
            'is_active' => true,
        ]);
    }

    private function plan(AcademicProgram $program, string $name = 'Plan 2026'): AcademicPlan
    {
        return AcademicPlan::create([
            'academic_program_id' => $program->id,
            'name' => $name,
            'version' => '1.0',
            'is_active' => true,
        ]);
    }

    private function level(AcademicPlan $plan, string $name = 'Primer Ciclo', int $order = 1): AcademicLevel
    {
        return AcademicLevel::create([
            'academic_plan_id' => $plan->id,
            'name' => $name,
            'order' => $order,
            'is_active' => true,
        ]);
    }

    private function member(Institution $institution, bool $active = true): User
    {
        $user = User::factory()->create();
        $user->institutions()->attach($institution->id, ['is_active' => $active, 'role' => 'institution_admin']);

        return $user;
    }

    private function headers(Institution $institution): array
    {
        return ['X-Institution-Id' => (string) $institution->id];
    }

    private function payload(AcademicPlan $plan, string $name = 'Segundo Ciclo', int $order = 2): array
    {
        return [
            'academic_plan_id' => $plan->id,
            'name' => $name,
            'order' => $order,
            'is_active' => true,
        ];
    }

    public function test_unauthenticated_user_cannot_list_levels(): void
    {
        $institution = $this->institution();

        $this->getJson(self::ENDPOINT, $this->headers($institution))
            ->assertUnauthorized();
    }

    public function test_authenticated_user_must_select_an_institution(): void
    {
        $institution = $this->institution();
        $this->actingAs($this->member($institution));

        $this->getJson(self::ENDPOINT)->assertUnprocessable();
    }

    public function test_user_without_membership_cannot_list_levels(): void
    {
        $institution = $this->institution();
        $this->actingAs(User::factory()->create());

        $this->getJson(self::ENDPOINT, $this->headers($institution))
            ->assertForbidden();
    }

    public function test_inactive_membership_cannot_access_levels(): void
    {
        $institution = $this->institution();
        $this->actingAs($this->member($institution, false));

        $this->getJson(self::ENDPOINT, $this->headers($institution))
            ->assertForbidden();
    }

    public function test_inactive_institution_cannot_be_selected(): void
    {
        $institution = $this->institution('INST-A', false);
        $this->actingAs($this->member($institution));

        $this->getJson(self::ENDPOINT, $this->headers($institution))
            ->assertForbidden();
    }

    public function test_index_returns_only_levels_of_selected_institution(): void
    {
        $a = $this->institution('INST-A');
        $b = $this->institution('INST-B');
        $levelA = $this->level($this->plan($this->program($a)));
        $this->level($this->plan($this->program($b)));
        $this->actingAs($this->member($a));

        $this->getJson(self::ENDPOINT, $this->headers($a))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $levelA->id);
    }

    public function test_cannot_show_level_of_another_institution(): void
    {
        $a = $this->institution('INST-A');
        $b = $this->institution('INST-B');
        $levelB = $this->level($this->plan($this->program($b)));
        $this->actingAs($this->member($a));

        $this->getJson(self::ENDPOINT.'/'.$levelB->id, $this->headers($a))
            ->assertNotFound();
    }

    public function test_cannot_create_level_in_plan_of_another_institution(): void
    {
        $a = $this->institution('INST-A');
        $b = $this->institution('INST-B');
        $planB = $this->plan($this->program($b));
        $this->actingAs($this->member($a));

        $this->postJson(self::ENDPOINT, $this->payload($planB), $this->headers($a))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('academic_plan_id');

        $this->assertDatabaseCount('academic_levels', 0);
    }

    public function test_cannot_update_level_of_another_institution(): void
    {
        $a = $this->institution('INST-A');
        $b = $this->institution('INST-B');
        $planA = $this->plan($this->program($a));
        $planB = $this->plan($this->program($b));
        $levelB = $this->level($planB);
        $this->actingAs($this->member($a));

        $this->putJson(self::ENDPOINT.'/'.$levelB->id, $this->payload($planA), $this->headers($a))
            ->assertNotFound();

        $this->assertDatabaseHas('academic_levels', [
            'id' => $levelB->id,
            'academic_plan_id' => $planB->id,
            'name' => 'Primer Ciclo',
        ]);
    }

    public function test_cannot_delete_level_of_another_institution(): void
    {
        $a = $this->institution('INST-A');
        $b = $this->institution('INST-B');
        $levelB = $this->level($this->plan($this->program($b)));
        $this->actingAs($this->member($a));

        $this->deleteJson(self::ENDPOINT.'/'.$levelB->id, [], $this->headers($a))
            ->assertNotFound();

        $this->assertDatabaseHas('academic_levels', ['id' => $levelB->id]);
    }

    public function test_cannot_move_own_level_to_plan_of_another_institution(): void
    {
        $a = $this->institution('INST-A');
        $b = $this->institution('INST-B');
        $planA = $this->plan($this->program($a));
        $planB = $this->plan($this->program($b));
        $levelA = $this->level($planA);
        $this->actingAs($this->member($a));

        $this->putJson(self::ENDPOINT.'/'.$levelA->id, $this->payload($planB), $this->headers($a))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('academic_plan_id');

        $this->assertDatabaseHas('academic_levels', [
            'id' => $levelA->id,
            'academic_plan_id' => $planA->id,
        ]);
    }

    public function test_authorized_user_can_create_show_update_and_delete_own_level(): void
    {
        $a = $this->institution();
        $plan = $this->plan($this->program($a));
        $this->actingAs($this->member($a));
        $headers = $this->headers($a);

        $created = $this->postJson(self::ENDPOINT, $this->payload($plan), $headers)
            ->assertCreated()
            ->assertJsonPath('data.academic_plan_id', $plan->id);
        $id = $created->json('data.id');

        $this->getJson(self::ENDPOINT.'/'.$id, $headers)
            ->assertOk()
            ->assertJsonPath('data.id', $id);

        $this->putJson(self::ENDPOINT.'/'.$id, $this->payload($plan, 'Tercer Ciclo', 3), $headers)
            ->assertOk()
            ->assertJsonPath('data.name', 'Tercer Ciclo');

        $this->deleteJson(self::ENDPOINT.'/'.$id, [], $headers)->assertOk();
        $this->assertDatabaseMissing('academic_levels', ['id' => $id]);
    }

    public function test_user_with_two_memberships_still_cannot_mix_level_data(): void
    {
        $a = $this->institution('INST-A');
        $b = $this->institution('INST-B');
        $levelA = $this->level($this->plan($this->program($a)));
        $levelB = $this->level($this->plan($this->program($b)));

        $user = $this->member($a);
        $user->institutions()->attach($b->id, ['is_active' => true, 'role' => 'institution_admin']);
        $this->actingAs($user);

        $this->getJson(self::ENDPOINT, $this->headers($a))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $levelA->id);

        $this->getJson(self::ENDPOINT.'/'.$levelB->id, $this->headers($a))
            ->assertNotFound();

        $this->getJson(self::ENDPOINT, $this->headers($b))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $levelB->id);
    }

    public function test_can_move_level_between_plans_of_same_institution(): void
    {
        $a = $this->institution();
        $program = $this->program($a);
        $planOne = $this->plan($program, 'Plan 2026');
        $planTwo = $this->plan($program, 'Plan 2027');
        $level = $this->level($planOne);
        $this->actingAs($this->member($a));

        $this->putJson(self::ENDPOINT.'/'.$level->id, $this->payload($planTwo), $this->headers($a))
            ->assertOk()
            ->assertJsonPath('data.academic_plan_id', $planTwo->id);

        $this->assertDatabaseHas('academic_levels', [
            'id' => $level->id,
            'academic_plan_id' => $planTwo->id,
        ]);
    }
}
