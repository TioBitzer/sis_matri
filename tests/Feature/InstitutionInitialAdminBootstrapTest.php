<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InstitutionInitialAdminBootstrapTest extends TestCase
{
    use RefreshDatabase;

    private function institution(string $code = 'BOOT-A', bool $active = true): Institution
    {
        return Institution::create([
            'name' => 'Institucion '.$code,
            'code' => $code,
            'type' => 'institute',
            'is_active' => $active,
        ]);
    }

    private function runBootstrap(mixed $institutionId, mixed $userId, bool $confirm = true): int
    {
        return Artisan::call('institution:bootstrap-admin', [
            'institution_id' => (string) $institutionId,
            'user_id' => (string) $userId,
            '--confirm' => $confirm,
        ]);
    }

    public function test_requires_explicit_confirmation(): void
    {
        $institution = $this->institution();
        $user = User::factory()->create();

        $this->assertSame(1, $this->runBootstrap($institution->id, $user->id, false));
        $this->assertDatabaseCount('institution_user', 0);
    }

    public function test_requires_positive_numeric_institution_id(): void
    {
        $user = User::factory()->create();
        $this->assertSame(1, $this->runBootstrap('not-an-id', $user->id));
        $this->assertSame(1, $this->runBootstrap('0', $user->id));
        $this->assertDatabaseCount('institution_user', 0);
    }

    public function test_requires_positive_numeric_user_id(): void
    {
        $institution = $this->institution();
        $this->assertSame(1, $this->runBootstrap($institution->id, '-1'));
        $this->assertSame(1, $this->runBootstrap($institution->id, 'fake'));
        $this->assertDatabaseCount('institution_user', 0);
    }

    public function test_cannot_bootstrap_nonexistent_institution(): void
    {
        $user = User::factory()->create();
        $this->assertSame(1, $this->runBootstrap(99999, $user->id));
        $this->assertDatabaseCount('institution_user', 0);
    }

    public function test_cannot_bootstrap_inactive_institution(): void
    {
        $institution = $this->institution('INACTIVE', false);
        $user = User::factory()->create();
        $this->assertSame(1, $this->runBootstrap($institution->id, $user->id));
        $this->assertDatabaseCount('institution_user', 0);
    }

    public function test_cannot_bootstrap_nonexistent_user(): void
    {
        $institution = $this->institution();
        $this->assertSame(1, $this->runBootstrap($institution->id, 99999));
        $this->assertDatabaseCount('institution_user', 0);
    }

    public function test_creates_first_active_admin_for_existing_user(): void
    {
        $institution = $this->institution();
        $user = User::factory()->create();

        $this->assertSame(0, $this->runBootstrap($institution->id, $user->id));
        $this->assertDatabaseHas('institution_user', [
            'user_id' => $user->id,
            'institution_id' => $institution->id,
            'role' => 'institution_admin',
            'is_active' => true,
            'person_id' => null,
        ]);
        $this->assertFalse($user->fresh()->is_platform_admin);
    }

    public function test_promotes_existing_member_without_creating_duplicate(): void
    {
        $institution = $this->institution();
        $user = User::factory()->create();
        $user->institutions()->attach($institution->id, ['role' => 'member', 'is_active' => true]);

        $this->assertSame(0, $this->runBootstrap($institution->id, $user->id));
        $this->assertDatabaseCount('institution_user', 1);
        $this->assertDatabaseHas('institution_user', [
            'user_id' => $user->id,
            'institution_id' => $institution->id,
            'role' => 'institution_admin',
            'is_active' => true,
        ]);
    }

    public function test_reactivates_existing_inactive_member(): void
    {
        $institution = $this->institution();
        $user = User::factory()->create();
        $user->institutions()->attach($institution->id, ['role' => 'registrar', 'is_active' => false]);

        $this->assertSame(0, $this->runBootstrap($institution->id, $user->id));
        $this->assertDatabaseCount('institution_user', 1);
        $this->assertDatabaseHas('institution_user', [
            'user_id' => $user->id,
            'institution_id' => $institution->id,
            'role' => 'institution_admin',
            'is_active' => true,
        ]);
    }

    public function test_refuses_when_an_active_admin_already_exists(): void
    {
        $institution = $this->institution();
        $admin = User::factory()->create();
        $candidate = User::factory()->create();
        $admin->institutions()->attach($institution->id, ['role' => 'institution_admin', 'is_active' => true]);

        $this->assertSame(1, $this->runBootstrap($institution->id, $candidate->id));
        $this->assertDatabaseCount('institution_user', 1);
        $this->assertDatabaseMissing('institution_user', [
            'user_id' => $candidate->id,
            'institution_id' => $institution->id,
        ]);
    }

    public function test_existing_active_admin_prevents_promotion_of_another_member(): void
    {
        $institution = $this->institution();
        $admin = User::factory()->create();
        $candidate = User::factory()->create();
        $admin->institutions()->attach($institution->id, ['role' => 'institution_admin', 'is_active' => true]);
        $candidate->institutions()->attach($institution->id, ['role' => 'teacher', 'is_active' => true]);

        $this->assertSame(1, $this->runBootstrap($institution->id, $candidate->id));
        $this->assertDatabaseHas('institution_user', [
            'user_id' => $candidate->id,
            'institution_id' => $institution->id,
            'role' => 'teacher',
            'is_active' => true,
        ]);
    }

    public function test_repeated_command_cannot_rebootstrap_or_modify_an_admin(): void
    {
        $institution = $this->institution();
        $user = User::factory()->create();
        $this->assertSame(0, $this->runBootstrap($institution->id, $user->id));
        $first = DB::table('institution_user')->where('institution_id', $institution->id)->first();

        $this->assertSame(1, $this->runBootstrap($institution->id, $user->id));
        $this->assertDatabaseCount('institution_user', 1);
        $second = DB::table('institution_user')->where('institution_id', $institution->id)->first();
        $this->assertSame($first->updated_at, $second->updated_at);
    }

    public function test_other_institution_admin_does_not_block_bootstrap(): void
    {
        $a = $this->institution('BOOT-A');
        $b = $this->institution('BOOT-B');
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $admin->institutions()->attach($a->id, ['role' => 'institution_admin']);

        $this->assertSame(0, $this->runBootstrap($b->id, $user->id));
        $this->assertDatabaseHas('institution_user', [
            'institution_id' => $b->id, 'user_id' => $user->id,
            'role' => 'institution_admin',
        ]);
    }

    public function test_does_not_change_memberships_in_other_institutions(): void
    {
        $a = $this->institution('BOOT-A');
        $b = $this->institution('BOOT-B');
        $user = User::factory()->create();
        $user->institutions()->attach($a->id, ['role' => 'student']);

        $this->assertSame(0, $this->runBootstrap($b->id, $user->id));
        $this->assertDatabaseHas('institution_user', [
            'institution_id' => $a->id, 'user_id' => $user->id, 'role' => 'student',
        ]);
    }

    public function test_inactive_admin_does_not_prevent_initial_recovery(): void
    {
        $institution = $this->institution();
        $oldAdmin = User::factory()->create();
        $newAdmin = User::factory()->create();
        $oldAdmin->institutions()->attach($institution->id, ['role' => 'institution_admin', 'is_active' => false]);

        $this->assertSame(0, $this->runBootstrap($institution->id, $newAdmin->id));
        $this->assertDatabaseHas('institution_user', [
            'institution_id' => $institution->id,
            'user_id' => $oldAdmin->id,
            'role' => 'institution_admin',
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('institution_user', [
            'institution_id' => $institution->id,
            'user_id' => $newAdmin->id,
            'role' => 'institution_admin',
            'is_active' => true,
        ]);
    }
}