
import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api from '../services/api'

function AcademicLevels() {
  const [niveles, setNiveles] = useState([])
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    api.get('/academic-levels')
      .then((response) => {
        setNiveles(response.data.data)
        setCargando(false)
      })
      .catch((error) => {
        console.error('Error al cargar niveles:', error)
        setError('No se pudieron cargar los niveles académicos')
        setCargando(false)
      })
  }, [])

  const handleDelete = async (id) => {
    const confirmar = window.confirm(
      '¿Estás seguro de eliminar este nivel académico?'
    )

    if (!confirmar) {
      return
    }

    try {
      const response = await api.delete(
        `/academic-levels/${id}`
      )

      console.log('Eliminación exitosa:', response.data)

      window.location.reload()
    } catch (error) {
      console.error('Error completo:', error)
      console.error(
        'Respuesta del servidor:',
        error.response?.data
      )
      console.error(
        'Estado HTTP:',
        error.response?.status
      )

      if (error.response?.status === 500) {
        alert(
          'No se puede eliminar este nivel porque tiene registros asociados.'
        )
      } else {
        alert('No se pudo eliminar el nivel académico.')
      }
    }
  }

  if (cargando) {
    return <p>Cargando niveles académicos...</p>
  }

  if (error) {
    return <p>{error}</p>
  }

  return (
    <div>
      <h1>Niveles Académicos</h1>

      <Link to="/academic-levels/create">
        <button>Nuevo nivel</button>
      </Link>

      <ul>
        {niveles.map((nivel) => (
          <li key={nivel.id}>
            {nivel.name}

            {' '}

            <Link to={`/academic-levels/${nivel.id}/edit`}>
              <button>Editar</button>
            </Link>

            <button
              onClick={() => handleDelete(nivel.id)}
            >
              Eliminar
            </button>
          </li>
        ))}
      </ul>
    </div>
  )
}

export default AcademicLevels
