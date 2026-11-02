<?php

namespace Tests\Feature;

use App\Models\AcademicLevel;
use App\Models\AcademicOffering;
use App\Models\AcademicPlan;
use App\Models\AcademicProgram;
use App\Models\AcademicRecord;
use App\Models\Enrollment;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EligibleEnrollmentOfferingsTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;
    private bool $selected = false;

    private function context(string $suffix = 'A', bool $login = true, string $role = 'registrar'): array
    {
        $institution = Institution::create([
            'name' => "Instituto {$suffix}",
            'code' => "INST-{$suffix}",
            'type' => 'institute',
        ]);
        $program = AcademicProgram::create([
            'institution_id' => $institution->id,
            'name' => "Programa {$suffix}",
            'code' => "PROG-{$suffix}",
            'is_active' => true,
        ]);
        $plan = AcademicPlan::create([
            'academic_program_id' => $program->id,
            'name' => "Plan {$suffix}",
            'version' => '1.0',
            'is_active' => true,
        ]);
        $level = AcademicLevel::create([
            'academic_plan_id' => $plan->id,
            'name' => "Ciclo {$suffix}",
            'order' => 1,
            'is_active' => true,
        ]);
        $period = DB::table('academic_periods')->insertGetId([
            'institution_id' => $institution->id,
            'name' => "Periodo {$suffix}",
            'code' => "PER-{$suffix}",
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
        ]);
        $campus = DB::table('campuses')->insertGetId([
            'institution_id' => $institution->id,
            'name' => "Sede {$suffix}",
            'code' => "SED-{$suffix}",
        ]);
        $studentId = $this->student($institution->id);
        $record = AcademicRecord::create([
            'student_id' => $studentId,
            'academic_program_id' => $program->id,
            'academic_plan_id' => $plan->id,
            'start_date' => '2026-01-01',
            'status' => 'active',
        ]);
        $enrollment = Enrollment::create([
            'student_id' => $studentId,
            'academic_period_id' => $period,
            'academic_record_id' => $record->id,
            'enrollment_date' => '2026-03-01',
            'status' => 'active',
        ]);
        $unit = $this->unit($institution->id);
        $this->curriculum($plan->id, $level->id, $unit);
        $offering = AcademicOffering::create([
            'academic_unit_id' => $unit,
            'academic_period_id' => $period,
            'campus_id' => $campus,
            'section' => 'A',
            'capacity' => 2,
            'is_active' => true,
        ]);
        if ($login && !$this->selected) {
            $user = User::factory()->create();
            $user->institutions()->attach($institution->id, [
                'is_active' => true, 'role' => $role,
            ]);
            $this->actingAs($user);
            $this->withHeader('X-Institution-Id', (string) $institution->id);
            $this->selected = true;
        }

        return compact('institution', 'program', 'plan', 'level', 'period', 'campus', 'studentId', 'record', 'enrollment', 'unit', 'offering');
    }

    private function student(int $institutionId): int
    {
        $number = ++$this->seq;
        $personId = DB::table('people')->insertGetId([
            'institution_id' => $institutionId,
            'first_name' => "Alumno {$number}",
            'last_name' => 'Prueba',
            'document_type' => 'DNI',
            'document_number' => "DOC-6B-{$number}",
        ]);
        return DB::table('students')->insertGetId([
            'person_id' => $personId,
            'student_code' => "EST-6B-{$number}",
            'status' => 'active',
        ]);
    }

    private function unit(int $institutionId): int
    {
        $number = ++$this->seq;
        return DB::table('academic_units')->insertGetId([
            'institution_id' => $institutionId,
            'name' => "Curso {$number}",
            'code' => "UNIT-6B-{$number}",
            'credits' => 4,
            'hours' => 64,
            'is_active' => true,
        ]);
    }

    private function curriculum(int $planId, int $levelId, int $unitId): void
    {
        DB::table('academic_curriculum_items')->insert([
            'academic_plan_id' => $planId,
            'academic_level_id' => $levelId,
            'academic_unit_id' => $unitId,
            'credits' => 4,
            'hours' => 64,
            'is_required' => true,
            'position' => ++$this->seq,
        ]);
    }

    private function url(array $context): string
    {
        return '/api/enrollments/'.$context['enrollment']->id.'/available-offerings';
    }

    public function test_guests_cannot_query_offerings(): void
    {
        $c = $this->context('A', false);
        $this->withHeader('X-Institution-Id', (string) $c['institution']->id)
            ->getJson($this->url($c))->assertUnauthorized();
    }

    public function test_institution_role_is_required(): void
    {
        $c = $this->context('A', true, 'teacher');
        $this->getJson($this->url($c))->assertForbidden();
    }

    public function test_missing_institution_header_is_rejected(): void
    {
        $c = $this->context();
        $this->withHeader('X-Institution-Id', '')->getJson($this->url($c))
            ->assertUnprocessable();
    }

    public function test_other_tenant_enrollment_is_not_visible(): void
    {
        $this->context('A');
        $b = $this->context('B');
        $this->getJson($this->url($b))->assertNotFound();
    }

    public function test_only_compatible_plan_and_period_offerings_are_listed(): void
    {
        $c = $this->context();
        $outsidePlan = $this->unit($c['institution']->id);
        $outsidePlanOffer = AcademicOffering::create([
            'academic_unit_id' => $outsidePlan,
            'academic_period_id' => $c['period'],
            'campus_id' => $c['campus'],
            'section' => 'B', 'capacity' => 1, 'is_active' => true,
        ]);
        $otherPeriod = DB::table('academic_periods')->insertGetId([
            'institution_id' => $c['institution']->id,
            'name' => 'Otro periodo', 'code' => 'PER-OTRO',
            'start_date' => '2026-07-01', 'end_date' => '2026-12-31',
        ]);
        $otherPeriodOffer = AcademicOffering::create([
            'academic_unit_id' => $c['unit'],
            'academic_period_id' => $otherPeriod,
            'campus_id' => $c['campus'],
            'section' => 'B', 'capacity' => 1, 'is_active' => true,
        ]);
        $inactive = AcademicOffering::create([
            'academic_unit_id' => $c['unit'],
            'academic_period_id' => $c['period'],
            'campus_id' => $c['campus'],
            'section' => 'C', 'capacity' => 1, 'is_active' => false,
        ]);
        $this->getJson($this->url($c))
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $c['offering']->id)
            ->assertJsonMissing(['id' => $outsidePlanOffer->id])
            ->assertJsonMissing(['id' => $otherPeriodOffer->id])
            ->assertJsonMissing(['id' => $inactive->id]);
    }

    public function test_course_is_not_offered_twice_if_already_registered_even_cancelled(): void
    {
        $c = $this->context();
        DB::table('enrollment_details')->insert([
            'enrollment_id' => $c['enrollment']->id,
            'academic_offering_id' => $c['offering']->id,
            'status' => 'cancelled',
        ]);
        AcademicOffering::create([
            'academic_unit_id' => $c['unit'],
            'academic_period_id' => $c['period'],
            'campus_id' => $c['campus'],
            'section' => 'B', 'capacity' => 5, 'is_active' => true,
        ]);
        $this->getJson($this->url($c))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_occupied_seats_and_full_section_are_reported(): void
    {
        $c = $this->context();
        $c['offering']->update(['capacity' => 1]);
        DB::table('enrollment_details')->insert([
            'enrollment_id' => $c['enrollment']->id,
            'academic_offering_id' => $c['offering']->id,
            'status' => 'active',
        ]);
        // Inscritos en la misma matrícula ya no deben figurar como seleccionables.
        $otherStudent = $this->student($c['institution']->id);
        $otherRecord = AcademicRecord::create([
            'student_id' => $otherStudent,
            'academic_program_id' => $c['program']->id,
            'academic_plan_id' => $c['plan']->id,
            'start_date' => '2026-01-01', 'status' => 'active',
        ]);
        $otherEnrollment = Enrollment::create([
            'student_id' => $otherStudent,
            'academic_period_id' => $c['period'],
            'academic_record_id' => $otherRecord->id,
            'enrollment_date' => '2026-03-01', 'status' => 'active',
        ]);
        // Distinta asignatura del mismo plan para poder consultar una sección llena.
        $unit2 = $this->unit($c['institution']->id);
        $this->curriculum($c['plan']->id, $c['level']->id, $unit2);
        $offering2 = AcademicOffering::create([
            'academic_unit_id' => $unit2,
            'academic_period_id' => $c['period'],
            'campus_id' => $c['campus'],
            'section' => 'A', 'capacity' => 1, 'is_active' => true,
        ]);
        DB::table('enrollment_details')->insert([
            'enrollment_id' => $otherEnrollment->id,
            'academic_offering_id' => $offering2->id,
            'status' => 'active',
        ]);
        $this->getJson($this->url($c))->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $offering2->id)
            ->assertJsonPath('data.0.occupied', 1)
            ->assertJsonPath('data.0.remaining', 0)
            ->assertJsonPath('data.0.can_enroll', false);
    }

    public function test_null_capacity_is_reported_as_unlimited(): void
    {
        $c = $this->context();
        $c['offering']->update(['capacity' => null]);
        $this->getJson($this->url($c))->assertOk()
            ->assertJsonPath('data.0.capacity', null)
            ->assertJsonPath('data.0.remaining', null)
            ->assertJsonPath('data.0.can_enroll', true);
    }

    public function test_cancelled_enrollment_is_not_eligible_for_new_active_courses(): void
    {
        $c = $this->context();
        $c['enrollment']->update(['status' => 'cancelled']);
        $this->getJson($this->url($c))->assertStatus(409);
    }

    public function test_pagination_and_input_limits(): void
    {
        $c = $this->context();
        AcademicOffering::create([
            'academic_unit_id' => $c['unit'],
            'academic_period_id' => $c['period'],
            'campus_id' => $c['campus'],
            'section' => 'B', 'capacity' => 2, 'is_active' => true,
        ]);
        $this->getJson($this->url($c).'?per_page=1')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.last_page', 2);
        $this->getJson($this->url($c).'?per_page=1&page=2')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('meta.current_page', 2);
        $this->getJson($this->url($c).'?per_page=1000')
            ->assertUnprocessable()->assertJsonValidationErrors('per_page');
    }

    public function test_query_does_not_modify_enrollment_or_details(): void
    {
        $c = $this->context();
        $this->getJson($this->url($c))->assertOk();
        $this->assertDatabaseHas('enrollments', ['id' => $c['enrollment']->id, 'status' => 'active']);
        $this->assertDatabaseCount('enrollment_details', 0);
    }
}
