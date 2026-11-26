# CivicFix PWA

Frontend móvil vanilla (HTML/CSS/JS, sin build step) para ciudadanos, cuadrillas, supervisores y administradores. Se sirve desde la misma origin que la API Laravel, montada bajo `/pwa/`.

## Instalación

Copiar este directorio a la raíz pública de nginx y servir `/pwa/` con `index.html` como índice. No hay dependencias ni compilación. La API debe estar disponible en `/api/v1` bajo el mismo dominio.

Ejemplo de ubicación en nginx:

```nginx
location /pwa/ {
    alias /var/www/civicfix/frontend-pwa/;
    try_files $uri $uri/ /pwa/index.html;
}
```

El service worker se registra en `/pwa/sw.js` con scope `/pwa/`. Requiere HTTPS (o `localhost`). Leaflet se carga desde CDN; si no está disponible, el mapa muestra un mensaje y el resto de la app sigue funcionando.

## Almacenamiento local

- `localStorage`: `civicfix.token` (Bearer de Sanctum) y `civicfix.user` (usuario en caché para roles y modo offline).
- IndexedDB `civicfix` versión 1, store `outbox` con clave primaria `idempotencyKey` e índices `status` y `createdAt`. Cada registro guarda `{idempotencyKey, path, method, payload, photoDataUrl, photoName, photoType, photoField, status, createdAt, attempts, error}`.

El token se envía como `Authorization: Bearer <token>` con `credentials: 'omit'`. Un `401` borra token y usuario y vuelve al formulario de acceso.

## Contrato API usado

| Método | Ruta | Uso |
| --- | --- | --- |
| POST | `/auth/register`, `/auth/login`, `/auth/logout` | Autenticación Sanctum |
| GET | `/auth/me` | Restaurar sesión |
| GET | `/categories` | Categorías del formulario |
| POST | `/incidents` | Crear reporte multipart con header `X-Idempotency-Key` |
| GET | `/incidents`, `/incidents/{id}` | Listado y detalle con `media` y `timeline` |
| POST | `/incidents/{id}/start`, `/resolve`, `/reject` | Acciones de cuadrilla y supervisor |
| GET | `/crews`, `/dashboard` | Datos de staff |
| GET | `/audit-logs` | Registro de auditoría (admin) |

Todas las respuestas se leen desde el wrapper `data`. La foto se comprime en el cliente a WebP/JPEG de menos de 350 KB antes de subirla.

## Comportamiento offline

Si el envío de un reporte falla por red, se encola en `outbox` con la foto como data URL y estado `pending`; la UI muestra «pendiente de sincronización». `syncOutbox()` se ejecuta al iniciar, al evento `online` y cada 60 s (solo con conexión y sesión activa), reconstruye el `FormData` y el archivo, reutiliza la misma clave de idempotencia, elimina el registro en caso de éxito 2xx y marca `failed` con el error en caso contrario. Un bloqueo evita sincronizaciones concurrentes.

## Política del service worker

- App shell (`/pwa/`, `index.html`, `styles.css`, `app.js`, `manifest.webmanifest`, `icon.svg`) y assets CDN de Leaflet: cache-first.
- GET a `/api/`: network-first con fallback a caché; las respuestas se guardan con `Vary: Authorization` solo si son 2xx.
- Cualquier método distinto de GET: se omite por completo, por lo que las respuestas POST nunca se cachean.
- Al activar, se eliminan las cachés de versiones anteriores.
