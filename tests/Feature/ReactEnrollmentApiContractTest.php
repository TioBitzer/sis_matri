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
 * Fase 6F: contratos HTTP que consume React. No modifica código de producción.
 * Ejecutar únicamente con una base de datos de PRUEBAS (PHPUnit).
 */
class ReactEnrollmentApiContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_the_session_institution_or_enrollment_contract(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
        $this->getJson('/api/me/institutions')->assertUnauthorized();
        $this->getJson('/api/enrollments')->assertUnauthorized();
        $this->getJson('/api/enrollments/1/available-offerings')->assertUnauthorized();
    }

    public function test_institution_picker_has_a_stable_shape_without_tenant_header(): void
    {
        $institution = $this->institution('Picker');
        $user = User::factory()->create();
        $user->institutions()->attach($institution->id, [
            'role' => 'registrar', 'is_active' => true,
        ]);

        $response = $this->actingAs($user)->getJson('/api/me/institutions')
            ->assertOk()->assertJsonCount(1, 'data');
        $response->assertJsonStructure(['data' => [['id', 'name', 'code', 'type', 'role']]])
            ->assertJsonPath('data.0.id', $institution->id)
            ->assertJsonPath('data.0.role', 'registrar');
        $this->assertArrayNotHasKey('person_id', $response->json('data.0'));
    }

    public function test_missing_tenant_header_is_a_422_json_error(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/enrollments')
            ->assertUnprocessable()
            ->assertJsonStructure(['message']);
    }

    public function test_institution_header_cannot_select_a_foreign_tenant(): void
    {
        $mine = $this->institution('Mine');
        $foreign = $this->institution('Foreign');
        $user = User::factory()->create();
        $user->institutions()->attach($mine->id, [
            'role' => 'registrar', 'is_active' => true,
        ]);

        $this->actingAs($user)
            ->withHeader('X-Institution-Id', (string) $foreign->id)
            ->getJson('/api/enrollments')
            ->assertForbidden()->assertJsonStructure(['message']);
    }

    public function test_member_role_cannot_read_administrative_enrollments(): void
    {
        $institution = $this->institution('Member');
        $user = User::factory()->create();
        $user->institutions()->attach($institution->id, [
            'role' => 'member', 'is_active' => true,
        ]);

        $this->actingAs($user)
            ->withHeader('X-Institution-Id', (string) $institution->id)
            ->getJson('/api/enrollments')
            ->assertForbidden();
    }

    public function test_form_errors_have_the_laravel_validation_bag(): void
    {
        $institution = $this->institution('Validation');
        $this->asRegistrar($institution);

        $this->postJson('/api/enrollments', [])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonValidationErrors([
                'student_id', 'academic_period_id', 'academic_record_id',
                'enrollment_date', 'status',
            ]);
    }

    public function test_eligible_offerings_use_data_and_pagination_meta(): void
    {
        $context = $this->academicFixture();
        $this->asRegistrar($context['institution']);
        $enrollmentId = $this->createEnrollment($context);

        $response = $this->getJson("/api/enrollments/{$enrollmentId}/available-offerings?per_page=1")
            ->assertOk()->assertJsonPath('success', true)->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'success', 'data' => [[
                    'id', 'academic_unit', 'campus', 'section', 'capacity',
                    'occupied', 'remaining', 'can_enroll',
                ]],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('data.0.id', $context['offeringId'])
            ->assertJsonPath('data.0.occupied', 0)
            ->assertJsonPath('data.0.remaining', 1)
            ->assertJsonPath('data.0.can_enroll', true)
            ->assertJsonPath('meta.per_page', 1);

        $this->assertSame(1, $response->json('meta.total'));
        $this->getJson("/api/enrollments/{$enrollmentId}/available-offerings?per_page=51")
            ->assertUnprocessable()->assertJsonValidationErrors('per_page');
    }

    public function test_create_register_and_cancel_keep_the_documented_response_shapes(): void
    {
        $context = $this->academicFixture();
        $this->asRegistrar($context['institution']);
        $enrollmentId = $this->createEnrollment($context);

        $this->getJson("/api/enrollments/{$enrollmentId}")
            ->assertOk()->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'active');

        $detail = $this->postJson('/api/enrollment-details', [
            'enrollment_id' => $enrollmentId,
            'academic_offering_id' => $context['offeringId'],
            'status' => 'active',
        ])->assertCreated()->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'data' => ['id', 'status']]);

        $detailId = (int) $detail->json('data.id');
        $this->putJson("/api/enrollments/{$enrollmentId}", $this->payload($context, 'cancelled'))
            ->assertOk()->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('enrollment_details', ['id' => $detailId, 'status' => 'cancelled']);
        $this->getJson("/api/enrollments/{$enrollmentId}/available-offerings")
            ->assertStatus(409)->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message']);
    }

    private function institution(string $suffix): Institution
    {
        return Institution::create([
            'name' => "Institucion Contrato {$suffix}",
            'code' => "F6F-{$suffix}",
            'type' => 'institute',
            'is_active' => true,
        ]);
    }

    private function asRegistrar(Institution $institution): void
    {
        $user = User::factory()->create();
        $user->institutions()->attach($institution->id, [
            'is_active' => true, 'role' => 'registrar',
        ]);
        $this->actingAs($user);
        $this->withHeader('X-Institution-Id', (string) $institution->id);
    }

    private function academicFixture(): array
    {
        $institution = $this->institution('Academic');
        $program = AcademicProgram::create([
            'institution_id' => $institution->id, 'name' => 'Programa F6F',
            'code' => 'PROG-F6F', 'is_active' => true,
        ]);
        $plan = AcademicPlan::create([
            'academic_program_id' => $program->id, 'name' => 'Plan F6F',
            'version' => '1.0', 'is_active' => true,
        ]);
        $level = AcademicLevel::create([
            'academic_plan_id' => $plan->id, 'name' => 'Nivel F6F',
            'order' => 1, 'is_active' => true,
        ]);
        $periodId = DB::table('academic_periods')->insertGetId([
            'institution_id' => $institution->id, 'name' => 'Periodo F6F',
            'code' => 'PER-F6F', 'start_date' => '2026-01-01',
            'end_date' => '2026-06-30', 'is_active' => true,
        ]);
        $campusId = DB::table('campuses')->insertGetId([
            'institution_id' => $institution->id, 'name' => 'Campus F6F',
            'code' => 'CAM-F6F',
        ]);
        $personId = DB::table('people')->insertGetId([
            'institution_id' => $institution->id, 'first_name' => 'Alumno',
            'last_name' => 'Contrato', 'document_type' => 'DNI',
            'document_number' => 'DNI-F6F-001',
        ]);
        $studentId = DB::table('students')->insertGetId([
            'person_id' => $personId, 'student_code' => 'EST-F6F',
            'status' => 'active',
        ]);
        $record = AcademicRecord::create([
            'student_id' => $studentId, 'academic_program_id' => $program->id,
            'academic_plan_id' => $plan->id, 'start_date' => '2026-01-01',
            'status' => 'active',
        ]);
        $unitId = DB::table('academic_units')->insertGetId([
            'institution_id' => $institution->id, 'name' => 'Curso F6F',
            'code' => 'UNIT-F6F', 'credits' => 4, 'hours' => 64,
            'is_active' => true,
        ]);
        DB::table('academic_curriculum_items')->insert([
            'academic_plan_id' => $plan->id, 'academic_level_id' => $level->id,
            'academic_unit_id' => $unitId, 'credits' => 4, 'hours' => 64,
            'is_required' => true, 'position' => 1,
        ]);
        $offering = AcademicOffering::create([
            'academic_unit_id' => $unitId, 'academic_period_id' => $periodId,
            'campus_id' => $campusId, 'section' => 'A',
            'capacity' => 1, 'is_active' => true,
        ]);
        return [
            'institution' => $institution, 'studentId' => $studentId,
            'periodId' => $periodId, 'recordId' => $record->id,
            'offeringId' => $offering->id,
        ];
    }

    private function payload(array $context, string $status): array
    {
        return [
            'student_id' => $context['studentId'],
            'academic_period_id' => $context['periodId'],
            'academic_record_id' => $context['recordId'],
            'enrollment_date' => '2026-03-01',
            'status' => $status,
        ];
    }

    private function createEnrollment(array $context): int
    {
        $response = $this->postJson('/api/enrollments', $this->payload($context, 'active'))
            ->assertCreated()->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'data' => ['id', 'status']]);
        return (int) $response->json('data.id');
    }
}
