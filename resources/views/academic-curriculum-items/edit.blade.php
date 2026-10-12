<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Elemento Curricular</title>
</head>

<body>

<div class="container">

    <h1>Editar Elemento Curricular</h1>

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
        action="{{ route('academic-curriculum-items.update', $item) }}"
        method="POST"
    >

        @csrf
        @method('PUT')

        <div>
            <label for="academic_plan_id">
                Plan Académico:
            </label>

            <select
                name="academic_plan_id"
                id="academic_plan_id"
                required
            >

                @foreach ($plans as $plan)

                    <option
                        value="{{ $plan->id }}"
                        {{ old('academic_plan_id', $item->academic_plan_id) == $plan->id ? 'selected' : '' }}
                    >
                        {{ $plan->academicProgram->name }} -
                        {{ $plan->name }}
                    </option>

                @endforeach

            </select>
        </div>

        <br>

        <div>
            <label for="academic_level_id">
                Nivel Académico:
            </label>

            <select
                name="academic_level_id"
                id="academic_level_id"
                required
            >

                @foreach ($levels as $level)

                    <option
                        value="{{ $level->id }}"
                        {{ old('academic_level_id', $item->academic_level_id) == $level->id ? 'selected' : '' }}
                    >
                        {{ $level->name }}
                    </option>

                @endforeach

            </select>
        </div>

        <br>

        <div>
            <label for="academic_unit_id">
                Unidad Académica:
            </label>

            <select
                name="academic_unit_id"
                id="academic_unit_id"
                required
            >

                @foreach ($units as $unit)

                    <option
                        value="{{ $unit->id }}"
                        {{ old('academic_unit_id', $item->academic_unit_id) == $unit->id ? 'selected' : '' }}
                    >
                        {{ $unit->name }} - {{ $unit->code }}
                    </option>

                @endforeach

            </select>
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
                value="{{ old('credits', $item->credits) }}"
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
                value="{{ old('hours', $item->hours) }}"
                min="0"
                required
            >
        </div>

        <br>

        <div>
            <label>
                <input
                    type="checkbox"
                    name="is_required"
                    value="1"
                    {{ old('is_required', $item->is_required) ? 'checked' : '' }}
                >

                Unidad obligatoria
            </label>
        </div>

        <br>

        <div>
            <label for="position">
                Posición:
            </label>

            <input
                type="number"
                id="position"
                name="position"
                value="{{ old('position', $item->position) }}"
                min="1"
                required
            >
        </div>

        <br>

        <button type="submit">
            Guardar cambios
        </button>

        <a href="{{ route('academic-curriculum-items.show', $item) }}">
            Cancelar
        </a>

    </form>

</div>

</body>
</html>