
import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api from '../services/api'

function AcademicPeriods() {
  const [periodos, setPeriodos] = useState([])
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    api.get('/academic-periods')
      .then((response) => {
        setPeriodos(response.data.data)
        setCargando(false)
      })
      .catch((error) => {
        console.error(
          'Error al cargar períodos académicos:',
          error
        )

        setError(
          'No se pudieron cargar los períodos académicos'
        )

        setCargando(false)
      })
  }, [])

  const handleDelete = async (id) => {
    const confirmar = window.confirm(
      '¿Estás seguro de eliminar este período académico?'
    )

    if (!confirmar) {
      return
    }

    try {
      const response = await api.delete(
        `/academic-periods/${id}`
      )

      console.log('Eliminación exitosa:', response.data)

      window.location.reload()
    } catch (error) {
      console.error('Error completo:', error)
      console.error(
        'Respuesta del servidor:',
        error.response?.data
      )

      alert(
        'No se pudo eliminar el período académico.'
      )
    }
  }

  if (cargando) {
    return <p>Cargando períodos académicos...</p>
  }

  if (error) {
    return <p>{error}</p>
  }

  return (
    <div>
      <h1>Períodos Académicos</h1>

      <Link to="/academic-periods/create">
        <button>Nuevo período</button>
      </Link>

      <table className="student-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Fecha inicio</th>
            <th>Fecha fin</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>

        <tbody>
          {periodos.map((periodo) => (
            <tr key={periodo.id}>
              <td>{periodo.id}</td>

              <td>
                {periodo.name}
              </td>

              <td>
                {periodo.start_date}
              </td>

              <td>
                {periodo.end_date}
              </td>

              <td>
                {periodo.is_active ? 'Activo' : 'Inactivo'}
              </td>

              <td>
                <Link
                  to={`/academic-periods/${periodo.id}/edit`}
                >
                  <button>Editar</button>
                </Link>

                <button
                  onClick={() => handleDelete(periodo.id)}
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

export default AcademicPeriods
