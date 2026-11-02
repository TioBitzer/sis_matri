<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstitutionRoleFoundationTest extends TestCase
{
    use RefreshDatabase;

    private function institution(string $code): Institution
    {
        return Institution::create([
            'name' => 'Instituto '.$code,
            'code' => $code,
            'type' => 'institute',
            'is_active' => true,
        ]);
    }

    public function test_new_membership_has_no_privileged_role_by_default(): void
    {
        $user = User::factory()->create();
        $institution = $this->institution('ROLE-A');

        $user->institutions()->attach($institution->id);

        $this->assertSame('member', $user->institutions()->firstOrFail()->pivot->role);
        $this->assertFalse($user->fresh()->is_platform_admin);
        $this->assertDatabaseHas('institution_user', [
            'institution_id' => $institution->id,
            'user_id' => $user->id,
            'role' => 'member',
        ]);
    }

    public function test_same_user_can_have_independent_roles_per_institution(): void
    {
        $user = User::factory()->create();
        $a = $this->institution('ROLE-A');
        $b = $this->institution('ROLE-B');

        $user->institutions()->attach($a->id, ['role' => 'institution_admin']);
        $user->institutions()->attach($b->id, ['role' => 'registrar']);

        $memberships = $user->fresh()->institutions->keyBy('id');
        $this->assertSame('institution_admin', $memberships[$a->id]->pivot->role);
        $this->assertSame('registrar', $memberships[$b->id]->pivot->role);
    }

    public function test_changing_one_membership_role_does_not_change_another(): void
    {
        $user = User::factory()->create();
        $a = $this->institution('ROLE-A');
        $b = $this->institution('ROLE-B');

        $user->institutions()->attach($a->id, ['role' => 'member']);
        $user->institutions()->attach($b->id, ['role' => 'teacher']);
        $user->institutions()->updateExistingPivot($a->id, ['role' => 'registrar']);

        $memberships = $user->fresh()->institutions->keyBy('id');
        $this->assertSame('registrar', $memberships[$a->id]->pivot->role);
        $this->assertSame('teacher', $memberships[$b->id]->pivot->role);
    }

    public function test_institution_side_exposes_membership_role(): void
    {
        $user = User::factory()->create();
        $institution = $this->institution('ROLE-A');

        $user->institutions()->attach($institution->id, ['role' => 'institution_admin']);

        $this->assertSame('institution_admin', $institution->users()->firstOrFail()->pivot->role);
    }
}
