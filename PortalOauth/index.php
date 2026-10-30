<?php

/**
 * SinergiaCRM Portal OAuth — Demo Client
 *
 * Full OAuth2 authorization code flow demonstration for external apps
 * authenticating portal users (Contacts / Accounts) against SinergiaCRM.
 * The app never sees the user's password — they log in directly on the CRM.
 *
 * On successful login the callback endpoint exchanges the authorization code
 * for access + refresh tokens, then displays the portal ID, type, and primary email.
 *
 * Configuration is read from .env (preferred) or config.php (legacy fallback).
 * Copy .env.example to .env and set your values.
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
    // Fallback to legacy config.php
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

/** @var array $config Server-side defaults from .env or config.php. */
$config = loadPortalConfig();
$saveMsg = '';
session_name('PORTALOAUTHDEMOSESSID');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

if (empty($_SESSION['portal_oauth_csrf'])) {
    $_SESSION['portal_oauth_csrf'] = bin2hex(random_bytes(32));
}
foreach ($_SESSION['portal_oauth_flows'] ?? [] as $flowState => $flow) {
    if (!is_array($flow) || ($flow['expires_at'] ?? 0) < time()) {
        unset($_SESSION['portal_oauth_flows'][$flowState]);
    }
}

/**
 * Start one OAuth flow using the settings submitted by this browser.
 * Persistent user overrides live only in this browser's localStorage; the
 * session copy is transient and keyed by state solely for the callback exchange.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_login'])) {
    $csrf = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['portal_oauth_csrf'], $csrf)) {
        http_response_code(400);
        // Popup flow: answer with JSON so the page can show the error inline
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Invalid request. Reload the page and try again.']);
        exit;
    } else {
        $crmUrl = trim((string) ($_POST['crm_url'] ?? $config['crm_url']));
        $crmInternal = trim((string) ($_POST['crm_internal'] ?? $config['crm_internal']));
        $clientId = trim((string) ($_POST['client_id'] ?? $config['client_id']));
        $redirectUri = trim((string) ($_POST['redirect_uri'] ?? $config['redirect_uri']));
        $urlIsValid = static function ($url) {
            if ($url === '') return true;
            $parts = parse_url($url);
            return $parts !== false
                && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
                && !empty($parts['host'])
                && empty($parts['user'])
                && empty($parts['pass']);
        };

        if (!$urlIsValid($crmUrl) || !$urlIsValid($crmInternal) || !$urlIsValid($redirectUri)
            || $crmUrl === '' || $redirectUri === '') {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Enter valid HTTP or HTTPS CRM and callback URLs.']);
            exit;
        } else {
            $flowConfig = [
                'crm_url'       => $crmUrl,
                'crm_internal'  => $crmInternal,
                'client_id'     => $clientId,
                'client_secret' => $config['client_secret'] ?? '',
                'redirect_uri'  => $redirectUri,
            ];
            if (($_POST['has_client_secret_override'] ?? '') === '1') {
                $flowConfig['client_secret'] = (string) ($_POST['client_secret'] ?? '');
            }

            $state = bin2hex(random_bytes(16));
            $_SESSION['portal_oauth_flows'][$state] = [
                'config'     => $flowConfig,
                'expires_at' => time() + 600,
            ];
            setcookie('oauth_demo_state', $state, [
                'expires'  => time() + 600,
                'path'     => rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/',
                'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);

            $loginUrl = rtrim($crmUrl, '/') . '/index.php?' . http_build_query([
                'entryPoint'    => 'sticPortalLogin',
                'client_id'     => $clientId,
                'redirect_uri'  => $flowConfig['redirect_uri'],
                'response_type' => 'code',
                'state'         => $state,
            ]);

            // Popup flow (Google-style): the page opens this URL in a same-site
            // popup instead of navigating away. The user logs in on the CRM,
            // is redirected back to callback.php, and callback.php posts the
            // token-exchange result to this page (see the message listener in
            // the page script) and closes itself. With cached credentials
            // (active portal session or remember-me cookie) the CRM authorizes
            // immediately, so the popup closes without asking for a password.
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true, 'loginUrl' => $loginUrl]);
            exit;
        }
    }
}

// Only public values are rendered into the page. The default client secret
// stays server-side and is never exposed to browser JavaScript.
$browserDefaults = [
    'crm_url'      => $config['crm_url'],
    'crm_internal' => $config['crm_internal'],
    'client_id'    => $config['client_id'],
    'redirect_uri' => $config['redirect_uri'],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>External App — SinergiaCRM Portal OAuth</title>
    <!-- Inline styles for the landing card UI, config settings panel, and form controls -->
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0 }
        [hidden] { display: none !important }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f0f2f5; color: #333;
            display: flex; justify-content: center; align-items: center;
            min-height: 100vh; padding: 20px
        }
        .card {
            background: #fff; max-width: 500px; width: 100%;
            border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,.1);
            padding: 36px; text-align: center
        }
        h1 { font-size: 1.3rem; margin-bottom: 4px; color: #1976d2 }
        .desc {
            font-size: 0.82rem; color: #666; margin-bottom: 20px;
            line-height: 1.55; padding: 0 4px
        }
        .desc code { background: #eee; padding: 1px 5px; border-radius: 3px; font-size: 0.75rem }
        .btn {
            display: inline-block; padding: 13px 30px; background: #1976d2;
            color: #fff; border: none; border-radius: 6px; font-size: 0.95rem;
            font-weight: 600; text-decoration: none; cursor: pointer; transition: background .15s
        }
        .btn:hover { background: #1565c0 }
        .page-nav { display: flex; justify-content: space-between; gap: 1rem; margin-bottom: 1.25rem; font-size: 0.78rem }
        .page-nav a { color: #1976d2; text-decoration: none }
        .page-nav a:hover { text-decoration: underline }

        .flow-box {
            margin: 20px 0; padding: 14px 16px; background: #e3f2fd;
            border-radius: 6px; font-size: 0.78rem; color: #555; text-align: left; line-height: 1.6
        }
        .flow-box strong { color: #1976d2 }
        .flow-box code { background: #bbdefb; padding: 1px 5px; border-radius: 3px; font-size: 0.72rem }

        /* ── Config card ── */
        .config-card {
            text-align: left; margin-top: 16px; padding: 16px;
            background: #fafafa; border: 1px solid #e0e0e0; border-radius: 6px
        }
        .config-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px }
        .config-title-row { display: flex; align-items: center; gap: 8px }
        .config-header h3 { font-size: 0.85rem; margin: 0; color: #555 }
        .config-grid { display: flex; flex-direction: column; gap: 6px }
        .config-row { display: flex; justify-content: space-between; align-items: baseline; font-size: 0.82rem }
        .config-label { color: #999; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.3px }
        .config-value { color: #333; text-align: right; word-break: break-all; max-width: 65% }
        .config-value code { background: #eee; padding: 1px 5px; border-radius: 3px; font-size: 0.72rem }
        .override-badge {
            display: inline-block; background: #fff3e0; color: #e65100;
            font-size: 0.65rem; padding: 1px 6px; border-radius: 3px; font-weight: 500
        }
        .override-note { margin-top: 6px; font-size: 0.7rem; color: #e65100 }
        .override-note code { background: #fff8e1; padding: 1px 4px; border-radius: 2px; font-size: 0.65rem }

        .settings-toggle {
            font-size: 0.72rem; color: #1976d2; cursor: pointer; user-select: none;
            padding: 3px 9px; border: 1px solid #1976d2; border-radius: 4px;
            transition: all .15s; white-space: nowrap
        }
        .settings-toggle:hover, .settings-toggle.active { background: #1976d2; color: #fff }
        .settings-form { display: none; margin-top: 10px; padding-top: 10px; border-top: 1px solid #eee }
        .settings-form.open { display: block }
        .field-group { margin-bottom: 10px }
        .field-group label { display: block; font-size: 0.75rem; font-weight: 600; color: #555; margin-bottom: 3px }
        .field-group input {
            width: 100%; padding: 7px 9px; border: 1px solid #ccc;
            border-radius: 5px; font-size: 0.82rem; font-family: inherit
        }
        .field-group input:focus { outline: none; border-color: #1976d2; box-shadow: 0 0 0 2px rgba(25,118,210,.15) }
        .field-help { font-size: 0.68rem; color: #999; margin-top: 3px }
        .field-help code { background: #eee; padding: 1px 4px; border-radius: 2px; font-size: 0.65rem }
        .btn-row { display: flex; gap: 6px; margin-top: 8px }
        .btn-sm { padding: 5px 14px; font-size: 0.78rem; border-radius: 4px; border: none; cursor: pointer; font-weight: 500 }
        .btn-save { background: #1976d2; color: #fff }
        .btn-save:hover { background: #1565c0 }
        .btn-clear { background: #e0e0e0; color: #555 }
        .btn-clear:hover { background: #ccc }
        .save-msg { font-size: 0.75rem; color: #2e7d32; margin-top: 6px }

        /* ── Inline result of the popup login flow ── */
        .result-success {
            background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9;
            padding: 10px; border-radius: 5px; font-size: 0.85rem; margin-bottom: 14px
        }
        .result-error {
            background: #fdecea; color: #c62828; border: 1px solid #f5c6cb;
            padding: 10px; border-radius: 5px; font-size: 0.85rem; margin-bottom: 14px
        }
        #portalResult h2 { font-size: 0.95rem; margin: 14px 0 6px; color: #333; text-align: left }
        .result-grid {
            display: grid; grid-template-columns: auto 1fr; gap: 4px 16px;
            font-size: 0.78rem; text-align: left; margin-bottom: 8px
        }
        .result-grid dt { color: #888; font-weight: 600 }
        .result-grid dd { word-break: break-all }
        .result-grid code { background: #eee; padding: 1px 5px; border-radius: 3px; font-size: 0.7rem }
        #portalResult .toggle { cursor: pointer; font-size: 0.72rem; color: #1976d2; display: inline-block; margin-top: 6px }
        #portalResult pre.raw {
            display: block; background: #263238; color: #89ddff; padding: 10px 14px;
            border-radius: 4px; font-size: 0.68rem; word-break: break-all; overflow-x: auto;
            margin-top: 8px; white-space: pre-wrap; text-align: left
        }
        #portalResult .json-hidden { display: none }
        .result-links { margin-top: 16px; font-size: 0.8rem }
        .result-links a { color: #1976d2; text-decoration: none }
        #loginMsg { font-size: 0.8rem; min-height: 18px; margin-top: 8px }
    </style>
</head>

<body>
    <!--
    Main landing card:
      - Login button (starts OAuth2 authorization code flow)
      - Flow diagram (explains the 3-step process)
      - Connection settings card (view/edit CRM URL, Client ID, etc.)
    -->
    <div class="card">
        <nav class="page-nav" aria-label="Example navigation">
            <a href="../index.php">← All API examples</a>
            <a href="https://github.com/SinergiaTIC/SinergiaCRM-API-Examples/blob/main/PortalOauth/README.md" target="_blank" rel="noopener">Read Docs ↗</a>
        </nav>
        <h1>SinergiaCRM Portal OAuth2 <span style="display:inline-block;background:#fff3e0;color:#e65100;font-size:0.65rem;padding:2px 8px;border-radius:10px;vertical-align:middle">⚠ Under development</span></h1>
        <p class="desc">
            <strong>Authorization code flow</strong> for external apps authenticating portal users.
            The app never sees the user's password — they log in directly on the SinergiaCRM portal,
            in a popup on this same website.<br><br>
            After login the <code>callback.php</code> endpoint performs a server-side token exchange
            and the portal ID, portal type, primary email, and OAuth connection tokens appear here.<br>
            <em style="color:#e65100">This demo client is still under development; its API and documentation may change.</em>
        </p>
        <form method="post" id="loginForm">
            <input type="hidden" name="start_login" value="1">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['portal_oauth_csrf']) ?>">
            <input type="hidden" name="crm_url" id="flowCrmUrl" value="<?= htmlspecialchars($browserDefaults['crm_url']) ?>">
            <input type="hidden" name="crm_internal" id="flowCrmInternal" value="<?= htmlspecialchars($browserDefaults['crm_internal']) ?>">
            <input type="hidden" name="client_id" id="flowClientId" value="<?= htmlspecialchars($browserDefaults['client_id']) ?>">
            <input type="hidden" name="redirect_uri" id="flowRedirectUri" value="<?= htmlspecialchars($browserDefaults['redirect_uri']) ?>">
            <input type="hidden" name="has_client_secret_override" id="flowHasClientSecretOverride" value="0">
            <input type="hidden" name="client_secret" id="flowClientSecret" value="">
            <button type="submit" class="btn">Login with SinergiaCRM</button>
        </form>
        <div id="loginMsg" aria-live="polite"></div>

        <!-- Inline result of the popup login flow (filled by the message listener) -->
        <div id="portalResult" hidden></div>

        <div class="flow-box">
            <strong>Flow:</strong><br>
            1. Click button → the CRM login opens in a popup (same website, no redirect)<br>
            2. User authenticates → the popup closes and the result appears here<br>
            3. The code is exchanged for tokens via server-side <code>curl</code> in <code>callback.php</code><br>
            With cached credentials the popup authorizes silently and closes by itself.
        </div>

        <!-- Connection settings card -->
        <div class="config-card">
            <div class="config-header">
                <div class="config-title-row">
                    <h3>Connection Settings</h3>
                    <span class="override-badge" id="overrideBadge" hidden>browser override</span>
                </div>
                <span class="settings-toggle" onclick="document.getElementById('settingsForm').classList.toggle('open'); this.classList.toggle('active')">
                    &#9881; Edit
                </span>
            </div>
            <div class="config-grid">
                <div class="config-row">
                    <span class="config-label">CRM URL</span>
                    <span class="config-value" id="displayCrmUrl"><?= htmlspecialchars($browserDefaults['crm_url']) ?></span>
                </div>
                <div class="config-row">
                    <span class="config-label">Client ID</span>
                    <span class="config-value"><code id="displayClientId"><?= htmlspecialchars($browserDefaults['client_id']) ?></code></span>
                </div>
                <div class="config-row">
                    <span class="config-label">Callback URL</span>
                    <span class="config-value" id="displayRedirectUri"><?= htmlspecialchars($browserDefaults['redirect_uri']) ?></span>
                </div>
                <div class="config-row">
                    <span class="config-label">Auth method</span>
                    <span class="config-value"><code>portal_authorization_code</code></span>
                </div>
            </div>
            <div class="override-note" id="overrideNote" hidden>Settings are stored only in this browser's localStorage.</div>
            <form id="settingsForm" class="settings-form">
                <div class="field-group">
                    <label for="crm_url">CRM URL</label>
                    <input type="text" name="crm_url" id="crm_url" value="<?= htmlspecialchars($config['crm_url']) ?>" placeholder="https://mycrm.example.com/sinergiacrm">
                    <div class="field-help">Public URL that users and the browser redirect to.</div>
                </div>
                <div class="field-group">
                    <label for="crm_internal">Internal URL (token exchange)</label>
                    <input type="text" name="crm_internal" id="crm_internal" value="<?= htmlspecialchars($config['crm_internal'] ?? '') ?>" placeholder="Same as CRM URL if not behind proxy">
                    <div class="field-help">Internal URL for server-to-server <code>curl</code> calls. Use Docker service name if behind a reverse proxy (e.g. <code>http://sw-webserver/sinergiacrm</code>).</div>
                </div>
                <div class="field-group">
                    <label for="client_id">OAuth2 Client ID</label>
                    <input type="text" name="client_id" id="client_id" value="<?= htmlspecialchars($config['client_id']) ?>" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                    <div class="field-help">Portal OAuth2 client UUID with <code>portal_authorization_code</code> grant type.</div>
                </div>
                <div class="field-group">
                    <label for="redirect_uri">OAuth Redirect URI (callback)</label>
                    <input type="text" name="redirect_uri" id="redirect_uri" value="<?= htmlspecialchars($config['redirect_uri']) ?>" placeholder="https://myapp.example.com/PortalOauth/callback.php">
                    <div class="field-help">Defaults to this app's public URL plus <code>/callback.php</code> when unset. Must match the Redirect URL registered on the CRM OAuth2 client.</div>
                </div>
                <div class="field-group">
                    <label for="client_secret">Client Secret <em>(optional)</em></label>
                    <input type="password" name="client_secret" id="client_secret" value="" placeholder="Leave unchanged to use the code default" autocomplete="new-password">
                    <div class="field-help">Optional browser-specific secret override. It is saved in this browser's localStorage and sent to the server only for this login flow.</div>
                </div>
                <div class="btn-row">
                    <button type="button" id="saveSettings" class="btn-sm btn-save">Save for this browser</button>
                    <button type="button" id="clearSettings" class="btn-sm btn-clear" hidden>Revert to Code Defaults</button>
                </div>
                <div class="save-msg" id="saveMsg" role="status"><?= htmlspecialchars($saveMsg) ?></div>
            </form>
        </div>
    </div>
    <script type="application/json" id="portalOauthDefaults"><?= json_encode($browserDefaults, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
    <script>
        (() => {
            const storageKey = 'sinergiacrm.portalOauth.configOverrides.v1';
            const defaults = JSON.parse(document.getElementById('portalOauthDefaults').textContent);
            if (!defaults.redirect_uri) {
                defaults.redirect_uri = new URL('callback.php', window.location.href).href;
            }
            const fields = ['crm_url', 'crm_internal', 'client_id', 'redirect_uri'];
            const form = document.getElementById('settingsForm');
            const saveMsg = document.getElementById('saveMsg');
            let overrides = {};

            try {
                const saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
                if (saved && typeof saved === 'object' && !Array.isArray(saved)) {
                    ['crm_url', 'crm_internal', 'client_id', 'redirect_uri', 'client_secret'].forEach((name) => {
                        if (typeof saved[name] === 'string'
                            && (name !== 'redirect_uri' || saved[name].trim() !== '')) {
                            overrides[name] = saved[name];
                        }
                    });
                }
            } catch (error) {
                saveMsg.textContent = 'Browser storage is unavailable; settings will use code defaults.';
            }

            const effectiveConfig = () => ({ ...defaults, ...overrides });
            const hasOverrides = () => Object.keys(overrides).length > 0;

            function render() {
                const config = effectiveConfig();
                document.getElementById('crm_url').value = config.crm_url || '';
                document.getElementById('crm_internal').value = config.crm_internal || '';
                document.getElementById('client_id').value = config.client_id || '';
                document.getElementById('redirect_uri').value = config.redirect_uri || '';
                document.getElementById('client_secret').value = overrides.client_secret || '';
                document.getElementById('displayCrmUrl').textContent = config.crm_url || '';
                document.getElementById('displayClientId').textContent = config.client_id || '';
                document.getElementById('displayRedirectUri').textContent = config.redirect_uri || '';
                document.getElementById('overrideBadge').hidden = !hasOverrides();
                document.getElementById('overrideNote').hidden = !hasOverrides();
                document.getElementById('clearSettings').hidden = !hasOverrides();
            }

            document.getElementById('saveSettings').addEventListener('click', () => {
                const next = {};
                fields.forEach((name) => {
                    const value = document.getElementById(name).value.trim();
                    if (name !== 'redirect_uri' || value !== '') next[name] = value;
                });
                const secret = document.getElementById('client_secret').value;
                if (secret !== '') next.client_secret = secret;
                try {
                    localStorage.setItem(storageKey, JSON.stringify(next));
                    overrides = next;
                    render();
                    saveMsg.textContent = 'Settings saved in this browser only.';
                } catch (error) {
                    saveMsg.textContent = 'Could not save settings in this browser.';
                }
            });

            document.getElementById('clearSettings').addEventListener('click', () => {
                try { localStorage.removeItem(storageKey); } catch (error) {}
                overrides = {};
                render();
                saveMsg.textContent = 'Browser overrides cleared; using code defaults.';
            });

            // Popup OAuth flow (Google-style): submit via fetch, open the CRM
            // login in a same-site popup, and render the result inline when the
            // popup posts it back. Falls back to the classic full-page redirect
            // when the popup is blocked.
            document.getElementById('loginForm').addEventListener('submit', (event) => {
                event.preventDefault();
                const form = event.target;
                const config = effectiveConfig();
                document.getElementById('flowCrmUrl').value = config.crm_url || '';
                document.getElementById('flowCrmInternal').value = config.crm_internal || '';
                document.getElementById('flowClientId').value = config.client_id || '';
                document.getElementById('flowRedirectUri').value = config.redirect_uri || '';
                const hasSecretOverride = Object.prototype.hasOwnProperty.call(overrides, 'client_secret');
                document.getElementById('flowHasClientSecretOverride').value = hasSecretOverride ? '1' : '0';
                document.getElementById('flowClientSecret').value = hasSecretOverride ? overrides.client_secret : '';

                const msg = document.getElementById('loginMsg');
                msg.style.color = '#1976d2';
                msg.textContent = 'Opening the SinergiaCRM login window...';

                fetch(window.location.pathname, { method: 'POST', body: new FormData(form), credentials: 'same-origin' })
                    .then((response) => response.json().then((json) => ({ ok: response.ok, json })))
                    .then((result) => {
                        if (!result.ok || !result.json || !result.json.ok) {
                            msg.style.color = '#c62828';
                            msg.textContent = (result.json && result.json.error) || 'Could not start the login flow.';
                            return;
                        }
                        msg.textContent = '';
                        const popup = window.open(
                            result.json.loginUrl,
                            'sinergiacrmPortalAuth',
                            'width=520,height=680,menubar=no,toolbar=no,location=yes,resizable=yes'
                        );
                        if (!popup) {
                            // Popup blocked: classic full-page redirect flow
                            window.location.href = result.json.loginUrl;
                        }
                    })
                    .catch(() => {
                        msg.style.color = '#c62828';
                        msg.textContent = 'Could not start the login flow.';
                    });
            });

            // The login popup posts the token-exchange result back and closes
            // itself (see callback.php). Render it inline, exactly like the
            // standalone callback page does.
            window.addEventListener('message', (event) => {
                const message = event.data;
                if (!message || message.type !== 'sinergiacrm-portal-oauth-complete') return;
                renderPortalResult(message);
            });

            function escapeHtml(value) {
                return String(value == null ? '' : value)
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
            }

            function renderPortalResult(message) {
                const container = document.getElementById('portalResult');
                let html = '';
                if (!message.ok) {
                    html += '<div class="result-error">Login failed: ' + escapeHtml(message.error || 'unknown error') + '</div>';
                } else {
                    const data = message.data || {};
                    html += '<div class="result-success">Authentication successful. The portal connection details are below.</div>';
                    html += '<h2>Tokens</h2><div class="result-grid">';
                    html += '<dt>Access Token</dt><dd><code>' + escapeHtml(data.access_token) + '</code></dd>';
                    html += '<dt>Expires in</dt><dd>' + escapeHtml(data.expires_in) + ' seconds</dd>';
                    html += '<dt>Token Type</dt><dd>' + escapeHtml(data.token_type) + '</dd>';
                    html += '<dt>Refresh Token</dt><dd><code>' + escapeHtml(data.refresh_token) + '</code></dd>';
                    html += '</div>';
                    if (data.user) {
                        html += '<h2>Portal Identity</h2><div class="result-grid">';
                        html += '<dt>Portal ID</dt><dd><code>' + escapeHtml(data.portal_id) + '</code></dd>';
                        html += '<dt>Portal Type</dt><dd>' + escapeHtml(data.portal_type) + '</dd>';
                        html += '<dt>Primary Email</dt><dd>' + escapeHtml(data.user.email) + '</dd>';
                        html += '</div>';
                    }
                    html += '<span class="toggle" id="resultToggle">Show raw JSON</span>';
                }
                html += '<div class="result-links"><a href="index.php">Start again</a></div>';
                container.innerHTML = html;

                // Raw JSON payload via textContent (no HTML parsing of the data)
                if (message.ok) {
                    const pre = document.createElement('pre');
                    pre.id = 'portalResultRaw';
                    pre.className = 'raw json-hidden';
                    pre.textContent = JSON.stringify(message.data || {}, null, 2);
                    container.insertBefore(pre, container.querySelector('.result-links'));
                    const toggle = document.getElementById('resultToggle');
                    toggle.addEventListener('click', () => {
                        pre.classList.toggle('json-hidden');
                        toggle.textContent = pre.classList.contains('json-hidden') ? 'Show raw JSON' : 'Hide raw JSON';
                    });
                }

                container.hidden = false;
                document.getElementById('loginForm').hidden = true;
                document.getElementById('loginMsg').hidden = true;
                const flow = document.querySelector('.flow-box');
                if (flow) flow.hidden = true;
            }

            render();
        })();
    </script>
</body>

</html>
