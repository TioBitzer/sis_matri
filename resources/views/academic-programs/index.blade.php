<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Programas Académicos</title>

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

    <h1>Programas Académicos</h1>

    @if (session('success'))
        <div class="success">
            {{ session('success') }}
        </div>
    @endif

    <a href="{{ route('academic-programs.create') }}" class="button">
        Nuevo programa
    </a>

    <table>

        <thead>
            <tr>
                <th>ID</th>
                <th>Institución</th>
                <th>Nombre</th>
                <th>Código</th>
                <th>Descripción</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>

        <tbody>

            @forelse ($programs as $program)

                <tr>

                    <td>
                        {{ $program->id }}
                    </td>

                    <td>
                        {{ $program->institution->name }}
                    </td>

                    <td>
                        {{ $program->name }}
                    </td>

                    <td>
                        {{ $program->code }}
                    </td>

                    <td>
                        {{ $program->description ?? 'Sin descripción' }}
                    </td>

                    <td>
                        {{ $program->is_active ? 'Activo' : 'Inactivo' }}
                    </td>

                    <td class="actions">

                        <a href="{{ route('academic-programs.show', $program) }}">
                            Ver
                        </a>

                        <a href="{{ route('academic-programs.edit', $program) }}">
                            Editar
                        </a>

                        <form
                            action="{{ route('academic-programs.destroy', $program) }}"
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
                        No hay programas académicos registrados.
                    </td>
                </tr>

            @endforelse

        </tbody>

    </table>

</div>

</body>
</html>