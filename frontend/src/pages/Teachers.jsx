
import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api from '../services/api'

function Teachers() {
  const [docentes, setDocentes] = useState([])
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    api.get('/teachers')
      .then((response) => {
        setDocentes(response.data.data)
      })
      .catch((error) => {
        console.error('Error al cargar docentes:', error)

        setError(
          'No se pudieron cargar los docentes'
        )
      })
      .finally(() => {
        setCargando(false)
      })
  }, [])

  const handleDelete = async (id) => {
    const confirmar = window.confirm(
      '¿Estás seguro de eliminar este docente?'
    )

    if (!confirmar) {
      return
    }

    try {
      const response = await api.delete(
        `/teachers/${id}`
      )

      console.log(
        'Eliminación exitosa:',
        response.data
      )

      setDocentes((docentesActuales) =>
        docentesActuales.filter(
          (docente) => docente.id !== id
        )
      )
    } catch (error) {
      console.error('Error al eliminar docente:', error)
      console.error('Respuesta del servidor:', error.response?.data)

      alert('No se pudo eliminar el docente.')
    }
  }

  if (cargando) {
    return <p>Cargando docentes...</p>
  }

  if (error) {
    return <p>{error}</p>
  }

  return (
    <div>
      <h1>Docentes</h1>

      <Link to="/teachers/create">
        <button>Nuevo docente</button>
      </Link>

      <table className="student-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Persona</th>
            <th>Institución</th>
            <th>Código docente</th>
            <th>Especialidad</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>

        <tbody>
          {docentes.map((docente) => (
            <tr key={docente.id}>
              <td>{docente.id}</td>

              <td>
                {docente.person
                  ? `${docente.person.first_name} ${docente.person.last_name}`
                  : '—'}
              </td>

              <td>
                {docente.institution?.name || '—'}
              </td>

              <td>
                {docente.employee_code || '—'}
              </td>

              <td>
                {docente.specialization || '—'}
              </td>

              <td>
                {docente.is_active
                  ? 'Activo'
                  : 'Inactivo'}
              </td>

              <td>
                <Link to={`/teachers/${docente.id}/edit`}>
                  <button>Editar</button>
                </Link>

                <button
                  onClick={() => handleDelete(docente.id)}
                >
                  Eliminar
                </button>
              </td>
            </tr>
          ))}

          {docentes.length === 0 && (
            <tr>
              <td colSpan="7">
                No hay docentes registrados.
              </td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  )
}

export default Teachers
