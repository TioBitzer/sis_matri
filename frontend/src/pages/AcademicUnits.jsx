
import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api from '../services/api'

function AcademicUnits() {
  const [unidades, setUnidades] = useState([])
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    api.get('/academic-units')
      .then((response) => {
        setUnidades(response.data.data)
        setCargando(false)
      })
      .catch((error) => {
        console.error(
          'Error al cargar unidades académicas:',
          error
        )

        setError(
          'No se pudieron cargar las unidades académicas'
        )

        setCargando(false)
      })
  }, [])

  const handleDelete = async (id) => {
    const confirmar = window.confirm(
      '¿Estás seguro de eliminar esta unidad académica?'
    )

    if (!confirmar) {
      return
    }

    try {
      const response = await api.delete(
        `/academic-units/${id}`
      )

      console.log(
        'Eliminación exitosa:',
        response.data
      )

      window.location.reload()
    } catch (error) {
      console.error('Error completo:', error)

      console.error(
        'Respuesta del servidor:',
        error.response?.data
      )

      alert(
        'No se pudo eliminar la unidad académica.'
      )
    }
  }

  if (cargando) {
    return <p>Cargando unidades académicas...</p>
  }

  if (error) {
    return <p>{error}</p>
  }

  return (
    <div>
      <h1>Unidades Académicas</h1>

      <Link to="/academic-units/create">
        <button>Nueva unidad</button>
      </Link>

      <table className="student-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Institución</th>
            <th>Nombre</th>
            <th>Código</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>

        <tbody>
          {unidades.map((unidad) => (
            <tr key={unidad.id}>
              <td>{unidad.id}</td>

              <td>
                {unidad.institution?.name}
              </td>

              <td>
                {unidad.name}
              </td>

              <td>
                {unidad.code || '-'}
              </td>

              <td>
                {unidad.is_active
                  ? 'Activo'
                  : 'Inactivo'}
              </td>

              <td>
                <Link
                  to={`/academic-units/${unidad.id}/edit`}
                >
                  <button>Editar</button>
                </Link>

                <button
                  onClick={() =>
                    handleDelete(unidad.id)
                  }
                >
                  Eliminar
                </button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

export default AcademicUnits
