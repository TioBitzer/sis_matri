<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Oferta Académica</title>
</head>

<body>

<div class="container">

    <h1>Oferta Académica</h1>

    <p>
        <strong>Unidad Académica:</strong>
        {{ $offering->academicUnit->name }}
    </p>

    <p>
        <strong>Código de unidad:</strong>
        {{ $offering->academicUnit->code }}
    </p>

    <p>
        <strong>Periodo Académico:</strong>
        {{ $offering->academicPeriod->name }}
    </p>

    <p>
        <strong>Campus:</strong>
        {{ $offering->campus->name }}
    </p>

    <p>
        <strong>Sección:</strong>
        {{ $offering->section }}
    </p>

    <p>
        <strong>Capacidad:</strong>
        {{ $offering->capacity }}
    </p>

    <p>
        <strong>Estado:</strong>

        @if ($offering->is_active)
            Activo
        @else
            Inactivo
        @endif
    </p>

    <br>

    <a href="{{ route('academic-offerings.edit', $offering->id) }}">
        Editar
    </a>

    <a href="{{ route('academic-offerings.index') }}">
        Volver
    </a>

</div>

</body>
</html>