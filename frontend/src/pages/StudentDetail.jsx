import { useParams } from 'react-router-dom'
import { useState, useEffect } from 'react'
import api from '../services/api'

function StudentDetail() {
  const { id } = useParams()

  const [estudiante, setEstudiante] = useState(null)
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    api.get(`/students/${id}`)
      .then((response) => {
        setEstudiante(response.data.data)
        setCargando(false)
      })
      .catch((error) => {
        console.error('Error al cargar estudiante:', error)
        setError('No se pudo cargar el estudiante')
        setCargando(false)
      })
  }, [id])

  if (cargando) {
    return <p>Cargando estudiante...</p>
  }

  if (error) {
    return <p>{error}</p>
  }

  return (
    <div>
      <h1>Detalle del estudiante</h1>

      <p>
        <strong>ID del estudiante:</strong> {estudiante.id}
      </p>

      <p>
        <strong>Nombre:</strong>{' '}
        {estudiante.person?.first_name}{' '}
        {estudiante.person?.last_name}
      </p>

      <p>
        <strong>Código:</strong> {estudiante.student_code}
      </p>

      <p>
        <strong>Fecha de ingreso:</strong>{' '}
        {estudiante.admission_date}
      </p>

      <p>
        <strong>Estado:</strong>{' '}
        {estudiante.status === 'active'
          ? 'Activo'
          : 'Inactivo'}
      </p>
    </div>
  )
}

export default StudentDetail
