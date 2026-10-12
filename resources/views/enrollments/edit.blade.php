```html
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Editar Matrícula</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
</head>

<body>

<div class="container mt-5">

    <h1 class="mb-4">Editar Matrícula #{{ $enrollment->id }}</h1>

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Se encontraron errores:</strong>

            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form
        action="{{ route('enrollments.update', $enrollment->id) }}"
        method="POST"
    >
        @csrf
        @method('PUT')

        <!-- Estudiante -->
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

                @foreach ($students as $student)
                    <option
                        value="{{ $student->id }}"
                        {{ old('student_id', $enrollment->student_id) == $student->id ? 'selected' : '' }}
                    >
                        {{ $student->student_code }}
                        -
                        {{ $student->person->first_name }}
                        {{ $student->person->last_name }}
                    </option>
                @endforeach
            </select>
        </div>

        <!-- Período -->
        <div class="mb-3">
            <label for="academic_period_id" class="form-label">
                Período académico
            </label>

            <select
                name="academic_period_id"
                id="academic_period_id"
                class="form-select"
                required
            >
                <option value="">Seleccione un período</option>

                @foreach ($periods as $period)
                    <option
                        value="{{ $period->id }}"
                        {{ old('academic_period_id', $enrollment->academic_period_id) == $period->id ? 'selected' : '' }}
                    >
                        {{ $period->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <!-- Historial académico -->
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

                @foreach ($academicRecords as $record)
                    <option
                        value="{{ $record->id }}"
                        {{ old('academic_record_id', $enrollment->academic_record_id) == $record->id ? 'selected' : '' }}
                    >
                        {{ $record->student->student_code }}
                        -
                        {{ $record->academicProgram->name }}
                        -
                        {{ $record->academicPlan->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <!-- Fecha -->
        <div class="mb-3">
            <label for="enrollment_date" class="form-label">
                Fecha de matrícula
            </label>

            <input
                type="date"
                name="enrollment_date"
                id="enrollment_date"
                class="form-control"
                value="{{ old('enrollment_date', $enrollment->enrollment_date?->format('Y-m-d')) }}"
                required
            >
        </div>

        <!-- Estado -->
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
                <option
                    value="active"
                    {{ old('status', $enrollment->status) == 'active' ? 'selected' : '' }}
                >
                    Activa
                </option>

                <option
                    value="cancelled"
                    {{ old('status', $enrollment->status) == 'cancelled' ? 'selected' : '' }}
                >
                    Cancelada
                </option>

                <option
                    value="completed"
                    {{ old('status', $enrollment->status) == 'completed' ? 'selected' : '' }}
                >
                    Completada
                </option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">
            Actualizar matrícula
        </button>

        <a
            href="{{ route('enrollments.index') }}"
            class="btn btn-secondary"
        >
            Cancelar
        </a>

    </form>

</div>

</body>
</html>
```
