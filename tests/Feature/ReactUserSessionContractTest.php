<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReactUserSessionContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_read_session_user(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_authenticated_user_receives_only_public_session_fields(): void
    {
        $user = User::factory()->create([
            'name' => 'Usuario Integracion',
            'email' => 'integracion@example.test',
        ]);
        $user->forceFill(['is_platform_admin' => true])->save();

        $this->actingAs($user, 'web')
            ->getJson('/api/user')
            ->assertOk()
            ->assertExactJson([
                'id' => $user->id,
                'name' => 'Usuario Integracion',
                'email' => 'integracion@example.test',
            ]);
    }
}
