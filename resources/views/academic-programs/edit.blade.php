<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Programa Académico</title>
</head>

<body>

<div class="container">

    <h1>Editar Programa Académico</h1>

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
        action="{{ route('academic-programs.update', $program) }}"
        method="POST"
    >

        @csrf
        @method('PUT')

        <div>
            <label for="institution_id">Institución:</label>

            <select name="institution_id" id="institution_id" required>

                @foreach ($institutions as $institution)

                    <option
                        value="{{ $institution->id }}"
                        {{ old('institution_id', $program->institution_id) == $institution->id ? 'selected' : '' }}
                    >
                        {{ $institution->name }}
                    </option>

                @endforeach

            </select>
        </div>

        <br>

        <div>
            <label for="name">Nombre:</label>

            <input
                type="text"
                id="name"
                name="name"
                value="{{ old('name', $program->name) }}"
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
                value="{{ old('code', $program->code) }}"
                required
            >
        </div>

        <br>

        <div>
            <label for="description">Descripción:</label>

            <textarea
                id="description"
                name="description"
                rows="4"
            >{{ old('description', $program->description) }}</textarea>
        </div>

        <br>

        <div>
            <label>
                <input
                    type="checkbox"
                    name="is_active"
                    value="1"
                    {{ old('is_active', $program->is_active) ? 'checked' : '' }}
                >

                Programa activo
            </label>
        </div>

        <br>

        <button type="submit">
            Guardar cambios
        </button>

        <a href="{{ route('academic-programs.show', $program) }}">
            Cancelar
        </a>

    </form>

</div>

</body>
</html>