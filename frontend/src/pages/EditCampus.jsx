
import { useParams } from 'react-router-dom'
import { useState, useEffect } from 'react'
import api from '../services/api'

function EditCampus() {
  const { id } = useParams()

  const [formulario, setFormulario] = useState({
    institution_id: '',
    name: '',
    code: '',
    address: '',
    is_active: true,
  })

  const [instituciones, setInstituciones] = useState([])
  const [cargando, setCargando] = useState(true)
  const [mensaje, setMensaje] = useState('')
  const [errores, setErrores] = useState({})

  useEffect(() => {
    api.get(`/campuses/${id}`)
      .then((response) => {
        const campus = response.data.data

        setFormulario({
          institution_id: campus.institution_id,
          name: campus.name,
          code: campus.code || '',
          address: campus.address || '',
          is_active: campus.is_active,
        })

        setCargando(false)
      })
      .catch((error) => {
        console.error(
          'Error al cargar campus:',
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
        `/campuses/${id}`,
        formulario
      )

      console.log(response.data)

      setMensaje(
        'Campus actualizado correctamente'
      )
    } catch (error) {
      console.error('Error completo:', error)

      console.error(
        'Respuesta del servidor:',
        error.response?.data
      )

      setMensaje(
        error.response?.data?.message ||
        'Error al actualizar el campus'
      )

      setErrores(
        error.response?.data?.errors || {}
      )
    }
  }

  if (cargando) {
    return <p>Cargando campus...</p>
  }

  return (
    <div>
      <h1>Editar Campus</h1>

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
          <label>Dirección</label>

          <input
            type="text"
            name="address"
            value={formulario.address}
            onChange={handleChange}
            maxLength="255"
          />

          {errores.address && (
            <p>{errores.address[0]}</p>
          )}
        </div>

        <div>
          <label>Estado</label>

          <select
            name="is_active"
            value={formulario.is_active}
            onChange={handleChange}
          >
            <option value="true">
              Activo
            </option>

            <option value="false">
              Inactivo
            </option>
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

export default EditCampus
