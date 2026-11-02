# Contrato de integración React ↔ Laravel — Matrículas (fase 6F)

**Base revisada:** rama `feat/institution-roles-permissions` del repositorio `TioBitzer/sis_matri` (2 de noviembre de 2026).  
**Objetivo:** que Cristian pueda consumir la API existente sin inventar nombres de campos ni esquemas de error.  
**Alcance:** autenticación y selección de institución, matrículas, detalles y ofertas elegibles. **No se modifican endpoints** con esta fase.

## 1. Autenticación y sesión (Sanctum SPA)

La aplicación React de primera parte debe usar cookies de sesión. No guardar tokens ni contraseñas en `localStorage`.

1. `GET /sanctum/csrf-cookie` con credenciales; Axios debe admitir cookies y cabeceras XSRF.
2. `POST /login` con `email` y `password`; devuelve `200` y un objeto `user` con `id`, `name` y `email`.
3. `GET /api/user` para verificar la sesión; **su respuesta es el modelo de usuario serializado, no** `{data: {...}}`.
4. `GET /api/me/institutions` con sesión; devuelve solo las instituciones y membresías activas del usuario.
5. Seleccionar un `id` de esa lista y mandar `X-Institution-Id` en las peticiones siguientes.
6. `POST /logout` para invalidar la sesión. Limpiar el estado de institución del frontend.

Las rutas protegidas verifican la membresía **en el servidor en cada solicitud**. El estado local de React no autoriza acceso por sí mismo.

### Respuesta de selección de institución

`GET /api/me/institutions` **no necesita** `X-Institution-Id` y devuelve:

```json
{
  "data": [
    {
      "id": 1,
      "name": "Instituto ejemplo",
      "code": "INST-01",
      "type": "institute",
      "role": "registrar"
    }
  ]
}
```

Los valores son ilustrativos. Roles contemplados: `institution_admin`, `registrar`, `teacher`, `student`, `member`.

## 2. Endpoints del módulo de matrículas

**Cabeceras en rutas institucionales:** cookie de sesión, `Accept: application/json`, `X-Institution-Id: <ID válido>`. En operaciones JSON, `Content-Type: application/json`.

| Método | Ruta | Rol permitido | Uso en React |
|---|---|---|---|
| GET | `/api/enrollments` | `institution_admin`, `registrar` | Lista de matrículas **sin paginación actualmente** |
| POST | `/api/enrollments` | `institution_admin`, `registrar` | Crear matrícula |
| GET | `/api/enrollments/{id}` | `institution_admin`, `registrar` | Ver detalle |
| PUT | `/api/enrollments/{id}` | `institution_admin`, `registrar` | Actualizar/cancelar; se envían todos los campos requeridos |
| DELETE | `/api/enrollments/{id}` | `institution_admin`, `registrar` | Eliminar solo cuando no tiene cursos |
| GET | `/api/enrollments/{id}/available-offerings?per_page=20&page=1` | `institution_admin`, `registrar` | Mostrar cursos compatibles y cupos (máx. 50 por página) |
| GET | `/api/enrollment-details` | `institution_admin`, `registrar` | Lista de detalles; **sin paginación actualmente** |
| POST | `/api/enrollment-details` | `institution_admin`, `registrar` | Inscribir un curso |
| PUT | `/api/enrollment-details/{id}` | `institution_admin`, `registrar` | Actualizar estado/datos del curso |
| GET | `/api/enrollment-details/{id}` | `institution_admin`, `registrar` | Ver inscripción individual |
| DELETE | `/api/enrollment-details/{id}` | `institution_admin`, `registrar` | Eliminar detalle (comprobar política de historial antes de usar en UI) |

### Crear matrícula

`POST /api/enrollments`:

```json
{
  "student_id": 10,
  "academic_period_id": 3,
  "academic_record_id": 8,
  "enrollment_date": "2026-03-01",
  "status": "active"
}
```

Devuelve **HTTP 201** y `{"success": true, "data": {"id": 42, "status": "active"}}`, además de otros atributos y relaciones que el controlador carga. **No asumir** que el ejemplo contiene todos los campos.

Los estados admitidos por `EnrollmentController` son `active`, `pending`, `cancelled`, `completed`, `suspended`, `withdrawn`, `inactive`. Los detalles admiten `active`, `cancelled`, `completed`. No todos los estados representan una transición operativa permitida.

### Inscribir un curso

`POST /api/enrollment-details`:

```json
{
  "enrollment_id": 42,
  "academic_offering_id": 17,
  "status": "active"
}
```

