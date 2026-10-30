<?php
/**
 * SinergiaCRM Portal OAuth — Callback Endpoint
 *
 * Handles the second leg of the OAuth2 authorization code flow.
 * The CRM redirects the user here with `?code=...&state=...` after a
 * successful login. This endpoint:
 *
 * 1. Validates the anti-CSRF `state` parameter against the cookie.
 * 2. Performs a server-side `curl` POST to exchange the authorization
 *    code for access and refresh tokens plus the portal identity.
 * 3. Renders token info and the portal ID, type, and email.
 *
 * If no authorization code is present the user is redirected back to
 * index.php to start the login flow.
 */

/**
 * Load Portal OAuth configuration from .env file or legacy config.php fallback.
 *
 * Reads key-value pairs from `.env` (preferred) or falls back to the legacy
 * `config.php` PHP array file if no .env variables are found.
 *
 * @return array{
 *     crm_url: string,
 *     crm_internal: string,
 *     client_id: string,
 *     redirect_uri: string
 * }
 */
function loadPortalConfig(): array
{
    $envFile = __DIR__ . '/.env';
    $vars = [];
    if (file_exists($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            [$key, $value] = explode('=', $line, 2);
            $vars[trim($key)] = trim($value);
        }
    }
    if (empty($vars)) {
        $cfgFile = __DIR__ . '/config.php';
        if (file_exists($cfgFile)) $vars = require $cfgFile;
    }
    return [
        'crm_url'       => $vars['CRM_URL'] ?? $vars['crm_url'] ?? 'http://localhost:8000/sinergiacrm',
        'crm_internal'  => $vars['CRM_INTERNAL'] ?? $vars['crm_internal'] ?? '',
        'client_id'     => $vars['OAUTH_CLIENT_ID'] ?? $vars['client_id'] ?? '',
        'client_secret' => $vars['OAUTH_CLIENT_SECRET'] ?? $vars['client_secret'] ?? '',
        'redirect_uri'  => $vars['OAUTH_REDIRECT_URI'] ?? $vars['redirect_uri'] ?? '',
    ];
}

/** @var array $config Server defaults, overlaid with this browser's one-flow settings. */
$config = loadPortalConfig();
session_name('PORTALOAUTHDEMOSESSID');
session_set_cookie_params([
  'lifetime' => 0,
  'path'     => rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/',
  'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
  'httponly' => true,
  'samesite' => 'Lax',
]);
session_start();
$error = '';
$data  = null;

/**
 * OAuth2 authorization code → token exchange (server-side POST via curl).
 *
 * Only runs on GET requests that carry the authorization `code` parameter.
 * Validates the `state` parameter to prevent CSRF attacks, then POSTs to
 * the CRM's token endpoint to obtain access + refresh tokens.
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['code'])) {
  $code  = $_GET['code'];
  $state = $_GET['state'] ?? '';
  $flow = $_SESSION['portal_oauth_flows'][$state] ?? null;
  if ($state === ''
      || !hash_equals((string) ($_COOKIE['oauth_demo_state'] ?? ''), $state)
      || !is_array($flow)
      || ($flow['expires_at'] ?? 0) < time()
      || !is_array($flow['config'] ?? null)) {
    $error = 'Parámetro state no válido. Posible ataque CSRF o sesión caducada.';
  } else {
    // Consume the one-time flow settings from this browser's PHP session. No
    // per-user settings are written to a shared JSON file or database table.
    unset($_SESSION['portal_oauth_flows'][$state]);
    $config = array_merge($config, $flow['config']);
    $crmBase = rtrim(trim((string) ($config['crm_internal'] ?? '')) ?: $config['crm_url'], '/');
    $tokenUrl = $crmBase . '/index.php?entryPoint=sticPortalOAuthToken';
    $clientId = $config['client_id'];
    $clientSecret = $config['client_secret'] ?? '';
    $redirectUri = $config['redirect_uri'];

    $postFields = [
      'grant_type'    => 'authorization_code',
      'code'          => $code,
      'client_id'     => $clientId,
      'redirect_uri'  => $redirectUri,
    ];
    // Confidential clients (those with a stored secret) must authenticate on the
    // token exchange. Only included when configured.
    if ($clientSecret !== '') {
      $postFields['client_secret'] = $clientSecret;
    }
    $ch = curl_init($tokenUrl);
    curl_setopt_array($ch, [
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => http_build_query($postFields),
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT        => 10,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    if (!$response) {
      $error = 'No se pudo conectar con el endpoint de tokens.';
    } else {
      $data = json_decode($response, true);
    }
  }
  // Clear state cookie once consumed (single-use, prevents replay).
  setcookie('oauth_demo_state', '', [
    'expires'  => time() - 3600,
    'path'     => rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
}

/**
 * Guard: redirect to index.php if no authorization code is present.
 * This prevents showing an empty callback page when the URL is visited
 * directly without going through the OAuth flow.
 */
