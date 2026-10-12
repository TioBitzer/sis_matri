<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva sede</title>
</head>

<body>

<div class="container">

    <h1>Nueva sede</h1>

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

    <form action="{{ route('campuses.store') }}" method="POST">

        @csrf

        <div>
            <label for="institution_id">Institución:</label>

            <select name="institution_id" id="institution_id" required>
                <option value="">Selecciona una institución</option>

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
            <label for="name">Nombre:</label>

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
            <label for="code">Código:</label>

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
            <label for="address">Dirección:</label>

            <input
                type="text"
                id="address"
                name="address"
                value="{{ old('address') }}"
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

                Sede activa
            </label>
        </div>

        <br>

        <button type="submit">
            Guardar sede
        </button>

        <a href="{{ route('campuses.index') }}">
            Cancelar
        </a>

    </form>

</div>

</body>
</html>