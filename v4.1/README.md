# SinergiaCRM API v4.1 — Demo Client

Cliente web completo para la API REST v4.1 de SinergiaCRM (SuiteCRM) con autenticación por usuario/contraseña.

La página incluye **← All API examples** para volver al catálogo y **Read Docs ↗** para abrir esta documentación en GitHub.

## Tabla de contenidos

1. [Inicio rápido](#inicio-rápido)
2. [Configuración](#configuración)
3. [Interfaz web](#interfaz-web)
4. [Funciones API disponibles](#funciones-api-disponibles)
5. [Ejemplos PHP originales](#ejemplos-php-originales)
6. [Autenticación](#autenticación)
7. [Sobrescritura de configuración desde la UI](#sobrescritura-de-configuración-desde-la-ui)
8. [Arquitectura del código](#arquitectura-del-código)
9. [Referencia rápida de API](#referencia-rápida-de-api)
10. [Requisitos](#requisitos)
11. [Enlaces](#enlaces)

---

## Inicio rápido

```bash
# 1. Copiar la configuración de ejemplo
cp .env.example .env

# 2. Editar .env con tus credenciales
#    CRM_URL=http://sw-webserver/sinergiacrm
#    CRM_USER=tu-usuario
#    CRM_PASSWORD=tu-password

# 3. Abrir en un navegador o servidor PHP integrado
php -S localhost:8080 index.php
```

---

## Configuración

El archivo `.env` contiene toda la configuración del cliente. No se sube al repositorio (está en `.gitignore`).

| Variable | Tipo | Descripción | Ejemplo |
|----------|------|-------------|---------|
| `CRM_URL` | string | URL del Workkit accesible desde PHP-FPM | `http://sw-webserver/sinergiacrm` |
| `API_PATH` | string | Ruta relativa del endpoint REST v4.1 | `/custom/service/v4_1_SticCustom/rest.php` |
| `CRM_USER` | string | Usuario del CRM con permisos de API | `sinergiacrm` |
| `CRM_PASSWORD` | string | Contraseña del usuario del CRM | (tu contraseña) |
| `API_LANGUAGE` | string | Idioma para las interacciones con la API | `es_ES`, `ca_ES`, `gl_ES`, `en_us` |

> **Workkit local:** las llamadas REST las hace PHP-FPM dentro de Docker; por ello el
> default es `http://sw-webserver/sinergiacrm`. Si ejecutas el cliente fuera de los
> contenedores, usa `http://localhost:8000/sinergiacrm`.

### Variables de entorno (alternativa)

También puedes usar variables de entorno reales en lugar del archivo `.env`:

```bash
export CRM_URL=http://sw-webserver/sinergiacrm
export CRM_USER=tu-usuario
export CRM_PASSWORD=tu-password
export API_LANGUAGE=es_ES
```

---

## Interfaz web

Abre `index.php` en un navegador para acceder a la interfaz web con las siguientes herramientas:

### Connection Settings

Tarjeta que muestra la instancia a la que está conectado el cliente:
- URL del CRM
- Ruta del endpoint API
- Nombre de usuario

El botón **Edit** despliega el formulario de configuración. **Save for this browser** guarda
las sobrescrituras en el `localStorage` de este navegador; cada petición las envía en un
header de esa petición y el servidor no las persiste. **Revert to .env Defaults** elimina
los overrides locales.

### Dropdown List Lookup

Busca cualquier lista desplegable del CRM por su clave interna (`app_list_strings`). Útil para obtener los valores de enumeraciones, tipos, estados, etc.

Ejemplos de claves:
- `stic_contacts_relationships_types_list` — Tipos de relación
- `account_type_dom` — Tipos de organización
- `gender_list` — Géneros
- `stic_identification_type_list` — Tipos de identificación
- `industry_dom` — Sectores/industrias
- `lead_source_dom` — Orígenes de interesados

Método API usado: `get_language_definition` con `modules: 'app_list_strings'`

### Get Record by ID

Obtiene un registro completo por su UUID. Selecciona el módulo y escribe el ID del registro. La respuesta contiene todos los campos y sus valores.

Método API usado: `get_entry`

| Parámetro | Descripción |
|-----------|-------------|
| `module_name` | Módulo del registro |
| `id` | UUID del registro |
| `select_fields` | Campos a devolver (vacío = todos) |

### Get Module Fields

Obtiene la definición de todos los campos de un módulo: nombre, tipo, requerido, opciones (para enum), etc.

Método API usado: `get_module_fields`

| Parámetro | Descripción |
|-----------|-------------|
| `module_name` | Módulo a consultar |
| `fields` | Campos específicos (vacío = todos) |

### Get Relationships

Obtiene los registros relacionados con un registro dado. Necesitas el módulo, el ID del registro y el nombre del campo de enlace (link field).

Método API usado: `get_relationships`

| Parámetro | Descripción |
|-----------|-------------|
| `module_name` | Módulo del registro principal |
| `module_id` | UUID del registro principal |
| `link_field_name` | Nombre del campo de relación |
| `deleted` | 0 = solo activos, 1 = solo eliminados |
| `limit` | Máximo de registros a devolver |

### Set Relationship

La tarjeta **Set Relationship** crea vínculos entre un registro principal y uno o más
registros existentes. Introduce el módulo y UUID principal, el nombre del link field y los
UUID relacionados separados por comas.

Método API usado: `set_relationship`:

```json
{
  "session": "session_id",
  "module_name": "Contacts",
  "module_id": "contact-uuid",
  "link_field_name": "accounts",
  "related_ids": ["account-uuid"]
}
```

La interfaz admite de 1 a 100 IDs relacionados por petición. Usa el entorno de pruebas y
un link field válido; la operación modifica relaciones en el CRM.

### Get Language Definition

Obtiene todas las cadenas de idioma del CRM (`app_list_strings`, `app_strings`, `mod_strings`). La respuesta contiene TODAS las listas desplegables, etiquetas de módulos y textos de la interfaz.

Método API usado: `get_language_definition`

| Parámetro | Descripción |
|-----------|-------------|
| `modules` | `'app_list_strings'` para listas, `'Contacts'` para etiquetas de un módulo |

### Set Entry (Create / Update)

Crea o actualiza un registro enviando pares nombre-valor. Si proporcionas un ID de registro existente se actualiza; si está vacío se crea uno nuevo.

Método API usado: `set_entry`

| Parámetro | Descripción |
|-----------|-------------|
| `module_name` | Módulo donde crear/actualizar |
| `name_value_list` | Array de pares `{name, value}` |
| `id` (opcional) | UUID del registro a actualizar |

---

## Funciones API disponibles

La API v4.1 expone los siguientes métodos a través del endpoint `v4_1_SticCustom/rest.php`:

### Gestión de sesión

| Método | Descripción |
|--------|-------------|
| `login` | Inicia sesión y devuelve un `session_id` |
| `logout` | Cierra la sesión |

### GET (consulta)

| Método | Descripción |
|--------|-------------|
| `get_entry` | Obtener un registro por ID |
| `get_entry_list` | Obtener lista de registros con filtros |
| `get_entries` | Obtener múltiples registros por sus IDs |
| `get_module_fields` | Obtener definición de campos de un módulo |
| `get_relationships` | Obtener registros relacionados |
| `get_available_modules` | Listar módulos accesibles vía API |
| `get_language_definition` | Obtener cadenas de idioma |
| `get_document_revision` | Obtener revisión de documento |
| `get_image` | Obtener imagen almacenada en el CRM |

### SET (creación/actualización)

| Método | Descripción |
|--------|-------------|
| `set_entry` | Crear o actualizar un registro |
| `set_entries` | Crear o actualizar múltiples registros |
| `set_relationship` | Crear una relación entre registros |
| `set_document_revision` | Subir una nueva revisión de documento |
| `set_image` | Subir una imagen |

### Otros

| Método | Descripción |
|--------|-------------|
| `search_by_module` | Búsqueda global |
| `get_note_attachment` | Obtener adjunto de nota |
| `set_note_attachment` | Adjuntar archivo a nota |

---

## Ejemplos PHP originales

El directorio `REST/PHP/` contiene los ejemplos originales en PHP para ejecutar por consola o incluir en scripts. El flujo es:

1. Configurar credenciales en `app.php`
2. Incluir y ejecutar `app.php` (hace login, ejecuta ejemplos, hace logout)
3. Descomentar los ejemplos que se quieran ejecutar

### Ejemplos GET

| Archivo | Descripción |
|---------|-------------|
| `Ejemplos/GET/get_available_modules.php` | Listar todos los módulos con sus ACLs |
| `Ejemplos/GET/get_entry.php` | Obtener un registro por ID |
| `Ejemplos/GET/get_entry_list.php` | Obtener lista de registros con filtros |
| `Ejemplos/GET/get_module_fields.php` | Obtener definición de campos de un módulo |
| `Ejemplos/GET/get_relationships.php` | Obtener relaciones de un registro |
| `Ejemplos/GET/get_language_definition.php` | Obtener definición de idioma y listas |
| `Ejemplos/GET/get_document_revision.php` | Obtener documento |
| `Ejemplos/GET/get_image.php` | Obtener imagen |

### Ejemplos SET

| Archivo | Descripción |
|---------|-------------|
| `Ejemplos/SET/set_entry.php` | Crear o actualizar registro |
| `Ejemplos/SET/set_relationship.php` | Crear relación entre registros |
| `Ejemplos/SET/set_document_revision.php` | Subir documento |
| `Ejemplos/SET/set_image.php` | Subir imagen |

### Cliente JavaScript (REST)

El directorio `REST/Javascript/` contiene un ejemplo de cliente REST v4.1 en JavaScript puro:
- `app.html` — Interfaz web
- `rest.js` — Cliente REST (login, llamadas, logout)

---

## Autenticación

La API v4.1 usa autenticación por usuario/contraseña con **MD5**:

```
POST {CRM_URL}{API_PATH}
Content-Type: multipart/form-data

method=login
input_type=JSON
response_type=JSON
rest_data={"user_auth":{"user_name":"...","password":"<md5>"},"application_name":"...","name_value_list":[...]}
```

El servidor devuelve:

```json
{
  "id": "session_id_value",
  "module_name": "Users",
  "name_value_list": { ... }
}
```

El `session_id` obtenido se pasa en todas las llamadas posteriores como parámetro `session`.

### Flujo completo

1. **POST** `login` → obtener `session_id`
2. **POST** `get_entry` / `set_entry` / etc. con `session` + parámetros
3. **POST** `logout` con `session` → cerrar sesión

El `password` se envía como **hash MD5** en texto plano — no es seguro para producción sin HTTPS.

---

## Sobrescritura de configuración desde la UI

La interfaz web incluye una tarjeta **Connection Settings** que permite:

1. **Ver** la configuración actual (URL, endpoint, usuario)
2. **Editar** haciendo clic en el botón **Edit**
3. **Save for this browser** guarda las sobrescrituras solo en el `localStorage` de ese navegador
4. **Revert to .env Defaults** borra los valores locales y vuelve a los valores por defecto

Las opciones del navegador no se guardan en `config-override.json` ni se comparten con otros
usuarios. Cada llamada API las envía a PHP en un header de esa petición, donde se usan solo
para esa ejecución. Los archivos `config-override.json` heredados se ignoran.

La contraseña **nunca se muestra** en la interfaz — se muestra el texto "Password configured" si está definida. Para cambiarla hay que escribir un nuevo valor en el campo de contraseña.

### Orden de prioridad de configuración

1. Override de `localStorage` (si existe)
2. `.env` — valores por defecto del servidor

---

## Arquitectura del código

### `index.php`

Archivo principal con dos modos de funcionamiento:

1. **GET** — Muestra la interfaz web HTML con todas las herramientas
2. **POST** con `api_action` — Ejecuta una llamada a la API y devuelve JSON

**Funciones principales:**

```
loadV4Config()          → Carga configuración desde .env / config.php
restCall(url, method, params) → Ejecuta llamada REST v4.1
```

**Flujo de una llamada API desde la UI:**

1. El JS hace `fetch('', { method: 'POST', body: formData })` al mismo `index.php` e incluye los overrides de este navegador en `X-V4-API-Config`
2. PHP recibe `$_POST['api_action']`, aplica esos valores solo a la petición y ejecuta la rama correspondiente
3. PHP hace login → ejecuta el método → logout
4. PHP devuelve JSON con el resultado
5. JS renderiza el JSON en el panel de resultado

**Endpoint REST v4.1:**

```
POST {CRM_URL}{API_PATH}
method={método}
input_type=JSON
response_type=JSON
rest_data={parámetros en JSON}
```

### `APIClient.php`

Clase PHP original que encapsula todas las llamadas a la API v4.1. Usada por los ejemplos de consola.

### Estructura de directorios

```
v4.1/
├── index.php              # Interfaz web + backend API
├── .env                   # Configuración (no se sube al repo)
├── .env.example           # Plantilla de configuración
├── README.md              # Esta documentación
└── REST/
    ├── PHP/
    │   ├── app.php        # Script principal de ejemplos
    │   ├── APIClient.php  # Clase cliente REST v4.1
    │   └── Ejemplos/
    │       ├── GET/       # Ejemplos de consulta
    │       └── SET/       # Ejemplos de creación/actualización
    └── Javascript/
        ├── app.html       # Cliente web JS
        └── rest.js        # Librería REST en JS
```

---

## Referencia rápida de API

### Formato de llamada

```
POST {crm_url}/custom/service/v4_1_SticCustom/rest.php

method={method}
input_type=JSON
response_type=JSON
rest_data={params_json}
```

### Login

```json
{
  "user_auth": {
    "user_name": "usuario",
    "password": "md5hash"
  },
  "application_name": "Mi App",
  "name_value_list": [
    {"name": "notifyonsave", "value": false},
    {"name": "language", "value": "es_ES"}
  ]
}
```

### get_entry

```json
{
  "session": "session_id",
  "module_name": "Contacts",
  "id": "uuid-del-registro",
  "select_fields": [],
  "link_name_to_fields_array": []
}
```

### get_entry_list

```json
{
  "session": "session_id",
  "module_name": "Contacts",
  "query": "",
  "order_by": "",
  "offset": 0,
  "select_fields": ["first_name", "last_name", "email1"],
  "link_name_to_fields_array": [],
  "max_results": 20,
  "deleted": 0
}
```

### set_entry

```json
{
  "session": "session_id",
  "module_name": "Contacts",
  "name_value_list": [
    {"name": "first_name", "value": "John"},
    {"name": "last_name", "value": "Doe"},
    {"name": "email1", "value": "john@example.com"}
  ]
}
```

### get_relationships

```json
{
  "session": "session_id",
  "module_name": "Contacts",
  "module_id": "uuid",
  "link_field_name": "accounts",
  "related_module_query": "",
  "related_fields": ["name", "account_type"],
  "related_module_link_name_to_fields_array": [],
  "deleted": 0,
  "order_by": "",
  "offset": 0,
  "limit": 100
}
```

---

## Requisitos

- **PHP 7.4+** con extensión **curl** habilitada
- Una instancia de **SinergiaCRM** (SuiteCRM) con el endpoint v4.1 expuesto
- Un **usuario del CRM** con permisos de API (rol con acceso a los módulos necesarios)
- **HTTPS** recomendado para producción (la contraseña viaja como MD5, fácilmente descifrable)

---

## Enlaces

- [Documentación oficial de API v4.1 de SuiteCRM](https://docs.suitecrm.com/developer/api/api-v4.1-methods/)
- [SinergiaCRM-API-Examples en GitHub](https://github.com/SinergiaTIC/SinergiaCRM-API-Examples)
- [SinergiaCRM](https://www.sinergiacrm.org)
