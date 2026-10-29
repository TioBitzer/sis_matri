<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstitutionPlatformAdminTest extends TestCase
{
    use RefreshDatabase;

    private function platformAdmin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_platform_admin' => true])->save();
        return $user;
    }

    private function institution(): Institution
    {
        return Institution::create([
            'name' => 'Institución base',
            'code' => 'INS-BASE',
            'type' => 'institute',
            'is_active' => true,
        ]);
    }

    public function test_platform_admin_can_create_institution_without_existing_membership(): void
    {
        $this->actingAs($this->platformAdmin());
        $this->postJson('/api/institutions', [
            'name' => 'Institución creada', 'code' => 'CREATED', 'type' => 'institute',
        ])->assertCreated()->assertJsonPath('data.code', 'CREATED');
        $this->assertDatabaseHas('institutions', ['code' => 'CREATED']);
    }

    public function test_platform_admin_can_update_institution_without_tenant_header(): void
    {
        $institution = $this->institution();
        $this->actingAs($this->platformAdmin());
        $this->putJson('/api/institutions/'.$institution->id, [
            'name' => 'Institución actualizada', 'code' => 'INS-BASE', 'type' => 'institute',
            'is_active' => false,
        ])->assertOk()->assertJsonPath('data.name', 'Institución actualizada');
    }

    public function test_platform_admin_can_delete_empty_institution(): void
    {
        $institution = $this->institution();
        $this->actingAs($this->platformAdmin());
        $this->deleteJson('/api/institutions/'.$institution->id)->assertOk();
        $this->assertDatabaseMissing('institutions', ['id' => $institution->id]);
    }

    public function test_new_user_is_not_platform_admin_and_cannot_self_assign_role(): void
    {
        $user = User::factory()->create();
        $this->assertFalse($user->is_platform_admin);
        $this->assertFalse(in_array('is_platform_admin', $user->getFillable(), true));
    }
}
