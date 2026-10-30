<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Permisos de los CRUD generales, no endpoints de autoservicio docente/estudiante.
 */
class InstitutionAcademicOperationsRoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private const MODULES = ['enrollment-details', 'academic-records', 'teaching-assignments'];
    private const OPERATIONS = [
        ['GET', ''], ['GET', '/999999'], ['POST', ''],
        ['PUT', '/999999'], ['DELETE', '/999999'],
    ];

    private function institution(string $suffix = 'A', bool $active = true): Institution
    {
        return Institution::create([
            'name' => 'RBAC Academico '.$suffix,
            'code' => 'RBAC-AC-'.$suffix,
            'type' => 'institute',
            'is_active' => $active,
        ]);
    }

    private function member(Institution $institution, string $role = 'member', bool $active = true): User
    {
        $user = User::factory()->create();
        $user->institutions()->attach($institution->id, [
            'is_active' => $active,
            'role' => $role,
        ]);
        return $user;
    }

    private function headers(Institution $institution, array $more = []): array
    {
        return array_merge(['X-Institution-Id' => (string) $institution->id], $more);
    }

    private function assertAllMethodsForbidden(string $module, array $headers): void
    {
        foreach (self::OPERATIONS as [$method, $suffix]) {
            $this->json($method, '/api/'.$module.$suffix, [], $headers)->assertForbidden();
        }
    }

    public function test_all_fifteen_routes_have_correct_role_middleware(): void
    {
        foreach (self::MODULES as $module) {
            $expectedRole = $module === 'teaching-assignments'
                ? 'institution.role:institution_admin'
                : 'institution.role:institution_admin,registrar';
            foreach (self::OPERATIONS as [$method, $suffix]) {
                $route = Route::getRoutes()->match(Request::create('/api/'.$module.$suffix, $method));
                $middleware = $route->gatherMiddleware();
                $this->assertContains('auth:sanctum', $middleware);
                $this->assertContains('institution.access', $middleware);
                $this->assertContains($expectedRole, $middleware);
            }
        }
    }

    public function test_guests_cannot_access_any_module(): void
    {
        $institution = $this->institution();
        foreach (self::MODULES as $module) {
            $this->getJson('/api/'.$module, $this->headers($institution))->assertUnauthorized();
        }
    }

    public function test_authenticated_user_without_header_receives_validation_error(): void
    {
        $institution = $this->institution();
        $this->actingAs($this->member($institution, 'institution_admin'));
        foreach (self::MODULES as $module) {
            $this->getJson('/api/'.$module)->assertUnprocessable();
        }
    }

    public function test_invalid_institution_header_is_rejected(): void
    {
        $institution = $this->institution();
        $this->actingAs($this->member($institution, 'institution_admin'));
        foreach (self::MODULES as $module) {
            $this->getJson('/api/'.$module, ['X-Institution-Id' => 'invalid'])->assertUnprocessable();
        }
    }

    public function test_nonmember_is_denied(): void
    {
        $institution = $this->institution();
        $this->actingAs(User::factory()->create());
        foreach (self::MODULES as $module) {
            $this->getJson('/api/'.$module, $this->headers($institution))->assertForbidden();
        }
    }

    public function test_inactive_membership_stays_denied(): void
    {
        $institution = $this->institution();
        $this->actingAs($this->member($institution, 'institution_admin', false));
        foreach (self::MODULES as $module) {
            $this->getJson('/api/'.$module, $this->headers($institution))->assertForbidden();
        }
    }

    public function test_inactive_institution_stays_denied(): void
    {
        $institution = $this->institution('A', false);
        $this->actingAs($this->member($institution, 'institution_admin'));
        foreach (self::MODULES as $module) {
            $this->getJson('/api/'.$module, $this->headers($institution))->assertForbidden();
        }
    }

    public function test_member_cannot_use_any_general_crud(): void
    {
        $institution = $this->institution();
        $this->actingAs($this->member($institution));
        foreach (self::MODULES as $module) {
            $this->assertAllMethodsForbidden($module, $this->headers($institution));
        }
    }

    public function test_teacher_cannot_use_any_general_crud(): void
    {
        $institution = $this->institution();
        $this->actingAs($this->member($institution, 'teacher'));
        foreach (self::MODULES as $module) {
            $this->assertAllMethodsForbidden($module, $this->headers($institution));
        }
    }

    public function test_student_cannot_use_any_general_crud(): void
    {
        $institution = $this->institution();
        $this->actingAs($this->member($institution, 'student'));
        foreach (self::MODULES as $module) {
            $this->assertAllMethodsForbidden($module, $this->headers($institution));
        }
    }

    public function test_registrar_can_read_enrollment_details_and_academic_records(): void
    {
        $institution = $this->institution();
        $this->actingAs($this->member($institution, 'registrar'));
        $this->getJson('/api/enrollment-details', $this->headers($institution))->assertOk();
        $this->getJson('/api/academic-records', $this->headers($institution))->assertOk();
    }

    public function test_registrar_cannot_use_teaching_assignments_crud(): void
    {
        $institution = $this->institution();
        $this->actingAs($this->member($institution, 'registrar'));
        $this->assertAllMethodsForbidden('teaching-assignments', $this->headers($institution));
    }

    public function test_institution_admin_can_read_all_three_modules(): void
    {
        $institution = $this->institution();
        $this->actingAs($this->member($institution, 'institution_admin'));
        foreach (self::MODULES as $module) {
            $this->getJson('/api/'.$module, $this->headers($institution))->assertOk();
        }
    }

    public function test_role_in_a_is_not_inherited_by_institution_b(): void
    {
        $a = $this->institution('A');
        $b = $this->institution('B');
        $user = $this->member($a, 'institution_admin');
        $user->institutions()->attach($b->id, ['is_active' => true, 'role' => 'member']);
        $this->actingAs($user);
        foreach (self::MODULES as $module) {
            $this->assertAllMethodsForbidden($module, $this->headers($b));
        }
    }

    public function test_independent_roles_work_when_switching_institutions(): void
    {
        $a = $this->institution('A');
        $b = $this->institution('B');
        $user = $this->member($a, 'institution_admin');
        $user->institutions()->attach($b->id, ['is_active' => true, 'role' => 'registrar']);
        $this->actingAs($user);
        $this->getJson('/api/teaching-assignments', $this->headers($a))->assertOk();
        $this->getJson('/api/enrollment-details', $this->headers($b))->assertOk();
        $this->getJson('/api/academic-records', $this->headers($b))->assertOk();
        $this->getJson('/api/teaching-assignments', $this->headers($b))->assertForbidden();
    }

    public function test_role_change_is_respected_without_relogin(): void
    {
        $institution = $this->institution();
        $user = $this->member($institution, 'registrar');
        $this->actingAs($user);
        $this->getJson('/api/academic-records', $this->headers($institution))->assertOk();
        $user->institutions()->updateExistingPivot($institution->id, ['role' => 'teacher']);
        $this->getJson('/api/academic-records', $this->headers($institution))->assertForbidden();
    }

    public function test_spoofing_role_does_not_grant_access(): void
    {
        $institution = $this->institution();
        $this->actingAs($this->member($institution, 'member'));
        foreach (self::MODULES as $module) {
            $this->postJson('/api/'.$module, ['role' => 'institution_admin'], $this->headers($institution, [
                'X-Institution-Role' => 'institution_admin',
            ]))->assertForbidden();
        }
    }

    public function test_platform_admin_without_tenant_membership_has_no_automatic_access(): void
    {
        $institution = $this->institution();
        $user = User::factory()->create();
        $user->forceFill(['is_platform_admin' => true])->save();
        $this->actingAs($user);
        foreach (self::MODULES as $module) {
            $this->getJson('/api/'.$module, $this->headers($institution))->assertForbidden();
        }
    }
}