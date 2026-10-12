<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Periodo Académico</title>
</head>

<body>

<div class="container">

    <h1>Periodo Académico</h1>

    <p>
        <strong>Institución:</strong>
        {{ $period->institution->name }}
    </p>

    <p>
        <strong>Nombre:</strong>
        {{ $period->name }}
    </p>

    <p>
        <strong>Código:</strong>
        {{ $period->code }}
    </p>

    <p>
        <strong>Fecha de inicio:</strong>
        {{ $period->start_date->format('d/m/Y') }}
    </p>

    <p>
        <strong>Fecha de fin:</strong>
        {{ $period->end_date->format('d/m/Y') }}
    </p>

    <p>
        <strong>Estado:</strong>

        @if ($period->is_active)
            Activo
        @else
            Inactivo
        @endif
    </p>

    <br>

    <a href="{{ route('academic-periods.edit', $period->id) }}">
        Editar
    </a>

    <a href="{{ route('academic-periods.index') }}">
        Volver
    </a>

</div>

</body>
</html>