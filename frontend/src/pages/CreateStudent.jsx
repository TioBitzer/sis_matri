
import { useState, useEffect } from 'react'
import api from '../services/api'

function CreateStudent() {
  const [formulario, setFormulario] = useState({
    person_id: '',
    student_code: '',
    admission_date: '',
    status: 'active',
  })

  const [mensaje, setMensaje] = useState('')
  const [errores, setErrores] = useState({})
  const [personas, setPersonas] = useState([])
  const [estudiantes, setEstudiantes] = useState([])

  useEffect(() => {
    api.get('/people')
      .then((response) => {
        setPersonas(response.data.data)
      })
      .catch((error) => {
        console.error(
          'Error al cargar personas:',
          error
        )
      })
  }, [])

  useEffect(() => {
    api.get('/students')
      .then((response) => {
        setEstudiantes(response.data.data)
      })
      .catch((error) => {
        console.error(
          'Error al cargar estudiantes:',
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

    setMensaje('')
  }

  const handleSubmit = async (event) => {
    event.preventDefault()

    setMensaje('')
    setErrores({})

    try {
      const response = await api.post(
        '/students',
        formulario
      )

      console.log(response.data)

      setMensaje(
        'Estudiante registrado correctamente'
      )

      setFormulario({
        person_id: '',
        student_code: '',
        admission_date: '',
        status: 'active',
      })

      // Actualizamos la lista de estudiantes
      setEstudiantes([
        ...estudiantes,
        response.data.data,
      ])
    } catch (error) {
      console.error(
        'Error completo:',
        error
      )

      console.error(
        'Respuesta del servidor:',
        error.response?.data
      )

      setErrores(
        error.response?.data?.errors || {}
      )

      setMensaje(
        error.response?.data?.message ||
        'Error al registrar el estudiante'
      )
    }
  }

  return (
    <div>
      <h1>Nuevo estudiante</h1>

      {mensaje && (
        <p>{mensaje}</p>
      )}

      <form onSubmit={handleSubmit}>
        <div>
          <label>Persona</label>

          <select
            name="person_id"
            value={formulario.person_id}
            onChange={handleChange}
            required
          >
            <option value="">
              Selecciona una persona
            </option>

            {personas
              .filter((persona) => {
                return !estudiantes.some(
                  (estudiante) =>
                    estudiante.person_id === persona.id
                )
              })
              .map((persona) => (
                <option
                  key={persona.id}
                  value={persona.id}
                >
                  {persona.first_name}{' '}
                  {persona.last_name}
                </option>
              ))}
          </select>

          {errores.person_id && (
            <p>
              {errores.person_id[0]}
            </p>
          )}
        </div>

        <div>
          <label>Código de estudiante</label>

          <input
            type="text"
            name="student_code"
            value={formulario.student_code}
            onChange={handleChange}
            required
          />

          {errores.student_code && (
            <p>
              {errores.student_code[0]}
            </p>
          )}
        </div>

        <div>
          <label>Fecha de ingreso</label>

          <input
            type="date"
            name="admission_date"
            value={formulario.admission_date}
            onChange={handleChange}
          />

          {errores.admission_date && (
            <p>
              {errores.admission_date[0]}
            </p>
          )}
        </div>

        <div>
          <label>Estado</label>

          <select
            name="status"
            value={formulario.status}
            onChange={handleChange}
          >
            <option value="active">
              Activo
            </option>

            <option value="inactive">
              Inactivo
            </option>
          </select>

          {errores.status && (
            <p>
              {errores.status[0]}
            </p>
          )}
        </div>

        <button type="submit">
          Registrar estudiante
        </button>
      </form>
    </div>
  )
}

export default CreateStudent
