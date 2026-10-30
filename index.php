<?php
/**
 * SinergiaCRM API Examples — Root index
 *
 * Landing page for developers explaining available demo clients
 * and how to integrate external apps with SinergiaCRM.
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SinergiaCRM — Ejemplos de API</title>
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
        <h1>SinergiaCRM — Ejemplos de API</h1>
        <p class="subtitle">
            Clientes de demostración para integrar aplicaciones externas con SinergiaCRM.<br>
            Cada ejemplo es autocontenido y muestra un patrón de integración diferente.
        </p>

        <div class="section">
            <h2>Cliente API V8</h2>
            <p>
                Cliente web de la <strong>API REST V8 de SuiteCRM</strong> que usa el grant OAuth
                <code>client_credentials</code>. Consulta personas, obtiene sus
                <code>stic_Contacts_Relationships</code> activas con datos de los proyectos relacionados,
                consulta los valores de las listas desplegables y explora los módulos disponibles.
            </p>
            <a href="v8/" class="btn">Abrir el cliente API V8</a>
            <a href="https://github.com/SinergiaTIC/SinergiaCRM-API-Examples/blob/main/v8/README.md" class="btn btn-outline" target="_blank" rel="noopener">Ver documentación ↗</a>
        </div>

        <hr class="divider">

        <div class="section">
            <h2>Cliente API v4.1</h2>
            <p>
                Cliente de la <strong>API REST clásica v4.1</strong> con autenticación por usuario/contraseña.
                Soporta <code>get_entry</code>, <code>get_relationships</code>, <code>get_module_fields</code>,
                <code>get_available_modules</code>, <code>set_entry</code> y más, a través del endpoint
                <code>v4_1_SticCustom</code>. Explora registros y relaciones, crea o actualiza registros y vincula registros existentes con <code>set_relationship</code>.
            </p>
            <a href="v4.1/" class="btn">Abrir el cliente v4.1</a>
            <a href="https://github.com/SinergiaTIC/SinergiaCRM-API-Examples/blob/main/v4.1/README.md" class="btn btn-outline" target="_blank" rel="noopener">Ver documentación ↗</a>
        </div>

        <hr class="divider">

        <div class="section">
            <h2>Demo Portal OAuth2 <span style="display:inline-block;background:#fff3e0;color:#e65100;font-size:11px;padding:2px 8px;border-radius:10px;vertical-align:middle">⚠ En desarrollo</span></h2>
            <p>
                Flujo completo de OAuth2 *authorization code* para <strong>aplicaciones externas</strong> que autentican
                usuarios del portal (Personas (Contacts) / Organizaciones (Accounts)). La aplicación nunca ve la contraseña del usuario:
                este inicia sesión directamente en el portal del CRM. Intercambia un código de autorización por
                tokens de acceso y de renovación, y obtén el perfil del usuario y sus datos de relación.<br>
                <em style="color:#e65100">Este cliente aún está en desarrollo; su API y su documentación pueden cambiar.</em>
            </p>
            <a href="PortalOauth/" class="btn">Abrir la demo de PortalOauth</a>
            <a href="https://github.com/SinergiaTIC/SinergiaCRM-API-Examples/blob/main/PortalOauth/README.md" class="btn btn-outline" target="_blank" rel="noopener">Ver documentación ↗</a>
        </div>

        <hr class="divider">

        <div class="section">
            <h2>Configuración</h2>
            <p>
                Cada demo usa <code>.env</code> como configuración por defecto. Los cambios hechos desde la interfaz se guardan en el <code>localStorage</code> del navegador actual y se envían solo con las peticiones de ese navegador; no se almacenan en un fichero compartido del servidor.
            </p>
        </div>
    </div>
</body>
</html>
