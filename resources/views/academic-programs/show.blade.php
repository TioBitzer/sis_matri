<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $program->name }}</title>
</head>

<body>

<div class="container">

    <h1>{{ $program->name }}</h1>

    <p>
        <strong>ID:</strong>
        {{ $program->id }}
    </p>

    <p>
        <strong>Institución:</strong>
        {{ $program->institution->name }}
    </p>

    <p>
        <strong>Nombre:</strong>
        {{ $program->name }}
    </p>

    <p>
        <strong>Código:</strong>
        {{ $program->code }}
    </p>

    <p>
        <strong>Descripción:</strong>
        {{ $program->description ?? 'Sin descripción' }}
    </p>

    <p>
        <strong>Estado:</strong>

        @if ($program->is_active)
            Activo
        @else
            Inactivo
        @endif
    </p>

    <p>
        <strong>Creado:</strong>
        {{ $program->created_at->format('d/m/Y H:i') }}
    </p>

    <p>
        <strong>Última actualización:</strong>
        {{ $program->updated_at->format('d/m/Y H:i') }}
    </p>

    <br>

    <a href="{{ route('academic-programs.edit', $program) }}">
        Editar
    </a>

    <a href="{{ route('academic-programs.index') }}">
        Volver
    </a>

</div>

</body>
</html>