<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Auditoria de las rutas de instituciones.
 *
 * Contrato propuesto:
 * - GET: requiere autenticacion, membresia activa y contexto de institucion.
 * - POST/PUT/DELETE: operaciones de plataforma, prohibidas a miembros comunes.
 * - Un usuario con dos membresias no puede mezclar contextos.
 *
 * IMPORTANTE: Estas pruebas son RED FIRST: inicialmente deben fallar.
 * No constituyen una implementacion de administrador de plataforma.
 */
class InstitutionSecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/institutions';
    private int $number = 0;

    private function institution(string $label = 'A', bool $active = true): Institution
    {
        $n = ++$this->number;
        return Institution::create([
            'name' => 'Institucion auditoria '.$label.' '.$n,
            'code' => 'AUD-'.$label.'-'.$n,
            'type' => 'institute',
            'is_active' => $active,
        ]);
    }

    private function member(Institution $institution, bool $active = true): User
    {
        $user = User::factory()->create();
        $user->institutions()->attach($institution->id, ['is_active' => $active]);
        return $user;
    }

    private function headers(Institution $institution): array
    {
        return ['X-Institution-Id' => (string) $institution->id];
    }

    private function payload(string $suffix = 'A'): array
    {
        $n = ++$this->number;
        return [
            'name' => 'Institucion nueva '.$suffix.' '.$n,
            'code' => 'NEW-'.$suffix.'-'.$n,
            'type' => 'institute',
            'is_active' => true,
        ];
    }

    public function test_anonymous_cannot_list_institutions(): void
    {
        $a = $this->institution();
        $this->getJson(self::ENDPOINT, $this->headers($a))->assertUnauthorized();
    }

    public function test_anonymous_cannot_show_institution(): void
    {
        $a = $this->institution();
        $this->getJson(self::ENDPOINT.'/'.$a->id, $this->headers($a))->assertUnauthorized();
    }

    public function test_anonymous_cannot_create_institution(): void
    {
        $this->postJson(self::ENDPOINT, $this->payload())->assertUnauthorized();
        $this->assertDatabaseCount('institutions', 0);
    }

    public function test_anonymous_cannot_update_institution(): void
    {
        $a = $this->institution();
        $this->putJson(self::ENDPOINT.'/'.$a->id, $this->payload(), $this->headers($a))
            ->assertUnauthorized();
        $this->assertDatabaseHas('institutions', ['id' => $a->id, 'name' => $a->name]);
    }

    public function test_anonymous_cannot_delete_institution(): void
    {
        $a = $this->institution();
        $this->deleteJson(self::ENDPOINT.'/'.$a->id, [], $this->headers($a))
            ->assertUnauthorized();
        $this->assertDatabaseHas('institutions', ['id' => $a->id]);
    }

    public function test_authenticated_member_must_select_institution_to_list(): void
    {
        $a = $this->institution();
        $this->actingAs($this->member($a));
        $this->getJson(self::ENDPOINT)->assertUnprocessable();
    }

    public function test_invalid_institution_header_is_rejected(): void
    {
        $a = $this->institution();
        $this->actingAs($this->member($a));
        $this->getJson(self::ENDPOINT, ['X-Institution-Id' => 'abc'])
            ->assertUnprocessable();
    }

    public function test_user_without_membership_cannot_list_institutions(): void
    {
        $a = $this->institution();
        $this->actingAs(User::factory()->create());
        $this->getJson(self::ENDPOINT, $this->headers($a))->assertForbidden();
    }

    public function test_inactive_membership_cannot_list_institutions(): void
    {
        $a = $this->institution();
        $this->actingAs($this->member($a, false));
        $this->getJson(self::ENDPOINT, $this->headers($a))->assertForbidden();
    }

    public function test_inactive_institution_cannot_be_selected(): void
    {
        $a = $this->institution('A', false);
        $this->actingAs($this->member($a));
        $this->getJson(self::ENDPOINT, $this->headers($a))->assertForbidden();
    }

    public function test_list_only_returns_selected_institution(): void
    {
        $a = $this->institution('A');
        $this->institution('B');
        $this->actingAs($this->member($a));
        $this->getJson(self::ENDPOINT, $this->headers($a))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $a->id);
    }

    public function test_user_cannot_show_foreign_institution(): void
    {
        $a = $this->institution('A');
        $b = $this->institution('B');
        $this->actingAs($this->member($a));
        $this->getJson(self::ENDPOINT.'/'.$b->id, $this->headers($a))->assertNotFound();
    }

    public function test_authorized_member_can_show_selected_institution(): void
    {
        $a = $this->institution('A');
        $this->actingAs($this->member($a));
        $this->getJson(self::ENDPOINT.'/'.$a->id, $this->headers($a))
            ->assertOk()->assertJsonPath('data.id', $a->id);
    }

    public function test_two_memberships_cannot_mix_institution_lists(): void
    {
        $a = $this->institution('A');
        $b = $this->institution('B');
        $user = $this->member($a);
        $user->institutions()->attach($b->id, ['is_active' => true]);
        $this->actingAs($user);

        $this->getJson(self::ENDPOINT, $this->headers($a))
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $a->id);
        $this->getJson(self::ENDPOINT, $this->headers($b))
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $b->id);
    }

    public function test_two_memberships_cannot_show_other_context_institution(): void
    {
        $a = $this->institution('A');
        $b = $this->institution('B');
        $user = $this->member($a);
        $user->institutions()->attach($b->id, ['is_active' => true]);
        $this->actingAs($user);
        $this->getJson(self::ENDPOINT.'/'.$b->id, $this->headers($a))->assertNotFound();
    }

    public function test_ordinary_member_cannot_create_platform_institution(): void
    {
        $a = $this->institution('A');
        $this->actingAs($this->member($a));
        $this->postJson(self::ENDPOINT, $this->payload('B'), $this->headers($a))
            ->assertForbidden();
        $this->assertDatabaseCount('institutions', 1);
    }

    public function test_ordinary_member_cannot_modify_platform_institution(): void
    {
        $a = $this->institution('A');
        $this->actingAs($this->member($a));
        $this->putJson(self::ENDPOINT.'/'.$a->id, $this->payload('C'), $this->headers($a))
            ->assertForbidden();
        $this->assertDatabaseHas('institutions', ['id' => $a->id, 'name' => $a->name]);
    }

    public function test_ordinary_member_cannot_delete_platform_institution(): void
    {
        $a = $this->institution('A');
        $this->actingAs($this->member($a));
        $this->deleteJson(self::ENDPOINT.'/'.$a->id, [], $this->headers($a))
            ->assertForbidden();
        $this->assertDatabaseHas('institutions', ['id' => $a->id]);
    }
}
