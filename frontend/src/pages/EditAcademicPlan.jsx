
import { useParams } from 'react-router-dom'
import { useState, useEffect } from 'react'
import api from '../services/api'

function EditAcademicPlan() {
  const { id } = useParams()

  const [formulario, setFormulario] = useState({
    academic_program_id: '',
    name: '',
    version: '',
    start_date: '',
    end_date: '',
    is_active: true,
  })

  const [programas, setProgramas] = useState([])
  const [cargando, setCargando] = useState(true)
  const [mensaje, setMensaje] = useState('')
  const [errores, setErrores] = useState({})

  useEffect(() => {
    api.get(`/academic-plans/${id}`)
      .then((response) => {
        const plan = response.data.data

        console.log(
          'PLAN RECIBIDO:',
          plan
        )

        setFormulario({
          academic_program_id:
            plan.academic_program_id,

          name:
            plan.name,

          version:
            plan.version,

          start_date: plan.start_date
            ? plan.start_date.substring(0, 10)
            : '',

          end_date: plan.end_date
            ? plan.end_date.substring(0, 10)
            : '',

          is_active:
            plan.is_active,
        })

        setCargando(false)
      })
      .catch((error) => {
        console.error(
          'Error al cargar plan:',
          error
        )

        setMensaje(
          'No se pudo cargar el plan académico'
        )

        setCargando(false)
      })
  }, [id])

  useEffect(() => {
    api.get('/academic-programs')
      .then((response) => {
        setProgramas(
          response.data.data
        )
      })
      .catch((error) => {
        console.error(
          'Error al cargar programas:',
          error
        )
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
        `/academic-plans/${id}`,
        formulario
      )

      console.log(
        response.data
      )

      setMensaje(
        'Plan académico actualizado correctamente'
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
        'Error al actualizar el plan académico'
      )
    }
  }

  if (cargando) {
    return (
      <p>
        Cargando plan académico...
      </p>
    )
  }

  return (
    <div>
      <h1>Editar Plan Académico</h1>

      {mensaje && (
        <p>{mensaje}</p>
      )}

      <form onSubmit={handleSubmit}>
        <div>
          <label>
            Programa Académico
          </label>

          <select
            name="academic_program_id"
            value={
              formulario.academic_program_id
            }
            onChange={handleChange}
            required
          >
            <option value="">
              Selecciona un programa
            </option>

            {programas.map((programa) => (
              <option
                key={programa.id}
                value={programa.id}
              >
                {programa.name}
              </option>
            ))}
          </select>

          {errores.academic_program_id && (
            <p>
              {
                errores
                  .academic_program_id[0]
              }
            </p>
          )}
        </div>

        <div>
          <label>
            Nombre
          </label>

          <input
            type="text"
            name="name"
            value={formulario.name}
            onChange={handleChange}
            required
          />

          {errores.name && (
            <p>
              {errores.name[0]}
            </p>
          )}
        </div>

        <div>
          <label>
            Versión
          </label>

          <input
            type="text"
            name="version"
            value={formulario.version}
            onChange={handleChange}
            required
          />

          {errores.version && (
            <p>
              {errores.version[0]}
            </p>
          )}
        </div>

        <div>
          <label>
            Fecha de inicio
          </label>

          <input
            type="date"
            name="start_date"
            value={
              formulario.start_date
            }
            onChange={handleChange}
          />

          {errores.start_date && (
            <p>
              {errores.start_date[0]}
            </p>
          )}
        </div>

        <div>
          <label>
            Fecha de fin
          </label>

          <input
            type="date"
            name="end_date"
            value={
              formulario.end_date
            }
            onChange={handleChange}
          />

          {errores.end_date && (
            <p>
              {errores.end_date[0]}
            </p>
          )}
        </div>

        <div>
          <label>
            Estado
          </label>

          <select
            name="is_active"
            value={
              formulario.is_active
            }
            onChange={handleChange}
          >
            <option value="true">
              Activo
            </option>

            <option value="false">
              Inactivo
            </option>
          </select>

          {errores.is_active && (
            <p>
              {errores.is_active[0]}
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

export default EditAcademicPlan
