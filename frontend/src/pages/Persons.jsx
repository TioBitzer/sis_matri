
import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api from '../services/api'

function Persons() {
  const [personas, setPersonas] = useState([])
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    api.get('/people')
      .then((response) => {
        setPersonas(response.data.data)
        setCargando(false)
      })
      .catch((error) => {
        console.error(
          'Error al cargar personas:',
          error
        )

        setError(
          'No se pudieron cargar las personas'
        )

        setCargando(false)
      })
  }, [])

  const handleDelete = async (id) => {
    const confirmar = window.confirm(
      '¿Estás seguro de eliminar esta persona?'
    )

    if (!confirmar) {
      return
    }

    try {
      const response = await api.delete(
        `/people/${id}`
      )

      console.log(
        'Eliminación exitosa:',
        response.data
      )

      window.location.reload()
    } catch (error) {
      console.error(
        'Error completo:',
        error
      )

      console.error(
        'Respuesta del servidor:',
        error.response?.data
      )

      alert(
        'No se pudo eliminar la persona.'
      )
    }
  }

  if (cargando) {
    return <p>Cargando personas...</p>
  }

  if (error) {
    return <p>{error}</p>
  }

  return (
    <div>
      <h1>Personas</h1>

      <Link to="/persons/create">
        <button>Nueva persona</button>
      </Link>

      <table className="student-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Tipo documento</th>
            <th>Número documento</th>
            <th>Nombres</th>
            <th>Apellidos</th>
            <th>Email</th>
            <th>Teléfono</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>

        <tbody>
          {personas.map((persona) => (
            <tr key={persona.id}>
              <td>{persona.id}</td>

              <td>
                {persona.document_type}
              </td>

              <td>
                {persona.document_number}
              </td>

              <td>
                {persona.first_name}
              </td>

              <td>
                {persona.last_name}
              </td>

              <td>
                {persona.email}
              </td>

              <td>
                {persona.phone}
              </td>

              <td>
                {persona.is_active
                  ? 'Activo'
                  : 'Inactivo'}
              </td>

              <td>
                <Link
                  to={`/persons/${persona.id}/edit`}
                >
                  <button>Editar</button>
                </Link>

                <button
                  onClick={() =>
                    handleDelete(persona.id)
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

export default Persons
