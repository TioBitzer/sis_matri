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
 * Fase 6D: las operaciones se hacen por HTTP real contra los controladores
 * de matrículas. La base de datos usada es la configurada por PHPUnit,
 * NUNCA ejecutar este test apuntando a la base de producción.
 */
class EnrollmentEndToEndTest extends TestCase
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

    public function test_complete_flow_creates_enrollment_registers_course_and_cancels_without_erasing_history(): void
    {
        $c = $this->fixture();
        $this->selectInstitution($c);

        $enrollmentId = $this->createEnrollment($c);
        $url = "/api/enrollments/{$enrollmentId}/available-offerings";

        $this->getJson($url)->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $c['offeringId'])
            ->assertJsonPath('data.0.occupied', 0)
            ->assertJsonPath('data.0.remaining', 1)
            ->assertJsonPath('data.0.can_enroll', true);

        $detailId = $this->registerCourse($enrollmentId, $c['offeringId']);
        $this->assertDatabaseHas('enrollment_details', [
            'id' => $detailId, 'enrollment_id' => $enrollmentId,
            'academic_offering_id' => $c['offeringId'], 'status' => 'active',
        ]);
        $this->getJson($url)->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/enrollments/{$enrollmentId}")
            ->assertOk()->assertJsonPath('data.status', 'active');

        $this->putJson("/api/enrollments/{$enrollmentId}",
            $this->enrollmentPayload($c, ['status' => 'cancelled']))
            ->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertDatabaseHas('enrollments', ['id' => $enrollmentId, 'status' => 'cancelled']);
        $this->assertDatabaseHas('enrollment_details', [
            'id' => $detailId, 'enrollment_id' => $enrollmentId, 'status' => 'cancelled',
        ]);
        $this->assertSame(0, DB::table('enrollment_details')
            ->where('academic_offering_id', $c['offeringId'])->where('status', 'active')->count());
        $this->getJson($url)->assertStatus(409);
    }

    public function test_cancelled_enrollment_releases_last_seat_for_another_student(): void
    {
        $c = $this->fixture();
        $this->selectInstitution($c);
        $first = $this->createEnrollment($c);
        $firstDetail = $this->registerCourse($first, $c['offeringId']);

        [$student2, $record2] = $this->studentAndRecord($c);
        $secondContext = array_replace($c, ['studentId' => $student2, 'recordId' => $record2]);
        $second = $this->createEnrollment($secondContext);
        $this->getJson("/api/enrollments/{$second}/available-offerings")
            ->assertOk()->assertJsonPath('data.0.remaining', 0)
            ->assertJsonPath('data.0.can_enroll', false);

        $this->postJson('/api/enrollment-details', [
            'enrollment_id' => $second, 'academic_offering_id' => $c['offeringId'], 'status' => 'active',
        ])->assertUnprocessable();
        $this->assertDatabaseCount('enrollment_details', 1);

        $this->putJson("/api/enrollments/{$first}",
            $this->enrollmentPayload($c, ['status' => 'cancelled']))->assertOk();
        $this->getJson("/api/enrollments/{$second}/available-offerings")
            ->assertOk()->assertJsonPath('data.0.remaining', 1)
            ->assertJsonPath('data.0.can_enroll', true);

        $secondDetail = $this->registerCourse($second, $c['offeringId']);
        $this->assertDatabaseHas('enrollment_details', ['id' => $firstDetail, 'status' => 'cancelled']);
        $this->assertDatabaseHas('enrollment_details', ['id' => $secondDetail, 'status' => 'active']);
        $this->assertSame(1, DB::table('enrollment_details')
            ->where('academic_offering_id', $c['offeringId'])->where('status', 'active')->count());
    }

    public function test_invalid_period_offering_is_rejected_without_writing_a_detail(): void
    {
        $c = $this->fixture();
        $this->selectInstitution($c);
        $enrollmentId = $this->createEnrollment($c);

        $otherPeriodId = DB::table('academic_periods')->insertGetId([
            'institution_id' => $c['institution']->id,
            'name' => 'Periodo E2E siguiente',
            'code' => 'PER-E2E-OTRO',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
        ]);
        $foreignPeriodOffering = AcademicOffering::create([
            'academic_unit_id' => DB::table('academic_offerings')->where('id', $c['offeringId'])->value('academic_unit_id'),
            'academic_period_id' => $otherPeriodId,
            'campus_id' => $c['campusId'],
            'section' => 'B', 'capacity' => 1, 'is_active' => true,
        ]);
        $this->postJson('/api/enrollment-details', [
            'enrollment_id' => $enrollmentId,
            'academic_offering_id' => $foreignPeriodOffering->id,
            'status' => 'active',
        ])->assertUnprocessable()->assertJsonValidationErrors('academic_offering_id');

        $this->assertDatabaseCount('enrollment_details', 0);
        $this->assertDatabaseHas('enrollments', ['id' => $enrollmentId, 'status' => 'active']);
    }

    public function test_same_subject_in_another_section_cannot_be_registered_twice(): void
    {
        $c = $this->fixture();
        $this->selectInstitution($c);
        $enrollmentId = $this->createEnrollment($c);
        $this->registerCourse($enrollmentId, $c['offeringId']);

        $secondSection = AcademicOffering::create([
            'academic_unit_id' => DB::table('academic_offerings')->where('id', $c['offeringId'])->value('academic_unit_id'),
            'academic_period_id' => $c['periodId'],
            'campus_id' => $c['campusId'],
            'section' => 'B', 'capacity' => 5, 'is_active' => true,
        ]);
        $this->getJson("/api/enrollments/{$enrollmentId}/available-offerings")
            ->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/enrollment-details', [
            'enrollment_id' => $enrollmentId,
            'academic_offering_id' => $secondSection->id,
            'status' => 'active',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('enrollment_details', 1);
    }

    public function test_completed_course_keeps_its_history_when_enrollment_is_cancelled(): void
    {
        $c = $this->fixture();
        $this->selectInstitution($c);
        $enrollmentId = $this->createEnrollment($c);
        $detailId = $this->registerCourse($enrollmentId, $c['offeringId']);
        $this->putJson("/api/enrollment-details/{$detailId}", [
            'enrollment_id' => $enrollmentId,
            'academic_offering_id' => $c['offeringId'],
            'status' => 'completed',
        ])->assertOk();

        $this->putJson("/api/enrollments/{$enrollmentId}",
            $this->enrollmentPayload($c, ['status' => 'cancelled']))->assertOk();
        $this->assertDatabaseHas('enrollment_details', [
            'id' => $detailId,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('enrollments', ['id' => $enrollmentId, 'status' => 'cancelled']);
    }

    public function test_other_institutions_enrollment_is_not_visible_or_mutable(): void
    {
        $a = $this->fixture('A');
        $b = $this->fixture('B');
        $this->selectInstitution($b);
        $otherEnrollment = $this->createEnrollment($b);

        $this->selectInstitution($a);
        $this->getJson("/api/enrollments/{$otherEnrollment}")->assertNotFound();
        $this->getJson("/api/enrollments/{$otherEnrollment}/available-offerings")->assertNotFound();
        $this->postJson('/api/enrollment-details', [
            'enrollment_id' => $otherEnrollment,
            'academic_offering_id' => $b['offeringId'],
            'status' => 'active',
        ])->assertUnprocessable();

        $this->assertDatabaseHas('enrollments', ['id' => $otherEnrollment, 'status' => 'active']);
        $this->assertDatabaseCount('enrollment_details', 0);
    }

    public function test_invalid_student_record_pair_does_not_create_partial_enrollment(): void
    {
        $c = $this->fixture();
        $this->selectInstitution($c);
        [$otherStudent, $otherRecord] = $this->studentAndRecord($c);
        $this->postJson('/api/enrollments', $this->enrollmentPayload($c, [
            'student_id' => $otherStudent,
            'academic_record_id' => $c['recordId'],
        ]))->assertUnprocessable()->assertJsonValidationErrors('academic_record_id');

        $this->assertDatabaseCount('enrollments', 0);
        $this->assertDatabaseCount('enrollment_details', 0);
        $this->assertDatabaseHas('academic_records', ['id' => $otherRecord, 'student_id' => $otherStudent]);
    }
}
