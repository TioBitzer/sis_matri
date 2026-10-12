import { Link } from 'react-router-dom'
import api from '../services/api'

function StudentTable({ estudiantes }) {
const handleDelete = async (id) => {
const confirmar = window.confirm(
'¿Estás seguro de eliminar este estudiante?'
)


if (!confirmar) {
  return
}

try {
  const response = await api.delete(`/students/${id}`)

  console.log('Eliminación exitosa:', response.data)

  window.location.reload()
} catch (error) {
  console.error('Error completo:', error)
  console.error('Respuesta del servidor:', error.response?.data)
  console.error('Estado HTTP:', error.response?.status)

  if (error.response?.status === 500) {
    alert(
      'No se puede eliminar este estudiante porque tiene registros académicos o matrículas asociadas.'
    )
  } else {
    alert('No se pudo eliminar el estudiante.')
  }
}


}

return ( <div className="table-container"> <table className="student-table"> <thead> <tr> <th className="student-id">ID</th> <th>Nombres</th> <th>Apellidos</th> <th className="student-actions-heading">Acciones</th> </tr> </thead>

```
    <tbody>
      {!estudiantes || estudiantes.length === 0 ? (
        <tr>
          <td colSpan={4} className="student-empty">
            No hay estudiantes registrados.
          </td>
        </tr>
      ) : (
        estudiantes.map((estudiante) => (
          <tr key={estudiante.id}>
            <td className="student-id">{estudiante.id}</td>

            <td>
              {estudiante.person?.first_name || '—'}
            </td>

            <td>
              {estudiante.person?.last_name || '—'}
            </td>

            <td className="student-actions">
              <Link
                to={`/students/${estudiante.id}`}
                className="student-action student-action-view"
              >
                Ver
              </Link>

              <Link
                to={`/students/${estudiante.id}/edit`}
                className="student-action student-action-edit"
              >
                Editar
              </Link>

              <button
                type="button"
                className="student-action student-action-delete"
                onClick={() => handleDelete(estudiante.id)}
              >
                Eliminar
              </button>
            </td>
          </tr>
        ))
      )}
    </tbody>
  </table>
</div>


)
}

export default StudentTable
