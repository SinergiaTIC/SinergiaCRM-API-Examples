# SinergiaCRM Portal — OAuth2 Client Integration

This document is for **external developers** integrating their applications with SinergiaCRM's portal authentication system.

**Your app never sees the user's password** — authentication happens directly on the CRM portal via standard OAuth2 authorization code flow.

---

## Quick Start

```bash
# 1. Create a Portal OAuth2 Client in the CRM
#    Administration → OAuth2 Clients → New Portal Client (Authorization Code)

# 2. Configure this demo
cp config.dist.php config.php
# Edit config.php with your client_id and redirect_uri

# 3. Serve
php -S localhost:8000

# 4. Open http://localhost:8000
```

---

## OAuth2 Flow (Authorization Code Grant)

```
  User                   Your App                     SinergiaCRM
  ────                   ───────                     ────────────
   │                        │                             │
   │ 1. Click Login         │                             │
   │───────────────────────→│                             │
   │                        │ 2. Redirect to              │
   │                        │    entryPoint=sticPortalLogin
   │                        │    ?client_id=...            │
   │                        │    &redirect_uri=...         │
   │                        │    &response_type=code       │
   │                        │    &state=<random>           │
   │                        │────────────────────────────→│
   │                                             3. Show login form
   │←─────────────────────────────────────────────────────┤
   │  4. Enter credentials                               │
   │─────────────────────────────────────────────────────→│
   │                        │ 5. Redirect to callback     │
   │                        │    ?code={auth_code}        │
   │                        │    &state={state}           │
   │                        │←────────────────────────────┤
   │                        │                             │
   │                        │ 6. POST code to             │
   │                        │    entryPoint=sticPortalOAuthToken
   │                        │    (server-side, never expose token URL)
   │                        │────────────────────────────→│
   │                        │ 7. Returns JSON with        │
   │                        │    access_token + user data │
   │                        │←────────────────────────────┤
   │                        │                             │
   │  8. Show results       │                             │
   │←───────────────────────│                             │
```

---

## Step-by-Step Implementation

All steps reference the diagram above.

### Step 1 — User clicks Login

Your app presents a "Login with SinergiaCRM" button. No special code needed — just a link to the authorization URL (see Step 2).

### Step 2 — Redirect to Authorization Endpoint

Generate a random `state` parameter for CSRF protection, then redirect the user's browser to:

```
GET /index.php?entryPoint=sticPortalLogin
    &client_id={your_client_id}
    &redirect_uri={your_callback_url}
    &response_type=code
    &state={random_state}
```

Store `state` in a session/cookie so you can verify it on callback.

### Step 3 — CRM Shows Login Form

Handled entirely by SinergiaCRM. The user sees the portal login page with password and optional Magic Link tabs. Your app does nothing during this step.

### Step 4 — User Enters Credentials

The user enters their portal username and password directly on the CRM login page. Your app **never sees the password** — this is the key security property of OAuth2.

### Step 5 — Callback Handling

After successful login, the CRM redirects the user back to your `redirect_uri`:

```
GET {redirect_uri}?code={auth_code}&state={state}
```

**Validate `state` matches** before proceeding (CSRF protection). The `code` expires in 10 minutes and is single-use.

### Step 6 — Token Exchange

Exchange the authorization code for tokens. This is a **server-side** call — do NOT expose this in client-side code.

```
POST /index.php?entryPoint=sticPortalOAuthToken
Content-Type: application/x-www-form-urlencoded

grant_type=authorization_code
&code={auth_code}
&client_id={your_client_id}
&redirect_uri={your_callback_url}
```

### Step 7 — Token Response

The CRM returns JSON with tokens, user data, and relationships. See full schema below.

### Step 8 — Show Results

Your app stores the tokens (securely, server-side) and displays the user information. Use the `access_token` to make authenticated API calls to the SuiteCRM V8 API.

---

