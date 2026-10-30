<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;






class StudentTest extends TestCase
{
    use RefreshDatabase;

    // STUDENT_AUTH_LEGACY_TESTS_V1
    private function ownedInstitution(array $data): Institution
    {
        $institution = Institution::create($data);
        $user = \App\Models\User::factory()->create();
        $user->institutions()->attach($institution->id, ['is_active' => true, 'role' => 'registrar']);
        $this->actingAs($user);
        $this->withHeader('X-Institution-Id', (string) $institution->id);
        return $institution;
    }

    private function authenticateForEmptyStudentRequest(): void
    {
        $this->ownedInstitution([
            'name' => 'Instituto de Prueba',
            'code' => 'TEST001',
            'is_active' => true,
        ]);
    }


    public function test_can_create_a_student(): void
    {
        // 1. Crear una institución
        $institution = $this->ownedInstitution([
            'name' => 'Instituto de Prueba',
            'code' => 'TEST001',
            'is_active' => true,
        ]);

        // 2. Crear una persona
        $person = Person::create([
            'institution_id' => $institution->id,
            'first_name' => 'Carlos',
            'last_name' => 'Ramírez',
            'document_type' => 'DNI',
            'document_number' => '12345678',
        ]);

        // 3. Registrar al estudiante mediante la API
        $response = $this->postJson('/api/students', [
            'person_id' => $person->id,
            'student_code' => 'EST001',
            'admission_date' => '2026-10-14',
            'status' => 'active',
        ]);

        // 4. Verificar la respuesta HTTP
        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.student_code', 'EST001');

        // 5. Verificar que se guardó en la BD
        $this->assertDatabaseHas('students', [
            'person_id' => $person->id,
            'student_code' => 'EST001',
            'status' => 'active',
        ]);
    }
    public function test_rejects_duplicate_student_code(): void
{
    $institution = $this->ownedInstitution([
        'name' => 'Instituto de Prueba',
        'code' => 'TEST001',
        'is_active' => true,
    ]);

    $person1 = Person::create([
        'institution_id' => $institution->id,
        'first_name' => 'Carlos',
        'last_name' => 'Ramírez',
        'document_number' => '12345678',
    ]);

    $person2 = Person::create([
        'institution_id' => $institution->id,
        'first_name' => 'María',
        'last_name' => 'López',
        'document_number' => '87654321',
    ]);

    $this->postJson('/api/students', [
        'person_id' => $person1->id,
        'student_code' => 'EST001',
        'status' => 'active',
    ])->assertCreated();

    $response = $this->postJson('/api/students', [
        'person_id' => $person2->id,
        'student_code' => 'EST001',
        'status' => 'active',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['student_code']);

    $this->assertDatabaseCount('students', 1);
}

public function test_rejects_duplicate_person_as_student(): void
{
    $institution = $this->ownedInstitution([
        'name' => 'Instituto de Prueba',
        'code' => 'TEST001',
        'is_active' => true,
    ]);

    $person = Person::create([
        'institution_id' => $institution->id,
        'first_name' => 'Carlos',
        'last_name' => 'Ramírez',
        'document_number' => '12345678',
    ]);

    // Primer registro: debe funcionar
    $this->postJson('/api/students', [
        'person_id' => $person->id,
        'student_code' => 'EST001',
        'status' => 'active',
    ])->assertCreated();

    // Segundo registro: misma persona, otro código
    $response = $this->postJson('/api/students', [
        'person_id' => $person->id,
        'student_code' => 'EST002',
        'status' => 'active',
    ]);

    // Debe rechazar el registro duplicado
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['person_id']);

    // Solo debe existir un estudiante
    $this->assertDatabaseCount('students', 1);
}

public function test_rejects_missing_required_student_fields(): void
{
    $this->authenticateForEmptyStudentRequest();
    // Intentar registrar un estudiante sin datos
    $response = $this->postJson('/api/students', []);

    // Laravel debe rechazar la solicitud
    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'person_id',
            'student_code',
            'status',
        ]);

    // No debe registrarse ningún estudiante
    $this->assertDatabaseCount('students', 0);
}

