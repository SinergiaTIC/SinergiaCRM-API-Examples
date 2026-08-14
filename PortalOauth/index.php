<?php

/**
 * SinergiaCRM Portal OAuth — Demo Client
 *
 * Full OAuth2 authorization code flow demonstration for external apps
 * authenticating portal users (Contacts / Accounts) against SinergiaCRM.
 * The app never sees the user's password — they log in directly on the CRM.
 *
 * On successful login the callback endpoint exchanges the authorization code
 * for access + refresh tokens, then displays the user profile and relationship data.
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
        'redirect_uri'  => $vars['OAUTH_REDIRECT_URI'] ?? $vars['redirect_uri'] ?? '',
    ];
}

/** @var array $config Active runtime configuration (base + overrides merged). */
$config = loadPortalConfig();
$overrideFile = __DIR__ . '/config-override.json';

/**
 * Load saved overrides from JSON file.
 * Overrides take precedence over code config values — they are merged
 * on top so the user can temporarily change settings via the UI.
 */
if (file_exists($overrideFile)) {
    $overrides = json_decode(file_get_contents($overrideFile), true) ?? [];
    $config = array_merge($config, array_filter($overrides, fn($v) => $v !== '' && $v !== null));
}

$saveMsg = '';
$hasOverrides = file_exists($overrideFile);

/**
 * Handle "Save Override" POST — persist user-edited settings to a JSON file
 * so they survive page reloads without modifying .env or code files.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $overrides = [
        'crm_url'       => $_POST['crm_url'] ?? '',
        'crm_internal'  => $_POST['crm_internal'] ?? '',
        'client_id'     => $_POST['client_id'] ?? '',
    ];
    file_put_contents($overrideFile, json_encode($overrides, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $config = array_merge($config, array_filter($overrides, fn($v) => $v !== '' && $v !== null));
    $hasOverrides = true;
    $saveMsg = 'Settings saved to config-override.json.';
}

/**
 * Handle "Revert to Code Defaults" POST — delete the override file and
 * fall back to values from .env / config.php.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_settings'])) {
    if (file_exists($overrideFile)) unlink($overrideFile);
    $config = loadPortalConfig();
    $hasOverrides = false;
    $saveMsg = 'Overrides cleared — using defaults from .env.';
}

/**
 * Start session and generate an anti-CSRF state parameter.
 * The state is stored in a cookie so callback.php can validate it
 * after the CRM redirects the user back.
 */
session_start();
$state = bin2hex(random_bytes(16));
setcookie('oauth_demo_state', $state, time() + 600, '/', '', false, true);

/**
 * Build the SinergiaCRM Portal Login URL with all OAuth2 parameters.
 * The user is redirected to this URL to authenticate on the CRM side.
 */
$loginUrl = $config['crm_url'] . '/index.php?' . http_build_query([
    'entryPoint'    => 'sticPortalLogin',
    'client_id'     => $config['client_id'],
    'redirect_uri'  => $config['redirect_uri'],
    'response_type' => 'code',
    'state'         => $state,
]);
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
        <h1>SinergiaCRM Portal OAuth2</h1>
        <p class="desc">
            <strong>Authorization code flow</strong> for external apps authenticating portal users.
            The app never sees the user's password — they log in directly on the SinergiaCRM portal.<br><br>
            After login the <code>callback.php</code> endpoint performs a server-side token exchange
            and displays the <strong>user profile</strong> and <strong>relationship data</strong> returned by the API.
        </p>
        <a href="<?= htmlspecialchars($loginUrl) ?>" class="btn">Login with SinergiaCRM</a>

        <div class="flow-box">
            <strong>Flow:</strong><br>
            1. Click button → redirected to CRM login page<br>
            2. User authenticates → CRM redirects back with <code>?code=...</code><br>
            3. Callback exchanges the code for tokens via server-side <code>curl</code>
        </div>

        <!-- Connection settings card -->
        <div class="config-card">
            <div class="config-header">
                <div class="config-title-row">
                    <h3>Connection Settings</h3>
                    <?php if ($hasOverrides): ?><span class="override-badge">overridden</span><?php endif; ?>
                </div>
                <span class="settings-toggle" onclick="document.getElementById('settingsForm').classList.toggle('open'); this.classList.toggle('active')">
                    &#9881; Edit
                </span>
            </div>
            <div class="config-grid">
                <div class="config-row">
                    <span class="config-label">CRM URL</span>
                    <span class="config-value"><?= htmlspecialchars($config['crm_url']) ?></span>
                </div>
                <div class="config-row">
                    <span class="config-label">Client ID</span>
                    <span class="config-value"><code><?= htmlspecialchars($config['client_id']) ?></code></span>
                </div>
                <div class="config-row">
                    <span class="config-label">Auth method</span>
                    <span class="config-value"><code>portal_authorization_code</code></span>
                </div>
            </div>
            <?php if ($hasOverrides): ?>
            <div class="override-note">Overrides active from <code>config-override.json</code></div>
            <?php endif; ?>
            <form method="post" id="settingsForm" class="settings-form">
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
                <div class="btn-row">
                    <button type="submit" name="save_settings" class="btn-sm btn-save">Save Override</button>
                    <?php if ($hasOverrides): ?>
                    <button type="submit" name="clear_settings" class="btn-sm btn-clear">Revert to Code Defaults</button>
                    <?php endif; ?>
                </div>
                <?php if ($saveMsg): ?><div class="save-msg"><?= htmlspecialchars($saveMsg) ?></div><?php endif; ?>
            </form>
        </div>
    </div>
</body>

</html>
