<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Oferta Académica</title>
</head>

<body>

<div class="container">

    <h1>Crear Oferta Académica</h1>

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
        action="{{ route('academic-offerings.store') }}"
        method="POST"
    >

        @csrf

        <div>
            <label for="academic_unit_id">
                Unidad Académica:
            </label>

            <select
                name="academic_unit_id"
                id="academic_unit_id"
                required
            >

                <option value="">
                    Seleccionar unidad académica
                </option>

                @foreach ($units as $unit)

                    <option
                        value="{{ $unit->id }}"
                        {{ old('academic_unit_id') == $unit->id ? 'selected' : '' }}
                    >
                        {{ $unit->name }} ({{ $unit->code }})
                    </option>

                @endforeach

            </select>
        </div>

        <br>

        <div>
            <label for="academic_period_id">
                Periodo Académico:
            </label>

            <select
                name="academic_period_id"
                id="academic_period_id"
                required
            >

                <option value="">
                    Seleccionar periodo
                </option>

                @foreach ($periods as $period)

                    <option
                        value="{{ $period->id }}"
                        {{ old('academic_period_id') == $period->id ? 'selected' : '' }}
                    >
                        {{ $period->name }}
                    </option>

                @endforeach

            </select>
        </div>

        <br>

        <div>
            <label for="campus_id">
                Campus:
            </label>

            <select
                name="campus_id"
                id="campus_id"
                required
            >

                <option value="">
                    Seleccionar campus
                </option>

                @foreach ($campuses as $campus)

                    <option
                        value="{{ $campus->id }}"
                        {{ old('campus_id') == $campus->id ? 'selected' : '' }}
                    >
                        {{ $campus->name }}
                    </option>

                @endforeach

            </select>
        </div>

        <br>

        <div>
            <label for="section">
                Sección:
            </label>

            <input
                type="text"
                id="section"
                name="section"
                value="{{ old('section', 'A') }}"
                required
            >
        </div>

        <br>

        <div>
            <label for="capacity">
                Capacidad:
            </label>

            <input
                type="number"
                id="capacity"
                name="capacity"
                value="{{ old('capacity', 30) }}"
                min="1"
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

                Oferta activa
            </label>
        </div>

        <br>

        <button type="submit">
            Guardar
        </button>

        <a href="{{ route('academic-offerings.index') }}">
            Cancelar
        </a>

    </form>

</div>

</body>
</html>