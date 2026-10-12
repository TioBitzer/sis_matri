<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $campus->name }}</title>
</head>

<body>

<div class="container">

    <h1>{{ $campus->name }}</h1>

    <p>
        <strong>ID:</strong>
        {{ $campus->id }}
    </p>

    <p>
        <strong>Institución:</strong>
        {{ $campus->institution->name }}
    </p>

    <p>
        <strong>Nombre:</strong>
        {{ $campus->name }}
    </p>

    <p>
        <strong>Código:</strong>
        {{ $campus->code }}
    </p>

    <p>
        <strong>Dirección:</strong>
        {{ $campus->address ?? 'Sin dirección' }}
    </p>

    <p>
        <strong>Estado:</strong>

        @if ($campus->is_active)
            Activo
        @else
            Inactivo
        @endif
    </p>

    <p>
        <strong>Creada:</strong>
        {{ $campus->created_at->format('d/m/Y H:i') }}
    </p>

    <p>
        <strong>Última actualización:</strong>
        {{ $campus->updated_at->format('d/m/Y H:i') }}
    </p>

    <br>

    <a href="{{ route('campuses.edit', $campus) }}">
        Editar
    </a>

    <a href="{{ route('campuses.index') }}">
        Volver
    </a>

</div>

</body>
</html>