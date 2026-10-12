<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Nivel Académico</title>
</head>

<body>

<div class="container">

    <h1>Nuevo Nivel Académico</h1>

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

    <form action="{{ route('academic-levels.store') }}" method="POST">

        @csrf

        <div>
            <label for="academic_plan_id">
                Plan Académico:
            </label>

            <select
                name="academic_plan_id"
                id="academic_plan_id"
                required
            >

                <option value="">
                    Selecciona un plan
                </option>

                @foreach ($plans as $plan)

                    <option
                        value="{{ $plan->id }}"
                        {{ old('academic_plan_id') == $plan->id ? 'selected' : '' }}
                    >
                        {{ $plan->academicProgram->name }} -
                        {{ $plan->name }}
                    </option>

                @endforeach

            </select>
        </div>

        <br>

        <div>
            <label for="name">
                Nombre del nivel:
            </label>

            <input
                type="text"
                id="name"
                name="name"
                value="{{ old('name') }}"
                placeholder="Nivel 1"
                required
            >
        </div>

        <br>

        <div>
            <label for="order">
                Orden:
            </label>

            <input
                type="number"
                id="order"
                name="order"
                value="{{ old('order', 1) }}"
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

                Nivel activo
            </label>
        </div>

        <br>

        <button type="submit">
            Guardar nivel
        </button>

        <a href="{{ route('academic-levels.index') }}">
            Cancelar
        </a>

    </form>

</div>

</body>
</html>