<a href="https://www.sinergiacrm.org">
  <img width="180px" height="60px" src="https://github.com/SinergiaTIC/SinergiaCRM-SuiteCRM/assets/125350097/af3300d0-1b17-427c-b681-1971d39a1528" align="right" />
</a>

# SinergiaCRM API V8 Client

Web-based client for the SuiteCRM / SinergiaCRM V8 JSON:API. Demonstrates OAuth2 authentication and common API operations.

[![LICENSE](https://img.shields.io/badge/License-AGPL_v3-orange.svg)](./LICENSE.txt)

[Website](https://www.sinergiacrm.org) |
[API Documentation](https://docs.suitecrm.com/developer/api/developer-setup-guide/json-api/)

---

## Features

- **OAuth2 Client Credentials** authentication
- **Fetch Modules** — List all available modules with labels and ACL permissions
- **Fetch Enum Types** — Retrieve dropdown values for enum/multienum fields (uses `option_items` from Meta API)
- **Contact Details** — Retrieve full contact info by ID (name, phone, email, identification, address, etc.)
- **Relationships** — Fetch active `stic_Contacts_Relationships` with project data for a contact
- **Language examples**: PHP (browser UI + CLI) and Python

## Quick Start

### 1. Configure

```bash
cp .env.example .env
```

Edit `.env` with your SuiteCRM URL and OAuth2 credentials:

```ini
SUITECRM_BASE_URL=http://sw-webserver/sinergiacrm
OAUTH2_CLIENT_ID=your-client-id
OAUTH2_CLIENT_SECRET=your-secret
```

### 2. Serve

```bash
php -S localhost:8000
```

### 3. Open

http://localhost:8000 — Use the web UI tools to explore your CRM data via the V8 API.

## Configuration

| Key | Description |
|-----|-------------|
| `SUITECRM_BASE_URL` | Base URL of your SuiteCRM instance (API at `/Api/access_token` relative to this) |
| `OAUTH2_CLIENT_ID` | OAuth2 Client UUID from CRM → Administration → OAuth2 Clients |
| `OAUTH2_CLIENT_SECRET` | Client secret |

### Multi-instance

To switch between CRM instances, change `SUITECRM_BASE_URL`:
- `http://sw-webserver/sinergiacrm`
- `http://sw-webserver/SCRM_alberto.sinergiacrm.org`

### Docker

When running inside Docker, use the Docker hostname `sw-webserver` instead of `localhost:8000`:

```
SUITECRM_BASE_URL=http://sw-webserver/sinergiacrm
```

The nginx config must route `/sinergiacrm/Api/` to PHP-FPM:
```nginx
location ~ ^/([^/]+)/Api/(.*)$ {
    fastcgi_pass sw-php-fpm:9000;
    fastcgi_param SCRIPT_FILENAME $document_root/$1/Api/index.php;
    fastcgi_param REQUEST_URI /$2$is_args$args;
}
```

## Requirements

- PHP 7.4+ with cURL extension
- SuiteCRM / SinergiaCRM instance with V8 API enabled
- OAuth2 Client record with `client_credentials` grant type
- OAuth2 RSA keys in `Api/V8/OAuth2/` (private.key 600, public.key 644, owned by www-data)

## Related

- [Portal OAuth2 Demo](../PortalOauth/) — Authorization code flow for external apps
- [SuiteCRM V8 JSON:API](https://docs.suitecrm.com/developer/api/developer-setup-guide/json-api/)
