<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $institution->name }}</title>
</head>

<body>

<div class="container">

    <h1>{{ $institution->name }}</h1>

    <p>
        <strong>ID:</strong>
        {{ $institution->id }}
    </p>

    <p>
        <strong>Nombre:</strong>
        {{ $institution->name }}
    </p>

    <p>
        <strong>Código:</strong>
        {{ $institution->code }}
    </p>

    <p>
        <strong>Tipo:</strong>
        {{ $institution->type }}
    </p>

    <p>
        <strong>Descripción:</strong>
        {{ $institution->description ?? 'Sin descripción' }}
    </p>

    <p>
        <strong>Estado:</strong>

        @if ($institution->is_active)
            Activo
        @else
            Inactivo
        @endif
    </p>

    <p>
        <strong>Creada:</strong>
        {{ $institution->created_at->format('d/m/Y H:i') }}
    </p>

    <p>
        <strong>Última actualización:</strong>
        {{ $institution->updated_at->format('d/m/Y H:i') }}
    </p>

    <br>

    <a href="{{ route('institutions.edit', $institution) }}">
        Editar
    </a>

    <a href="{{ route('institutions.index') }}">
        Volver
    </a>

</div>

</body>
</html>