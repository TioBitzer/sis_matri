<?php

namespace Tests\Feature;

use App\Models\AcademicOffering;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AcademicOfferingTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/academic-offerings';

    private function context(string $suffix = 'A', bool $active = true): array
    {
        $institution = Institution::create([
            'name' => 'Instituto Oferta '.$suffix,
            'code' => 'OF-'.$suffix,
            'type' => 'institute',
            'is_active' => $active,
        ]);

        $unitId = DB::table('academic_units')->insertGetId([
            'institution_id' => $institution->id,
            'name' => 'Programacion '.$suffix,
            'code' => 'OFUNIT-'.$suffix,
            'is_active' => true,
        ]);
        $periodId = DB::table('academic_periods')->insertGetId([
            'institution_id' => $institution->id,
            'name' => 'Periodo '.$suffix,
            'code' => '2026-'.$suffix,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
        $campusId = DB::table('campuses')->insertGetId([
            'institution_id' => $institution->id,
            'name' => 'Sede '.$suffix,
            'code' => 'OFSEDE-'.$suffix,
        ]);

        return [
            'institution' => $institution,
            'unit_id' => $unitId,
            'period_id' => $periodId,
            'campus_id' => $campusId,
        ];
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

    private function payload(array $context, array $changes = []): array
    {
        return array_merge([
            'academic_unit_id' => $context['unit_id'],
            'academic_period_id' => $context['period_id'],
            'campus_id' => $context['campus_id'],
            'section' => 'A',
            'capacity' => 30,
            'is_active' => true,
        ], $changes);
    }

    private function offering(array $context, array $changes = []): AcademicOffering
    {
        return AcademicOffering::create($this->payload($context, $changes));
    }

    public function test_unauthenticated_user_cannot_list_offerings(): void
    {
        $a = $this->context();
        $this->getJson(self::ENDPOINT, $this->headers($a['institution']))->assertUnauthorized();
    }

    public function test_authenticated_user_must_select_an_institution(): void
    {
        $a = $this->context();
        $this->actingAs($this->member($a['institution']));
        $this->getJson(self::ENDPOINT)->assertUnprocessable();
    }

    public function test_nonnumeric_institution_header_is_rejected(): void
    {
        $a = $this->context();
        $this->actingAs($this->member($a['institution']));
        $this->getJson(self::ENDPOINT, ['X-Institution-Id' => 'invalid'])->assertUnprocessable();
    }

    public function test_user_without_membership_cannot_access_offerings(): void
    {
        $a = $this->context();
        $this->actingAs(User::factory()->create());
        $this->getJson(self::ENDPOINT, $this->headers($a['institution']))->assertForbidden();
    }

    public function test_inactive_membership_cannot_access_offerings(): void
    {
        $a = $this->context();
        $this->actingAs($this->member($a['institution'], false));
        $this->getJson(self::ENDPOINT, $this->headers($a['institution']))->assertForbidden();
    }

    public function test_inactive_institution_cannot_be_selected(): void
    {
        $a = $this->context('A', false);
        $this->actingAs($this->member($a['institution']));
        $this->getJson(self::ENDPOINT, $this->headers($a['institution']))->assertForbidden();
    }

    public function test_index_returns_only_offerings_of_selected_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $own = $this->offering($a);
        $this->offering($b);
        $this->actingAs($this->member($a['institution']));
        $this->getJson(self::ENDPOINT, $this->headers($a['institution']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id);
    }

    public function test_cannot_show_offering_of_another_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $foreign = $this->offering($b);
        $this->actingAs($this->member($a['institution']));
        $this->getJson(self::ENDPOINT.'/'.$foreign->id, $this->headers($a['institution']))
            ->assertNotFound();
    }

    public function test_cannot_create_offering_using_all_foreign_relations(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $this->actingAs($this->member($a['institution']));
        $this->postJson(self::ENDPOINT, $this->payload($b), $this->headers($a['institution']))
            ->assertUnprocessable()->assertJsonValidationErrors('academic_unit_id');
        $this->assertDatabaseCount('academic_offerings', 0);
    }

    public function test_cannot_create_offering_using_foreign_unit(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $this->actingAs($this->member($a['institution']));
        $this->postJson(self::ENDPOINT, $this->payload($a, [
            'academic_unit_id' => $b['unit_id'],
        ]), $this->headers($a['institution']))
            ->assertUnprocessable()->assertJsonValidationErrors('academic_unit_id');
        $this->assertDatabaseCount('academic_offerings', 0);
    }

    public function test_cannot_create_offering_using_foreign_period(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $this->actingAs($this->member($a['institution']));
        $this->postJson(self::ENDPOINT, $this->payload($a, [
            'academic_period_id' => $b['period_id'],
        ]), $this->headers($a['institution']))
            ->assertUnprocessable()->assertJsonValidationErrors('academic_period_id');
    }

    public function test_cannot_create_offering_using_foreign_campus(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $this->actingAs($this->member($a['institution']));
        $this->postJson(self::ENDPOINT, $this->payload($a, [
            'campus_id' => $b['campus_id'],
        ]), $this->headers($a['institution']))
            ->assertUnprocessable()->assertJsonValidationErrors('campus_id');
    }

    public function test_cannot_update_offering_of_another_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $foreign = $this->offering($b);
        $this->actingAs($this->member($a['institution']));
        $this->putJson(self::ENDPOINT.'/'.$foreign->id, $this->payload($a), $this->headers($a['institution']))
            ->assertNotFound();
        $this->assertDatabaseHas('academic_offerings', [
            'id' => $foreign->id, 'academic_unit_id' => $b['unit_id'], 'section' => 'A',
        ]);
    }

    public function test_cannot_delete_offering_of_another_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $foreign = $this->offering($b);
        $this->actingAs($this->member($a['institution']));
        $this->deleteJson(self::ENDPOINT.'/'.$foreign->id, [], $this->headers($a['institution']))
            ->assertNotFound();
        $this->assertDatabaseHas('academic_offerings', ['id' => $foreign->id]);
    }

    public function test_cannot_move_own_offering_to_all_foreign_relations(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $own = $this->offering($a);
        $this->actingAs($this->member($a['institution']));
        $this->putJson(self::ENDPOINT.'/'.$own->id, $this->payload($b), $this->headers($a['institution']))
            ->assertUnprocessable()->assertJsonValidationErrors('academic_unit_id');
        $this->assertDatabaseHas('academic_offerings', [
            'id' => $own->id,
            'academic_unit_id' => $a['unit_id'],
            'academic_period_id' => $a['period_id'],
            'campus_id' => $a['campus_id'],
        ]);
    }

    public function test_cannot_move_own_offering_to_foreign_unit(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $own = $this->offering($a);
        $this->actingAs($this->member($a['institution']));
        $this->putJson(self::ENDPOINT.'/'.$own->id, $this->payload($a, [
            'academic_unit_id' => $b['unit_id'],
        ]), $this->headers($a['institution']))
            ->assertUnprocessable()->assertJsonValidationErrors('academic_unit_id');
        $this->assertDatabaseHas('academic_offerings', [
            'id' => $own->id, 'academic_unit_id' => $a['unit_id'],
        ]);
    }

    public function test_cannot_move_own_offering_to_foreign_period(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $own = $this->offering($a);
        $this->actingAs($this->member($a['institution']));
        $this->putJson(self::ENDPOINT.'/'.$own->id, $this->payload($a, [
            'academic_period_id' => $b['period_id'],
        ]), $this->headers($a['institution']))
            ->assertUnprocessable()->assertJsonValidationErrors('academic_period_id');
        $this->assertDatabaseHas('academic_offerings', [
            'id' => $own->id, 'academic_period_id' => $a['period_id'],
        ]);
    }

    public function test_cannot_move_own_offering_to_foreign_campus(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $own = $this->offering($a);
        $this->actingAs($this->member($a['institution']));
        $this->putJson(self::ENDPOINT.'/'.$own->id, $this->payload($a, [
            'campus_id' => $b['campus_id'],
        ]), $this->headers($a['institution']))
            ->assertUnprocessable()->assertJsonValidationErrors('campus_id');
        $this->assertDatabaseHas('academic_offerings', [
            'id' => $own->id, 'campus_id' => $a['campus_id'],
        ]);
    }

    public function test_authorized_user_can_create_show_update_and_delete_own_offering(): void
    {
        $a = $this->context();
        $this->actingAs($this->member($a['institution']));
        $headers = $this->headers($a['institution']);
        $created = $this->postJson(self::ENDPOINT, $this->payload($a), $headers)
            ->assertCreated()
            ->assertJsonPath('data.academic_unit_id', $a['unit_id'])
            ->assertJsonPath('data.academic_period_id', $a['period_id'])
            ->assertJsonPath('data.campus_id', $a['campus_id']);
        $id = (int) $created->json('data.id');
        $this->getJson(self::ENDPOINT.'/'.$id, $headers)
            ->assertOk()->assertJsonPath('data.id', $id);
        $this->putJson(self::ENDPOINT.'/'.$id, $this->payload($a, [
            'section' => 'B', 'capacity' => 35,
        ]), $headers)->assertOk()->assertJsonPath('data.section', 'B');
        $this->deleteJson(self::ENDPOINT.'/'.$id, [], $headers)->assertOk();
        $this->assertDatabaseMissing('academic_offerings', ['id' => $id]);
    }

    public function test_two_memberships_still_isolate_data_by_selected_header(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $offeringA = $this->offering($a);
        $offeringB = $this->offering($b);
        $user = $this->member($a['institution']);
        $user->institutions()->attach($b['institution']->id, ['is_active' => true, 'role' => 'institution_admin']);
        $this->actingAs($user);
        $this->getJson(self::ENDPOINT, $this->headers($a['institution']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $offeringA->id);
        $this->getJson(self::ENDPOINT.'/'.$offeringB->id, $this->headers($a['institution']))
            ->assertNotFound();
        $this->getJson(self::ENDPOINT, $this->headers($b['institution']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $offeringB->id);
    }

    public function test_two_memberships_cannot_create_with_wrong_selected_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $user = $this->member($a['institution']);
        $user->institutions()->attach($b['institution']->id, ['is_active' => true, 'role' => 'institution_admin']);
        $this->actingAs($user);
        $this->postJson(self::ENDPOINT, $this->payload($b), $this->headers($a['institution']))
            ->assertUnprocessable()->assertJsonValidationErrors('academic_unit_id');
        $this->assertDatabaseCount('academic_offerings', 0);
    }

    public function test_same_section_is_allowed_in_different_institutions(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $user = $this->member($a['institution']);
        $user->institutions()->attach($b['institution']->id, ['is_active' => true, 'role' => 'institution_admin']);
        $this->actingAs($user);
        $this->postJson(self::ENDPOINT, $this->payload($a), $this->headers($a['institution']))->assertCreated();
        $this->postJson(self::ENDPOINT, $this->payload($b), $this->headers($b['institution']))->assertCreated();
        $this->assertDatabaseCount('academic_offerings', 2);
    }

    public function test_can_change_offering_to_other_relations_in_same_institution(): void
    {
        $a = $this->context();
        $own = $this->offering($a);
        $anotherUnit = DB::table('academic_units')->insertGetId([
            'institution_id' => $a['institution']->id,
            'name' => 'Bases de Datos', 'code' => 'BD-OF', 'is_active' => true,
        ]);
        $anotherPeriod = DB::table('academic_periods')->insertGetId([
            'institution_id' => $a['institution']->id,
            'name' => 'Periodo 2027', 'code' => '2027-A',
            'start_date' => '2027-01-01', 'end_date' => '2027-12-31',
        ]);
        $anotherCampus = DB::table('campuses')->insertGetId([
            'institution_id' => $a['institution']->id,
            'name' => 'Sede Norte', 'code' => 'OF-NORTH',
        ]);
        $this->actingAs($this->member($a['institution']));
        $this->putJson(self::ENDPOINT.'/'.$own->id, $this->payload($a, [
            'academic_unit_id' => $anotherUnit,
            'academic_period_id' => $anotherPeriod,
            'campus_id' => $anotherCampus,
            'section' => 'C',
        ]), $this->headers($a['institution']))
            ->assertOk()
            ->assertJsonPath('data.academic_unit_id', $anotherUnit)
            ->assertJsonPath('data.academic_period_id', $anotherPeriod)
            ->assertJsonPath('data.campus_id', $anotherCampus);
    }

    public function test_invalid_capacity_still_returns_validation_error_for_authorized_user(): void
    {
        $a = $this->context();
        $this->actingAs($this->member($a['institution']));
        $this->postJson(self::ENDPOINT, $this->payload($a, ['capacity' => 0]), $this->headers($a['institution']))
            ->assertUnprocessable()->assertJsonValidationErrors('capacity');
    }
}
