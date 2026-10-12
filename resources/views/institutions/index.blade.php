<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instituciones</title>

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

    <h1>Instituciones</h1>

    @if (session('success'))
        <div class="success">
            {{ session('success') }}
        </div>
    @endif

    <a href="{{ route('institutions.create') }}" class="button">
        Nueva institución
    </a>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Código</th>
                <th>Tipo</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>

        <tbody>

            @forelse ($institutions as $institution)

                <tr>
                    <td>{{ $institution->id }}</td>

                    <td>{{ $institution->name }}</td>

                    <td>{{ $institution->code }}</td>

                    <td>{{ $institution->type }}</td>

                    <td>
                        {{ $institution->is_active ? 'Activo' : 'Inactivo' }}
                    </td>

                    <td class="actions">

                        <a href="{{ route('institutions.show', $institution) }}">
                            Ver
                        </a>

                        <a href="{{ route('institutions.edit', $institution) }}">
                            Editar
                        </a>

                        <form
                            action="{{ route('institutions.destroy', $institution) }}"
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
                    <td colspan="6">
                        No hay instituciones registradas.
                    </td>
                </tr>

            @endforelse

        </tbody>
    </table>

</div>

</body>
</html>