## Endpoints Reference

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/index.php?entryPoint=sticPortalLogin` | GET | Authorization endpoint — user logs in here (Steps 2-4) |
| `/index.php?entryPoint=sticPortalOAuthToken` | POST | Token endpoint — exchange code for tokens (Step 6) |
| `/index.php?entryPoint=sticPortalOAuthToken&access_token=...` | GET | Token validation |

---

## Token Response Schema

### Contact

```json
{
  "access_token": "a1b2c3...",
  "token_type": "Bearer",
  "expires_in": 3600,
  "refresh_token": "d4e5f6...",
  "portal_id": "abc123-...",
  "portal_type": "Contact",
  "user": {
    "id": "abc123-...",
    "first_name": "Jane",
    "last_name": "Doe",
    "phone_mobile": "555-0100",
    "birthdate": "1985-03-15",
    "email": "jane.doe@example.com",
    "stic_identification_number_c": "12345678Z",
    "stic_identification_type_c": "nif",
    "stic_language_c": "es_ES",
    "stic_gender_c": "female",
    "stic_age_c": 39,
    "primary_address_postalcode": "08001",
    "primary_address_country": "Spain",
    "primary_address_state": "Barcelona",
    "primary_address_city": "Barcelona"
  },
  "relationships": [
    {
      "id": "rel-1",
      "name": "Acme Scholarship",
      "relationship_type": "Scholarship",
      "start_date": "2024-01-15",
      "end_date": null,
      "role": "Beneficiary",
      "project_name": "Education Program 2024",
      "estimated_start_date": "2024-01-01",
      "estimated_end_date": "2024-12-31",
      "stic_portal_decidim_excluded_c": 0
    }
  ],
  "relationship_count": 1
}
```

### Account

When the authenticated user is an Account, the response uses the same structure with Account-specific fields:

```json
{
  "access_token": "...",
  "token_type": "Bearer",
  "expires_in": 3600,
  "refresh_token": "...",
  "portal_id": "def456-...",
  "portal_type": "Account",
  "user": {
    "id": "def456-...",
    "name": "Acme Corporation",
    "phone_office": "555-0200",
    "phone_alternate": "555-0201",
    "website": "https://acme.example.com",
    "email": "contact@acme.example.com",
    "account_type": "Customer",
    "industry": "Technology",
    "description": "Key account",
    "stic_identification_number_c": "B12345678",
    "stic_identification_type_c": "cif",
    "stic_language_c": "es_ES",
    "billing_address_postalcode": "28001",
    "billing_address_country": "Spain",
    "billing_address_state": "Madrid",
    "billing_address_city": "Madrid"
  },
  "relationships": [],
  "relationship_count": 0
}
```

#### All `user` fields

| Contact field | Account field | Type | Description |
|---|---|---|---|
| `id` | `id` | UUID | Record ID |
| `first_name` | — | string | First name |
| `last_name` | — | string | Last name |
| — | `name` | string | Organization name |
| `birthdate` | — | date | Date of birth (YYYY-MM-DD) |
| `phone_mobile` | — | string | Mobile phone |
| — | `phone_office` | string | Office phone |
| — | `phone_alternate` | string | Alternate phone |
| — | `website` | string | Website URL |
| `email` | `email` | string | Primary email address |
| `stic_identification_number_c` | `stic_identification_number_c` | string | Tax ID / NIF / CIF |
| `stic_identification_type_c` | `stic_identification_type_c` | string | ID type (nif, nie, cif, other) |
| `stic_language_c` | `stic_language_c` | string | Language preference (es_ES, en_us, ca_ES, etc.) |
| `stic_gender_c` | — | string | Gender |
| `stic_age_c` | — | int | Calculated age |
| `primary_address_postalcode` | — | string | Postal/ZIP code |
| `primary_address_country` | — | string | Country |
| `primary_address_state` | — | string | State/Province |
| `primary_address_city` | — | string | City |
| — | `billing_address_postalcode` | string | Billing ZIP code |
| — | `billing_address_country` | string | Billing country |
| — | `billing_address_state` | string | Billing state |
| — | `billing_address_city` | string | Billing city |
| — | `account_type` | string | Account type |
| — | `industry` | string | Industry |
| — | `description` | string | Description/notes |

All fields may be `null` or empty string if not filled in the CRM.

#### Relationships

The `relationships` array contains records from `stic_contacts_relationships` linked to the authenticated user. Each relationship includes:

| Field | Type | Description |
|-------|------|-------------|
| `id` | string | Relationship record ID |
| `name` | string | Display name |
| `relationship_type` | string | Type (e.g., Scholarship, Volunteer, Donation) |
| `start_date` | date | Start date (YYYY-MM-DD) |
| `end_date` | date/null | End date (null = active) |
| `role` | string | User's role in the relationship |
| `project_name` | string/null | Linked project name (if any) |
| `estimated_start_date` | date/null | Project estimated start date |
| `estimated_end_date` | date/null | Project estimated end date |
| `stic_portal_decidim_excluded_c` | 0\|1 | Portal Decidim exclusion flag (checkbox) |

Relationships with `end_date = null` or `0000-00-00` are active. Others are ended.

### Refresh Token

Access tokens expire in 1 hour. Use the refresh token to get a new one:

```
POST /index.php?entryPoint=sticPortalOAuthToken
Content-Type: application/x-www-form-urlencoded

