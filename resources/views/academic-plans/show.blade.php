<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $plan->name }}</title>
</head>

<body>

<div class="container">

    <h1>{{ $plan->name }}</h1>

    <p>
        <strong>ID:</strong>
        {{ $plan->id }}
    </p>

    <p>
        <strong>Programa Académico:</strong>
        {{ $plan->academicProgram->name }}
    </p>

    <p>
        <strong>Nombre:</strong>
        {{ $plan->name }}
    </p>

    <p>
        <strong>Versión:</strong>
        {{ $plan->version }}
    </p>

    <p>
        <strong>Fecha de inicio:</strong>
        {{ $plan->start_date->format('d/m/Y') }}
    </p>

    <p>
        <strong>Fecha de fin:</strong>

        @if ($plan->end_date)
            {{ $plan->end_date->format('d/m/Y') }}
        @else
            Vigente
        @endif
    </p>

    <p>
        <strong>Estado:</strong>

        @if ($plan->is_active)
            Activo
        @else
            Inactivo
        @endif
    </p>

    <p>
        <strong>Creado:</strong>
        {{ $plan->created_at->format('d/m/Y H:i') }}
    </p>

    <p>
        <strong>Última actualización:</strong>
        {{ $plan->updated_at->format('d/m/Y H:i') }}
    </p>

    <br>

    <a href="{{ route('academic-plans.edit', $plan) }}">
        Editar
    </a>

    <a href="{{ route('academic-plans.index') }}">
        Volver
    </a>

</div>

</body>
</html>