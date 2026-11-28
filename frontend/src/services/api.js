
import axios from 'axios'

const config = {
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
  },
}

// Conexión con los módulos del sistema
const api = axios.create({
  baseURL: 'http://localhost:8000/api',
  ...config,
})

// Recuperar la institución seleccionada
// cada vez que React realice una petición
api.interceptors.request.use((request) => {
  const institutionId = sessionStorage.getItem(
    'sis_matri_institution_id'
  )

  const noRequiereInstitucion =
    request.url === '/user' ||
    request.url === '/me/institutions'

  if (institutionId && !noRequiereInstitucion) {
    request.headers.set(
      'X-Institution-Id',
      institutionId
    )
  } else {
    request.headers.delete('X-Institution-Id')
  }

  return request
})

// Conexión con la autenticación Laravel Sanctum
export const authApi = axios.create({
  baseURL: 'http://localhost:8000',
  ...config,
})

export default api
