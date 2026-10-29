<?php

namespace Tests\Feature;

use App\Models\AcademicPlan;
use App\Models\AcademicProgram;
use App\Models\AcademicRecord;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AcademicRecordTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/academic-records';

    private function context(string $suffix = 'A', bool $active = true): array
    {
        $institution = Institution::create([
            'name' => 'Instituto Historial '.$suffix,
            'code' => 'AR-'.$suffix,
            'type' => 'institute',
            'is_active' => $active,
        ]);

        $program = AcademicProgram::create([
            'institution_id' => $institution->id,
            'name' => 'Programa '.$suffix,
            'code' => 'PR-'.$suffix,
            'is_active' => true,
        ]);

        $plan = AcademicPlan::create([
            'academic_program_id' => $program->id,
            'name' => 'Plan '.$suffix,
            'version' => '1.0',
            'is_active' => true,
        ]);

        $studentId = $this->makeStudent($institution, $suffix);

        return compact('institution', 'program', 'plan', 'studentId');
    }

    private function makeStudent(Institution $institution, string $suffix): int
    {
        $personId = DB::table('people')->insertGetId([
            'institution_id' => $institution->id,
            'first_name' => 'Persona',
            'last_name' => 'Historial '.$suffix,
            'document_number' => 'DOC-AR-'.$suffix,
        ]);

        return DB::table('students')->insertGetId([
            'person_id' => $personId,
            'student_code' => 'EST-AR-'.$suffix,
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
            'academic_program_id' => $context['program']->id,
            'academic_plan_id' => $context['plan']->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
        ], $changes);
    }

    private function record(array $context, array $changes = []): AcademicRecord
    {
        return AcademicRecord::create($this->payload($context, $changes));
    }

    public function test_unauthenticated_user_cannot_list_records(): void
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
        $this->getJson(self::ENDPOINT, ['X-Institution-Id' => 'invalido'])
            ->assertUnprocessable();
    }

    public function test_user_without_membership_cannot_access_records(): void
    {
        $a = $this->context();
        $this->actingAs(User::factory()->create());
        $this->getJson(self::ENDPOINT, $this->headers($a['institution']))->assertForbidden();
    }

    public function test_inactive_membership_cannot_access_records(): void
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

    public function test_index_returns_only_records_of_selected_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $own = $this->record($a);
        $this->record($b);
        $this->actingAs($this->member($a['institution']));

        $this->getJson(self::ENDPOINT, $this->headers($a['institution']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);
    }

    public function test_cannot_show_record_of_another_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $foreign = $this->record($b);
        $this->actingAs($this->member($a['institution']));

        $this->getJson(self::ENDPOINT.'/'.$foreign->id, $this->headers($a['institution']))
            ->assertNotFound();
    }

    public function test_cannot_create_record_using_all_foreign_relations(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $this->actingAs($this->member($a['institution']));

        $this->postJson(self::ENDPOINT, $this->payload($b), $this->headers($a['institution']))
            ->assertUnprocessable()->assertJsonValidationErrors('student_id');
        $this->assertDatabaseCount('academic_records', 0);
    }

    public function test_cannot_create_record_using_foreign_student(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $this->actingAs($this->member($a['institution']));

        $this->postJson(self::ENDPOINT, $this->payload($a, [
            'student_id' => $b['studentId'],
        ]), $this->headers($a['institution']))
            ->assertUnprocessable()->assertJsonValidationErrors('student_id');
        $this->assertDatabaseCount('academic_records', 0);
    }

    public function test_cannot_create_record_using_foreign_program(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $this->actingAs($this->member($a['institution']));

        $this->postJson(self::ENDPOINT, $this->payload($a, [
            'academic_program_id' => $b['program']->id,
            'academic_plan_id' => $b['plan']->id,
        ]), $this->headers($a['institution']))
            ->assertUnprocessable()->assertJsonValidationErrors('academic_program_id');
        $this->assertDatabaseCount('academic_records', 0);
    }

    public function test_cannot_create_record_using_foreign_plan(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $this->actingAs($this->member($a['institution']));

        $this->postJson(self::ENDPOINT, $this->payload($a, [
            'academic_plan_id' => $b['plan']->id,
        ]), $this->headers($a['institution']))
            ->assertUnprocessable()->assertJsonValidationErrors('academic_plan_id');
        $this->assertDatabaseCount('academic_records', 0);
    }

    public function test_cannot_update_record_of_another_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $foreign = $this->record($b);
        $this->actingAs($this->member($a['institution']));

        $this->putJson(self::ENDPOINT.'/'.$foreign->id, $this->payload($a), $this->headers($a['institution']))
            ->assertNotFound();
        $this->assertDatabaseHas('academic_records', [
            'id' => $foreign->id,
            'student_id' => $b['studentId'],
            'status' => 'active',
        ]);
    }

    public function test_cannot_delete_record_of_another_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $foreign = $this->record($b);
        $this->actingAs($this->member($a['institution']));

        $this->deleteJson(self::ENDPOINT.'/'.$foreign->id, [], $this->headers($a['institution']))
            ->assertNotFound();
        $this->assertDatabaseHas('academic_records', ['id' => $foreign->id]);
    }

    public function test_cannot_move_own_record_to_foreign_student(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $own = $this->record($a);
        $this->actingAs($this->member($a['institution']));

        $this->putJson(self::ENDPOINT.'/'.$own->id, $this->payload($a, [
            'student_id' => $b['studentId'],
        ]), $this->headers($a['institution']))
            ->assertUnprocessable()->assertJsonValidationErrors('student_id');
        $this->assertDatabaseHas('academic_records', ['id' => $own->id, 'student_id' => $a['studentId']]);
    }

    public function test_cannot_move_own_record_to_foreign_program(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $own = $this->record($a);
        $this->actingAs($this->member($a['institution']));

        $this->putJson(self::ENDPOINT.'/'.$own->id, $this->payload($a, [
            'academic_program_id' => $b['program']->id,
            'academic_plan_id' => $b['plan']->id,
        ]), $this->headers($a['institution']))
            ->assertUnprocessable()->assertJsonValidationErrors('academic_program_id');
        $this->assertDatabaseHas('academic_records', [
            'id' => $own->id,
            'academic_program_id' => $a['program']->id,
        ]);
    }

    public function test_cannot_move_own_record_to_foreign_plan(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $own = $this->record($a);
        $this->actingAs($this->member($a['institution']));

        $this->putJson(self::ENDPOINT.'/'.$own->id, $this->payload($a, [
            'academic_plan_id' => $b['plan']->id,
        ]), $this->headers($a['institution']))
            ->assertUnprocessable()->assertJsonValidationErrors('academic_plan_id');
        $this->assertDatabaseHas('academic_records', ['id' => $own->id, 'academic_plan_id' => $a['plan']->id]);
    }

    public function test_authorized_user_can_create_show_update_and_delete_own_record(): void
    {
        $a = $this->context();
        $this->actingAs($this->member($a['institution']));
        $headers = $this->headers($a['institution']);

        $response = $this->postJson(self::ENDPOINT, $this->payload($a), $headers)
            ->assertCreated()
            ->assertJsonPath('data.student_id', $a['studentId'])
            ->assertJsonPath('data.academic_program_id', $a['program']->id)
            ->assertJsonPath('data.academic_plan_id', $a['plan']->id);

        $id = $response->json('data.id');
        $this->getJson(self::ENDPOINT.'/'.$id, $headers)
            ->assertOk()->assertJsonPath('data.id', $id);

        $this->putJson(self::ENDPOINT.'/'.$id, $this->payload($a, [
            'status' => 'completed',
        ]), $headers)->assertOk()->assertJsonPath('data.status', 'completed');

        $this->deleteJson(self::ENDPOINT.'/'.$id, [], $headers)->assertOk();
        $this->assertDatabaseMissing('academic_records', ['id' => $id]);
    }

    public function test_user_with_two_memberships_still_cannot_mix_records(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $recordA = $this->record($a);
        $recordB = $this->record($b);
        $user = $this->member($a['institution']);
        $user->institutions()->attach($b['institution']->id, ['is_active' => true]);
        $this->actingAs($user);

        $this->getJson(self::ENDPOINT, $this->headers($a['institution']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $recordA->id);
        $this->getJson(self::ENDPOINT.'/'.$recordB->id, $this->headers($a['institution']))
            ->assertNotFound();
        $this->getJson(self::ENDPOINT, $this->headers($b['institution']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $recordB->id);
    }

    public function test_user_with_two_memberships_cannot_create_for_other_selected_institution(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $user = $this->member($a['institution']);
        $user->institutions()->attach($b['institution']->id, ['is_active' => true]);
        $this->actingAs($user);

        $this->postJson(self::ENDPOINT, $this->payload($b), $this->headers($a['institution']))
            ->assertUnprocessable()->assertJsonValidationErrors('student_id');
        $this->assertDatabaseCount('academic_records', 0);
    }

    public function test_authorized_user_can_register_two_students_from_own_institution(): void
    {
        $a = $this->context();
        $secondStudentId = $this->makeStudent($a['institution'], 'A2');
        $this->actingAs($this->member($a['institution']));

        $this->postJson(self::ENDPOINT, $this->payload($a), $this->headers($a['institution']))
            ->assertCreated();
        $this->postJson(self::ENDPOINT, $this->payload($a, [
            'student_id' => $secondStudentId,
        ]), $this->headers($a['institution']))->assertCreated();
        $this->assertDatabaseCount('academic_records', 2);
    }

    public function test_authorized_user_can_switch_to_another_plan_of_same_institution(): void
    {
        $a = $this->context();
        $own = $this->record($a);
        $otherPlan = AcademicPlan::create([
            'academic_program_id' => $a['program']->id,
            'name' => 'Plan 2027',
            'version' => '2.0',
            'is_active' => true,
        ]);
        $this->actingAs($this->member($a['institution']));

        $this->putJson(self::ENDPOINT.'/'.$own->id, $this->payload($a, [
            'academic_plan_id' => $otherPlan->id,
        ]), $this->headers($a['institution']))
            ->assertOk()->assertJsonPath('data.academic_plan_id', $otherPlan->id);
    }

    public function test_authorized_user_still_cannot_set_invalid_end_date(): void
    {
        $a = $this->context();
        $this->actingAs($this->member($a['institution']));

        $this->postJson(self::ENDPOINT, $this->payload($a, [
            'end_date' => '2025-12-31',
        ]), $this->headers($a['institution']))
            ->assertUnprocessable()->assertJsonValidationErrors('end_date');
    }

    public function test_authorized_user_cannot_change_identity_of_record_with_enrollments(): void
    {
        $a = $this->context();
        $own = $this->record($a);
        $otherStudentId = $this->makeStudent($a['institution'], 'A2');
        $periodId = DB::table('academic_periods')->insertGetId([
            'institution_id' => $a['institution']->id,
            'name' => 'Periodo de prueba',
            'code' => '2026-AR',
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
        ]);
        DB::table('enrollments')->insert([
            'student_id' => $own->student_id,
            'academic_period_id' => $periodId,
            'academic_record_id' => $own->id,
            'enrollment_date' => '2026-03-01',
            'status' => 'active',
        ]);
        $this->actingAs($this->member($a['institution']));

        $this->putJson(self::ENDPOINT.'/'.$own->id, $this->payload($a, [
            'student_id' => $otherStudentId,
        ]), $this->headers($a['institution']))->assertStatus(409);
        $this->assertDatabaseHas('academic_records', ['id' => $own->id, 'student_id' => $a['studentId']]);
    }
}
