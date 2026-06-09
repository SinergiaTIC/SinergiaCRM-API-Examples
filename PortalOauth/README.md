# SinergiaCRM Portal — OAuth2 Client Demo

External app demonstrating OAuth2 authorization code flow against SinergiaCRM's portal authentication system.

The app never sees the user's password — they log in directly on the CRM portal.

## Quick Start

```bash
# 1. Create an OAuth2 Client in your CRM
#    Administration → OAuth2 Clients and Tokens

# 2. Configure
cp config.dist.php config.php
# Edit config.php with your CRM URL, client_id, client_secret, and redirect_uri

# 3. Serve
php -S localhost:8000

# 4. Open http://localhost:8000 in your browser
```

## Flow

1. User clicks "Login with SinergiaCRM" → redirected to CRM portal login
2. User authenticates → CRM redirects back to callback.php with `?code=...`
3. Callback exchanges code for tokens (server-side via cURL)
4. Tokens + active relationships displayed

## Files

| File | Purpose |
|------|---------|
| `index.php` | Login button → redirects to CRM OAuth endpoint |
| `callback.php` | Handles OAuth callback: token exchange, relationships display |
| `config.dist.php` | Configuration template — copy to `config.php` |
| `config.php` | Local config (gitignored) with your credentials |

## Configuration

Edit `config.php` or set environment variables:

```bash
export CRM_URL=http://your-crm.com/sinergiacrm
export OAUTH_CLIENT_ID=your-oauth2-client-uuid
export OAUTH_CLIENT_SECRET=your-oauth2-client-secret
export OAUTH_REDIRECT_URI=https://yourapp.com/callback.php
```

## Requirements

- PHP 7.4+ with cURL extension
- A SinergiaCRM instance with the portal authentication feature enabled

## Related

- [SinergiaCRM Portal Authentication](../sinergiacrm/SticInclude/SticPortalAuthUtils.php)
- [Portal Login Page](../sinergiacrm/index.php?entryPoint=sticPortalLogin)
