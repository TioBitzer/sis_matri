
import { useEffect, useState } from 'react'
import { Navigate, Outlet, useLocation } from 'react-router-dom'
import api from '../services/api'

function ProtectedRoute() {
  const location = useLocation()
  const [estado, setEstado] = useState('cargando')

  useEffect(() => {
    let activo = true

    api.get('/user')
      .then(() => {
        if (activo) setEstado('autenticado')
      })
      .catch(() => {
        if (activo) setEstado('no-autenticado')
      })

    return () => {
      activo = false
    }
  }, [])

  if (estado === 'cargando') {
    return <p>Verificando sesión...</p>
  }

  if (estado === 'no-autenticado') {
    return (
      <Navigate
        to="/login"
        state={{ from: location }}
        replace
      />
    )
  }

  // Exigir que se seleccione una institución
  const institutionId = sessionStorage.getItem(
    'sis_matri_institution_id'
  )

  if (
    !institutionId &&
    location.pathname !== '/select-institution'
  ) {
    return <Navigate to="/select-institution" replace />
  }

  return <Outlet />
}

export default ProtectedRoute
