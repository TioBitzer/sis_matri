<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Editar detalle de matrícula</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
</head>

<body>

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Editar detalle de matrícula</h1>

        <a
            href="{{ route('enrollment-details.index') }}"
            class="btn btn-secondary"
        >
            Volver
        </a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <strong>Corrige los siguientes errores:</strong>

            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form
        action="{{ route('enrollment-details.update', $detail->id) }}"
        method="POST"
    >
        @csrf
        @method('PUT')

        <div class="card">

            <div class="card-header">
                <strong>Datos del detalle</strong>
            </div>

            <div class="card-body">

                {{-- Matrícula --}}
                <div class="mb-3">
                    <label for="enrollment_id" class="form-label">
                        Matrícula
                    </label>

                    <select
                        name="enrollment_id"
                        id="enrollment_id"
                        class="form-select"
                        required
                    >
                        <option value="">
                            Seleccione una matrícula
                        </option>

                        @foreach($enrollments as $enrollment)
                            <option
                                value="{{ $enrollment->id }}"
                                {{ old('enrollment_id', $detail->enrollment_id) == $enrollment->id ? 'selected' : '' }}
                            >
                                Matrícula #{{ $enrollment->id }}
                                -
                                {{ $enrollment->student->person->first_name }}
                                {{ $enrollment->student->person->last_name }}
                                -
                                {{ $enrollment->academicPeriod->name }}
                            </option>
                        @endforeach

                    </select>
                </div>

                {{-- Oferta académica --}}
                <div class="mb-3">

                    <label for="academic_offering_id" class="form-label">
                        Curso / Oferta académica
                    </label>

                    <select
                        name="academic_offering_id"
                        id="academic_offering_id"
                        class="form-select"
                        required
                    >

                        <option value="">
                            Seleccione un curso
                        </option>

                        @foreach($offerings as $offering)

                            <option
                                value="{{ $offering->id }}"
                                {{ old('academic_offering_id', $detail->academic_offering_id) == $offering->id ? 'selected' : '' }}
                            >
                                {{ $offering->academicUnit->name }}
                                -
                                Sección {{ $offering->section }}
                                -
                                {{ $offering->academicPeriod->name }}
                                -
                                {{ $offering->campus->name }}
                            </option>

                        @endforeach

                    </select>

                </div>

                {{-- Estado --}}
                <div class="mb-3">

                    <label for="status" class="form-label">
                        Estado
                    </label>

                    <select
                        name="status"
                        id="status"
                        class="form-select"
                        required
                    >

                        <option
                            value="active"
                            {{ old('status', $detail->status) == 'active' ? 'selected' : '' }}
                        >
                            Activo
                        </option>

                        <option
                            value="enrolled"
                            {{ old('status', $detail->status) == 'enrolled' ? 'selected' : '' }}
                        >
                            Matriculado
                        </option>

                        <option
                            value="completed"
                            {{ old('status', $detail->status) == 'completed' ? 'selected' : '' }}
                        >
                            Completado
                        </option>

                        <option
                            value="cancelled"
                            {{ old('status', $detail->status) == 'cancelled' ? 'selected' : '' }}
                        >
                            Cancelado
                        </option>

                    </select>

                </div>

            </div>

            <div class="card-footer text-end">

                <a
                    href="{{ route('enrollment-details.index') }}"
                    class="btn btn-secondary"
                >
                    Cancelar
                </a>

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Actualizar detalle
                </button>

            </div>

        </div>

    </form>

</div>

</body>
</html>