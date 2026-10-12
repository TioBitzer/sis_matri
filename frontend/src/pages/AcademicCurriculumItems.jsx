
import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api from '../services/api'

function AcademicCurriculumItems() {
  const [elementos, setElementos] = useState([])
  const [cargando, setCargando] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    api.get('/academic-curriculum-items')
      .then((response) => {
        setElementos(response.data.data)
        setCargando(false)
      })
      .catch((error) => {
        console.error(
          'Error al cargar elementos curriculares:',
          error
        )

        setError(
          'No se pudieron cargar los elementos curriculares'
        )

        setCargando(false)
      })
  }, [])

  const handleDelete = async (id) => {
    const confirmar = window.confirm(
      '¿Estás seguro de eliminar este elemento curricular?'
    )

    if (!confirmar) {
      return
    }

    try {
      const response = await api.delete(
        `/academic-curriculum-items/${id}`
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
        'No se pudo eliminar el elemento curricular.'
      )
    }
  }

  if (cargando) {
    return <p>Cargando elementos curriculares...</p>
  }

  if (error) {
    return <p>{error}</p>
  }

  return (
    <div>
      <h1>Elementos Curriculares</h1>

      <Link to="/academic-curriculum-items/create">
        <button>Nuevo elemento</button>
      </Link>

      <table className="student-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Plan</th>
            <th>Nivel</th>
            <th>Unidad</th>
            <th>Créditos</th>
            <th>Horas</th>
            <th>Obligatorio</th>
            <th>Posición</th>
            <th>Acciones</th>
          </tr>
        </thead>

        <tbody>
          {elementos.map((elemento) => (
            <tr key={elemento.id}>
              <td>{elemento.id}</td>

              <td>
                {elemento.academic_plan?.name}
              </td>

              <td>
                {elemento.academic_level?.name}
              </td>

              <td>
                {elemento.academic_unit?.name}
              </td>

              <td>
                {elemento.credits}
              </td>

              <td>
                {elemento.hours}
              </td>

              <td>
                {elemento.is_required ? 'Sí' : 'No'}
              </td>

              <td>
                {elemento.position}
              </td>

              <td>
                <Link
                  to={`/academic-curriculum-items/${elemento.id}/edit`}
                >
                  <button>Editar</button>
                </Link>

                <button
                  onClick={() => handleDelete(elemento.id)}
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

export default AcademicCurriculumItems
