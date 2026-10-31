<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\Person;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TeacherTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/teachers';

    private function context(string $suffix = 'A', bool $active = true): array
    {
        $institution = Institution::create([
            'name' => 'Instituto Docentes '.$suffix,
            'code' => 'TD-INST-'.$suffix,
            'type' => 'institute',
            'is_active' => $active,
        ]);

        $person = $this->person($institution, '01');

        return compact('institution', 'person');
    }

    private function person(Institution $institution, string $suffix): Person
    {
        return Person::create([
            'institution_id' => $institution->id,
            'first_name' => 'Docente '.$suffix,
            'last_name' => 'Aislamiento',
            'document_type' => 'DNI',
            'document_number' => 'TD-'.$institution->id.'-'.$suffix,
        ]);
    }

    private function payload(array $context, array $changes = []): array
    {
        return array_replace([
            'person_id' => $context['person']->id,
            'institution_id' => $context['institution']->id,
            'employee_code' => 'TD-CODE-'.$context['person']->id,
            'specialization' => 'Programación',
            'is_active' => true,
        ], $changes);
    }

    private function teacher(array $context, array $changes = []): Teacher
    {
        return Teacher::create($this->payload($context, $changes));
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

    private function assignment(Teacher $teacher): int
    {
        $institutionId = $teacher->institution_id;

        $unitId = DB::table('academic_units')->insertGetId([
            'institution_id' => $institutionId,
            'name' => 'Programación Docente',
            'code' => 'TD-UNIT-'.$teacher->id,
            'is_active' => true,
        ]);
        $periodId = DB::table('academic_periods')->insertGetId([
            'institution_id' => $institutionId,
            'name' => 'Periodo Docente',
            'code' => 'TD-PER-'.$teacher->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
        $offeringId = DB::table('academic_offerings')->insertGetId([
            'academic_unit_id' => $unitId,
            'academic_period_id' => $periodId,
            'section' => 'A',
            'capacity' => 30,
            'is_active' => true,
        ]);

        return DB::table('teaching_assignments')->insertGetId([
            'teacher_id' => $teacher->id,
            'academic_offering_id' => $offeringId,
            'role' => 'Titular',
        ]);
    }

    public function test_unauthenticated_user_cannot_list_teachers(): void
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

    public function test_user_without_membership_cannot_access_teachers(): void
    {
        $a = $this->context();
        $this->actingAs(User::factory()->create());
        $this->getJson(self::ENDPOINT, $this->headers($a['institution']))->assertForbidden();
    }

    public function test_inactive_membership_cannot_access_teachers(): void
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

    public function test_index_returns_only_teachers_of_selected_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $own = $this->teacher($a);
        $this->teacher($b);
        $this->actingAs($this->member($a['institution']));

        $this->getJson(self::ENDPOINT, $this->headers($a['institution']))
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);
    }

    public function test_cannot_show_teacher_of_another_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $foreign = $this->teacher($b);
        $this->actingAs($this->member($a['institution']));

        $this->getJson(self::ENDPOINT.'/'.$foreign->id, $this->headers($a['institution']))
            ->assertNotFound();
    }

    public function test_cannot_create_teacher_using_all_foreign_relations(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $this->actingAs($this->member($a['institution']));

        $this->postJson(self::ENDPOINT, $this->payload($b), $this->headers($a['institution']))
            ->assertUnprocessable()->assertJsonValidationErrors('institution_id');
        $this->assertDatabaseCount('teachers', 0);
    }

    public function test_cannot_create_teacher_using_foreign_person(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $this->actingAs($this->member($a['institution']));

        $this->postJson(
            self::ENDPOINT,
            $this->payload($a, ['person_id' => $b['person']->id]),
            $this->headers($a['institution'])
        )->assertUnprocessable()->assertJsonValidationErrors('person_id');
        $this->assertDatabaseCount('teachers', 0);
    }

    public function test_cannot_create_teacher_for_foreign_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $this->actingAs($this->member($a['institution']));

        $this->postJson(
            self::ENDPOINT,
            $this->payload($a, ['institution_id' => $b['institution']->id]),
            $this->headers($a['institution'])
        )->assertUnprocessable()->assertJsonValidationErrors('institution_id');
        $this->assertDatabaseCount('teachers', 0);
    }

    public function test_cannot_update_teacher_of_another_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $foreign = $this->teacher($b);
        $this->actingAs($this->member($a['institution']));

        $this->putJson(
            self::ENDPOINT.'/'.$foreign->id, $this->payload($a),
            $this->headers($a['institution'])
        )->assertNotFound();

        $this->assertDatabaseHas('teachers', [
            'id' => $foreign->id,
            'person_id' => $b['person']->id,
            'institution_id' => $b['institution']->id,
        ]);
    }

    public function test_cannot_delete_teacher_of_another_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $foreign = $this->teacher($b);
        $this->actingAs($this->member($a['institution']));

        $this->deleteJson(
            self::ENDPOINT.'/'.$foreign->id, [], $this->headers($a['institution'])
        )->assertNotFound();

        $this->assertDatabaseHas('teachers', ['id' => $foreign->id]);
    }

    public function test_cannot_move_own_teacher_to_all_foreign_relations(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $own = $this->teacher($a);
        $this->actingAs($this->member($a['institution']));

        $this->putJson(
            self::ENDPOINT.'/'.$own->id, $this->payload($b),
            $this->headers($a['institution'])
        )->assertUnprocessable()->assertJsonValidationErrors('institution_id');

        $this->assertDatabaseHas('teachers', [
            'id' => $own->id,
            'person_id' => $a['person']->id,
            'institution_id' => $a['institution']->id,
        ]);
    }

    public function test_cannot_move_own_teacher_to_foreign_person(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $own = $this->teacher($a);
        $this->actingAs($this->member($a['institution']));

        $this->putJson(
            self::ENDPOINT.'/'.$own->id,
            $this->payload($a, ['person_id' => $b['person']->id]),
            $this->headers($a['institution'])
        )->assertUnprocessable()->assertJsonValidationErrors('person_id');

        $this->assertDatabaseHas('teachers', ['id' => $own->id, 'person_id' => $a['person']->id]);
    }

    public function test_cannot_move_own_teacher_to_foreign_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $own = $this->teacher($a);
        $this->actingAs($this->member($a['institution']));

        $this->putJson(
            self::ENDPOINT.'/'.$own->id,
            $this->payload($a, ['institution_id' => $b['institution']->id]),
            $this->headers($a['institution'])
        )->assertUnprocessable()->assertJsonValidationErrors('institution_id');

        $this->assertDatabaseHas('teachers', [
            'id' => $own->id, 'institution_id' => $a['institution']->id,
        ]);
    }

    public function test_authorized_user_can_create_show_update_and_delete_own_teacher(): void
    {
        $a = $this->context();
        $this->actingAs($this->member($a['institution']));
        $headers = $this->headers($a['institution']);

        $response = $this->postJson(self::ENDPOINT, $this->payload($a), $headers)
            ->assertCreated()
            ->assertJsonPath('data.institution_id', $a['institution']->id);
        $id = (int) $response->json('data.id');

        $this->getJson(self::ENDPOINT.'/'.$id, $headers)
            ->assertOk()->assertJsonPath('data.id', $id);

        $this->putJson(
            self::ENDPOINT.'/'.$id,
            $this->payload($a, ['specialization' => 'Redes']),
            $headers
        )->assertOk()->assertJsonPath('data.specialization', 'Redes');

        $this->deleteJson(self::ENDPOINT.'/'.$id, [], $headers)->assertOk();
        $this->assertDatabaseMissing('teachers', ['id' => $id]);
    }

    public function test_user_with_two_memberships_still_cannot_mix_teachers(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $teacherA = $this->teacher($a);
        $teacherB = $this->teacher($b);

        $user = $this->member($a['institution']);
        $user->institutions()->attach($b['institution']->id, ['is_active' => true, 'role' => 'institution_admin']);
        $this->actingAs($user);

        $this->getJson(self::ENDPOINT, $this->headers($a['institution']))
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $teacherA->id);

        $this->getJson(self::ENDPOINT.'/'.$teacherB->id, $this->headers($a['institution']))
            ->assertNotFound();

        $this->getJson(self::ENDPOINT, $this->headers($b['institution']))
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $teacherB->id);
    }

    public function test_two_memberships_cannot_create_for_unselected_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');

        $user = $this->member($a['institution']);
        $user->institutions()->attach($b['institution']->id, ['is_active' => true, 'role' => 'institution_admin']);
        $this->actingAs($user);

        $this->postJson(
            self::ENDPOINT, $this->payload($b), $this->headers($a['institution'])
        )->assertUnprocessable()->assertJsonValidationErrors('institution_id');

        $this->assertDatabaseCount('teachers', 0);
    }

    public function test_authorized_user_can_create_in_both_institutions_by_switching_header(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');

        $user = $this->member($a['institution']);
        $user->institutions()->attach($b['institution']->id, ['is_active' => true, 'role' => 'institution_admin']);
        $this->actingAs($user);

        $this->postJson(self::ENDPOINT, $this->payload($a), $this->headers($a['institution']))
            ->assertCreated();
        $this->postJson(self::ENDPOINT, $this->payload($b), $this->headers($b['institution']))
            ->assertCreated();

        $this->assertDatabaseCount('teachers', 2);
    }

    public function test_authorized_user_still_cannot_duplicate_employee_code_within_institution(): void
    {
        $a = $this->context('A');
        $this->teacher($a, ['employee_code' => 'TD-DUPLICADO']);
        $otherPerson = $this->person($a['institution'], '02');
        $this->actingAs($this->member($a['institution']));

        $this->postJson(
            self::ENDPOINT,
            $this->payload($a, [
                'person_id' => $otherPerson->id,
                'employee_code' => 'TD-DUPLICADO',
            ]),
            $this->headers($a['institution'])
        )->assertUnprocessable()->assertJsonValidationErrors('employee_code');
        $this->assertDatabaseCount('teachers', 1);
    }

    public function test_same_employee_code_allowed_in_two_institutions(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');

        $user = $this->member($a['institution']);
        $user->institutions()->attach($b['institution']->id, ['is_active' => true, 'role' => 'institution_admin']);
        $this->actingAs($user);

        $this->postJson(
            self::ENDPOINT,
            $this->payload($a, ['employee_code' => 'TD-COMPARTIDO']),
            $this->headers($a['institution'])
        )->assertCreated();
        $this->postJson(
            self::ENDPOINT,
            $this->payload($b, ['employee_code' => 'TD-COMPARTIDO']),
            $this->headers($b['institution'])
        )->assertCreated();
        $this->assertDatabaseCount('teachers', 2);
    }

    public function test_authorized_user_can_register_inactive_teacher(): void
    {
        $a = $this->context();
        $this->actingAs($this->member($a['institution']));

        $this->postJson(
            self::ENDPOINT,
            $this->payload($a, ['is_active' => false]),
            $this->headers($a['institution'])
        )->assertCreated()->assertJsonPath('data.is_active', false);
    }

    public function test_teacher_with_assignments_cannot_change_identity_or_be_deleted(): void
    {
        $a = $this->context();
        $teacher = $this->teacher($a);
        $anotherPerson = $this->person($a['institution'], '02');
        $assignmentId = $this->assignment($teacher);
        $this->actingAs($this->member($a['institution']));
        $headers = $this->headers($a['institution']);

        $this->putJson(
            self::ENDPOINT.'/'.$teacher->id,
            $this->payload($a, ['person_id' => $anotherPerson->id]),
            $headers
        )->assertStatus(409)->assertJsonPath('success', false);

        $this->deleteJson(self::ENDPOINT.'/'.$teacher->id, [], $headers)
            ->assertStatus(409)->assertJsonPath('success', false);

        $this->putJson(
            self::ENDPOINT.'/'.$teacher->id,
            $this->payload($a, ['specialization' => 'Bases de datos', 'is_active' => false]),
            $headers
        )->assertOk()->assertJsonPath('data.specialization', 'Bases de datos');

        $this->assertDatabaseHas('teaching_assignments', [
            'id' => $assignmentId,
            'teacher_id' => $teacher->id,
        ]);
    }
}
