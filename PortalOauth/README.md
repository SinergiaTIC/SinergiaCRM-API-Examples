# SinergiaCRM Portal OAuth2 — Demo Client

Cliente OAuth2 completo para aplicaciones externas que autentican usuarios del portal (Personas / Organizaciones) contra SinergiaCRM. Implementa el flujo **authorization code grant** estándar.

## Tabla de contenidos

1. [Inicio rápido](#inicio-rápido)
2. [Configuración](#configuración)
3. [Preparar credenciales en SinergiaCRM](#preparar-credenciales-en-sinergiacrm)
4. [Integrar el login en una aplicación](#integrar-el-login-en-una-aplicación)
5. [El client_secret: qué hace y por qué importa](#el-client_secret--qué-hace-y-por-qué-importa)
6. [Flujo OAuth2](#flujo-oauth2)
7. [Endpoints del CRM](#endpoints-del-crm)
8. [Respuesta del token](#respuesta-del-token)
9. [Interfaz web](#interfaz-web)
10. [Sobrescritura de configuración desde la UI](#sobrescritura-de-configuración-desde-la-ui)
11. [Arquitectura del código](#arquitectura-del-código)
12. [Seguridad](#seguridad)
13. [Requisitos](#requisitos)
14. [Enlaces](#enlaces)

---

## Inicio rápido

```bash
# 1. Copiar la configuración de ejemplo
cp .env.example .env

# 2. Editar .env con tus credenciales
#    CRM_URL=http://localhost:8000/sinergiacrm
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
| `CRM_URL` | URL del CRM accesible desde el navegador | `http://localhost:8000/sinergiacrm` |
| `CRM_INTERNAL` | URL para llamadas curl servidor-servidor. Workkit Docker usa el hostname interno; fuera de Docker puede quedar vacío para usar `CRM_URL`. | `http://sw-webserver/sinergiacrm` |
| `OAUTH_CLIENT_ID` | UUID del cliente OAuth2 (grant type: `portal_authorization_code`) | `00000bcf-9168-4c63-...` |
| `OAUTH_CLIENT_SECRET` | Opcional. Solo para clientes OAuth2 *confidenciales* (creados con un secreto almacenado). Se envía en el intercambio de tokens (`client_secret`); déjalo vacío para clientes sin secreto. | *(vacío)* |
| `OAUTH_REDIRECT_URI` | URL de callback de este cliente. Debe coincidir exactamente con la configurada en el OAuth2 Client del CRM. | `http://localhost:8000/SinergiaCRM-API-Examples/PortalOauth/callback.php` |

La tarjeta **Connection Settings → Edit** también permite cambiar la URL de callback
para el navegador actual. El valor se guarda en `localStorage` de ese navegador y se
envía tanto en la solicitud de autorización como en el intercambio del código; debe
coincidir con la URL registrada en el cliente OAuth2. **Revert to Code Defaults** elimina
los overrides y vuelve a usar `OAUTH_REDIRECT_URI`.

---

## Preparar credenciales en SinergiaCRM

Antes de integrar la plataforma, crea un cliente OAuth2 de portal en la instancia de
SinergiaCRM:

1. Entra en SinergiaCRM con un usuario administrador.
2. Abre **Administración → OAuth2 Clients → New Portal Client (Authorization Code)**.
3. Completa **Name** con un nombre reconocible para la integración.
4. En **Redirect URL**, registra la URL HTTPS del callback de tu aplicación, por ejemplo
   `https://plataforma.example.org/auth/sinergiacrm/callback`. Debe ser la misma que la
   aplicación enviará como `redirect_uri`; se recomienda usar coincidencia exacta.
5. Elige el tipo de cliente:
   - **Backend/confidencial**: marca **Is Confidential** y define **Change secret**. Copia
     el secreto antes de guardar: SinergiaCRM guarda su hash y no permite recuperarlo
     después. El secreto debe quedarse en el servidor de la plataforma.
   - **Público**: deja **Is Confidential** desmarcado y no configures secreto. Nunca
     incluyas un secreto en JavaScript, una app móvil o código distribuido al usuario.
6. Guarda el cliente. La pantalla del cliente muestra su UUID; úsalo como
   `OAUTH_CLIENT_ID`. SinergiaCRM asigna automáticamente el grant
   `portal_authorization_code` al crear el cliente desde esta opción.
7. Guarda `OAUTH_CLIENT_ID`, el callback registrado y, si aplica, el secreto en la
   configuración privada del servidor de la aplicación. No los pongas en el repositorio.

El cliente OAuth identifica **la aplicación**; cada Persona u Organización inicia sesión
con su propia cuenta del portal. Comprueba también que los registros que deban entrar
tienen habilitado el acceso al portal y un usuario/contraseña válido.

### Preparar Personas y Organizaciones que iniciarán sesión

Comprueba que el registro de **Personas** o **Organizaciones** tiene un email principal
válido. Para enviar la invitación a la aplicación:

1. Abre el registro y pulsa **Portal Actions** en la vista de detalle, o selecciona uno o
   varios registros en la vista de lista y pulsa **Portal Actions** en el menú de acciones.
2. Elige **Send Invitation Email** y la aplicación OAuth2 de destino; pulsa **Execute**.
3. La persona recibe el enlace para establecer su contraseña y, al terminar, vuelve a la
   aplicación seleccionada.

También se puede elegir **Send Password Reset** para enviar un enlace de restablecimiento.
Estas acciones están disponibles para los usuarios del CRM que tienen acceso al módulo y
al registro; **no son exclusivas de administradores**. Al enviar una invitación, SinergiaCRM
activa el acceso al portal y, si falta **Portal Username**, usa el email principal del
registro. 

---

## Integrar el login en una aplicación

La integración recomendada para una plataforma web es hacer el intercambio de código en
su **backend**. La aplicación no recibe ni solicita la contraseña del usuario; la persona
se autentica directamente en SinergiaCRM y la plataforma recibe el perfil del portal.

### 1. Iniciar la autorización

Al pulsar «Entrar con SinergiaCRM», genera un `state` impredecible y guárdalo en la sesión
del navegador antes de redirigir. Usa la URL pública del CRM (accesible desde el navegador)
y envía el callback registrado en SinergiaCRM:

```php
<?php
session_start();

$crmUrl = 'https://crm.example.org/sinergiacrm';
$clientId = getenv('OAUTH_CLIENT_ID');
$redirectUri = 'https://plataforma.example.org/auth/sinergiacrm/callback';

$state = bin2hex(random_bytes(32));
$_SESSION['sinergiacrm_oauth_state'] = $state;

$authorizeUrl = rtrim($crmUrl, '/') . '/index.php?' . http_build_query([
    'entryPoint' => 'sticPortalLogin',
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'state' => $state,
]);

header('Location: ' . $authorizeUrl, true, 302);
exit;
```

No aceptes un `redirect_uri` arbitrario del navegador: usa el valor fijo configurado para
esa integración y registrado en el cliente OAuth2.

### 2. Validar callback e intercambiar el código

SinergiaCRM redirige al callback con `code` y `state`. Compara `state` con el valor guardado
en la sesión y consúmelo una sola vez. Después canjea el código **desde el servidor**:

```php
<?php
session_start();

$expectedState = $_SESSION['sinergiacrm_oauth_state'] ?? '';
$receivedState = $_GET['state'] ?? '';
unset($_SESSION['sinergiacrm_oauth_state']);

if ($expectedState === '' || !hash_equals($expectedState, $receivedState)) {
    http_response_code(400);
    exit('Respuesta OAuth inválida (state).');
}

if (!empty($_GET['error'])) {
    http_response_code(401);
    exit('SinergiaCRM no autorizó el acceso.');
}

$crmInternal = getenv('CRM_INTERNAL') ?: 'https://crm.example.org/sinergiacrm';
$clientId = getenv('OAUTH_CLIENT_ID');
$clientSecret = getenv('OAUTH_CLIENT_SECRET') ?: ''; // Solo backend confidencial
$redirectUri = 'https://plataforma.example.org/auth/sinergiacrm/callback';
$code = $_GET['code'] ?? '';
if ($code === '') {
    http_response_code(400);
    exit('Falta el código de autorización.');
}

$form = [
    'grant_type' => 'authorization_code',
    'code' => $code,
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
];
if ($clientSecret !== '') {
    $form['client_secret'] = $clientSecret;
}

$ch = curl_init(rtrim($crmInternal, '/') . '/index.php?entryPoint=sticPortalOAuthToken');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($form),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
]);
$body = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$tokenResponse = json_decode($body ?: '', true);
if ($status !== 200 || !is_array($tokenResponse) || !empty($tokenResponse['error'])) {
    http_response_code(401);
    exit('No se pudo completar el intercambio OAuth.');
}

// Guarda tokens y perfil en la sesión/almacenamiento privado de tu aplicación.
$_SESSION['portal_identity'] = [
    'portal_type' => $tokenResponse['portal_type'],
    'portal_id' => $tokenResponse['portal_id'],
    'user' => $tokenResponse['user'] ?? null,
    'relationships' => $tokenResponse['relationships'] ?? [],
];
$_SESSION['portal_access_token'] = $tokenResponse['access_token'];
$_SESSION['portal_refresh_token'] = $tokenResponse['refresh_token'];
```

Adapta el ejemplo al framework de tu plataforma: usa su sesión y manejo de errores, y no
imprimas tokens/secreto en logs o en la respuesta HTML.

### 3. Crear la sesión de la plataforma

El intercambio incluye `portal_type` (`Contact` o `Account`), `portal_id`, `user` y
`relationships`. Vincula la cuenta local usando **el par `portal_type` + `portal_id`**; no
uses únicamente el email como identificador, porque puede cambiar o no ser único entre
tipos de registro. Aplica en la plataforma sus propias reglas de alta, permisos y sesión.

### 4. Renovar tokens

El access token caduca en una hora y el refresh token dura 30 días. Cuando renueves, envía
`grant_type=refresh_token`, `refresh_token` y `client_id` al mismo endpoint. Incluye también
`client_secret` para clientes confidenciales. La respuesta contiene nuevos tokens y el
refresh token anterior queda revocado: reemplázalo de forma segura en tu almacenamiento.

---

## El client_secret — qué hace y por qué importa

### Qué autentica: la aplicación, no el usuario

En el flujo hay **dos identidades** que el CRM debe verificar:

1. **El usuario** — la página de login (`sticPortalLogin`) comprueba sus credenciales (contraseña/magic link). Esto ya está resuelto antes de que intervenga el secreto.
2. **La aplicación (cliente OAuth2)** — cuando tu `callback.php` canjea el código por tokens (`POST sticPortalOAuthToken`), el CRM debe saber que *el que pregunta* es realmente la aplicación registrada y no un tercero que se ha hecho con un código o un token. Eso es exactamente lo que demuestra el `client_secret`.

### Cliente público vs. confidencial

| | Sin secreto (cliente *público*) | Con secreto (cliente *confidencial*) |
|---|---|---|
| Al crear el cliente | **Is Confidential** desmarcado, sin *Change Secret* | **Is Confidential** marcado + *Change Secret* |
| `client_secret` en el intercambio | No se envía | **Obligatorio** |
| Respuesta del CRM sin secreto | `200` (no se comprueba) | `400 {"error":"invalid_client"}` |
| Precaución frente a | — | El CRM compara `sha256(client_secret)` con el hash almacenado; un secreto erróneo también da `invalid_client` |

En este demo: si tu `.env` define `OAUTH_CLIENT_SECRET`, `callback.php` lo envía en el intercambio; si lo dejas vacío, no lo envía y el CRM no lo exige. La regla la pone el **cliente**, no el demo.

### ¿Por qué importa? Tres casos reales

**1. Robo del código de autorización.** El código viaja por el navegador
(`redirect_uri?code=...&state=...`) y puede filtrarse (logs, historial del navegador,
proxies, un redirect mal configurado). Sin secreto, un atacante que robe el código solo
necesita el `client_id` — que es **público** (aparece en la propia URL del login:
`?client_id=4cf95c5a-…`) — para canjearlo y obtener los tokens + perfil completo del
usuario. Con secreto, el código robado es **inservible** sin la configuración de tu app.

**2. Robo de un refresh token.** Los refresh tokens duran 30 días y rotan. Si uno se filtra
(base de datos, logs, XSS), el atacante intenta renovarlo indicando tu `client_id`. El
CRM ya liga el token a su cliente, pero el `client_id` se puede adivinar; el `client_secret`
es lo que impide que el robo se convierta en tokens nuevos — necesitaría además tu `.env`.

**3. Auditoría y rotación.** Cada integración tiene su propio secreto: puedes revocar/rotar
el de una app sin afectar a las demás (Administración → OAuth2 Clients → *Change Secret*),
y los intentos de canje con secreto incorrecto quedan registrados (`invalid_client`) para
detectar abusos.

### ¿Cuándo NO debes poner secreto?

Cuando la aplicación se ejecuta donde el "secreto" llegaría al usuario final: **SPA en el
navegador, móvil o escritorio**. Un secreto dentro del bundle es público de todas formas,
así que configurarlo no añade seguridad. Esta implementación no negocia PKCE; por ello, para
una plataforma web se recomienda un callback backend (patrón *backend-for-frontend*) que
mantenga los tokens fuera del navegador. Si aun así integras un cliente público, déjalo sin
secreto y evalúa explícitamente el riesgo de que un código o token expuesto sea utilizado
por terceros; `state`, el redirect registrado y el código de un solo uso no sustituyen PKCE.

> **Resumen:** pon `OAUTH_CLIENT_SECRET` cuando tu `callback.php` corre en **servidor
> propio** (PHP, Node, etc.) y quieres que quien tenga un código o token suelto no pueda
> canjearlo sin acceso a tu configuración. Déjalo vacío en apps públicas o de prueba.

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
client_secret={client_secret}     ← solo si está definido (cliente confidencial)
```

El CRM valida el código y devuelve los tokens. Si el cliente tiene un secreto almacenado
y el `client_secret` no coincide, responde `400 {"error":"invalid_client"}` — ver
[El client_secret: qué hace y por qué importa](#el-client_secret--qué-hace-y-por-qué-importa).

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
      "decidim_excluded": 0
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
| `decidim_excluded` | Excluido de Decidim |

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

La tarjeta **Connection Settings** permite personalizar la configuración para el navegador actual:

1. Haz clic en **Edit**
2. Cambia CRM URL, Internal URL, o Client ID
3. Haz clic en **Save for this browser** → guarda en el `localStorage` de este navegador
4. Para volver a los valores de `.env`/`config.php`, haz clic en **Revert to Code Defaults**

Las opciones no se escriben en archivos compartidos del servidor ni afectan a otros
usuarios o navegadores. Al iniciar sesión, la configuración seleccionada se envía por
POST y se conserva solo en la sesión PHP de ese flujo para que `callback.php` pueda hacer
el intercambio server-to-server. El secreto guardado por el usuario permanece en su
`localStorage`; el secreto por defecto de `.env`/`config.php` no se expone al navegador.

### Orden de prioridad

1. Configuración personalizada guardada en el `localStorage` de este navegador
2. `.env` — configuración predeterminada principal
3. `config.php` — fallback legacy

---

## Arquitectura del código

### `index.php`

```
loadPortalConfig()        → Carga config desde .env o config.php
                           → Carga overrides desde localStorage en el navegador
                           → POST de inicio: guarda config transitoria en sesión PHP
                           → Genera state CSRF + redirección al login del CRM
                           → Renderiza HTML con config card + settings form
```

### `callback.php`

```
loadPortalConfig()        → Misma función de carga de config
                           → Recupera config de este flujo desde sesión PHP
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

### Configuración aislada por navegador
- El archivo `.env` está en `.gitignore`
- Los overrides de la interfaz se guardan en `localStorage`, no en un fichero compartido del servidor
- La copia de configuración en sesión PHP dura solo lo necesario para completar el flujo OAuth
- `localStorage` es accesible a JavaScript del mismo origen: en una aplicación real, no guardes ahí un secreto de producción; usa un cliente público o gestiona el secreto en tu backend
- Las variables de entorno se leen con `getenv()` como alternativa

### El `client_secret` del cliente OAuth2
El secreto autentica la **aplicación** en el intercambio de tokens (no al usuario): sin él,
cualquiera que consiga un código de autorización o un refresh token podría canjearlo con
solo conocer el `client_id` (público). Ver la sección
[El client_secret: qué hace y por qué importa](#el-client_secret--qué-hace-y-por-qué-importa)
para cuándo configurarlo y cuándo dejarlo vacío.

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
