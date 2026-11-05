# Sistema de Gestión de Matrículas — Multiinstitución

Proyecto colaborativo para gestionar instituciones educativas, sedes, programas, planes, cursos, docentes, estudiantes y matrículas, con permisos y aislamiento por institución. Está en desarrollo; **no se considera listo para producción**.

**Equipo:** Bruno (coordinación y backend), Junior (autenticación React) y Cristian (interfaz de matrículas React).

## Estado y contexto para colaboradores

- **[Estado del proyecto, avances, tareas y cómo retomar la fase 6G](docs/ESTADO_PROYECTO.md)** — punto de partida para el equipo y otros asistentes.
- **[Contrato React ↔ API de matrículas](docs/API_REACT_MATRICULAS.md)** — endpoints, respuesta JSON, roles y errores esperados.
- **[Modelo del dominio](docs/domain.md)** — entidades académicas y relaciones del sistema.
- **[Pull Request #20 fusionado](https://github.com/TioBitzer/sis_matri/pull/20)** — última gran integración confirmada en `main` antes de la fase 6G.

### Tecnología

- Backend: PHP 8.2+ / Laravel 12 / Laravel Sanctum / Eloquent / API REST.
- Frontend: React 19 / Vite / Axios (`frontend/`).
- Base de datos: relacional; pruebas PHPUnit configuradas con SQLite en memoria.
- Colaboración: Git, ramas por funcionalidad, Pull Requests y GitHub Actions.

### Lo que ya existe

Backend con instituciones, administración de membresías y roles, catálogo académico, personas/docentes/estudiantes, historiales, matrículas, detalles, ofertas elegibles, permisos por institución y controles de estado/cupo.

Las páginas React de varios CRUD existen. La integración completa de sesión Sanctum, selección de institución y validación de flujos reales en navegador **todavía está pendiente**.

La última ejecución de pruebas **informada por el equipo** antes de la fase 6G fue **1,073 pruebas / 4,360 aserciones** (2 de noviembre de 2026). Para el resultado actual, consultar los *Checks* del Pull Request y ejecutar la suite.

## Desarrollo local

```bash
# Backend: desde la raíz
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve --host=localhost --port=8000
```

Usar una base de desarrollo. Si ya existe `.env`, conservarlo y revisar las variables de sesión/SPA que aparecen en `.env.example`. **No versionar `.env`**.

```bash
# Frontend: en otra terminal
cd frontend
npm ci
npm run dev -- --host localhost
```

Para las pruebas de backend:

```bash
php artisan test
```

Para compilar el frontend:

```bash
cd frontend
npm run build
```

Las pruebas se ejecutan usando el entorno PHPUnit (SQLite en memoria definido en `phpunit.xml`). Confirmar la configuración de pruebas antes de ejecutarlas y nunca usar una base con información real.

## Trabajo en equipo

1. Actualizar `main` y crear una rama propia desde allí.
2. Evitar modificar simultáneamente archivos de otro integrante sin coordinarlo.
3. Crear Pull Request hacia `main` y verificar los trabajos automáticos de Laravel y React.
4. Documentar decisiones de API en `docs/API_REACT_MATRICULAS.md` y actualizar `docs/ESTADO_PROYECTO.md` al completar una fase.

**Para quien retoma el trabajo desde otro ChatGPT:** leer primero [docs/ESTADO_PROYECTO.md](docs/ESTADO_PROYECTO.md) en la rama o PR activo, no solo en `main`. Revisar los commits y pruebas reales antes de dar por completada una tarea.
