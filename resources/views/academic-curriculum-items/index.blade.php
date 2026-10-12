<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Currícula Académica</title>
</head>

<body>

<div class="container">

    <h1>Currícula Académica</h1>

    @if (session('success'))
        <div>
            {{ session('success') }}
        </div>
    @endif

    <br>

    <a href="{{ route('academic-curriculum-items.create') }}">
        Agregar Unidad al Currículo
    </a>

    <br><br>

    @if ($items->count())

        <table border="1" cellpadding="8">

            <thead>
                <tr>
                    <th>ID</th>
                    <th>Programa</th>
                    <th>Plan</th>
                    <th>Nivel</th>
                    <th>Unidad Académica</th>
                    <th>Créditos</th>
                    <th>Horas</th>
                    <th>Obligatorio</th>
                    <th>Posición</th>
                    <th>Acciones</th>
                </tr>
            </thead>

            <tbody>

                @foreach ($items as $item)

                    <tr>

                        <td>
                            {{ $item->id }}
                        </td>

                        <td>
                            {{ $item->academicPlan->academicProgram->name }}
                        </td>

                        <td>
                            {{ $item->academicPlan->name }}
                        </td>

                        <td>
                            {{ $item->academicLevel->name }}
                        </td>

                        <td>
                            {{ $item->academicUnit->name }}
                        </td>

                        <td>
                            {{ $item->credits }}
                        </td>

                        <td>
                            {{ $item->hours }}
                        </td>

                        <td>
                            {{ $item->is_required ? 'Sí' : 'No' }}
                        </td>

                        <td>
                            {{ $item->position }}
                        </td>

                        <td>

                            <a href="{{ route('academic-curriculum-items.show', $item) }}">
                                Ver
                            </a>

                            |

                            <a href="{{ route('academic-curriculum-items.edit', $item) }}">
                                Editar
                            </a>

                            |

                            <form
                                action="{{ route('academic-curriculum-items.destroy', $item) }}"
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
            No hay elementos curriculares registrados.
        </p>

    @endif

</div>

</body>
</html>