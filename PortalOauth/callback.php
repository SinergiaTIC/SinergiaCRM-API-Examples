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
 *    code for an access token, refresh token, and user profile data.
 * 3. Renders the token info, user profile, and relationship data.
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

/** @var array $config Active runtime configuration (base + overrides merged). */
$config = loadPortalConfig();

/**
 * Load saved overrides from JSON file (created by the Settings panel on index.php).
 * Overrides take precedence so user-edited values survive page reloads.
 */
$overrideFile = __DIR__ . '/config-override.json';
if (file_exists($overrideFile)) {
    $overrides = json_decode(file_get_contents($overrideFile), true) ?? [];
    $config = array_merge($config, array_filter($overrides, fn($v) => $v !== '' && $v !== null));
}

/**
 * Determine token endpoint URL.
 * Uses the internal URL (e.g. Docker service name) for server-side curl
 * when configured, falling back to the public CRM URL.
 */
$crmBase    = rtrim(($config['crm_internal'] ?? $config['crm_url']), '/');
$tokenUrl   = $crmBase . '/index.php?entryPoint=sticPortalOAuthToken';
$clientId   = $config['client_id'];
$clientSecret = $config['client_secret'] ?? '';
$redirectUri = $config['redirect_uri'];
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
  if ($state !== ($_COOKIE['oauth_demo_state'] ?? '')) {
    $error = 'Invalid state parameter. Possible CSRF attack or expired session.';
  } else {
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
      $error = 'Failed to connect to token endpoint.';
    } else {
      $data = json_decode($response, true);
    }
  }
  // Clear state cookie once consumed (single-use, prevents replay).
  setcookie('oauth_demo_state', '', time() - 3600, '/');
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

/**
 * Format a table cell value — outputs an em-dash placeholder if the value
 * is null or empty, otherwise returns the HTML-escaped value.
 *
 * @param mixed $val Value to display in a table cell.
 * @return string Safe HTML string (either escaped value or placeholder).
 */
