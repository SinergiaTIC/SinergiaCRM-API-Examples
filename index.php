<?php
/**
 * SinergiaCRM API Examples — Root index
 *
 * Landing page for developers explaining available demo clients
 * and how to integrate external apps with SinergiaCRM.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SinergiaCRM — API Examples</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0 }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f0f2f5; color: #333;
            display: flex; justify-content: center; align-items: center;
            min-height: 100vh; padding: 20px
        }
        .card {
            background: #fff; max-width: 600px; width: 100%;
            border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,.1);
            padding: 40px
        }
        h1 { font-size: 22px; margin-bottom: 6px; color: #1976d2 }
        .subtitle { font-size: 14px; color: #666; margin-bottom: 24px; line-height: 1.6 }
        .section { margin-bottom: 20px }
        .section h2 { font-size: 14px; color: #333; margin-bottom: 6px }
        .section p { font-size: 12px; color: #777; line-height: 1.6; margin-bottom: 10px }
        .btn {
            display: inline-block; padding: 10px 24px;
            background: #1976d2; color: #fff; border: none;
            border-radius: 5px; font-size: 13px; font-weight: 600;
            text-decoration: none; cursor: pointer; margin-right: 8px
        }
        .btn:hover { background: #1565c0 }
        .btn-outline {
            background: transparent; color: #1976d2;
            border: 1px solid #1976d2
        }
        .btn-outline:hover { background: #e3f2fd }
        .divider { border: none; border-top: 1px solid #eee; margin: 20px 0 }
        code {
            background: #eee; padding: 1px 5px; border-radius: 3px;
            font-size: 11px; word-break: break-all
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>SinergiaCRM — API Examples</h1>
        <p class="subtitle">
            Demo clients for integrating external applications with SinergiaCRM.<br>
            Each example is self-contained and demonstrates a different integration pattern.
        </p>

        <div class="section">
            <h2>Portal OAuth2 Demo</h2>
            <p>
                Full OAuth2 authorization code flow for <strong>external apps</strong> authenticating
                portal users (Contacts / Accounts). The app never sees the user's password —
                they log in directly on the CRM portal. Exchange an authorization code for
                access + refresh tokens, then retrieve user profile and relationship data.
            </p>
            <a href="PortalOauth/" class="btn">Open PortalOauth Demo</a>
            <a href="PortalOauth/README.md" class="btn btn-outline">Read Docs</a>
        </div>

        <hr class="divider">

        <div class="section">
            <h2>V8 API Client</h2>
            <p>
                Browser-based <strong>SuiteCRM V8 REST API</strong> client using <code>client_credentials</code>
                OAuth grant. Query contacts, fetch active <code>stic_Contacts_Relationships</code> with
                related project data, inspect enum field values, and explore available modules.
            </p>
            <a href="v8/" class="btn">Open V8 API Client</a>
            <a href="v8/README.md" class="btn btn-outline">Read Docs</a>
        </div>

        <hr class="divider">

        <div class="section">
            <h2>API v4.1 Client</h2>
            <p>
                <strong>Legacy REST API v4.1</strong> client using username/password authentication.
                Supports <code>get_entry</code>, <code>get_relationships</code>, <code>get_module_fields</code>,
                <code>get_available_modules</code>, <code>set_entry</code>, and more via the
                <code>v4_1_SticCustom</code> endpoint.
            </p>
            <a href="v4.1/" class="btn">Open v4.1 Client</a>
        </div>

        <hr class="divider">

        <div class="section">
            <h2>Configuration</h2>
            <p>
                Each demo uses a <code>.env</code> file for its configuration (copy <code>.env.example</code> and edit).
                You can also override settings from the UI — each client page has a
                "Connection Settings" card where you can change the CRM URL and client credentials
                without editing files. Saved overrides persist via a local <code>config-override.json</code> file.
            </p>
        </div>
    </div>
</body>
</html>
