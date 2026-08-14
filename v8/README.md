# SinergiaCRM API V8 — Demo Client

Cliente web para la API REST V8 de SuiteCRM/SinergiaCRM usando el grant OAuth2 `client_credentials`. Permite explorar contactos, relaciones, valores de listas desplegables y módulos.

## Tabla de contenidos

1. [Inicio rápido](#inicio-rápido)
2. [Configuración](#configuración)
3. [Herramientas disponibles](#herramientas-disponibles)
4. [Endpoints de la API](#endpoints-de-la-api)
5. [Arquitectura del código](#arquitectura-del-código)
6. [Sobrescritura de configuración desde la UI](#sobrescritura-de-configuración-desde-la-ui)
7. [Seguridad](#seguridad)
8. [Requisitos](#requisitos)

---

## Inicio rápido

```bash
# 1. Copiar la configuración de ejemplo
cp .env.example .env

# 2. Editar .env con tus credenciales
#    SUITECRM_BASE_URL=https://tu-sinergiacrm.org
#    OAUTH2_CLIENT_ID=tu-client-id
#    OAUTH2_CLIENT_SECRET=tu-client-secret

# 3. Abrir en un navegador o servidor PHP integrado
php -S localhost:8080 index.php
```

**Nota:** Necesitas un cliente OAuth2 con grant type `client_credentials` configurado en el CRM (Administration → OAuth2 Clients).

---

## Configuración

El archivo `.env` contiene toda la configuración. También puedes usar variables de entorno reales.

| Variable | Descripción | Ejemplo |
|----------|-------------|---------|
| `SUITECRM_BASE_URL` | URL base de la instancia SuiteCRM/SinergiaCRM | `https://daniel.sinergiacrm.org` |
| `OAUTH2_CLIENT_ID` | UUID del cliente OAuth2 (grant type: `client_credentials`) | `00000baa-662d-8bf6-...` |
| `OAUTH2_CLIENT_SECRET` | Secret del cliente OAuth2 | `test` |

---

## Herramientas disponibles

Abre `index.php` en un navegador. La página muestra:

### Connection Settings

Tarjeta que muestra la instancia conectada (URL, Client ID, método de autenticación). El botón **Edit** permite cambiar la configuración sin editar el código.

### Active Contacts Relationships by Contact

Busca todas las relaciones activas (`stic_Contacts_Relationships`) de una persona por su ID. Para cada relación obtiene también los datos del proyecto asociado.

**Endpoint:** `GET /Api/V8/module/Contacts/{id}/relationships/stic_contacts_relationships_contacts`

**Parámetros:** `filter[active][eq]=1`, paginación automática.

### Contact Details by ID

Obtiene los datos completos de una persona por su UUID. Devuelve: nombre, teléfonos, emails, identificación, género, idioma, dirección, empleo, canal de adquisición, fechas y descripción.

**Endpoint:** `GET /Api/V8/module/Contacts/{id}`

**Parámetros:** `fields[Contacts]=...` (sparse fieldsets para optimizar la respuesta).

### Dropdown List Values

Busca los valores de cualquier lista desplegable por su clave interna. El sistema busca en campos de módulos comunes y en definiciones de campos de módulos estándar para encontrar la lista.

**Endpoint:** `GET /Api/V8/meta/fields/{module}`

**Estrategia de búsqueda:**
1. Primero busca en módulos comunes (`stic_Contacts_Relationships`, `Contacts`, `Accounts`, `Project`, `Leads`, `Opportunities`) — comprueba el campo `options` y `option_items`
2. Si no encuentra, busca en campos conocidos de Contactos y Cuentas
3. Si aún no encuentra, muestra error con sugerencias

Ejemplos de claves que funcionan:
- `stic_contacts_relationships_types_list` — Tipos de relación
- `account_type_dom` — Tipos de organización
- `gender_list` — Géneros
- `salutation` — Tratamientos (busca automáticamente en el campo `salutation` de Contacts)

### Available Modules

Lista todos los módulos disponibles en la API con sus etiquetas traducidas y permisos ACL.

**Endpoint:** `GET /Api/V8/meta/modules`

Incluye filtro de búsqueda en tiempo real y tabla con desplazamiento.

---

## Endpoints de la API

### Autenticación

```
POST {BASE_URL}/Api/access_token
Content-Type: application/vnd.api+json

{
  "grant_type": "client_credentials",
  "client_id": "{OAUTH2_CLIENT_ID}",
  "client_secret": "{OAUTH2_CLIENT_SECRET}"
}
```

Respuesta:
```json
{
  "access_token": "eyJ...",
  "expires_in": 3600,
  "token_type": "Bearer"
}
```

### Módulos

```
GET /Api/V8/module/{module_name}/{id}
Authorization: Bearer {access_token}
Accept: application/vnd.api+json
```

### Relaciones

```
GET /Api/V8/module/{module_name}/{id}/relationships/{link_name}
Authorization: Bearer {access_token}
```

Parámetros de filtro/paginación:
```
filter[active][eq]=1
page[number]=1
page[size]=50
```

### Meta — Definición de campos

```
GET /Api/V8/meta/fields/{module_name}
Authorization: Bearer {access_token}
```

Devuelve todos los campos con su tipo, opciones (para enum), requerido, etc.

### Meta — Módulos disponibles

```
GET /Api/V8/meta/modules
Authorization: Bearer {access_token}
```

Devuelve todos los módulos con etiquetas y ACLs.

---

## Arquitectura del código

### Modo dual

El archivo `index.php` funciona en dos modos:

**1. API proxy** (cuando `?action=` está presente en la URL):
- PHP actúa como proxy entre el navegador y la API V8 del CRM
- Hace login OAuth2 → ejecuta la acción solicitada → devuelve JSON
- Esto evita exponer las credenciales al navegador

**2. Interfaz web** (cuando no hay `?action=`):
- Renderiza la página HTML completa con CSS y JS
- El JS hace llamadas `fetch` al mismo `index.php?action=...`
- Las respuestas se muestran en las tarjetas de resultado

### Funciones PHP

| Función | Descripción |
|---------|-------------|
| `loadEnv()` | Carga configuración desde `.env` |
| `loadOverrides()` | Carga sobrescrituras de `config-override.json` |
| `getAccessToken()` | Obtiene token OAuth2 `client_credentials` |
| `apiGet(endpoint, token, params)` | Ejecuta petición GET autenticada a la API V8 |
| `fetchRelationships(contactId)` | Pagina y enriquece relaciones con datos de proyecto |
| `fetchContact(contactId)` | Obtiene datos completos de persona |
| `fetchRelationshipTypes()` | Obtiene valores enum de tipo de relación |
| `fetchModules()` | Obtiene lista de módulos disponibles |

### Estructura de directorios

```
v8/
├── index.php              # Cliente web + backend API
├── .env                   # Configuración (no se sube al repo)
├── .env.example           # Plantilla de configuración
├── config-override.json   # Sobrescrituras de UI (no se sube)
└── README.md              # Esta documentación
```

---

## Sobrescritura de configuración desde la UI

Funciona igual que los otros clientes (PortalOauth, v4.1):

1. **Connection Settings** muestra URL, Client ID y método de autenticación
2. Botón **Edit** despliega formulario con 3 campos:
   - SuiteCRM Base URL — URL raíz de la instancia
   - OAuth2 Client ID — UUID del cliente
   - OAuth2 Client Secret — **nunca se muestra** en el HTML (placeholder "Secret configured")
3. **Save Override** escribe `config-override.json`
4. **Revert to .env Defaults** elimina el archivo y vuelve a los valores originales

### Protección del Client Secret

- Si hay un secret configurado en `.env`, el campo muestra el placeholder "Secret configured — type a new one to override"
- El valor del secret **nunca** se escribe en el atributo `value` del `<input>`
- Si se deja el campo vacío al guardar, el secret del `.env` se sigue usando
- Para cambiar el secret, hay que escribir explícitamente un nuevo valor

---

## Seguridad

### Client Credentials no expuestos al navegador
- El `client_secret` se usa solo en PHP (servidor), nunca en JavaScript
- El navegador hace `fetch` al mismo `index.php` (sin credenciales en la URL)
- PHP maneja la autenticación OAuth2 y reenvía los resultados

### Sin secretos en el código
- `.env` y `config-override.json` están en `.gitignore`
- El `client_secret` nunca aparece en el HTML renderizado

---

## Requisitos

- **PHP 7.4+** con extensión **curl**
- Un **cliente OAuth2** con grant type `client_credentials` en el CRM
- La instancia de SinergiaCRM debe tener la API V8 habilitada

---

## Enlaces

- [SinergiaCRM-API-Examples](https://github.com/SinergiaTIC/SinergiaCRM-API-Examples)
- [SuiteCRM V8 API Documentation](https://docs.suitecrm.com/developer/api/api-v8/)
- [JSON:API Specification](https://jsonapi.org/)
