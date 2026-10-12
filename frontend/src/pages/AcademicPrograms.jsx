
import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api from '../services/api'

function AcademicPrograms() {
  const [programas, setProgramas] = useState([])
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    api.get('/academic-programs')
      .then((response) => {
        setProgramas(response.data.data)
        setCargando(false)
      })
      .catch((error) => {
        console.error('Error al cargar programas:', error)
        setError('No se pudieron cargar los programas académicos')
        setCargando(false)
      })
  }, [])

  const handleDelete = async (id) => {
    const confirmar = window.confirm(
      '¿Estás seguro de eliminar este programa académico?'
    )

    if (!confirmar) {
      return
    }

    try {
      const response = await api.delete(
        `/academic-programs/${id}`
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
          'No se puede eliminar este programa porque tiene registros asociados.'
        )
      } else {
        alert('No se pudo eliminar el programa académico.')
      }
    }
  }

  if (cargando) {
    return <p>Cargando programas académicos...</p>
  }

  if (error) {
    return <p>{error}</p>
  }

  return (
    <div>
      <h1>Programas Académicos</h1>

      <Link to="/academic-programs/create">
        <button>Nuevo programa</button>
      </Link>

      <ul>
        {programas.map((programa) => (
          <li key={programa.id}>
            {programa.name}

            {' '}

            <Link to={`/academic-programs/${programa.id}/edit`}>
              <button>Editar</button>
            </Link>

            <button
              onClick={() => handleDelete(programa.id)}
            >
              Eliminar
            </button>
          </li>
        ))}
      </ul>
    </div>
  )
}

export default AcademicPrograms
