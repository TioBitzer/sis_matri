
import { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import api from '../services/api'

function EditTeachingAssignment() {
  const { id } = useParams()

  const [formulario, setFormulario] = useState({
    teacher_id: '',
    academic_offering_id: '',
    role: '',
  })

  const [docentes, setDocentes] = useState([])
  const [ofertas, setOfertas] = useState([])
  const [cargando, setCargando] = useState(true)
  const [guardando, setGuardando] = useState(false)
  const [mensaje, setMensaje] = useState('')
  const [errores, setErrores] = useState({})

  useEffect(() => {
    const cargarDatos = async () => {
      try {
        const [
          asignacionResponse,
          docentesResponse,
          ofertasResponse,
        ] = await Promise.all([
          api.get(`/teaching-assignments/${id}`),
          api.get('/teachers'),
          api.get('/academic-offerings'),
        ])

        const asignacion = asignacionResponse.data.data

        setFormulario({
          teacher_id: String(asignacion.teacher_id ?? ''),
          academic_offering_id: String(
            asignacion.academic_offering_id ?? ''
          ),
          role: asignacion.role ?? '',
        })

        setDocentes(docentesResponse.data.data ?? [])
        setOfertas(ofertasResponse.data.data ?? [])
      } catch (error) {
        console.error(
          'Error al cargar los datos de la asignación docente:',
          error
        )

        setMensaje(
          error.response?.data?.message ||
          'No se pudo cargar la asignación, los docentes o las ofertas académicas.'
        )
      } finally {
        setCargando(false)
      }
    }

    cargarDatos()
  }, [id])

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

      const response = await api.put(
        `/teaching-assignments/${id}`,
        datos
      )

      setMensaje(
        response.data.message ||
        'Asignación docente actualizada correctamente.'
      )
    } catch (error) {
      console.error(
        'Error al actualizar la asignación docente:',
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
          'Ocurrió un error al actualizar la asignación docente.'
        )
      }
    } finally {
      setGuardando(false)
    }
  }

  if (cargando) {
    return <p>Cargando asignación docente...</p>
  }

  return (
    <div>
      <h1>Editar Asignación Docente</h1>

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

            {docentes.map((docente) => (
              <option
                key={docente.id}
                value={docente.id}
              >
                {docente.person
                  ? `${docente.person.first_name} ${docente.person.last_name}`
                  : `Docente ${docente.id}`}
              </option>
            ))}
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

              const detalles = [
                unidad?.name,
                oferta.section
                  ? `Sección ${oferta.section}`
                  : null,
                periodo?.name,
                oferta.campus?.name,
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
            ? 'Guardando...'
            : 'Guardar cambios'}
        </button>
      </form>
    </div>
  )
}

export default EditTeachingAssignment
