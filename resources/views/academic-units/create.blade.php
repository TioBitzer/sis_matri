<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Unidad Académica</title>
</head>

<body>

<div class="container">

    <h1>Crear Unidad Académica</h1>

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
        action="{{ route('academic-units.store') }}"
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
                value="{{ old('name') }}"
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
                value="{{ old('code') }}"
                required
            >
        </div>

        <br>

        <div>
            <label for="unit_type">
                Tipo:
            </label>

            <input
                type="text"
                id="unit_type"
                name="unit_type"
                value="{{ old('unit_type', 'course') }}"
                required
            >
        </div>

        <br>

        <div>
            <label for="description">
                Descripción:
            </label>

            <textarea
                id="description"
                name="description"
                rows="4"
            >{{ old('description') }}</textarea>
        </div>

        <br>

        <div>
            <label for="credits">
                Créditos:
            </label>

            <input
                type="number"
                id="credits"
                name="credits"
                value="{{ old('credits', 3) }}"
                min="0"
                required
            >
        </div>

        <br>

        <div>
            <label for="hours">
                Horas:
            </label>

            <input
                type="number"
                id="hours"
                name="hours"
                value="{{ old('hours', 4) }}"
                min="0"
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
                    checked
                >

                Unidad activa
            </label>
        </div>

        <br>

        <button type="submit">
            Guardar
        </button>

        <a href="{{ route('academic-units.index') }}">
            Cancelar
        </a>

    </form>

</div>

</body>
</html>