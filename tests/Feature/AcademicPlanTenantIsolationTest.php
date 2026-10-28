<?php

namespace Tests\Feature;

use App\Models\AcademicPlan;
use App\Models\AcademicProgram;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicPlanTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function makeInstitution(string $code = 'INST-A', bool $active = true): Institution
    {
        return Institution::create([
            'name' => 'Institución '.$code,
            'code' => $code,
            'type' => 'institute',
            'is_active' => $active,
        ]);
    }

    private function makeProgram(Institution $institution, string $name = 'Desarrollo de Software'): AcademicProgram
    {
        return AcademicProgram::create([
            'institution_id' => $institution->id,
            'name' => $name,
            'code' => 'DS',
            'description' => 'Programa de prueba',
            'is_active' => true,
        ]);
    }

    private function makePlan(AcademicProgram $program, string $name = 'Plan Curricular 2026'): AcademicPlan
    {
        return AcademicPlan::create([
            'academic_program_id' => $program->id,
            'name' => $name,
            'version' => '1.0',
            'is_active' => true,
        ]);
    }

    private function userWithMembership(Institution $institution, bool $active = true): User
    {
        $user = User::factory()->create();
        $user->institutions()->attach($institution->id, ['is_active' => $active]);

        return $user;
    }

    private function headers(Institution $institution): array
    {
        return ['X-Institution-Id' => (string) $institution->id];
    }

    private function planData(AcademicProgram $program, string $name = 'Plan Curricular 2027'): array
    {
        return [
            'academic_program_id' => $program->id,
            'name' => $name,
            'version' => '2.0',
            'is_active' => true,
        ];
    }

    public function test_unauthenticated_user_cannot_list_plans(): void
    {
        $institution = $this->makeInstitution();

        $this->getJson('/api/academic-plans', $this->headers($institution))
            ->assertUnauthorized();
    }

    public function test_authenticated_user_must_select_an_institution(): void
    {
        $institution = $this->makeInstitution();
        $this->actingAs($this->userWithMembership($institution));

        $this->getJson('/api/academic-plans')->assertUnprocessable();
    }

    public function test_user_without_membership_cannot_list_plans(): void
    {
        $institution = $this->makeInstitution();
        $this->actingAs(User::factory()->create());

        $this->getJson('/api/academic-plans', $this->headers($institution))
            ->assertForbidden();
    }

    public function test_inactive_membership_cannot_access_plans(): void
    {
        $institution = $this->makeInstitution();
        $this->actingAs($this->userWithMembership($institution, false));

        $this->getJson('/api/academic-plans', $this->headers($institution))
            ->assertForbidden();
    }

    public function test_inactive_institution_cannot_be_selected(): void
    {
        $institution = $this->makeInstitution('INST-A', false);
        $this->actingAs($this->userWithMembership($institution));

        $this->getJson('/api/academic-plans', $this->headers($institution))
            ->assertForbidden();
    }

    public function test_index_returns_only_plans_of_selected_institution(): void
    {
        $institutionA = $this->makeInstitution('INST-A');
        $institutionB = $this->makeInstitution('INST-B');

        $planA = $this->makePlan($this->makeProgram($institutionA, 'Programa A'));
        $this->makePlan($this->makeProgram($institutionB, 'Programa B'));
        $this->actingAs($this->userWithMembership($institutionA));

        $this->getJson('/api/academic-plans', $this->headers($institutionA))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $planA->id);
    }

    public function test_cannot_show_plan_belonging_to_another_institution(): void
    {
        $institutionA = $this->makeInstitution('INST-A');
        $institutionB = $this->makeInstitution('INST-B');
        $planB = $this->makePlan($this->makeProgram($institutionB));

        $this->actingAs($this->userWithMembership($institutionA));

        $this->getJson('/api/academic-plans/'.$planB->id, $this->headers($institutionA))
            ->assertNotFound();
    }

    public function test_cannot_create_plan_for_program_of_another_institution(): void
    {
        $institutionA = $this->makeInstitution('INST-A');
        $institutionB = $this->makeInstitution('INST-B');
        $programB = $this->makeProgram($institutionB);

        $this->actingAs($this->userWithMembership($institutionA));

        $this->postJson('/api/academic-plans', $this->planData($programB), $this->headers($institutionA))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('academic_program_id');

        $this->assertDatabaseCount('academic_plans', 0);
    }

    public function test_cannot_update_plan_belonging_to_another_institution(): void
    {
        $institutionA = $this->makeInstitution('INST-A');
        $institutionB = $this->makeInstitution('INST-B');
        $programA = $this->makeProgram($institutionA);
        $programB = $this->makeProgram($institutionB);
        $planB = $this->makePlan($programB);

        $this->actingAs($this->userWithMembership($institutionA));

        $this->putJson('/api/academic-plans/'.$planB->id, $this->planData($programA), $this->headers($institutionA))
            ->assertNotFound();

        $this->assertDatabaseHas('academic_plans', [
            'id' => $planB->id,
            'academic_program_id' => $programB->id,
            'name' => 'Plan Curricular 2026',
        ]);
    }

    public function test_cannot_delete_plan_belonging_to_another_institution(): void
    {
        $institutionA = $this->makeInstitution('INST-A');
        $institutionB = $this->makeInstitution('INST-B');
        $planB = $this->makePlan($this->makeProgram($institutionB));

        $this->actingAs($this->userWithMembership($institutionA));

        $this->deleteJson('/api/academic-plans/'.$planB->id, [], $this->headers($institutionA))
            ->assertNotFound();

        $this->assertDatabaseHas('academic_plans', ['id' => $planB->id]);
    }

    public function test_cannot_move_own_plan_to_program_of_another_institution(): void
    {
        $institutionA = $this->makeInstitution('INST-A');
        $institutionB = $this->makeInstitution('INST-B');
        $programA = $this->makeProgram($institutionA);
        $programB = $this->makeProgram($institutionB);
        $planA = $this->makePlan($programA);

        $this->actingAs($this->userWithMembership($institutionA));

        $this->putJson('/api/academic-plans/'.$planA->id, $this->planData($programB), $this->headers($institutionA))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('academic_program_id');

        $this->assertDatabaseHas('academic_plans', [
            'id' => $planA->id,
            'academic_program_id' => $programA->id,
            'name' => 'Plan Curricular 2026',
        ]);
    }

    public function test_authorized_user_can_create_show_update_and_delete_own_plan(): void
    {
        $institution = $this->makeInstitution();
        $program = $this->makeProgram($institution);
        $this->actingAs($this->userWithMembership($institution));
        $headers = $this->headers($institution);

        $created = $this->postJson('/api/academic-plans', $this->planData($program), $headers)
            ->assertCreated()
            ->assertJsonPath('data.academic_program_id', $program->id);

        $id = $created->json('data.id');

        $this->getJson('/api/academic-plans/'.$id, $headers)
            ->assertOk()
            ->assertJsonPath('data.id', $id);

        $this->putJson(
            '/api/academic-plans/'.$id,
            $this->planData($program, 'Plan Curricular Actualizado'),
            $headers
        )
            ->assertOk()
            ->assertJsonPath('data.name', 'Plan Curricular Actualizado');

        $this->deleteJson('/api/academic-plans/'.$id, [], $headers)->assertOk();

        $this->assertDatabaseMissing('academic_plans', ['id' => $id]);
    }

    public function test_user_with_two_memberships_still_cannot_mix_institution_data(): void
    {
        $institutionA = $this->makeInstitution('INST-A');
        $institutionB = $this->makeInstitution('INST-B');
        $planA = $this->makePlan($this->makeProgram($institutionA, 'Programa A'));
        $planB = $this->makePlan($this->makeProgram($institutionB, 'Programa B'));

        $user = $this->userWithMembership($institutionA);
        $user->institutions()->attach($institutionB->id, ['is_active' => true]);
        $this->actingAs($user);

        $this->getJson('/api/academic-plans', $this->headers($institutionA))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $planA->id);

        $this->getJson('/api/academic-plans/'.$planB->id, $this->headers($institutionA))
            ->assertNotFound();

        $this->getJson('/api/academic-plans', $this->headers($institutionB))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $planB->id);
    }

    public function test_can_move_plan_between_programs_of_the_same_institution(): void
    {
        $institution = $this->makeInstitution();
        $programA = $this->makeProgram($institution, 'Desarrollo de Software');
        $programB = $this->makeProgram($institution, 'Administración');
        $plan = $this->makePlan($programA);

        $this->actingAs($this->userWithMembership($institution));

        $this->putJson('/api/academic-plans/'.$plan->id, $this->planData($programB), $this->headers($institution))
            ->assertOk()
            ->assertJsonPath('data.academic_program_id', $programB->id);

        $this->assertDatabaseHas('academic_plans', [
            'id' => $plan->id,
            'academic_program_id' => $programB->id,
        ]);
    }
}
