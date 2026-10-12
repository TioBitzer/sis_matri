
import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import api from '../services/api'

function CreatePerson() {
  const navigate = useNavigate()

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
  const [mensaje, setMensaje] = useState('')
  const [errores, setErrores] = useState({})

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
      const response = await api.post(
        '/people',
        formulario
      )

      console.log(response.data)

      setMensaje(
        'Persona creada correctamente'
      )

      setTimeout(() => {
        navigate('/persons')
      }, 1000)
    } catch (error) {
      console.error('Error completo:', error)

      console.error(
        'Respuesta del servidor:',
        error.response?.data
      )

      setMensaje(
        error.response?.data?.message ||
        'Error al crear la persona'
      )

      setErrores(
        error.response?.data?.errors || {}
      )
    }
  }

  return (
    <div>
      <h1>Nueva Persona</h1>

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
            <option value="">
              Selecciona una institución
            </option>

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
            <option value="">
              Selecciona un tipo
            </option>

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
          Crear persona
        </button>
      </form>
    </div>
  )
}

export default CreatePerson
