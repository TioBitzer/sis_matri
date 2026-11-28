
import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import api from '../services/api'

function CreateEnrollmentDetail() {
  const navigate = useNavigate()

  const [formulario, setFormulario] = useState({
    enrollment_id: '',
    academic_offering_id: '',
    status: '',
  })

  const [matriculas, setMatriculas] = useState([])
  const [ofertas, setOfertas] = useState([])
  const [cargandoOfertas, setCargandoOfertas] = useState(false)
  const [errorOfertas, setErrorOfertas] = useState("")
  const [errores, setErrores] = useState({})
  const [errorCarga, setErrorCarga] = useState('')
  const [cargando, setCargando] = useState(true)
  const [guardando, setGuardando] = useState(false)
  const [mensaje, setMensaje] = useState('')

  useEffect(() => {
    let activo = true

    const cargarDatos = async () => {
      try {
        const respuestaMatriculas = await api.get("/enrollments")

        if (activo) {
          setMatriculas(
            Array.isArray(respuestaMatriculas.data?.data)
              ? respuestaMatriculas.data.data
              : []
          )


        }
      } catch (error) {
        console.error(
          'Error al cargar los datos del formulario:',
          error
        )

        if (activo) {
          setErrorCarga(
            'No se pudieron cargar las matrículas o las ofertas académicas. Intenta nuevamente.'
          )
        }
      } finally {
        if (activo) {
          setCargando(false)
        }
      }
    }

    cargarDatos()

    return () => {
      activo = false
    }
  }, [])

  useEffect(() => {
    if (!formulario.enrollment_id) return

    let activo = true

    async function cargar() {
      try {
        const resultado = []
        let pagina = 1
        let ultima = 1

        do {
          const { data } = await api.get(
            "/enrollments/" + formulario.enrollment_id + "/available-offerings",
            { params: { per_page: 50, page: pagina } }
          )

          if (!activo) return
          resultado.push(...(data.data ?? []))
          ultima = data.meta?.last_page ?? 1
          pagina++
        } while (pagina <= ultima)

        if (activo) {
          setOfertas(resultado.filter(o => o.can_enroll))
        }
      } catch (error) {
        if (activo) {
          setErrorOfertas(error.response?.data?.message || "Error al consultar ofertas.")
        }
      } finally {
        if (activo) setCargandoOfertas(false)
      }
    }

    cargar()
    return () => { activo = false }
  }, [formulario.enrollment_id])

  const handleChange = (event) => {
    const { name, value } = event.target

    setFormulario((actual) => ({
      ...actual,
      [name]: value,
      ...(name === "enrollment_id" ? { academic_offering_id: "" } : {}),
    }))

    if (name === "enrollment_id") {
      setOfertas([])
      setErrorOfertas("")
      setCargandoOfertas(Boolean(value))
    }

    setErrores((actual) => ({
      ...actual,
      [name]: '',
      general: '',
    }))

    setMensaje('')
  }

  const handleSubmit = async (event) => {
    event.preventDefault()

    setMensaje('')
    setErrores({})

    const nuevosErrores = {}

    if (!formulario.enrollment_id) {
      nuevosErrores.enrollment_id =
        'Debes seleccionar una matrícula.'
    }

    if (!formulario.academic_offering_id) {
      nuevosErrores.academic_offering_id =
        'Debes seleccionar una oferta académica.'
    }

    if (!formulario.status.trim()) {
      nuevosErrores.status =
        'Debes seleccionar un estado.'
    } else if (formulario.status.length > 50) {
      nuevosErrores.status =
        'El estado no puede superar los 50 caracteres.'
    }

    if (Object.keys(nuevosErrores).length > 0) {
      setErrores(nuevosErrores)
      return
    }

    setGuardando(true)

    try {
      await api.post('/enrollment-details', {
        enrollment_id: Number(formulario.enrollment_id),
        academic_offering_id: Number(
          formulario.academic_offering_id
        ),
        status: formulario.status,
      })

      navigate('/enrollment-details', {
        state: {
          mensaje: 'Detalle de matrícula creado correctamente.',
        },
      })
    } catch (error) {
      console.error(
        'Error al crear el detalle de matrícula:',
        error
      )

      const erroresServidor = error.response?.data?.errors

      if (erroresServidor) {
        const erroresFormateados = {}

        Object.entries(erroresServidor).forEach(
          ([campo, mensajes]) => {
            erroresFormateados[campo] = Array.isArray(mensajes)
              ? mensajes[0]
              : mensajes
          }
        )

        setErrores(erroresFormateados)
      } else {
        setErrores({
          general:
            error.response?.data?.message ||
            'No se pudo crear el detalle de matrícula. Intenta nuevamente.',
        })
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
      <h1>Nuevo detalle de matrícula</h1>

      {errorCarga && (
        <p role="alert">{errorCarga}</p>
      )}

      {errores.general && (
        <p role="alert">{errores.general}</p>
      )}

      {mensaje && (
        <p role="status">{mensaje}</p>
      )}

      <form onSubmit={handleSubmit} noValidate>
        <div>
          <label htmlFor="enrollment_id">
            Matrícula
          </label>

          <select
            id="enrollment_id"
            name="enrollment_id"
            value={formulario.enrollment_id}
            onChange={handleChange}
            required
            disabled={!!errorCarga || guardando}
            aria-invalid={!!errores.enrollment_id}
          >
            <option value="">
              Selecciona una matrícula
            </option>

            {matriculas.map((matricula) => {
              const persona =
                matricula.student?.person ??
                matricula.student?.Person

              const periodo =
                matricula.academic_period ??
                matricula.academicPeriod

              const nombre = persona
                ? [
                    persona.first_name ?? persona.firstName,
                    persona.last_name ?? persona.lastName,
                  ]
                    .filter(Boolean)
                    .join(' ')
                : `Matrícula ${matricula.id}`

              return (
                <option
                  key={matricula.id}
                  value={matricula.id}
                >
                  {nombre}
                  {' - '}
                  {periodo?.name || 'Sin período'}
                </option>
              )
            })}
          </select>

          {errores.enrollment_id && (
            <p role="alert">{errores.enrollment_id}</p>
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
            disabled={!!errorCarga || guardando}
            aria-invalid={!!errores.academic_offering_id}
          >
            <option value="">
              Selecciona una oferta académica
            </option>

            {ofertas.map((oferta) => {
              const unidad =
                oferta.academic_unit ??
                oferta.academicUnit



              const campus = oferta.campus

              return (
                <option
                  key={oferta.id}
                  value={oferta.id}
                >
                  {unidad?.name || 'Sin curso'}
                  {' - '}
                  {oferta.section || 'Sin sección'}
                  {' - '}
                  {oferta.remaining === null ? "Sin limite" : oferta.remaining + " vacantes"}
                  {campus?.name ? ` - ${campus.name}` : ''}
                </option>
              )
            })}
          </select>

          {cargandoOfertas && <p>Consultando vacantes...</p>}
          {errorOfertas && <p role="alert">{errorOfertas}</p>}
          {formulario.enrollment_id && !cargandoOfertas && !errorOfertas && ofertas.length === 0 && (
            <p>No hay cursos disponibles.</p>
          )}

          {errores.academic_offering_id && (
            <p role="alert">
              {errores.academic_offering_id}
            </p>
          )}
        </div>

        <div>
          <label htmlFor="status">
            Estado
          </label>

          <select
            id="status"
            name="status"
            value={formulario.status}
            onChange={handleChange}
            required
            disabled={guardando}
            aria-invalid={!!errores.status}
          >
            <option value="">
              Selecciona un estado
            </option>

            <option value="active">Activo</option>
            <option value="completed">Completado</option>
            <option value="cancelled">Cancelado</option>
          </select>

          {errores.status && (
            <p role="alert">{errores.status}</p>
          )}
        </div>

        <button
          type="submit"
          disabled={guardando || !!errorCarga || cargandoOfertas || !!errorOfertas || !formulario.academic_offering_id}
        >
          {guardando ? 'Guardando...' : 'Crear detalle'}
        </button>

        <button
          type="button"
          onClick={() => navigate('/enrollment-details')}
          disabled={guardando}
        >
          Cancelar
        </button>
      </form>
    </div>
  )
}

export default CreateEnrollmentDetail
