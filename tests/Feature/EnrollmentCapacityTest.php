<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;




class EnrollmentCapacityTest extends TestCase
{
    use RefreshDatabase;

    private function createScenario(): array
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Instituto de Pruebas',
            'code' => 'TEST',
        ]);

        $periodId = DB::table('academic_periods')->insertGetId([
            'institution_id' => $institutionId,
            'name' => '2026-Test',
            'code' => '2026-T',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $unitId = DB::table('academic_units')->insertGetId([
            'institution_id' => $institutionId,
            'name' => 'Programación I',
            'code' => 'PROG1',
            'unit_type' => 'course',
        ]);

        $programId = DB::table('academic_programs')->insertGetId([
            'institution_id' => $institutionId,
            'name' => 'Desarrollo de Software',
            'code' => 'DS',
        ]);

        $planId = DB::table('academic_plans')->insertGetId([
            'academic_program_id' => $programId,
            'name' => 'Plan 2026',
        ]);

        $levelId = DB::table('academic_levels')->insertGetId([
            'academic_plan_id' => $planId,
            'name' => 'Nivel 1',
            'order' => 1,
        ]);

        DB::table('academic_curriculum_items')->insert([
            'academic_plan_id' => $planId,
            'academic_level_id' => $levelId,
            'academic_unit_id' => $unitId,
        ]);

        $offeringId = DB::table('academic_offerings')->insertGetId([
            'academic_unit_id' => $unitId,
            'academic_period_id' => $periodId,
            'section' => 'A',
            'capacity' => 2,
            'is_active' => true,
        ]);

        $enrollmentIds = [];

        for ($i = 1; $i <= 3; $i++) {
            $personId = DB::table('people')->insertGetId([
                'institution_id' => $institutionId,
                'first_name' => 'Estudiante',
                'last_name' => "Prueba {$i}",
                'document_number' => "TEST{$i}",
            ]);

            $studentId = DB::table('students')->insertGetId([
                'person_id' => $personId,
                'student_code' => "EST-TEST-{$i}",
            ]);

            $recordId = DB::table('academic_records')->insertGetId([
                'student_id' => $studentId,
                'academic_program_id' => $programId,
                'academic_plan_id' => $planId,
            ]);

            $enrollmentIds[] = DB::table('enrollments')->insertGetId([
                'student_id' => $studentId,
                'academic_period_id' => $periodId,
                'academic_record_id' => $recordId,
                'enrollment_date' => '2026-10-13',
            ]);
        }

        // Los dos primeros estudiantes ocupan la sección.
        foreach (array_slice($enrollmentIds, 0, 2) as $enrollmentId) {
            DB::table('enrollment_details')->insert([
                'enrollment_id' => $enrollmentId,
                'academic_offering_id' => $offeringId,
                'status' => 'active',
            ]);
        }

        $detailId = DB::table('enrollment_details')
            ->where('enrollment_id', $enrollmentIds[0])
            ->value('id');

        // TENANT_AUTH_CAPACITY_TESTS_V1
        // Los endpoints ahora exigen Sanctum y membresia a la institucion elegida.
        // Se autentica un usuario real de prueba; NO se desactiva ningun middleware.
        $user = \App\Models\User::factory()->create();
        $user->institutions()->attach($institutionId, ['is_active' => true]);
        $this->actingAs($user);
        $this->withHeader('X-Institution-Id', (string) $institutionId);

        return [
            'offering_id' => $offeringId,
            'enrollment_ids' => $enrollmentIds,
            'detail_id' => $detailId,
        ];
    }

    private function activeCount(int $offeringId): int
    {
        return DB::table('enrollment_details')
            ->where('academic_offering_id', $offeringId)
            ->where('status', 'active')
            ->count();
    }

    private function updateDetail(
        int $detailId,
        int $enrollmentId,
        int $offeringId,
        string $status
    ) {
        return $this->putJson(
            "/api/enrollment-details/{$detailId}",
            [
                'enrollment_id' => $enrollmentId,
                'academic_offering_id' => $offeringId,
                'status' => $status,
            ]
        );
    }

    public function test_rejects_enrollment_when_offering_is_full(): void
    {
        $data = $this->createScenario();

        $this->assertSame(2, $this->activeCount($data['offering_id']));

        $this->postJson('/api/enrollment-details', [
            'enrollment_id' => $data['enrollment_ids'][2],
            'academic_offering_id' => $data['offering_id'],
            'status' => 'active',
        ])
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'No hay vacantes disponibles en esta sección.'
            );

        $this->assertDatabaseMissing('enrollment_details', [
            'enrollment_id' => $data['enrollment_ids'][2],
            'academic_offering_id' => $data['offering_id'],
        ]);

        $this->assertSame(2, $this->activeCount($data['offering_id']));
    }

    public function test_updates_existing_enrollment_without_extra_capacity(): void
    {
        $data = $this->createScenario();

        $this->updateDetail(
            $data['detail_id'],
            $data['enrollment_ids'][0],
            $data['offering_id'],
            'active'
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->assertSame(2, $this->activeCount($data['offering_id']));
    }

    public function test_cancellation_releases_a_vacancy(): void
    {
        $data = $this->createScenario();

        $this->updateDetail(
            $data['detail_id'],
            $data['enrollment_ids'][0],
            $data['offering_id'],
            'cancelled'
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(1, $this->activeCount($data['offering_id']));
    }

    public function test_another_student_can_use_released_vacancy(): void
    {
        $data = $this->createScenario();

        $this->updateDetail(
            $data['detail_id'],
            $data['enrollment_ids'][0],
            $data['offering_id'],
            'cancelled'
        )->assertOk();

        $this->postJson('/api/enrollment-details', [
            'enrollment_id' => $data['enrollment_ids'][2],
            'academic_offering_id' => $data['offering_id'],
            'status' => 'active',
        ])->assertSuccessful();

        $this->assertDatabaseHas('enrollment_details', [
            'enrollment_id' => $data['enrollment_ids'][2],
            'academic_offering_id' => $data['offering_id'],
            'status' => 'active',
        ]);

        $this->assertSame(2, $this->activeCount($data['offering_id']));
    }

    public function test_rejects_reactivation_when_offering_is_full(): void
    {
        $data = $this->createScenario();

        // Liberar una vacante.
        $this->updateDetail(
            $data['detail_id'],
            $data['enrollment_ids'][0],
            $data['offering_id'],
            'cancelled'
        )->assertOk();

        // Ocuparla con el tercer estudiante.
        $this->postJson('/api/enrollment-details', [
            'enrollment_id' => $data['enrollment_ids'][2],
            'academic_offering_id' => $data['offering_id'],
            'status' => 'active',
        ])->assertSuccessful();

        // Intentar reactivar al primero con la sección llena.
        $this->updateDetail(
            $data['detail_id'],
            $data['enrollment_ids'][0],
            $data['offering_id'],
            'active'
        )
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'No hay vacantes disponibles en esta sección.'
            );

        $this->assertDatabaseHas('enrollment_details', [
            'enrollment_id' => $data['enrollment_ids'][0],
            'academic_offering_id' => $data['offering_id'],
            'status' => 'cancelled',
        ]);

        $this->assertSame(2, $this->activeCount($data['offering_id']));
    }
}
