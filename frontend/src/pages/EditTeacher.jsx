
import { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import api from '../services/api'

function EditTeacher() {
  const { id } = useParams()

  const [formulario, setFormulario] = useState({
    person_id: '',
    institution_id: '',
    employee_code: '',
    specialization: '',
    is_active: true,
  })

  const [personas, setPersonas] = useState([])
  const [instituciones, setInstituciones] = useState([])
  const [cargando, setCargando] = useState(true)
  const [mensaje, setMensaje] = useState('')
  const [errores, setErrores] = useState({})

  useEffect(() => {
    api.get(`/teachers/${id}`)
      .then((response) => {
        const docente = response.data.data

        setFormulario({
          person_id: String(docente.person_id ?? ''),
          institution_id: String(docente.institution_id ?? ''),
          employee_code: docente.employee_code ?? '',
          specialization: docente.specialization ?? '',
          is_active: Boolean(docente.is_active),
        })
      })
      .catch((error) => {
        console.error('Error al cargar docente:', error)
        setMensaje(
          error.response?.data?.message ||
          'Error al cargar el docente'
        )
      })
      .finally(() => {
        setCargando(false)
      })
  }, [id])

  useEffect(() => {
    api.get('/people')
      .then((response) => {
        setPersonas(response.data.data)
      })
      .catch((error) => {
        console.error('Error al cargar personas:', error)
      })

    api.get('/institutions')
      .then((response) => {
        setInstituciones(response.data.data)
      })
      .catch((error) => {
        console.error('Error al cargar instituciones:', error)
      })
  }, [])

  const handleChange = (event) => {
    const { name, value, type, checked } = event.target

    setFormulario({
      ...formulario,
      [name]: type === 'checkbox' ? checked : value,
    })

    setErrores({
      ...errores,
      [name]: '',
    })

    setMensaje('')
  }

  const handleSubmit = async (event) => {
    event.preventDefault()
    setErrores({})
    setMensaje('')

    try {
      await api.put(`/teachers/${id}`, formulario)

      setMensaje('Docente actualizado correctamente')
    } catch (error) {
      console.error('Error al actualizar docente:', error)
      console.error('Respuesta del servidor:', error.response?.data)

      if (error.response?.data?.errors) {
        setErrores(error.response.data.errors)
      } else {
        setMensaje(
          error.response?.data?.message ||
          'Error al actualizar el docente'
        )
      }
    }
  }

  if (cargando) {
    return <p>Cargando docente...</p>
  }

  return (
    <div>
      <h1>Editar Docente</h1>

      {mensaje && <p role="status">{mensaje}</p>}

      <form onSubmit={handleSubmit}>
        <div>
          <label htmlFor="person_id">Persona</label>

          <select
            id="person_id"
            name="person_id"
            value={formulario.person_id}
            onChange={handleChange}
            required
          >
            <option value="">Selecciona una persona</option>

            {personas.map((persona) => (
              <option key={persona.id} value={persona.id}>
                {persona.first_name} {persona.last_name}
              </option>
            ))}
          </select>

          {errores.person_id && <p>{errores.person_id[0]}</p>}
        </div>

        <div>
          <label htmlFor="institution_id">Institución</label>

          <select
            id="institution_id"
            name="institution_id"
            value={formulario.institution_id}
            onChange={handleChange}
            required
          >
            <option value="">Selecciona una institución</option>

            {instituciones.map((institucion) => (
              <option key={institucion.id} value={institucion.id}>
                {institucion.name}
              </option>
            ))}
          </select>

          {errores.institution_id && (
            <p>{errores.institution_id[0]}</p>
          )}
        </div>

        <div>
          <label htmlFor="employee_code">Código docente</label>

          <input
            id="employee_code"
            type="text"
            name="employee_code"
            value={formulario.employee_code}
            onChange={handleChange}
            maxLength={50}
          />

          {errores.employee_code && (
            <p>{errores.employee_code[0]}</p>
          )}
        </div>

        <div>
          <label htmlFor="specialization">Especialidad</label>

          <input
            id="specialization"
            type="text"
            name="specialization"
            value={formulario.specialization}
            onChange={handleChange}
            maxLength={255}
          />

          {errores.specialization && (
            <p>{errores.specialization[0]}</p>
          )}
        </div>

        <div>
          <label>
            <input
              type="checkbox"
              name="is_active"
              checked={formulario.is_active}
              onChange={handleChange}
            />
            Docente activo
          </label>

          {errores.is_active && <p>{errores.is_active[0]}</p>}
        </div>

        <button type="submit">Guardar cambios</button>
      </form>
    </div>
  )
}

export default EditTeacher
