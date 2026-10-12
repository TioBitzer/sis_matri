<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Asignación Docente</title>
</head>

<body>

<div class="container">

    <h1>Crear Asignación Docente</h1>

    @if ($errors->any())
        <div>
            <strong>Hay errores:</strong>

            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form
        action="{{ route('teaching-assignments.store') }}"
        method="POST"
    >

        @csrf

        <div>
            <label for="teacher_id">
                Docente:
            </label>

            <select
                name="teacher_id"
                id="teacher_id"
                required
            >

                <option value="">
                    Seleccionar docente
                </option>

                @foreach ($teachers as $teacher)

                    <option
                        value="{{ $teacher->id }}"
                        {{ old('teacher_id') == $teacher->id ? 'selected' : '' }}
                    >
                        {{ $teacher->person->first_name }}
                        {{ $teacher->person->last_name }}
                        — {{ $teacher->employee_code }}
                    </option>

                @endforeach

            </select>
        </div>

        <br>

        <div>
            <label for="academic_offering_id">
                Oferta Académica:
            </label>

            <select
                name="academic_offering_id"
                id="academic_offering_id"
                required
            >

                <option value="">
                    Seleccionar oferta
                </option>

                @foreach ($offerings as $offering)

                    <option
                        value="{{ $offering->id }}"
                        {{ old('academic_offering_id') == $offering->id ? 'selected' : '' }}
                    >
                        {{ $offering->academicUnit->name }}
                        —
                        {{ $offering->academicPeriod->name }}
                        —
                        Sección {{ $offering->section }}
                        —
                        {{ $offering->campus->name }}
                    </option>

                @endforeach

            </select>
        </div>

        <br>

        <div>
            <label for="role">
                Rol:
            </label>

            <select
                name="role"
                id="role"
                required
            >

                <option value="">
                    Seleccionar rol
                </option>

                <option
                    value="principal"
                    {{ old('role') == 'principal' ? 'selected' : '' }}
                >
                    Docente principal
                </option>

                <option
                    value="assistant"
                    {{ old('role') == 'assistant' ? 'selected' : '' }}
                >
                    Docente asistente
                </option>

                <option
                    value="coordinator"
                    {{ old('role') == 'coordinator' ? 'selected' : '' }}
                >
                    Coordinador
                </option>

            </select>
        </div>

        <br>

        <button type="submit">
            Guardar
        </button>

        <a href="{{ route('teaching-assignments.index') }}">
            Cancelar
        </a>

    </form>

</div>

</body>
</html>