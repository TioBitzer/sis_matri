
import { useEffect, useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import api from '../services/api'

function AcademicRecords() {
  const [registros, setRegistros] = useState([])
  const [cargando, setCargando] = useState(true)
  const [eliminando, setEliminando] = useState(null)
  const [error, setError] = useState('')
  const [mensaje, setMensaje] = useState('')

  const location = useLocation()
  const navigate = useNavigate()

  useEffect(() => {
    const mensajeNavegacion = location.state?.mensaje

    if (mensajeNavegacion) {
      setMensaje(mensajeNavegacion)

      navigate(location.pathname, {
        replace: true,
        state: null,
      })
    }
  }, [location, navigate])

  useEffect(() => {
    const cargarRegistros = async () => {
      try {
        const response = await api.get('/academic-records')
        setRegistros(response.data.data ?? [])
      } catch (error) {
        console.error(
          'Error al cargar registros académicos:',
          error.response?.data || error
        )

        setError(
          error.response?.data?.message ||
            'No se pudieron cargar los registros académicos. Inténtalo nuevamente.'
        )
      } finally {
        setCargando(false)
      }
    }

    cargarRegistros()
  }, [])

  const handleDelete = async (id) => {
    const confirmar = window.confirm(
      '¿Estás seguro de eliminar este registro académico?'
    )

    if (!confirmar) {
      return
    }

    setError('')
    setMensaje('')
    setEliminando(id)

    try {
      await api.delete(`/academic-records/${id}`)

      setRegistros((previos) =>
        previos.filter((registro) => registro.id !== id)
      )

      setMensaje('Registro académico eliminado correctamente.')
    } catch (error) {
      console.error(
        'Error al eliminar el registro académico:',
        error.response?.data || error
      )

      setError(
        error.response?.data?.message ||
          'No se pudo eliminar el registro académico. Inténtalo nuevamente.'
      )
    } finally {
      setEliminando(null)
    }
  }

  if (cargando) {
    return <p>Cargando registros académicos...</p>
  }

  return (
    <div>
      <h1>Registros Académicos</h1>

      {mensaje && (
        <p role="status">
          {mensaje}
        </p>
      )}

      {error && (
        <p role="alert">
          {error}
        </p>
      )}

      <Link to="/academic-records/create">
        Nuevo registro
      </Link>

      {registros.length === 0 ? (
        <p>No hay registros académicos para mostrar.</p>
      ) : (
        <table className="student-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Estudiante</th>
              <th>Programa académico</th>
              <th>Plan académico</th>
              <th>Fecha inicio</th>
              <th>Fecha fin</th>
              <th>Estado</th>
              <th>Acciones</th>
            </tr>
          </thead>

          <tbody>
            {registros.map((registro) => {
              const estudiante =
                registro.student?.person ??
                registro.student?.person

              return (
                <tr key={registro.id}>
                  <td>{registro.id}</td>

                  <td>
                    {estudiante
                      ? `${estudiante.first_name} ${estudiante.last_name}`
                      : 'Sin estudiante'}
                  </td>

                  <td>
                    {registro.academic_program?.name ||
                      registro.academicProgram?.name ||
                      'Sin programa'}
                  </td>

                  <td>
                    {registro.academic_plan?.name ||
                      registro.academicPlan?.name ||
                      'Sin plan'}
                  </td>

                  <td>{registro.start_date || 'Sin fecha'}</td>

                  <td>{registro.end_date || 'Sin fecha'}</td>

                  <td>{registro.status || 'Sin estado'}</td>

                  <td>
                    <Link
                      to={`/academic-records/${registro.id}/edit`}
                    >
                      Editar
                    </Link>

                    {' '}

                    <button
                      type="button"
                      onClick={() => handleDelete(registro.id)}
                      disabled={eliminando !== null}
                    >
                      {eliminando === registro.id
                        ? 'Eliminando...'
                        : 'Eliminar'}
                    </button>
                  </td>
                </tr>
              )
            })}
          </tbody>
        </table>
      )}
    </div>
  )
}

export default AcademicRecords
