
import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api from '../services/api'

function AcademicOfferings() {
  const [ofertas, setOfertas] = useState([])
  const [cargando, setCargando] = useState(true)
  const [eliminando, setEliminando] = useState(null)
  const [mensaje, setMensaje] = useState('')
  const [error, setError] = useState('')

  const cargarOfertas = async () => {
    try {
      setError('')

      const response = await api.get('/academic-offerings')

      setOfertas(response.data.data ?? [])
    } catch (error) {
      console.error(
        'Error al cargar ofertas académicas:',
        error
      )

      setError(
        error.response?.data?.message ||
        'No se pudieron cargar las ofertas académicas.'
      )
    } finally {
      setCargando(false)
    }
  }

  useEffect(() => {
    cargarOfertas()
  }, [])

  const handleDelete = async (id) => {
    const confirmar = window.confirm(
      '¿Estás seguro de eliminar esta oferta académica?'
    )

    if (!confirmar) {
      return
    }

    setMensaje('')
    setError('')
    setEliminando(id)

    try {
      const response = await api.delete(
        `/academic-offerings/${id}`
      )

      setOfertas((anteriores) =>
        anteriores.filter((oferta) => oferta.id !== id)
      )

      setMensaje(
        response.data.message ||
        'Oferta académica eliminada correctamente.'
      )
    } catch (error) {
      console.error(
        'Error al eliminar oferta académica:',
        error
      )

      setError(
        error.response?.data?.message ||
        'No se pudo eliminar la oferta académica.'
      )
    } finally {
      setEliminando(null)
    }
  }

  if (cargando) {
    return <p>Cargando ofertas académicas...</p>
  }

  return (
    <div>
      <h1>Ofertas Académicas</h1>

      {mensaje && (
        <p role="status">{mensaje}</p>
      )}

      {error && (
        <p role="alert">{error}</p>
      )}

      <Link to="/academic-offerings/create">
        Nueva oferta
      </Link>

      <table className="student-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Unidad académica</th>
            <th>Período académico</th>
            <th>Campus</th>
            <th>Sección</th>
            <th>Capacidad</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>

        <tbody>
          {ofertas.length === 0 ? (
            <tr>
              <td colSpan="8">
                No hay ofertas académicas registradas.
              </td>
            </tr>
          ) : (
            ofertas.map((oferta) => (
              <tr key={oferta.id}>
                <td>{oferta.id}</td>

                <td>
                  {oferta.academic_unit?.name ?? 'Sin unidad'}
                </td>

                <td>
                  {oferta.academic_period?.name ?? 'Sin período'}
                </td>

                <td>
                  {oferta.campus?.name ?? 'Sin campus'}
                </td>

                <td>{oferta.section}</td>

                <td>{oferta.capacity}</td>

                <td>
                  {oferta.is_active
                    ? 'Activo'
                    : 'Inactivo'}
                </td>

                <td>
                  <Link
                    to={`/academic-offerings/${oferta.id}/edit`}
                  >
                    Editar
                  </Link>

                  {' '}

                  <button
                    type="button"
                    onClick={() => handleDelete(oferta.id)}
                    disabled={eliminando !== null}
                  >
                    {eliminando === oferta.id
                      ? 'Eliminando...'
                      : 'Eliminar'}
                  </button>
                </td>
              </tr>
            ))
          )}
        </tbody>
      </table>
    </div>
  )
}

export default AcademicOfferings
