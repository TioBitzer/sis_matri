
import { useParams } from 'react-router-dom'
import { useState, useEffect } from 'react'
import api from '../services/api'

function EditAcademicProgram() {
  const { id } = useParams()

  const [formulario, setFormulario] = useState({
    institution_id: '',
    name: '',
    code: '',
    description: '',
    is_active: true,
  })

  const [instituciones, setInstituciones] = useState([])
  const [cargando, setCargando] = useState(true)
  const [mensaje, setMensaje] = useState('')

  useEffect(() => {
    api.get(`/academic-programs/${id}`)
      .then((response) => {
        const programa = response.data.data

        setFormulario({
          institution_id: programa.institution_id,
          name: programa.name,
          code: programa.code,
          description: programa.description || '',
          is_active: programa.is_active,
        })

        setCargando(false)
      })
      .catch((error) => {
        console.error('Error al cargar programa:', error)
        setCargando(false)
      })
  }, [id])

  useEffect(() => {
    api.get('/institutions')
      .then((response) => {
        setInstituciones(response.data.data)
      })
      .catch((error) => {
        console.error('Error al cargar instituciones:', error)
      })
  }, [])

  const handleChange = (event) => {
    const { name, value } = event.target

    setFormulario({
      ...formulario,
      [name]: name === 'is_active'
        ? value === 'true'
        : value,
    })
  }

  const handleSubmit = async (event) => {
    event.preventDefault()

    try {
      const response = await api.put(
        `/academic-programs/${id}`,
        formulario
      )

      console.log(response.data)

      setMensaje('Programa académico actualizado correctamente')
    } catch (error) {
      console.error('Error completo:', error)
      console.error(
        'Respuesta del servidor:',
        error.response?.data
      )

      setMensaje(
        error.response?.data?.message ||
        'Error al actualizar el programa académico'
      )
    }
  }

  if (cargando) {
    return <p>Cargando programa académico...</p>
  }

  return (
    <div>
      <h1>Editar Programa Académico</h1>

      {mensaje && <p>{mensaje}</p>}

      <form onSubmit={handleSubmit}>
        <div>
          <label>Institución</label>

          <select
            name="institution_id"
            value={formulario.institution_id}
            onChange={handleChange}
          >
            {instituciones.map((institucion) => (
              <option
                key={institucion.id}
                value={institucion.id}
              >
                {institucion.name}
              </option>
            ))}
          </select>
        </div>

        <div>
          <label>Nombre</label>

          <input
            type="text"
            name="name"
            value={formulario.name}
            onChange={handleChange}
          />
        </div>

        <div>
          <label>Código</label>

          <input
            type="text"
            name="code"
            value={formulario.code}
            onChange={handleChange}
          />
        </div>

        <div>
          <label>Descripción</label>

          <textarea
            name="description"
            value={formulario.description}
            onChange={handleChange}
          />
        </div>

        <div>
          <label>Estado</label>

          <select
            name="is_active"
            value={formulario.is_active}
            onChange={handleChange}
          >
            <option value="true">Activo</option>
            <option value="false">Inactivo</option>
          </select>
        </div>

        <button type="submit">
          Guardar cambios
        </button>
      </form>
    </div>
  )
}

export default EditAcademicProgram