Devuelve **HTTP 201** y `{"success": true, "data": {"id": 99, "status": "active"}}` más atributos y relaciones. Se comprueban institución, programa/plan, periodo, duplicados, estado y vacantes en el backend.

### Listar ofertas disponibles

`GET /api/enrollments/42/available-offerings?per_page=20&page=1`:

```json
{
  "success": true,
  "data": [
    {
      "id": 17,
      "academic_unit": {"id": 5, "name": "Programación I", "code": "PRG-1"},
      "campus": {"id": 2, "name": "Sede principal", "code": "SED-1"},
      "section": "A",
      "capacity": 30,
      "occupied": 28,
      "remaining": 2,
      "can_enroll": true
    }
  ],
  "meta": {"current_page": 1, "last_page": 1, "per_page": 20, "total": 1}
}
```

Son valores ilustrativos. `capacity`/`remaining` pueden ser `null` si no existe límite; `can_enroll` ayuda al UI, **pero no reserva cupo**. El POST posterior puede devolver `422` si se llenó entretanto. No ofrecer cursos para matrículas canceladas ni periodos inactivos.

## 3. Manejo real de errores

No todos los errores tienen el mismo JSON. **React debe manejar el código HTTP primero**, y leer `errors` solo si existe:

| HTTP | Significado | Forma habitual |
|---|---|---|
| `401` | Sesión ausente/expirada | `{"message":"Unauthenticated."}` |
| `403` | Sin acceso a institución o rol | `{"message":"..."}` |
| `404` | Registro inexistente o ajeno al tenant | error estándar de Laravel |
| `409` | Conflicto de estado (p. ej. matrícula cancelada) | `{"success":false,"message":"..."}` |
| `422` | Validación/formulario o encabezado inválido | Laravel: `{"message":"...","errors":{"campo":["..."]}}`; el middleware del header puede devolver solo `message` |
| `429` | Demasiados intentos de login | limitación del middleware `throttle:5,1` |

**No basar la lógica en el texto exacto** de `message`, que puede cambiar por localización o políticas.

## 4. Integración Axios mínima para Cristian

```js
import axios from 'axios';

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? 'http://localhost:8000',
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json' },
});

let selectedInstitutionId = null;
export function selectInstitution(id) {
  selectedInstitutionId = id == null ? null : Number(id);
}

api.interceptors.request.use((config) => {
  // No añadir este header al selector de instituciones ni al login.
  const path = new URL(config.url ?? '/', config.baseURL).pathname;
  const institutional = path.startsWith('/api/') &&
    !['/api/me/institutions', '/api/user'].includes(path);
  if (institutional && selectedInstitutionId !== null) {
    config.headers['X-Institution-Id'] = String(selectedInstitutionId);
  }
  return config;
});

export async function login(email, password) {
  await api.get('/sanctum/csrf-cookie');
  const { data } = await api.post('/login', { email, password });
  return data.user;
}
export async function listMyInstitutions() {
  const { data } = await api.get('/api/me/institutions');
  return data.data;
}
export async function getEligibleOfferings(enrollmentId, page = 1) {
  const { data } = await api.get(`/api/enrollments/${enrollmentId}/available-offerings`, {
    params: { page, per_page: 20 },
  });
  return data; // {success, data, meta}
}
export async function logout() {
  try { await api.post('/logout'); }
  finally { selectInstitution(null); }
}
```

Este ejemplo asume que React y Laravel comparten el mismo sitio principal, con cookies y CORS configurados para el entorno concreto. Probar integración real en navegador con `/sanctum/csrf-cookie` y sesión. El identificador de institución se guarda solo en memoria; al recargar, volver a consultar membresías y volver a seleccionar.

## 5. Pendientes encontrados para 6G o una evolución de la API

- `GET /api/enrollments` y `GET /api/enrollment-details` usan `get()` sin paginación; conviene planificar una versión paginada **con compatibilidad controlada**, no cambiar la forma de respuesta silenciosamente.
- Las respuestas de error no están normalizadas entre middleware, validación y conflictos; conviene decidir un formato único antes de declarar API v1 estable.
- Existe `GET /api/test` público. Revisar su necesidad y retirarlo antes de producción si es una ruta de diagnóstico temporal.
- La respuesta de `GET /api/user` serializa el modelo completo según sus atributos visibles; revisar exposición de datos antes de producción.
- Falta comprobar en navegador real cookies, CSRF, CORS, expiración de sesión y revocación al cambiar de institución.
- El endpoint de vacantes muestra un **estado instantáneo**, no una reserva. La protección final debe seguir en el backend.

**Pruebas de contrato asociadas:** `php artisan test --filter=ReactEnrollmentApiContractTest`.