grant_type=refresh_token
&refresh_token={your_refresh_token}
&client_id={your_client_id}
```

Response:
```json
{
  "access_token": "<new>",
  "token_type": "Bearer",
  "expires_in": 3600,
  "refresh_token": "<new>"
}
```

Refresh tokens are valid for 30 days. Old refresh token is revoked on use (rotation).

### Token Validation

```
GET /index.php?entryPoint=sticPortalOAuthToken&access_token={token}
```

Response:
```
200: {"valid": true, "portal_id": "abc123-..."}
401: {"error": "invalid_token"}
```

---

## Error Responses

| HTTP | `error` value | When |
|------|--------------|------|
| 400 | `invalid_request` | Missing `code` or `client_id` |
| 400 | `invalid_client` | Client not found, deleted, or redirect_uri mismatch |
| 400 | `invalid_grant` | Code expired, already used, or wrong client |
| 400 | `unsupported_grant_type` | Grant type not `authorization_code` or `refresh_token` |
| 401 | `invalid_token` | Token not found or revoked |

---

## Implementing in Your Own App (Language-Agnostic)

This section is for developers integrating the SinergiaCRM portal OAuth2 flow into **any** backend technology (Node.js, Python, Java, .NET, Ruby, Go, etc.) or frontend framework.

### Prerequisites

1. A **Portal OAuth2 Client** registered in SinergiaCRM (see [Creating a Portal OAuth2 Client](#creating-a-portal-oauth2-client) below)
2. Your app must be able to:
   - Redirect the user's browser to a URL (Step 2)
   - Receive GET parameters on a callback URL (Step 5)
   - Make a `POST` request with `application/x-www-form-urlencoded` body from your **server** (Step 6)
   - Parse JSON response (Step 7)

### Implementation Checklist

```text
☐ 1. Store client_id, client_secret, redirect_uri in env vars (never commit secrets)
☐ 2. Create a /login route that generates state, stores it, redirects to CRM
☐ 3. Create a /callback route that validates state, exchanges code for tokens
☐ 4. Store tokens securely (encrypted DB, server-side session, or HTTP-only cookie)
☐ 5. Handle token refresh transparently (before calling API, check if access_token expired)
☐ 6. Implement logout (optional: call /sticPortalLogout or just discard tokens)
```

### Pseudocode

```
// ── Step 1-2: Login endpoint ──
function login():
    state = crypto.randomBytes(16).hex          // 32-char random hex
    store_in_session("oauth_state", state)
    params = url_encode({
        entryPoint:    "sticPortalLogin",
        client_id:     CLIENT_ID,
        redirect_uri:  REDIRECT_URI,
        response_type: "code",
        state:         state
    })
    redirect("CRM_URL/index.php?" + params)

