
import { useState, useEffect } from 'react'
import api from '../services/api'

function CreateAcademicLevel() {
  const [formulario, setFormulario] = useState({
    academic_plan_id: '',
    name: '',
    order: '',
    is_active: true,
  })

  const [planes, setPlanes] = useState([])
  const [mensaje, setMensaje] = useState('')
  const [errores, setErrores] = useState({})

  useEffect(() => {
    api.get('/academic-plans')
      .then((response) => {
        setPlanes(response.data.data)
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
      const response = await api.post(
        '/academic-levels',
        formulario
      )

      console.log(response.data)

      setMensaje(
        'Nivel académico creado correctamente'
      )

      setFormulario({
        academic_plan_id: '',
        name: '',
        order: '',
        is_active: true,
      })
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
        'Error al crear el nivel académico'
      )
    }
  }

  return (
    <div>
      <h1>Nuevo Nivel Académico</h1>

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
          Crear nivel
        </button>
      </form>
    </div>
  )
}

export default CreateAcademicLevel
