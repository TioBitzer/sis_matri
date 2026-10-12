<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar institución</title>
</head>

<body>

<div class="container">

    <h1>Editar institución</h1>

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
        action="{{ route('institutions.update', $institution) }}"
        method="POST"
    >

        @csrf
        @method('PUT')

        <div>
            <label for="name">Nombre:</label>

            <input
                type="text"
                id="name"
                name="name"
                value="{{ old('name', $institution->name) }}"
                required
            >
        </div>

        <br>

        <div>
            <label for="code">Código:</label>

            <input
                type="text"
                id="code"
                name="code"
                value="{{ old('code', $institution->code) }}"
                required
            >
        </div>

        <br>

        <div>
            <label for="type">Tipo:</label>

            <select name="type" id="type" required>

                <option value="institute"
                    {{ old('type', $institution->type) == 'institute' ? 'selected' : '' }}>
                    Instituto
                </option>

                <option value="university"
                    {{ old('type', $institution->type) == 'university' ? 'selected' : '' }}>
                    Universidad
                </option>

                <option value="school"
                    {{ old('type', $institution->type) == 'school' ? 'selected' : '' }}>
                    Colegio
                </option>

            </select>
        </div>

        <br>

        <div>
            <label for="description">Descripción:</label>

            <textarea
                id="description"
                name="description"
                rows="4"
            >{{ old('description', $institution->description) }}</textarea>
        </div>

        <br>

        <div>
            <label>

                <input
                    type="checkbox"
                    name="is_active"
                    value="1"
                    {{ old('is_active', $institution->is_active) ? 'checked' : '' }}
                >

                Institución activa

            </label>
        </div>

        <br>

        <button type="submit">
            Guardar cambios
        </button>

        <a href="{{ route('institutions.show', $institution) }}">
            Cancelar
        </a>

    </form>

</div>

</body>
</html>