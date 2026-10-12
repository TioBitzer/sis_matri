<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Detalle de Matrícula</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
</head>

<body>

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Detalle de matrícula</h1>

        <a
            href="{{ route('enrollment-details.index') }}"
            class="btn btn-secondary"
        >
            Volver
        </a>
    </div>

    <div class="card">

        <div class="card-header">
            <strong>Información del detalle</strong>
        </div>

        <div class="card-body">

            <div class="row mb-3">
                <div class="col-md-6">
                    <strong>ID:</strong>
                    {{ $detail->id }}
                </div>

                <div class="col-md-6">
                    <strong>Estado:</strong>
                    {{ $detail->status }}
                </div>
            </div>

            <hr>

            <h5>Estudiante</h5>

            <div class="row mb-3">
                <div class="col-md-6">
                    <strong>Nombre:</strong>
                    {{ $detail->enrollment->student->person->first_name }}
                    {{ $detail->enrollment->student->person->last_name }}
                </div>

                <div class="col-md-6">
                    <strong>Código:</strong>
                    {{ $detail->enrollment->student->student_code }}
                </div>
            </div>

            <hr>

            <h5>Matrícula</h5>

            <div class="row mb-3">
                <div class="col-md-6">
                    <strong>Matrícula:</strong>
                    #{{ $detail->enrollment->id }}
                </div>

                <div class="col-md-6">
                    <strong>Período:</strong>
                    {{ $detail->enrollment->academicPeriod->name }}
                </div>
            </div>

            <hr>

            <h5>Oferta académica</h5>

            <div class="row mb-3">
                <div class="col-md-6">
                    <strong>Curso:</strong>
                    {{ $detail->academicOffering->academicUnit->name }}
                </div>

                <div class="col-md-6">
                    <strong>Sección:</strong>
                    {{ $detail->academicOffering->section }}
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <strong>Período:</strong>
                    {{ $detail->academicOffering->academicPeriod->name }}
                </div>

                <div class="col-md-6">
                    <strong>Campus:</strong>
                    {{ $detail->academicOffering->campus->name }}
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <strong>Capacidad:</strong>
                    {{ $detail->academicOffering->capacity }}
                </div>

                <div class="col-md-6">
                    <strong>Oferta activa:</strong>

                    @if($detail->academicOffering->is_active)
                        <span class="badge bg-success">Sí</span>
                    @else
                        <span class="badge bg-secondary">No</span>
                    @endif
                </div>
            </div>

        </div>

        <div class="card-footer text-end">

            <a
                href="{{ route('enrollment-details.edit', $detail->id) }}"
                class="btn btn-warning"
            >
                Editar
            </a>

            <a
                href="{{ route('enrollment-details.index') }}"
                class="btn btn-secondary"
            >
                Volver
            </a>

        </div>

    </div>

</div>

</body>
</html>