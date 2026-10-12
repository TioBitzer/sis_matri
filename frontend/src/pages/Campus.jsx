
import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api from '../services/api'

function Campus() {
  const [campus, setCampus] = useState([])
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    api.get('/campuses')
      .then((response) => {
        setCampus(response.data.data)
        setCargando(false)
      })
      .catch((error) => {
        console.error(
          'Error al cargar campus:',
          error
        )

        setError(
          'No se pudieron cargar los campus'
        )

        setCargando(false)
      })
  }, [])

  const handleDelete = async (id) => {
    const confirmar = window.confirm(
      '¿Estás seguro de eliminar este campus?'
    )

    if (!confirmar) {
      return
    }

    try {
      const response = await api.delete(
        `/campuses/${id}`
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
        'No se pudo eliminar el campus.'
      )
    }
  }

  if (cargando) {
    return <p>Cargando campus...</p>
  }

  if (error) {
    return <p>{error}</p>
  }

  return (
    <div>
      <h1>Campus</h1>

      <Link to="/campuses/create">
        <button>Nuevo campus</button>
      </Link>

      <table className="student-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Institución</th>
            <th>Nombre</th>
            <th>Código</th>
            <th>Dirección</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>

        <tbody>
          {campus.map((item) => (
            <tr key={item.id}>
              <td>{item.id}</td>

              <td>
                {item.institution?.name}
              </td>

              <td>
                {item.name}
              </td>

              <td>
                {item.code}
              </td>

              <td>
                {item.address}
              </td>

              <td>
                {item.is_active
                  ? 'Activo'
                  : 'Inactivo'}
              </td>

              <td>
                <Link
                  to={`/campuses/${item.id}/edit`}
                >
                  <button>Editar</button>
                </Link>

                <button
                  onClick={() =>
                    handleDelete(item.id)
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

export default Campus
