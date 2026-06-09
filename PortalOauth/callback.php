<?php
/**
 * OAuth Callback Handler
 *
 * Receives the authorization code from the CRM, exchanges it for tokens,
 * and displays the authenticated user's active contact relationships.
 * Also queries the V8 Meta API to show available relationship types.
 */

$config = require 'config.php';

$crmBase    = rtrim($config['crm_url'], '/');
$tokenUrl   = $crmBase . '/index.php?entryPoint=sticPortalOAuthToken';
$metaUrl    = $crmBase . '/../Api/V8/meta/fields/stic_Contacts_Relationships';
$clientId   = $config['client_id'];
$redirectUri = $config['redirect_uri'];

$error = '';
$tokens = null;
$relationships = [];
$relationshipTypes = [];

// Step 1: Exchange authorization code for tokens
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['code'])) {
    $code  = $_GET['code'];
    $state = $_GET['state'] ?? '';

    // CSRF protection
    if ($state !== ($_COOKIE['oauth_demo_state'] ?? '')) {
        $error = 'Invalid state parameter. Possible CSRF attack.';
    } else {
        $ch = curl_init($tokenUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'client_id'     => $clientId,
                'redirect_uri'  => $redirectUri,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            $error = 'Failed to connect to the token endpoint.';
        } else {
            $tokens = json_decode($response, true);

            if ($tokens && !isset($tokens['error'])) {
                // Relationships are returned directly in the token response
                $relationships = $tokens['relationships'] ?? [];

                // Step 2: Fetch relationship type options from V8 Meta API
                $ch = curl_init($metaUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 10,
                ]);
                $meta = json_decode(curl_exec($ch), true);
                curl_close($ch);

                $fields = $meta['data']['attributes']['fields'] ?? [];
                foreach ($fields as $field) {
                    if (($field['name'] ?? '') === 'relationship_type') {
                        $relationshipTypes = $field['options'] ?? [];
                        break;
                    }
                }
            }
        }
    }
    // Clean up CSRF cookie
    setcookie('oauth_demo_state', '', time() - 3600, '/');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OAuth Callback — SinergiaCRM Demo</title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f0f2f5;color:#333;display:flex;justify-content:center;align-items:flex-start;min-height:100vh;padding:20px}
        .card{background:#fff;max-width:750px;width:100%;margin:20px;border-radius:8px;box-shadow:0 2px 12px rgba(0,0,0,.1);padding:30px}
        h1{font-size:20px;margin-bottom:15px;color:#1976d2}
        h2{font-size:15px;margin:20px 0 10px;border-bottom:1px solid #eee;padding-bottom:5px}
        .msg{padding:10px;border-radius:4px;margin-bottom:15px;font-size:13px}
        .msg-error{background:#fdecea;color:#c62828}
        .msg-success{background:#e8f5e9;color:#2e7d32}
        .box{margin-bottom:10px}
        .box label{font-size:11px;font-weight:600;color:#888;display:block;margin-bottom:3px}
        .box code{display:block;background:#f5f5f5;padding:8px 10px;border-radius:4px;font-size:11px;word-break:break-all}
        .links{margin-top:15px;font-size:13px}
        .links a{color:#1976d2;text-decoration:none}
        table{width:100%;border-collapse:collapse;margin-top:10px;font-size:13px}
        th{background:#f5f5f5;padding:8px 10px;text-align:left;border-bottom:2px solid #ddd;font-size:12px;color:#555}
        td{padding:8px 10px;border-bottom:1px solid #eee}
        .badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;background:#e3f2fd;color:#1565c0}
        .tags{margin-top:10px}
        .tag{display:inline-block;padding:3px 10px;margin:2px;border-radius:12px;font-size:11px;background:#e8f5e9;color:#2e7d32}
    </style>
</head>
<body>
<div class="card">
    <h1>OAuth Callback</h1>

    <?php if ($error): ?>
        <div class="msg msg-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($tokens && !isset($tokens['error'])): ?>
        <div class="msg msg-success">Authentication successful!</div>

        <h2>Tokens</h2>
        <div class="box"><label>Access Token</label><code><?= htmlspecialchars($tokens['access_token']) ?></code></div>
        <div class="box"><label>Refresh Token</label><code><?= htmlspecialchars($tokens['refresh_token']) ?></code></div>
        <div class="box"><label>Expires in</label><code><?= $tokens['expires_in'] ?> seconds</code></div>

        <?php if (!empty($relationships)): ?>
            <h2>Active Relationships (<?= count($relationships) ?>)</h2>
            <table>
                <thead><tr><th>Name</th><th>Type</th><th>Start</th><th>Role</th><th>Project</th></tr></thead>
                <tbody>
                <?php foreach ($relationships as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['name'] ?? '-') ?></td>
                        <td><span class="badge"><?= htmlspecialchars($r['relationship_type'] ?? '-') ?></span></td>
                        <td><?= htmlspecialchars($r['start_date'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($r['role'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($r['project_name'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if (!empty($relationshipTypes)): ?>
            <h2>Available Relationship Types (V8 Meta API)</h2>
            <div class="tags">
            <?php foreach ($relationshipTypes as $opt):
                $label = is_array($opt) ? ($opt['label'] ?? $opt['name'] ?? '') : $opt;
            ?>
                <span class="tag"><?= htmlspecialchars($label) ?></span>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php elseif ($tokens && isset($tokens['error'])): ?>
        <div class="msg msg-error">Token exchange failed: <?= htmlspecialchars($tokens['error']) ?></div>
    <?php endif; ?>

    <div class="links"><a href="index.php">Try again</a></div>
</div>
</body>
</html>
