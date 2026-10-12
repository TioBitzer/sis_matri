<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Asignación Docente</title>
</head>

<body>

<div class="container">

    <h1>Asignación Docente</h1>

    <p>
        <strong>Docente:</strong>
        {{ $assignment->teacher->person->first_name }}
        {{ $assignment->teacher->person->last_name }}
    </p>

    <p>
        <strong>Código del docente:</strong>
        {{ $assignment->teacher->employee_code ?? 'Sin código' }}
    </p>

    <p>
        <strong>Unidad Académica:</strong>
        {{ $assignment->academicOffering->academicUnit->name }}
    </p>

    <p>
        <strong>Periodo:</strong>
        {{ $assignment->academicOffering->academicPeriod->name }}
    </p>

    <p>
        <strong>Campus:</strong>
        {{ $assignment->academicOffering->campus->name }}
    </p>

    <p>
        <strong>Sección:</strong>
        {{ $assignment->academicOffering->section }}
    </p>

    <p>
        <strong>Rol:</strong>
        {{ $assignment->role }}
    </p>

    <br>

    <a href="{{ route('teaching-assignments.edit', $assignment->id) }}">
        Editar
    </a>

    <a href="{{ route('teaching-assignments.index') }}">
        Volver
    </a>

</div>

</body>
</html>