// ── Step 5-6: Callback endpoint ──
function callback(request):
    if request.GET["state"] != get_from_session("oauth_state"):
        return 400 "CSRF validation failed"
    
    code  = request.GET["code"]
    token = http_post(CRM_URL + "/index.php?entryPoint=sticPortalOAuthToken", {
        grant_type:    "authorization_code",
        code:          code,
        client_id:     CLIENT_ID,
        redirect_uri:  REDIRECT_URI
    })
    
    if token.error:
        return 400 token.error_description
    
    store_tokens(token.access_token, token.refresh_token, token.expires_in)
    save_user_session(token.portal_id, token.user)
    redirect_to_app_home()

// ── Token refresh (call before each API request) ──
function get_valid_token():
    if access_token_is_expired():
        new = http_post(CRM_URL + "/index.php?entryPoint=sticPortalOAuthToken", {
            grant_type:    "refresh_token",
            refresh_token: stored_refresh_token,
            client_id:     CLIENT_ID
        })
        store_tokens(new.access_token, new.refresh_token, new.expires_in)
        return new.access_token
    return stored_access_token
```

### Key Constraints

| Constraint | Details |
|------------|---------|
| **State must be validated** | Compare received `state` against stored value. Prevents CSRF attacks. |
| **Token exchange is server-side** | Never call the token endpoint from browser JS — your `client_secret` would be exposed. Use a backend route as proxy. |
| **Code is single-use** | The authorization code expires after first exchange. Retrying will get `invalid_grant`. |
| **Code expires in 10 min** | If the user takes longer, redirect them to login again. |
| **Refresh token rotation** | Each refresh gives a new refresh token. Old one is revoked. Always store the latest. |
| **No CSRF token needed** | The `sticPortalOAuthToken` endpoint does not require a SuiteCRM CSRF token — it's a standalone OAuth2 endpoint. |
| **Content-Type** | Token endpoint expects `application/x-www-form-urlencoded`, not JSON. |

### Examples by Language

#### Node.js (Express)

```javascript
const crypto = require('crypto');
const fetch = require('node-fetch');

// Login route
app.get('/login', (req, res) => {
  const state = crypto.randomBytes(16).toString('hex');
  req.session.oauthState = state;
  const params = new URLSearchParams({
    entryPoint: 'sticPortalLogin',
    client_id: process.env.CLIENT_ID,
    redirect_uri: process.env.REDIRECT_URI,
    response_type: 'code',
    state: state,
  });
  res.redirect(`${process.env.CRM_URL}/index.php?${params}`);
});

// Callback route
app.get('/callback', async (req, res) => {
  if (req.query.state !== req.session.oauthState) return res.status(400).send('Invalid state');
  
  const tokenRes = await fetch(`${process.env.CRM_URL}/index.php?entryPoint=sticPortalOAuthToken`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      grant_type: 'authorization_code',
      code: req.query.code,
      client_id: process.env.CLIENT_ID,
      redirect_uri: process.env.REDIRECT_URI,
    }),
  });
  
  const data = await tokenRes.json();
  if (data.error) return res.status(400).send(data.error_description);
  
  req.session.tokens = { access: data.access_token, refresh: data.refresh_token, expires: Date.now() + data.expires_in * 1000 };
  req.session.user = data.user;
  res.redirect('/dashboard');
});
```

#### Python (Flask)

```python
import secrets, requests
from flask import Flask, redirect, request, session

app = Flask(__name__)

