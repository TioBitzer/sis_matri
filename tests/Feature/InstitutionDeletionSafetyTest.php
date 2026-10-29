<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Auditoria de eliminacion de tenants: no destruir datos por cascadas FK.
 * Se espera HTTP 409 cuando existen registros dependientes.
 */
class InstitutionDeletionSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function institution(string $suffix = 'A'): Institution
    {
        return Institution::create([
            'name' => 'Institucion de auditoria '.$suffix,
            'code' => 'AUDIT-DELETE-'.$suffix,
            'type' => 'institute',
            'is_active' => true,
        ]);
    }

    private function asPlatformAdmin(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['is_platform_admin' => true])->save();
        $this->actingAs($user);
    }

    private function assertDeletionBlocked(Institution $institution, string $table, array $record): void
    {
        $this->deleteJson('/api/institutions/'.$institution->id)->assertStatus(409);
        $this->assertDatabaseHas('institutions', ['id' => $institution->id]);
        $this->assertDatabaseHas($table, $record);
    }

    public function test_platform_admin_cannot_delete_institution_with_campus(): void
    {
        $institution = $this->institution();
        DB::table('campuses')->insert([
            'institution_id' => $institution->id,
            'name' => 'Sede principal',
            'is_active' => true,
        ]);
        $this->asPlatformAdmin();
        $this->assertDeletionBlocked($institution, 'campuses', [
            'institution_id' => $institution->id, 'name' => 'Sede principal',
        ]);
    }

    public function test_platform_admin_cannot_delete_institution_with_people(): void
    {
        $institution = $this->institution();
        DB::table('people')->insert([
            'institution_id' => $institution->id,
            'first_name' => 'Estudiante',
            'last_name' => 'Ejemplo',
            'document_type' => 'DNI',
            'document_number' => '87654321',
        ]);
        $this->asPlatformAdmin();
        $this->assertDeletionBlocked($institution, 'people', [
            'institution_id' => $institution->id, 'document_number' => '87654321',
        ]);
    }

    public function test_platform_admin_cannot_delete_institution_with_academic_period(): void
    {
        $institution = $this->institution();
        DB::table('academic_periods')->insert([
            'institution_id' => $institution->id,
            'name' => 'Periodo de prueba',
            'code' => '2026-I',
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
            'is_active' => true,
        ]);
        $this->asPlatformAdmin();
        $this->assertDeletionBlocked($institution, 'academic_periods', [
            'institution_id' => $institution->id, 'name' => 'Periodo de prueba',
        ]);
    }

    public function test_platform_admin_cannot_delete_institution_with_academic_program(): void
    {
        $institution = $this->institution();
        DB::table('academic_programs')->insert([
            'institution_id' => $institution->id,
            'name' => 'Desarrollo de software',
            'code' => 'SW',
            'is_active' => true,
        ]);
        $this->asPlatformAdmin();
        $this->assertDeletionBlocked($institution, 'academic_programs', [
            'institution_id' => $institution->id, 'name' => 'Desarrollo de software',
        ]);
    }

    public function test_platform_admin_cannot_delete_institution_with_academic_unit(): void
    {
        $institution = $this->institution();
        DB::table('academic_units')->insert([
            'institution_id' => $institution->id,
            'name' => 'Programacion',
            'unit_type' => 'course',
            'credits' => 3,
            'hours' => 48,
            'is_active' => true,
        ]);
        $this->asPlatformAdmin();
        $this->assertDeletionBlocked($institution, 'academic_units', [
            'institution_id' => $institution->id, 'name' => 'Programacion',
        ]);
    }

    public function test_platform_admin_cannot_delete_institution_with_membership(): void
    {
        $institution = $this->institution();
        $member = User::factory()->create();
        $member->institutions()->attach($institution->id, ['is_active' => true]);
        $this->asPlatformAdmin();
        $this->assertDeletionBlocked($institution, 'institution_user', [
            'institution_id' => $institution->id, 'user_id' => $member->id,
        ]);
    }

    public function test_platform_admin_can_deactivate_institution_without_deleting_its_data(): void
    {
        $institution = $this->institution();
        DB::table('campuses')->insert([
            'institution_id' => $institution->id,
            'name' => 'Sede historica',
            'is_active' => true,
        ]);
        $this->asPlatformAdmin();
        $this->putJson('/api/institutions/'.$institution->id, [
            'name' => $institution->name,
            'code' => $institution->code,
            'type' => $institution->type,
            'is_active' => false,
        ])->assertOk();
        $this->assertDatabaseHas('institutions', ['id' => $institution->id, 'is_active' => false]);
        $this->assertDatabaseHas('campuses', ['institution_id' => $institution->id, 'name' => 'Sede historica']);
    }

    public function test_platform_admin_cannot_delete_inactive_institution_with_data(): void
    {
        $institution = $this->institution();
        $institution->update(['is_active' => false]);
        DB::table('campuses')->insert([
            'institution_id' => $institution->id,
            'name' => 'Sede archivada',
            'is_active' => false,
        ]);
        $this->asPlatformAdmin();
        $this->assertDeletionBlocked($institution, 'campuses', [
            'institution_id' => $institution->id, 'name' => 'Sede archivada',
        ]);
    }

    public function test_platform_admin_can_delete_only_empty_institution_without_affecting_another(): void
    {
        $empty = $this->institution('EMPTY');
        $preserved = $this->institution('DATA');
        DB::table('campuses')->insert([
            'institution_id' => $preserved->id,
            'name' => 'Sede protegida',
            'is_active' => true,
        ]);
        $this->asPlatformAdmin();
        $this->deleteJson('/api/institutions/'.$empty->id)->assertOk();
        $this->assertDatabaseMissing('institutions', ['id' => $empty->id]);
        $this->assertDatabaseHas('institutions', ['id' => $preserved->id]);
        $this->assertDatabaseHas('campuses', ['institution_id' => $preserved->id, 'name' => 'Sede protegida']);
    }
}
