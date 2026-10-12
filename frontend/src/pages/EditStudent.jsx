
import { useParams } from 'react-router-dom'
import { useState, useEffect } from 'react'
import api from '../services/api'

function EditStudent() {
  const { id } = useParams()

  const [estudiante, setEstudiante] = useState(null)

  const [formulario, setFormulario] = useState({
    person_id: '',
    student_code: '',
    admission_date: '',
    status: 'active',
  })

  const [mensaje, setMensaje] = useState('')
  const [errores, setErrores] = useState({})
  const [cargando, setCargando] = useState(true)

  useEffect(() => {
    api.get(`/students/${id}`)
      .then((response) => {
        const datosEstudiante = response.data.data

        setEstudiante(datosEstudiante)

        setFormulario({
          person_id: datosEstudiante.person_id,
          student_code: datosEstudiante.student_code,
          admission_date:
            datosEstudiante.admission_date || '',
          status: datosEstudiante.status,
        })

        setCargando(false)
      })
      .catch((error) => {
        console.error(
          'Error al cargar estudiante:',
          error
        )

        setMensaje(
          'No se pudo cargar el estudiante'
        )

        setCargando(false)
      })
  }, [id])

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
      const response = await api.put(
        `/students/${id}`,
        formulario
      )

      console.log(response.data)

      setMensaje(
        'Estudiante actualizado correctamente'
      )
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
        'Error al actualizar el estudiante'
      )
    }
  }

  if (cargando) {
    return <p>Cargando estudiante...</p>
  }

  return (
    <div>
      <h1>Editar estudiante</h1>

      <p>ID del estudiante: {id}</p>

      {mensaje && (
        <p>{mensaje}</p>
      )}

      <form onSubmit={handleSubmit}>
        <div>
          <label>Persona</label>

          <input
            type="text"
            value={
              estudiante?.person
                ? `${estudiante.person.first_name} ${estudiante.person.last_name}`
                : ''
            }
            disabled
          />
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
          Guardar cambios
        </button>
      </form>
    </div>
  )
}

export default EditStudent
