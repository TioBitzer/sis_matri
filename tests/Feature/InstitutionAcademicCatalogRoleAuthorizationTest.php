<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class InstitutionAcademicCatalogRoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private const MODULES = ['academic-programs', 'academic-plans'];
    private const REQUESTS = [
        ['GET', ''], ['GET', '/999999'], ['POST', ''],
        ['PUT', '/999999'], ['DELETE', '/999999'],
    ];

    private function institution(string $suffix = 'A', bool $active = true): Institution
    {
        return Institution::create([
            'name' => 'Catalogo RBAC '.$suffix,
            'code' => 'RBAC-CATALOG-'.$suffix,
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

    private function headers(Institution $institution, array $additional = []): array
    {
        return array_merge(['X-Institution-Id' => (string) $institution->id], $additional);
    }

    private function assertAllForbidden(array $headers): void
    {
        foreach (self::MODULES as $module) {
            foreach (self::REQUESTS as [$method, $suffix]) {
                $this->json($method, '/api/'.$module.$suffix, [], $headers)->assertForbidden();
            }
        }
    }

    public function test_ten_routes_require_correct_role_middleware(): void
    {
        foreach (self::MODULES as $module) {
            foreach (self::REQUESTS as [$method, $suffix]) {
                $route = Route::getRoutes()->match(Request::create('/api/'.$module.$suffix, $method));
                $middle = $route->gatherMiddleware();
                $this->assertContains('auth:sanctum', $middle);
                $this->assertContains('institution.access', $middle);
                $expected = $method === 'GET'
                    ? 'institution.role:institution_admin,registrar'
                    : 'institution.role:institution_admin';
                $this->assertContains($expected, $middle);
            }
        }
    }

    public function test_guest_cannot_read_catalog(): void
    {
        $institution = $this->institution();
        foreach (self::MODULES as $module) {
            $this->getJson('/api/'.$module, $this->headers($institution))->assertUnauthorized();
        }
    }

    public function test_missing_header_is_rejected(): void
    {
        $institution = $this->institution();
        $this->actingAs($this->member($institution, 'institution_admin'));
        foreach (self::MODULES as $module) {
            $this->getJson('/api/'.$module)->assertUnprocessable();
        }
    }

    public function test_invalid_header_is_rejected(): void
    {
        $institution = $this->institution();
        $this->actingAs($this->member($institution, 'institution_admin'));
        foreach (self::MODULES as $module) {
            $this->getJson('/api/'.$module, ['X-Institution-Id'=>'xyz'])->assertUnprocessable();
        }
    }

    public function test_nonmember_cannot_read(): void
    {
        $institution = $this->institution();
        $this->actingAs(User::factory()->create());
        $this->assertAllForbidden($this->headers($institution));
    }

    public function test_inactive_member_cannot_read(): void
    {
        $institution = $this->institution();
        $this->actingAs($this->member($institution, 'institution_admin', false));
        $this->assertAllForbidden($this->headers($institution));
    }

    public function test_inactive_institution_cannot_read(): void
    {
        $institution = $this->institution('A', false);
        $this->actingAs($this->member($institution, 'institution_admin'));
        $this->assertAllForbidden($this->headers($institution));
    }

    public function test_default_member_cannot_use_general_catalog_crud(): void
    {
        $institution = $this->institution();
        $this->actingAs($this->member($institution));
        $this->assertAllForbidden($this->headers($institution));
    }

    public function test_teacher_cannot_use_general_catalog_crud(): void
    {
        $institution = $this->institution();
        $this->actingAs($this->member($institution, 'teacher'));
        $this->assertAllForbidden($this->headers($institution));
    }

    public function test_student_cannot_use_general_catalog_crud(): void
    {
        $institution = $this->institution();
        $this->actingAs($this->member($institution, 'student'));
        $this->assertAllForbidden($this->headers($institution));
    }

    public function test_registrar_can_read_but_cannot_modify_catalog(): void
    {
        $institution = $this->institution();
        $this->actingAs($this->member($institution, 'registrar'));
        foreach (self::MODULES as $module) {
            $this->getJson('/api/'.$module, $this->headers($institution))->assertOk();
            foreach ([['POST', ''], ['PUT','/999999'], ['DELETE','/999999']] as [$method,$suffix]) {
                $this->json($method, '/api/'.$module.$suffix, [], $this->headers($institution))->assertForbidden();
            }
        }
    }

    public function test_institution_admin_can_read_catalog(): void
    {
        $institution = $this->institution();
        $this->actingAs($this->member($institution, 'institution_admin'));
        foreach (self::MODULES as $module) {
            $this->getJson('/api/'.$module, $this->headers($institution))->assertOk();
        }
    }

    public function test_role_from_another_institution_is_not_reused(): void
    {
        $a = $this->institution('A');
        $b = $this->institution('B');
        $user = $this->member($a, 'institution_admin');
        $user->institutions()->attach($b->id, ['is_active'=>true, 'role'=>'member']);
        $this->actingAs($user);
        foreach (self::MODULES as $module) {
            $this->getJson('/api/'.$module, $this->headers($a))->assertOk();
            $this->getJson('/api/'.$module, $this->headers($b))->assertForbidden();
        }
    }

    public function test_role_revocation_takes_effect_immediately(): void
    {
        $institution = $this->institution();
        $user = $this->member($institution, 'institution_admin');
        $this->actingAs($user);
        $this->getJson('/api/academic-programs', $this->headers($institution))->assertOk();
        $user->institutions()->updateExistingPivot($institution->id, ['role'=>'member']);
        $this->getJson('/api/academic-programs', $this->headers($institution))->assertForbidden();
    }

    public function test_forged_role_in_request_is_ignored(): void
    {
        $institution = $this->institution();
        $this->actingAs($this->member($institution, 'member'));
        $this->postJson('/api/academic-programs', ['role'=>'institution_admin'], $this->headers($institution, [
            'X-Institution-Role'=>'institution_admin',
        ]))->assertForbidden();
    }

    public function test_platform_admin_without_membership_has_no_tenant_access(): void
    {
        $institution = $this->institution();
        $user = User::factory()->create();
        $user->forceFill(['is_platform_admin'=>true])->save();
        $this->actingAs($user);
        $this->assertAllForbidden($this->headers($institution));
    }
}