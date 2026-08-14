# SinergiaCRM-API-Examples

Colección de clientes demo para las diferentes APIs de SinergiaCRM. Abre `index.php` en un navegador o en un servidor PHP para ver la página de inicio con todos los clientes disponibles.

## Clientes disponibles

### Portal OAuth2 Demo (`PortalOauth/`)
Cliente OAuth2 completo para apps externas que autentican usuarios del portal (Personas / Organizaciones). Implementa el flujo *authorization code grant*. La app nunca ve la contraseña del usuario.

- **Autenticación:** `portal_authorization_code` OAuth2 grant
- **Endpoints usados:** `sticPortalLogin`, `sticPortalOAuthToken`
- **Funciones:** Login con SinergiaCRM → intercambio de código por tokens → perfil del usuario + relaciones
- **Configuración:** `.env` (copiar `.env.example`)
- **Documentación:** [PortalOauth/README.md](PortalOauth/README.md)

### V8 API Client (`v8/`)
Cliente web para la API REST V8 de SuiteCRM usando el grant `client_credentials`. Permite explorar contactos, relaciones, valores de listas desplegables y módulos.

- **Autenticación:** `client_credentials` OAuth2 grant
- **Endpoints usados:** `/Api/access_token`, `/Api/V8/module/*`, `/Api/V8/meta/*`
- **Funciones:**
  - Buscar relaciones activas por persona (con datos de proyecto)
  - Obtener detalles de persona por ID
  - Buscar valores de cualquier lista desplegable por su clave
  - Listar módulos disponibles con ACLs
- **Configuración:** `.env` (copia `.env.example`)
- **Documentación:** [v8/README.md](v8/README.md)

### API v4.1 Client (`v4.1/`)
Cliente web para la API REST v4.1 clásica con autenticación por usuario/contraseña.

- **Autenticación:** Usuario / contraseña (md5)
- **Endpoint:** `v4_1_SticCustom/rest.php`
- **Funciones:**
  - Buscar valores de cualquier lista desplegable por su clave
  - Obtener un registro por ID (get_entry)
  - Obtener definición de campos de un módulo (get_module_fields)
  - Obtener relaciones de un registro (get_relationships)
  - Obtener definición de idioma (get_language_definition)
  - Crear/actualizar registros (set_entry)
- **Configuración:** `.env` (copia `.env.example`)

## Configuración

Cada cliente usa un archivo `.env` para su configuración. Copia el `.env.example` de cada directorio y edita los valores.

### Sobrescritura de configuración desde la UI

Cada cliente tiene una tarjeta **Connection Settings** en su interfaz que muestra la instancia a la que está conectado. Puedes editar y guardar las opciones desde la UI sin modificar los archivos de código:

1. Haz clic en **Edit** en la tarjeta Connection Settings
2. Cambia los valores (URL, client ID, credenciales, etc.)
3. Haz clic en **Save Override** → se guarda en `config-override.json`
4. Para volver a los valores por defecto, haz clic en **Revert**

Los archivos `config-override.json` son locales y no se suben al repositorio (están en `.gitignore`).

Las contraseñas nunca se muestran en el HTML — si están configuradas, se muestra "configured" y solo se sobrescriben si se escribe un nuevo valor.

## Requisitos

- PHP 7.4+ con curl
- Una instancia de SinergiaCRM accesible
- Credenciales (usuario/contraseña para v4.1, client_id/secret para V8, client_id para PortalOauth)

## SinergiaCRM

[SinergiaCRM](https://www.sinergiacrm.org/es) es una iniciativa de la Asociación SinergiaTIC, una entidad sin ánimo de lucro. SinergiaCRM se basa en [SuiteCRM](https://github.com/suitecrm/suitecrm).

[Website](https://www.sinergiacrm.org) |
[Manual de uso](https://wikisuite.sinergiacrm.org/index.php?title=Manual_de_SinergiaCRM) |
[Manual de instalación](https://github.com/SinergiaTIC/SinergiaCRM-SuiteCRM/wiki) |
[Wiki](https://github.com/SinergiaTIC/SinergiaCRM-API-Examples/wiki)
