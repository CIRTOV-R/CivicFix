# agent-context.md

Contexto técnico para continuar el desarrollo de CivicFix. Todo el código está en `/home/vixt0/Projects/CivicFix`.

## 1. Objetivo y alcance

Construir una plataforma de reporte y gestión de incidencias viales/urbanas para Ciudad Guayana con:

- Backend API Laravel 11 (`backend-laravel/`).
- Microservicio de ruteo óptimo FastAPI (`routing-fastapi/`) con OSRM real o grafo vial embebido de contingencia.
- PWA (`frontend-pwa/`) con soporte offline, cámara, GPS y mapa Leaflet.
- PostgreSQL + PostGIS, Redis + Horizon, MinIO/S3, Docker Compose.

La documentación funcional de origen es `CivicFix.pdf`; se extrajo con `pdftotext -layout CivicFix.pdf -`.

## 2. Estructura actual

```text
CivicFix/
├── CivicFix.pdf
├── docker-compose.yml
├── .env.example
├── agent-context.md
├── backend-laravel/
│   ├── Dockerfile
│   ├── .dockerignore
│   ├── docker/
│   │   ├── entrypoint.sh
│   │   ├── supervisord.conf
│   │   └── nginx/default.conf
│   ├── app/
│   │   ├── Actions/Incidents/
│   │   ├── Data/
│   │   ├── Enums/
│   │   ├── Events/
│   │   ├── Http/{Controllers,Requests,Resources,Middleware}/
│   │   ├── Jobs/
│   │   ├── Listeners/
│   │   ├── Models/
│   │   ├── Policies/
│   │   ├── Providers/
│   │   └── Services/
│   ├── config/{civicfix.php,filesystems.php,services.php}
│   ├── database/{migrations,factories,seeders}
│   ├── routes/{api.php,console.php,web.php}
│   └── tests/{Feature,Unit}
├── routing-fastapi/
│   ├── Dockerfile
│   ├── main.py
│   ├── app/{api,core,services,schemas}
│   └── tests/
└── frontend-pwa/
    ├── index.html
    ├── styles.css
    ├── app.js
    ├── sw.js
    ├── manifest.webmanifest
    └── icon.svg
```

## 3. Stack y decisiones

- Laravel 11 + PHP 8.2+ en Docker (`php:8.2-fpm-bookworm`).
- Autenticación Sanctum con tokens personales; expiración por defecto `43200` minutos si `sanctum.expiration` está ausente o `null`.
- RBAC con tabla `roles` y enum `RoleName`: `CIUDADANO`, `CUADRILLA`, `SUPERVISOR`, `ADMINISTRADOR`.
- PostGIS en producción: columnas `location geography(Point,4326)` y `boundary geography(Polygon,4326)`, índices GiST, trigger `audit_logs_immutable`.
- SQLite local: `SpatialService` usa Haversine como fallback; el schema no agrega columnas geográficas fuera de PostgreSQL.
- Redis para caché, sesiones, colas y Horizon; `predis/predis`.
- MediaLibrary + Flysystem S3/MinIO; evidencia convertida a WebP, EXIF eliminado, URLs firmadas.
- FastAPI se consulta con `X-Internal-Token`; si falla, `CircuitBreaker` + fallback geodésico local.
- Se usa PHPUnit porque Pest entra en conflicto con `phpunit/phpunit 11.5.58` en el lock actual.

## 4. Endpoints API (`/api/v1`)

Públicos:

- `POST /auth/register`
- `POST /auth/login`

Autenticados (`auth:sanctum`):

- `POST /auth/logout`
- `GET /auth/me`
- `GET /categories`
- `POST /incidents` (middleware `idempotent`; header `X-Idempotency-Key` UUID obligatorio)
- `GET /incidents` (filtros `status`, `priority`, `per_page`)
- `GET /incidents/{incident}`
- `POST /incidents/{incident}/assign` (`SUPERVISOR|ADMINISTRADOR`)
- `POST /incidents/{incident}/start`
- `POST /incidents/{incident}/resolve`
- `POST /incidents/{incident}/reject` (`SUPERVISOR|ADMINISTRADOR`)
- `GET /crews` (`CUADRILLA|SUPERVISOR|ADMINISTRADOR`)
- `PATCH /crews/{crew}/location`
- `GET /dashboard` (`SUPERVISOR|ADMINISTRADOR`)
- `GET /audit-logs` (`ADMINISTRADOR`)

`/` redirige a `/pwa/`; la PWA se sirve desde `/pwa/` en Nginx.

### Contrato de reporte

`POST /incidents` acepta:

```json
{
  "title": "Bache profundo",
  "description": "Obstruye el tránsito",
  "category_id": 1,
  "latitude": 8.295,
  "longitude": -62.742,
  "address_reference": "Frente al mercado",
  "priority": "ALTA",
  "before_photo": "<multipart opcional>"
}
```

Respuesta exitosa: `201`, cuerpo `{ "data": IncidentResource }`.

`POST /incidents/{id}/start` y `/resolve` usan `latitude`/`longitude`; `/resolve` exige `after_photo`.

## 5. Servicio FastAPI

Endpoint:

```text
POST /api/v1/routing/calculate-optimal-crew
X-Internal-Token: <token>
```

Request:

```json
{
  "incident_id": 12,
  "incident_location": { "latitude": 8.295, "longitude": -62.742 },
  "specialty_required": "VIALIDAD",
  "candidates": [
    { "crew_id": 3, "latitude": 8.296, "longitude": -62.743, "current_load": 1 }
  ]
}
```

Response:

