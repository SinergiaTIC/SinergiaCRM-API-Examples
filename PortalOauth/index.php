<?php

/**
 * SinergiaCRM Portal OAuth — Demo Client
 *
 * Demonstrates the OAuth2 authorization code flow for external apps
 * authenticating against SinergiaCRM's portal authentication system.
 *
 * The app never sees the user's password — they log in directly on the CRM.
 */

$config = require 'config.php';

session_start();
$state = bin2hex(random_bytes(16));
setcookie('oauth_demo_state', $state, time() + 600, '/', '', false, true);

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
    <title>External App — SinergiaCRM OAuth Demo</title>
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
            align-items: center;
            min-height: 100vh
        }

        .card {
            background: #fff;
            max-width: 450px;
            width: 100%;
            margin: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, .1);
            padding: 40px;
            text-align: center
        }

        h1 {
            font-size: 22px;
            margin-bottom: 10px;
            color: #1976d2
        }

        p {
            font-size: 14px;
            color: #666;
            margin-bottom: 25px;
            line-height: 1.6
        }

        .btn {
            display: inline-block;
            padding: 14px 32px;
            background: #1976d2;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer
        }

        .btn:hover {
            background: #1565c0
        }

        .info {
            margin-top: 20px;
            padding: 12px;
            background: #e3f2fd;
            border-radius: 6px;
            font-size: 12px;
            color: #555;
            text-align: left
        }

        code {
            background: #bbdefb;
            padding: 1px 5px;
            border-radius: 3px;
            font-size: 11px;
            word-break: break-all
        }
    </style>
</head>

<body>
    <div class="card">
        <h1>External App — OAuth2 Demo</h1>
        <p>Standard OAuth2 authorization code flow. The app never sees your password — you log in directly on the SinergiaCRM portal.</p>
        <a href="<?= htmlspecialchars($loginUrl) ?>" class="btn">Login with SinergiaCRM</a>
        <div class="info">
            <strong>Flow:</strong><br>
            1. Click button → redirected to CRM login<br>
            2. Log in → redirected back with <code>?code=...</code><br>
            3. App exchanges code for tokens (server-side)
        </div>
    </div>
</body>

</html>