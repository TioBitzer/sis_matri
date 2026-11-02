<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InstitutionMembershipManagementTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/institution-memberships';

    private function institution(string $suffix = 'A', bool $active = true): Institution
    {
        return Institution::create([
            'name' => 'Institución Roles '.$suffix,
            'code' => 'MEM-'.$suffix,
            'type' => 'institute',
            'is_active' => $active,
        ]);
    }

    private function member(Institution $institution, string $role = 'member', bool $active = true): User
    {
        $user = User::factory()->create();
        $user->institutions()->attach($institution->id, [
            'role' => $role,
            'is_active' => $active,
        ]);

        return $user;
    }

    private function login(User $user, Institution $institution): void
    {
        $this->actingAs($user)->withHeader('X-Institution-Id', (string) $institution->id);
    }

    private function membershipId(Institution $institution, User $user): int
    {
        return (int) DB::table('institution_user')
            ->where('institution_id', $institution->id)
            ->where('user_id', $user->id)
            ->value('id');
    }

    public function test_guest_is_unauthorized(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();
    }

    public function test_missing_institution_header_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())->getJson(self::URL)->assertUnprocessable();
    }

    public function test_invalid_institution_header_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->withHeader('X-Institution-Id', 'invalid')
            ->getJson(self::URL)->assertUnprocessable();
    }

    public function test_user_without_membership_is_denied(): void
    {
        $this->login(User::factory()->create(), $this->institution());
        $this->getJson(self::URL)->assertForbidden();
    }

    public function test_non_admin_roles_cannot_list_or_create_memberships(): void
    {
        foreach (['member', 'registrar', 'teacher', 'student'] as $role) {
            $institution = $this->institution('R-'.$role);
            $this->login($this->member($institution, $role), $institution);
            $this->getJson(self::URL)->assertForbidden();
            $this->postJson(self::URL, ['user_id' => User::factory()->create()->id])->assertForbidden();
        }
    }

    public function test_inactive_membership_is_denied(): void
    {
        $institution = $this->institution();
        $this->login($this->member($institution, 'institution_admin', false), $institution);
        $this->getJson(self::URL)->assertForbidden();
    }

    public function test_inactive_institution_is_denied(): void
    {
        $institution = $this->institution();
        $admin = $this->member($institution, 'institution_admin');
        $institution->update(['is_active' => false]);
        $this->login($admin, $institution);
        $this->getJson(self::URL)->assertForbidden();
    }

    public function test_platform_admin_does_not_get_implicit_tenant_admin_role(): void
    {
        $institution = $this->institution();
        $user = $this->member($institution, 'member');
        $user->forceFill(['is_platform_admin' => true])->save();
        $this->login($user, $institution);
        $this->getJson(self::URL)->assertForbidden();
    }

    public function test_admin_lists_only_memberships_in_selected_institution_without_secrets(): void
    {
        $a = $this->institution('A');
        $b = $this->institution('B');
        $admin = $this->member($a, 'institution_admin');
        $own = $this->member($a, 'registrar');
        $foreign = $this->member($b, 'teacher');
        $this->login($admin, $a);

        $response = $this->getJson(self::URL)->assertOk()->assertJsonCount(2, 'data');
        $this->assertContains($own->id, array_column($response->json('data'), 'user_id'));
        $this->assertNotContains($foreign->id, array_column($response->json('data'), 'user_id'));
        $response->assertDontSee('password')->assertDontSee('remember_token')
            ->assertDontSee('is_platform_admin');
    }

    public function test_admin_can_show_member_from_selected_institution(): void
    {
        $institution = $this->institution();
        $admin = $this->member($institution, 'institution_admin');
        $member = $this->member($institution, 'student');
        $this->login($admin, $institution);
        $this->getJson(self::URL.'/'.$this->membershipId($institution, $member))
            ->assertOk()->assertJsonPath('data.user_id', $member->id)
            ->assertJsonPath('data.role', 'student');
    }

    public function test_membership_from_another_institution_is_not_visible(): void
    {
        $a = $this->institution('A');
        $b = $this->institution('B');
        $admin = $this->member($a, 'institution_admin');
        $other = $this->member($b, 'member');
        $this->login($admin, $a);
        $this->getJson(self::URL.'/'.$this->membershipId($b, $other))->assertNotFound();
    }

    public function test_new_existing_user_receives_member_role_by_default(): void
    {
        $institution = $this->institution();
        $this->login($this->member($institution, 'institution_admin'), $institution);
        $user = User::factory()->create();

        $this->postJson(self::URL, ['user_id' => $user->id])
            ->assertCreated()->assertJsonPath('data.role', 'member')
            ->assertJsonPath('data.is_active', true);
        $this->assertDatabaseHas('institution_user', [
            'institution_id' => $institution->id,
            'user_id' => $user->id,
            'role' => 'member',
        ]);
    }

    public function test_admin_can_assign_registrar_or_second_admin_role(): void
    {
        $institution = $this->institution();
        $this->login($this->member($institution, 'institution_admin'), $institution);
        $registrar = User::factory()->create();
        $secondAdmin = User::factory()->create();
        $this->postJson(self::URL, ['user_id' => $registrar->id, 'role' => 'registrar'])
            ->assertCreated()->assertJsonPath('data.role', 'registrar');
        $this->postJson(self::URL, ['user_id' => $secondAdmin->id, 'role' => 'institution_admin'])
            ->assertCreated()->assertJsonPath('data.role', 'institution_admin');
    }

    public function test_duplicate_membership_is_rejected_without_changes(): void
    {
        $institution = $this->institution();
        $admin = $this->member($institution, 'institution_admin');
        $existing = $this->member($institution);
        $this->login($admin, $institution);
        $this->postJson(self::URL, ['user_id' => $existing->id, 'role' => 'institution_admin'])
            ->assertStatus(409);
        $this->assertDatabaseHas('institution_user', [
            'institution_id' => $institution->id,
            'user_id' => $existing->id,
            'role' => 'member',
        ]);
    }

    public function test_nonexistent_user_is_rejected(): void
    {
        $institution = $this->institution();
        $this->login($this->member($institution, 'institution_admin'), $institution);
        $this->postJson(self::URL, ['user_id' => 999999999])->assertUnprocessable();
    }

    public function test_role_allowlist_prevents_unrecognized_privileges(): void
    {
        $institution = $this->institution();
        $this->login($this->member($institution, 'institution_admin'), $institution);
        $this->postJson(self::URL, [
            'user_id' => User::factory()->create()->id,
            'role' => 'platform_admin',
        ])->assertUnprocessable()->assertJsonValidationErrors('role');
    }

    public function test_can_only_attach_a_person_of_selected_institution(): void
    {
        $a = $this->institution('A');
        $b = $this->institution('B');
        $personId = DB::table('people')->insertGetId([
            'institution_id' => $b->id,
            'first_name' => 'Otra',
            'last_name' => 'Institución',
            'document_type' => 'DNI',
            'document_number' => 'MEMBER-FOREIGN-1',
        ]);
        $this->login($this->member($a, 'institution_admin'), $a);
        $this->postJson(self::URL, [
            'user_id' => User::factory()->create()->id,
            'person_id' => $personId,
        ])->assertUnprocessable()->assertJsonValidationErrors('person_id');
    }

    public function test_admin_can_change_another_members_role(): void
    {
        $institution = $this->institution();
        $admin = $this->member($institution, 'institution_admin');
        $member = $this->member($institution, 'member');
        $this->login($admin, $institution);
        $this->patchJson(self::URL.'/'.$this->membershipId($institution, $member), [
            'role' => 'registrar',
        ])->assertOk()->assertJsonPath('data.role', 'registrar');
    }

    public function test_admin_cannot_modify_own_membership(): void
    {
        $institution = $this->institution();
        $admin = $this->member($institution, 'institution_admin');
        $this->login($admin, $institution);
        $id = $this->membershipId($institution, $admin);
        $this->patchJson(self::URL.'/'.$id, ['role' => 'member'])->assertForbidden();
        $this->deleteJson(self::URL.'/'.$id)->assertForbidden();
        $this->assertDatabaseHas('institution_user', [
            'id' => $id, 'role' => 'institution_admin', 'is_active' => true,
        ]);
    }

    public function test_updating_foreign_membership_does_not_modify_it(): void
    {
        $a = $this->institution('A');
        $b = $this->institution('B');
        $admin = $this->member($a, 'institution_admin');
        $foreign = $this->member($b, 'teacher');
        $id = $this->membershipId($b, $foreign);
        $this->login($admin, $a);
        $this->patchJson(self::URL.'/'.$id, ['role' => 'institution_admin'])->assertNotFound();
        $this->deleteJson(self::URL.'/'.$id)->assertNotFound();
        $this->assertDatabaseHas('institution_user', ['id' => $id, 'role' => 'teacher']);
    }

    public function test_deactivation_is_soft_and_revokes_access_immediately(): void
    {
        $institution = $this->institution();
        $admin = $this->member($institution, 'institution_admin');
        $registrar = $this->member($institution, 'registrar');
        $id = $this->membershipId($institution, $registrar);
        $this->login($admin, $institution);
        $this->deleteJson(self::URL.'/'.$id)->assertOk();
        $this->assertDatabaseHas('institution_user', [
            'id' => $id, 'is_active' => false, 'role' => 'registrar',
        ]);

        $this->login($registrar, $institution);
        $this->getJson('/api/students')->assertForbidden();
    }

    public function test_inactive_member_can_be_reactivated_by_admin(): void
    {
        $institution = $this->institution();
        $admin = $this->member($institution, 'institution_admin');
        $inactive = $this->member($institution, 'student', false);
        $this->login($admin, $institution);
        $this->patchJson(self::URL.'/'.$this->membershipId($institution, $inactive), [
            'is_active' => true,
        ])->assertOk()->assertJsonPath('data.is_active', true);
    }

    public function test_update_rejects_mutation_of_user_or_institution_identity(): void
    {
        $institution = $this->institution();
        $admin = $this->member($institution, 'institution_admin');
        $member = $this->member($institution);
        $this->login($admin, $institution);
        $this->patchJson(self::URL.'/'.$this->membershipId($institution, $member), [
            'user_id' => $admin->id,
            'institution_id' => 999,
        ])->assertUnprocessable();
        $this->assertDatabaseHas('institution_user', [
            'institution_id' => $institution->id,
            'user_id' => $member->id,
            'role' => 'member',
        ]);
    }

    public function test_update_requires_a_modifiable_field(): void
    {
        $institution = $this->institution();
        $admin = $this->member($institution, 'institution_admin');
        $member = $this->member($institution);
        $this->login($admin, $institution);
        $this->patchJson(self::URL.'/'.$this->membershipId($institution, $member), [])
            ->assertUnprocessable();
    }

    public function test_role_is_scoped_when_switching_tenants(): void
    {
        $a = $this->institution('A');
        $b = $this->institution('B');
        $admin = $this->member($a, 'institution_admin');
        $admin->institutions()->attach($b->id, ['role' => 'member']);
        $this->login($admin, $a);
        $this->getJson(self::URL)->assertOk();
        $this->withHeader('X-Institution-Id', (string) $b->id)
            ->getJson(self::URL)->assertForbidden();
    }

    public function test_spoofed_platform_admin_is_rejected(): void
    {
        $institution = $this->institution();
        $this->login($this->member($institution, 'institution_admin'), $institution);
        $this->postJson(self::URL, [
            'user_id' => User::factory()->create()->id,
            'is_platform_admin' => true,
        ])->assertUnprocessable();
    }
}
