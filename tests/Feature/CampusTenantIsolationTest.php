<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampusTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private int $institutionSequence = 0;

    private function makeInstitution(): Institution
    {
        $this->institutionSequence++;

        return Institution::create([
            'name' => 'Institución de prueba '.$this->institutionSequence,
            'code' => 'TENANT'.$this->institutionSequence,
            'type' => 'institute',
            'is_active' => true,
        ]);
    }

    private function makeCampus(Institution $institution, string $name = 'Sede Central'): Campus
    {
        return Campus::create([
            'institution_id' => $institution->id,
            'name' => $name,
            'code' => 'SEDE'.($institution->id).'-'.str_replace(' ', '-', $name),
            'is_active' => true,
        ]);
    }

    private function userWithMembership(Institution $institution): User
    {
        $user = User::factory()->create();
        $user->institutions()->attach($institution->id, ['is_active' => true]);

        return $user;
    }

    private function institutionHeader(Institution $institution): array
    {
        return ['X-Institution-Id' => (string) $institution->id];
    }

    public function test_unauthenticated_user_cannot_list_campuses(): void
    {
        $institution = $this->makeInstitution();

        $this->getJson('/api/campuses', $this->institutionHeader($institution))
            ->assertUnauthorized();
    }

    public function test_authenticated_user_must_select_an_institution(): void
    {
        $institution = $this->makeInstitution();
        $this->actingAs($this->userWithMembership($institution));

        $this->getJson('/api/campuses')->assertUnprocessable();
    }

    public function test_user_without_membership_cannot_list_campuses(): void
    {
        $institution = $this->makeInstitution();
        $this->actingAs(User::factory()->create());

        $this->getJson('/api/campuses', $this->institutionHeader($institution))
            ->assertForbidden();
    }

    public function test_index_returns_only_campuses_of_selected_institution(): void
    {
        $institutionA = $this->makeInstitution();
        $institutionB = $this->makeInstitution();
        $campusA = $this->makeCampus($institutionA, 'Sede A');
        $this->makeCampus($institutionB, 'Sede B');
        $this->actingAs($this->userWithMembership($institutionA));

        $this->getJson('/api/campuses', $this->institutionHeader($institutionA))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $campusA->id);
    }

    public function test_cannot_show_campus_belonging_to_other_institution(): void
    {
        $institutionA = $this->makeInstitution();
        $institutionB = $this->makeInstitution();
        $campusB = $this->makeCampus($institutionB);
        $this->actingAs($this->userWithMembership($institutionA));

        $this->getJson('/api/campuses/'.$campusB->id, $this->institutionHeader($institutionA))
            ->assertNotFound();
    }

    public function test_cannot_create_campus_in_other_institution(): void
    {
        $institutionA = $this->makeInstitution();
        $institutionB = $this->makeInstitution();
        $this->actingAs($this->userWithMembership($institutionA));

        $this->postJson('/api/campuses', [
            'institution_id' => $institutionB->id,
            'name' => 'Sede no autorizada',
            'code' => 'NO-AUT',
        ], $this->institutionHeader($institutionA))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('institution_id');

        $this->assertDatabaseMissing('campuses', [
            'institution_id' => $institutionB->id,
            'name' => 'Sede no autorizada',
        ]);
    }

    public function test_cannot_update_campus_belonging_to_other_institution(): void
    {
        $institutionA = $this->makeInstitution();
        $institutionB = $this->makeInstitution();
        $campusB = $this->makeCampus($institutionB);
        $this->actingAs($this->userWithMembership($institutionA));

        $this->putJson('/api/campuses/'.$campusB->id, [
            'institution_id' => $institutionA->id,
            'name' => 'Sede intervenida',
            'code' => 'INT',
        ], $this->institutionHeader($institutionA))
            ->assertNotFound();

        $this->assertDatabaseHas('campuses', [
            'id' => $campusB->id,
            'institution_id' => $institutionB->id,
            'name' => 'Sede Central',
        ]);
    }

    public function test_cannot_delete_campus_belonging_to_other_institution(): void
    {
        $institutionA = $this->makeInstitution();
        $institutionB = $this->makeInstitution();
        $campusB = $this->makeCampus($institutionB);
        $this->actingAs($this->userWithMembership($institutionA));

        $this->deleteJson('/api/campuses/'.$campusB->id, [], $this->institutionHeader($institutionA))
            ->assertNotFound();

        $this->assertDatabaseHas('campuses', ['id' => $campusB->id]);
    }

    public function test_cannot_move_own_campus_into_other_institution(): void
    {
        $institutionA = $this->makeInstitution();
        $institutionB = $this->makeInstitution();
        $campusA = $this->makeCampus($institutionA);
        $this->actingAs($this->userWithMembership($institutionA));

        $this->putJson('/api/campuses/'.$campusA->id, [
            'institution_id' => $institutionB->id,
            'name' => 'Sede Central',
            'code' => 'MOVIDA',
        ], $this->institutionHeader($institutionA))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('institution_id');

        $this->assertDatabaseHas('campuses', [
            'id' => $campusA->id,
            'institution_id' => $institutionA->id,
        ]);
    }

    public function test_authorized_user_can_create_show_update_and_delete_own_campus(): void
    {
        $institution = $this->makeInstitution();
        $this->actingAs($this->userWithMembership($institution));
        $headers = $this->institutionHeader($institution);

        $response = $this->postJson('/api/campuses', [
            'institution_id' => $institution->id,
            'name' => 'Sede Autorizada',
            'code' => 'AUT',
        ], $headers)->assertCreated();

        $campusId = $response->json('data.id');
        $this->assertNotNull($campusId);

        $this->getJson('/api/campuses/'.$campusId, $headers)
            ->assertOk()
            ->assertJsonPath('data.id', $campusId);

        $this->putJson('/api/campuses/'.$campusId, [
            'institution_id' => $institution->id,
            'name' => 'Sede Actualizada',
            'code' => 'AUT2',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.name', 'Sede Actualizada');

        $this->deleteJson('/api/campuses/'.$campusId, [], $headers)
            ->assertOk();

        $this->assertDatabaseMissing('campuses', ['id' => $campusId]);
    }

    public function test_user_with_two_memberships_still_cannot_mix_institution_data(): void
    {
        $institutionA = $this->makeInstitution();
        $institutionB = $this->makeInstitution();
        $campusA = $this->makeCampus($institutionA, 'Sede A');
        $campusB = $this->makeCampus($institutionB, 'Sede B');
        $user = $this->userWithMembership($institutionA);
        $user->institutions()->attach($institutionB->id, ['is_active' => true]);
        $this->actingAs($user);

        $this->getJson('/api/campuses', $this->institutionHeader($institutionA))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $campusA->id);

        $this->getJson('/api/campuses/'.$campusB->id, $this->institutionHeader($institutionA))
            ->assertNotFound();

        $this->getJson('/api/campuses', $this->institutionHeader($institutionB))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $campusB->id);
    }
}
