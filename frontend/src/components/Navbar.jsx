
import { useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import api, { authApi } from '../services/api'

const grupos = [
  {
    titulo: 'Personas',
    enlaces: [
      { nombre: 'Personas', ruta: '/persons' },
      { nombre: 'Estudiantes', ruta: '/students' },
      { nombre: 'Docentes', ruta: '/teachers' },
    ],
  },
  {
    titulo: 'Estructura académica',
    enlaces: [
      { nombre: 'Programas académicos', ruta: '/academic-programs' },
      { nombre: 'Planes académicos', ruta: '/academic-plans' },
      { nombre: 'Niveles académicos', ruta: '/academic-levels' },
      { nombre: 'Elementos curriculares', ruta: '/academic-curriculum-items' },
      { nombre: 'Unidades académicas', ruta: '/academic-units' },
    ],
  },
  {
    titulo: 'Organización',
    enlaces: [
      { nombre: 'Períodos académicos', ruta: '/academic-periods' },
      { nombre: 'Campus', ruta: '/campuses' },
      { nombre: 'Ofertas académicas', ruta: '/academic-offerings' },
      { nombre: 'Asignaciones docentes', ruta: '/teaching-assignments' },
    ],
  },
  {
    titulo: 'Gestión de matrículas',
    enlaces: [
      { nombre: 'Historiales académicos', ruta: '/academic-records' },
      { nombre: 'Matrículas', ruta: '/enrollments' },
      { nombre: 'Detalles de matrícula', ruta: '/enrollment-details' },
    ],
  },
]

function Navbar() {
  const location = useLocation()
  const navigate = useNavigate()

  const [menuAbierto, setMenuAbierto] = useState(null)
  const [cerrandoSesion, setCerrandoSesion] = useState(false)

  const cerrarMenu = () => setMenuAbierto(null)

  const cerrarSesion = async () => {
    if (cerrandoSesion) return

    setCerrandoSesion(true)

    try {
      await authApi.post('/logout')

      sessionStorage.removeItem('sis_matri_institution_id')
      delete api.defaults.headers.common['X-Institution-Id']

      navigate('/login', { replace: true })
    } catch (error) {
      console.error('Error al cerrar sesión:', error)
      window.alert('No se pudo cerrar sesión. Intenta nuevamente.')
    } finally {
      setCerrandoSesion(false)
    }
  }

  const enlaceActivo = (ruta) => {
    if (ruta === '/') {
      return location.pathname === '/'
    }

    return (
      location.pathname === ruta ||
      location.pathname.startsWith(`${ruta}/`)
    )
  }

  if (
    location.pathname === '/login' ||
    location.pathname === '/select-institution'
  ) {
    return null
  }

  return (
    <nav className="navbar">
      <Link
        to="/"
        className="navbar-brand"
        onClick={cerrarMenu}
      >
        Sistema de Matrículas
      </Link>

      <div className="navbar-links">
        <Link
          to="/"
          className={enlaceActivo('/') ? 'activo' : ''}
          onClick={cerrarMenu}
        >
          Dashboard
        </Link>

        {grupos.map((grupo) => (
          <div className="navbar-group" key={grupo.titulo}>
            <button
              type="button"
              className="navbar-dropdown-button"
              aria-expanded={menuAbierto === grupo.titulo}
              onClick={() =>
                setMenuAbierto((actual) =>
                  actual === grupo.titulo ? null : grupo.titulo
                )
              }
            >
              {grupo.titulo}
              <span aria-hidden="true">
                {menuAbierto === grupo.titulo ? ' ▲' : ' ▼'}
              </span>
            </button>

            {menuAbierto === grupo.titulo && (
              <div className="navbar-dropdown">
                {grupo.enlaces.map((enlace) => (
                  <Link
                    key={enlace.ruta}
                    to={enlace.ruta}
                    className={enlaceActivo(enlace.ruta) ? 'activo' : ''}
                    onClick={cerrarMenu}
                  >
                    {enlace.nombre}
                  </Link>
                ))}
              </div>
            )}
          </div>
        ))}

        <button
          type="button"
          className="navbar-dropdown-button"
          onClick={cerrarSesion}
          disabled={cerrandoSesion}
        >
          {cerrandoSesion ? 'Cerrando sesión...' : 'Cerrar sesión'}
        </button>
      </div>
    </nav>
  )
}

export default Navbar
