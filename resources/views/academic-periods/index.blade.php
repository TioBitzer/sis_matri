<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Periodos Académicos</title>
</head>

<body>

<div class="container">

    <h1>Periodos Académicos</h1>

    @if (session('success'))
        <div>
            {{ session('success') }}
        </div>
    @endif

    <br>

    <a href="{{ route('academic-periods.create') }}">
        Crear Periodo Académico
    </a>

    <br><br>

    @if ($periods->count())

        <table border="1" cellpadding="8">

            <thead>
                <tr>
                    <th>ID</th>
                    <th>Institución</th>
                    <th>Nombre</th>
                    <th>Código</th>
                    <th>Inicio</th>
                    <th>Fin</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>

            <tbody>

                @foreach ($periods as $period)

                    <tr>

                        <td>
                            {{ $period->id }}
                        </td>

                        <td>
                            {{ $period->institution->name }}
                        </td>

                        <td>
                            {{ $period->name }}
                        </td>

                        <td>
                            {{ $period->code }}
                        </td>

                        <td>
                            {{ $period->start_date->format('d/m/Y') }}
                        </td>

                        <td>
                            {{ $period->end_date->format('d/m/Y') }}
                        </td>

                        <td>
                            {{ $period->is_active ? 'Activo' : 'Inactivo' }}
                        </td>

                        <td>

                            <a href="{{ route('academic-periods.show', $period) }}">
                                Ver
                            </a>

                            |

                            <a href="{{ route('academic-periods.edit', $period) }}">
                                Editar
                            </a>

                            |

                            <form
                                action="{{ route('academic-periods.destroy', $period) }}"
                                method="POST"
                                style="display:inline;"
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

    @else

        <p>
            No hay periodos académicos registrados.
        </p>

    @endif

</div>

</body>
</html>