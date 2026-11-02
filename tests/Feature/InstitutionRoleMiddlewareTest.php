<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class InstitutionRoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/_test-institution-role';

    protected function setUp(): void
    {
        parent::setUp();

        // Ruta solo creada durante este test: NO se publica en routes/api.php.
        Route::get(self::URL, function (Request $request) {
            return response()->json([
                'institution_id' => $request->attributes->get('institution_id'),
                'institution_role' => $request->attributes->get('institution_role'),
            ]);
        })->middleware([
            'auth:sanctum',
            'institution.access',
            'institution.role:institution_admin,registrar',
        ]);

        // Comprueba que el middleware falle cerrado sin el guard previo.
        Route::get('/api/_test-role-without-context', fn () => response()->json(['ok' => true]))
            ->middleware(['auth:sanctum', 'institution.role:institution_admin']);

        // Comprueba que no se pueda utilizar sin declarar roles autorizados.
        Route::get('/api/_test-role-without-allowed-roles', fn () => response()->json(['ok' => true]))
            ->middleware(['auth:sanctum', 'institution.access', 'institution.role']);
    }

    private function institution(string $suffix): Institution
    {
        return Institution::create([
            'name' => 'Institucion '.$suffix,
            'code' => 'ROLE-MW-'.$suffix,
            'type' => 'institute',
            'is_active' => true,
        ]);
    }

    private function member(string $role, Institution $institution, array $extra = []): User
    {
        $user = User::factory()->create();
        $user->institutions()->attach($institution->id, array_merge(['role' => $role], $extra));

        return $user;
    }

    private function getWithInstitution(int $institutionId, string $url = self::URL)
    {
        return $this->withHeader('X-Institution-Id', (string) $institutionId)->getJson($url);
    }

    public function test_guest_cannot_access_role_protected_route(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();
    }

    public function test_authenticated_user_must_choose_institution(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(self::URL)->assertStatus(422);
    }

    public function test_non_numeric_institution_header_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->withHeader('X-Institution-Id', 'invalid')
            ->getJson(self::URL)->assertStatus(422);
    }

    public function test_user_without_membership_cannot_use_a_role(): void
    {
        $institution = $this->institution('A');
        $this->actingAs(User::factory()->create());
        $this->getWithInstitution($institution->id)->assertForbidden();
    }

    public function test_inactive_membership_is_denied_even_if_role_is_admin(): void
    {
        $institution = $this->institution('A');
        $this->actingAs($this->member('institution_admin', $institution, ['is_active' => false]));
        $this->getWithInstitution($institution->id)->assertForbidden();
    }

    public function test_inactive_institution_is_denied_even_if_role_is_admin(): void
    {
        $institution = $this->institution('A');
        $this->actingAs($this->member('institution_admin', $institution));
        $institution->update(['is_active' => false]);
        $this->getWithInstitution($institution->id)->assertForbidden();
    }

    public function test_default_member_has_no_operational_privileges(): void
    {
        $institution = $this->institution('A');
        $user = User::factory()->create();
        $user->institutions()->attach($institution->id);
        $this->actingAs($user);
        $this->getWithInstitution($institution->id)->assertForbidden();
    }

    public function test_teacher_cannot_use_admin_or_registrar_route(): void
    {
        $institution = $this->institution('A');
        $this->actingAs($this->member('teacher', $institution));
        $this->getWithInstitution($institution->id)->assertForbidden();
    }

    public function test_student_cannot_use_admin_or_registrar_route(): void
    {
        $institution = $this->institution('A');
        $this->actingAs($this->member('student', $institution));
        $this->getWithInstitution($institution->id)->assertForbidden();
    }

    public function test_institution_admin_can_use_allowed_route(): void
    {
        $institution = $this->institution('A');
        $this->actingAs($this->member('institution_admin', $institution));
        $this->getWithInstitution($institution->id)
            ->assertOk()
            ->assertJsonPath('institution_id', $institution->id)
            ->assertJsonPath('institution_role', 'institution_admin');
    }

    public function test_registrar_can_use_allowed_route(): void
    {
        $institution = $this->institution('A');
        $this->actingAs($this->member('registrar', $institution));
        $this->getWithInstitution($institution->id)
            ->assertOk()
            ->assertJsonPath('institution_role', 'registrar');
    }

    public function test_admin_role_in_institution_a_does_not_apply_to_b(): void
    {
        $a = $this->institution('A');
        $b = $this->institution('B');
        $user = $this->member('institution_admin', $a);
        $user->institutions()->attach($b->id, ['role' => 'teacher']);
        $this->actingAs($user);

        $this->getWithInstitution($a->id)->assertOk();
        $this->getWithInstitution($b->id)->assertForbidden();
    }

    public function test_role_changes_are_respected_without_reauthentication(): void
    {
        $institution = $this->institution('A');
        $user = $this->member('registrar', $institution);
        $this->actingAs($user);

        $this->getWithInstitution($institution->id)->assertOk();
        $user->institutions()->updateExistingPivot($institution->id, ['role' => 'student']);
        $this->getWithInstitution($institution->id)->assertForbidden();
    }

    public function test_query_cannot_forge_an_administrator_role(): void
    {
        $institution = $this->institution('A');
        $this->actingAs($this->member('member', $institution));
        $this->getWithInstitution($institution->id, self::URL.'?role=institution_admin')
            ->assertForbidden();
    }

    public function test_platform_admin_has_no_implicit_tenant_role(): void
    {
        $institution = $this->institution('A');
        $user = User::factory()->create();
        $user->forceFill(['is_platform_admin' => true])->save();
        $user->institutions()->attach($institution->id, ['role' => 'member']);
        $this->actingAs($user);
        $this->getWithInstitution($institution->id)->assertForbidden();
    }

    public function test_middleware_rejects_route_without_validated_tenant_context(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/_test-role-without-context')->assertForbidden();
    }

    public function test_middleware_rejects_route_without_allowed_roles(): void
    {
        $institution = $this->institution('A');
        $this->actingAs($this->member('institution_admin', $institution));
        $this->getWithInstitution($institution->id, '/api/_test-role-without-allowed-roles')
            ->assertForbidden();
    }
}
