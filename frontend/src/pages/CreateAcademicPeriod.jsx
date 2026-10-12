
import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import api from '../services/api'

function CreateAcademicPeriod() {
  const navigate = useNavigate()

  const [formulario, setFormulario] = useState({
    institution_id: '',
    name: '',
    start_date: '',
    end_date: '',
    is_active: true,
  })

  const [instituciones, setInstituciones] = useState([])
  const [mensaje, setMensaje] = useState('')
  const [errores, setErrores] = useState({})

  useEffect(() => {
    api.get('/institutions')
      .then((response) => {
        setInstituciones(response.data.data)
      })
      .catch((error) => {
        console.error(
          'Error al cargar instituciones:',
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
  }

  const handleSubmit = async (event) => {
    event.preventDefault()

    setMensaje('')
    setErrores({})

    try {
      const response = await api.post(
        '/academic-periods',
        formulario
      )

      console.log(response.data)

      setMensaje(
        'Período académico creado correctamente'
      )

      setTimeout(() => {
        navigate('/academic-periods')
      }, 1000)
    } catch (error) {
      console.error('Error completo:', error)
      console.error(
        'Respuesta del servidor:',
        error.response?.data
      )

      setMensaje(
        error.response?.data?.message ||
        'Error al crear el período académico'
      )

      setErrores(
        error.response?.data?.errors || {}
      )
    }
  }

  return (
    <div>
      <h1>Nuevo Período Académico</h1>

      {mensaje && <p>{mensaje}</p>}

      <form onSubmit={handleSubmit}>
        <div>
          <label>Institución</label>

          <select
            name="institution_id"
            value={formulario.institution_id}
            onChange={handleChange}
            required
          >
            <option value="">
              Selecciona una institución
            </option>

            {instituciones.map((institucion) => (
              <option
                key={institucion.id}
                value={institucion.id}
              >
                {institucion.name}
              </option>
            ))}
          </select>

          {errores.institution_id && (
            <p>{errores.institution_id[0]}</p>
          )}
        </div>

        <div>
          <label>Nombre</label>

          <input
            type="text"
            name="name"
            value={formulario.name}
            onChange={handleChange}
            required
          />

          {errores.name && (
            <p>{errores.name[0]}</p>
          )}
        </div>

        <div>
          <label>Fecha de inicio</label>

          <input
            type="date"
            name="start_date"
            value={formulario.start_date}
            onChange={handleChange}
            required
          />

          {errores.start_date && (
            <p>{errores.start_date[0]}</p>
          )}
        </div>

        <div>
          <label>Fecha de fin</label>

          <input
            type="date"
            name="end_date"
            value={formulario.end_date}
            onChange={handleChange}
            required
          />

          {errores.end_date && (
            <p>{errores.end_date[0]}</p>
          )}
        </div>

        <div>
          <label>Estado</label>

          <select
            name="is_active"
            value={formulario.is_active}
            onChange={handleChange}
          >
            <option value="true">Activo</option>
            <option value="false">Inactivo</option>
          </select>

          {errores.is_active && (
            <p>{errores.is_active[0]}</p>
          )}
        </div>

        <button type="submit">
          Crear período
        </button>
      </form>
    </div>
  )
}

export default CreateAcademicPeriod
