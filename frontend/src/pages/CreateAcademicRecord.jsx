
import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import api from '../services/api'

function CreateAcademicRecord() {
  const navigate = useNavigate()

  const [formulario, setFormulario] = useState({
    student_id: '',
    academic_program_id: '',
    academic_plan_id: '',
    start_date: '',
    end_date: '',
    status: '',
  })

  const [estudiantes, setEstudiantes] = useState([])
  const [programas, setProgramas] = useState([])
  const [planes, setPlanes] = useState([])
  const [errores, setErrores] = useState({})
  const [mensaje, setMensaje] = useState('')
  const [cargando, setCargando] = useState(true)
  const [guardando, setGuardando] = useState(false)

  useEffect(() => {
    const cargarDatos = async () => {
      try {
        const [estudiantesRes, programasRes, planesRes] =
          await Promise.all([
            api.get('/students'),
            api.get('/academic-programs'),
            api.get('/academic-plans'),
          ])

        setEstudiantes(estudiantesRes.data.data ?? [])
        setProgramas(programasRes.data.data ?? [])
        setPlanes(planesRes.data.data ?? [])
      } catch (error) {
        console.error('Error al cargar los datos:', error)

        setMensaje(
          error.response?.data?.message ||
            'No se pudieron cargar los datos necesarios. Recarga la página e inténtalo nuevamente.'
        )
      } finally {
        setCargando(false)
      }
    }

    cargarDatos()
  }, [])

  const handleChange = (event) => {
    const { name, value } = event.target

    setFormulario((previo) => ({
      ...previo,
      [name]: value,
    }))

    setErrores((previos) => ({
      ...previos,
      [name]: '',
    }))

    setMensaje('')
  }

  const validarFormulario = () => {
    const nuevosErrores = {}

    if (!formulario.student_id) {
      nuevosErrores.student_id = 'Selecciona un estudiante.'
    }

    if (!formulario.academic_program_id) {
      nuevosErrores.academic_program_id =
        'Selecciona un programa académico.'
    }

    if (!formulario.academic_plan_id) {
      nuevosErrores.academic_plan_id =
        'Selecciona un plan académico.'
    }

    if (!formulario.start_date) {
      nuevosErrores.start_date =
        'La fecha de inicio es obligatoria.'
    }

    if (
      formulario.end_date &&
      formulario.start_date &&
      formulario.end_date < formulario.start_date
    ) {
      nuevosErrores.end_date =
        'La fecha de fin debe ser igual o posterior a la fecha de inicio.'
    }

    if (!formulario.status.trim()) {
      nuevosErrores.status = 'Selecciona un estado.'
    } else if (formulario.status.length > 50) {
      nuevosErrores.status =
        'El estado no puede superar los 50 caracteres.'
    }

    setErrores(nuevosErrores)

    return Object.keys(nuevosErrores).length === 0
  }

  const handleSubmit = async (event) => {
    event.preventDefault()
    setMensaje('')

    if (!validarFormulario()) {
      setMensaje('Revisa los campos señalados.')
      return
    }

    setGuardando(true)
    setErrores({})

    const datos = {
      ...formulario,
      student_id: Number(formulario.student_id),
      academic_program_id: Number(formulario.academic_program_id),
      academic_plan_id: Number(formulario.academic_plan_id),
      end_date: formulario.end_date || null,
    }

    try {
      await api.post('/academic-records', datos)

      navigate('/academic-records', {
        state: {
          mensaje: 'Registro académico creado correctamente.',
        },
      })
    } catch (error) {
      console.error(
        'Error al crear el registro académico:',
        error.response?.data || error
      )

      const erroresServidor = error.response?.data?.errors

      if (erroresServidor) {
        const erroresFormateados = {}

        Object.entries(erroresServidor).forEach(([campo, mensajes]) => {
          erroresFormateados[campo] = Array.isArray(mensajes)
            ? mensajes[0]
            : mensajes
        })

        setErrores(erroresFormateados)
        setMensaje('Revisa los campos señalados.')
      } else {
        setMensaje(
          error.response?.data?.message ||
            'Error al crear el registro académico. Inténtalo nuevamente.'
        )
      }
    } finally {
      setGuardando(false)
    }
  }

  if (cargando) {
    return <p>Cargando datos del formulario...</p>
  }

  return (
    <div>
      <h1>Nuevo Registro Académico</h1>

      {mensaje && (
        <p role="alert">
          {mensaje}
        </p>
      )}

      <form onSubmit={handleSubmit} noValidate>
        <div>
          <label htmlFor="student_id">Estudiante</label>

          <select
            id="student_id"
            name="student_id"
            value={formulario.student_id}
            onChange={handleChange}
            required
            aria-invalid={Boolean(errores.student_id)}
          >
            <option value="">Selecciona un estudiante</option>

            {estudiantes.map((estudiante) => (
              <option key={estudiante.id} value={estudiante.id}>
                {estudiante.person
                  ? `${estudiante.person.first_name} ${estudiante.person.last_name}`
                  : `Estudiante ${estudiante.id}`}
              </option>
            ))}
          </select>

          {errores.student_id && (
            <p role="alert">{errores.student_id}</p>
          )}
        </div>

        <div>
          <label htmlFor="academic_program_id">
            Programa académico
          </label>

          <select
            id="academic_program_id"
            name="academic_program_id"
            value={formulario.academic_program_id}
            onChange={handleChange}
            required
            aria-invalid={Boolean(errores.academic_program_id)}
          >
            <option value="">Selecciona un programa</option>

            {programas.map((programa) => (
              <option key={programa.id} value={programa.id}>
                {programa.name}
              </option>
            ))}
          </select>

          {errores.academic_program_id && (
            <p role="alert">{errores.academic_program_id}</p>
          )}
        </div>

        <div>
          <label htmlFor="academic_plan_id">Plan académico</label>

          <select
            id="academic_plan_id"
            name="academic_plan_id"
            value={formulario.academic_plan_id}
            onChange={handleChange}
            required
            aria-invalid={Boolean(errores.academic_plan_id)}
          >
            <option value="">Selecciona un plan</option>

            {planes.map((plan) => (
              <option key={plan.id} value={plan.id}>
                {plan.name}
              </option>
            ))}
          </select>

          {errores.academic_plan_id && (
            <p role="alert">{errores.academic_plan_id}</p>
          )}
        </div>

        <div>
          <label htmlFor="start_date">Fecha de inicio</label>

          <input
            id="start_date"
            type="date"
            name="start_date"
            value={formulario.start_date}
            onChange={handleChange}
            required
            aria-invalid={Boolean(errores.start_date)}
          />

          {errores.start_date && (
            <p role="alert">{errores.start_date}</p>
          )}
        </div>

        <div>
          <label htmlFor="end_date">Fecha de fin (opcional)</label>

          <input
            id="end_date"
            type="date"
            name="end_date"
            value={formulario.end_date}
            onChange={handleChange}
            min={formulario.start_date || undefined}
            aria-invalid={Boolean(errores.end_date)}
          />

          {errores.end_date && (
            <p role="alert">{errores.end_date}</p>
          )}
        </div>

        <div>
          <label htmlFor="status">Estado</label>

          <select
            id="status"
            name="status"
            value={formulario.status}
            onChange={handleChange}
            required
            aria-invalid={Boolean(errores.status)}
          >
            <option value="">Selecciona un estado</option>
            <option value="Activo">Activo</option>
            <option value="Finalizado">Finalizado</option>
            <option value="Retirado">Retirado</option>
            <option value="Suspendido">Suspendido</option>
          </select>

          {errores.status && (
            <p role="alert">{errores.status}</p>
          )}
        </div>

        <button type="submit" disabled={guardando}>
          {guardando ? 'Creando...' : 'Crear registro'}
        </button>
      </form>
    </div>
  )
}

export default CreateAcademicRecord
