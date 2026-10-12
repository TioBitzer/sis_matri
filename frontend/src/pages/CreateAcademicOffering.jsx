
import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import api from '../services/api'

function CreateAcademicOffering() {
  const navigate = useNavigate()

  const [formulario, setFormulario] = useState({
    academic_unit_id: '',
    academic_period_id: '',
    campus_id: '',
    section: '',
    capacity: '',
    is_active: true,
  })

  const [unidades, setUnidades] = useState([])
  const [periodos, setPeriodos] = useState([])
  const [campus, setCampus] = useState([])
  const [mensaje, setMensaje] = useState('')
  const [errores, setErrores] = useState({})

  useEffect(() => {
    api.get('/academic-units')
      .then((response) => {
        setUnidades(response.data.data)
      })
      .catch((error) => {
        console.error('Error al cargar unidades académicas:', error)
      })

    api.get('/academic-periods')
      .then((response) => {
        setPeriodos(response.data.data)
      })
      .catch((error) => {
        console.error('Error al cargar períodos académicos:', error)
      })

    api.get('/campuses')
      .then((response) => {
        setCampus(response.data.data)
      })
      .catch((error) => {
        console.error('Error al cargar campus:', error)
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
      await api.post('/academic-offerings', formulario)

      setMensaje('Oferta académica creada correctamente')

      setTimeout(() => {
        navigate('/academic-offerings')
      }, 1000)
    } catch (error) {
      console.error('Error al crear oferta académica:', error)
      console.error('Respuesta del servidor:', error.response?.data)

      if (error.response?.data?.errors) {
        setErrores(error.response.data.errors)
      } else {
        setMensaje(
          error.response?.data?.message ||
          'Error al crear la oferta académica'
        )
      }
    }
  }

  return (
    <div>
      <h1>Nueva Oferta Académica</h1>

      {mensaje && <p role="status">{mensaje}</p>}

      <form onSubmit={handleSubmit}>
        <div>
          <label htmlFor="academic_unit_id">Unidad académica</label>

          <select
            id="academic_unit_id"
            name="academic_unit_id"
            value={formulario.academic_unit_id}
            onChange={handleChange}
            required
          >
            <option value="">Selecciona una unidad</option>

            {unidades.map((unidad) => (
              <option key={unidad.id} value={unidad.id}>
                {unidad.name}
              </option>
            ))}
          </select>

          {errores.academic_unit_id && (
            <p>{errores.academic_unit_id[0]}</p>
          )}
        </div>

        <div>
          <label htmlFor="academic_period_id">Período académico</label>

          <select
            id="academic_period_id"
            name="academic_period_id"
            value={formulario.academic_period_id}
            onChange={handleChange}
            required
          >
            <option value="">Selecciona un período</option>

            {periodos.map((periodo) => (
              <option key={periodo.id} value={periodo.id}>
                {periodo.name}
              </option>
            ))}
          </select>

          {errores.academic_period_id && (
            <p>{errores.academic_period_id[0]}</p>
          )}
        </div>

        <div>
          <label htmlFor="campus_id">Campus</label>

          <select
            id="campus_id"
            name="campus_id"
            value={formulario.campus_id}
            onChange={handleChange}
            required
          >
            <option value="">Selecciona un campus</option>

            {campus.map((item) => (
              <option key={item.id} value={item.id}>
                {item.name}
              </option>
            ))}
          </select>

          {errores.campus_id && (
            <p>{errores.campus_id[0]}</p>
          )}
        </div>

        <div>
          <label htmlFor="section">Sección</label>

          <input
            id="section"
            type="text"
            name="section"
            value={formulario.section}
            onChange={handleChange}
            maxLength={50}
            required
          />

          {errores.section && <p>{errores.section[0]}</p>}
        </div>

        <div>
          <label htmlFor="capacity">Capacidad</label>

          <input
            id="capacity"
            type="number"
            name="capacity"
            min="1"
            step="1"
            value={formulario.capacity}
            onChange={handleChange}
            required
          />

          {errores.capacity && <p>{errores.capacity[0]}</p>}
        </div>

        <div>
          <label>
            <input
              type="checkbox"
              name="is_active"
              checked={formulario.is_active}
              onChange={handleChange}
            />
            {' '}Activo
          </label>

          {errores.is_active && <p>{errores.is_active[0]}</p>}
        </div>

        <button type="submit">Crear oferta</button>
      </form>
    </div>
  )
}

export default CreateAcademicOffering