```json
{
  "status": "success",
  "incident_id": 12,
  "optimal_crew": {
    "crew_id": 3,
    "distance_meters": 342.5,
    "duration_seconds": 480.0,
    "distance_km": 0.34,
    "eta_minutes": 8.0,
    "route_summary": "Av. Guayana → C. Unare",
    "bridge_crossing_required": false,
    "polyline": "..."
  },
  "evaluated_candidates_count": 1
}
```

Configuración relevante: `INTERNAL_API_TOKEN`, `OSRM_BASE_URL`, `OSRM_REQUEST_TIMEOUT`, `CARONI_SPLIT_LONGITUDE`, `TRAFFIC_FACTOR`.

## 6. Reglas de negocio implementadas

- Prefiltrado geoespacial: `15000m` (`CIVICFIX_PREFILTER_RADIUS_METERS`).
- Geocercado de cierre: `50m` (`CIVICFIX_GEOFENCE_RADIUS_METERS`).
- SLA: alto riesgo no atendido tras `72h` pasa a `CRITICA` (`civicfix:check-sla`).
- Transiciones permitidas: `REPORTADO → ASIGNADO → EN_PROCESO → RESUELTO`; rechazo desde estados no terminales.
- Idempotencia: hash `user_id|X-Idempotency-Key`, respuesta persistida en `idempotency_responses`.
- Auditoría: `AuditLog::update()` y `delete()` lanzan `LogicException`; PostgreSQL además usa trigger inmutable.
- Asignación: FastAPI/OSRM → caché Redis → circuit breaker → fallback Haversine/PostGIS.

## 7. Usuarios demo

Seeder `DemoUserSeeder`, password común `civicfix123`:

- `ciudadano@civicfix.local`
- `cuadrilla@civicfix.local`
- `supervisor@civicfix.local`
- `admin@civicfix.local`

## 8. Comandos verificados

Laravel:

```bash
cd /home/vixt0/Projects/CivicFix/backend-laravel
php artisan migrate:fresh --seed --force
php artisan route:list --path=api
vendor/bin/phpunit
vendor/bin/pint --test
```

FastAPI:

```bash
cd /home/vixt0/Projects/CivicFix/routing-fastapi
python3 -m pytest -q
python3 -m compileall -q app main.py tests
```

PWA:

```bash
cd /home/vixt0/Projects/CivicFix/frontend-pwa
node --check app.js
node --check sw.js
```

Estado verificado:

- Laravel: `OK (33 tests, 105 assertions)`.
- Pint: `passed`.
- FastAPI: `7 passed`.
- PWA: sintaxis JS correcta.
- `docker-compose.yml` validado con parser YAML; no ejecutado porque Docker no está instalado en este entorno.
- Smoke FastAPI con `TestClient`: sin token `401`, con token `200`, `crew_id=7`, `evaluated_candidates_count=2`.
- Smoke HTTP Laravel + FastAPI: `register 201`, `me 200`, `categories 5`, incidencia creada con `status ASIGNADO`, `assignment_source fastapi_osrm`, replay de idempotencia `201`, RBAC `403`, invitado `401`.

## 9. Ejecución con Docker

```bash
cd /home/vixt0/Projects/CivicFix
cp .env.example .env
cp backend-laravel/.env.example backend-laravel/.env
cd backend-laravel
php artisan key:generate
cd ..
docker compose up --build
```

Servicios:

- `nginx`: `http://localhost:8080`, PWA en `/pwa/`.
- `laravel-app`: PHP-FPM + Horizon + scheduler vía supervisord.
- `fastapi-routing`: red interna, no expuesto públicamente.
- `postgres-db`: PostGIS 15/3.4.
- `redis-cache`.
- `minio`: API `9000`, consola `9001`, bucket `civicfix-media` creado por `minio-init`.

Si se usa OSRM externo, definir `OSRM_BASE_URL`; si está vacío, FastAPI usa el grafo embebido.

## 10. Entorno local sin Docker

No hay `docker`/`docker compose` ni Composer global. Se usó:

- Composer local: `/tmp/opencode/composer`.
- Wrapper PHP: `/tmp/opencode/phpw`.
- Extensiones: `/tmp/opencode/php-ext`.

Para comandos Laravel locales, anteponer:

```bash
/tmp/opencode/phpw artisan ...
/tmp/opencode/phpw vendor/bin/phpunit
/tmp/opencode/phpw vendor/bin/pint
```

Para `artisan serve`, el proceso hijo también necesita las extensiones:

```bash
PHP_INI_SCAN_DIR=/tmp/opencode/php-ext/conf.d /tmp/opencode/phpw artisan serve --host=127.0.0.1 --port=8000
```

El `.env` local usa SQLite para poder migrar y probar sin servidor PostgreSQL:

```dotenv
DB_CONNECTION=sqlite
DB_DATABASE=/home/vixt0/Projects/CivicFix/backend-laravel/database/database.sqlite
CACHE_STORE=array
QUEUE_CONNECTION=sync
MEDIA_DISK=public
```

## 11. Pendientes / recomendaciones

- No ejecutar `docker compose up` en este entorno; validar en una máquina con Docker.
- Confirmar despliegue real de PostGIS, Horizon y MinIO con el stack Docker.
- Si se habilita OSRM real, generar/montar un `.osm.pbf` y un servicio `osrm-routed`; hoy el grafo embebido evita esa dependencia.
- Verificado: `role` enviado desde el registro de la PWA es ignorado por `RegisterRequest`; el rol siempre se fuerza a `CIUDADANO`.
- Mantener `ROUTING_INTERNAL_TOKEN` igual entre Laravel y FastAPI en cualquier entorno.
