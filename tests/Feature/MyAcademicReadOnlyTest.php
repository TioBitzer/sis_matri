<?php

namespace Tests\Feature;

use App\Models\AcademicOffering;
use App\Models\AcademicPlan;
use App\Models\AcademicProgram;
use App\Models\AcademicRecord;
use App\Models\Enrollment;
use App\Models\Institution;
use App\Models\Person;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MyAcademicReadOnlyTest extends TestCase
{
    use RefreshDatabase;

    private const TEACHER_URL = '/api/me/teacher';
    private const ASSIGNMENTS_URL = '/api/me/teacher/assignments';
    private const STUDENT_URL = '/api/me/student';
    private const ENROLLMENTS_URL = '/api/me/student/enrollments';
    private const RECORDS_URL = '/api/me/student/academic-records';

    private function scenario(string $suffix = 'A'): array
    {
        $institution = Institution::create([
            'name' => 'Institucion Mi Portal '.$suffix,
            'code' => 'ME-'.$suffix,
            'type' => 'institute',
            'is_active' => true,
        ]);
        $teacherPerson = Person::create([
            'institution_id' => $institution->id,
            'first_name' => 'Docente',
            'last_name' => $suffix,
            'document_type' => 'DNI',
            'document_number' => 'ME-T-'.$suffix,
        ]);
        $teacher = Teacher::create([
            'institution_id' => $institution->id,
            'person_id' => $teacherPerson->id,
            'employee_code' => 'ME-EMP-'.$suffix,
            'is_active' => true,
        ]);
        $studentPerson = Person::create([
            'institution_id' => $institution->id,
            'first_name' => 'Estudiante',
            'last_name' => $suffix,
            'document_type' => 'DNI',
            'document_number' => 'ME-S-'.$suffix,
        ]);
        $student = Student::create([
            'person_id' => $studentPerson->id,
            'student_code' => 'ME-ST-'.$suffix,
            'status' => 'active',
        ]);
        $unitId = DB::table('academic_units')->insertGetId([
            'institution_id' => $institution->id,
            'name' => 'Unidad '.$suffix,
            'code' => 'ME-UNIT-'.$suffix,
            'is_active' => true,
        ]);
        $periodId = DB::table('academic_periods')->insertGetId([
            'institution_id' => $institution->id,
            'name' => 'Periodo '.$suffix,
            'code' => 'ME-PER-'.$suffix,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
        $offering = AcademicOffering::create([
            'academic_unit_id' => $unitId,
            'academic_period_id' => $periodId,
            'section' => 'A',
            'capacity' => 30,
            'is_active' => true,
        ]);
        $assignment = TeachingAssignment::create([
            'teacher_id' => $teacher->id,
            'academic_offering_id' => $offering->id,
            'role' => 'Titular',
        ]);
        $program = AcademicProgram::create([
            'institution_id' => $institution->id,
            'name' => 'Programa '.$suffix,
            'code' => 'ME-PR-'.$suffix,
            'is_active' => true,
        ]);
        $plan = AcademicPlan::create([
            'academic_program_id' => $program->id,
            'name' => 'Plan '.$suffix,
            'version' => '1.0',
            'is_active' => true,
        ]);
        $record = AcademicRecord::create([
            'student_id' => $student->id,
            'academic_program_id' => $program->id,
            'academic_plan_id' => $plan->id,
            'start_date' => '2026-01-01',
            'status' => 'active',
        ]);
        $enrollment = Enrollment::create([
            'student_id' => $student->id,
            'academic_period_id' => $periodId,
            'academic_record_id' => $record->id,
            'enrollment_date' => '2026-03-01',
            'status' => 'active',
        ]);

        return compact('institution', 'teacherPerson', 'teacher', 'studentPerson',
            'student', 'assignment', 'record', 'enrollment', 'offering', 'periodId', 'program', 'plan');
    }

    private function member(Institution $institution, string $role, ?int $personId = null, bool $active = true): User
    {
        $user = User::factory()->create();
        $user->institutions()->attach($institution->id, [
            'role' => $role,
            'person_id' => $personId,
            'is_active' => $active,
        ]);

        return $user;
    }

    private function headers(Institution $institution): array
    {
        return ['X-Institution-Id' => (string) $institution->id];
    }

    public function test_all_five_routes_are_get_only_and_require_role_and_tenant(): void
    {
        $paths = [
            self::TEACHER_URL => 'teacher',
            self::ASSIGNMENTS_URL => 'teacher',
            self::STUDENT_URL => 'student',
            self::ENROLLMENTS_URL => 'student',
            self::RECORDS_URL => 'student',
        ];
        foreach ($paths as $url => $role) {
            $route = Route::getRoutes()->match(Request::create($url, 'GET'));
            $this->assertContains('auth:sanctum', $route->gatherMiddleware());
            $this->assertContains('institution.access', $route->gatherMiddleware());
            $this->assertContains('institution.role:'.$role, $route->gatherMiddleware());
            $this->assertNotContains('POST', $route->methods());
            $this->assertNotContains('PUT', $route->methods());
            $this->assertNotContains('DELETE', $route->methods());
        }
    }

    public function test_guests_cannot_read_personal_endpoints(): void
    {
        $a = $this->scenario();
        foreach ([self::TEACHER_URL, self::ASSIGNMENTS_URL, self::STUDENT_URL,
            self::ENROLLMENTS_URL, self::RECORDS_URL] as $url) {
            $this->getJson($url, $this->headers($a['institution']))->assertUnauthorized();
        }
    }

    public function test_missing_header_returns_validation_error(): void
    {
        $a = $this->scenario();
        $this->actingAs($this->member($a['institution'], 'teacher', $a['teacherPerson']->id));
        $this->getJson(self::TEACHER_URL)->assertUnprocessable();
    }

    public function test_invalid_header_returns_validation_error(): void
    {
        $a = $this->scenario();
        $this->actingAs($this->member($a['institution'], 'student', $a['studentPerson']->id));
        $this->getJson(self::STUDENT_URL, ['X-Institution-Id' => 'abc'])->assertUnprocessable();
    }

    public function test_user_without_membership_is_denied(): void
    {
        $a = $this->scenario();
        $this->actingAs(User::factory()->create());
        $this->getJson(self::STUDENT_URL, $this->headers($a['institution']))->assertForbidden();
    }

    public function test_inactive_membership_is_denied(): void
    {
        $a = $this->scenario();
        $this->actingAs($this->member($a['institution'], 'teacher', $a['teacherPerson']->id, false));
        $this->getJson(self::TEACHER_URL, $this->headers($a['institution']))->assertForbidden();
    }

    public function test_inactive_institution_is_denied(): void
    {
        $a = $this->scenario();
        $this->actingAs($this->member($a['institution'], 'student', $a['studentPerson']->id));
        $a['institution']->update(['is_active' => false]);
        $this->getJson(self::STUDENT_URL, $this->headers($a['institution']))->assertForbidden();
    }

    public function test_wrong_roles_cannot_access_other_people_endpoints(): void
    {
        $a = $this->scenario();
        foreach (['member', 'registrar', 'institution_admin'] as $role) {
            $this->actingAs($this->member($a['institution'], $role));
            $this->getJson(self::TEACHER_URL, $this->headers($a['institution']))->assertForbidden();
            $this->getJson(self::STUDENT_URL, $this->headers($a['institution']))->assertForbidden();
        }
        $this->actingAs($this->member($a['institution'], 'teacher', $a['teacherPerson']->id));
        $this->getJson(self::STUDENT_URL, $this->headers($a['institution']))->assertForbidden();
        $this->actingAs($this->member($a['institution'], 'student', $a['studentPerson']->id));
        $this->getJson(self::TEACHER_URL, $this->headers($a['institution']))->assertForbidden();
    }

    public function test_teacher_without_linked_person_cannot_read(): void
    {
        $a = $this->scenario();
        $this->actingAs($this->member($a['institution'], 'teacher'));
        $this->getJson(self::TEACHER_URL, $this->headers($a['institution']))->assertForbidden();
        $this->getJson(self::ASSIGNMENTS_URL, $this->headers($a['institution']))->assertForbidden();
    }

    public function test_student_without_linked_person_cannot_read(): void
    {
        $a = $this->scenario();
        $this->actingAs($this->member($a['institution'], 'student'));
        $this->getJson(self::STUDENT_URL, $this->headers($a['institution']))->assertForbidden();
        $this->getJson(self::RECORDS_URL, $this->headers($a['institution']))->assertForbidden();
    }

    public function test_teacher_profile_returns_only_linked_teacher(): void
    {
        $a = $this->scenario();
        $this->actingAs($this->member($a['institution'], 'teacher', $a['teacherPerson']->id));
        $this->getJson(self::TEACHER_URL, $this->headers($a['institution']))
            ->assertOk()->assertJsonPath('data.id', $a['teacher']->id)
            ->assertDontSee('password');
    }

    public function test_teacher_assignments_exclude_other_teachers_and_institutions(): void
    {
        $a = $this->scenario('A');
        $b = $this->scenario('B');
        $otherPerson = Person::create([
            'institution_id' => $a['institution']->id,
            'first_name' => 'Otro', 'last_name' => 'Docente',
            'document_number' => 'ME-T-OTHER', 'document_type' => 'DNI',
        ]);
        $otherTeacher = Teacher::create([
            'institution_id' => $a['institution']->id,
            'person_id' => $otherPerson->id,
            'employee_code' => 'ME-EMP-OTHER',
            'is_active' => true,
        ]);
        TeachingAssignment::create([
            'teacher_id' => $otherTeacher->id,
            'academic_offering_id' => $a['offering']->id,
            'role' => 'Auxiliar',
        ]);
        $this->actingAs($this->member($a['institution'], 'teacher', $a['teacherPerson']->id));
        $this->getJson(self::ASSIGNMENTS_URL, $this->headers($a['institution']))
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $a['assignment']->id)
            ->assertJsonMissing(['id' => $b['assignment']->id]);
    }

    public function test_student_profile_and_lists_return_only_own_data(): void
    {
        $a = $this->scenario('A');
        $b = $this->scenario('B');
        $this->actingAs($this->member($a['institution'], 'student', $a['studentPerson']->id));
        $this->getJson(self::STUDENT_URL, $this->headers($a['institution']))
            ->assertOk()->assertJsonPath('data.id', $a['student']->id);
        $this->getJson(self::ENROLLMENTS_URL, $this->headers($a['institution']))
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $a['enrollment']->id);
        $this->getJson(self::RECORDS_URL, $this->headers($a['institution']))
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $a['record']->id);
        $this->assertNotSame($a['enrollment']->id, $b['enrollment']->id);
    }

    public function test_student_cannot_read_another_student_in_same_institution(): void
    {
        $a = $this->scenario();
        $person = Person::create([
            'institution_id' => $a['institution']->id,
            'first_name' => 'Otro', 'last_name' => 'Alumno',
            'document_number' => 'ME-S-OTHER', 'document_type' => 'DNI',
        ]);
        $other = Student::create([
            'person_id' => $person->id,
            'student_code' => 'ME-ST-OTHER',
            'status' => 'active',
        ]);
        $otherRecord = AcademicRecord::create([
            'student_id' => $other->id,
            'academic_program_id' => $a['program']->id,
            'academic_plan_id' => $a['plan']->id,
            'start_date' => '2026-01-01',
            'status' => 'active',
        ]);
        $otherEnrollment = Enrollment::create([
            'student_id' => $other->id,
            'academic_period_id' => $a['periodId'],
            'academic_record_id' => $otherRecord->id,
            'enrollment_date' => '2026-03-01',
            'status' => 'active',
        ]);
        $this->actingAs($this->member($a['institution'], 'student', $a['studentPerson']->id));
        $this->getJson(self::ENROLLMENTS_URL, $this->headers($a['institution']))
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $a['enrollment']->id);
        $this->getJson(self::RECORDS_URL, $this->headers($a['institution']))
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $a['record']->id);
        $this->assertNotSame($otherEnrollment->id, $a['enrollment']->id);
    }

    public function test_spoofed_identifiers_in_query_do_not_change_ownership(): void
    {
        $a = $this->scenario('A');
        $b = $this->scenario('B');
        $this->actingAs($this->member($a['institution'], 'teacher', $a['teacherPerson']->id));
        $this->getJson(self::ASSIGNMENTS_URL.'?teacher_id='.$b['teacher']->id.'&person_id='.$b['teacherPerson']->id,
            $this->headers($a['institution']))
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $a['assignment']->id);
        $this->actingAs($this->member($a['institution'], 'student', $a['studentPerson']->id));
        $this->getJson(self::ENROLLMENTS_URL.'?student_id='.$b['student']->id,
            $this->headers($a['institution']))
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $a['enrollment']->id);
    }

    public function test_role_revocation_takes_effect_immediately(): void
    {
        $a = $this->scenario();
        $user = $this->member($a['institution'], 'teacher', $a['teacherPerson']->id);
        $this->actingAs($user);
        $this->getJson(self::TEACHER_URL, $this->headers($a['institution']))->assertOk();
        $user->institutions()->updateExistingPivot($a['institution']->id, ['role' => 'member']);
        $this->getJson(self::TEACHER_URL, $this->headers($a['institution']))->assertForbidden();
    }

    public function test_same_user_can_switch_institutions_but_never_mix_linked_persons(): void
    {
        $a = $this->scenario('A');
        $b = $this->scenario('B');
        $user = $this->member($a['institution'], 'teacher', $a['teacherPerson']->id);
        $user->institutions()->attach($b['institution']->id, [
            'role' => 'teacher', 'is_active' => true, 'person_id' => $b['teacherPerson']->id,
        ]);
        $this->actingAs($user);
        $this->getJson(self::TEACHER_URL, $this->headers($a['institution']))
            ->assertOk()->assertJsonPath('data.id', $a['teacher']->id);
        $this->getJson(self::TEACHER_URL, $this->headers($b['institution']))
            ->assertOk()->assertJsonPath('data.id', $b['teacher']->id);
        $this->getJson(self::ASSIGNMENTS_URL, $this->headers($b['institution']))
            ->assertOk()->assertJsonPath('data.0.id', $b['assignment']->id);
    }

    public function test_corrupt_cross_institution_person_link_is_rejected(): void
    {
        $a = $this->scenario('A');
        $b = $this->scenario('B');
        $user = $this->member($a['institution'], 'teacher', $a['teacherPerson']->id);
        DB::table('institution_user')->where('user_id', $user->id)
            ->where('institution_id', $a['institution']->id)
            ->update(['person_id' => $b['teacherPerson']->id]);
        $this->actingAs($user);
        $this->getJson(self::TEACHER_URL, $this->headers($a['institution']))->assertForbidden();
    }

    public function test_valid_membership_without_matching_teacher_or_student_returns_not_found(): void
    {
        $a = $this->scenario();
        $this->actingAs($this->member($a['institution'], 'teacher', $a['studentPerson']->id));
        $this->getJson(self::TEACHER_URL, $this->headers($a['institution']))->assertNotFound();
        $this->actingAs($this->member($a['institution'], 'student', $a['teacherPerson']->id));
        $this->getJson(self::STUDENT_URL, $this->headers($a['institution']))->assertNotFound();
    }
}
