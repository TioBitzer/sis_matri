<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Unidad Académica</title>
</head>

<body>

<div class="container">

    <h1>Detalle de Unidad Académica</h1>

    <p>
        <strong>ID:</strong>
        {{ $unit->id }}
    </p>

    <p>
        <strong>Institución:</strong>
        {{ $unit->institution->name }}
    </p>

    <p>
        <strong>Nombre:</strong>
        {{ $unit->name }}
    </p>

    <p>
        <strong>Código:</strong>
        {{ $unit->code }}
    </p>

    <p>
        <strong>Tipo:</strong>
        {{ $unit->unit_type }}
    </p>

    <p>
        <strong>Descripción:</strong>
        {{ $unit->description ?: 'Sin descripción' }}
    </p>

    <p>
        <strong>Créditos:</strong>
        {{ $unit->credits }}
    </p>

    <p>
        <strong>Horas:</strong>
        {{ $unit->hours }}
    </p>

    <p>
        <strong>Estado:</strong>
        {{ $unit->is_active ? 'Activo' : 'Inactivo' }}
    </p>

    <br>

    <a href="{{ route('academic-units.edit', $unit) }}">
        Editar
    </a>

    |

    <a href="{{ route('academic-units.index') }}">
        Volver
    </a>

</div>

</body>
</html>