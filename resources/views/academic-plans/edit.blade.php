<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Plan Académico</title>
</head>

<body>

<div class="container">

    <h1>Editar Plan Académico</h1>

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
        action="{{ route('academic-plans.update', $plan) }}"
        method="POST"
    >

        @csrf
        @method('PUT')

        <div>
            <label for="academic_program_id">
                Programa Académico:
            </label>

            <select
                name="academic_program_id"
                id="academic_program_id"
                required
            >

                @foreach ($programs as $program)

                    <option
                        value="{{ $program->id }}"
                        {{ old('academic_program_id', $plan->academic_program_id) == $program->id ? 'selected' : '' }}
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
                value="{{ old('name', $plan->name) }}"
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
                value="{{ old('version', $plan->version) }}"
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
                value="{{ old('start_date', $plan->start_date->format('Y-m-d')) }}"
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
                value="{{ old('end_date', $plan->end_date?->format('Y-m-d')) }}"
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
                    {{ old('is_active', $plan->is_active) ? 'checked' : '' }}
                >

                Plan activo
            </label>
        </div>

        <br>

        <button type="submit">
            Guardar cambios
        </button>

        <a href="{{ route('academic-plans.show', $plan) }}">
            Cancelar
        </a>

    </form>

</div>

</body>
</html>