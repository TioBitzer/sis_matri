<?php

namespace Tests\Feature;

use App\Models\AcademicPlan;
use App\Models\AcademicProgram;
use App\Models\AcademicRecord;
use App\Models\Enrollment;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EnrollmentTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/enrollments';

    private function context(string $suffix = 'A', bool $active = true): array
    {
        $institution = Institution::create([
            'name' => 'Instituto Matriculas '.$suffix,
            'code' => 'EN-'.$suffix,
            'type' => 'institute',
            'is_active' => $active,
        ]);

        $program = AcademicProgram::create([
            'institution_id' => $institution->id,
            'name' => 'Programa Matriculas '.$suffix,
            'code' => 'ENPROG-'.$suffix,
            'is_active' => true,
        ]);

        $plan = AcademicPlan::create([
            'academic_program_id' => $program->id,
            'name' => 'Plan Matriculas '.$suffix,
            'version' => '1.0',
            'is_active' => true,
        ]);

        $periodId = DB::table('academic_periods')->insertGetId([
            'institution_id' => $institution->id,
            'name' => 'Periodo 2026 '.$suffix,
            'code' => 'EN-2026-'.$suffix,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $studentId = $this->makeStudent($institution, $suffix);

        $record = AcademicRecord::create([
            'student_id' => $studentId,
            'academic_program_id' => $program->id,
            'academic_plan_id' => $plan->id,
            'start_date' => '2026-01-01',
            'status' => 'active',
        ]);

        return compact('institution', 'program', 'plan', 'periodId', 'studentId', 'record');
    }

    private function makeStudent(Institution $institution, string $suffix): int
    {
        $personId = DB::table('people')->insertGetId([
            'institution_id' => $institution->id,
            'first_name' => 'Estudiante',
            'last_name' => 'Matricula '.$suffix,
            'document_type' => 'DNI',
            'document_number' => 'EN-DOC-'.$institution->id.'-'.$suffix,
        ]);

        return DB::table('students')->insertGetId([
            'person_id' => $personId,
            'student_code' => 'EN-EST-'.$institution->id.'-'.$suffix,
            'status' => 'active',
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

    private function payload(array $context, array $changes = []): array
    {
        return array_replace([
            'student_id' => $context['studentId'],
            'academic_period_id' => $context['periodId'],
            'academic_record_id' => $context['record']->id,
            'enrollment_date' => '2026-03-01',
            'status' => 'active',
        ], $changes);
    }

    private function enrollment(array $context, array $changes = []): Enrollment
    {
        return Enrollment::create($this->payload($context, $changes));
    }

    private function makeDetail(array $context, Enrollment $enrollment): int
    {
        $unitId = DB::table('academic_units')->insertGetId([
            'institution_id' => $context['institution']->id,
            'name' => 'Unidad Matricula',
            'code' => 'EN-UNIT-'.$context['institution']->id,
            'is_active' => true,
        ]);

        $offeringId = DB::table('academic_offerings')->insertGetId([
            'academic_unit_id' => $unitId,
            'academic_period_id' => $enrollment->academic_period_id,
            'section' => 'A',
            'capacity' => 30,
            'is_active' => true,
        ]);

        return DB::table('enrollment_details')->insertGetId([
            'enrollment_id' => $enrollment->id,
            'academic_offering_id' => $offeringId,
            'status' => 'active',
        ]);
    }

    public function test_unauthenticated_user_cannot_list_enrollments(): void
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

    public function test_user_without_membership_cannot_access_enrollments(): void
    {
        $a = $this->context();
        $this->actingAs(User::factory()->create());
        $this->getJson(self::ENDPOINT, $this->headers($a['institution']))->assertForbidden();
    }

    public function test_inactive_membership_cannot_access_enrollments(): void
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

    public function test_index_returns_only_enrollments_of_selected_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $own = $this->enrollment($a);
        $this->enrollment($b);
        $this->actingAs($this->member($a['institution']));

        $this->getJson(self::ENDPOINT, $this->headers($a['institution']))
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);
    }

    public function test_cannot_show_enrollment_of_another_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $foreign = $this->enrollment($b);
        $this->actingAs($this->member($a['institution']));

        $this->getJson(self::ENDPOINT.'/'.$foreign->id, $this->headers($a['institution']))
            ->assertNotFound();
    }

    public function test_cannot_create_enrollment_using_all_foreign_relations(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $this->actingAs($this->member($a['institution']));

        $this->postJson(self::ENDPOINT, $this->payload($b), $this->headers($a['institution']))
            ->assertUnprocessable()->assertJsonValidationErrors('student_id');
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_cannot_create_enrollment_using_foreign_student(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $this->actingAs($this->member($a['institution']));

        $this->postJson(
            self::ENDPOINT,
            $this->payload($a, ['student_id' => $b['studentId']]),
            $this->headers($a['institution'])
        )->assertUnprocessable()->assertJsonValidationErrors('student_id');
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_cannot_create_enrollment_using_foreign_period(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $this->actingAs($this->member($a['institution']));

        $this->postJson(
            self::ENDPOINT,
            $this->payload($a, ['academic_period_id' => $b['periodId']]),
            $this->headers($a['institution'])
        )->assertUnprocessable()->assertJsonValidationErrors('academic_period_id');
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_cannot_create_enrollment_using_foreign_academic_record(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $this->actingAs($this->member($a['institution']));

        $this->postJson(
            self::ENDPOINT,
            $this->payload($a, ['academic_record_id' => $b['record']->id]),
            $this->headers($a['institution'])
        )->assertUnprocessable()->assertJsonValidationErrors('academic_record_id');
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_cannot_update_enrollment_of_another_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $foreign = $this->enrollment($b);
        $this->actingAs($this->member($a['institution']));

        $this->putJson(
            self::ENDPOINT.'/'.$foreign->id,
            $this->payload($a),
            $this->headers($a['institution'])
        )->assertNotFound();

        $this->assertDatabaseHas('enrollments', [
            'id' => $foreign->id,
            'student_id' => $b['studentId'],
            'academic_period_id' => $b['periodId'],
        ]);
    }

    public function test_cannot_delete_enrollment_of_another_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $foreign = $this->enrollment($b);
        $this->actingAs($this->member($a['institution']));

        $this->deleteJson(
            self::ENDPOINT.'/'.$foreign->id, [], $this->headers($a['institution'])
        )->assertNotFound();
        $this->assertDatabaseHas('enrollments', ['id' => $foreign->id]);
    }

    public function test_cannot_move_own_enrollment_to_all_foreign_relations(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $own = $this->enrollment($a);
        $this->actingAs($this->member($a['institution']));

        $this->putJson(
            self::ENDPOINT.'/'.$own->id,
            $this->payload($b),
            $this->headers($a['institution'])
        )->assertUnprocessable()->assertJsonValidationErrors('student_id');

        $this->assertDatabaseHas('enrollments', [
            'id' => $own->id,
            'student_id' => $a['studentId'],
            'academic_period_id' => $a['periodId'],
            'academic_record_id' => $a['record']->id,
        ]);
    }

    public function test_cannot_move_own_enrollment_to_foreign_student(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $own = $this->enrollment($a);
        $this->actingAs($this->member($a['institution']));

        $this->putJson(
            self::ENDPOINT.'/'.$own->id,
            $this->payload($a, ['student_id' => $b['studentId']]),
            $this->headers($a['institution'])
        )->assertUnprocessable()->assertJsonValidationErrors('student_id');
        $this->assertDatabaseHas('enrollments', ['id' => $own->id, 'student_id' => $a['studentId']]);
    }

    public function test_cannot_move_own_enrollment_to_foreign_period(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $own = $this->enrollment($a);
        $this->actingAs($this->member($a['institution']));

        $this->putJson(
            self::ENDPOINT.'/'.$own->id,
            $this->payload($a, ['academic_period_id' => $b['periodId']]),
            $this->headers($a['institution'])
        )->assertUnprocessable()->assertJsonValidationErrors('academic_period_id');
        $this->assertDatabaseHas('enrollments', [
            'id' => $own->id, 'academic_period_id' => $a['periodId'],
        ]);
    }

    public function test_cannot_move_own_enrollment_to_foreign_academic_record(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $own = $this->enrollment($a);
        $this->actingAs($this->member($a['institution']));

        $this->putJson(
            self::ENDPOINT.'/'.$own->id,
            $this->payload($a, ['academic_record_id' => $b['record']->id]),
            $this->headers($a['institution'])
        )->assertUnprocessable()->assertJsonValidationErrors('academic_record_id');
        $this->assertDatabaseHas('enrollments', [
            'id' => $own->id, 'academic_record_id' => $a['record']->id,
        ]);
    }

    public function test_authorized_user_can_create_show_update_and_delete_own_enrollment(): void
    {
        $a = $this->context();
        $this->actingAs($this->member($a['institution']));
        $headers = $this->headers($a['institution']);

        $response = $this->postJson(self::ENDPOINT, $this->payload($a), $headers)
            ->assertCreated()->assertJsonPath('data.student_id', $a['studentId']);
        $id = (int) $response->json('data.id');

        $this->getJson(self::ENDPOINT.'/'.$id, $headers)
            ->assertOk()->assertJsonPath('data.id', $id);

        $this->putJson(
            self::ENDPOINT.'/'.$id,
            $this->payload($a, ['status' => 'completed']),
            $headers
        )->assertOk()->assertJsonPath('data.status', 'completed');

        $this->deleteJson(self::ENDPOINT.'/'.$id, [], $headers)->assertOk();
        $this->assertDatabaseMissing('enrollments', ['id' => $id]);
    }

    public function test_user_with_two_memberships_still_cannot_mix_enrollments(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $enrollmentA = $this->enrollment($a);
        $enrollmentB = $this->enrollment($b);
        $user = $this->member($a['institution']);
        $user->institutions()->attach($b['institution']->id, ['is_active' => true]);
        $this->actingAs($user);

        $this->getJson(self::ENDPOINT, $this->headers($a['institution']))
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $enrollmentA->id);

        $this->getJson(self::ENDPOINT.'/'.$enrollmentB->id, $this->headers($a['institution']))
            ->assertNotFound();

        $this->getJson(self::ENDPOINT, $this->headers($b['institution']))
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $enrollmentB->id);
    }

    public function test_user_with_two_memberships_cannot_create_for_unselected_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $user = $this->member($a['institution']);
        $user->institutions()->attach($b['institution']->id, ['is_active' => true]);
        $this->actingAs($user);

        $this->postJson(
            self::ENDPOINT, $this->payload($b), $this->headers($a['institution'])
        )->assertUnprocessable()->assertJsonValidationErrors('student_id');
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_authorized_user_can_enroll_in_both_institutions_by_switching_header(): void
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
        $this->assertDatabaseCount('enrollments', 2);
    }

    public function test_authorized_user_still_cannot_duplicate_student_and_period(): void
    {
        $a = $this->context();
        $this->enrollment($a);
        $this->actingAs($this->member($a['institution']));

        $this->postJson(self::ENDPOINT, $this->payload($a), $this->headers($a['institution']))
            ->assertUnprocessable()->assertJsonValidationErrors('academic_period_id');
        $this->assertDatabaseCount('enrollments', 1);
    }

    public function test_authorized_user_cannot_change_identity_of_enrollment_with_courses(): void
    {
        $a = $this->context();
        $own = $this->enrollment($a);
        $detailId = $this->makeDetail($a, $own);
        $secondPeriodId = DB::table('academic_periods')->insertGetId([
            'institution_id' => $a['institution']->id,
            'name' => 'Periodo 2027',
            'code' => 'EN-2027',
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
        ]);
        $this->actingAs($this->member($a['institution']));

        $this->putJson(
            self::ENDPOINT.'/'.$own->id,
            $this->payload($a, ['academic_period_id' => $secondPeriodId]),
            $this->headers($a['institution'])
        )->assertStatus(409)->assertJsonPath('success', false);
        $this->assertDatabaseHas('enrollment_details', ['id' => $detailId]);
        $this->assertDatabaseHas('enrollments', [
            'id' => $own->id, 'academic_period_id' => $a['periodId'],
        ]);
    }
}
