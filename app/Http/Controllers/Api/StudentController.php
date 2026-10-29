<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    // STUDENT_TENANT_ISOLATION_V1
    // La institución proviene exclusivamente del middleware, nunca del body.
    private function institutionId(Request $request): int
    {
        $id = (int) $request->attributes->get('institution_id', 0);
        abort_if($id < 1, 403, 'No se ha validado la institución seleccionada.');
        return $id;
    }

    private function scopedStudents(Request $request): Builder
    {
        $institutionId = $this->institutionId($request);
        return Student::query()->whereHas('person', function (Builder $query) use ($institutionId) {
            $query->where('institution_id', $institutionId);
        });
    }

    public function index(Request $request)
    {
        $students = $this->scopedStudents($request)->with('person')->get();

        return response()->json([
            'success' => true,
            'data' => $students,
        ]);
    }

    public function show(Request $request, string $id)
    {
        $student = $this->scopedStudents($request)->with('person')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $student,
        ]);
    }

    public function store(StoreStudentRequest $request)
    {


        $validated = $request->validated();

        // Rechaza referencias a personas de otra institución, aunque el usuario
        // tenga membresías activas en ambas.
        Validator::make($validated, [
            'person_id' => [
                'required', 'integer',
                Rule::exists('people', 'id')->where('institution_id', $this->institutionId($request)),
            ],
        ])->validate();

        $student = Student::create($validated);

        return response()->json([
            'success' => true,
            'data' => $student,
        ], 201);
    }

    public function update(UpdateStudentRequest $request, string $id)
    {
        $student = $this->scopedStudents($request)->findOrFail($id);

       $validated = $request->validated();

        if ($request->exists('person_id')) {
            $identity = Validator::make(
                ['person_id' => $request->input('person_id')],
                [
                    'person_id' => [
                        'required', 'integer',
                        Rule::exists('people', 'id')->where('institution_id', $this->institutionId($request)),
                        Rule::unique('students', 'person_id')->ignore($student->id),
                    ],
                ]
            )->validate();

            if (
                (int) $student->person_id !== (int) $identity['person_id']
                && ($student->academicRecords()->exists() || $student->enrollments()->exists())
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede cambiar la persona de un estudiante con historial académico o matrículas.',
                ], 409);
            }

            $validated['person_id'] = $identity['person_id'];
        }

        $student->update($validated);

        return response()->json([
            'success' => true,
            'data' => $student,
        ]);

    }
    public function destroy(Request $request, string $id)
{
    $student = $this->scopedStudents($request)->findOrFail($id);

    // Evita borrados en cascada que podrían llevarse historiales y matrículas.
    if ($student->academicRecords()->exists() || $student->enrollments()->exists()) {
        return response()->json([
            'success' => false,
            'message' => 'No se puede eliminar el estudiante porque tiene historial académico o matrículas.',
        ], 409);
    }

    $student->delete();

    return response()->json([
        'success' => true,
        'message' => 'Estudiante eliminado correctamente',
    ]);
}
}