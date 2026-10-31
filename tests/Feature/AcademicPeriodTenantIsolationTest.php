<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicPeriodTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/academic-periods';

    private function institution(string $code = 'PER-A', bool $active = true): Institution
    {
        return Institution::create([
            'name' => 'Institucion '.$code,
            'code' => $code,
            'type' => 'institute',
            'is_active' => $active,
        ]);
    }

    private function period(Institution $institution, string $name = 'Periodo 2026-II'): AcademicPeriod
    {
        return AcademicPeriod::create([
            'institution_id' => $institution->id,
            'name' => $name,
            'code' => '2026-II',
            'start_date' => '2026-08-01',
            'end_date' => '2026-12-31',
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

    private function payload(Institution $institution, string $name = 'Periodo 2027-I'): array
    {
        return [
            'institution_id' => $institution->id,
            'name' => $name,
            'code' => '2027-I',
            'start_date' => '2027-01-10',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ];
    }

    public function test_unauthenticated_user_cannot_list_periods(): void
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

    public function test_nonnumeric_institution_header_is_rejected(): void
    {
        $institution = $this->institution();
        $this->actingAs($this->member($institution));

        $this->getJson(self::ENDPOINT, ['X-Institution-Id' => 'no-es-un-numero'])
            ->assertUnprocessable();
    }

    public function test_user_without_membership_cannot_access_periods(): void
    {
        $institution = $this->institution();
        $this->actingAs(User::factory()->create());

        $this->getJson(self::ENDPOINT, $this->headers($institution))
            ->assertForbidden();
    }

    public function test_inactive_membership_cannot_access_periods(): void
    {
        $institution = $this->institution();
        $this->actingAs($this->member($institution, false));

        $this->getJson(self::ENDPOINT, $this->headers($institution))
            ->assertForbidden();
    }

    public function test_inactive_institution_cannot_be_selected(): void
    {
        $institution = $this->institution('PER-A', false);
        $this->actingAs($this->member($institution));

        $this->getJson(self::ENDPOINT, $this->headers($institution))
            ->assertForbidden();
    }

    public function test_index_returns_only_periods_of_selected_institution(): void
    {
        $institutionA = $this->institution('PER-A');
        $institutionB = $this->institution('PER-B');
        $own = $this->period($institutionA);
        $this->period($institutionB);
        $this->actingAs($this->member($institutionA));

        $this->getJson(self::ENDPOINT, $this->headers($institutionA))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);
    }

    public function test_cannot_show_period_of_another_institution(): void
    {
        $institutionA = $this->institution('PER-A');
        $institutionB = $this->institution('PER-B');
        $foreign = $this->period($institutionB);
        $this->actingAs($this->member($institutionA));

        $this->getJson(self::ENDPOINT.'/'.$foreign->id, $this->headers($institutionA))
            ->assertNotFound();
    }

    public function test_cannot_create_period_for_another_institution(): void
    {
        $institutionA = $this->institution('PER-A');
        $institutionB = $this->institution('PER-B');
        $this->actingAs($this->member($institutionA));

        $this->postJson(self::ENDPOINT, $this->payload($institutionB), $this->headers($institutionA))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('institution_id');

        $this->assertDatabaseCount('academic_periods', 0);
    }

    public function test_cannot_update_period_of_another_institution(): void
    {
        $institutionA = $this->institution('PER-A');
        $institutionB = $this->institution('PER-B');
        $foreign = $this->period($institutionB);
        $this->actingAs($this->member($institutionA));

        $this->putJson(self::ENDPOINT.'/'.$foreign->id, $this->payload($institutionA), $this->headers($institutionA))
            ->assertNotFound();

        $this->assertDatabaseHas('academic_periods', [
            'id' => $foreign->id,
            'institution_id' => $institutionB->id,
            'name' => 'Periodo 2026-II',
        ]);
    }

    public function test_cannot_delete_period_of_another_institution(): void
    {
        $institutionA = $this->institution('PER-A');
        $institutionB = $this->institution('PER-B');
        $foreign = $this->period($institutionB);
        $this->actingAs($this->member($institutionA));

        $this->deleteJson(self::ENDPOINT.'/'.$foreign->id, [], $this->headers($institutionA))
            ->assertNotFound();

        $this->assertDatabaseHas('academic_periods', ['id' => $foreign->id]);
    }

    public function test_cannot_transfer_own_period_to_another_institution(): void
    {
        $institutionA = $this->institution('PER-A');
        $institutionB = $this->institution('PER-B');
        $own = $this->period($institutionA);
        $this->actingAs($this->member($institutionA));

        $this->putJson(self::ENDPOINT.'/'.$own->id, $this->payload($institutionB), $this->headers($institutionA))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('institution_id');

        $this->assertDatabaseHas('academic_periods', [
            'id' => $own->id,
            'institution_id' => $institutionA->id,
            'name' => 'Periodo 2026-II',
        ]);
    }

    public function test_authorized_user_can_create_show_update_and_delete_own_period(): void
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

        $this->putJson(self::ENDPOINT.'/'.$id, $this->payload($institution, 'Periodo 2027-I Actualizado'), $headers)
            ->assertOk()
            ->assertJsonPath('data.name', 'Periodo 2027-I Actualizado');

        $this->deleteJson(self::ENDPOINT.'/'.$id, [], $headers)->assertOk();
        $this->assertDatabaseMissing('academic_periods', ['id' => $id]);
    }

    public function test_user_with_two_memberships_cannot_mix_periods(): void
    {
        $institutionA = $this->institution('PER-A');
        $institutionB = $this->institution('PER-B');
        $periodA = $this->period($institutionA);
        $periodB = $this->period($institutionB);

        $user = $this->member($institutionA);
        $user->institutions()->attach($institutionB->id, ['is_active' => true, 'role' => 'institution_admin']);
        $this->actingAs($user);

        $this->getJson(self::ENDPOINT, $this->headers($institutionA))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $periodA->id);

        $this->getJson(self::ENDPOINT.'/'.$periodB->id, $this->headers($institutionA))
            ->assertNotFound();

        $this->getJson(self::ENDPOINT, $this->headers($institutionB))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $periodB->id);
    }

    public function test_same_period_name_is_allowed_across_institutions(): void
    {
        $institutionA = $this->institution('PER-A');
        $institutionB = $this->institution('PER-B');
        $user = $this->member($institutionA);
        $user->institutions()->attach($institutionB->id, ['is_active' => true, 'role' => 'institution_admin']);
        $this->actingAs($user);

        $this->postJson(self::ENDPOINT, $this->payload($institutionA, 'Semestre I'), $this->headers($institutionA))
            ->assertCreated();
        $this->postJson(self::ENDPOINT, $this->payload($institutionB, 'Semestre I'), $this->headers($institutionB))
            ->assertCreated();

        $this->assertDatabaseCount('academic_periods', 2);
    }

    public function test_selected_institution_does_not_allow_spoofed_body_institution_id(): void
    {
        $institutionA = $this->institution('PER-A');
        $institutionB = $this->institution('PER-B');
        $user = $this->member($institutionA);
        $user->institutions()->attach($institutionB->id, ['is_active' => true, 'role' => 'institution_admin']);
        $this->actingAs($user);

        $this->postJson(self::ENDPOINT, $this->payload($institutionB), $this->headers($institutionA))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('institution_id');

        $this->assertDatabaseCount('academic_periods', 0);
    }

    public function test_authorized_user_cannot_create_period_with_end_before_start(): void
    {
        $institution = $this->institution();
        $this->actingAs($this->member($institution));
        $data = $this->payload($institution);
        $data['end_date'] = '2027-01-01';

        $this->postJson(self::ENDPOINT, $data, $this->headers($institution))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('end_date');

        $this->assertDatabaseCount('academic_periods', 0);
    }

    public function test_authorized_user_cannot_duplicate_period_name_in_same_institution(): void
    {
        $institution = $this->institution();
        $this->period($institution, 'Periodo 2027-I');
        $this->actingAs($this->member($institution));

        $this->postJson(self::ENDPOINT, $this->payload($institution, 'Periodo 2027-I'), $this->headers($institution))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertDatabaseCount('academic_periods', 1);
    }
}
