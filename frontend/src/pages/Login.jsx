
import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { authApi } from '../services/api'

function Login() {
  const navigate = useNavigate()

  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  const handleSubmit = async (event) => {
    event.preventDefault()
    setError('')
    setLoading(true)

    try {
      // Obtener cookie CSRF de Laravel Sanctum
      await authApi.get('/sanctum/csrf-cookie')

      // Iniciar sesión
      await authApi.post('/login', {
        email,
        password,
      })

      // La ruta de selección se configurará después
      sessionStorage.removeItem("sis_matri_institution_id")
      navigate("/select-institution", { replace: true })
    } catch (err) {
      setError(
        err.response?.data?.message ||
          'No se pudo iniciar sesión. Revisa tus credenciales.'
      )
    } finally {
      setLoading(false)
    }
  }

  return (
    <main className="login-page">
      <h1>Iniciar sesión</h1>
      <p>Sistema de Matrículas</p>

      <form onSubmit={handleSubmit}>
        <div>
          <label htmlFor="email">Correo electrónico</label>
          <input
            id="email"
            type="email"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            autoComplete="username"
            required
          />
        </div>

        <div>
          <label htmlFor="password">Contraseña</label>
          <input
            id="password"
            type="password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            autoComplete="current-password"
            required
          />
        </div>

        {error && <p role="alert">{error}</p>}

        <button type="submit" disabled={loading}>
          {loading ? 'Ingresando...' : 'Iniciar sesión'}
        </button>
      </form>
    </main>
  )
}

export default Login
