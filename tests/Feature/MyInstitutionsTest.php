<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MyInstitutionsTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/me/institutions';

    private function institution(string $suffix, bool $active = true): Institution
    {
        return Institution::create([
            'name' => 'Institución '.$suffix,
            'code' => 'MEI-'.$suffix,
            'type' => 'institute',
            'is_active' => $active,
        ]);
    }

    private function addMembership(User $user, Institution $institution, string $role = 'member', bool $active = true): void
    {
        $user->institutions()->attach($institution->id, [
            'role' => $role,
            'is_active' => $active,
        ]);
    }

    public function test_guest_cannot_list_institutions(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();
    }

    public function test_logged_in_user_without_memberships_receives_empty_list(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(self::URL)->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_only_own_active_memberships_and_active_institutions_are_listed(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $own = $this->institution('Own');
        $inactiveMembership = $this->institution('IM');
        $inactiveInstitution = $this->institution('II', false);
        $foreign = $this->institution('Foreign');

        $this->addMembership($user, $own, 'registrar');
        $this->addMembership($user, $inactiveMembership, 'teacher', false);
        $this->addMembership($user, $inactiveInstitution, 'institution_admin');
        $this->addMembership($other, $foreign, 'student');

        $response = $this->actingAs($user)->getJson(self::URL)->assertOk()->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $own->id)
            ->assertJsonPath('data.0.name', $own->name)
            ->assertJsonPath('data.0.code', $own->code)
            ->assertJsonPath('data.0.type', 'institute')
            ->assertJsonPath('data.0.role', 'registrar');
    }

    public function test_no_institution_header_is_required_before_selection(): void
    {
        $institution = $this->institution('NH');
        $user = User::factory()->create();
        $this->addMembership($user, $institution, 'teacher');

        $this->actingAs($user)->getJson(self::URL)
            ->assertOk()->assertJsonPath('data.0.id', $institution->id);
    }

    public function test_user_with_two_active_institutions_sees_both_with_independent_roles(): void
    {
        $user = User::factory()->create();
        $a = $this->institution('A');
        $b = $this->institution('B');
        $this->addMembership($user, $a, 'institution_admin');
        $this->addMembership($user, $b, 'student');

        $result = $this->actingAs($user)->getJson(self::URL)->assertOk()->assertJsonCount(2, 'data')->json('data');
        $byId = array_column($result, 'role', 'id');
        $this->assertSame('institution_admin', $byId[$a->id]);
        $this->assertSame('student', $byId[$b->id]);
    }

    public function test_query_parameters_and_header_cannot_select_another_users_institutions(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $own = $this->institution('Mine');
        $foreign = $this->institution('Other');
        $this->addMembership($user, $own, 'teacher');
        $this->addMembership($other, $foreign, 'institution_admin');

        $this->actingAs($user)
            ->withHeader('X-Institution-Id', (string) $foreign->id)
            ->getJson(self::URL.'?user_id='.$other->id.'&institution_id='.$foreign->id)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id);
    }

    public function test_deactivation_of_membership_is_reflected_on_next_request(): void
    {
        $user = User::factory()->create();
        $institution = $this->institution('Revoked');
        $this->addMembership($user, $institution, 'registrar');
        $this->actingAs($user)->getJson(self::URL)->assertJsonCount(1, 'data');

        DB::table('institution_user')->where('user_id', $user->id)
            ->where('institution_id', $institution->id)->update(['is_active' => false]);

        $this->getJson(self::URL)->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_role_change_is_reflected_on_next_request(): void
    {
        $user = User::factory()->create();
        $institution = $this->institution('Changed');
        $this->addMembership($user, $institution, 'student');
        $this->actingAs($user)->getJson(self::URL)->assertJsonPath('data.0.role', 'student');

        DB::table('institution_user')->where('user_id', $user->id)
            ->where('institution_id', $institution->id)->update(['role' => 'registrar']);

        $this->getJson(self::URL)->assertOk()->assertJsonPath('data.0.role', 'registrar');
    }

    public function test_platform_admin_without_membership_does_not_see_every_institution(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_platform_admin' => true])->save();
        $this->institution('Platform');

        $this->actingAs($admin)->getJson(self::URL)->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_response_does_not_include_sensitive_user_or_pivot_fields(): void
    {
        $user = User::factory()->create();
        $institution = $this->institution('Minimal');
        $this->addMembership($user, $institution, 'member');

        $response = $this->actingAs($user)->getJson(self::URL)->assertOk();
        $this->assertSame(['id', 'name', 'code', 'type', 'role'], array_keys($response->json('data.0')));
        $response->assertDontSee('password')->assertDontSee('person_id')
            ->assertDontSee('is_platform_admin')->assertDontSee('remember_token');
    }

    public function test_only_get_is_supported(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(self::URL, [])->assertStatus(405);
    }

    public function test_inactive_institution_disappears_without_changing_membership(): void
    {
        $user = User::factory()->create();
        $institution = $this->institution('Closed');
        $this->addMembership($user, $institution, 'registrar');
        $this->actingAs($user)->getJson(self::URL)->assertJsonCount(1, 'data');

        $institution->update(['is_active' => false]);
        $this->getJson(self::URL)->assertOk()->assertExactJson(['data' => []]);
    }
}
