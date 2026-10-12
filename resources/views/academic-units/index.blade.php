<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unidades Académicas</title>
</head>

<body>

<div class="container">

    <h1>Unidades Académicas</h1>

    @if (session('success'))
        <div>
            {{ session('success') }}
        </div>
    @endif

    <br>

    <a href="{{ route('academic-units.create') }}">
        Crear Unidad Académica
    </a>

    <br><br>

    @if ($units->count())

        <table border="1" cellpadding="8">

            <thead>
                <tr>
                    <th>ID</th>
                    <th>Institución</th>
                    <th>Nombre</th>
                    <th>Código</th>
                    <th>Tipo</th>
                    <th>Créditos</th>
                    <th>Horas</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>

            <tbody>

                @foreach ($units as $unit)

                    <tr>
                        <td>{{ $unit->id }}</td>

                        <td>
                            {{ $unit->institution->name }}
                        </td>

                        <td>
                            {{ $unit->name }}
                        </td>

                        <td>
                            {{ $unit->code }}
                        </td>

                        <td>
                            {{ $unit->unit_type }}
                        </td>

                        <td>
                            {{ $unit->credits }}
                        </td>

                        <td>
                            {{ $unit->hours }}
                        </td>

                        <td>
                            {{ $unit->is_active ? 'Activo' : 'Inactivo' }}
                        </td>

                        <td>

                            <a href="{{ route('academic-units.show', $unit) }}">
                                Ver
                            </a>

                            |

                            <a href="{{ route('academic-units.edit', $unit) }}">
                                Editar
                            </a>

                            |

                            <form
                                action="{{ route('academic-units.destroy', $unit) }}"
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
            No hay unidades académicas registradas.
        </p>

    @endif

</div>

</body>
</html>