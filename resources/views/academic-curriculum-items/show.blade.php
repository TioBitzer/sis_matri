<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle del Elemento Curricular</title>
</head>

<body>

<div class="container">

    <h1>Detalle del Elemento Curricular</h1>

    <p>
        <strong>ID:</strong>
        {{ $item->id }}
    </p>

    <p>
        <strong>Programa:</strong>
        {{ $item->academicPlan->academicProgram->name }}
    </p>

    <p>
        <strong>Plan Académico:</strong>
        {{ $item->academicPlan->name }}
    </p>

    <p>
        <strong>Nivel:</strong>
        {{ $item->academicLevel->name }}
    </p>

    <p>
        <strong>Unidad Académica:</strong>
        {{ $item->academicUnit->name }}
    </p>

    <p>
        <strong>Código de la Unidad:</strong>
        {{ $item->academicUnit->code }}
    </p>

    <p>
        <strong>Créditos:</strong>
        {{ $item->credits }}
    </p>

    <p>
        <strong>Horas:</strong>
        {{ $item->hours }}
    </p>

    <p>
        <strong>Obligatorio:</strong>
        {{ $item->is_required ? 'Sí' : 'No' }}
    </p>

    <p>
        <strong>Posición:</strong>
        {{ $item->position }}
    </p>

    <br>

    <a href="{{ route('academic-curriculum-items.edit', $item) }}">
        Editar
    </a>

    |

    <a href="{{ route('academic-curriculum-items.index') }}">
        Volver
    </a>

</div>

</body>
</html>