public function test_rejects_invalid_student_status(): void
{
    $institution = $this->ownedInstitution([
        'name' => 'Instituto de Prueba',
        'code' => 'TEST001',
        'is_active' => true,
    ]);

    $person = Person::create([
        'institution_id' => $institution->id,
        'first_name' => 'Carlos',
        'last_name' => 'Ramírez',
        'document_number' => '12345678',
    ]);

    $response = $this->postJson('/api/students', [
        'person_id' => $person->id,
        'student_code' => 'EST001',
        'status' => 'pending',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);

    $this->assertDatabaseCount('students', 0);
}

public function test_can_update_a_student(): void
{
    $institution = $this->ownedInstitution([
        'name' => 'Instituto de Prueba',
        'code' => 'TEST001',
        'is_active' => true,
    ]);

    $person = Person::create([
        'institution_id' => $institution->id,
        'first_name' => 'Carlos',
        'last_name' => 'Ramírez',
        'document_number' => '12345678',
    ]);

    $student = \App\Models\Student::create([
        'person_id' => $person->id,
        'student_code' => 'EST001',
        'status' => 'active',
    ]);

    $response = $this->putJson('/api/students/' . $student->id, [
        'student_code' => 'EST002',
        'status' => 'inactive',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.student_code', 'EST002')
        ->assertJsonPath('data.status', 'inactive');

    $this->assertDatabaseHas('students', [
        'id' => $student->id,
        'student_code' => 'EST002',
        'status' => 'inactive',
    ]);
}

public function test_rejects_duplicate_student_code_on_update(): void
{
    $institution = $this->ownedInstitution([
        'name' => 'Instituto de Prueba',
        'code' => 'TEST001',
        'is_active' => true,
    ]);

    $person1 = Person::create([
        'institution_id' => $institution->id,
        'first_name' => 'Carlos',
        'last_name' => 'Ramírez',
        'document_number' => '12345678',
    ]);

    $person2 = Person::create([
        'institution_id' => $institution->id,
        'first_name' => 'María',
        'last_name' => 'López',
        'document_number' => '87654321',
    ]);

    $student1 = \App\Models\Student::create([
        'person_id' => $person1->id,
        'student_code' => 'EST001',
        'status' => 'active',
    ]);

    $student2 = \App\Models\Student::create([
        'person_id' => $person2->id,
        'student_code' => 'EST002',
        'status' => 'active',
    ]);

    // Intentar asignar al segundo estudiante
    // el código del primero
    $response = $this->putJson(
        '/api/students/' . $student2->id,
        [
            'student_code' => $student1->student_code,
            'status' => 'active',
        ]
    );

    // La API debe rechazar el código duplicado
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['student_code']);

    // El segundo estudiante debe conservar su código
    $this->assertDatabaseHas('students', [
        'id' => $student2->id,
        'student_code' => 'EST002',
    ]);

    $this->assertDatabaseCount('students', 2);
}

public function test_can_update_student_without_changing_own_code(): void
{
    $institution = $this->ownedInstitution([
        'name' => 'Instituto de Prueba',
        'code' => 'TEST001',
        'is_active' => true,
    ]);

    $person = Person::create([
        'institution_id' => $institution->id,
        'first_name' => 'Carlos',
        'last_name' => 'Ramírez',
        'document_number' => '12345678',
    ]);

    $student = \App\Models\Student::create([
        'person_id' => $person->id,
        'student_code' => 'EST001',
        'status' => 'active',
    ]);

    // Actualizar el estado conservando el mismo código
    $response = $this->putJson(
        '/api/students/' . $student->id,
        [
            'student_code' => 'EST001',
            'status' => 'inactive',
        ]
    );

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.student_code', 'EST001')
        ->assertJsonPath('data.status', 'inactive');

    $this->assertDatabaseHas('students', [
        'id' => $student->id,
        'student_code' => 'EST001',
        'status' => 'inactive',
    ]);
}

public function test_can_show_a_student_with_person(): void
{
    $institution = $this->ownedInstitution([
        'name' => 'Instituto de Prueba',
        'code' => 'TEST001',
        'is_active' => true,
    ]);

    $person = Person::create([
        'institution_id' => $institution->id,
        'first_name' => 'Carlos',
        'last_name' => 'Ramírez',
        'document_number' => '12345678',
    ]);

    $student = \App\Models\Student::create([
        'person_id' => $person->id,
        'student_code' => 'EST001',
        'status' => 'active',
    ]);

    $response = $this->getJson('/api/students/' . $student->id);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $student->id)
        ->assertJsonPath('data.student_code', 'EST001')
        ->assertJsonPath('data.person.first_name', 'Carlos')
        ->assertJsonPath('data.person.last_name', 'Ramírez');
}

public function test_can_list_students_with_their_person_data(): void
{
    $institution = $this->ownedInstitution([
        'name' => 'Instituto de Prueba',
        'code' => 'TEST001',
        'is_active' => true,
    ]);

    $person1 = Person::create([
        'institution_id' => $institution->id,
        'first_name' => 'Carlos',
        'last_name' => 'Ramírez',
        'document_number' => '12345678',
    ]);

    $person2 = Person::create([
        'institution_id' => $institution->id,
        'first_name' => 'María',
        'last_name' => 'López',
        'document_number' => '87654321',
    ]);

    \App\Models\Student::create([
        'person_id' => $person1->id,
        'student_code' => 'EST001',
        'status' => 'active',
    ]);

    \App\Models\Student::create([
        'person_id' => $person2->id,
        'student_code' => 'EST002',
        'status' => 'active',
    ]);

    $response = $this->getJson('/api/students');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['student_code' => 'EST001'])
        ->assertJsonFragment(['student_code' => 'EST002'])
        ->assertJsonFragment(['first_name' => 'Carlos'])
        ->assertJsonFragment(['first_name' => 'María']);
}

public function test_can_delete_a_student(): void
{
    $institution = $this->ownedInstitution([
        'name' => 'Instituto de Prueba',
        'code' => 'TEST001',
        'is_active' => true,
    ]);

    $person = Person::create([
        'institution_id' => $institution->id,
        'first_name' => 'Carlos',
        'last_name' => 'Ramírez',
        'document_number' => '12345678',
    ]);

    $student = \App\Models\Student::create([
        'person_id' => $person->id,
        'student_code' => 'EST001',
        'status' => 'active',
    ]);

    $response = $this->deleteJson(
        '/api/students/' . $student->id
    );

    $response->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('students', [
        'id' => $student->id,
    ]);

    // La persona debe seguir existiendo.
    $this->assertDatabaseHas('people', [
        'id' => $person->id,
    ]);
}

public function test_returns_404_when_student_does_not_exist(): void
{
    $this->authenticateForEmptyStudentRequest();
    $response = $this->getJson('/api/students/999999');

    $response->assertNotFound();
}

}
