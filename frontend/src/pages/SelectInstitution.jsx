
import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import api from '../services/api'

function SelectInstitution() {
  const navigate = useNavigate()
  const [institutions, setInstitutions] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    let active = true

    api.get('/me/institutions')
      .then((response) => {
        if (active) {
          setInstitutions(response.data.data ?? [])
        }
      })
      .catch((err) => {
        if (active) {
          setError(
            err.response?.data?.message ||
            'No se pudieron cargar tus instituciones.'
          )
        }
      })
      .finally(() => {
        if (active) setLoading(false)
      })

    return () => {
      active = false
    }
  }, [])


const seleccionar = (institution) => {
  sessionStorage.setItem(
    'sis_matri_institution_id',
    String(institution.id)
  )

  navigate('/', { replace: true })
}


  return (
    <main>
      <h1>Seleccionar institución</h1>
      <p>Elige la institución con la que deseas trabajar.</p>

      {loading && <p>Cargando instituciones...</p>}

      {error && <p role="alert">{error}</p>}

      {!loading && !error && institutions.length === 0 && (
        <p>No tienes instituciones activas asignadas.</p>
      )}

      {!loading && !error && institutions.map((institution) => (
        <div key={institution.id}>
          <h2>{institution.name}</h2>
          <p>Código: {institution.code}</p>
          <p>Rol: {institution.role}</p>

          <button
            type="button"
            onClick={() => seleccionar(institution)}
          >
            Seleccionar
          </button>
        </div>
      ))}
    </main>
  )
}

export default SelectInstitution
