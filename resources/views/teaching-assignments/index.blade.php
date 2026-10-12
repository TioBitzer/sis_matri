<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asignaciones Docentes</title>
</head>

<body>

<div class="container">

    <h1>Asignaciones Docentes</h1>

    @if (session('success'))
        <div>
            {{ session('success') }}
        </div>
    @endif

    <br>

    <a href="{{ route('teaching-assignments.create') }}">
        Nueva Asignación
    </a>

    <br>
    <br>

    @if ($assignments->isEmpty())

        <p>No hay asignaciones docentes registradas.</p>

    @else

        <table border="1" cellpadding="8">

            <thead>
                <tr>
                    <th>Docente</th>
                    <th>Unidad Académica</th>
                    <th>Periodo</th>
                    <th>Campus</th>
                    <th>Sección</th>
                    <th>Rol</th>
                    <th>Acciones</th>
                </tr>
            </thead>

            <tbody>

                @foreach ($assignments as $assignment)

                    <tr>

                        <td>
                            {{ $assignment->teacher->person->first_name }}
                            {{ $assignment->teacher->person->last_name }}
                        </td>

                        <td>
                            {{ $assignment->academicOffering->academicUnit->name }}
                        </td>

                        <td>
                            {{ $assignment->academicOffering->academicPeriod->name }}
                        </td>

                        <td>
                            {{ $assignment->academicOffering->campus->name }}
                        </td>

                        <td>
                            {{ $assignment->academicOffering->section }}
                        </td>

                        <td>
                            {{ $assignment->role }}
                        </td>

                        <td>

                            <a href="{{ route('teaching-assignments.show', $assignment->id) }}">
                                Ver
                            </a>

                            |

                            <a href="{{ route('teaching-assignments.edit', $assignment->id) }}">
                                Editar
                            </a>

                            |

                            <form
                                action="{{ route('teaching-assignments.destroy', $assignment->id) }}"
                                method="POST"
                                style="display:inline;"
                                onsubmit="return confirm('¿Seguro que deseas eliminar esta asignación?');"
                            >

                                @csrf
                                @method('DELETE')

                                <button type="submit">
                                    Eliminar
                                </button>

                            </form>

                        </td>

                    </tr>

                @endforeach

            </tbody>

        </table>

    @endif

</div>

</body>
</html>