
import { useEffect, useState } from 'react'
import { Link, useLocation } from 'react-router-dom'
import api from '../services/api'

function TeachingAssignments() {
  const location = useLocation()

  const [asignaciones, setAsignaciones] = useState([])
  const [cargando, setCargando] = useState(true)
  const [eliminando, setEliminando] = useState(null)
  const [mensaje, setMensaje] = useState(
    location.state?.mensaje || ''
  )
  const [error, setError] = useState('')

  useEffect(() => {
    const cargarAsignaciones = async () => {
      try {
        const response = await api.get(
          '/teaching-assignments'
        )

        setAsignaciones(response.data.data ?? [])
      } catch (error) {
        console.error(
          'Error al cargar asignaciones docentes:',
          error
        )

        setError(
          error.response?.data?.message ||
          'No se pudieron cargar las asignaciones docentes.'
        )
      } finally {
        setCargando(false)
      }
    }

    cargarAsignaciones()
  }, [])

  const handleDelete = async (id) => {
    const confirmar = window.confirm(
      '¿Estás seguro de eliminar esta asignación docente?'
    )

    if (!confirmar) {
      return
    }

    setMensaje('')
    setError('')
    setEliminando(id)

    try {
      const response = await api.delete(
        `/teaching-assignments/${id}`
      )

      setAsignaciones((anteriores) =>
        anteriores.filter(
          (asignacion) => asignacion.id !== id
        )
      )

      setMensaje(
        response.data.message ||
        'Asignación docente eliminada correctamente.'
      )
    } catch (error) {
      console.error(
        'Error al eliminar asignación docente:',
        error
      )

      setError(
        error.response?.data?.message ||
        'No se pudo eliminar la asignación docente.'
      )
    } finally {
      setEliminando(null)
    }
  }

  if (cargando) {
    return <p>Cargando asignaciones docentes...</p>
  }

  return (
    <div>
      <h1>Asignaciones Docentes</h1>

      {mensaje && (
        <p role="status">{mensaje}</p>
      )}

      {error && (
        <p role="alert">{error}</p>
      )}

      <Link to="/teaching-assignments/create">
        Nueva asignación
      </Link>

      <table className="student-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Docente</th>
            <th>Oferta académica</th>
            <th>Período</th>
            <th>Campus</th>
            <th>Rol</th>
            <th>Acciones</th>
          </tr>
        </thead>

        <tbody>
          {asignaciones.length === 0 ? (
            <tr>
              <td colSpan="7">
                No hay asignaciones docentes registradas.
              </td>
            </tr>
          ) : (
            asignaciones.map((asignacion) => {
              const docente = asignacion.teacher
              const persona = docente?.person
              const oferta = asignacion.academic_offering

              const unidad =
                oferta?.academic_unit ??
                oferta?.academicUnit

              const periodo =
                oferta?.academic_period ??
                oferta?.academicPeriod

              const nombreDocente = persona
                ? `${persona.first_name ?? ''} ${persona.last_name ?? ''}`.trim()
                : 'Sin docente'

              const nombreOferta = [
                unidad?.name,
                oferta?.section
                  ? `Sección ${oferta.section}`
                  : null,
              ]
                .filter(Boolean)
                .join(' - ')

              return (
                <tr key={asignacion.id}>
                  <td>{asignacion.id}</td>

                  <td>{nombreDocente}</td>

                  <td>
                    {nombreOferta || 'Sin oferta'}
                  </td>

                  <td>
                    {periodo?.name ?? 'Sin período'}
                  </td>

                  <td>
                    {oferta?.campus?.name ?? 'Sin campus'}
                  </td>

                  <td>
                    {asignacion.role || 'Sin rol'}
                  </td>

                  <td>
                    <Link
                      to={`/teaching-assignments/${asignacion.id}/edit`}
                    >
                      Editar
                    </Link>

                    {' '}

                    <button
                      type="button"
                      onClick={() =>
                        handleDelete(asignacion.id)
                      }
                      disabled={eliminando !== null}
                    >
                      {eliminando === asignacion.id
                        ? 'Eliminando...'
                        : 'Eliminar'}
                    </button>
                  </td>
                </tr>
              )
            })
          )}
        </tbody>
      </table>
    </div>
  )
}

export default TeachingAssignments