function tableVal($val)
{
  return $val === null || $val === '' ? '<em style="color:#aaa">—</em>' : h($val);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>OAuth Callback — SinergiaCRM</title>
  <!-- Inline styles for the callback result page (tokens, user profile, relationships table, raw JSON) -->
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

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 12px;
      margin-top: 8px
    }

    th,
    td {
      padding: 6px 8px;
      text-align: left;
      border-bottom: 1px solid #eee
    }

    th {
      background: #f5f5f5;
      font-weight: 600;
      color: #555
    }

    .badge {
      display: inline-block;
      padding: 1px 8px;
      border-radius: 10px;
      font-size: 10px;
      font-weight: 600
    }

    .badge-active {
      background: #c8e6c9;
      color: #2e7d32
    }

    .badge-ended {
      background: #ffcdd2;
      color: #c62828
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
  </style>
</head>

<body>
  <!--
  Callback result page:
    - Error banner (state mismatch, connection failure, API error)
    - Success banner + token info, user profile, relationships table
    - Raw JSON toggle for debugging the full response payload
    - Bottom bar with retry/reload links and connected instance info
  -->
  <div class="card">

    <h1>OAuth Callback</h1>

    <!-- Pre-exchange error (e.g. CSRF state mismatch or connection failure) -->
    <?php if ($error): ?>
      <div class="msg msg-error"><?= h($error) ?></div>
    <?php endif; ?>

    <!-- Token exchange succeeded — display everything the API returned -->
    <?php if ($data && !isset($data['error'])): ?>
      <div class="msg msg-success">Authentication successful! Here is everything your app received.</div>

      <!-- Section: Tokens (access, refresh, expiry, portal info) -->
      <h2>Tokens</h2>
      <div class="grid">
        <dt>Access Token</dt>
        <dd><code><?= h($data['access_token']) ?></code></dd>
        <dt>Expires in</dt>
        <dd><?= h($data['expires_in']) ?> seconds</dd>
        <dt>Token Type</dt>
        <dd><?= h($data['token_type']) ?></dd>
        <dt>Refresh Token</dt>
        <dd><code><?= h($data['refresh_token']) ?></code></dd>
        <dt>Portal ID</dt>
        <dd><code><?= h($data['portal_id']) ?></code></dd>
        <dt>Portal Type</dt>
        <dd><?= h($data['portal_type']) ?></dd>
      </div>

      <?php if (!empty($data['user'])): $u = $data['user']; ?>
        <!-- Section: User profile — field list varies by portal type (Contact vs Account) -->
        <h2>User Information (<?= h($data['portal_type']) ?>)</h2>
        <div class="grid">
          <dt>ID</dt>
          <dd><code><?= h($u['id']) ?></code></dd>
          <?php if ($data['portal_type'] === 'Contact'): ?>
            <dt>First Name</dt>
            <dd><?= tableVal($u['first_name']) ?></dd>
            <dt>Last Name</dt>
            <dd><?= tableVal($u['last_name']) ?></dd>
            <dt>Birthdate</dt>
            <dd><?= tableVal($u['birthdate']) ?></dd>
            <dt>Phone Mobile</dt>
            <dd><?= tableVal($u['phone_mobile']) ?></dd>
            <dt>Language</dt>
            <dd><?= tableVal($u['stic_language_c']) ?></dd>
            <dt>Gender</dt>
            <dd><?= tableVal($u['stic_gender_c']) ?></dd>
            <dt>Age</dt>
            <dd><?= tableVal($u['stic_age_c']) ?></dd>
            <dt>ID Number</dt>
            <dd><?= tableVal($u['stic_identification_number_c']) ?></dd>
            <dt>ID Type</dt>
            <dd><?= tableVal($u['stic_identification_type_c']) ?></dd>
            <dt>Address (ZIP)</dt>
            <dd><?= tableVal($u['primary_address_postalcode']) ?></dd>
            <dt>Address (Country)</dt>
            <dd><?= tableVal($u['primary_address_country']) ?></dd>
            <dt>Address (State)</dt>
            <dd><?= tableVal($u['primary_address_state']) ?></dd>
            <dt>Address (City)</dt>
            <dd><?= tableVal($u['primary_address_city']) ?></dd>
          <?php else: ?>
            <dt>Name</dt>
            <dd><?= tableVal($u['name']) ?></dd>
            <dt>Phone Office</dt>
            <dd><?= tableVal($u['phone_office']) ?></dd>
            <dt>Phone Alt</dt>
            <dd><?= tableVal($u['phone_alternate']) ?></dd>
            <dt>Website</dt>
            <dd><?= tableVal($u['website']) ?></dd>
            <dt>Account Type</dt>
            <dd><?= tableVal($u['account_type']) ?></dd>
            <dt>Industry</dt>
            <dd><?= tableVal($u['industry']) ?></dd>
            <dt>Description</dt>
            <dd><?= tableVal($u['description']) ?></dd>
            <dt>ID Number</dt>
            <dd><?= tableVal($u['stic_identification_number_c']) ?></dd>
            <dt>ID Type</dt>
            <dd><?= tableVal($u['stic_identification_type_c']) ?></dd>
            <dt>Language</dt>
            <dd><?= tableVal($u['stic_language_c']) ?></dd>
            <dt>Address (ZIP)</dt>
            <dd><?= tableVal($u['billing_address_postalcode']) ?></dd>
            <dt>Address (Country)</dt>
            <dd><?= tableVal($u['billing_address_country']) ?></dd>
            <dt>Address (State)</dt>
            <dd><?= tableVal($u['billing_address_state']) ?></dd>
            <dt>Address (City)</dt>
            <dd><?= tableVal($u['billing_address_city']) ?></dd>
          <?php endif; ?>
          <dt>Email</dt>
          <dd><?= tableVal($u['email']) ?></dd>
        </div>
      <?php endif; ?>

      <?php if (!empty($data['relationships'])): ?>
        <!-- Section: Relationships table (linked contacts/accounts with role and date range) -->
        <h2>Relationships (<?= count($data['relationships']) ?>)</h2>
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Type</th>
              <th>Start Date</th>
              <th>End Date</th>
              <th>Role</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($data['relationships'] as $r): ?>
              <tr>
                <td><?= h($r['name'] ?? '') ?></td>
                <td><?= h($r['relationship_type'] ?? '') ?></td>
                <td><?= tableVal($r['start_date']) ?></td>
                <td><?= tableVal($r['end_date']) ?></td>
                <td><?= h($r['role'] ?? '') ?></td>
                <td>
                  <?php if (empty($r['end_date']) || $r['end_date'] === '0000-00-00'): ?>
                    <span class="badge badge-active">Active</span>
                  <?php else: ?>
                    <span class="badge badge-ended">Ended</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <!-- Toggle: show/hide the raw JSON response payload for debugging -->
      <span class="toggle" onclick="var el=document.getElementById('rawjson');el.classList.toggle('json-hidden');this.textContent=el.classList.contains('json-hidden')?'Show raw JSON':'Hide raw JSON'">Show raw JSON</span>
      <pre class="raw json-hidden" id="rawjson"><?= h(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>

    <!-- Token exchange failed — the API returned an error payload -->
    <?php elseif ($data && isset($data['error'])): ?>
      <div class="msg msg-error">Token exchange failed: <?= h($data['error']) ?>
        <?php if (!empty($data['message'])): ?><br><small><?= h($data['message']) ?></small><?php endif; ?>
      </div>
    <?php endif; ?>

    <!-- Navigation links and connected instance info bar -->
    <div class="links">
      <a href="index.php">Try again</a>
      <a href="<?= h(basename(__FILE__)) ?>">Reload</a>
    </div>

    <div class="instance-info">
      <strong>Connected to:</strong> <?= h($config['crm_url']) ?><br>
      <strong>Client ID:</strong> <code><?= h($config['client_id']) ?></code>
    </div>

  </div>
</body>

</html>