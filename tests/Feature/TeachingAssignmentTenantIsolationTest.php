<?php

namespace Tests\Feature;

use App\Models\AcademicOffering;
use App\Models\Institution;
use App\Models\Person;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TeachingAssignmentTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/teaching-assignments';

    private function context(string $suffix = 'A', bool $active = true): array
    {
        $institution = Institution::create([
            'name' => 'Instituto Asignaciones '.$suffix,
            'code' => 'TA-INST-'.$suffix,
            'type' => 'institute',
            'is_active' => $active,
        ]);

        $unitId = DB::table('academic_units')->insertGetId([
            'institution_id' => $institution->id,
            'name' => 'Curso Asignaciones '.$suffix,
            'code' => 'TA-UNIT-'.$suffix,
            'is_active' => true,
        ]);

        $periodId = DB::table('academic_periods')->insertGetId([
            'institution_id' => $institution->id,
            'name' => 'Periodo Asignaciones '.$suffix,
            'code' => 'TA-2026-'.$suffix,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $campusId = DB::table('campuses')->insertGetId([
            'institution_id' => $institution->id,
            'name' => 'Sede Asignaciones '.$suffix,
            'code' => 'TA-CAMP-'.$suffix,
        ]);

        $teacher = $this->teacher($institution, $suffix);
        $offering = AcademicOffering::create([
            'academic_unit_id' => $unitId,
            'academic_period_id' => $periodId,
            'campus_id' => $campusId,
            'section' => 'A',
            'capacity' => 30,
            'is_active' => true,
        ]);

        return compact('institution', 'unitId', 'periodId', 'campusId', 'teacher', 'offering');
    }

    private function teacher(Institution $institution, string $suffix, bool $active = true): Teacher
    {
        $person = Person::create([
            'institution_id' => $institution->id,
            'first_name' => 'Docente '.$suffix,
            'last_name' => 'Aislamiento',
            'document_type' => 'DNI',
            'document_number' => 'TA-DOC-'.$suffix,
        ]);

        return Teacher::create([
            'person_id' => $person->id,
            'institution_id' => $institution->id,
            'employee_code' => 'TA-DOCENTE-'.$suffix,
            'specialization' => 'Programación',
            'is_active' => $active,
        ]);
    }

    private function assignment(array $context): TeachingAssignment
    {
        return TeachingAssignment::create($this->payload($context));
    }

    private function payload(array $context, array $changes = []): array
    {
        return array_replace([
            'teacher_id' => $context['teacher']->id,
            'academic_offering_id' => $context['offering']->id,
            'role' => 'Titular',
        ], $changes);
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

    public function test_unauthenticated_user_cannot_list_assignments(): void
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

    public function test_user_without_membership_cannot_access_assignments(): void
    {
        $a = $this->context();
        $this->actingAs(User::factory()->create());
        $this->getJson(self::ENDPOINT, $this->headers($a['institution']))->assertForbidden();
    }

    public function test_inactive_membership_cannot_access_assignments(): void
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

    public function test_index_returns_only_assignments_of_selected_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $own = $this->assignment($a);
        $this->assignment($b);
        $this->actingAs($this->member($a['institution']));

        $this->getJson(self::ENDPOINT, $this->headers($a['institution']))
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);
    }

    public function test_cannot_show_assignment_of_another_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $foreign = $this->assignment($b);
        $this->actingAs($this->member($a['institution']));

        $this->getJson(self::ENDPOINT.'/'.$foreign->id, $this->headers($a['institution']))
            ->assertNotFound();
    }

    public function test_cannot_create_assignment_using_all_foreign_relations(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $this->actingAs($this->member($a['institution']));

        $this->postJson(self::ENDPOINT, $this->payload($b), $this->headers($a['institution']))
            ->assertUnprocessable()->assertJsonValidationErrors('teacher_id');
        $this->assertDatabaseCount('teaching_assignments', 0);
    }

    public function test_cannot_create_assignment_using_foreign_teacher(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $this->actingAs($this->member($a['institution']));

        $this->postJson(
            self::ENDPOINT,
            $this->payload($a, ['teacher_id' => $b['teacher']->id]),
            $this->headers($a['institution'])
        )->assertUnprocessable()->assertJsonValidationErrors('teacher_id');
        $this->assertDatabaseCount('teaching_assignments', 0);
    }

    public function test_cannot_create_assignment_using_foreign_offering(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $this->actingAs($this->member($a['institution']));

        $this->postJson(
            self::ENDPOINT,
            $this->payload($a, ['academic_offering_id' => $b['offering']->id]),
            $this->headers($a['institution'])
        )->assertUnprocessable()->assertJsonValidationErrors('academic_offering_id');
        $this->assertDatabaseCount('teaching_assignments', 0);
    }

    public function test_cannot_update_assignment_of_another_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $foreign = $this->assignment($b);
        $this->actingAs($this->member($a['institution']));

        $this->putJson(
            self::ENDPOINT.'/'.$foreign->id,
            $this->payload($a),
            $this->headers($a['institution'])
        )->assertNotFound();
        $this->assertDatabaseHas('teaching_assignments', [
            'id' => $foreign->id,
            'teacher_id' => $b['teacher']->id,
            'academic_offering_id' => $b['offering']->id,
        ]);
    }

    public function test_cannot_delete_assignment_of_another_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $foreign = $this->assignment($b);
        $this->actingAs($this->member($a['institution']));

        $this->deleteJson(
            self::ENDPOINT.'/'.$foreign->id, [], $this->headers($a['institution'])
        )->assertNotFound();
        $this->assertDatabaseHas('teaching_assignments', ['id' => $foreign->id]);
    }

    public function test_cannot_move_own_assignment_to_all_foreign_relations(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $own = $this->assignment($a);
        $this->actingAs($this->member($a['institution']));

        $this->putJson(
            self::ENDPOINT.'/'.$own->id,
            $this->payload($b),
            $this->headers($a['institution'])
        )->assertUnprocessable()->assertJsonValidationErrors('teacher_id');
        $this->assertDatabaseHas('teaching_assignments', [
            'id' => $own->id,
            'teacher_id' => $a['teacher']->id,
            'academic_offering_id' => $a['offering']->id,
        ]);
    }

    public function test_cannot_move_own_assignment_to_foreign_teacher(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $own = $this->assignment($a);
        $this->actingAs($this->member($a['institution']));

        $this->putJson(
            self::ENDPOINT.'/'.$own->id,
            $this->payload($a, ['teacher_id' => $b['teacher']->id]),
            $this->headers($a['institution'])
        )->assertUnprocessable()->assertJsonValidationErrors('teacher_id');
        $this->assertDatabaseHas('teaching_assignments', [
            'id' => $own->id, 'teacher_id' => $a['teacher']->id,
        ]);
    }

    public function test_cannot_move_own_assignment_to_foreign_offering(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $own = $this->assignment($a);
        $this->actingAs($this->member($a['institution']));

        $this->putJson(
            self::ENDPOINT.'/'.$own->id,
            $this->payload($a, ['academic_offering_id' => $b['offering']->id]),
            $this->headers($a['institution'])
        )->assertUnprocessable()->assertJsonValidationErrors('academic_offering_id');
        $this->assertDatabaseHas('teaching_assignments', [
            'id' => $own->id, 'academic_offering_id' => $a['offering']->id,
        ]);
    }

    public function test_authorized_user_can_create_show_update_and_delete_own_assignment(): void
    {
        $a = $this->context();
        $this->actingAs($this->member($a['institution']));
        $headers = $this->headers($a['institution']);

        $created = $this->postJson(self::ENDPOINT, $this->payload($a), $headers)
            ->assertCreated()->assertJsonPath('data.teacher_id', $a['teacher']->id);
        $id = (int) $created->json('data.id');

        $this->getJson(self::ENDPOINT.'/'.$id, $headers)
            ->assertOk()->assertJsonPath('data.id', $id);

        $this->putJson(
            self::ENDPOINT.'/'.$id, $this->payload($a, ['role' => 'Auxiliar']), $headers
        )->assertOk()->assertJsonPath('data.role', 'Auxiliar');

        $this->deleteJson(self::ENDPOINT.'/'.$id, [], $headers)->assertOk();
        $this->assertDatabaseMissing('teaching_assignments', ['id' => $id]);
    }

    public function test_user_with_two_memberships_still_cannot_mix_assignments(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $ownA = $this->assignment($a);
        $ownB = $this->assignment($b);

        $user = $this->member($a['institution']);
        $user->institutions()->attach($b['institution']->id, ['is_active' => true]);
        $this->actingAs($user);

        $this->getJson(self::ENDPOINT, $this->headers($a['institution']))
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownA->id);
        $this->getJson(self::ENDPOINT.'/'.$ownB->id, $this->headers($a['institution']))
            ->assertNotFound();
        $this->getJson(self::ENDPOINT, $this->headers($b['institution']))
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownB->id);
    }

    public function test_two_memberships_cannot_create_for_unselected_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $user = $this->member($a['institution']);
        $user->institutions()->attach($b['institution']->id, ['is_active' => true]);
        $this->actingAs($user);

        $this->postJson(
            self::ENDPOINT, $this->payload($b), $this->headers($a['institution'])
        )->assertUnprocessable()->assertJsonValidationErrors('teacher_id');
        $this->assertDatabaseCount('teaching_assignments', 0);
    }

    public function test_authorized_user_can_assign_in_both_institutions_by_switching_header(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $user = $this->member($a['institution']);
        $user->institutions()->attach($b['institution']->id, ['is_active' => true]);
        $this->actingAs($user);

        $this->postJson(self::ENDPOINT, $this->payload($a), $this->headers($a['institution']))
            ->assertCreated();
        $this->postJson(self::ENDPOINT, $this->payload($b), $this->headers($b['institution']))
            ->assertCreated();
        $this->assertDatabaseCount('teaching_assignments', 2);
    }

    public function test_authorized_user_still_cannot_duplicate_teacher_offering_pair(): void
    {
        $a = $this->context();
        $this->assignment($a);
        $this->actingAs($this->member($a['institution']));

        $this->postJson(self::ENDPOINT, $this->payload($a), $this->headers($a['institution']))
            ->assertUnprocessable()->assertJsonValidationErrors('teacher_id');
        $this->assertDatabaseCount('teaching_assignments', 1);
    }

    public function test_authorized_user_cannot_assign_inactive_teacher(): void
    {
        $a = $this->context();
        $a['teacher']->update(['is_active' => false]);
        $this->actingAs($this->member($a['institution']));

        $this->postJson(self::ENDPOINT, $this->payload($a), $this->headers($a['institution']))
            ->assertUnprocessable()->assertJsonValidationErrors('teacher_id');
        $this->assertDatabaseCount('teaching_assignments', 0);
    }

    public function test_authorized_user_cannot_assign_inactive_offering(): void
    {
        $a = $this->context();
        $a['offering']->update(['is_active' => false]);
        $this->actingAs($this->member($a['institution']));

        $this->postJson(self::ENDPOINT, $this->payload($a), $this->headers($a['institution']))
            ->assertUnprocessable()->assertJsonValidationErrors('academic_offering_id');
        $this->assertDatabaseCount('teaching_assignments', 0);
    }

    public function test_authorized_user_can_edit_historical_role_when_links_are_inactive(): void
    {
        $a = $this->context();
        $assignment = $this->assignment($a);
        $a['teacher']->update(['is_active' => false]);
        $a['offering']->update(['is_active' => false]);
        $this->actingAs($this->member($a['institution']));

        $this->putJson(
            self::ENDPOINT.'/'.$assignment->id,
            $this->payload($a, ['role' => 'Asesor']),
            $this->headers($a['institution'])
        )->assertOk()->assertJsonPath('data.role', 'Asesor');
    }
}
