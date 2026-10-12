<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planes Académicos</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
            background: #f5f5f5;
        }

        .container {
            max-width: 1200px;
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

    <h1>Planes Académicos</h1>

    @if (session('success'))
        <div class="success">
            {{ session('success') }}
        </div>
    @endif

    <a href="{{ route('academic-plans.create') }}" class="button">
        Nuevo plan
    </a>

    <table>

        <thead>
            <tr>
                <th>ID</th>
                <th>Programa Académico</th>
                <th>Nombre</th>
                <th>Versión</th>
                <th>Inicio</th>
                <th>Fin</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>

        <tbody>

            @forelse ($plans as $plan)

                <tr>

                    <td>
                        {{ $plan->id }}
                    </td>

                    <td>
                        {{ $plan->academicProgram->name }}
                    </td>

                    <td>
                        {{ $plan->name }}
                    </td>

                    <td>
                        {{ $plan->version }}
                    </td>

                    <td>
                        {{ $plan->start_date->format('d/m/Y') }}
                    </td>

                    <td>
                        {{ $plan->end_date
                            ? $plan->end_date->format('d/m/Y')
                            : 'Vigente'
                        }}
                    </td>

                    <td>
                        {{ $plan->is_active ? 'Activo' : 'Inactivo' }}
                    </td>

                    <td class="actions">

                        <a href="{{ route('academic-plans.show', $plan) }}">
                            Ver
                        </a>

                        <a href="{{ route('academic-plans.edit', $plan) }}">
                            Editar
                        </a>

                        <form
                            action="{{ route('academic-plans.destroy', $plan) }}"
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
                    <td colspan="8">
                        No hay planes académicos registrados.
                    </td>
                </tr>

            @endforelse

        </tbody>

    </table>

</div>

</body>
</html>