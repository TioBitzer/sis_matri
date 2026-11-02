<?php

namespace Tests\Feature;

use App\Models\AcademicLevel;
use App\Models\AcademicOffering;
use App\Models\AcademicPlan;
use App\Models\AcademicProgram;
use App\Models\AcademicRecord;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fase 6E: validar estados académicos con llamadas HTTP reales.
 * Usa exclusivamente la base de datos de pruebas configurada en PHPUnit.
 */
class EnrollmentAcademicStateTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    private function fixture(string $suffix = 'A'): array
    {
        $institution = Institution::create([
            'name' => "Instituto E2E {$suffix}",
            'code' => "E2E-{$suffix}",
            'type' => 'institute',
        ]);

        $program = AcademicProgram::create([
            'institution_id' => $institution->id,
            'name' => "Desarrollo de Software {$suffix}",
            'code' => "PROG-E2E-{$suffix}",
            'is_active' => true,
        ]);
        $plan = AcademicPlan::create([
            'academic_program_id' => $program->id,
            'name' => "Plan E2E {$suffix}",
            'version' => '1.0',
            'is_active' => true,
        ]);
        $level = AcademicLevel::create([
            'academic_plan_id' => $plan->id,
            'name' => "Primer ciclo {$suffix}",
            'order' => 1,
            'is_active' => true,
        ]);
        $periodId = DB::table('academic_periods')->insertGetId([
            'institution_id' => $institution->id,
            'name' => "Periodo E2E {$suffix}",
            'code' => "PER-E2E-{$suffix}",
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
        ]);
        $campusId = DB::table('campuses')->insertGetId([
            'institution_id' => $institution->id,
            'name' => "Campus {$suffix}",
            'code' => "CAM-E2E-{$suffix}",
        ]);

        $context = compact('institution', 'program', 'plan', 'level', 'periodId', 'campusId');
        [$studentId, $recordId] = $this->studentAndRecord($context);
        $context['studentId'] = $studentId;
        $context['recordId'] = $recordId;
        $context['offeringId'] = $this->addOffering($context, 'A', 1);

        return $context;
    }

    private function studentAndRecord(array $c): array
    {
        $n = ++$this->sequence;
        $personId = DB::table('people')->insertGetId([
            'institution_id' => $c['institution']->id,
            'first_name' => 'Estudiante',
            'last_name' => "E2E {$n}",
            'document_type' => 'DNI',
            'document_number' => "DOC-E2E-{$n}",
        ]);
        $studentId = DB::table('students')->insertGetId([
            'person_id' => $personId,
            'student_code' => "EST-E2E-{$n}",
            'status' => 'active',
        ]);
        $record = AcademicRecord::create([
            'student_id' => $studentId,
            'academic_program_id' => $c['program']->id,
            'academic_plan_id' => $c['plan']->id,
            'start_date' => '2026-01-01',
            'status' => 'active',
        ]);

        return [$studentId, $record->id];
    }

    /** Crea una asignatura del plan, con una sección en el mismo periodo. */
    private function addOffering(array $c, string $section, int $capacity = 1): int
    {
        $n = ++$this->sequence;
        $unitId = DB::table('academic_units')->insertGetId([
            'institution_id' => $c['institution']->id,
            'name' => "Asignatura E2E {$n}",
            'code' => "UNIT-E2E-{$n}",
            'credits' => 4,
            'hours' => 64,
            'is_active' => true,
        ]);
        DB::table('academic_curriculum_items')->insert([
            'academic_plan_id' => $c['plan']->id,
            'academic_level_id' => $c['level']->id,
            'academic_unit_id' => $unitId,
            'credits' => 4,
            'hours' => 64,
            'is_required' => true,
            'position' => $n,
        ]);

        return (int) AcademicOffering::create([
            'academic_unit_id' => $unitId,
            'academic_period_id' => $c['periodId'],
            'campus_id' => $c['campusId'],
            'section' => $section,
            'capacity' => $capacity,
            'is_active' => true,
        ])->id;
    }

    private function selectInstitution(array $c): void
    {
        $user = User::factory()->create();
        $user->institutions()->attach($c['institution']->id, [
            'is_active' => true,
            'role' => 'registrar',
        ]);
        $this->actingAs($user);
        $this->withHeader('X-Institution-Id', (string) $c['institution']->id);
    }

    private function enrollmentPayload(array $c, array $overrides = []): array
    {
        return array_replace([
            'student_id' => $c['studentId'],
            'academic_period_id' => $c['periodId'],
            'academic_record_id' => $c['recordId'],
            'enrollment_date' => '2026-03-01',
            'status' => 'active',
        ], $overrides);
    }

    private function createEnrollment(array $c, array $overrides = []): int
    {
        $response = $this->postJson('/api/enrollments', $this->enrollmentPayload($c, $overrides));
        $response->assertCreated()->assertJsonPath('success', true);
        return (int) $response->json('data.id');
    }

    private function registerCourse(int $enrollmentId, int $offeringId): int
    {
        $response = $this->postJson('/api/enrollment-details', [
            'enrollment_id' => $enrollmentId,
            'academic_offering_id' => $offeringId,
            'status' => 'active',
        ]);
        $response->assertCreated()->assertJsonPath('success', true);
        return (int) $response->json('data.id');
    }

    public function test_inactive_period_rejects_new_active_enrollment_without_writing(): void
    {
        $c = $this->fixture();
        $this->selectInstitution($c);
        DB::table('academic_periods')->where('id', $c['periodId'])->update(['is_active' => false]);

        $this->postJson('/api/enrollments', $this->enrollmentPayload($c))
            ->assertUnprocessable()->assertJsonValidationErrors('academic_period_id');
        $this->assertDatabaseMissing('enrollments', [
            'student_id' => $c['studentId'], 'academic_period_id' => $c['periodId'],
        ]);
    }

    public function test_inactive_period_rejects_new_active_course_without_writing(): void
    {
        $c = $this->fixture();
        $this->selectInstitution($c);
        $enrollment = $this->createEnrollment($c);
        DB::table('academic_periods')->where('id', $c['periodId'])->update(['is_active' => false]);

        $this->postJson('/api/enrollment-details', [
            'enrollment_id' => $enrollment,
            'academic_offering_id' => $c['offeringId'],
            'status' => 'active',
        ])->assertUnprocessable()->assertJsonValidationErrors('academic_period_id');
        $this->assertDatabaseCount('enrollment_details', 0);
    }

    public function test_inactive_period_does_not_offer_sections_in_react_lookup(): void
    {
        $c = $this->fixture();
        $this->selectInstitution($c);
        $enrollment = $this->createEnrollment($c);
        DB::table('academic_periods')->where('id', $c['periodId'])->update(['is_active' => false]);

        $this->getJson("/api/enrollments/{$enrollment}/available-offerings")
            ->assertStatus(409)->assertJsonPath('success', false);
    }

    public function test_cancellation_remains_possible_after_period_closes_and_preserves_history(): void
    {
        $c = $this->fixture();
        $this->selectInstitution($c);
        $enrollment = $this->createEnrollment($c);
        $detail = $this->registerCourse($enrollment, $c['offeringId']);
        DB::table('academic_periods')->where('id', $c['periodId'])->update(['is_active' => false]);

        $this->putJson("/api/enrollments/{$enrollment}",
            $this->enrollmentPayload($c, ['status' => 'cancelled']))
            ->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertDatabaseHas('enrollment_details', [
            'id' => $detail, 'status' => 'cancelled',
        ]);
    }

    public function test_reactivating_cancelled_course_in_closed_period_is_rejected(): void
    {
        $c = $this->fixture();
        $this->selectInstitution($c);
        $enrollment = $this->createEnrollment($c);
        $detail = $this->registerCourse($enrollment, $c['offeringId']);
        $data = [
            'enrollment_id' => $enrollment,
            'academic_offering_id' => $c['offeringId'],
            'status' => 'cancelled',
        ];
        $this->putJson("/api/enrollment-details/{$detail}", $data)->assertOk();
        DB::table('academic_periods')->where('id', $c['periodId'])->update(['is_active' => false]);

        $this->putJson("/api/enrollment-details/{$detail}", array_replace($data, ['status' => 'active']))
            ->assertUnprocessable()->assertJsonValidationErrors('academic_period_id');
        $this->assertDatabaseHas('enrollment_details', ['id' => $detail, 'status' => 'cancelled']);
    }

    public function test_inactive_offering_cannot_receive_active_course(): void
    {
        $c = $this->fixture();
        $this->selectInstitution($c);
        $enrollment = $this->createEnrollment($c);
        DB::table('academic_offerings')->where('id', $c['offeringId'])->update(['is_active' => false]);
        $this->postJson('/api/enrollment-details', [
            'enrollment_id' => $enrollment,
            'academic_offering_id' => $c['offeringId'],
            'status' => 'active',
        ])->assertUnprocessable()->assertJsonValidationErrors('academic_offering_id');
        $this->assertDatabaseCount('enrollment_details', 0);
    }

    public function test_cancelled_enrollment_cannot_receive_new_active_course(): void
    {
        $c = $this->fixture();
        $this->selectInstitution($c);
        $enrollment = $this->createEnrollment($c);
        $this->putJson("/api/enrollments/{$enrollment}",
            $this->enrollmentPayload($c, ['status' => 'cancelled']))->assertOk();

        $this->postJson('/api/enrollment-details', [
            'enrollment_id' => $enrollment,
            'academic_offering_id' => $c['offeringId'],
            'status' => 'active',
        ])->assertUnprocessable()->assertJsonValidationErrors('enrollment_id');
        $this->assertDatabaseCount('enrollment_details', 0);
    }

    public function test_closed_period_prevents_reactivation_of_pending_enrollment(): void
    {
        $c = $this->fixture();
        $this->selectInstitution($c);
        $enrollment = $this->createEnrollment($c, ['status' => 'pending']);
        DB::table('academic_periods')->where('id', $c['periodId'])->update(['is_active' => false]);

        $this->putJson("/api/enrollments/{$enrollment}",
            $this->enrollmentPayload($c, ['status' => 'active']))
            ->assertUnprocessable()->assertJsonValidationErrors('academic_period_id');
        $this->assertDatabaseHas('enrollments', ['id' => $enrollment, 'status' => 'pending']);
    }
}
