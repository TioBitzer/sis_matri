<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\Person;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PersonTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/people';

    private function institution(string $suffix = 'A', bool $active = true): Institution
    {
        return Institution::create([
            'name' => 'Instituto Personas '.$suffix,
            'code' => 'PERS-INST-'.$suffix,
            'type' => 'institute',
            'is_active' => $active,
        ]);
    }

    private function payload(Institution $institution, array $changes = []): array
    {
        return array_replace([
            'institution_id' => $institution->id,
            'first_name' => 'Persona',
            'last_name' => 'Aislamiento',
            'document_type' => 'DNI',
            'document_number' => 'PERS-DOC-'.$institution->id,
            'email' => 'persona'.$institution->id.'@example.test',
            'phone' => '987654321',
            'birth_date' => '2000-01-10',
        ], $changes);
    }

    private function person(Institution $institution, array $changes = []): Person
    {
        return Person::create($this->payload($institution, $changes));
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

    private function student(Person $person): int
    {
        return DB::table('students')->insertGetId([
            'person_id' => $person->id,
            'student_code' => 'PERS-STU-'.$person->id,
            'status' => 'active',
        ]);
    }

    private function teacher(Person $person): Teacher
    {
        return Teacher::create([
            'person_id' => $person->id,
            'institution_id' => $person->institution_id,
            'employee_code' => 'PERS-TEACH-'.$person->id,
            'specialization' => 'Software',
            'is_active' => true,
        ]);
    }

    public function test_unauthenticated_user_cannot_list_people(): void
    {
        $institution = $this->institution();
        $this->getJson(self::ENDPOINT, $this->headers($institution))->assertUnauthorized();
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
        $this->getJson(self::ENDPOINT, ['X-Institution-Id' => 'abc'])->assertUnprocessable();
    }

    public function test_user_without_membership_cannot_access_people(): void
    {
        $institution = $this->institution();
        $this->actingAs(User::factory()->create());
        $this->getJson(self::ENDPOINT, $this->headers($institution))->assertForbidden();
    }

    public function test_inactive_membership_cannot_access_people(): void
    {
        $institution = $this->institution();
        $this->actingAs($this->member($institution, false));
        $this->getJson(self::ENDPOINT, $this->headers($institution))->assertForbidden();
    }

    public function test_inactive_institution_cannot_be_selected(): void
    {
        $institution = $this->institution('A', false);
        $this->actingAs($this->member($institution));
        $this->getJson(self::ENDPOINT, $this->headers($institution))->assertForbidden();
    }

    public function test_index_returns_only_people_from_selected_institution(): void
    {
        $a = $this->institution('A');
        $b = $this->institution('B');
        $own = $this->person($a);
        $this->person($b);
        $this->actingAs($this->member($a));

        $this->getJson(self::ENDPOINT, $this->headers($a))
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id)
            ->assertJsonPath('data.0.institution_id', $a->id);
    }

    public function test_cannot_show_person_from_another_institution(): void
    {
        $a = $this->institution('A');
        $b = $this->institution('B');
        $foreign = $this->person($b);
        $this->actingAs($this->member($a));

        $this->getJson(self::ENDPOINT.'/'.$foreign->id, $this->headers($a))
            ->assertNotFound();
    }

    public function test_cannot_create_person_for_another_institution(): void
    {
        $a = $this->institution('A');
        $b = $this->institution('B');
        $this->actingAs($this->member($a));

        $this->postJson(self::ENDPOINT, $this->payload($b), $this->headers($a))
            ->assertUnprocessable()->assertJsonValidationErrors('institution_id');
        $this->assertDatabaseCount('people', 0);
    }

    public function test_cannot_update_person_from_another_institution(): void
    {
        $a = $this->institution('A');
        $b = $this->institution('B');
        $foreign = $this->person($b);
        $this->actingAs($this->member($a));

        $this->putJson(
            self::ENDPOINT.'/'.$foreign->id,
            $this->payload($a, ['first_name' => 'Modificado']),
            $this->headers($a)
        )->assertNotFound();
        $this->assertDatabaseHas('people', [
            'id' => $foreign->id,
            'institution_id' => $b->id,
            'first_name' => 'Persona',
        ]);
    }

    public function test_cannot_delete_person_from_another_institution(): void
    {
        $a = $this->institution('A');
        $b = $this->institution('B');
        $foreign = $this->person($b);
        $this->actingAs($this->member($a));

        $this->deleteJson(self::ENDPOINT.'/'.$foreign->id, [], $this->headers($a))
            ->assertNotFound();
        $this->assertDatabaseHas('people', ['id' => $foreign->id]);
    }

    public function test_cannot_move_own_unlinked_person_to_another_institution(): void
    {
        $a = $this->institution('A');
        $b = $this->institution('B');
        $own = $this->person($a);
        $this->actingAs($this->member($a));

        $this->putJson(self::ENDPOINT.'/'.$own->id, $this->payload($b), $this->headers($a))
            ->assertUnprocessable()->assertJsonValidationErrors('institution_id');
        $this->assertDatabaseHas('people', [
            'id' => $own->id, 'institution_id' => $a->id,
        ]);
    }

    public function test_authorized_user_can_create_show_update_and_delete_own_person(): void
    {
        $institution = $this->institution();
        $this->actingAs($this->member($institution));
        $headers = $this->headers($institution);

        $response = $this->postJson(self::ENDPOINT, $this->payload($institution), $headers)
            ->assertCreated()->assertJsonPath('data.institution_id', $institution->id);
        $id = (int) $response->json('data.id');

        $this->getJson(self::ENDPOINT.'/'.$id, $headers)
            ->assertOk()->assertJsonPath('data.id', $id);
        $this->putJson(
            self::ENDPOINT.'/'.$id,
            $this->payload($institution, ['first_name' => 'Actualizada']),
            $headers
        )->assertOk()->assertJsonPath('data.first_name', 'Actualizada');
        $this->deleteJson(self::ENDPOINT.'/'.$id, [], $headers)->assertOk();
        $this->assertDatabaseMissing('people', ['id' => $id]);
    }

    public function test_user_with_two_memberships_still_cannot_mix_people(): void
    {
        $a = $this->institution('A');
        $b = $this->institution('B');
        $ownA = $this->person($a);
        $ownB = $this->person($b);
        $user = $this->member($a);
        $user->institutions()->attach($b->id, ['is_active' => true, 'role' => 'institution_admin']);
        $this->actingAs($user);

        $this->getJson(self::ENDPOINT, $this->headers($a))
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownA->id);
        $this->getJson(self::ENDPOINT.'/'.$ownB->id, $this->headers($a))
            ->assertNotFound();
        $this->getJson(self::ENDPOINT, $this->headers($b))
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownB->id);
    }

    public function test_two_memberships_cannot_create_in_unselected_institution(): void
    {
        $a = $this->institution('A');
        $b = $this->institution('B');
        $user = $this->member($a);
        $user->institutions()->attach($b->id, ['is_active' => true, 'role' => 'institution_admin']);
        $this->actingAs($user);

        $this->postJson(self::ENDPOINT, $this->payload($b), $this->headers($a))
            ->assertUnprocessable()->assertJsonValidationErrors('institution_id');
        $this->assertDatabaseCount('people', 0);
    }

    public function test_two_memberships_cannot_move_own_person_to_other_institution(): void
    {
        $a = $this->institution('A');
        $b = $this->institution('B');
        $own = $this->person($a);
        $user = $this->member($a);
        $user->institutions()->attach($b->id, ['is_active' => true, 'role' => 'institution_admin']);
        $this->actingAs($user);

        $this->putJson(self::ENDPOINT.'/'.$own->id, $this->payload($b), $this->headers($a))
            ->assertUnprocessable()->assertJsonValidationErrors('institution_id');
        $this->assertDatabaseHas('people', ['id' => $own->id, 'institution_id' => $a->id]);
    }

    public function test_authorized_user_can_create_in_both_institutions_by_switching_header(): void
    {
        $a = $this->institution('A');
        $b = $this->institution('B');
        $user = $this->member($a);
        $user->institutions()->attach($b->id, ['is_active' => true, 'role' => 'institution_admin']);
        $this->actingAs($user);

        $this->postJson(self::ENDPOINT, $this->payload($a), $this->headers($a))
            ->assertCreated();
        $this->postJson(self::ENDPOINT, $this->payload($b), $this->headers($b))
            ->assertCreated();
        $this->assertDatabaseCount('people', 2);
    }

    public function test_same_document_number_is_allowed_across_institutions(): void
    {
        $a = $this->institution('A');
        $b = $this->institution('B');
        $user = $this->member($a);
        $user->institutions()->attach($b->id, ['is_active' => true, 'role' => 'institution_admin']);
        $this->actingAs($user);

        $this->postJson(
            self::ENDPOINT,
            $this->payload($a, ['document_number' => '12345678']),
            $this->headers($a)
        )->assertCreated();
        $this->postJson(
            self::ENDPOINT,
            $this->payload($b, ['document_number' => '12345678']),
            $this->headers($b)
        )->assertCreated();
        $this->assertDatabaseCount('people', 2);
    }

    public function test_duplicate_document_number_in_selected_institution_is_rejected(): void
    {
        $institution = $this->institution();
        $this->person($institution);
        $this->actingAs($this->member($institution));

        $this->postJson(
            self::ENDPOINT,
            $this->payload($institution, ['first_name' => 'Otra']),
            $this->headers($institution)
        )->assertUnprocessable()->assertJsonValidationErrors('document_number');
        $this->assertDatabaseCount('people', 1);
    }

    public function test_invalid_email_is_rejected_for_authorized_user(): void
    {
        $institution = $this->institution();
        $this->actingAs($this->member($institution));

        $this->postJson(
            self::ENDPOINT,
            $this->payload($institution, ['email' => 'invalido']),
            $this->headers($institution)
        )->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_cannot_delete_person_linked_to_student(): void
    {
        $institution = $this->institution();
        $person = $this->person($institution);
        $studentId = $this->student($person);
        $this->actingAs($this->member($institution));

        $this->deleteJson(self::ENDPOINT.'/'.$person->id, [], $this->headers($institution))
            ->assertStatus(409)->assertJsonPath('success', false);
        $this->assertDatabaseHas('people', ['id' => $person->id]);
        $this->assertDatabaseHas('students', ['id' => $studentId, 'person_id' => $person->id]);
    }

    public function test_cannot_delete_person_linked_to_teacher(): void
    {
        $institution = $this->institution();
        $person = $this->person($institution);
        $teacher = $this->teacher($person);
        $this->actingAs($this->member($institution));

        $this->deleteJson(self::ENDPOINT.'/'.$person->id, [], $this->headers($institution))
            ->assertStatus(409)->assertJsonPath('success', false);
        $this->assertDatabaseHas('people', ['id' => $person->id]);
        $this->assertDatabaseHas('teachers', ['id' => $teacher->id, 'person_id' => $person->id]);
    }

    public function test_cannot_move_person_linked_to_student_into_another_institution(): void
    {
        $a = $this->institution('A');
        $b = $this->institution('B');
        $person = $this->person($a);
        $studentId = $this->student($person);
        $user = $this->member($a);
        $user->institutions()->attach($b->id, ['is_active' => true, 'role' => 'institution_admin']);
        $this->actingAs($user);

        $this->putJson(self::ENDPOINT.'/'.$person->id, $this->payload($b), $this->headers($a))
            ->assertUnprocessable()->assertJsonValidationErrors('institution_id');
        $this->assertDatabaseHas('people', ['id' => $person->id, 'institution_id' => $a->id]);
        $this->assertDatabaseHas('students', ['id' => $studentId, 'person_id' => $person->id]);
    }

    public function test_can_update_same_institution_person_linked_to_teacher(): void
    {
        $institution = $this->institution();
        $person = $this->person($institution);
        $teacher = $this->teacher($person);
        $this->actingAs($this->member($institution));

        $this->putJson(
            self::ENDPOINT.'/'.$person->id,
            $this->payload($institution, ['phone' => '912345678']),
            $this->headers($institution)
        )->assertOk()->assertJsonPath('data.phone', '912345678');
        $this->assertDatabaseHas('teachers', ['id' => $teacher->id, 'person_id' => $person->id]);
    }
}
