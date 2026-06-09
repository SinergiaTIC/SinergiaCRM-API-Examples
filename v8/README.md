<a href="https://www.sinergiacrm.org">
  <img width="180px" height="60px" src="https://github.com/SinergiaTIC/SinergiaCRM-SuiteCRM/assets/125350097/af3300d0-1b17-427c-b681-1971d39a1528" align="right" />
</a>

# SinergiaCRM API V8 Client Examples

Client examples for the [SuiteCRM / SinergiaCRM V8 JSON:API](https://docs.suitecrm.com/developer/api/developer-setup-guide/json-api/). Demonstrates OAuth2 authentication and common API operations.

[![LICENSE](https://img.shields.io/badge/License-AGPL_v3-orange.svg)](./LICENSE.txt)

[Website](https://www.sinergiacrm.org) |
[API Documentation](https://docs.suitecrm.com/developer/api/developer-setup-guide/json-api/)

---

## Features

- **OAuth2 Client Credentials** authentication flow
- **Get access token** from SuiteCRM V8 API
- **List contacts** with pagination (Python & PHP CLI)
- **Web UI** with two lookup tools:
  1. Fetch active `stic_Contacts_Relationships` for a contact with related project details
  2. Retrieve full contact information by ID

## Quick Start

### 1. Configure

```bash
cp .env.example .env
```

Edit `.env` with your SuiteCRM instance details:

```ini
SUITECRM_BASE_URL=http://your-suitecrm-instance
OAUTH2_CLIENT_ID=your-client-id
OAUTH2_CLIENT_SECRET=your-client-secret
```

The OAuth2 client must have **Client Credentials** grant type enabled. Create one in SuiteCRM under Administration > OAuth2 Clients and Tokens.

### 2. Web UI

Place this directory in your web server root and open `index.php`:

```
http://your-server/SinergiaCRM-API-Examples/v8/
```

### 3. CLI (PHP)

```bash
php suitecrm_client.php           # First 3 pages
php suitecrm_client.php --all     # All pages
```

### 4. CLI (Python)

```bash
python3 suitecrm_client.py        # First 3 pages
python3 suitecrm_client.py --all  # All pages
```

## Requirements

- PHP 7.4+ with `curl` extension
- Python 3.7+ (standard library only)
- A SuiteCRM / SinergiaCRM instance with V8 API enabled
- An OAuth2 client with Client Credentials grant

## API Endpoints Used

| Endpoint | Method | Description |
|---|---|---|
| `/Api/access_token` | POST | Obtain OAuth2 access token |
| `/Api/V8/module/Contacts` | GET | List contacts (paginated) |
| `/Api/V8/module/Contacts/{id}` | GET | Get a single contact |
| `/Api/V8/module/Contacts/{id}/relationships/stic_contacts_relationships_contacts` | GET | Get contact relationships |
| `/Api/V8/module/Project/{id}` | GET | Get project details |

## Project Structure

```
SinergiaCRM-API-Examples/v8/
  .env.example              # Configuration template
  .env                      # Local configuration (git-ignored)
  .gitignore
  README.md                 # This file
  index.php                 # Web UI + API backend
  suitecrm_client.php       # PHP CLI client
  suitecrm_client.py        # Python CLI client
```

## License

This project is distributed under the [GNU Affero General Public License (AGPLv3)](./LICENSE.txt).
