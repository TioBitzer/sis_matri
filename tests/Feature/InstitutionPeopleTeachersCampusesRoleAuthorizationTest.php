<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class InstitutionPeopleTeachersCampusesRoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private const MODULES = ['people', 'teachers', 'campuses'];
    private const OPERATIONS = [
        ['GET', ''], ['GET', '/999999'], ['POST', ''],
        ['PUT', '/999999'], ['DELETE', '/999999'],
    ];

    private function institution(string $suffix = 'A', bool $active = true): Institution
    {
        return Institution::create([
            'name' => 'Institucion Roles 2E '.$suffix,
            'code' => 'RBAC-2E-'.$suffix,
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

    private function headers(Institution $institution, array $extra = []): array
    {
        return array_merge(['X-Institution-Id' => (string) $institution->id], $extra);
    }

    private function assertAllForbidden(array $headers): void
    {
        foreach (self::MODULES as $module) {
            foreach (self::OPERATIONS as [$method, $suffix]) {
                $this->json($method, '/api/'.$module.$suffix, [], $headers)->assertForbidden();
            }
        }
    }

    public function test_all_fifteen_routes_have_required_middleware(): void
    {
        foreach (self::MODULES as $module) {
            foreach (self::OPERATIONS as [$method, $suffix]) {
                $route = Route::getRoutes()->match(Request::create('/api/'.$module.$suffix, $method));
                $middleware = $route->gatherMiddleware();
                $this->assertContains('auth:sanctum', $middleware);
                $this->assertContains('institution.access', $middleware);
                $canEditPerson = $module === 'people' && in_array($method, ['POST', 'PUT'], true);
                $expected = $method === 'GET' || $canEditPerson
                    ? 'institution.role:institution_admin,registrar'
                    : 'institution.role:institution_admin';
                $this->assertContains($expected, $middleware);
            }
        }
    }

    public function test_guest_cannot_access_general_cruds(): void
    {
        $i = $this->institution();
        foreach (self::MODULES as $module) {
            $this->getJson('/api/'.$module, $this->headers($i))->assertUnauthorized();
        }
    }

    public function test_authenticated_user_must_select_institution(): void
    {
        $i = $this->institution();
        $this->actingAs($this->member($i, 'institution_admin'));
        foreach (self::MODULES as $module) {
            $this->getJson('/api/'.$module)->assertUnprocessable();
        }
    }

    public function test_invalid_institution_header_is_rejected(): void
    {
        $i = $this->institution();
        $this->actingAs($this->member($i, 'institution_admin'));
        $this->getJson('/api/people', ['X-Institution-Id' => 'invalido'])->assertUnprocessable();
    }

    public function test_user_without_membership_is_denied(): void
    {
        $i = $this->institution();
        $this->actingAs(User::factory()->create());
        $this->assertAllForbidden($this->headers($i));
    }

    public function test_inactive_membership_is_denied(): void
    {
        $i = $this->institution();
        $this->actingAs($this->member($i, 'institution_admin', false));
        $this->assertAllForbidden($this->headers($i));
    }

    public function test_inactive_institution_is_denied(): void
    {
        $i = $this->institution('A', false);
        $this->actingAs($this->member($i, 'institution_admin'));
        $this->assertAllForbidden($this->headers($i));
    }

    public function test_default_member_cannot_access_general_cruds(): void
    {
        $i = $this->institution();
        $this->actingAs($this->member($i));
        $this->assertAllForbidden($this->headers($i));
    }

    public function test_teacher_cannot_access_general_cruds(): void
    {
        $i = $this->institution();
        $this->actingAs($this->member($i, 'teacher'));
        $this->assertAllForbidden($this->headers($i));
    }

    public function test_student_cannot_access_general_cruds(): void
    {
        $i = $this->institution();
        $this->actingAs($this->member($i, 'student'));
        $this->assertAllForbidden($this->headers($i));
    }

    public function test_registrar_can_read_three_general_lists(): void
    {
        $i = $this->institution();
        $this->actingAs($this->member($i, 'registrar'));
        foreach (self::MODULES as $module) {
            $this->getJson('/api/'.$module, $this->headers($i))->assertOk();
        }
    }

    public function test_registrar_can_create_people_but_validation_applies(): void
    {
        $i = $this->institution();
        $this->actingAs($this->member($i, 'registrar'));
        $this->postJson('/api/people', [], $this->headers($i))
            ->assertUnprocessable()->assertJsonValidationErrors('first_name');
    }

    public function test_registrar_cannot_delete_people(): void
    {
        $i = $this->institution();
        $this->actingAs($this->member($i, 'registrar'));
        $this->deleteJson('/api/people/999999', [], $this->headers($i))->assertForbidden();
    }

    public function test_registrar_cannot_modify_teachers_or_campuses(): void
    {
        $i = $this->institution();
        $this->actingAs($this->member($i, 'registrar'));
        foreach (['teachers', 'campuses'] as $module) {
            foreach ([['POST', ''], ['PUT', '/999999'], ['DELETE', '/999999']] as [$method, $suffix]) {
                $this->json($method, '/api/'.$module.$suffix, [], $this->headers($i))->assertForbidden();
            }
        }
    }

    public function test_institution_admin_can_read_all_lists(): void
    {
        $i = $this->institution();
        $this->actingAs($this->member($i, 'institution_admin'));
        foreach (self::MODULES as $module) {
            $this->getJson('/api/'.$module, $this->headers($i))->assertOk();
        }
    }

    public function test_admin_can_reach_business_validation_for_writes(): void
    {
        $i = $this->institution();
        $this->actingAs($this->member($i, 'institution_admin'));
        foreach (self::MODULES as $module) {
            $this->postJson('/api/'.$module, [], $this->headers($i))->assertUnprocessable();
        }
    }

    public function test_role_in_one_institution_is_not_inherited_by_another(): void
    {
        $a = $this->institution('A');
        $b = $this->institution('B');
        $user = $this->member($a, 'institution_admin');
        $user->institutions()->attach($b->id, ['is_active' => true, 'role' => 'member']);
        $this->actingAs($user);
        $this->getJson('/api/teachers', $this->headers($a))->assertOk();
        $this->getJson('/api/teachers', $this->headers($b))->assertForbidden();
    }

    public function test_role_revocation_is_effective_immediately(): void
    {
        $i = $this->institution();
        $user = $this->member($i, 'institution_admin');
        $this->actingAs($user);
        $this->getJson('/api/campuses', $this->headers($i))->assertOk();
        $user->institutions()->updateExistingPivot($i->id, ['role' => 'member']);
        $this->getJson('/api/campuses', $this->headers($i))->assertForbidden();
    }

    public function test_role_spoofing_does_not_grant_access(): void
    {
        $i = $this->institution();
        $this->actingAs($this->member($i, 'member'));
        $this->postJson('/api/teachers', [
            'role' => 'institution_admin',
            'institution_role' => 'institution_admin',
        ], $this->headers($i, ['X-Institution-Role' => 'institution_admin']))->assertForbidden();
    }

    public function test_platform_admin_without_membership_has_no_tenant_role(): void
    {
        $i = $this->institution();
        $user = User::factory()->create();
        $user->forceFill(['is_platform_admin' => true])->save();
        $this->actingAs($user);
        $this->getJson('/api/people', $this->headers($i))->assertForbidden();
    }
}