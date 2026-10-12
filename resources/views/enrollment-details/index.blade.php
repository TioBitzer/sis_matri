<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Detalles de Matrícula</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
</head>

<body>

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Detalles de matrícula</h1>

        <a
            href="{{ route('enrollment-details.create') }}"
            class="btn btn-primary"
        >
            Agregar curso a matrícula
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if($details->isEmpty())

        <div class="alert alert-info">
            No existen cursos registrados en las matrículas.
        </div>

    @else

        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle">

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Estudiante</th>
                        <th>Periodo</th>
                        <th>Curso</th>
                        <th>Sección</th>
                        <th>Campus</th>
                        <th>Estado</th>
                        <th width="180">Acciones</th>
                    </tr>
                </thead>

                <tbody>

                    @foreach($details as $detail)

                        <tr>

                            <td>
                                {{ $detail->id }}
                            </td>

                            <td>
                                {{ $detail->enrollment->student->person->first_name }}
                                {{ $detail->enrollment->student->person->last_name }}
                            </td>

                            <td>
                                {{ $detail->enrollment->academicPeriod->name }}
                            </td>

                            <td>
                                {{ $detail->academicOffering->academicUnit->name }}
                            </td>

                            <td>
                                {{ $detail->academicOffering->section }}
                            </td>

                            <td>
                                {{ $detail->academicOffering->campus->name }}
                            </td>

                            <td>
                                {{ $detail->status }}
                            </td>

                            <td>

                                <a
                                    href="{{ route('enrollment-details.show', $detail->id) }}"
                                    class="btn btn-sm btn-info"
                                >
                                    Ver
                                </a>

                                <a
                                    href="{{ route('enrollment-details.edit', $detail->id) }}"
                                    class="btn btn-sm btn-warning"
                                >
                                    Editar
                                </a>

                                <form
                                    action="{{ route('enrollment-details.destroy', $detail->id) }}"
                                    method="POST"
                                    class="d-inline"
                                >
                                    @csrf
                                    @method('DELETE')

                                    <button
                                        type="submit"
                                        class="btn btn-sm btn-danger"
                                        onclick="return confirm('¿Eliminar este curso de la matrícula?')"
                                    >
                                        Eliminar
                                    </button>

                                </form>

                            </td>

                        </tr>

                    @endforeach

                </tbody>

            </table>
        </div>

    @endif

</div>

</body>
</html>