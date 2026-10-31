<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
use App\Support\InstitutionMembershipAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class InstitutionMembershipAuditTest extends TestCase
{
    use RefreshDatabase;

    private const MEMBERS = '/api/institution-memberships';
    private const AUDITS = '/api/institution-membership-audits';

    private function institution(string $suffix = 'A'): Institution
    {
        return Institution::create([
            'name' => 'Auditoria '.$suffix,
            'code' => 'AUD-'.$suffix,
            'type' => 'institute',
            'is_active' => true,
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

    public function test_creation_records_actor_and_values(): void
    {
        $i = $this->institution();
        $admin = $this->member($i, 'institution_admin');
        $target = User::factory()->create();
        $this->login($admin, $i);

        $response = $this->postJson(self::MEMBERS, [
            'user_id' => $target->id,
            'role' => 'registrar',
        ])->assertCreated();

        $this->assertDatabaseHas('institution_membership_audits', [
            'institution_id' => $i->id,
            'membership_id' => $response->json('data.id'),
            'target_user_id' => $target->id,
            'actor_user_id' => $admin->id,
            'actor_type' => 'user',
            'action' => 'created',
            'old_role' => null,
            'new_role' => 'registrar',
            'old_is_active' => null,
            'new_is_active' => true,
        ]);
        $this->assertDatabaseCount('institution_membership_audits', 1);
    }

    public function test_role_change_records_before_after(): void
    {
        $i = $this->institution();
        $admin = $this->member($i, 'institution_admin');
        $target = $this->member($i, 'teacher');
        $this->login($admin, $i);
        $this->patchJson(self::MEMBERS.'/'.$this->membershipId($i, $target), [
            'role' => 'registrar',
        ])->assertOk();

        $this->assertDatabaseHas('institution_membership_audits', [
            'old_role' => 'teacher',
            'new_role' => 'registrar',
            'actor_user_id' => $admin->id,
            'target_user_id' => $target->id,
            'action' => 'updated',
        ]);
    }

    public function test_reactivation_records_old_and_new_states(): void
    {
        $i = $this->institution();
        $admin = $this->member($i, 'institution_admin');
        $target = $this->member($i, 'registrar', false);
        $this->login($admin, $i);
        $this->patchJson(self::MEMBERS.'/'.$this->membershipId($i, $target), [
            'is_active' => true,
        ])->assertOk();
        $this->assertDatabaseHas('institution_membership_audits', [
            'target_user_id' => $target->id,
            'old_is_active' => false,
            'new_is_active' => true,
            'old_role' => 'registrar',
            'new_role' => 'registrar',
        ]);
    }

    public function test_deactivation_is_audited_and_revokes_access(): void
    {
        $i = $this->institution();
        $admin = $this->member($i, 'institution_admin');
        $target = $this->member($i, 'registrar');
        $this->login($admin, $i);
        $this->deleteJson(self::MEMBERS.'/'.$this->membershipId($i, $target))->assertOk();
        $this->assertDatabaseHas('institution_membership_audits', [
            'target_user_id' => $target->id,
            'action' => 'deactivated',
            'old_is_active' => true,
            'new_is_active' => false,
        ]);
        $this->login($target, $i);
        $this->getJson('/api/students')->assertForbidden();
    }

    public function test_person_link_change_is_audited(): void
    {
        $i = $this->institution();
        $admin = $this->member($i, 'institution_admin');
        $target = $this->member($i);
        $personId = DB::table('people')->insertGetId([
            'institution_id' => $i->id,
            'first_name' => 'Persona',
            'last_name' => 'Auditoria',
            'document_type' => 'DNI',
            'document_number' => 'AUD-PER-1',
        ]);
        $this->login($admin, $i);
        $this->patchJson(self::MEMBERS.'/'.$this->membershipId($i, $target), [
            'person_id' => $personId,
        ])->assertOk();
        $this->assertDatabaseHas('institution_membership_audits', [
            'target_user_id' => $target->id,
            'old_person_id' => null,
            'new_person_id' => $personId,
        ]);
    }

    public function test_bootstrap_new_admin_records_console_origin(): void
    {
        $i = $this->institution();
        $user = User::factory()->create();
        $result = Artisan::call('institution:bootstrap-admin', [
            'institution_id' => (string) $i->id,
            'user_id' => (string) $user->id,
            '--confirm' => true,
        ]);
        $this->assertSame(0, $result);
        $this->assertDatabaseHas('institution_membership_audits', [
            'institution_id' => $i->id,
            'target_user_id' => $user->id,
            'actor_type' => 'console',
            'actor_user_id' => null,
            'action' => 'bootstrap_admin',
            'old_role' => null,
            'new_role' => 'institution_admin',
        ]);
    }

    public function test_bootstrap_promote_records_previous_member_role(): void
    {
        $i = $this->institution();
        $user = $this->member($i, 'teacher');
        $this->assertSame(0, Artisan::call('institution:bootstrap-admin', [
            'institution_id' => (string) $i->id,
            'user_id' => (string) $user->id,
            '--confirm' => true,
        ]));
        $this->assertDatabaseHas('institution_membership_audits', [
            'target_user_id' => $user->id,
            'old_role' => 'teacher',
            'new_role' => 'institution_admin',
            'old_is_active' => true,
            'new_is_active' => true,
        ]);
    }

    public function test_bootstrap_recovery_records_old_inactive_state(): void
    {
        $i = $this->institution();
        $user = $this->member($i, 'student', false);
        $this->assertSame(0, Artisan::call('institution:bootstrap-admin', [
            'institution_id' => (string) $i->id,
            'user_id' => (string) $user->id,
            '--confirm' => true,
        ]));
        $this->assertDatabaseHas('institution_membership_audits', [
            'target_user_id' => $user->id,
            'old_role' => 'student',
            'new_role' => 'institution_admin',
            'old_is_active' => false,
            'new_is_active' => true,
        ]);
    }

    public function test_failed_bootstrap_creates_no_event(): void
    {
        $i = $this->institution();
        $admin = $this->member($i, 'institution_admin');
        $user = User::factory()->create();
        $this->assertSame(1, Artisan::call('institution:bootstrap-admin', [
            'institution_id' => (string) $i->id,
            'user_id' => (string) $user->id,
            '--confirm' => true,
        ]));
        $this->assertDatabaseCount('institution_membership_audits', 0);
        $this->assertDatabaseHas('institution_user', [
            'institution_id' => $i->id,
            'user_id' => $admin->id,
            'role' => 'institution_admin',
        ]);
    }

    public function test_duplicate_membership_rejection_does_not_create_event(): void
    {
        $i = $this->institution();
        $admin = $this->member($i, 'institution_admin');
        $target = $this->member($i);
        $this->login($admin, $i);
        $this->postJson(self::MEMBERS, ['user_id' => $target->id])->assertStatus(409);
        $this->assertDatabaseCount('institution_membership_audits', 0);
    }

    public function test_self_change_rejection_does_not_create_event(): void
    {
        $i = $this->institution();
        $admin = $this->member($i, 'institution_admin');
        $this->login($admin, $i);
        $this->patchJson(self::MEMBERS.'/'.$this->membershipId($i, $admin), [
            'role' => 'registrar',
        ])->assertForbidden();
        $this->assertDatabaseCount('institution_membership_audits', 0);
    }

    public function test_foreign_membership_rejection_does_not_create_event(): void
    {
        $a = $this->institution('A');
        $b = $this->institution('B');
        $admin = $this->member($a, 'institution_admin');
        $target = $this->member($b, 'teacher');
        $this->login($admin, $a);
        $this->patchJson(self::MEMBERS.'/'.$this->membershipId($b, $target), [
            'role' => 'institution_admin',
        ])->assertNotFound();
        $this->assertDatabaseCount('institution_membership_audits', 0);
    }

    public function test_guest_cannot_read_audits(): void
    {
        $this->getJson(self::AUDITS)->assertUnauthorized();
    }

    public function test_missing_tenant_header_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $this->getJson(self::AUDITS)->assertUnprocessable();
    }

    public function test_non_admin_cannot_read_audits(): void
    {
        $i = $this->institution();
        foreach (['member', 'registrar', 'teacher', 'student'] as $role) {
            $this->login($this->member($i, $role), $i);
            $this->getJson(self::AUDITS)->assertForbidden();
        }
    }

    public function test_audit_read_is_scoped_to_selected_institution(): void
    {
        $a = $this->institution('A');
        $b = $this->institution('B');
        $adminA = $this->member($a, 'institution_admin');
        $adminB = $this->member($b, 'institution_admin');
        $this->login($adminA, $a);
        $this->postJson(self::MEMBERS, ['user_id' => User::factory()->create()->id])->assertCreated();
        $this->login($adminB, $b);
        $this->postJson(self::MEMBERS, ['user_id' => User::factory()->create()->id])->assertCreated();
        $this->login($adminA, $a);
        $this->getJson(self::AUDITS)->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.institution_id', $a->id);
    }

    public function test_paginated_history_is_bounded(): void
    {
        $i = $this->institution();
        $admin = $this->member($i, 'institution_admin');
        $this->login($admin, $i);
        $this->postJson(self::MEMBERS, ['user_id' => User::factory()->create()->id])->assertCreated();
        $this->postJson(self::MEMBERS, ['user_id' => User::factory()->create()->id])->assertCreated();
        $this->getJson(self::AUDITS.'?per_page=1')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.last_page', 2);
        $this->getJson(self::AUDITS.'?per_page=101')->assertUnprocessable();
    }

    public function test_history_does_not_expose_secrets(): void
    {
        $i = $this->institution();
        $admin = $this->member($i, 'institution_admin');
        $this->login($admin, $i);
        $this->postJson(self::MEMBERS, ['user_id' => User::factory()->create()->id])->assertCreated();
        $this->getJson(self::AUDITS)->assertOk()->assertDontSee('password')
            ->assertDontSee('remember_token')->assertDontSee('is_platform_admin');
    }

    public function test_audit_event_rolls_back_with_enclosing_transaction(): void
    {
        $i = $this->institution();
        try {
            DB::transaction(function () use ($i): void {
                InstitutionMembershipAudit::record($i->id, 1, 1, null, 'created', null, [
                    'role' => 'member', 'is_active' => true, 'person_id' => null,
                ]);
                throw new LogicException('Forzar rollback del evento');
            });
        } catch (LogicException $exception) {
            $this->assertSame('Forzar rollback del evento', $exception->getMessage());
        }
        $this->assertDatabaseCount('institution_membership_audits', 0);
    }

    public function test_no_http_endpoint_can_modify_audit_rows(): void
    {
        $i = $this->institution();
        $admin = $this->member($i, 'institution_admin');
        $this->login($admin, $i);
        $this->postJson(self::AUDITS, ['role' => 'institution_admin'])->assertStatus(405);
        $this->assertDatabaseCount('institution_membership_audits', 0);
    }
}
