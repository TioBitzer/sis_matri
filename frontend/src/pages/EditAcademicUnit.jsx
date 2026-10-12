
import { useParams } from 'react-router-dom'
import { useState, useEffect } from 'react'
import api from '../services/api'

function EditAcademicUnit() {
  const { id } = useParams()

  const [formulario, setFormulario] = useState({
    institution_id: '',
    name: '',
    code: '',
    description: '',
    is_active: true,
  })

  const [instituciones, setInstituciones] = useState([])
  const [cargando, setCargando] = useState(true)
  const [mensaje, setMensaje] = useState('')
  const [errores, setErrores] = useState({})

  useEffect(() => {
    api.get(`/academic-units/${id}`)
      .then((response) => {
        const unidad = response.data.data

        setFormulario({
          institution_id: unidad.institution_id,
          name: unidad.name,
          code: unidad.code || '',
          description: unidad.description || '',
          is_active: unidad.is_active,
        })

        setCargando(false)
      })
      .catch((error) => {
        console.error(
          'Error al cargar unidad académica:',
          error
        )

        setCargando(false)
      })
  }, [id])

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
      const response = await api.put(
        `/academic-units/${id}`,
        formulario
      )

      console.log(response.data)

      setMensaje(
        'Unidad académica actualizada correctamente'
      )
    } catch (error) {
      console.error('Error completo:', error)
      console.error(
        'Respuesta del servidor:',
        error.response?.data
      )

      setMensaje(
        error.response?.data?.message ||
        'Error al actualizar la unidad académica'
      )

      setErrores(
        error.response?.data?.errors || {}
      )
    }
  }

  if (cargando) {
    return <p>Cargando unidad académica...</p>
  }

  return (
    <div>
      <h1>Editar Unidad Académica</h1>

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
          <label>Código</label>

          <input
            type="text"
            name="code"
            value={formulario.code}
            onChange={handleChange}
            maxLength="50"
          />

          {errores.code && (
            <p>{errores.code[0]}</p>
          )}
        </div>

        <div>
          <label>Descripción</label>

          <textarea
            name="description"
            value={formulario.description}
            onChange={handleChange}
          />

          {errores.description && (
            <p>{errores.description[0]}</p>
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
          Guardar cambios
        </button>
      </form>
    </div>
  )
}

export default EditAcademicUnit
