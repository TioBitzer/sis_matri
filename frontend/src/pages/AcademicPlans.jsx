
import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api from '../services/api'

function AcademicPlans() {
  const [planes, setPlanes] = useState([])
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    api.get('/academic-plans')
      .then((response) => {
        setPlanes(response.data.data)
        setCargando(false)
      })
      .catch((error) => {
        console.error('Error al cargar planes:', error)
        setError('No se pudieron cargar los planes académicos')
        setCargando(false)
      })
  }, [])

  const handleDelete = async (id) => {
    const confirmar = window.confirm(
      '¿Estás seguro de eliminar este plan académico?'
    )

    if (!confirmar) {
      return
    }

    try {
      const response = await api.delete(
        `/academic-plans/${id}`
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
          'No se puede eliminar este plan porque tiene registros asociados.'
        )
      } else {
        alert('No se pudo eliminar el plan académico.')
      }
    }
  }

  if (cargando) {
    return <p>Cargando planes académicos...</p>
  }

  if (error) {
    return <p>{error}</p>
  }

  return (
    <div>
      <h1>Planes Académicos</h1>

      <Link to="/academic-plans/create">
        <button>Nuevo plan</button>
      </Link>

      <ul>
        {planes.map((plan) => (
          <li key={plan.id}>
            {plan.name}

            {' '}

            <Link to={`/academic-plans/${plan.id}/edit`}>
              <button>Editar</button>
            </Link>

            <button
              onClick={() => handleDelete(plan.id)}
            >
              Eliminar
            </button>
          </li>
        ))}
      </ul>
    </div>
  )
}

export default AcademicPlans