if (empty($_GET['code'])) {
    header('Location: index.php');
    exit;
}

/**
 * HTML-escape a string for safe output.
 *
 * @param mixed $s Value to escape (converted to string).
 * @return string HTML-safe escaped string.
 */
function h($s)
{
  return htmlspecialchars((string)$s);
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Callback OAuth — SinergiaCRM</title>
  <!-- Inline styles for the callback result page (tokens, portal identity, raw JSON) -->
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: #f0f2f5;
      color: #333;
      display: flex;
      justify-content: center;
      align-items: flex-start;
      min-height: 100vh;
      padding: 20px
    }

    .card {
      background: #fff;
      max-width: 780px;
      width: 100%;
      border-radius: 8px;
      box-shadow: 0 2px 12px rgba(0, 0, 0, .1);
      padding: 30px
    }

    h1 {
      font-size: 20px;
      margin-bottom: 15px;
      color: #1976d2
    }

    h2 {
      font-size: 15px;
      margin: 20px 0 8px;
      border-bottom: 1px solid #e0e0e0;
      padding-bottom: 5px;
      color: #333
    }

    .msg {
      padding: 10px 14px;
      border-radius: 4px;
      margin-bottom: 15px;
      font-size: 13px
    }

    .msg-error {
      background: #fdecea;
      color: #c62828
    }

    .msg-success {
      background: #e8f5e9;
      color: #2e7d32
    }

    .grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 4px 16px;
      font-size: 12px
    }

    .grid dt {
      color: #888;
      font-weight: 600
    }

    .grid dd {
      margin-bottom: 2px;
      word-break: break-all
    }

    code.raw {
      display: block;
      background: #263238;
      color: #89ddff;
      padding: 10px 14px;
      border-radius: 4px;
      font-size: 11px;
      word-break: break-all;
      overflow-x: auto;
      margin-top: 8px;
      white-space: pre-wrap
    }

    .links {
      margin-top: 20px;
      font-size: 13px
    }

    .links a {
      color: #1976d2;
      text-decoration: none;
      margin-right: 12px
    }

    .toggle {
      cursor: pointer;
      font-size: 11px;
      color: #1976d2;
      margin-top: 6px;
      display: inline-block
    }

    .json-hidden {
      display: none
    }

    .instance-info {
      margin-top: 14px;
      padding: 10px 14px;
      background: #f5f5f5;
      border: 1px solid #e0e0e0;
      border-radius: 6px;
      font-size: 11px;
      color: #777;
      line-height: 1.7
    }

    .instance-info strong {
      color: #444
    }

    .instance-info code {
      background: #e8e8e8;
      padding: 1px 5px;
      border-radius: 3px;
      font-size: 10px;
      word-break: break-all
    }
    .page-nav { display: flex; justify-content: space-between; gap: 1rem; margin-bottom: 1rem; font-size: 12px }
    .page-nav a { color: #1976d2; text-decoration: none }
    .page-nav a:hover { text-decoration: underline }
  </style>
</head>

<body>
  <!--
  Callback result page:
    - Error banner (state mismatch, connection failure, API error)
    - Success banner + token info and minimal portal identity
    - Raw JSON toggle for debugging the full response payload
    - Bottom bar with retry/reload links and connected instance info
  -->
  <div class="card">

    <nav class="page-nav" aria-label="Example navigation">
      <a href="../index.php">← Todos los ejemplos de API</a>
      <a href="https://github.com/SinergiaTIC/SinergiaCRM-API-Examples/blob/main/PortalOauth/README.md" target="_blank" rel="noopener">Ver documentación ↗</a>
    </nav>

    <h1>Callback OAuth</h1>

    <!-- Pre-exchange error (e.g. CSRF state mismatch or connection failure) -->
    <?php if ($error): ?>
      <div class="msg msg-error"><?= h($error) ?></div>
    <?php endif; ?>

    <!-- Token exchange succeeded — display OAuth tokens and minimal portal identity -->
    <?php if ($data && !isset($data['error'])): ?>
      <div class="msg msg-success">Autenticación correcta. Estos son los detalles de la conexión al portal.</div>

      <!-- Section: OAuth token details -->
      <h2>Tokens</h2>
      <div class="grid">
        <dt>Token de acceso</dt>
        <dd><code><?= h($data['access_token']) ?></code></dd>
        <dt>Caduca en</dt>
        <dd><?= h($data['expires_in']) ?> segundos</dd>
        <dt>Tipo de token</dt>
        <dd><?= h($data['token_type']) ?></dd>
        <dt>Token de renovación</dt>
        <dd><code><?= h($data['refresh_token']) ?></code></dd>
      </div>

      <?php if (!empty($data['user'])): $u = $data['user']; ?>
        <!-- Section: Only the portal identity needed by the external application -->
        <h2>Identidad de portal</h2>
        <div class="grid">
          <dt>ID de portal</dt>
          <dd><code><?= h($data['portal_id']) ?></code></dd>
          <dt>Tipo de portal</dt>
          <dd><?= h($data['portal_type']) ?></dd>
          <dt>Email principal</dt>
          <dd><?= h($u['email'] ?? '') ?></dd>
        </div>
      <?php endif; ?>

      <!-- Toggle: show/hide the raw JSON response payload for debugging -->
      <span class="toggle" onclick="var el=document.getElementById('rawjson');el.classList.toggle('json-hidden');this.textContent=el.classList.contains('json-hidden')?'Mostrar JSON':'Ocultar JSON'">Mostrar JSON</span>
      <pre class="raw json-hidden" id="rawjson"><?= h(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>

    <!-- Token exchange failed — the API returned an error payload -->
    <?php elseif ($data && isset($data['error'])): ?>
      <div class="msg msg-error">Error en el intercambio de tokens: <?= h($data['error']) ?>
        <?php if (!empty($data['message'])): ?><br><small><?= h($data['message']) ?></small><?php endif; ?>
      </div>
    <?php endif; ?>

    <!-- Navigation links and connected instance info bar -->
    <div class="links">
      <a href="index.php">Volver a intentar</a>
      <a href="<?= h(basename(__FILE__)) ?>">Reload</a>
    </div>

    <div class="instance-info">
      <strong>Conectado a:</strong> <?= h($config['crm_url']) ?><br>
      <strong>ID de cliente:</strong> <code><?= h($config['client_id']) ?></code>
    </div>

  </div>

  <?php
  // Popup handoff: when this callback is reached inside the login popup opened
  // by index.php (popup flow), forward the token-exchange result to the opener
  // page and close the popup. When opened directly (no opener), the normal
  // full page above is the result.
  $popupPayload = array(
      'type'  => 'sinergiacrm-portal-oauth-complete',
      'ok'    => ($data && !isset($data['error'])) ? true : false,
      'error' => (string) $error,
      'data'  => $data,
  );
  ?>
  <script>
    (function () {
      if (!window.opener || window.opener.closed) return;
      try {
        window.opener.postMessage(<?= json_encode($popupPayload) ?>, '*');
      } catch (e) {
        return;
      }
      document.body.innerHTML = '<div style="font-family:-apple-system,Segoe UI,Roboto,sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;color:#333;background:#f0f2f5"><div style="background:#fff;padding:30px 40px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.1);text-align:center">Autenticación completada.<br>Puedes cerrar esta ventana.</div></div>';
      document.title = 'Autenticación completada';
      setTimeout(function () { window.close(); }, 200);
    })();
  </script>
</body>

</html>
