<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Los CRUD generales de estudiantes y matriculas son funciones operativas,
 * no endpoints de autoservicio de estudiantes o docentes.
 */
class InstitutionStudentEnrollmentRoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function institution(string $suffix = 'A'): Institution
    {
        return Institution::create([
            'name' => 'Instituto Roles '.$suffix,
            'code' => 'ROLE-CRUD-'.$suffix,
            'type' => 'institute',
            'is_active' => true,
        ]);
    }

    private function member(Institution $institution, string $role = 'member'): User
    {
        $user = User::factory()->create();
        $user->institutions()->attach($institution->id, [
            'is_active' => true,
            'role' => $role,
        ]);

        return $user;
    }

    private function headers(Institution $institution, array $extra = []): array
    {
        return array_merge(['X-Institution-Id' => (string) $institution->id], $extra);
    }

    /**
     * Se usa un id inexistente para comprobar que el middleware deja avanzar
     * la peticion: un usuario autorizado recibe 404 del controlador, no 403.
     */
    private function assertCrudStatus(string $module, array $headers, int $expected): void
    {
        $base = '/api/'.$module;
        $this->getJson($base, $headers)->assertStatus($expected);
        $this->getJson($base.'/999999', $headers)->assertStatus($expected);
        $this->postJson($base, [], $headers)->assertStatus($expected);
        $this->putJson($base.'/999999', [], $headers)->assertStatus($expected);
        $this->deleteJson($base.'/999999', [], $headers)->assertStatus($expected);
    }

    private function assertCrudAllowed(string $module, array $headers): void
    {
        $base = '/api/'.$module;
        $this->getJson($base, $headers)->assertOk();
        $this->getJson($base.'/999999', $headers)->assertNotFound();
        $this->postJson($base, [], $headers)->assertUnprocessable();
        // Student usa FormRequest y valida antes de entrar al controlador.
        $this->putJson($base.'/999999', [
            'student_code' => 'ST-ROLE-MISSING',
            'status' => 'active',
        ], $headers)->assertNotFound();
        $this->deleteJson($base.'/999999', [], $headers)->assertNotFound();
    }

    public function test_institution_admin_can_operate_both_general_cruds(): void
    {
        $institution = $this->institution();
        $this->actingAs($this->member($institution, 'institution_admin'));
        foreach (['students', 'enrollments'] as $module) {
            $this->assertCrudAllowed($module, $this->headers($institution));
        }
    }

    public function test_registrar_can_operate_both_general_cruds(): void
    {
        $institution = $this->institution();
        $this->actingAs($this->member($institution, 'registrar'));
        foreach (['students', 'enrollments'] as $module) {
            $this->assertCrudAllowed($module, $this->headers($institution));
        }
    }

    public function test_member_cannot_use_any_general_crud_operation(): void
    {
        $institution = $this->institution();
        $this->actingAs($this->member($institution, 'member'));
        foreach (['students', 'enrollments'] as $module) {
            $this->assertCrudStatus($module, $this->headers($institution), 403);
        }
    }

    public function test_teacher_cannot_use_any_general_crud_operation(): void
    {
        $institution = $this->institution();
        $this->actingAs($this->member($institution, 'teacher'));
        foreach (['students', 'enrollments'] as $module) {
            $this->assertCrudStatus($module, $this->headers($institution), 403);
        }
    }

    public function test_student_cannot_use_any_general_crud_operation(): void
    {
        $institution = $this->institution();
        $this->actingAs($this->member($institution, 'student'));
        foreach (['students', 'enrollments'] as $module) {
            $this->assertCrudStatus($module, $this->headers($institution), 403);
        }
    }

    public function test_unauthed_user_is_rejected_before_role_lookup(): void
    {
        $institution = $this->institution();
        foreach (['students', 'enrollments'] as $module) {
            $this->getJson('/api/'.$module, $this->headers($institution))
                ->assertUnauthorized();
        }
    }

    public function test_missing_tenant_header_still_returns_validation_error(): void
    {
        $institution = $this->institution();
        $this->actingAs($this->member($institution, 'registrar'));
        foreach (['students', 'enrollments'] as $module) {
            $this->getJson('/api/'.$module)->assertUnprocessable();
        }
    }

    public function test_invalid_tenant_header_still_returns_validation_error(): void
    {
        $institution = $this->institution();
        $this->actingAs($this->member($institution, 'registrar'));
        foreach (['students', 'enrollments'] as $module) {
            $this->getJson('/api/'.$module, ['X-Institution-Id' => 'abc'])
                ->assertUnprocessable();
        }
    }

    public function test_inactive_membership_remains_forbidden_even_with_role(): void
    {
        $institution = $this->institution();
        $user = $this->member($institution, 'registrar');
        $user->institutions()->updateExistingPivot($institution->id, ['is_active' => false]);
        $this->actingAs($user);
        foreach (['students', 'enrollments'] as $module) {
            $this->getJson('/api/'.$module, $this->headers($institution))
                ->assertForbidden();
        }
    }

    public function test_platform_admin_without_tenant_membership_is_not_authorized(): void
    {
        $institution = $this->institution();
        $user = User::factory()->create();
        $user->forceFill(['is_platform_admin' => true])->save();
        $this->actingAs($user);
        foreach (['students', 'enrollments'] as $module) {
            $this->getJson('/api/'.$module, $this->headers($institution))
                ->assertForbidden();
        }
    }

    public function test_role_in_a_does_not_grant_permissions_in_b(): void
    {
        $a = $this->institution('A');
        $b = $this->institution('B');
        $user = $this->member($a, 'registrar');
        $user->institutions()->attach($b->id, ['is_active' => true, 'role' => 'member']);
        $this->actingAs($user);
        foreach (['students', 'enrollments'] as $module) {
            $this->getJson('/api/'.$module, $this->headers($a))->assertOk();
            $this->getJson('/api/'.$module, $this->headers($b))->assertForbidden();
        }
    }

    public function test_role_changes_are_effective_without_relogin(): void
    {
        $institution = $this->institution();
        $user = $this->member($institution, 'member');
        $this->actingAs($user);
        $this->getJson('/api/students', $this->headers($institution))->assertForbidden();
        $user->institutions()->updateExistingPivot($institution->id, ['role' => 'registrar']);
        $this->getJson('/api/students', $this->headers($institution))->assertOk();
    }

    public function test_role_spoofing_in_request_does_not_grant_access(): void
    {
        $institution = $this->institution();
        $this->actingAs($this->member($institution, 'member'));
        foreach (['students', 'enrollments'] as $module) {
            $this->postJson('/api/'.$module,
                ['role' => 'institution_admin'],
                $this->headers($institution, ['X-Institution-Role' => 'institution_admin'])
            )->assertForbidden();
        }
    }
}
