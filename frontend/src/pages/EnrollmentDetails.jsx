
import { useEffect, useState } from 'react'
import {
  Link,
  useLocation,
  useNavigate,
} from 'react-router-dom'
import api from '../services/api'

function EnrollmentDetails() {
  const [detalles, setDetalles] = useState([])
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState('')
  const [mensaje, setMensaje] = useState('')
  const [eliminando, setEliminando] = useState(null)

  const location = useLocation()
  const navigate = useNavigate()

  useEffect(() => {
    let activo = true

    const cargarDetalles = async () => {
      try {
        const response = await api.get('/enrollment-details')

        if (activo) {
          setDetalles(
            Array.isArray(response.data?.data)
              ? response.data.data
              : []
          )
          setError('')
        }
      } catch (err) {
        console.error(
          'Error al cargar detalles de matrícula:',
          err
        )

        if (activo) {
          setError(
            err.response?.data?.message ||
              'No se pudieron cargar los detalles de matrícula. Intenta nuevamente.'
          )
        }
      } finally {
        if (activo) {
          setCargando(false)
        }
      }
    }

    cargarDetalles()

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
      '¿Estás seguro de que deseas eliminar este detalle de matrícula?'
    )

    if (!confirmar) {
      return
    }

    setEliminando(id)
    setError('')
    setMensaje('')

    try {
      await api.delete(`/enrollment-details/${id}`)

      setDetalles((actuales) =>
        actuales.filter((detalle) => detalle.id !== id)
      )

      setMensaje(
        'Detalle de matrícula eliminado correctamente.'
      )
    } catch (err) {
      console.error(
        'Error al eliminar detalle de matrícula:',
        err
      )

      setError(
        err.response?.data?.message ||
          'No se pudo eliminar el detalle de matrícula. Intenta nuevamente.'
      )
    } finally {
      setEliminando(null)
    }
  }

  if (cargando) {
    return <p>Cargando detalles de matrícula...</p>
  }

  return (
    <div>
      <h1>Detalles de matrícula</h1>

      {mensaje && (
        <p role="status">{mensaje}</p>
      )}

      {error && (
        <p role="alert">{error}</p>
      )}

      <Link to="/enrollment-details/create">
        <button type="button">
          Nuevo detalle
        </button>
      </Link>

      {detalles.length === 0 ? (
        <p>No hay detalles de matrícula registrados.</p>
      ) : (
        <table className="student-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Estudiante</th>
              <th>Período</th>
              <th>Curso</th>
              <th>Sección</th>
              <th>Campus</th>
              <th>Estado</th>
              <th>Acciones</th>
            </tr>
          </thead>

          <tbody>
            {detalles.map((detalle) => {
              const matricula =
                detalle.enrollment ??
                detalle.Enrollment

              const persona =
                matricula?.student?.person ??
                matricula?.student?.Person

              const periodo =
                matricula?.academic_period ??
                matricula?.academicPeriod

              const oferta =
                detalle.academic_offering ??
                detalle.academicOffering

              const unidad =
                oferta?.academic_unit ??
                oferta?.academicUnit

              const periodoOferta =
                oferta?.academic_period ??
                oferta?.academicPeriod

              const campus = oferta?.campus

              const nombreEstudiante = persona
                ? [
                    persona.first_name ?? persona.firstName,
                    persona.last_name ?? persona.lastName,
                  ]
                    .filter(Boolean)
                    .join(' ')
                : ''

              return (
                <tr key={detalle.id}>
                  <td>{detalle.id}</td>

                  <td>
                    {nombreEstudiante || 'Sin estudiante'}
                  </td>

                  <td>
                    {periodo?.name ||
                      periodoOferta?.name ||
                      'Sin período'}
                  </td>

                  <td>
                    {unidad?.name || 'Sin curso'}
                  </td>

                  <td>
                    {oferta?.section || 'Sin sección'}
                  </td>

                  <td>
                    {campus?.name || 'Sin campus'}
                  </td>

                  <td>
                    {detalle.status || 'Sin estado'}
                  </td>

                  <td>
                    <Link
                      to={`/enrollment-details/${detalle.id}/edit`}
                    >
                      <button
                        type="button"
                        disabled={eliminando === detalle.id}
                      >
                        Editar
                      </button>
                    </Link>

                    <button
                      type="button"
                      onClick={() => handleDelete(detalle.id)}
                      disabled={eliminando === detalle.id}
                    >
                      {eliminando === detalle.id
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

export default EnrollmentDetails
