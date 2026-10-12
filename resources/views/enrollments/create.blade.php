<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Nueva Matrícula</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
</head>

<body>

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Nueva matrícula</h1>

        <a href="{{ route('enrollments.index') }}" class="btn btn-secondary">
            Volver
        </a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <strong>Corrige los siguientes errores:</strong>

            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('enrollments.store') }}" method="POST">
        @csrf

        <div class="card">
            <div class="card-header">
                <strong>Datos de la matrícula</strong>
            </div>

            <div class="card-body">

                {{-- Estudiante --}}
                <div class="mb-3">
                    <label for="student_id" class="form-label">
                        Estudiante
                    </label>

                    <select
                        name="student_id"
                        id="student_id"
                        class="form-select"
                        required
                    >
                        <option value="">Seleccione un estudiante</option>

                        @foreach($students as $student)
                            <option
                                value="{{ $student->id }}"
                                {{ old('student_id') == $student->id ? 'selected' : '' }}
                            >
                                {{ $student->student_code }} -
                                {{ $student->person->first_name }}
                                {{ $student->person->last_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Periodo académico --}}
                <div class="mb-3">
                    <label for="academic_period_id" class="form-label">
                        Periodo académico
                    </label>

                    <select
                        name="academic_period_id"
                        id="academic_period_id"
                        class="form-select"
                        required
                    >
                        <option value="">Seleccione un periodo</option>

                        @foreach($periods as $period)
                            <option
                                value="{{ $period->id }}"
                                {{ old('academic_period_id') == $period->id ? 'selected' : '' }}
                            >
                                {{ $period->name }} ({{ $period->code }})
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Historial académico --}}
                <div class="mb-3">
                    <label for="academic_record_id" class="form-label">
                        Historial académico
                    </label>

                    <select
                        name="academic_record_id"
                        id="academic_record_id"
                        class="form-select"
                        required
                    >
                        <option value="">Seleccione un historial</option>

                        @foreach($academicRecords as $record)
                            <option
                                value="{{ $record->id }}"
                                {{ old('academic_record_id') == $record->id ? 'selected' : '' }}
                            >
                                {{ $record->student->person->first_name }}
                                {{ $record->student->person->last_name }}
                                -
                                {{ $record->academicProgram->name }}
                                -
                                {{ $record->academicPlan->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Fecha --}}
                <div class="mb-3">
                    <label for="enrollment_date" class="form-label">
                        Fecha de matrícula
                    </label>

                    <input
                        type="date"
                        name="enrollment_date"
                        id="enrollment_date"
                        class="form-control"
                        value="{{ old('enrollment_date', date('Y-m-d')) }}"
                        required
                    >
                </div>

                {{-- Estado --}}
                <div class="mb-3">
                    <label for="status" class="form-label">
                        Estado
                    </label>

                    <select
                        name="status"
                        id="status"
                        class="form-select"
                        required
                    >
                        <option value="active"
                            {{ old('status', 'active') == 'active' ? 'selected' : '' }}>
                            Activa
                        </option>

                        <option value="completed"
                            {{ old('status') == 'completed' ? 'selected' : '' }}>
                            Completada
                        </option>

                        <option value="cancelled"
                            {{ old('status') == 'cancelled' ? 'selected' : '' }}>
                            Cancelada
                        </option>
                    </select>
                </div>

            </div>

            <div class="card-footer text-end">
                <a
                    href="{{ route('enrollments.index') }}"
                    class="btn btn-secondary"
                >
                    Cancelar
                </a>

                <button type="submit" class="btn btn-primary">
                    Guardar matrícula
                </button>
            </div>
        </div>

    </form>

</div>

</body>
</html>