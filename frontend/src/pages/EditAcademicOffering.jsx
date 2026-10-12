
import { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import api from '../services/api'

function EditAcademicOffering() {
  const { id } = useParams()

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
  const [cargando, setCargando] = useState(true)
  const [guardando, setGuardando] = useState(false)
  const [mensaje, setMensaje] = useState('')
  const [errores, setErrores] = useState({})

  useEffect(() => {
    const cargarOferta = async () => {
      try {
        const response = await api.get(
          `/academic-offerings/${id}`
        )

        const oferta = response.data.data

        setFormulario({
          academic_unit_id: String(
            oferta.academic_unit_id ?? ''
          ),
          academic_period_id: String(
            oferta.academic_period_id ?? ''
          ),
          campus_id: String(
            oferta.campus_id ?? ''
          ),
          section: oferta.section ?? '',
          capacity: String(oferta.capacity ?? ''),
          is_active:
            oferta.is_active === true ||
            oferta.is_active === 1 ||
            oferta.is_active === '1',
        })
      } catch (error) {
        console.error(
          'Error al cargar oferta académica:',
          error
        )

        setMensaje(
          error.response?.data?.message ||
          'No se pudo cargar la oferta académica.'
        )
      } finally {
        setCargando(false)
      }
    }

    cargarOferta()
  }, [id])

  useEffect(() => {
    const cargarOpciones = async () => {
      try {
        const [
          unidadesResponse,
          periodosResponse,
          campusResponse,
        ] = await Promise.all([
          api.get('/academic-units'),
          api.get('/academic-periods'),
          api.get('/campuses'),
        ])

        setUnidades(unidadesResponse.data.data ?? [])
        setPeriodos(periodosResponse.data.data ?? [])
        setCampus(campusResponse.data.data ?? [])
      } catch (error) {
        console.error(
          'Error al cargar las opciones:',
          error
        )

        setMensaje(
          'No se pudieron cargar las unidades, períodos o campus.'
        )
      }
    }

    cargarOpciones()
  }, [])

  const handleChange = (event) => {
    const { name, value, type, checked } = event.target

    setFormulario((anterior) => ({
      ...anterior,
      [name]: type === 'checkbox' ? checked : value,
    }))

    setErrores((anteriores) => ({
      ...anteriores,
      [name]: '',
    }))

    setMensaje('')
  }

  const handleSubmit = async (event) => {
    event.preventDefault()

    setMensaje('')
    setErrores({})
    setGuardando(true)

    try {
      const datos = {
        ...formulario,
        academic_unit_id: Number(formulario.academic_unit_id),
        academic_period_id: Number(formulario.academic_period_id),
        campus_id: Number(formulario.campus_id),
        capacity: Number(formulario.capacity),
      }

      const response = await api.put(
        `/academic-offerings/${id}`,
        datos
      )

      setMensaje(
        response.data.message ||
        'Oferta académica actualizada correctamente.'
      )
    } catch (error) {
      console.error(
        'Error al actualizar la oferta académica:',
        error
      )

      const erroresServidor = error.response?.data?.errors

      if (erroresServidor) {
        const erroresFormulario = {}

        Object.entries(erroresServidor).forEach(
          ([campo, mensajes]) => {
            erroresFormulario[campo] = mensajes[0]
          }
        )

        setErrores(erroresFormulario)
        setMensaje(
          'Revisa los campos indicados antes de guardar.'
        )
      } else {
        setMensaje(
          error.response?.data?.message ||
          'Ocurrió un error al actualizar la oferta académica.'
        )
      }
    } finally {
      setGuardando(false)
    }
  }

  if (cargando) {
    return <p>Cargando oferta académica...</p>
  }

  return (
    <div>
      <h1>Editar Oferta Académica</h1>

      {mensaje && (
        <p role="alert">{mensaje}</p>
      )}

      <form onSubmit={handleSubmit}>
        <div>
          <label htmlFor="academic_unit_id">
            Unidad académica
          </label>

          <select
            id="academic_unit_id"
            name="academic_unit_id"
            value={formulario.academic_unit_id}
            onChange={handleChange}
            required
            aria-invalid={Boolean(errores.academic_unit_id)}
          >
            <option value="">
              Selecciona una unidad académica
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
            <p role="alert">
              {errores.academic_unit_id}
            </p>
          )}
        </div>

        <div>
          <label htmlFor="academic_period_id">
            Período académico
          </label>

          <select
            id="academic_period_id"
            name="academic_period_id"
            value={formulario.academic_period_id}
            onChange={handleChange}
            required
            aria-invalid={Boolean(errores.academic_period_id)}
          >
            <option value="">
              Selecciona un período académico
            </option>

            {periodos.map((periodo) => (
              <option
                key={periodo.id}
                value={periodo.id}
              >
                {periodo.name}
              </option>
            ))}
          </select>

          {errores.academic_period_id && (
            <p role="alert">
              {errores.academic_period_id}
            </p>
          )}
        </div>

        <div>
          <label htmlFor="campus_id">
            Campus
          </label>

          <select
            id="campus_id"
            name="campus_id"
            value={formulario.campus_id}
            onChange={handleChange}
            required
            aria-invalid={Boolean(errores.campus_id)}
          >
            <option value="">
              Selecciona un campus
            </option>

            {campus.map((item) => (
              <option
                key={item.id}
                value={item.id}
              >
                {item.name}
              </option>
            ))}
          </select>

          {errores.campus_id && (
            <p role="alert">
              {errores.campus_id}
            </p>
          )}
        </div>

        <div>
          <label htmlFor="section">
            Sección
          </label>

          <input
            id="section"
            type="text"
            name="section"
            value={formulario.section}
            onChange={handleChange}
            maxLength={50}
            required
            aria-invalid={Boolean(errores.section)}
          />

          {errores.section && (
            <p role="alert">
              {errores.section}
            </p>
          )}
        </div>

        <div>
          <label htmlFor="capacity">
            Capacidad
          </label>

          <input
            id="capacity"
            type="number"
            name="capacity"
            min="1"
            step="1"
            value={formulario.capacity}
            onChange={handleChange}
            required
            aria-invalid={Boolean(errores.capacity)}
          />

          {errores.capacity && (
            <p role="alert">
              {errores.capacity}
            </p>
          )}
        </div>

        <div>
          <label htmlFor="is_active">
            <input
              id="is_active"
              type="checkbox"
              name="is_active"
              checked={formulario.is_active}
              onChange={handleChange}
            />
            {' '}Activo
          </label>

          {errores.is_active && (
            <p role="alert">
              {errores.is_active}
            </p>
          )}
        </div>

        <button
          type="submit"
          disabled={guardando}
        >
          {guardando
            ? 'Guardando...'
            : 'Guardar cambios'}
        </button>
      </form>
    </div>
  )
}

export default EditAcademicOffering
