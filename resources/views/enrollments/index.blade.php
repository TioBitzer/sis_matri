<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Matrículas</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
</head>

<body>

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Matrículas</h1>

        <a href="{{ route('enrollments.create') }}" class="btn btn-primary">
            Nueva matrícula
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if($enrollments->isEmpty())
        <div class="alert alert-info">
            No existen matrículas registradas.
        </div>
    @else

        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Estudiante</th>
                        <th>Periodo</th>
                        <th>Programa</th>
                        <th>Plan</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th width="180">Acciones</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($enrollments as $enrollment)
                        <tr>
                            <td>{{ $enrollment->id }}</td>

                            <td>
                                {{ $enrollment->student->person->first_name }}
                                {{ $enrollment->student->person->last_name }}
                            </td>

                            <td>
                                {{ $enrollment->academicPeriod->name }}
                            </td>

                            <td>
                                {{ $enrollment->academicRecord->academicProgram->name }}
                            </td>

                            <td>
                                {{ $enrollment->academicRecord->academicPlan->name }}
                            </td>

                            <td>
                                {{ $enrollment->enrollment_date->format('d/m/Y') }}
                            </td>

                            <td>
                                {{ $enrollment->status }}
                            </td>

                            <td>
                                <a
                                    href="{{ route('enrollments.show', $enrollment->id) }}"
                                    class="btn btn-sm btn-info"
                                >
                                    Ver
                                </a>

                                <a
                                    href="{{ route('enrollments.edit', $enrollment->id) }}"
                                    class="btn btn-sm btn-warning"
                                >
                                    Editar
                                </a>

                                <form
                                    action="{{ route('enrollments.destroy', $enrollment->id) }}"
                                    method="POST"
                                    class="d-inline"
                                >
                                    @csrf
                                    @method('DELETE')

                                    <button
                                        type="submit"
                                        class="btn btn-sm btn-danger"
                                        onclick="return confirm('¿Eliminar esta matrícula?')"
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