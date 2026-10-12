<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Unidad Académica</title>
</head>

<body>

<div class="container">

    <h1>Editar Unidad Académica</h1>

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
        action="{{ route('academic-units.update', $unit) }}"
        method="POST"
    >

        @csrf
        @method('PUT')

        <div>
            <label for="institution_id">
                Institución:
            </label>

            <select
                name="institution_id"
                id="institution_id"
                required
            >

                @foreach ($institutions as $institution)

                    <option
                        value="{{ $institution->id }}"
                        {{ old('institution_id', $unit->institution_id) == $institution->id ? 'selected' : '' }}
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
                value="{{ old('name', $unit->name) }}"
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
                value="{{ old('code', $unit->code) }}"
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
                value="{{ old('unit_type', $unit->unit_type) }}"
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
            >{{ old('description', $unit->description) }}</textarea>
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
                value="{{ old('credits', $unit->credits) }}"
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
                value="{{ old('hours', $unit->hours) }}"
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
                    {{ old('is_active', $unit->is_active) ? 'checked' : '' }}
                >

                Unidad activa
            </label>
        </div>

        <br>

        <button type="submit">
            Guardar cambios
        </button>

        <a href="{{ route('academic-units.show', $unit) }}">
            Cancelar
        </a>

    </form>

</div>

</body>
</html>