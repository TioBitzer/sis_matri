
import { useParams } from 'react-router-dom'
import { useState, useEffect } from 'react'
import api from '../services/api'

function EditAcademicCurriculumItem() {
  const { id } = useParams()

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
  const [cargando, setCargando] = useState(true)
  const [mensaje, setMensaje] = useState('')

  useEffect(() => {
    api.get(`/academic-curriculum-items/${id}`)
      .then((response) => {
        const elemento = response.data.data

        setFormulario({
          academic_plan_id: elemento.academic_plan_id,
          academic_level_id: elemento.academic_level_id,
          academic_unit_id: elemento.academic_unit_id,
          credits: elemento.credits,
          hours: elemento.hours,
          is_required: elemento.is_required,
          position: elemento.position,
        })

        setCargando(false)
      })
      .catch((error) => {
        console.error(
          'Error al cargar elemento curricular:',
          error
        )

        setCargando(false)
      })
  }, [id])

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
  }

  const handleSubmit = async (event) => {
    event.preventDefault()

    try {
      const response = await api.put(
        `/academic-curriculum-items/${id}`,
        formulario
      )

      console.log(response.data)

      setMensaje(
        'Elemento curricular actualizado correctamente'
      )
    } catch (error) {
      console.error('Error completo:', error)
      console.error(
        'Respuesta del servidor:',
        error.response?.data
      )

      setMensaje(
        error.response?.data?.message ||
        'Error al actualizar el elemento curricular'
      )
    }
  }

  if (cargando) {
    return <p>Cargando elemento curricular...</p>
  }

  return (
    <div>
      <h1>Editar Elemento Curricular</h1>

      {mensaje && <p>{mensaje}</p>}

      <form onSubmit={handleSubmit}>
        <div>
          <label>Plan Académico</label>

          <select
            name="academic_plan_id"
            value={formulario.academic_plan_id}
            onChange={handleChange}
          >
            {planes.map((plan) => (
              <option
                key={plan.id}
                value={plan.id}
              >
                {plan.name}
              </option>
            ))}
          </select>
        </div>

        <div>
          <label>Nivel Académico</label>

          <select
            name="academic_level_id"
            value={formulario.academic_level_id}
            onChange={handleChange}
          >
            {niveles.map((nivel) => (
              <option
                key={nivel.id}
                value={nivel.id}
              >
                {nivel.name}
              </option>
            ))}
          </select>
        </div>

        <div>
          <label>Unidad Académica</label>

          <select
            name="academic_unit_id"
            value={formulario.academic_unit_id}
            onChange={handleChange}
          >
            {unidades.map((unidad) => (
              <option
                key={unidad.id}
                value={unidad.id}
              >
                {unidad.name}
              </option>
            ))}
          </select>
        </div>

        <div>
          <label>Créditos</label>

          <input
            type="number"
            name="credits"
            value={formulario.credits}
            onChange={handleChange}
          />
        </div>

        <div>
          <label>Horas</label>

          <input
            type="number"
            name="hours"
            value={formulario.hours}
            onChange={handleChange}
          />
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
        </div>

        <div>
          <label>Posición</label>

          <input
            type="number"
            name="position"
            value={formulario.position}
            onChange={handleChange}
          />
        </div>

        <button type="submit">
          Guardar cambios
        </button>
      </form>
    </div>
  )
}

export default EditAcademicCurriculumItem
