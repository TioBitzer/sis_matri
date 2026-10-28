<?php

namespace Tests\Feature;

use App\Models\AcademicProgram;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicProgramTenantIsolationTest extends TestCase
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

    private function programData(Institution $institution, string $name = 'Ingeniería de Software'): array
    {
        return [
            'institution_id' => $institution->id,
            'name' => $name,
            'code' => 'IS',
            'description' => 'Programa actualizado',
            'is_active' => true,
        ];
    }

    public function test_unauthenticated_user_cannot_list_programs(): void
    {
        $institution = $this->makeInstitution();

        $this->getJson('/api/academic-programs', $this->headers($institution))
            ->assertUnauthorized();
    }

    public function test_authenticated_user_must_select_an_institution(): void
    {
        $institution = $this->makeInstitution();
        $this->actingAs($this->userWithMembership($institution));

        $this->getJson('/api/academic-programs')->assertUnprocessable();
    }

    public function test_user_without_membership_cannot_list_programs(): void
    {
        $institution = $this->makeInstitution();
        $this->actingAs(User::factory()->create());

        $this->getJson('/api/academic-programs', $this->headers($institution))
            ->assertForbidden();
    }

    public function test_inactive_membership_cannot_access_programs(): void
    {
        $institution = $this->makeInstitution();
        $this->actingAs($this->userWithMembership($institution, false));

        $this->getJson('/api/academic-programs', $this->headers($institution))
            ->assertForbidden();
    }

    public function test_inactive_institution_cannot_be_selected(): void
    {
        $institution = $this->makeInstitution('INST-A', false);
        $this->actingAs($this->userWithMembership($institution));

        $this->getJson('/api/academic-programs', $this->headers($institution))
            ->assertForbidden();
    }

    public function test_index_returns_only_programs_of_selected_institution(): void
    {
        $institutionA = $this->makeInstitution('INST-A');
        $institutionB = $this->makeInstitution('INST-B');
        $programA = $this->makeProgram($institutionA, 'Desarrollo de Software');
        $this->makeProgram($institutionB, 'Administración');
        $this->actingAs($this->userWithMembership($institutionA));

        $this->getJson('/api/academic-programs', $this->headers($institutionA))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $programA->id);
    }

    public function test_cannot_show_program_belonging_to_another_institution(): void
    {
        $institutionA = $this->makeInstitution('INST-A');
        $institutionB = $this->makeInstitution('INST-B');
        $programB = $this->makeProgram($institutionB);
        $this->actingAs($this->userWithMembership($institutionA));

        $this->getJson('/api/academic-programs/'.$programB->id, $this->headers($institutionA))
            ->assertNotFound();
    }

    public function test_cannot_create_program_in_another_institution(): void
    {
        $institutionA = $this->makeInstitution('INST-A');
        $institutionB = $this->makeInstitution('INST-B');
        $this->actingAs($this->userWithMembership($institutionA));

        $this->postJson('/api/academic-programs', $this->programData($institutionB), $this->headers($institutionA))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('institution_id');

        $this->assertDatabaseCount('academic_programs', 0);
    }

    public function test_cannot_update_program_belonging_to_another_institution(): void
    {
        $institutionA = $this->makeInstitution('INST-A');
        $institutionB = $this->makeInstitution('INST-B');
        $programB = $this->makeProgram($institutionB);
        $this->actingAs($this->userWithMembership($institutionA));

        $this->putJson('/api/academic-programs/'.$programB->id, $this->programData($institutionA), $this->headers($institutionA))
            ->assertNotFound();

        $this->assertDatabaseHas('academic_programs', [
            'id' => $programB->id,
            'institution_id' => $institutionB->id,
            'name' => 'Desarrollo de Software',
        ]);
    }

    public function test_cannot_delete_program_belonging_to_another_institution(): void
    {
        $institutionA = $this->makeInstitution('INST-A');
        $institutionB = $this->makeInstitution('INST-B');
        $programB = $this->makeProgram($institutionB);
        $this->actingAs($this->userWithMembership($institutionA));

        $this->deleteJson('/api/academic-programs/'.$programB->id, [], $this->headers($institutionA))
            ->assertNotFound();

        $this->assertDatabaseHas('academic_programs', ['id' => $programB->id]);
    }

    public function test_cannot_move_own_program_to_another_institution(): void
    {
        $institutionA = $this->makeInstitution('INST-A');
        $institutionB = $this->makeInstitution('INST-B');
        $programA = $this->makeProgram($institutionA);
        $this->actingAs($this->userWithMembership($institutionA));

        $this->putJson('/api/academic-programs/'.$programA->id, $this->programData($institutionB), $this->headers($institutionA))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('institution_id');

        $this->assertDatabaseHas('academic_programs', [
            'id' => $programA->id,
            'institution_id' => $institutionA->id,
            'name' => 'Desarrollo de Software',
        ]);
    }

    public function test_authorized_user_can_create_show_update_and_delete_own_program(): void
    {
        $institution = $this->makeInstitution();
        $this->actingAs($this->userWithMembership($institution));
        $headers = $this->headers($institution);

        $created = $this->postJson('/api/academic-programs', $this->programData($institution), $headers)
            ->assertCreated()
            ->assertJsonPath('data.institution_id', $institution->id);

        $id = $created->json('data.id');

        $this->getJson('/api/academic-programs/'.$id, $headers)
            ->assertOk()
            ->assertJsonPath('data.id', $id);

        $this->putJson('/api/academic-programs/'.$id, $this->programData($institution, 'Desarrollo de Aplicaciones'), $headers)
            ->assertOk()
            ->assertJsonPath('data.name', 'Desarrollo de Aplicaciones');

        $this->deleteJson('/api/academic-programs/'.$id, [], $headers)->assertOk();
        $this->assertDatabaseMissing('academic_programs', ['id' => $id]);
    }

    public function test_user_with_two_memberships_still_cannot_mix_institution_data(): void
    {
        $institutionA = $this->makeInstitution('INST-A');
        $institutionB = $this->makeInstitution('INST-B');
        $programA = $this->makeProgram($institutionA, 'Programa A');
        $programB = $this->makeProgram($institutionB, 'Programa B');
        $user = $this->userWithMembership($institutionA);
        $user->institutions()->attach($institutionB->id, ['is_active' => true]);
        $this->actingAs($user);

        $this->getJson('/api/academic-programs', $this->headers($institutionA))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $programA->id);

        $this->getJson('/api/academic-programs/'.$programB->id, $this->headers($institutionA))
            ->assertNotFound();

        $this->getJson('/api/academic-programs', $this->headers($institutionB))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $programB->id);
    }
}
