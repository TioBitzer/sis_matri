<?php

namespace Tests\Feature;

use App\Models\AcademicLevel;
use App\Models\AcademicOffering;
use App\Models\AcademicPlan;
use App\Models\AcademicProgram;
use App\Models\AcademicRecord;
use App\Models\Enrollment;
use App\Models\EnrollmentDetail;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EnrollmentDetailTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/enrollment-details';

    private int $sequence = 0;

    private function context(string $suffix = 'A', bool $active = true, int $capacity = 3): array
    {
        $institution = Institution::create([
            'name' => 'Instituto Detalles '.$suffix,
            'code' => 'ED-'.$suffix,
            'type' => 'institute',
            'is_active' => $active,
        ]);

        $program = AcademicProgram::create([
            'institution_id' => $institution->id,
            'name' => 'Programa Detalles '.$suffix,
            'code' => 'ED-PROG-'.$suffix,
            'is_active' => true,
        ]);

        $plan = AcademicPlan::create([
            'academic_program_id' => $program->id,
            'name' => 'Plan Detalles '.$suffix,
            'version' => '1.0',
            'is_active' => true,
        ]);

        $level = AcademicLevel::create([
            'academic_plan_id' => $plan->id,
            'name' => 'Primer ciclo',
            'order' => 1,
            'is_active' => true,
        ]);

        $unitId = $this->makeUnit($institution->id);

        DB::table('academic_curriculum_items')->insert([
            'academic_plan_id' => $plan->id,
            'academic_level_id' => $level->id,
            'academic_unit_id' => $unitId,
            'credits' => 4,
            'hours' => 64,
            'is_required' => true,
            'position' => 1,
        ]);

        $periodId = DB::table('academic_periods')->insertGetId([
            'institution_id' => $institution->id,
            'name' => 'Periodo Detalles '.$suffix,
            'code' => 'ED-2026-'.$suffix,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $campusId = DB::table('campuses')->insertGetId([
            'institution_id' => $institution->id,
            'name' => 'Sede Detalles '.$suffix,
            'code' => 'ED-SEDE-'.$suffix,
        ]);

        $context = compact(
            'institution', 'program', 'plan', 'level', 'unitId', 'periodId', 'campusId'
        );

        $context['enrollment'] = $this->makeEnrollment($context);
        $context['offering'] = $this->makeOffering($context, 'A', $capacity);

        return $context;
    }

    private function makeUnit(int $institutionId): int
    {
        $n = ++$this->sequence;
        return DB::table('academic_units')->insertGetId([
            'institution_id' => $institutionId,
            'name' => 'Asignatura Detalles '.$n,
            'code' => 'ED-UNIT-'.$n,
            'credits' => 4,
            'hours' => 64,
            'is_active' => true,
        ]);
    }

    private function makeEnrollment(array $context): Enrollment
    {
        $n = ++$this->sequence;

        $personId = DB::table('people')->insertGetId([
            'institution_id' => $context['institution']->id,
            'first_name' => 'Estudiante',
            'last_name' => 'Detalles '.$n,
            'document_type' => 'DNI',
            'document_number' => 'ED-DOC-'.$n,
        ]);

        $studentId = DB::table('students')->insertGetId([
            'person_id' => $personId,
            'student_code' => 'ED-EST-'.$n,
            'status' => 'active',
        ]);

        $record = AcademicRecord::create([
            'student_id' => $studentId,
            'academic_program_id' => $context['program']->id,
            'academic_plan_id' => $context['plan']->id,
            'start_date' => '2026-01-01',
            'status' => 'active',
        ]);

        return Enrollment::create([
            'student_id' => $studentId,
            'academic_period_id' => $context['periodId'],
            'academic_record_id' => $record->id,
            'enrollment_date' => '2026-03-01',
            'status' => 'active',
        ]);
    }

    private function makeOffering(
        array $context, string $section = 'B', int $capacity = 3
    ): AcademicOffering {
        return AcademicOffering::create([
            'academic_unit_id' => $context['unitId'],
            'academic_period_id' => $context['periodId'],
            'campus_id' => $context['campusId'],
            'section' => $section,
            'capacity' => $capacity,
            'is_active' => true,
        ]);
    }

    private function detail(array $context, string $status = 'active'): EnrollmentDetail
    {
        return EnrollmentDetail::create($this->payload($context, ['status' => $status]));
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
            'enrollment_id' => $context['enrollment']->id,
            'academic_offering_id' => $context['offering']->id,
            'status' => 'active',
        ], $changes);
    }

    public function test_unauthenticated_user_cannot_list_details(): void
    {
        $a = $this->context();
        $this->getJson(self::ENDPOINT, $this->headers($a['institution']))
            ->assertUnauthorized();
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

    public function test_user_without_membership_cannot_access_details(): void
    {
        $a = $this->context();
        $this->actingAs(User::factory()->create());
        $this->getJson(self::ENDPOINT, $this->headers($a['institution']))
            ->assertForbidden();
    }

    public function test_inactive_membership_cannot_access_details(): void
    {
        $a = $this->context();
        $this->actingAs($this->member($a['institution'], false));
        $this->getJson(self::ENDPOINT, $this->headers($a['institution']))
            ->assertForbidden();
    }

    public function test_inactive_institution_cannot_be_selected(): void
    {
        $a = $this->context('A', false);
        $this->actingAs($this->member($a['institution']));
        $this->getJson(self::ENDPOINT, $this->headers($a['institution']))
            ->assertForbidden();
    }

    public function test_index_returns_only_details_of_selected_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $own = $this->detail($a);
        $this->detail($b);
        $this->actingAs($this->member($a['institution']));

        $this->getJson(self::ENDPOINT, $this->headers($a['institution']))
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);
    }

    public function test_cannot_show_detail_of_another_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $foreign = $this->detail($b);
        $this->actingAs($this->member($a['institution']));

        $this->getJson(self::ENDPOINT.'/'.$foreign->id, $this->headers($a['institution']))
            ->assertNotFound();
    }

    public function test_cannot_create_detail_using_all_foreign_relations(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $this->actingAs($this->member($a['institution']));

        $this->postJson(self::ENDPOINT, $this->payload($b), $this->headers($a['institution']))
            ->assertUnprocessable()->assertJsonValidationErrors('enrollment_id');
        $this->assertDatabaseCount('enrollment_details', 0);
    }

    public function test_cannot_create_detail_using_foreign_enrollment(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $this->actingAs($this->member($a['institution']));

        $this->postJson(
            self::ENDPOINT,
            $this->payload($a, ['enrollment_id' => $b['enrollment']->id]),
            $this->headers($a['institution'])
        )->assertUnprocessable()->assertJsonValidationErrors('enrollment_id');
        $this->assertDatabaseCount('enrollment_details', 0);
    }

    public function test_cannot_create_detail_using_foreign_offering(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $this->actingAs($this->member($a['institution']));

        $this->postJson(
            self::ENDPOINT,
            $this->payload($a, ['academic_offering_id' => $b['offering']->id]),
            $this->headers($a['institution'])
        )->assertUnprocessable()->assertJsonValidationErrors('academic_offering_id');
        $this->assertDatabaseCount('enrollment_details', 0);
    }

    public function test_cannot_create_for_foreign_institution_even_if_relations_match(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $this->actingAs($this->member($a['institution']));

        $this->postJson(
            self::ENDPOINT, $this->payload($b, ['status' => 'cancelled']),
            $this->headers($a['institution'])
        )->assertUnprocessable()->assertJsonValidationErrors('enrollment_id');
        $this->assertDatabaseCount('enrollment_details', 0);
    }

    public function test_cannot_update_detail_of_another_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $foreign = $this->detail($b);
        $this->actingAs($this->member($a['institution']));

        $this->putJson(
            self::ENDPOINT.'/'.$foreign->id, $this->payload($a),
            $this->headers($a['institution'])
        )->assertNotFound();

        $this->assertDatabaseHas('enrollment_details', [
            'id' => $foreign->id,
            'enrollment_id' => $b['enrollment']->id,
            'academic_offering_id' => $b['offering']->id,
        ]);
    }

    public function test_cannot_delete_detail_of_another_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $foreign = $this->detail($b);
        $this->actingAs($this->member($a['institution']));

        $this->deleteJson(
            self::ENDPOINT.'/'.$foreign->id, [], $this->headers($a['institution'])
        )->assertNotFound();

        $this->assertDatabaseHas('enrollment_details', ['id' => $foreign->id]);
    }

    public function test_cannot_move_own_detail_to_all_foreign_relations(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $own = $this->detail($a);
        $this->actingAs($this->member($a['institution']));

        $this->putJson(
            self::ENDPOINT.'/'.$own->id, $this->payload($b),
            $this->headers($a['institution'])
        )->assertUnprocessable()->assertJsonValidationErrors('enrollment_id');

        $this->assertDatabaseHas('enrollment_details', [
            'id' => $own->id,
            'enrollment_id' => $a['enrollment']->id,
            'academic_offering_id' => $a['offering']->id,
        ]);
    }

    public function test_cannot_move_own_detail_to_foreign_enrollment(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $own = $this->detail($a);
        $this->actingAs($this->member($a['institution']));

        $this->putJson(
            self::ENDPOINT.'/'.$own->id,
            $this->payload($a, ['enrollment_id' => $b['enrollment']->id]),
            $this->headers($a['institution'])
        )->assertUnprocessable()->assertJsonValidationErrors('enrollment_id');

        $this->assertDatabaseHas('enrollment_details', [
            'id' => $own->id, 'enrollment_id' => $a['enrollment']->id,
        ]);
    }

    public function test_cannot_move_own_detail_to_foreign_offering(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $own = $this->detail($a);
        $this->actingAs($this->member($a['institution']));

        $this->putJson(
            self::ENDPOINT.'/'.$own->id,
            $this->payload($a, ['academic_offering_id' => $b['offering']->id]),
            $this->headers($a['institution'])
        )->assertUnprocessable()->assertJsonValidationErrors('academic_offering_id');

        $this->assertDatabaseHas('enrollment_details', [
            'id' => $own->id, 'academic_offering_id' => $a['offering']->id,
        ]);
    }

    public function test_authorized_user_can_create_show_update_and_delete_own_detail(): void
    {
        $a = $this->context();
        $this->actingAs($this->member($a['institution']));
        $headers = $this->headers($a['institution']);

        $response = $this->postJson(self::ENDPOINT, $this->payload($a), $headers)
            ->assertCreated()
            ->assertJsonPath('data.enrollment_id', $a['enrollment']->id);
        $id = (int) $response->json('data.id');

        $this->getJson(self::ENDPOINT.'/'.$id, $headers)
            ->assertOk()->assertJsonPath('data.id', $id);

        $this->putJson(
            self::ENDPOINT.'/'.$id,
            $this->payload($a, ['status' => 'completed']),
            $headers
        )->assertOk()->assertJsonPath('data.status', 'completed');

        $this->deleteJson(self::ENDPOINT.'/'.$id, [], $headers)->assertOk();
        $this->assertDatabaseMissing('enrollment_details', ['id' => $id]);
    }

    public function test_user_with_two_memberships_still_cannot_mix_details(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $detailA = $this->detail($a);
        $detailB = $this->detail($b);

        $user = $this->member($a['institution']);
        $user->institutions()->attach($b['institution']->id, ['is_active' => true]);
        $this->actingAs($user);

        $this->getJson(self::ENDPOINT, $this->headers($a['institution']))
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $detailA->id);

        $this->getJson(self::ENDPOINT.'/'.$detailB->id, $this->headers($a['institution']))
            ->assertNotFound();

        $this->getJson(self::ENDPOINT, $this->headers($b['institution']))
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $detailB->id);
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
        )->assertUnprocessable()->assertJsonValidationErrors('enrollment_id');
        $this->assertDatabaseCount('enrollment_details', 0);
    }

    public function test_authorized_user_can_enroll_courses_in_both_institutions_by_switching_header(): void
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

        $this->assertDatabaseCount('enrollment_details', 2);
    }

    public function test_authorized_user_can_transfer_detail_between_own_enrollments(): void
    {
        $a = $this->context();
        $detail = $this->detail($a);
        $otherEnrollment = $this->makeEnrollment($a);
        $this->actingAs($this->member($a['institution']));

        $this->putJson(
            self::ENDPOINT.'/'.$detail->id,
            $this->payload($a, ['enrollment_id' => $otherEnrollment->id]),
            $this->headers($a['institution'])
        )->assertOk()->assertJsonPath('data.enrollment_id', $otherEnrollment->id);
    }

    public function test_authorized_user_still_cannot_duplicate_course_in_same_enrollment(): void
    {
        $a = $this->context();
        $this->detail($a);
        $this->actingAs($this->member($a['institution']));

        $this->postJson(self::ENDPOINT, $this->payload($a), $this->headers($a['institution']))
            ->assertUnprocessable()->assertJsonValidationErrors('academic_offering_id');
        $this->assertDatabaseCount('enrollment_details', 1);
    }

    public function test_authorized_user_still_cannot_exceed_offering_capacity(): void
    {
        $a = $this->context('A', true, 1);
        $this->detail($a);
        $another = $this->makeEnrollment($a);
        $this->actingAs($this->member($a['institution']));

        $this->postJson(
            self::ENDPOINT,
            $this->payload($a, ['enrollment_id' => $another->id]),
            $this->headers($a['institution'])
        )->assertUnprocessable()->assertJsonValidationErrors('academic_offering_id');

        $this->assertDatabaseCount('enrollment_details', 1);
    }
}
