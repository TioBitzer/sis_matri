
import { useEffect, useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import api from '../services/api'

function Enrollments() {
  const [matriculas, setMatriculas] = useState([])
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState('')
  const [mensaje, setMensaje] = useState('')
  const [eliminando, setEliminando] = useState(null)

  const location = useLocation()
  const navigate = useNavigate()

  useEffect(() => {
    let activo = true

    const cargarMatriculas = async () => {
      try {
        const response = await api.get('/enrollments')

        if (activo) {
          setMatriculas(
            Array.isArray(response.data?.data)
              ? response.data.data
              : []
          )
          setError('')
        }
      } catch (err) {
        console.error('Error al cargar matrículas:', err)

        if (activo) {
          setError(
            err.response?.data?.message ||
              'No se pudieron cargar las matrículas. Intenta nuevamente.'
          )
        }
      } finally {
        if (activo) {
          setCargando(false)
        }
      }
    }

    cargarMatriculas()

    return () => {
      activo = false
    }
  }, [])

  useEffect(() => {
    if (location.state?.mensaje) {
      setMensaje(location.state.mensaje)

      navigate(location.pathname, {
        replace: true,
        state: null,
      })
    }
  }, [location, navigate])

  const handleDelete = async (id) => {
    const confirmar = window.confirm(
      '¿Estás seguro de que deseas eliminar esta matrícula?'
    )

    if (!confirmar) {
      return
    }

    setEliminando(id)
    setError('')
    setMensaje('')

    try {
      await api.delete(`/enrollments/${id}`)

      setMatriculas((actuales) =>
        actuales.filter(
          (matricula) => matricula.id !== id
        )
      )

      setMensaje('Matrícula eliminada correctamente.')
    } catch (err) {
      console.error('Error al eliminar matrícula:', err)

      setError(
        err.response?.data?.message ||
          'No se pudo eliminar la matrícula. Intenta nuevamente.'
      )
    } finally {
      setEliminando(null)
    }
  }

  if (cargando) {
    return <p>Cargando matrículas...</p>
  }

  return (
    <div>
      <h1>Matrículas</h1>

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

      <Link to="/enrollments/create">
        <button type="button">
          Nueva matrícula
        </button>
      </Link>

      {matriculas.length === 0 ? (
        <p>No hay matrículas registradas.</p>
      ) : (
        <table className="student-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Estudiante</th>
              <th>Período académico</th>
              <th>Estado</th>
              <th>Fecha de matrícula</th>
              <th>Acciones</th>
            </tr>
          </thead>

          <tbody>
            {matriculas.map((matricula) => {
              const persona =
                matricula.student?.person ??
                matricula.student?.Person

              const periodo =
                matricula.academic_period ??
                matricula.academicPeriod

              const nombreEstudiante = persona
                ? [
                    persona.first_name ?? persona.firstName,
                    persona.last_name ?? persona.lastName,
                  ]
                    .filter(Boolean)
                    .join(' ')
                : ''

              return (
                <tr key={matricula.id}>
                  <td>{matricula.id}</td>

                  <td>
                    {nombreEstudiante || 'Sin estudiante'}
                  </td>

                  <td>
                    {periodo?.name || 'Sin período'}
                  </td>

                  <td>
                    {matricula.status || 'Sin estado'}
                  </td>

                  <td>
                    {matricula.enrollment_date
                      ? String(matricula.enrollment_date).slice(0, 10)
                      : 'Sin fecha'}
                  </td>

                  <td>
                    <Link
                      to={`/enrollments/${matricula.id}/edit`}
                    >
                      <button
                        type="button"
                        disabled={eliminando === matricula.id}
                      >
                        Editar
                      </button>
                    </Link>

                    <button
                      type="button"
                      onClick={() => handleDelete(matricula.id)}
                      disabled={eliminando === matricula.id}
                    >
                      {eliminando === matricula.id
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

export default Enrollments
