<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_web_cruds_are_not_publicly_available(): void
    {
        $this->get('/institutions')->assertNotFound();
        $this->get('/enrollments')->assertNotFound();
        $this->get('/academic-programs')->assertNotFound();
    }

    public function test_login_requires_credentials(): void
    {
        $this->postJson('/login', [])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $user = User::factory()->create();

        $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);

        $this->assertGuest('web');
    }

    public function test_login_starts_session_without_exposing_sensitive_attributes(): void
    {
        $user = User::factory()->create([
            'password' => 'TestPassword123!',
        ]);

        $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'TestPassword123!',
        ])->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('user.is_platform_admin');

        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_login_is_rate_limited(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertUnprocessable();
        }

        $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_logout_invalidates_web_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web')
            ->postJson('/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Sesion cerrada correctamente.');

        $this->assertGuest('web');
    }

    public function test_guest_cannot_logout(): void
    {
        $this->postJson('/logout')->assertUnauthorized();
    }

    public function test_api_user_requires_authentication(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_no_public_registration_route_is_created(): void
    {
        $this->postJson('/register', [])->assertNotFound();
    }
}
