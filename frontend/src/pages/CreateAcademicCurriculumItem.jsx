
import { useState, useEffect } from 'react'
import api from '../services/api'

function CreateAcademicCurriculumItem() {
  const [formulario, setFormulario] = useState({
    academic_plan_id: '',
    academic_level_id: '',
    academic_unit_id: '',
    credits: '',
    hours: '',
    is_required: true,
    position: '',
  })

  const [planes, setPlanes] = useState([])
  const [niveles, setNiveles] = useState([])
  const [unidades, setUnidades] = useState([])
  const [mensaje, setMensaje] = useState('')
  const [errores, setErrores] = useState({})

  useEffect(() => {
    api.get('/academic-plans')
      .then((response) => {
        setPlanes(response.data.data)
      })
      .catch((error) => {
        console.error('Error al cargar planes:', error)
      })

    api.get('/academic-levels')
      .then((response) => {
        setNiveles(response.data.data)
      })
      .catch((error) => {
        console.error('Error al cargar niveles:', error)
      })

    api.get('/academic-units')
      .then((response) => {
        setUnidades(response.data.data)
      })
      .catch((error) => {
        console.error('Error al cargar unidades:', error)
      })
  }, [])

  const handleChange = (event) => {
    const { name, value } = event.target

    setFormulario({
      ...formulario,
      [name]: name === 'is_required'
        ? value === 'true'
        : value,
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
        '/academic-curriculum-items',
        formulario
      )

      console.log(response.data)

      setMensaje(
        'Elemento curricular creado correctamente'
      )

      setFormulario({
        academic_plan_id: '',
        academic_level_id: '',
        academic_unit_id: '',
        credits: '',
        hours: '',
        is_required: true,
        position: '',
      })
    } catch (error) {
      console.error('Error completo:', error)
      console.error(
        'Respuesta del servidor:',
        error.response?.data
      )

      setMensaje(
        error.response?.data?.message ||
        'Error al crear el elemento curricular'
      )

      setErrores(
        error.response?.data?.errors || {}
      )
    }
  }

  return (
    <div>
      <h1>Nuevo Elemento Curricular</h1>

      {mensaje && <p>{mensaje}</p>}

      <form onSubmit={handleSubmit}>
        <div>
          <label>Plan Académico</label>

          <select
            name="academic_plan_id"
            value={formulario.academic_plan_id}
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
            <p>{errores.academic_plan_id[0]}</p>
          )}
        </div>

        <div>
          <label>Nivel Académico</label>

          <select
            name="academic_level_id"
            value={formulario.academic_level_id}
            onChange={handleChange}
            required
          >
            <option value="">
              Selecciona un nivel
            </option>

            {niveles.map((nivel) => (
              <option
                key={nivel.id}
                value={nivel.id}
              >
                {nivel.name}
              </option>
            ))}
          </select>

          {errores.academic_level_id && (
            <p>{errores.academic_level_id[0]}</p>
          )}
        </div>

        <div>
          <label>Unidad Académica</label>

          <select
            name="academic_unit_id"
            value={formulario.academic_unit_id}
            onChange={handleChange}
            required
          >
            <option value="">
              Selecciona una unidad
            </option>

            {unidades.map((unidad) => (
              <option
                key={unidad.id}
                value={unidad.id}
              >
                {unidad.name}
              </option>
            ))}
          </select>

          {errores.academic_unit_id && (
            <p>{errores.academic_unit_id[0]}</p>
          )}
        </div>

        <div>
          <label>Créditos</label>

          <input
            type="number"
            name="credits"
            value={formulario.credits}
            onChange={handleChange}
            min="0"
          />

          {errores.credits && (
            <p>{errores.credits[0]}</p>
          )}
        </div>

        <div>
          <label>Horas</label>

          <input
            type="number"
            name="hours"
            value={formulario.hours}
            onChange={handleChange}
            min="0"
          />

          {errores.hours && (
            <p>{errores.hours[0]}</p>
          )}
        </div>

        <div>
          <label>¿Es obligatorio?</label>

          <select
            name="is_required"
            value={formulario.is_required}
            onChange={handleChange}
          >
            <option value="true">Sí</option>
            <option value="false">No</option>
          </select>

          {errores.is_required && (
            <p>{errores.is_required[0]}</p>
          )}
        </div>

        <div>
          <label>Posición</label>

          <input
            type="number"
            name="position"
            value={formulario.position}
            onChange={handleChange}
            min="1"
          />

          {errores.position && (
            <p>{errores.position[0]}</p>
          )}
        </div>

        <button type="submit">
          Crear elemento
        </button>
      </form>
    </div>
  )
}

export default CreateAcademicCurriculumItem
