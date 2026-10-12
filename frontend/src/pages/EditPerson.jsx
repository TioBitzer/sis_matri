
import { useParams } from 'react-router-dom'
import { useState, useEffect } from 'react'
import api from '../services/api'

function EditPerson() {
  const { id } = useParams()

  const [formulario, setFormulario] = useState({
    institution_id: '',
    first_name: '',
    last_name: '',
    document_type: '',
    document_number: '',
    email: '',
    phone: '',
    birth_date: '',
  })

  const [instituciones, setInstituciones] = useState([])
  const [cargando, setCargando] = useState(true)
  const [mensaje, setMensaje] = useState('')
  const [errores, setErrores] = useState({})

  useEffect(() => {
    api.get(`/people/${id}`)
      .then((response) => {
        const persona = response.data.data

        setFormulario({
          institution_id: persona.institution_id,
          first_name: persona.first_name,
          last_name: persona.last_name,
          document_type: persona.document_type,
          document_number: persona.document_number,
          email: persona.email || '',
          phone: persona.phone || '',
          birth_date: persona.birth_date
            ? persona.birth_date.substring(0, 10)
            : '',
        })

        setCargando(false)
      })
      .catch((error) => {
        console.error(
          'Error al cargar persona:',
          error
        )

        setCargando(false)
      })
  }, [id])

  useEffect(() => {
    api.get('/institutions')
      .then((response) => {
        setInstituciones(response.data.data)
      })
      .catch((error) => {
        console.error(
          'Error al cargar instituciones:',
          error
        )
      })
  }, [])

  const handleChange = (event) => {
    const { name, value } = event.target

    setFormulario({
      ...formulario,
      [name]: value,
    })

    setErrores({
      ...errores,
      [name]: '',
    })
  }

  const handleSubmit = async (event) => {
    event.preventDefault()

    setMensaje('')
    setErrores({})

    try {
      const response = await api.put(
        `/people/${id}`,
        formulario
      )

      console.log(response.data)

      setMensaje(
        'Persona actualizada correctamente'
      )
    } catch (error) {
      console.error('Error completo:', error)

      console.error(
        'Respuesta del servidor:',
        error.response?.data
      )

      setMensaje(
        error.response?.data?.message ||
        'Error al actualizar la persona'
      )

      setErrores(
        error.response?.data?.errors || {}
      )
    }
  }

  if (cargando) {
    return <p>Cargando persona...</p>
  }

  return (
    <div>
      <h1>Editar Persona</h1>

      {mensaje && <p>{mensaje}</p>}

      <form onSubmit={handleSubmit}>
        <div>
          <label>Institución</label>

          <select
            name="institution_id"
            value={formulario.institution_id}
            onChange={handleChange}
            required
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

          {errores.institution_id && (
            <p>{errores.institution_id[0]}</p>
          )}
        </div>

        <div>
          <label>Nombres</label>

          <input
            type="text"
            name="first_name"
            value={formulario.first_name}
            onChange={handleChange}
            required
            maxLength="255"
          />

          {errores.first_name && (
            <p>{errores.first_name[0]}</p>
          )}
        </div>

        <div>
          <label>Apellidos</label>

          <input
            type="text"
            name="last_name"
            value={formulario.last_name}
            onChange={handleChange}
            required
            maxLength="255"
          />

          {errores.last_name && (
            <p>{errores.last_name[0]}</p>
          )}
        </div>

        <div>
          <label>Tipo de documento</label>

          <select
            name="document_type"
            value={formulario.document_type}
            onChange={handleChange}
            required
          >
            <option value="DNI">
              DNI
            </option>

            <option value="CE">
              CE
            </option>

            <option value="Pasaporte">
              Pasaporte
            </option>
          </select>

          {errores.document_type && (
            <p>{errores.document_type[0]}</p>
          )}
        </div>

        <div>
          <label>Número de documento</label>

          <input
            type="text"
            name="document_number"
            value={formulario.document_number}
            onChange={handleChange}
            required
            maxLength="50"
          />

          {errores.document_number && (
            <p>{errores.document_number[0]}</p>
          )}
        </div>

        <div>
          <label>Email</label>

          <input
            type="email"
            name="email"
            value={formulario.email}
            onChange={handleChange}
            maxLength="255"
          />

          {errores.email && (
            <p>{errores.email[0]}</p>
          )}
        </div>

        <div>
          <label>Teléfono</label>

          <input
            type="text"
            name="phone"
            value={formulario.phone}
            onChange={handleChange}
            maxLength="20"
          />

          {errores.phone && (
            <p>{errores.phone[0]}</p>
          )}
        </div>

        <div>
          <label>Fecha de nacimiento</label>

          <input
            type="date"
            name="birth_date"
            value={formulario.birth_date}
            onChange={handleChange}
          />

          {errores.birth_date && (
            <p>{errores.birth_date[0]}</p>
          )}
        </div>

        <button type="submit">
          Guardar cambios
        </button>
      </form>
    </div>
  )
}

export default EditPerson
