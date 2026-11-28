<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Institution;

class DemoTesisSeeder extends Seeder
{
    public function run(): void
    {
        if (!app()->environment('local')) {
            throw new \RuntimeException('Solo permitido en entorno local.');
        }

        DB::transaction(function () {
            $institution = Institution::where('code', 'DEMO-001')
                ->where('is_active', true)
                ->firstOrFail();

            $id = $institution->id;

            $crear = function ($table, $keys, $values = []) {
                $existing = DB::table($table)->where($keys)->first();

                if ($existing) {
                    return $existing->id;
                }

                return DB::table($table)->insertGetId(
                    array_merge($keys, $values)
                );
            };

            $campus = $crear('campuses', [
                'institution_id' => $id,
                'code' => 'DEMO-LIM',
            ], [
                'name' => 'Lima Central',
            ]);

            $program = $crear('academic_programs', [
                'institution_id' => $id,
                'code' => 'DEMO-DS',
            ], [
                'name' => 'Desarrollo de Software',
                'is_active' => true,
            ]);

            $plan = $crear('academic_plans', [
                'academic_program_id' => $program,
                'version' => '1.0',
            ], [
                'name' => 'Plan Curricular 2026',
                'is_active' => true,
            ]);

            $level = $crear('academic_levels', [
                'academic_plan_id' => $plan,
                'order' => 1,
            ], [
                'name' => 'Primer ciclo',
                'is_active' => true,
            ]);

            $period = $crear('academic_periods', [
                'institution_id' => $id,
                'code' => 'DEMO-2026-II',
            ], [
                'name' => '2026-II',
                'start_date' => '2026-07-01',
                'end_date' => '2026-12-31',
                'is_active' => true,
            ]);

            $teacherPerson = $crear('people', [
                'institution_id' => $id,
                'document_type' => 'DNI',
                'document_number' => '00000001',
            ], [
                'first_name' => 'Carlos',
                'last_name' => 'Docente Demo',
            ]);

            $teacher = $crear('teachers', [
                'institution_id' => $id,
                'person_id' => $teacherPerson,
            ], [
                'employee_code' => 'DEMO-DOC-01',
                'specialization' => 'Desarrollo de Software',
                'is_active' => true,
            ]);

            $courses = [
                'Fundamentos de Programacion',
                'Base de Datos I',
                'Diseno y Desarrollo Web',
            ];

            foreach ($courses as $index => $name) {
                $position = $index + 1;

                $unit = $crear('academic_units', [
                    'institution_id' => $id,
                    'code' => 'DEMO-CUR-' . $position,
                ], [
                    'name' => $name,
                    'credits' => 4,
                    'hours' => 64,
                    'is_active' => true,
                ]);

                $crear('academic_curriculum_items', [
                    'academic_plan_id' => $plan,
                    'academic_level_id' => $level,
                    'academic_unit_id' => $unit,
                ], [
                    'credits' => 4,
                    'hours' => 64,
                    'is_required' => true,
                    'position' => $position,
                ]);

                $offering = $crear('academic_offerings', [
                    'academic_unit_id' => $unit,
                    'academic_period_id' => $period,
                    'campus_id' => $campus,
                    'section' => 'A',
                ], [
                    'capacity' => 30,
                    'is_active' => true,
                ]);

                $crear('teaching_assignments', [
                    'teacher_id' => $teacher,
                    'academic_offering_id' => $offering,
                ], [
                    'role' => 'Titular',
                ]);
            }

            $students = [
                ['Andrea', 'Estudiante Demo', '00000002'],
                ['Diego', 'Estudiante Demo', '00000003'],
                ['Valeria', 'Estudiante Demo', '00000004'],
            ];

            foreach ($students as $index => $data) {
                $person = $crear('people', [
                    'institution_id' => $id,
                    'document_type' => 'DNI',
                    'document_number' => $data[2],
                ], [
                    'first_name' => $data[0],
                    'last_name' => $data[1],
                ]);

                $student = $crear('students', [
                    'person_id' => $person,
                ], [
                    'student_code' => 'DEMO-EST-' . ($index + 1),
                    'status' => 'active',
                ]);

                $crear('academic_records', [
                    'student_id' => $student,
                    'academic_program_id' => $program,
                    'academic_plan_id' => $plan,
                ], [
                    'start_date' => '2026-07-01',
                    'status' => 'active',
                ]);
            }

            $this->command->info('Datos de demostracion creados correctamente.');
            $this->command->info('Institucion ID: ' . $id);
        });
    }
}
