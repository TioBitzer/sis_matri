<?php

namespace Tests\Feature;

use App\Models\AcademicUnit;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicUnitTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/academic-units';

    private function institution(string $code = 'UNIT-A', bool $active = true): Institution
    {
        return Institution::create([
            'name' => 'Institucion '.$code,
            'code' => $code,
            'type' => 'institute',
            'is_active' => $active,
        ]);
    }

    private function unit(Institution $institution, string $name = 'Programacion I'): AcademicUnit
    {
        return AcademicUnit::create([
            'institution_id' => $institution->id,
            'name' => $name,
            'code' => 'PROG1',
            'unit_type' => 'course',
            'credits' => 4,
            'hours' => 64,
            'is_active' => true,
        ]);
    }

    private function member(Institution $institution, bool $active = true): User
    {
        $user = User::factory()->create();
        $user->institutions()->attach($institution->id, ['is_active' => $active]);

        return $user;
    }

    private function headers(Institution $institution): array
    {
        return ['X-Institution-Id' => (string) $institution->id];
    }

    private function payload(Institution $institution, string $name = 'Base de Datos I'): array
    {
        return [
            'institution_id' => $institution->id,
            'name' => $name,
            'code' => 'BD1',
            'description' => 'Fundamentos de bases de datos',
            'unit_type' => 'course',
            'credits' => 3,
            'hours' => 48,
            'is_active' => true,
        ];
    }

    public function test_unauthenticated_user_cannot_list_units(): void
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

    public function test_user_without_membership_cannot_access_units(): void
    {
        $institution = $this->institution();
        $this->actingAs(User::factory()->create());

        $this->getJson(self::ENDPOINT, $this->headers($institution))
            ->assertForbidden();
    }

    public function test_inactive_membership_cannot_access_units(): void
    {
        $institution = $this->institution();
        $this->actingAs($this->member($institution, false));

        $this->getJson(self::ENDPOINT, $this->headers($institution))
            ->assertForbidden();
    }

    public function test_inactive_institution_cannot_be_selected(): void
    {
        $institution = $this->institution('UNIT-A', false);
        $this->actingAs($this->member($institution));

        $this->getJson(self::ENDPOINT, $this->headers($institution))
            ->assertForbidden();
    }

    public function test_index_returns_only_units_of_selected_institution(): void
    {
        $institutionA = $this->institution('UNIT-A');
        $institutionB = $this->institution('UNIT-B');
        $own = $this->unit($institutionA);
        $this->unit($institutionB);
        $this->actingAs($this->member($institutionA));

        $this->getJson(self::ENDPOINT, $this->headers($institutionA))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);
    }

    public function test_cannot_show_unit_of_another_institution(): void
    {
        $institutionA = $this->institution('UNIT-A');
        $institutionB = $this->institution('UNIT-B');
        $foreign = $this->unit($institutionB);
        $this->actingAs($this->member($institutionA));

        $this->getJson(self::ENDPOINT.'/'.$foreign->id, $this->headers($institutionA))
            ->assertNotFound();
    }

    public function test_cannot_create_unit_for_another_institution(): void
    {
        $institutionA = $this->institution('UNIT-A');
        $institutionB = $this->institution('UNIT-B');
        $this->actingAs($this->member($institutionA));

        $this->postJson(self::ENDPOINT, $this->payload($institutionB), $this->headers($institutionA))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('institution_id');

        $this->assertDatabaseCount('academic_units', 0);
    }

    public function test_cannot_update_unit_of_another_institution(): void
    {
        $institutionA = $this->institution('UNIT-A');
        $institutionB = $this->institution('UNIT-B');
        $foreign = $this->unit($institutionB);
        $this->actingAs($this->member($institutionA));

        $this->putJson(self::ENDPOINT.'/'.$foreign->id, $this->payload($institutionA), $this->headers($institutionA))
            ->assertNotFound();

        $this->assertDatabaseHas('academic_units', [
            'id' => $foreign->id,
            'institution_id' => $institutionB->id,
            'name' => 'Programacion I',
        ]);
    }

    public function test_cannot_delete_unit_of_another_institution(): void
    {
        $institutionA = $this->institution('UNIT-A');
        $institutionB = $this->institution('UNIT-B');
        $foreign = $this->unit($institutionB);
        $this->actingAs($this->member($institutionA));

        $this->deleteJson(self::ENDPOINT.'/'.$foreign->id, [], $this->headers($institutionA))
            ->assertNotFound();

        $this->assertDatabaseHas('academic_units', ['id' => $foreign->id]);
    }

    public function test_cannot_transfer_own_unit_to_another_institution(): void
    {
        $institutionA = $this->institution('UNIT-A');
        $institutionB = $this->institution('UNIT-B');
        $own = $this->unit($institutionA);
        $this->actingAs($this->member($institutionA));

        $this->putJson(self::ENDPOINT.'/'.$own->id, $this->payload($institutionB), $this->headers($institutionA))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('institution_id');

        $this->assertDatabaseHas('academic_units', [
            'id' => $own->id,
            'institution_id' => $institutionA->id,
            'name' => 'Programacion I',
        ]);
    }

    public function test_authorized_user_can_create_show_update_and_delete_own_unit(): void
    {
        $institution = $this->institution();
        $this->actingAs($this->member($institution));
        $headers = $this->headers($institution);

        $created = $this->postJson(self::ENDPOINT, $this->payload($institution), $headers)
            ->assertCreated()
            ->assertJsonPath('data.institution_id', $institution->id);

        $id = $created->json('data.id');

        $this->getJson(self::ENDPOINT.'/'.$id, $headers)
            ->assertOk()
            ->assertJsonPath('data.id', $id);

        $this->putJson(self::ENDPOINT.'/'.$id, $this->payload($institution, 'Base de Datos II'), $headers)
            ->assertOk()
            ->assertJsonPath('data.name', 'Base de Datos II');

        $this->deleteJson(self::ENDPOINT.'/'.$id, [], $headers)->assertOk();
        $this->assertDatabaseMissing('academic_units', ['id' => $id]);
    }

    public function test_user_with_two_memberships_cannot_mix_institution_data(): void
    {
        $institutionA = $this->institution('UNIT-A');
        $institutionB = $this->institution('UNIT-B');
        $unitA = $this->unit($institutionA);
        $unitB = $this->unit($institutionB);

        $user = $this->member($institutionA);
        $user->institutions()->attach($institutionB->id, ['is_active' => true]);
        $this->actingAs($user);

        $this->getJson(self::ENDPOINT, $this->headers($institutionA))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $unitA->id);

        $this->getJson(self::ENDPOINT.'/'.$unitB->id, $this->headers($institutionA))
            ->assertNotFound();

        $this->getJson(self::ENDPOINT, $this->headers($institutionB))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $unitB->id);
    }

    public function test_same_unit_name_is_allowed_in_different_institutions(): void
    {
        $institutionA = $this->institution('UNIT-A');
        $institutionB = $this->institution('UNIT-B');
        $user = $this->member($institutionA);
        $user->institutions()->attach($institutionB->id, ['is_active' => true]);
        $this->actingAs($user);

        $this->postJson(self::ENDPOINT, $this->payload($institutionA, 'Matematicas I'), $this->headers($institutionA))
            ->assertCreated();

        $this->postJson(self::ENDPOINT, $this->payload($institutionB, 'Matematicas I'), $this->headers($institutionB))
            ->assertCreated();

        $this->assertDatabaseCount('academic_units', 2);
    }

    public function test_selected_institution_does_not_allow_spoofed_body_institution_id(): void
    {
        $institutionA = $this->institution('UNIT-A');
        $institutionB = $this->institution('UNIT-B');
        $user = $this->member($institutionA);
        $user->institutions()->attach($institutionB->id, ['is_active' => true]);
        $this->actingAs($user);

        $this->postJson(self::ENDPOINT, $this->payload($institutionB), $this->headers($institutionA))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('institution_id');

        $this->assertDatabaseCount('academic_units', 0);
    }
}
