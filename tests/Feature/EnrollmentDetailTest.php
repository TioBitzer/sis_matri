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

class EnrollmentDetailTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/enrollment-details';

    private int $sequence = 0;

    private ?int $selectedInstitutionId = null;

    private function context(string $suffix = 'A', int $capacity = 3): array
    {
        $institution = Institution::create([
            'name' => "Instituto {$suffix}",
            'code' => "INST-{$suffix}",
            'type' => 'institute',
        ]);
        $program = AcademicProgram::create([
            'institution_id' => $institution->id,
            'name' => "Desarrollo de Software {$suffix}",
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
            'name' => 'Primer ciclo',
            'order' => 1,
            'is_active' => true,
        ]);
        $unitId = $this->makeUnit($institution->id);
        $this->addToPlan($plan->id, $level->id, $unitId, 1);

        $periodId = DB::table('academic_periods')->insertGetId([
            'institution_id' => $institution->id,
            'name' => "Periodo {$suffix}",
            'code' => "2026-{$suffix}",
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
        ]);
        $campusId = DB::table('campuses')->insertGetId([
            'institution_id' => $institution->id,
            'name' => "Sede {$suffix}",
            'code' => "SEDE-{$suffix}",
        ]);

        $context = compact('institution', 'program', 'plan', 'level', 'unitId', 'periodId', 'campusId');
        $context['enrollment'] = $this->makeEnrollment($context);
        $context['offering'] = $this->makeOffering($context, 'A', $capacity);

        if ($this->selectedInstitutionId === null) {
            $this->selectedInstitutionId = $institution->id;
            $user = User::factory()->create();
            $user->institutions()->attach($institution->id, ['is_active' => true, 'role' => 'registrar']);
            $this->actingAs($user);
            $this->withHeader('X-Institution-Id', (string) $institution->id);
        }

        return $context;
    }

    private function makeUnit(int $institutionId): int
    {
        $number = ++$this->sequence;
        return DB::table('academic_units')->insertGetId([
            'institution_id' => $institutionId,
            'name' => "Asignatura {$number}",
            'code' => "UNI-{$number}",
            'credits' => 4,
            'hours' => 64,
            'is_active' => true,
        ]);
    }

    private function addToPlan(int $planId, int $levelId, int $unitId, int $position): int
    {
        return DB::table('academic_curriculum_items')->insertGetId([
            'academic_plan_id' => $planId,
            'academic_level_id' => $levelId,
            'academic_unit_id' => $unitId,
            'credits' => 4,
            'hours' => 64,
            'is_required' => true,
            'position' => $position,
        ]);
    }

    private function makeEnrollment(array $context, string $status = 'active'): Enrollment
    {
        $number = ++$this->sequence;
        $personId = DB::table('people')->insertGetId([
            'institution_id' => $context['institution']->id,
            'first_name' => "Estudiante {$number}",
            'last_name' => 'Prueba',
            'document_type' => 'DNI',
            'document_number' => "DOC{$number}",
        ]);
        $studentId = DB::table('students')->insertGetId([
            'person_id' => $personId,
            'student_code' => "EST{$number}",
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
            'status' => $status,
        ]);
    }

    private function makeOffering(
        array $context,
        string $section = 'B',
        ?int $capacity = 3,
        bool $active = true,
        array $changes = []
    ): AcademicOffering {
        return AcademicOffering::create(array_replace([
            'academic_unit_id' => $context['unitId'],
            'academic_period_id' => $context['periodId'],
            'campus_id' => $context['campusId'],
            'section' => $section,
            'capacity' => $capacity,
            'is_active' => $active,
        ], $changes));
    }

    private function makeOtherCourse(array $context, string $section = 'C'): AcademicOffering
    {
        $unitId = $this->makeUnit($context['institution']->id);
        $this->addToPlan($context['plan']->id, $context['level']->id, $unitId, ++$this->sequence);
        return $this->makeOffering($context, $section, 3, true, ['academic_unit_id' => $unitId]);
    }

    private function payload(array $context, array $changes = []): array
    {
        return array_replace([
            'enrollment_id' => $context['enrollment']->id,
            'academic_offering_id' => $context['offering']->id,
            'status' => 'active',
        ], $changes);
    }

    private function detail(array $context, string $status = 'active', array $changes = []): EnrollmentDetail
    {
        return EnrollmentDetail::create($this->payload($context, array_replace([
            'status' => $status,
        ], $changes)));
    }

    private function updateDetail(EnrollmentDetail $detail, array $context, array $changes = [])
    {
        return $this->putJson(self::ENDPOINT . "/{$detail->id}", $this->payload($context, $changes));
    }

    public function test_puede_crear_detalle_activo_con_todas_sus_relaciones(): void
    {
        $c = $this->context();
        $this->postJson(self::ENDPOINT, $this->payload($c))
            ->assertStatus(201)->assertJsonPath('success', true)
            ->assertJsonPath('data.enrollment_id', $c['enrollment']->id)
            ->assertJsonPath('data.academic_offering_id', $c['offering']->id)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.enrollment.student.person.institution_id', $c['institution']->id)
            ->assertJsonPath('data.academic_offering.academic_unit.id', $c['unitId'])
            ->assertJsonPath('data.academic_offering.academic_period.id', $c['periodId']);
        $this->assertDatabaseCount('enrollment_details', 1);
    }

    public function test_puede_crear_detalle_cancelado(): void
    {
        $c = $this->context();
        $this->postJson(self::ENDPOINT, $this->payload($c, ['status' => 'cancelled']))
            ->assertStatus(201)->assertJsonPath('data.status', 'cancelled');
    }

    public function test_puede_crear_detalle_completado(): void
    {
        $c = $this->context();
        $this->postJson(self::ENDPOINT, $this->payload($c, ['status' => 'completed']))
            ->assertStatus(201)->assertJsonPath('data.status', 'completed');
    }

    public function test_lista_detalles_con_relaciones(): void
    {
        $c = $this->context();
        $this->detail($c);
        $this->getJson(self::ENDPOINT)
            ->assertStatus(200)->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.enrollment.id', $c['enrollment']->id)
            ->assertJsonPath('data.0.academic_offering.campus.id', $c['campusId']);
    }

    public function test_lista_vacia_sin_detalles(): void
    {
        $this->context();
        $this->getJson(self::ENDPOINT)->assertStatus(200)->assertJsonCount(0, 'data');
    }

    public function test_puede_consultar_detalle(): void
    {
        $c = $this->context();
        $d = $this->detail($c);
        $this->getJson(self::ENDPOINT . "/{$d->id}")
            ->assertStatus(200)->assertJsonPath('data.id', $d->id)
            ->assertJsonPath('data.enrollment.academic_record.academic_plan.id', $c['plan']->id);
    }

    public function test_rechaza_creacion_sin_campos_obligatorios(): void
    {
        $this->context();
        $this->postJson(self::ENDPOINT, [])->assertStatus(422)
            ->assertJsonValidationErrors(['enrollment_id', 'academic_offering_id', 'status']);
    }

    public function test_rechaza_matricula_inexistente(): void
    {
        $c = $this->context();
        $this->postJson(self::ENDPOINT, $this->payload($c, ['enrollment_id' => 99999]))
            ->assertStatus(422)->assertJsonValidationErrors('enrollment_id');
    }

    public function test_rechaza_oferta_inexistente(): void
    {
        $c = $this->context();
        $this->postJson(self::ENDPOINT, $this->payload($c, ['academic_offering_id' => 99999]))
            ->assertStatus(422)->assertJsonValidationErrors('academic_offering_id');
    }

    public function test_rechaza_matricula_no_numerica(): void
    {
        $c = $this->context();
        $this->postJson(self::ENDPOINT, $this->payload($c, ['enrollment_id' => 'abc']))
            ->assertStatus(422)->assertJsonValidationErrors('enrollment_id');
    }

    public function test_rechaza_oferta_no_numerica(): void
    {
        $c = $this->context();
        $this->postJson(self::ENDPOINT, $this->payload($c, ['academic_offering_id' => 'abc']))
            ->assertStatus(422)->assertJsonValidationErrors('academic_offering_id');
    }

    public function test_rechaza_estado_desconocido(): void
    {
        $c = $this->context();
        $this->postJson(self::ENDPOINT, $this->payload($c, ['status' => 'inventado']))
            ->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_rechaza_estado_no_textual(): void
    {
        $c = $this->context();
        $this->postJson(self::ENDPOINT, $this->payload($c, ['status' => 5]))
            ->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_rechaza_estado_nulo(): void
    {
        $c = $this->context();
        $this->postJson(self::ENDPOINT, $this->payload($c, ['status' => null]))
            ->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_rechaza_oferta_de_otro_periodo(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $this->postJson(self::ENDPOINT, $this->payload($a, ['academic_offering_id' => $b['offering']->id]))
            ->assertStatus(422)->assertJsonValidationErrors('academic_offering_id');
    }

    public function test_rechaza_curso_fuera_del_plan(): void
    {
        $c = $this->context();
        $unitId = $this->makeUnit($c['institution']->id);
        $offer = $this->makeOffering($c, 'X', 3, true, ['academic_unit_id' => $unitId]);
        $this->postJson(self::ENDPOINT, $this->payload($c, ['academic_offering_id' => $offer->id]))
            ->assertStatus(422)->assertJsonValidationErrors('academic_offering_id');
    }

    public function test_rechaza_curso_de_otra_institucion_aunque_periodo_coincida(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $offer = $this->makeOffering($a, 'X', 3, true, ['academic_unit_id' => $b['unitId']]);
        $this->postJson(self::ENDPOINT, $this->payload($a, ['academic_offering_id' => $offer->id]))
            ->assertStatus(422)->assertJsonValidationErrors('academic_offering_id');
    }

    public function test_rechaza_sede_de_otra_institucion(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $offer = $this->makeOffering($a, 'X', 3, true, ['campus_id' => $b['campusId']]);
        $this->postJson(self::ENDPOINT, $this->payload($a, ['academic_offering_id' => $offer->id]))
            ->assertStatus(422)->assertJsonValidationErrors('academic_offering_id');
    }

    public function test_rechaza_periodo_de_otra_institucion_en_la_oferta(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $offer = $this->makeOffering($a, 'X', 3, true, ['academic_period_id' => $b['periodId']]);
        $this->postJson(self::ENDPOINT, $this->payload($a, ['academic_offering_id' => $offer->id]))
            ->assertStatus(422)->assertJsonValidationErrors('academic_offering_id');
    }

    public function test_rechaza_matricula_con_historial_de_otra_institucion(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        DB::table('academic_records')->where('id', $a['enrollment']->academic_record_id)
            ->update(['academic_program_id' => $b['program']->id, 'academic_plan_id' => $b['plan']->id]);
        $this->postJson(self::ENDPOINT, $this->payload($a))
            ->assertStatus(422)->assertJsonValidationErrors('enrollment_id');
    }

    public function test_rechaza_matricula_con_plan_que_no_es_del_programa(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        DB::table('academic_records')->where('id', $a['enrollment']->academic_record_id)
            ->update(['academic_plan_id' => $b['plan']->id]);
        $this->postJson(self::ENDPOINT, $this->payload($a))
            ->assertStatus(422)->assertJsonValidationErrors('enrollment_id');
    }

    public function test_rechaza_oferta_inactiva_al_crear(): void
    {
        $c = $this->context();
        $c['offering']->update(['is_active' => false]);
        $this->postJson(self::ENDPOINT, $this->payload($c))
            ->assertStatus(422)->assertJsonValidationErrors('academic_offering_id');
    }

    public function test_rechaza_crear_cancelada_en_oferta_inactiva(): void
    {
        $c = $this->context();
        $c['offering']->update(['is_active' => false]);
        $this->postJson(self::ENDPOINT, $this->payload($c, ['status' => 'cancelled']))
            ->assertStatus(422)->assertJsonValidationErrors('academic_offering_id');
    }

    public function test_rechaza_activar_curso_en_matricula_cancelada(): void
    {
        $c = $this->context();
        $c['enrollment']->update(['status' => 'cancelled']);
        $this->postJson(self::ENDPOINT, $this->payload($c))
            ->assertStatus(422)->assertJsonValidationErrors('enrollment_id');
    }

    public function test_rechaza_activar_curso_en_matricula_pendiente(): void
    {
        $c = $this->context();
        $c['enrollment']->update(['status' => 'pending']);
        $this->postJson(self::ENDPOINT, $this->payload($c))
            ->assertStatus(422)->assertJsonValidationErrors('enrollment_id');
    }

    public function test_rechaza_activar_curso_en_matricula_completada(): void
    {
        $c = $this->context();
        $c['enrollment']->update(['status' => 'completed']);
        $this->postJson(self::ENDPOINT, $this->payload($c))
            ->assertStatus(422)->assertJsonValidationErrors('enrollment_id');
    }

    public function test_permite_registrar_cancelado_en_matricula_cancelada_sin_reservar_vacante(): void
    {
        $c = $this->context();
        $c['enrollment']->update(['status' => 'cancelled']);
        $this->postJson(self::ENDPOINT, $this->payload($c, ['status' => 'cancelled']))
            ->assertStatus(201)->assertJsonPath('data.status', 'cancelled');
    }

    public function test_permite_ocupar_ultima_vacante(): void
    {
        $c = $this->context('A', 1);
        $this->postJson(self::ENDPOINT, $this->payload($c))->assertStatus(201);
        $this->assertDatabaseCount('enrollment_details', 1);
    }

    public function test_rechaza_sobrecupo_al_crear(): void
    {
        $c = $this->context('A', 1);
        $this->detail($c);
        $another = $this->makeEnrollment($c);
        $this->postJson(self::ENDPOINT, $this->payload($c, ['enrollment_id' => $another->id]))
            ->assertStatus(422)->assertJsonValidationErrors('academic_offering_id');
        $this->assertDatabaseCount('enrollment_details', 1);
    }

    public function test_detalle_cancelado_no_ocupa_vacante(): void
    {
        $c = $this->context('A', 1);
        $this->detail($c, 'cancelled');
        $another = $this->makeEnrollment($c);
        $this->postJson(self::ENDPOINT, $this->payload($c, ['enrollment_id' => $another->id]))
            ->assertStatus(201);
    }

    public function test_detalle_completado_no_ocupa_vacante(): void
    {
        $c = $this->context('A', 1);
        $this->detail($c, 'completed');
        $another = $this->makeEnrollment($c);
        $this->postJson(self::ENDPOINT, $this->payload($c, ['enrollment_id' => $another->id]))
            ->assertStatus(201);
    }

    public function test_capacidad_nula_no_impone_limite_explicito(): void
    {
        $c = $this->context();
        $c['offering']->update(['capacity' => null]);
        $this->detail($c);
        $another = $this->makeEnrollment($c);
        $this->postJson(self::ENDPOINT, $this->payload($c, ['enrollment_id' => $another->id]))
            ->assertStatus(201);
    }

    public function test_no_permite_duplicar_misma_oferta(): void
    {
        $c = $this->context();
        $this->detail($c);
        $this->postJson(self::ENDPOINT, $this->payload($c))
            ->assertStatus(422)->assertJsonValidationErrors('academic_offering_id');
        $this->assertDatabaseCount('enrollment_details', 1);
    }

    public function test_no_permite_mismo_curso_en_otra_seccion(): void
    {
        $c = $this->context();
        $this->detail($c);
        $otherSection = $this->makeOffering($c, 'B');
        $this->postJson(self::ENDPOINT, $this->payload($c, ['academic_offering_id' => $otherSection->id]))
            ->assertStatus(422)->assertJsonValidationErrors('academic_offering_id');
    }

    public function test_permite_cursos_distintos_en_misma_matricula(): void
    {
        $c = $this->context();
        $this->detail($c);
        $anotherCourse = $this->makeOtherCourse($c);
        $this->postJson(self::ENDPOINT, $this->payload($c, ['academic_offering_id' => $anotherCourse->id]))
            ->assertStatus(201);
        $this->assertDatabaseCount('enrollment_details', 2);
    }

    public function test_permite_dos_estudiantes_en_misma_seccion_con_vacantes(): void
    {
        $c = $this->context('A', 2);
        $this->detail($c);
        $another = $this->makeEnrollment($c);
        $this->postJson(self::ENDPOINT, $this->payload($c, ['enrollment_id' => $another->id]))
            ->assertStatus(201);
        $this->assertDatabaseCount('enrollment_details', 2);
    }

    public function test_puede_cancelar_detalle_activo(): void
    {
        $c = $this->context();
        $d = $this->detail($c);
        $this->updateDetail($d, $c, ['status' => 'cancelled'])
            ->assertStatus(200)->assertJsonPath('data.status', 'cancelled');
    }

    public function test_puede_completar_detalle_activo(): void
    {
        $c = $this->context();
        $d = $this->detail($c);
        $this->updateDetail($d, $c, ['status' => 'completed'])
            ->assertStatus(200)->assertJsonPath('data.status', 'completed');
    }

    public function test_puede_reactivar_detalle_cancelado_con_vacantes(): void
    {
        $c = $this->context('A', 1);
        $d = $this->detail($c, 'cancelled');
        $this->updateDetail($d, $c, ['status' => 'active'])
            ->assertStatus(200)->assertJsonPath('data.status', 'active');
    }

    public function test_rechaza_reactivacion_cuando_seccion_llena(): void
    {
        $c = $this->context('A', 1);
        $d = $this->detail($c, 'cancelled');
        $other = $this->makeEnrollment($c);
        $this->detail($c, 'active', ['enrollment_id' => $other->id]);
        $this->updateDetail($d, $c, ['status' => 'active'])
            ->assertStatus(422)->assertJsonValidationErrors('academic_offering_id');
        $this->assertDatabaseHas('enrollment_details', ['id' => $d->id, 'status' => 'cancelled']);
    }

    public function test_permite_guardar_activo_en_seccion_llena_sin_consumir_otra_vacante(): void
    {
        $c = $this->context('A', 1);
        $d = $this->detail($c);
        $this->updateDetail($d, $c)
            ->assertStatus(200)->assertJsonPath('data.status', 'active');
    }

    public function test_puede_cambiar_a_seccion_libre(): void
    {
        $c = $this->context('A', 1);
        $d = $this->detail($c);
        $other = $this->makeOffering($c, 'B', 1);
        $this->updateDetail($d, $c, ['academic_offering_id' => $other->id])
            ->assertStatus(200)->assertJsonPath('data.academic_offering_id', $other->id);
    }

    public function test_rechaza_traslado_a_seccion_llena(): void
    {
        $c = $this->context('A', 1);
        $d = $this->detail($c);
        $other = $this->makeOffering($c, 'B', 1);
        $another = $this->makeEnrollment($c);
        $this->detail($c, 'active', [
            'enrollment_id' => $another->id,
            'academic_offering_id' => $other->id,
        ]);
        $this->updateDetail($d, $c, ['academic_offering_id' => $other->id])
            ->assertStatus(422)->assertJsonValidationErrors('academic_offering_id');
    }

    public function test_puede_cambiar_seccion_del_mismo_curso_en_actualizacion(): void
    {
        $c = $this->context();
        $d = $this->detail($c);
        $other = $this->makeOffering($c, 'B');
        $this->updateDetail($d, $c, ['academic_offering_id' => $other->id])
            ->assertStatus(200)->assertJsonPath('data.academic_offering_id', $other->id);
        $this->assertDatabaseCount('enrollment_details', 1);
    }

    public function test_rechaza_cambiar_a_oferta_ya_inscrita(): void
    {
        $c = $this->context();
        $d = $this->detail($c);
        $otherCourse = $this->makeOtherCourse($c);
        $this->detail($c, 'active', ['academic_offering_id' => $otherCourse->id]);
        $this->updateDetail($d, $c, ['academic_offering_id' => $otherCourse->id])
            ->assertStatus(422)->assertJsonValidationErrors('academic_offering_id');
    }

    public function test_rechaza_cambiar_a_curso_duplicado_otra_seccion(): void
    {
        $c = $this->context();
        $otherSection = $this->makeOffering($c, 'B');
        $otherCourse = $this->makeOtherCourse($c);
        $d = $this->detail($c, 'active', ['academic_offering_id' => $otherCourse->id]);
        $this->detail($c, 'active', ['academic_offering_id' => $c['offering']->id]);
        $this->updateDetail($d, $c, ['academic_offering_id' => $otherSection->id])
            ->assertStatus(422)->assertJsonValidationErrors('academic_offering_id');
    }

    public function test_rechaza_actualizacion_hacia_curso_fuera_del_plan(): void
    {
        $c = $this->context();
        $d = $this->detail($c);
        $unitId = $this->makeUnit($c['institution']->id);
        $offer = $this->makeOffering($c, 'B', 3, true, ['academic_unit_id' => $unitId]);
        $this->updateDetail($d, $c, ['academic_offering_id' => $offer->id])
            ->assertStatus(422)->assertJsonValidationErrors('academic_offering_id');
    }

    public function test_rechaza_actualizacion_hacia_otro_periodo(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $d = $this->detail($a);
        $this->updateDetail($d, $a, ['academic_offering_id' => $b['offering']->id])
            ->assertStatus(422)->assertJsonValidationErrors('academic_offering_id');
    }

    public function test_rechaza_actualizacion_hacia_otro_instituto(): void
    {
        $a = $this->context('A');
        $b = $this->context('B');
        $d = $this->detail($a);
        $offer = $this->makeOffering($a, 'B', 3, true, ['campus_id' => $b['campusId']]);
        $this->updateDetail($d, $a, ['academic_offering_id' => $offer->id])
            ->assertStatus(422)->assertJsonValidationErrors('academic_offering_id');
    }

    public function test_permite_trasladar_detalle_a_otra_matricula_de_misma_institucion(): void
    {
        $c = $this->context('A', 2);
        $d = $this->detail($c);
        $otherEnrollment = $this->makeEnrollment($c);
        $this->updateDetail($d, $c, ['enrollment_id' => $otherEnrollment->id])
            ->assertStatus(200)->assertJsonPath('data.enrollment_id', $otherEnrollment->id);
    }

    public function test_permite_trasladar_misma_vacante_activa_con_aforo_completo(): void
    {
        $c = $this->context('A', 1);
        $d = $this->detail($c);
        $otherEnrollment = $this->makeEnrollment($c);
        $this->updateDetail($d, $c, ['enrollment_id' => $otherEnrollment->id])
            ->assertStatus(200)->assertJsonPath('data.enrollment_id', $otherEnrollment->id);
        $this->assertDatabaseCount('enrollment_details', 1);
    }

    public function test_rechaza_trasladar_detalle_activo_a_matricula_cancelada(): void
    {
        $c = $this->context();
        $d = $this->detail($c);
        $otherEnrollment = $this->makeEnrollment($c, 'cancelled');
        $this->updateDetail($d, $c, ['enrollment_id' => $otherEnrollment->id])
            ->assertStatus(422)->assertJsonValidationErrors('enrollment_id');
    }

    public function test_rechaza_traslado_a_oferta_inactiva(): void
    {
        $c = $this->context();
        $d = $this->detail($c);
        $other = $this->makeOffering($c, 'B', 3, false);
        $this->updateDetail($d, $c, ['academic_offering_id' => $other->id])
            ->assertStatus(422)->assertJsonValidationErrors('academic_offering_id');
    }

    public function test_permite_completar_detalle_historico_en_oferta_ahora_inactiva(): void
    {
        $c = $this->context();
        $d = $this->detail($c);
        $c['offering']->update(['is_active' => false]);
        $this->updateDetail($d, $c, ['status' => 'completed'])
            ->assertStatus(200)->assertJsonPath('data.status', 'completed');
    }

    public function test_rechaza_actualizar_sin_campos_obligatorios(): void
    {
        $c = $this->context();
        $d = $this->detail($c);
        $this->putJson(self::ENDPOINT . "/{$d->id}", [])->assertStatus(422)
            ->assertJsonValidationErrors(['enrollment_id', 'academic_offering_id', 'status']);
    }

    public function test_rechaza_estados_invalidos_al_actualizar(): void
    {
        $c = $this->context();
        $d = $this->detail($c);
        $this->updateDetail($d, $c, ['status' => 'xyz'])
            ->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_rechaza_matricula_inexistente_al_actualizar(): void
    {
        $c = $this->context();
        $d = $this->detail($c);
        $this->updateDetail($d, $c, ['enrollment_id' => 99999])
            ->assertStatus(422)->assertJsonValidationErrors('enrollment_id');
    }

    public function test_rechaza_oferta_inexistente_al_actualizar(): void
    {
        $c = $this->context();
        $d = $this->detail($c);
        $this->updateDetail($d, $c, ['academic_offering_id' => 99999])
            ->assertStatus(422)->assertJsonValidationErrors('academic_offering_id');
    }

    public function test_devuelve_404_al_consultar_detalle_inexistente(): void
    {
        $this->context();
        $this->getJson(self::ENDPOINT . '/99999')->assertStatus(404);
    }

    public function test_devuelve_404_al_actualizar_detalle_inexistente(): void
    {
        $c = $this->context();
        $this->putJson(self::ENDPOINT . '/99999', $this->payload($c))->assertStatus(404);
    }

    public function test_devuelve_404_al_eliminar_detalle_inexistente(): void
    {
        $this->context();
        $this->deleteJson(self::ENDPOINT . '/99999')->assertStatus(404);
    }

    public function test_puede_eliminar_detalle_y_liberar_vacante(): void
    {
        $c = $this->context('A', 1);
        $d = $this->detail($c);
        $this->deleteJson(self::ENDPOINT . "/{$d->id}")
            ->assertStatus(200)->assertJsonPath('success', true);
        $other = $this->makeEnrollment($c);
        $this->postJson(self::ENDPOINT, $this->payload($c, ['enrollment_id' => $other->id]))
            ->assertStatus(201);
    }

    public function test_eliminar_detalle_no_elimina_matricula_ni_oferta(): void
    {
        $c = $this->context();
        $d = $this->detail($c);
        $this->deleteJson(self::ENDPOINT . "/{$d->id}")->assertStatus(200);
        $this->assertDatabaseHas('enrollments', ['id' => $c['enrollment']->id]);
        $this->assertDatabaseHas('academic_offerings', ['id' => $c['offering']->id]);
    }

    public function test_eliminar_un_detalle_no_elimina_otros(): void
    {
        $c = $this->context();
        $d = $this->detail($c);
        $other = $this->makeOtherCourse($c);
        $d2 = $this->detail($c, 'active', ['academic_offering_id' => $other->id]);
        $this->deleteJson(self::ENDPOINT . "/{$d->id}")->assertStatus(200);
        $this->assertDatabaseMissing('enrollment_details', ['id' => $d->id]);
        $this->assertDatabaseHas('enrollment_details', ['id' => $d2->id]);
    }

    public function test_puede_cancelar_detalle_aunque_la_matricula_se_cancelo_despues(): void
    {
        $c = $this->context();
        $d = $this->detail($c);
        $c['enrollment']->update(['status' => 'cancelled']);
        $this->updateDetail($d, $c, ['status' => 'cancelled'])
            ->assertStatus(200)->assertJsonPath('data.status', 'cancelled');
    }

    public function test_rechaza_reactivar_en_matricula_cancelada(): void
    {
        $c = $this->context();
        $d = $this->detail($c, 'cancelled');
        $c['enrollment']->update(['status' => 'cancelled']);
        $this->updateDetail($d, $c, ['status' => 'active'])
            ->assertStatus(422)->assertJsonValidationErrors('enrollment_id');
    }

    public function test_rechaza_reactivar_en_oferta_inactiva(): void
    {
        $c = $this->context();
        $d = $this->detail($c, 'cancelled');
        $c['offering']->update(['is_active' => false]);
        $this->updateDetail($d, $c, ['status' => 'active'])
            ->assertStatus(422)->assertJsonValidationErrors('academic_offering_id');
    }
}
