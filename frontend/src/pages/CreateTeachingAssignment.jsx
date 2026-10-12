
import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import api from '../services/api'

function CreateTeachingAssignment() {
  const navigate = useNavigate()

  const [formulario, setFormulario] = useState({
    teacher_id: '',
    academic_offering_id: '',
    role: '',
  })

  const [docentes, setDocentes] = useState([])
  const [ofertas, setOfertas] = useState([])
  const [mensaje, setMensaje] = useState('')
  const [errores, setErrores] = useState({})
  const [cargandoOpciones, setCargandoOpciones] = useState(true)
  const [guardando, setGuardando] = useState(false)

  useEffect(() => {
    const cargarOpciones = async () => {
      try {
        const [docentesResponse, ofertasResponse] =
          await Promise.all([
            api.get('/teachers'),
            api.get('/academic-offerings'),
          ])

        setDocentes(docentesResponse.data.data ?? [])
        setOfertas(ofertasResponse.data.data ?? [])
      } catch (error) {
        console.error(
          'Error al cargar docentes u ofertas académicas:',
          error
        )

        setMensaje(
          'No se pudieron cargar los docentes o las ofertas académicas. Intenta nuevamente.'
        )
      } finally {
        setCargandoOpciones(false)
      }
    }

    cargarOpciones()
  }, [])

  const handleChange = (event) => {
    const { name, value } = event.target

    setFormulario((anterior) => ({
      ...anterior,
      [name]: value,
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
        teacher_id: Number(formulario.teacher_id),
        academic_offering_id: Number(
          formulario.academic_offering_id
        ),
        role: formulario.role.trim() || null,
      }

      await api.post('/teaching-assignments', datos)

      setMensaje(
        'Asignación docente creada correctamente.'
      )

      navigate('/teaching-assignments', {
        state: {
          mensaje: 'Asignación docente creada correctamente.',
        },
      })
    } catch (error) {
      console.error(
        'Error al crear la asignación docente:',
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
          'Revisa los campos indicados antes de continuar.'
        )
      } else {
        setMensaje(
          error.response?.data?.message ||
          'Ocurrió un error al crear la asignación docente.'
        )
      }
    } finally {
      setGuardando(false)
    }
  }

  if (cargandoOpciones) {
    return <p>Cargando docentes y ofertas académicas...</p>
  }

  return (
    <div>
      <h1>Nueva Asignación Docente</h1>

      {mensaje && (
        <p role="alert">{mensaje}</p>
      )}

      <form onSubmit={handleSubmit}>
        <div>
          <label htmlFor="teacher_id">
            Docente
          </label>

          <select
            id="teacher_id"
            name="teacher_id"
            value={formulario.teacher_id}
            onChange={handleChange}
            required
            aria-invalid={Boolean(errores.teacher_id)}
          >
            <option value="">
              Selecciona un docente
            </option>

            {docentes.map((docente) => {
              const persona = docente.person

              return (
                <option
                  key={docente.id}
                  value={docente.id}
                >
                  {persona
                    ? `${persona.first_name} ${persona.last_name}`
                    : `Docente ${docente.id}`}
                </option>
              )
            })}
          </select>

          {errores.teacher_id && (
            <p role="alert">{errores.teacher_id}</p>
          )}
        </div>

        <div>
          <label htmlFor="academic_offering_id">
            Oferta académica
          </label>

          <select
            id="academic_offering_id"
            name="academic_offering_id"
            value={formulario.academic_offering_id}
            onChange={handleChange}
            required
            aria-invalid={Boolean(
              errores.academic_offering_id
            )}
          >
            <option value="">
              Selecciona una oferta
            </option>

            {ofertas.map((oferta) => {
              const unidad =
                oferta.academic_unit ??
                oferta.academicUnit

              const periodo =
                oferta.academic_period ??
                oferta.academicPeriod

              const campusNombre =
                oferta.campus?.name

              const detalles = [
                unidad?.name,
                oferta.section
                  ? `Sección ${oferta.section}`
                  : null,
                periodo?.name,
                campusNombre,
              ].filter(Boolean)

              return (
                <option
                  key={oferta.id}
                  value={oferta.id}
                >
                  {detalles.length > 0
                    ? detalles.join(' - ')
                    : `Oferta ${oferta.id}`}
                </option>
              )
            })}
          </select>

          {errores.academic_offering_id && (
            <p role="alert">
              {errores.academic_offering_id}
            </p>
          )}
        </div>

        <div>
          <label htmlFor="role">
            Rol (opcional)
          </label>

          <input
            id="role"
            type="text"
            name="role"
            value={formulario.role}
            onChange={handleChange}
            maxLength={100}
            placeholder="Ejemplo: Docente principal"
            aria-invalid={Boolean(errores.role)}
          />

          {errores.role && (
            <p role="alert">{errores.role}</p>
          )}
        </div>

        <button
          type="submit"
          disabled={guardando}
        >
          {guardando
            ? 'Creando...'
            : 'Crear asignación'}
        </button>
      </form>
    </div>
  )
}

export default CreateTeachingAssignment