@app.route('/login')
def login():
    state = secrets.token_hex(16)
    session['oauth_state'] = state
    params = {
        'entryPoint': 'sticPortalLogin',
        'client_id': os.environ['CLIENT_ID'],
        'redirect_uri': os.environ['REDIRECT_URI'],
        'response_type': 'code',
        'state': state,
    }
    return redirect(f"{os.environ['CRM_URL']}/index.php?{urlencode(params)}")

@app.route('/callback')
def callback():
    if request.args.get('state') != session.pop('oauth_state', None):
        return 'Invalid state', 400
    
    r = requests.post(f"{os.environ['CRM_URL']}/index.php?entryPoint=sticPortalOAuthToken", data={
        'grant_type': 'authorization_code',
        'code': request.args['code'],
        'client_id': os.environ['CLIENT_ID'],
        'redirect_uri': os.environ['REDIRECT_URI'],
    })
    
    data = r.json()
    if 'error' in data:
        return data['error_description'], 400
    
    session['access_token'] = data['access_token']
    session['refresh_token'] = data['refresh_token']
    session['user'] = data['user']
    return redirect('/dashboard')
```

---

## Creating a Portal OAuth2 Client

1. Log into SinergiaCRM as admin
2. Go to **OAuth2 Clients → New Portal Client (Authorization Code)**
3. Fill in:

| Field | Description |
|-------|-------------|
| Name | Display name for your app |
| Change Secret | Client secret (save it — shown only once) |
| Redirect URL | Your callback URL (must start with this exact prefix) |
| Is Confidential | Check if your app can keep the secret safe (server-side apps) |

The grant type is automatically set to `portal_authorization_code` (distinct from SuiteCRM's built-in OAuth2 types).

The `redirect_uri` sent during the authorization request must **start with** the registered Redirect URL. This is a prefix check — `http://example.com/callback` would match `http://example.com/callback?foo=bar` but NOT `http://evil.com/callback`.

---

## Configuration

`config.php` (copy from `config.dist.php`):

```php
<?php
return [
    // Public CRM URL (browser-accessible, used for redirects)
    'crm_url'       => 'http://localhost:8000/sinergiacrm',

    // Docker internal URL for server-to-server calls (omit if not using Docker)
    'crm_internal'  => 'http://sw-webserver/sinergiacrm',

    // OAuth2 Client UUID from CRM
    'client_id'     => '6b6ad4f5-5e99-11f1-b59b-b216769ad5d8',

    // Must start with the registered redirect_url prefix
    'redirect_uri'  => 'http://localhost:8000/SinergiaCRM-API-Examples/PortalOauth/callback.php',
];
```

| Key | Required | Purpose |
|-----|----------|---------|
| `crm_url` | Yes | Public CRM URL used for browser redirects |
| `crm_internal` | No | Internal URL for server-to-server curl (Docker networks) |
| `client_id` | Yes | OAuth2 Client UUID from the CRM |
| `redirect_uri` | Yes | Your callback URL (must start with registered prefix) |

---

## Using the Access Token

Make API calls to SuiteCRM V8 with the Bearer token:

```bash
curl -H "Authorization: Bearer {access_token}" \
     http://your-crm.com/sinergiacrm/Api/V8/custom/...
```

---

## Security Notes

- **Validate `state` parameter** on callback to prevent CSRF attacks
- **Never expose `client_secret`** in client-side code or public repos
- **Use HTTPS** in production — tokens are sent in plaintext over HTTP
- **Store refresh tokens securely** (server-side, encrypted at rest)
- **Rotate client secrets** periodically
- **The `code` is single-use** and expires in 10 minutes
- **Access tokens expire in 1 hour**, refresh tokens in 30 days

---

## Requirements

- PHP 7.4+ with cURL extension
- SinergiaCRM instance with portal authentication enabled
- A Portal OAuth2 Client record (`portal_authorization_code` grant type)
- Matching `redirect_uri` between client registration and authorization request
