<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Periodo Académico</title>
</head>

<body>

<div class="container">

    <h1>Crear Periodo Académico</h1>

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
        action="{{ route('academic-periods.store') }}"
        method="POST"
    >

        @csrf

        <div>
            <label for="institution_id">
                Institución:
            </label>

            <select
                name="institution_id"
                id="institution_id"
                required
            >

                <option value="">
                    Seleccionar institución
                </option>

                @foreach ($institutions as $institution)

                    <option
                        value="{{ $institution->id }}"
                        {{ old('institution_id') == $institution->id ? 'selected' : '' }}
                    >
                        {{ $institution->name }}
                    </option>

                @endforeach

            </select>
        </div>

        <br>

        <div>
            <label for="name">
                Nombre:
            </label>

            <input
                type="text"
                id="name"
                name="name"
                value="{{ old('name', '2026-I') }}"
                required
            >
        </div>

        <br>

        <div>
            <label for="code">
                Código:
            </label>

            <input
                type="text"
                id="code"
                name="code"
                value="{{ old('code', '2026-I') }}"
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
                value="{{ old('start_date', '2026-01-01') }}"
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
                value="{{ old('end_date', '2026-07-31') }}"
                required
            >
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

                Periodo activo
            </label>
        </div>

        <br>

        <button type="submit">
            Guardar
        </button>

        <a href="{{ route('academic-periods.index') }}">
            Cancelar
        </a>

    </form>

</div>

</body>
</html>