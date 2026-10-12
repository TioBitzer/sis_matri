<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Plan Académico</title>
</head>

<body>

<div class="container">

    <h1>Nuevo Plan Académico</h1>

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

    <form action="{{ route('academic-plans.store') }}" method="POST">

        @csrf

        <div>
            <label for="academic_program_id">
                Programa Académico:
            </label>

            <select
                name="academic_program_id"
                id="academic_program_id"
                required
            >

                <option value="">
                    Selecciona un programa
                </option>

                @foreach ($programs as $program)
                    <option
                        value="{{ $program->id }}"
                        {{ old('academic_program_id') == $program->id ? 'selected' : '' }}
                    >
                        {{ $program->name }}
                    </option>
                @endforeach

            </select>
        </div>

        <br>

        <div>
            <label for="name">
                Nombre del plan:
            </label>

            <input
                type="text"
                id="name"
                name="name"
                value="{{ old('name') }}"
                placeholder="Plan Académico 2026"
                required
            >
        </div>

        <br>

        <div>
            <label for="version">
                Versión:
            </label>

            <input
                type="text"
                id="version"
                name="version"
                value="{{ old('version') }}"
                placeholder="1.0"
                required
            >
        </div>

        <br>

        <div>
            <label for="start_date">
                Fecha de inicio:
            </label>

            <input
                type="date"
                id="start_date"
                name="start_date"
                value="{{ old('start_date') }}"
                required
            >
        </div>

        <br>

        <div>
            <label for="end_date">
                Fecha de fin:
            </label>

            <input
                type="date"
                id="end_date"
                name="end_date"
                value="{{ old('end_date') }}"
            >

            <small>
                Déjalo vacío si el plan sigue vigente.
            </small>
        </div>

        <br>

        <div>
            <label>
                <input
                    type="checkbox"
                    name="is_active"
                    value="1"
                    {{ old('is_active', true) ? 'checked' : '' }}
                >

                Plan activo
            </label>
        </div>

        <br>

        <button type="submit">
            Guardar plan
        </button>

        <a href="{{ route('academic-plans.index') }}">
            Cancelar
        </a>

    </form>

</div>

</body>
</html>