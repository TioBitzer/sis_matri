import { useEffect, useState } from 'react'
import api from '../services/api'
import StudentTable from '../components/StudentTable'
import { Link } from 'react-router-dom'

function Students() {
  const [estudiantes, setEstudiantes] = useState([])
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    api.get('/students')
      .then((response) => {
        setEstudiantes(response.data.data)
        setCargando(false)
      })
      .catch(() => {
        setError('No se pudieron cargar los estudiantes')
        setCargando(false)
      })
  }, [])

  if (cargando) {
    return <p>Cargando estudiantes...</p>
  }

  if (error) {
    return <p>{error}</p>
  }

  return (
    <div>
      <h1>Estudiantes</h1>

<Link to="/students/create">
  <button>Nuevo estudiante</button>
</Link>

<StudentTable estudiantes={estudiantes} />
    </div>
  )
}

export default Students
