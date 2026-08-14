# SinergiaCRM Portal OAuth2 — Demo Client

Cliente OAuth2 completo para aplicaciones externas que autentican usuarios del portal (Personas / Organizaciones) contra SinergiaCRM. Implementa el flujo **authorization code grant** estándar.

## Tabla de contenidos

1. [Inicio rápido](#inicio-rápido)
2. [Configuración](#configuración)
3. [Flujo OAuth2](#flujo-oauth2)
4. [Endpoints del CRM](#endpoints-del-crm)
5. [Respuesta del token](#respuesta-del-token)
6. [Interfaz web](#interfaz-web)
7. [Sobrescritura de configuración desde la UI](#sobrescritura-de-configuración-desde-la-ui)
8. [Arquitectura del código](#arquitectura-del-código)
9. [Seguridad](#seguridad)
10. [Requisitos](#requisitos)

---

## Inicio rápido

```bash
# 1. Copiar la configuración de ejemplo
cp .env.example .env

# 2. Editar .env con tus credenciales
#    CRM_URL=https://tu-sinergiacrm.org
#    OAUTH_CLIENT_ID=tu-client-id
#    OAUTH_REDIRECT_URI=http://localhost:8000/PortalOauth/callback.php

# 3. Abrir en un navegador o servidor PHP integrado
php -S localhost:8000 index.php
```

**Nota:** Necesitas un cliente OAuth2 configurado en el CRM (Administration → OAuth2 Clients → New Portal Client) con grant type `portal_authorization_code`.

---

## Configuración

El archivo `.env` contiene toda la configuración. Si no existe, el cliente usa `config.php` como fallback (formato legacy).

| Variable | Descripción | Ejemplo |
|----------|-------------|---------|
| `CRM_URL` | URL pública del CRM (accesible desde el navegador) | `https://daniel.sinergiacrm.org` |
| `CRM_INTERNAL` | URL interna para llamadas curl servidor-servidor. Útil en Docker (usa el nombre del servicio en lugar de localhost). Si está vacío se usa `CRM_URL`. | `http://sw-webserver/sinergiacrm` |
| `OAUTH_CLIENT_ID` | UUID del cliente OAuth2 (grant type: `portal_authorization_code`) | `00000bcf-9168-4c63-...` |
| `OAUTH_REDIRECT_URI` | URL de callback de este cliente. Debe coincidir exactamente con la configurada en el OAuth2 Client del CRM. | `http://localhost:8000/SinergiaCRM-API-Examples/PortalOauth/callback.php` |

---

## Flujo OAuth2

Este cliente implementa el flujo **Authorization Code Grant**:

### Paso 1 — Redirección al login del CRM

```
GET {CRM_URL}/index.php?entryPoint=sticPortalLogin
    &client_id={OAUTH_CLIENT_ID}
    &redirect_uri={OAUTH_REDIRECT_URI}
    &response_type=code
    &state={random_state}
```

El parámetro `state` es un token aleatorio de 32 caracteres generado por `index.php` y almacenado en una cookie. Se usa para prevenir ataques CSRF.

### Paso 2 — El usuario se autentica en el portal

El CRM muestra la página de login del portal. El usuario introduce sus credenciales (o usa magic link). La app **nunca ve la contraseña** del usuario — todo ocurre en el CRM.

### Paso 3 — Callback con código de autorización

Tras autenticarse, el CRM redirige al `redirect_uri`:

```
GET {OAUTH_REDIRECT_URI}?code={authorization_code}&state={state}
```

`callback.php` valida el `state` contra la cookie. Si no coincide → error CSRF. Si el código no está presente → redirige a `index.php`.

### Paso 4 — Intercambio del código por tokens (servidor a servidor)

`callback.php` hace una llamada **curl** al endpoint de token del CRM:

```
POST {CRM_INTERNAL}/index.php?entryPoint=sticPortalOAuthToken

grant_type=authorization_code
code={authorization_code}
client_id={client_id}
redirect_uri={redirect_uri}
```

El CRM valida el código y devuelve los tokens.

### Paso 5 — Uso de los tokens

La respuesta incluye `access_token` (Bearer, válido 1 hora), `refresh_token`, `portal_id`, `portal_type` y los datos completos del usuario y sus relaciones.

---

## Endpoints del CRM

### `sticPortalLogin`

Página de login del portal. Acepta parámetros OAuth2 opcionales:

| Parámetro | Descripción |
|-----------|-------------|
| `client_id` | UUID del cliente OAuth2 |
| `redirect_uri` | URL de callback |
| `response_type` | `code` |
| `state` | Token anti-CSRF |

### `sticPortalOAuthToken`

Endpoint de intercambio de tokens (POST). Métodos soportados:

**Authorization Code → Tokens:**
```
POST sticPortalOAuthToken
grant_type=authorization_code
code={code}
client_id={client_id}
redirect_uri={redirect_uri}
```

**Refresh Token → Nuevo Access Token:**
```
POST sticPortalOAuthToken
grant_type=refresh_token
refresh_token={refresh_token}
client_id={client_id}
```

**Validación de Access Token:**
```
GET sticPortalOAuthToken?access_token={token}
```

---

## Respuesta del token

### Éxito (200)

```json
{
  "access_token": "eyJ...",
  "expires_in": 3600,
  "token_type": "Bearer",
  "refresh_token": "def...",
  "portal_id": "uuid-del-contacto-o-cuenta",
  "portal_type": "Contact",
  "user": {
    "id": "uuid",
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "phone_mobile": "555-0123",
    "stic_gender_c": "male",
    "stic_language_c": "en_us",
    "primary_address_city": "Barcelona",
    "...": "..."
  },
  "relationships": [
    {
      "id": "rel-uuid",
      "name": "Project Alpha",
      "relationship_type": "volunteer",
      "start_date": "2024-01-01",
      "end_date": "",
      "role": "Coordinator",
      "project_name": "Project Alpha",
      "stic_portal_decidim_excluded_c": false
    }
  ]
}
```

### Usuario tipo Contact

| Campo | Descripción |
|-------|-------------|
| `id` | UUID |
| `first_name` | Nombre |
| `last_name` | Apellidos |
| `birthdate` | Fecha de nacimiento |
| `email` | Email principal |
| `phone_mobile` | Teléfono móvil |
| `stic_gender_c` | Género |
| `stic_language_c` | Idioma |
| `stic_age_c` | Edad |
| `stic_identification_number_c` | Número de identificación |
| `stic_identification_type_c` | Tipo de identificación |
| `primary_address_*` | Dirección (calle, ciudad, estado, CP, país) |

### Usuario tipo Account

| Campo | Descripción |
|-------|-------------|
| `id` | UUID |
| `name` | Nombre de la organización |
| `phone_office` | Teléfono oficina |
| `phone_alternate` | Teléfono alternativo |
| `website` | Sitio web |
| `email` | Email principal |
| `account_type` | Tipo de organización |
| `industry` | Sector |
| `description` | Descripción |
| `stic_identification_number_c` | Número de identificación fiscal |
| `stic_identification_type_c` | Tipo de identificación |
| `stic_language_c` | Idioma |
| `billing_address_*` | Dirección de facturación |

### Relaciones

Cada relación incluye:

| Campo | Descripción |
|-------|-------------|
| `id` | UUID de la relación |
| `name` | Nombre de la relación |
| `relationship_type` | Tipo de relación |
| `start_date` | Fecha de inicio |
| `end_date` | Fecha de fin (vacío si activa) |
| `role` | Rol en la relación |
| `project_name` | Nombre del proyecto |
| `project_id` | UUID del proyecto |
| `stic_portal_decidim_excluded_c` | Excluido de Decidim |

### Errores (400)

```json
{
  "error": "invalid_grant",
  "message": "The authorization code is invalid or has expired."
}
```

Códigos de error: `invalid_request`, `invalid_client`, `invalid_grant`, `unsupported_grant_type`.

---

## Interfaz web

### `index.php` — Página de inicio

- Título y descripción del flujo OAuth2
- Botón **Login with SinergiaCRM** que redirige al portal del CRM
- Explicación del flujo en 3 pasos
- Tarjeta **Connection Settings** con la instancia conectada
- Panel de configuración colapsable (botón **Edit**)

### `callback.php` — Página de callback

- **Sin código de autorización:** redirige automáticamente a `index.php`
- **Con código válido:** muestra tokens, perfil del usuario, relaciones y JSON crudo
- **Con error:** muestra mensaje de error descriptivo
- Incluye instancia conectada y Client ID

---

## Sobrescritura de configuración desde la UI

La tarjeta **Connection Settings** permite cambiar la configuración sin editar archivos:

1. Haz clic en **Edit**
2. Cambia CRM URL, Internal URL, o Client ID
3. Haz clic en **Save Override** → guarda en `config-override.json`
4. Para volver a los valores del `.env`/`config.php`, haz clic en **Revert to Code Defaults**

El archivo `config-override.json` es local y está en `.gitignore`.

La página `callback.php` también carga los overrides automáticamente, así que los cambios se aplican al flujo completo sin necesidad de modificar dos archivos.

### Orden de prioridad

1. `config-override.json` (UI) — mayor prioridad
2. `.env` — archivo de configuración principal
3. `config.php` — fallback legacy

---

## Arquitectura del código

### `index.php`

```
loadPortalConfig()        → Carga config desde .env o config.php
                           → Aplica overrides de config-override.json
                           → Maneja POST save_settings / clear_settings
                           → Genera state CSRF + URL de login
                           → Renderiza HTML con config card + settings form
```

### `callback.php`

```
loadPortalConfig()        → Misma función de carga de config
                           → Valida state CSRF contra cookie
                           → Intercambia código por tokens vía curl
                           → Sin código → redirige a index.php
                           → Con éxito → renderiza perfil + relaciones
```

### Funciones compartidas

Ambos archivos definen `loadPortalConfig()` de forma independiente (son autocontenidos, no comparten includes). La función:

1. Lee `.env` línea por línea (formato `KEY=VALUE`, ignora comentarios `#`)
2. Si `.env` no existe o está vacío, carga `config.php` (array PHP)
3. Devuelve array con `crm_url`, `crm_internal`, `client_id`, `redirect_uri`

---

## Seguridad

### CSRF Protection
- `state` aleatorio de 32 bytes generado en `index.php`
- Almacenado en cookie HTTP-only (`oauth_demo_state`, 10 min)
- Validado en `callback.php` antes de procesar el código

### No exposición de contraseñas
- El usuario NUNCA introduce su contraseña en esta app
- La autenticación ocurre íntegramente en el portal del CRM
- La app solo recibe tokens y datos de perfil

### Honeypot en el login
- El formulario de login del CRM incluye un campo oculto (`portal_hp`)
- Si un bot lo rellena, el login se rechaza silenciosamente

### Sin secretos en el código
- El archivo `.env` está en `.gitignore`
- `config-override.json` está en `.gitignore`
- Las variables de entorno se leen con `getenv()` como alternativa

---

## Requisitos

- **PHP 7.4+** con extensión **curl**
- Un **cliente OAuth2** configurado en el CRM (`portal_authorization_code`)
- La URL de callback debe coincidir exactamente con la registrada en el cliente OAuth2

---

## Enlaces

- [SinergiaCRM-API-Examples](https://github.com/SinergiaTIC/SinergiaCRM-API-Examples)
- [Documentación de OAuth2](https://oauth.net/2/)
- [Authorization Code Grant (RFC 6749)](https://datatracker.ietf.org/doc/html/rfc6749#section-4.1)
