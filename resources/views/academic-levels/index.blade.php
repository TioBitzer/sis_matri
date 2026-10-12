<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Niveles Académicos</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
            background: #f5f5f5;
        }

        .container {
            max-width: 1100px;
            margin: auto;
        }

        h1 {
            margin-bottom: 20px;
        }

        .success {
            background: #d1fae5;
            color: #065f46;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .button {
            display: inline-block;
            padding: 10px 15px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th,
        td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #eee;
        }

        .actions a,
        .actions button {
            margin-right: 5px;
        }
    </style>
</head>

<body>

<div class="container">

    <h1>Niveles Académicos</h1>

    @if (session('success'))
        <div class="success">
            {{ session('success') }}
        </div>
    @endif

    <a href="{{ route('academic-levels.create') }}" class="button">
        Nuevo nivel
    </a>

    <table>

        <thead>
            <tr>
                <th>ID</th>
                <th>Plan Académico</th>
                <th>Programa</th>
                <th>Nivel</th>
                <th>Orden</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>

        <tbody>

            @forelse ($levels as $level)

                <tr>

                    <td>
                        {{ $level->id }}
                    </td>

                    <td>
                        {{ $level->academicPlan->name }}
                    </td>

                    <td>
                        {{ $level->academicPlan->academicProgram->name }}
                    </td>

                    <td>
                        {{ $level->name }}
                    </td>

                    <td>
                        {{ $level->order }}
                    </td>

                    <td>
                        {{ $level->is_active ? 'Activo' : 'Inactivo' }}
                    </td>

                    <td class="actions">

                        <a href="{{ route('academic-levels.show', $level) }}">
                            Ver
                        </a>

                        <a href="{{ route('academic-levels.edit', $level) }}">
                            Editar
                        </a>

                        <form
                            action="{{ route('academic-levels.destroy', $level) }}"
                            method="POST"
                            style="display: inline;"
                        >

                            @csrf
                            @method('DELETE')

                            <button type="submit">
                                Eliminar
                            </button>

                        </form>

                    </td>

                </tr>

            @empty

                <tr>
                    <td colspan="7">
                        No hay niveles académicos registrados.
                    </td>
                </tr>

            @endforelse

        </tbody>

    </table>

</div>

</body>
</html>