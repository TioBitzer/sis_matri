<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $level->name }}</title>
</head>

<body>

<div class="container">

    <h1>{{ $level->name }}</h1>

    <p>
        <strong>ID:</strong>
        {{ $level->id }}
    </p>

    <p>
        <strong>Programa Académico:</strong>
        {{ $level->academicPlan->academicProgram->name }}
    </p>

    <p>
        <strong>Plan Académico:</strong>
        {{ $level->academicPlan->name }}
    </p>

    <p>
        <strong>Nombre:</strong>
        {{ $level->name }}
    </p>

    <p>
        <strong>Orden:</strong>
        {{ $level->order }}
    </p>

    <p>
        <strong>Estado:</strong>

        @if ($level->is_active)
            Activo
        @else
            Inactivo
        @endif
    </p>

    <p>
        <strong>Creado:</strong>
        {{ $level->created_at->format('d/m/Y H:i') }}
    </p>

    <p>
        <strong>Última actualización:</strong>
        {{ $level->updated_at->format('d/m/Y H:i') }}
    </p>

    <br>

    <a href="{{ route('academic-levels.edit', $level) }}">
        Editar
    </a>

    <a href="{{ route('academic-levels.index') }}">
        Volver
    </a>

</div>

</body>
</html>