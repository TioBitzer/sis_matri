<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ofertas Académicas</title>
</head>

<body>

<div class="container">

    <h1>Ofertas Académicas</h1>

    @if (session('success'))
        <div>
            {{ session('success') }}
        </div>
    @endif

    <br>

    <a href="{{ route('academic-offerings.create') }}">
        Nueva Oferta Académica
    </a>

    <br>
    <br>

    @if ($offerings->isEmpty())

        <p>No hay ofertas académicas registradas.</p>

    @else

        <table border="1" cellpadding="8">

            <thead>
                <tr>
                    <th>Unidad Académica</th>
                    <th>Periodo</th>
                    <th>Campus</th>
                    <th>Sección</th>
                    <th>Capacidad</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>

            <tbody>

                @foreach ($offerings as $offering)

                    <tr>

                        <td>
                            {{ $offering->academicUnit->name }}
                        </td>

                        <td>
                            {{ $offering->academicPeriod->name }}
                        </td>

                        <td>
                            {{ $offering->campus->name }}
                        </td>

                        <td>
                            {{ $offering->section }}
                        </td>

                        <td>
                            {{ $offering->capacity }}
                        </td>

                        <td>
                            @if ($offering->is_active)
                                Activo
                            @else
                                Inactivo
                            @endif
                        </td>

                        <td>

                            <a href="{{ route('academic-offerings.show', $offering->id) }}">
                                Ver
                            </a>

                            |

                            <a href="{{ route('academic-offerings.edit', $offering->id) }}">
                                Editar
                            </a>

                            |

                            <form
                                action="{{ route('academic-offerings.destroy', $offering->id) }}"
                                method="POST"
                                style="display:inline;"
                                onsubmit="return confirm('¿Seguro que deseas eliminar esta oferta?');"
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