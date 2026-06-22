# SinergiaCRM Portal — OAuth2 Client Demo

External app demonstrating OAuth2 authorization code flow against SinergiaCRM's portal authentication system.

**The app never sees the user's password** — they log in directly on the CRM portal.

## Quick Start

```bash
# 1. Create an OAuth2 Client in your CRM
#    Administration → OAuth2 Clients and Tokens

# 2. Configure
cp config.dist.php config.php
# Edit config.php with your CRM URL, client_id, and redirect_uri

# 3. Serve
php -S localhost:8000

# 4. Open http://localhost:8000 in your browser
```

## Flow (Authorization Code Grant)

```
  User                   Your App                     SinergiaCRM
  ────                   ───────                     ────────────
  │                        │                             │
  │ 1. Click Login         │                             │
  │───────────────────────→│                             │
  │                        │ 2. Redirect to              │
  │                        │    entryPoint=sticPortalLogin
  │                        │────────────────────────────→│
  │                                             3. Show login form
  │←─────────────────────────────────────────────────────┤
  │  4. Enter credentials                               │
  │─────────────────────────────────────────────────────→│
  │                        │ 5. Redirect to callback     │
  │                        │    ?code=XXXX&state=YYYY    │
  │                        │←────────────────────────────┤
  │                        │                             │
  │                        │ 6. POST code to             │
  │                        │    entryPoint=sticPortalOAuthToken
  │                        │   (server-side curl)        │
  │                        │────────────────────────────→│
  │                        │ 7. Returns JSON:            │
  │                        │    access_token             │
  │                        │    refresh_token            │
  │                        │    portal_id                │
  │                        │    contact info             │
  │                        │    relationships            │
  │                        │←────────────────────────────┤
  │                        │                             │
  │  8. Show results       │                             │
  │←───────────────────────│                             │
```

## Token Response

The `POST` to `sticPortalOAuthToken` returns:

```json
{
  "access_token": "def50...",
  "token_type": "Bearer",
  "expires_in": 3600,
  "refresh_token": "def50...",
  "portal_id": "abc123-...",
  "contact": {
    "id": "abc123-...",
    "first_name": "Jane",
    "last_name": "Doe",
    "title": "Manager",
    "department": "Engineering",
    "phone_mobile": "555-0100",
    "phone_work": "555-0101",
    "phone_home": null,
    "description": "VIP customer",
    "stic_portal_username_c": "jane.doe@example.com",
    "stic_portal_enabled_c": 1,
    "stic_portal_last_login_c": "2024-06-09 16:30:00",
    "stic_portal_failed_attempts_c": 0
  },
  "relationships": [
    {
      "name": "Acme Scholarship",
      "relationship_type": "Scholarship",
      "start_date": "2024-01-15",
      "end_date": null,
      "role": "Beneficiary",
      "status": "Active"
    }
  ],
  "relationship_count": 1
}
```

No extra API call needed — contact details and relationships come directly in the token response.

## Using the Access Token

Make API calls to SuiteCRM V8 with the Bearer token:

```bash
curl -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
     http://your-crm.com/sinergiacrm/Api/V8/custom/...
```

## Configuration

`config.php` (copy from `config.dist.php`):

```php
<?php
return [
    'crm_url'       => 'http://localhost:8000/sinergiacrm',
    'crm_internal'  => 'http://sw-webserver/sinergiacrm', // docker internal URL
    'client_id'     => '6b6ad4f5-5e99-11f1-b59b-b216769ad5d8',
    'redirect_uri'  => 'http://localhost:8000/callback.php',
];
```

| Key | Purpose |
|-----|---------|
| `crm_url` | Public CRM URL (browser-accessible) |
| `crm_internal` | Docker internal URL for server-to-server calls (omit if not using Docker) |
| `client_id` | OAuth2 Client UUID from CRM → Administration → OAuth2 Clients |
| `redirect_uri` | Must match the redirect URI on the OAuth2 Client exactly |

## Requirements

- PHP 7.4+ with cURL extension
- A SinergiaCRM instance with the portal authentication feature enabled
- An OAuth2 Client record with a matching `redirect_uri`

## Related

- [Portal Authentication (core)](../sinergiacrm/SticInclude/SticPortalAuthUtils.php)
- [Token Exchange Endpoint](../sinergiacrm/SticInclude/SticPortalOAuthToken.php)
- [OAuth Repositories](../sinergiacrm/SticInclude/SticPortalOAuthRepository.php)
- [Portal Login Page](http://localhost:8000/sinergiacrm/index.php?entryPoint=sticPortalLogin)
