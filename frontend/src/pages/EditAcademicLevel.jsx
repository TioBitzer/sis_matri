
import { useParams } from 'react-router-dom'
import { useState, useEffect } from 'react'
import api from '../services/api'

function EditAcademicLevel() {
  const { id } = useParams()

  const [formulario, setFormulario] = useState({
    academic_plan_id: '',
    name: '',
    order: '',
    is_active: true,
  })

  const [planes, setPlanes] = useState([])
  const [cargando, setCargando] = useState(true)
  const [mensaje, setMensaje] = useState('')
  const [errores, setErrores] = useState({})

  useEffect(() => {
    api.get(`/academic-levels/${id}`)
      .then((response) => {
        const nivel = response.data.data

        setFormulario({
          academic_plan_id:
            nivel.academic_plan_id,

          name:
            nivel.name,

          order:
            nivel.order,

          is_active:
            nivel.is_active,
        })

        setCargando(false)
      })
      .catch((error) => {
        console.error(
          'Error al cargar nivel:',
          error
        )

        setMensaje(
          'No se pudo cargar el nivel académico'
        )

        setCargando(false)
      })
  }, [id])

  useEffect(() => {
    api.get('/academic-plans')
      .then((response) => {
        setPlanes(
          response.data.data
        )
      })
      .catch((error) => {
        console.error(
          'Error al cargar planes:',
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
        `/academic-levels/${id}`,
        formulario
      )

      console.log(
        response.data
      )

      setMensaje(
        'Nivel académico actualizado correctamente'
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
        'Error al actualizar el nivel académico'
      )
    }
  }

  if (cargando) {
    return (
      <p>
        Cargando nivel académico...
      </p>
    )
  }

  return (
    <div>
      <h1>Editar Nivel Académico</h1>

      {mensaje && (
        <p>{mensaje}</p>
      )}

      <form onSubmit={handleSubmit}>
        <div>
          <label>
            Plan Académico
          </label>

          <select
            name="academic_plan_id"
            value={
              formulario.academic_plan_id
            }
            onChange={handleChange}
            required
          >
            <option value="">
              Selecciona un plan
            </option>

            {planes.map((plan) => (
              <option
                key={plan.id}
                value={plan.id}
              >
                {plan.name}
              </option>
            ))}
          </select>

          {errores.academic_plan_id && (
            <p>
              {
                errores
                  .academic_plan_id[0]
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
            Orden
          </label>

          <input
            type="number"
            name="order"
            value={formulario.order}
            onChange={handleChange}
            min="1"
            step="1"
            required
          />

          {errores.order && (
            <p>
              {errores.order[0]}
            </p>
          )}
        </div>

        <div>
          <label>
            Estado
          </label>

          <select
            name="is_active"
            value={formulario.is_active}
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

export default EditAcademicLevel
