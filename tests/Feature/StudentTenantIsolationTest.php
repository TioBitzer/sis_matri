<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/students';

    private function context(string $suffix = 'A', bool $active = true): array
    {
        $institution = Institution::create([
            'name' => 'Instituto Estudiantes '.$suffix,
            'code' => 'ST-INST-'.$suffix,
            'type' => 'institute',
            'is_active' => $active,
        ]);

        $personId = $this->person($institution, '01');
        return compact('institution', 'personId');
    }

    private function person(Institution $institution, string $suffix): int
    {
        return DB::table('people')->insertGetId([
            'institution_id' => $institution->id,
            'first_name' => 'Estudiante '.$suffix,
            'last_name' => 'Aislamiento',
            'document_type' => 'DNI',
            'document_number' => 'ST-DOC-'.$institution->id.'-'.$suffix,
        ]);
    }

    private function payload(array $context, array $changes = []): array
    {
        return array_replace([
            'person_id' => $context['personId'],
            'student_code' => 'ST-CODE-'.$context['personId'],
            'status' => 'active',
        ], $changes);
    }

    private function student(array $context, array $changes = []): int
    {
        return DB::table('students')->insertGetId($this->payload($context, $changes));
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

    public function test_unauthenticated_user_cannot_list_students(): void
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
        $this->getJson(self::ENDPOINT, ['X-Institution-Id' => 'invalid'])
            ->assertUnprocessable();
    }

    public function test_user_without_membership_cannot_access_students(): void
    {
        $a = $this->context();
        $this->actingAs(User::factory()->create());
        $this->getJson(self::ENDPOINT, $this->headers($a['institution']))->assertForbidden();
    }

    public function test_inactive_membership_cannot_access_students(): void
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

    public function test_index_returns_only_students_of_selected_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $own = $this->student($a);
        $this->student($b);
        $this->actingAs($this->member($a['institution']));

        $this->getJson(self::ENDPOINT, $this->headers($a['institution']))
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own);
    }

    public function test_cannot_show_student_of_another_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $foreign = $this->student($b);
        $this->actingAs($this->member($a['institution']));
        $this->getJson(self::ENDPOINT.'/'.$foreign, $this->headers($a['institution']))
            ->assertNotFound();
    }

    public function test_cannot_create_student_with_person_of_another_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $this->actingAs($this->member($a['institution']));

        $this->postJson(self::ENDPOINT, $this->payload($b), $this->headers($a['institution']))
            ->assertUnprocessable()->assertJsonValidationErrors('person_id');
        $this->assertDatabaseCount('students', 0);
    }

    public function test_cannot_update_student_of_another_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $foreign = $this->student($b);
        $this->actingAs($this->member($a['institution']));

        $this->putJson(self::ENDPOINT.'/'.$foreign, $this->payload($a),
            $this->headers($a['institution']))->assertNotFound();
        $this->assertDatabaseHas('students', ['id' => $foreign, 'person_id' => $b['personId']]);
    }

    public function test_cannot_delete_student_of_another_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $foreign = $this->student($b);
        $this->actingAs($this->member($a['institution']));

        $this->deleteJson(self::ENDPOINT.'/'.$foreign, [],
            $this->headers($a['institution']))->assertNotFound();
        $this->assertDatabaseHas('students', ['id' => $foreign]);
    }

    public function test_cannot_move_own_student_to_foreign_person(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $own = $this->student($a);
        $this->actingAs($this->member($a['institution']));

        $this->putJson(self::ENDPOINT.'/'.$own,
            $this->payload($a, ['person_id' => $b['personId']]),
            $this->headers($a['institution']))
            ->assertUnprocessable()->assertJsonValidationErrors('person_id');
        $this->assertDatabaseHas('students', ['id' => $own, 'person_id' => $a['personId']]);
    }

    public function test_authorized_user_can_create_show_update_and_delete_own_student(): void
    {
        $a = $this->context();
        $this->actingAs($this->member($a['institution']));
        $headers = $this->headers($a['institution']);

        $created = $this->postJson(self::ENDPOINT, $this->payload($a), $headers)
            ->assertCreated()->assertJsonPath('data.person_id', $a['personId']);
        $id = (int) $created->json('data.id');

        $this->getJson(self::ENDPOINT.'/'.$id, $headers)
            ->assertOk()->assertJsonPath('data.id', $id);
        $this->putJson(self::ENDPOINT.'/'.$id,
            $this->payload($a, ['student_code' => 'ST-UPDATED-'.$id]), $headers)
            ->assertOk()->assertJsonPath('data.student_code', 'ST-UPDATED-'.$id);
        $this->deleteJson(self::ENDPOINT.'/'.$id, [], $headers)->assertOk();
        $this->assertDatabaseMissing('students', ['id' => $id]);
    }

    public function test_user_with_two_memberships_still_cannot_mix_students(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $ownA = $this->student($a);
        $ownB = $this->student($b);
        $user = $this->member($a['institution']);
        $user->institutions()->attach($b['institution']->id, ['is_active' => true]);
        $this->actingAs($user);

        $this->getJson(self::ENDPOINT, $this->headers($a['institution']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ownA);
        $this->getJson(self::ENDPOINT.'/'.$ownB, $this->headers($a['institution']))
            ->assertNotFound();
        $this->getJson(self::ENDPOINT, $this->headers($b['institution']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ownB);
    }

    public function test_two_memberships_cannot_create_for_unselected_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $user = $this->member($a['institution']);
        $user->institutions()->attach($b['institution']->id, ['is_active' => true]);
        $this->actingAs($user);

        $this->postJson(self::ENDPOINT, $this->payload($b),
            $this->headers($a['institution']))
            ->assertUnprocessable()->assertJsonValidationErrors('person_id');
        $this->assertDatabaseCount('students', 0);
    }

    public function test_authorized_user_can_create_in_both_institutions_by_switching_header(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $user = $this->member($a['institution']);
        $user->institutions()->attach($b['institution']->id, ['is_active' => true]);
        $this->actingAs($user);

        $this->postJson(self::ENDPOINT, $this->payload($a),
            $this->headers($a['institution']))->assertCreated();
        $this->postJson(self::ENDPOINT, $this->payload($b),
            $this->headers($b['institution']))->assertCreated();
        $this->assertDatabaseCount('students', 2);
    }

    public function test_authorized_user_cannot_register_the_same_person_twice(): void
    {
        $a = $this->context();
        $this->student($a);
        $this->actingAs($this->member($a['institution']));

        $this->postJson(self::ENDPOINT,
            $this->payload($a, ['student_code' => 'ST-SECOND']),
            $this->headers($a['institution']))
            ->assertUnprocessable()->assertJsonValidationErrors('person_id');
        $this->assertDatabaseCount('students', 1);
    }

    public function test_authorized_user_cannot_duplicate_student_code(): void
    {
        $a = $this->context();
        $this->student($a, ['student_code' => 'ST-DUPLICATE']);
        $secondPerson = $this->person($a['institution'], '02');
        $this->actingAs($this->member($a['institution']));

        $this->postJson(self::ENDPOINT,
            $this->payload($a, [
                'person_id' => $secondPerson, 'student_code' => 'ST-DUPLICATE',
            ]), $this->headers($a['institution']))
            ->assertUnprocessable()->assertJsonValidationErrors('student_code');
        $this->assertDatabaseCount('students', 1);
    }

    public function test_authorized_user_cannot_register_invalid_student_status(): void
    {
        $a = $this->context();
        $this->actingAs($this->member($a['institution']));

        $this->postJson(self::ENDPOINT,
            $this->payload($a, ['status' => 'not-a-valid-status']),
            $this->headers($a['institution']))
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertDatabaseCount('students', 0);
    }

    public function test_two_memberships_cannot_register_foreign_person_using_wrong_header(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $user = $this->member($a['institution']);
        $user->institutions()->attach($b['institution']->id, ['is_active' => true]);
        $this->actingAs($user);

        $this->postJson(self::ENDPOINT,
            $this->payload($a, ['person_id' => $b['personId']]),
            $this->headers($a['institution']))
            ->assertUnprocessable()->assertJsonValidationErrors('person_id');
        $this->assertDatabaseCount('students', 0);
    }

    public function test_two_memberships_cannot_reassign_own_student_to_other_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $own = $this->student($a);
        $user = $this->member($a['institution']);
        $user->institutions()->attach($b['institution']->id, ['is_active' => true]);
        $this->actingAs($user);

        $this->putJson(self::ENDPOINT.'/'.$own,
            $this->payload($a, ['person_id' => $b['personId']]),
            $this->headers($a['institution']))
            ->assertUnprocessable()->assertJsonValidationErrors('person_id');
        $this->assertDatabaseHas('students', ['id' => $own, 'person_id' => $a['personId']]);
    }

    public function test_authorized_user_can_change_student_person_within_same_institution(): void
    {
        $a = $this->context();
        $own = $this->student($a);
        $otherPerson = $this->person($a['institution'], '02');
        $this->actingAs($this->member($a['institution']));

        $this->putJson(self::ENDPOINT.'/'.$own,
            $this->payload($a, ['person_id' => $otherPerson]),
            $this->headers($a['institution']))
            ->assertOk()->assertJsonPath('data.person_id', $otherPerson);
        $this->assertDatabaseHas('students', ['id' => $own, 'person_id' => $otherPerson]);
    }

    public function test_deleting_student_keeps_person_record(): void
    {
        $a = $this->context();
        $own = $this->student($a);
        $this->actingAs($this->member($a['institution']));

        $this->deleteJson(self::ENDPOINT.'/'.$own, [],
            $this->headers($a['institution']))->assertOk();
        $this->assertDatabaseMissing('students', ['id' => $own]);
        $this->assertDatabaseHas('people', [
            'id' => $a['personId'], 'institution_id' => $a['institution']->id,
        ]);
    }

    public function test_authorized_user_cannot_update_to_invalid_status(): void
    {
        $a = $this->context();
        $own = $this->student($a);
        $this->actingAs($this->member($a['institution']));

        $this->putJson(self::ENDPOINT.'/'.$own,
            $this->payload($a, ['status' => 'not-a-valid-status']),
            $this->headers($a['institution']))
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertDatabaseHas('students', ['id' => $own, 'status' => 'active']);
    }
}
