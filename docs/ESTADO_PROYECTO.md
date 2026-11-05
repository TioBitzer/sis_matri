# Estado del proyecto y relevo del equipo — Fase 6G

**Fecha de revisión:** 5 de noviembre de 2026  
**Repositorio:** https://github.com/TioBitzer/sis_matri  
**Rama estable:** `main`  
**Rama de trabajo actual:** `feat/api-integration-6g`  
**Objetivo:** Sistema de Gestión de Matrículas multiinstitución, con aislamiento por institución y una interfaz React.

> Este documento permite que cualquier integrante —o su asistente— retome el trabajo desde GitHub. Comprobar siempre el commit y el estado del PR antes de asumir que un cambio está integrado en `main`.

## 1. Estado confirmado en GitHub

- El **PR #20** se fusionó en `main` el 2 de noviembre de 2026: https://github.com/TioBitzer/sis_matri/pull/20
- Su commit de integración fue `581260073fc89baea4bee9b646acfb6c4ba253db`.
- Backend: entidades, CRUD académicos, estudiantes/docentes, matrícula/detalles, roles institucionales, aislamiento entre instituciones, reglas de estados, cupos y ofertas elegibles.
- La última ejecución local **reportada por el equipo antes de iniciar la fase 6G** fue de **1,073 pruebas aprobadas / 4,360 aserciones / 29.78 s**. Ese número no incluye necesariamente cambios posteriores y no equivale a un resultado de CI.
- Frontend: existe `frontend/` con React/Vite y pantallas de estudiantes, catálogo académico y matrículas, **pero no está completada ni verificada la integración de autenticación SPA**.

## 2. Cambios de esta rama (fase 6G)

- `GET /api/user` mantiene una respuesta JSON sin envoltura `data`, pero limitada a `id`, `name` y `email`. Requiere sesión.
- `tests/Feature/ReactUserSessionContractTest.php` comprueba el `401` del invitado y el contrato JSON sin atributos internos para un usuario autenticado.
- `.github/workflows/ci.yml` añade dos trabajos automáticos en Pull Requests y cambios de `main`: pruebas Laravel (PHP 8.3 / SQLite de PHPUnit) y compilación React (Node 22).
- `.env.example` documenta el origen del frontend y los dominios *stateful* de Sanctum para desarrollo local.
- Este archivo y el README hacen explícitos el estado, tareas y cómo verificar lo realizado.

**Importante:** la publicación de estos commits no garantiza que CI haya terminado o esté en verde. Revisar la pestaña **Checks** del PR. No fusionar cambios con pruebas fallidas.

## 3. Responsables y límites de cambios

| Responsable | Área | Próxima entrega |
| --- | --- | --- |
| Coordinación/backend (Bruno) | Laravel, seguridad, API y pruebas | Validación de sesión/CORS/tenant, coordinación y revisión de PR |
| Junior | Autenticación del frontend | Login/logout, recuperación de sesión, selector de institución, rutas protegidas y cliente Axios |
| Cristian | Matrículas del frontend | Ajustar formularios existentes para ofertas elegibles, cupos y errores HTTP |

**Para evitar conflictos:**

1. Junior será el responsable principal de `frontend/src/services/api.js` y del estado global de sesión/institución. Avisar a Cristian cuando el contrato del cliente HTTP esté disponible.
2. Cristian trabaja sobre `frontend/src/pages/Enrollments.jsx`, `CreateEnrollment.jsx`, `CreateEnrollmentDetail.jsx` y otras pantallas de matrícula. Debe consumir el cliente de Junior en lugar de reemplazarlo.
3. El equipo de backend evita editar las pantallas de React en paralelo.
4. Crear ramas desde `main` actualizado, abrir PR hacia `main`, revisar los tests y no introducir cambios directamente en `main`.

**Ramas recomendadas:** `feat/react-auth` (Junior), `feat/react-enrollments` (Cristian) y `feat/api-integration-6g` (backend). Una rama propuesta no significa que ya exista en remoto.

## 4. Contrato que React debe respetar

Ver el contrato más detallado: [API_REACT_MATRICULAS.md](API_REACT_MATRICULAS.md).

- `GET /sanctum/csrf-cookie` y luego `POST /login` con cookies / XSRF de Axios.
- `GET /api/user` devuelve directamente `{"id":1,"name":"...","email":"..."}` (sin `data`).
- `GET /api/me/institutions` devuelve `{"data":[...]}`. No necesita `X-Institution-Id`.
- Para rutas académicas protegidas enviar `X-Institution-Id` con un ID de institución permitido para ese usuario.
- `GET /api/enrollments/{id}/available-offerings` lista ofertas compatibles y cupos. Los cupos son informativos: el backend valida de nuevo en el POST de inscripción.
- Errores que React debe contemplar: `401` (sesión), `403` (permiso), `404`, `409` (conflicto), `422` (validación) y `429` (limitación de login).
- No guardar tokens ni credenciales en `localStorage` para la SPA propia.

## 5. Ejecución local reproducible

**Backend (entorno local de desarrollo):**

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve --host=localhost --port=8000
```

Usar una base **de desarrollo**, nunca datos de producción. En Windows, si ya existe `.env`, no sobrescribirlo: agregar manualmente `FRONTEND_URL=http://localhost:5173`, `SANCTUM_STATEFUL_DOMAINS=localhost:5173,localhost:8000` y verificar `APP_URL=http://localhost:8000`. Si se cambiaron valores de entorno, ejecutar `php artisan config:clear`.

**Frontend:**

```bash
cd frontend
npm ci
npm run dev -- --host localhost
```

Usar `localhost` de forma consistente en el navegador y el cliente API; mezclar `127.0.0.1` y `localhost` puede complicar cookies y CSRF. El archivo Axios existente todavía necesita la integración asignada a Junior.

**Tests backend (en la configuración exclusiva de PHPUnit, `phpunit.xml` usa SQLite en memoria):**

```bash
php artisan test --filter=ReactUserSessionContractTest
php artisan test --filter=ReactEnrollmentApiContractTest
php artisan test
```

**Build frontend:**

```bash
cd frontend
npm ci
npm run build
```

## 6. Validación pendiente antes del merge y de producción

- [ ] Confirmar la ejecución **actual** de GitHub Actions en el nuevo PR.
- [ ] Comprobar sesión completa React ↔ Laravel en navegador: CSRF, CORS, cookies, login/logout, `401` tras logout y recuperación de sesión.
- [ ] Comprobar selección y cambio de institución sin acceso cruzado ni permisos residuales.
- [ ] Probar un flujo real de matrícula: crear, listar ofertas elegibles, inscribir y verificar cupos/estados.
- [ ] Verificar que las listas sin paginar y el formato de errores sean adecuados antes de estabilizar API v1.
- [ ] Revisar si debe eliminarse `GET /api/test` (actualmente público) antes del despliegue.
- [ ] Definir despliegue, respaldos, seguridad de entorno y pruebas end-to-end para producción.

## 7. Cómo pasar este contexto a otro ChatGPT

Compartir esta URL y solicitar explícitamente la lectura del archivo en la **rama de trabajo o PR activo**:

https://github.com/TioBitzer/sis_matri/blob/feat/api-integration-6g/docs/ESTADO_PROYECTO.md

Después pedir que lea `docs/API_REACT_MATRICULAS.md`, el código de su área y el PR abierto. GitHub es la referencia para commits; los estados de ejecución local deben corroborarse con salidas de pruebas y Checks.

**No compartir archivos `.env`, tokens, contraseñas ni bases de datos reales con otros asistentes.**
