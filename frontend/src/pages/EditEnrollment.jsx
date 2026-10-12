
import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import api from '../services/api'

function EditEnrollment() {
  const { id } = useParams()
  const navigate = useNavigate()

  const [formulario, setFormulario] = useState({
    student_id: '',
    academic_period_id: '',
    academic_record_id: '',
    enrollment_date: '',
    status: '',
  })

  const [estudiantes, setEstudiantes] = useState([])
  const [periodos, setPeriodos] = useState([])
  const [registrosAcademicos, setRegistrosAcademicos] = useState([])
  const [cargando, setCargando] = useState(true)
  const [guardando, setGuardando] = useState(false)
  const [errores, setErrores] = useState({})
  const [mensaje, setMensaje] = useState('')

  useEffect(() => {
    const cargarDatos = async () => {
      setCargando(true)
      setMensaje('')

      try {
        const [
          matriculaRes,
          estudiantesRes,
          periodosRes,
          registrosRes,
        ] = await Promise.all([
          api.get(`/enrollments/${id}`),
          api.get('/students'),
          api.get('/academic-periods'),
          api.get('/academic-records'),
        ])

        const matricula = matriculaRes.data.data

        setFormulario({
          student_id: String(matricula.student_id ?? ''),
          academic_period_id: String(
            matricula.academic_period_id ?? ''
          ),
          academic_record_id: String(
            matricula.academic_record_id ?? ''
          ),
          enrollment_date: matricula.enrollment_date
            ? String(matricula.enrollment_date).slice(0, 10)
            : '',
          status: matricula.status ?? '',
        })

        setEstudiantes(estudiantesRes.data.data ?? [])
        setPeriodos(periodosRes.data.data ?? [])
        setRegistrosAcademicos(registrosRes.data.data ?? [])
      } catch (error) {
        console.error(
          'Error al cargar los datos de la matrícula:',
          error.response?.data || error
        )

        setMensaje(
          error.response?.data?.message ||
            'No se pudieron cargar los datos de la matrícula. Recarga la página e inténtalo nuevamente.'
        )
      } finally {
        setCargando(false)
      }
    }

    cargarDatos()
  }, [id])

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

    if (!formulario.academic_period_id) {
      nuevosErrores.academic_period_id =
        'Selecciona un período académico.'
    }

    if (!formulario.academic_record_id) {
      nuevosErrores.academic_record_id =
        'Selecciona un registro académico.'
    }

    if (!formulario.enrollment_date) {
      nuevosErrores.enrollment_date =
        'La fecha de matrícula es obligatoria.'
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
      academic_period_id: Number(formulario.academic_period_id),
      academic_record_id: Number(formulario.academic_record_id),
    }

    try {
      await api.put(`/enrollments/${id}`, datos)

      navigate('/enrollments', {
        state: {
          mensaje: 'Matrícula actualizada correctamente.',
        },
      })
    } catch (error) {
      console.error(
        'Error al actualizar la matrícula:',
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
            'Error al actualizar la matrícula. Inténtalo nuevamente.'
        )
      }
    } finally {
      setGuardando(false)
    }
  }

  if (cargando) {
    return <p>Cargando matrícula...</p>
  }

  return (
    <div>
      <h1>Editar Matrícula</h1>

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
          <label htmlFor="academic_record_id">
            Registro académico
          </label>

          <select
            id="academic_record_id"
            name="academic_record_id"
            value={formulario.academic_record_id}
            onChange={handleChange}
            required
            aria-invalid={Boolean(errores.academic_record_id)}
          >
            <option value="">Selecciona un registro académico</option>

            {registrosAcademicos.map((registro) => {
              const persona = registro.student?.person
              const nombreEstudiante = persona
                ? `${persona.first_name} ${persona.last_name}`
                : `Estudiante ${registro.student_id}`

              const programa =
                registro.academic_program?.name ||
                registro.academicProgram?.name

              const plan =
                registro.academic_plan?.name ||
                registro.academicPlan?.name

              return (
                <option key={registro.id} value={registro.id}>
                  {`Registro ${registro.id} - ${nombreEstudiante}${
                    programa ? ` - ${programa}` : ''
                  }${plan ? ` - ${plan}` : ''}`}
                </option>
              )
            })}
          </select>

          {errores.academic_record_id && (
            <p role="alert">{errores.academic_record_id}</p>
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
            <option value="">Selecciona un período</option>

            {periodos.map((periodo) => (
              <option key={periodo.id} value={periodo.id}>
                {periodo.name}
              </option>
            ))}
          </select>

          {errores.academic_period_id && (
            <p role="alert">{errores.academic_period_id}</p>
          )}
        </div>

        <div>
          <label htmlFor="enrollment_date">
            Fecha de matrícula
          </label>

          <input
            id="enrollment_date"
            type="date"
            name="enrollment_date"
            value={formulario.enrollment_date}
            onChange={handleChange}
            required
            aria-invalid={Boolean(errores.enrollment_date)}
          />

          {errores.enrollment_date && (
            <p role="alert">{errores.enrollment_date}</p>
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
            <option value="Cancelado">Cancelado</option>
          </select>

          {errores.status && (
            <p role="alert">{errores.status}</p>
          )}
        </div>

        <button type="submit" disabled={guardando}>
          {guardando ? 'Guardando...' : 'Guardar cambios'}
        </button>
      </form>
    </div>
  )
}

export default EditEnrollment
