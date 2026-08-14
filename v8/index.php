<?php

/**
 * SuiteCRM / SinergiaCRM API V8 Client
 *
 * A lightweight single-file PHP client for the SuiteCRM V8 JSON:API.
 * Dual-mode operation:
 *  - When `?action=` is present in the URL, this file acts as a JSON API proxy,
 *    returning structured JSON responses for programmatic consumption.
 *  - Otherwise, it renders an interactive HTML UI for manually exploring
 *    contacts, relationships, enum values and modules.
 *
 * Provided API endpoints (`?action=...`):
 *   - getRelationships   Fetch active stic_Contacts_Relationships for a contact,
 *                        enriched with related project details.
 *   - getContact         Retrieve full contact details by ID.
 *   - getRelationshipTypes  Retrieve available enum values for the
 *                            relationship_type field (legacy name: getEnumTypes).
 *   - getModules         List all available modules via meta/modules.
 *   - getDropdown        Look up a dropdown list by its list_key across
 *                        known module fields.
 *
 * Configuration is read from `.env` in the same directory, with optional
 * runtime overrides from `config-override.json` (written by the UI's
 * "Save Override" button). Copy `.env.example` to `.env` and set your
 * credentials before use.
 *
 * @license MIT
 */

/** Directory containing this file — used to locate .env and config-override.json */
define('APP_DIR', __DIR__);

/** MIME type required by SuiteCRM V8 JSON:API for all requests */
define('JSONAPI_MIME', 'application/vnd.api+json');

/**
 * Load environment variables from the `.env` file in APP_DIR.
 *
 * The .env file uses a simple KEY=VALUE format (one per line). Blank lines
 * and lines starting with `#` are treated as comments and skipped.
 *
 * @return array<string,string> Associative array of configuration key/value pairs.
 */
function loadEnv(): array
{
    $envFile = APP_DIR . '/.env';
    $vars = [];
    if (file_exists($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            [$key, $value] = explode('=', $line, 2);
            $vars[trim($key)] = trim($value);
        }
    }
    return $vars;
}

/**
 * Load UI-based overrides from `config-override.json` in APP_DIR.
 *
 * These overrides are written by the "Save Override" button in the HTML UI
 * and take precedence over .env values. If the file doesn't exist or can't
 * be parsed, an empty array is returned.
 *
 * @return array An associative array of overridden config values.
 */
function loadOverrides(): array
{
    $f = APP_DIR . '/config-override.json';
    if (file_exists($f)) {
        return json_decode(file_get_contents($f), true) ?? [];
    }
    return [];
}

/** ──────── Bootstrap: load configuration from .env and override file ─────── */

$env = loadEnv();
$overrides = loadOverrides();

/**
 * Merge overrides on top of .env values. Non-empty, non-null override values
 * take precedence over the corresponding .env entries.
 */
$env = array_merge($env, array_filter($overrides, fn($v) => $v !== '' && $v !== null));

/** SuiteCRM instance base URL (without trailing slash) */
define('SUITECRM_BASE_URL', rtrim($env['SUITECRM_BASE_URL'] ?? 'http://localhost:8000', '/'));

/** OAuth2 client credentials for the client_credentials grant flow */
define('OAUTH2_CLIENT_ID', $env['OAUTH2_CLIENT_ID'] ?? '');
define('OAUTH2_CLIENT_SECRET', $env['OAUTH2_CLIENT_SECRET'] ?? '');

/** Whether any non-trivial overrides from config-override.json are active */
$hasOverrides = !empty(array_filter($overrides, fn($v) => $v !== '' && $v !== null));

/** Status message shown in the UI after saving or clearing settings */
$saveMsg = '';

/** ──────── Configuration save/clear handlers (POST from HTML UI) ──────── */

/**
 * Handle "Save Override" form submission.
 * Persists the submitted SUITECRM_BASE_URL, OAUTH2_CLIENT_ID, and
 * OAUTH2_CLIENT_SECRET to config-override.json.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $ov = array_filter([
        'SUITECRM_BASE_URL'   => $_POST['SUITECRM_BASE_URL'] ?? '',
        'OAUTH2_CLIENT_ID'    => $_POST['OAUTH2_CLIENT_ID'] ?? '',
        'OAUTH2_CLIENT_SECRET' => $_POST['OAUTH2_CLIENT_SECRET'] ?? '',
    ], fn($v) => $v !== '' && $v !== null);
    file_put_contents(APP_DIR . '/config-override.json', json_encode($ov, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $saveMsg = 'Settings saved.';
}

/**
 * Handle "Revert to .env Defaults" button.
 * Deletes config-override.json, removing all runtime overrides.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_settings'])) {
    $f = APP_DIR . '/config-override.json';
    if (file_exists($f)) unlink($f);
    $saveMsg = 'Overrides cleared — using .env defaults.';
}

/** ──────── API helper functions ──────── */

/**
 * Obtain an OAuth2 access token from the SuiteCRM API using the
 * client_credentials grant flow.
 *
 * Uses the constants SUITECRM_BASE_URL, OAUTH2_CLIENT_ID and
 * OAUTH2_CLIENT_SECRET that were defined during bootstrap.
 *
 * @return string The access_token value from the OAuth2 response.
 * @throws RuntimeException If the authentication endpoint returns a non-200 HTTP status.
 */
function getAccessToken(): string
{
    $ch = curl_init(SUITECRM_BASE_URL . '/Api/access_token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'grant_type' => 'client_credentials',
            'client_id' => OAUTH2_CLIENT_ID,
            'client_secret' => OAUTH2_CLIENT_SECRET,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: ' . JSONAPI_MIME, 'Accept: ' . JSONAPI_MIME],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new RuntimeException("Auth failed ($httpCode): $response");
    }
    return json_decode($response, true)['access_token'];
}

/**
 * Perform a GET request to the SuiteCRM V8 API.
 *
 * Automatically attaches the Authorization header and the JSON:API MIME
 * headers. Accepts optional query string parameters.
 *
 * @param string $endpoint The API path (e.g. `/Api/V8/module/Contacts/{id}`).
 * @param string $token    A valid OAuth2 access token.
 * @param array  $params   Optional query string parameters appended to the URL.
 * @return array           Decoded JSON response body as an associative array.
 * @throws RuntimeException If the API returns a non-200 HTTP status.
 */
function apiGet(string $endpoint, string $token, array $params = []): array
{
    $url = SUITECRM_BASE_URL . $endpoint;
    if ($params) {
        $url .= '?' . http_build_query($params);
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: ' . JSONAPI_MIME,
            'Accept: ' . JSONAPI_MIME,
            'Authorization: Bearer ' . $token,
        ],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new RuntimeException("API error ($httpCode): $response");
    }
    return json_decode($response, true);
}

/**
 * Fetch all active stic_Contacts_Relationships for a given contact,
 * including each relationship's related Project data.
 *
 * Handles pagination automatically — iterates through all result pages
 * and collects every active relationship. For each relationship that
 * references a project, a separate API call retrieves the project details.
 *
 * @param string $contactId The UUID of the contact to query.
 * @return array            Structured result with `success`, `count`, and `data` keys.
 *                          Each item in `data` includes relationship fields and
 *                          an optional `project` sub-object.
 */
function fetchRelationships(string $contactId): array
{
    $token = getAccessToken();
    $relationships = [];
    $page = 1;

    /** Paginate through all pages of active relationships */
    while (true) {
        $data = apiGet("/Api/V8/module/Contacts/$contactId/relationships/stic_contacts_relationships_contacts", $token, [
            'filter[active][eq]' => '1',
            'page[number]' => $page,
            'page[size]' => '50',
        ]);
        $items = $data['data'] ?? [];
        if (empty($items)) {
            break;
        }
        $relationships = array_merge($relationships, $items);
        $totalPages = $data['meta']['total-pages'] ?? 1;
        if ($page >= $totalPages) {
            break;
        }
        $page++;
    }

    /** Enrich each relationship with its related project details */
    $result = [];
    foreach ($relationships as $rel) {
        $attrs = $rel['attributes'] ?? [];
        $projId = $attrs['stic_contacts_relationships_projectproject_ida'] ?? '';
        $relContactId = $attrs['stic_contacts_relationships_contactscontacts_ida'] ?? '';

        /** If a project is linked, fetch its attributes via the Project API */
        $project = null;
        if ($projId) {
            try {
                $projData = apiGet("/Api/V8/module/Project/$projId", $token);
                $project = $projData['data']['attributes'] ?? [];
            } catch (RuntimeException $e) {
                $project = ['error' => $e->getMessage()];
            }
        }

        $result[] = [
            'id' => $rel['id'],
            'name' => $attrs['name'] ?? '',
            'start_date' => $attrs['start_date'] ?? '',
            'end_date' => $attrs['end_date'] ?? '',
            'relationship_type' => $attrs['relationship_type'] ?? '',
            'role' => $attrs['role'] ?? '',
            'end_reason' => $attrs['end_reason'] ?? '',
            'other_end_reasons' => $attrs['other_end_reasons'] ?? '',
            'contact_name' => $attrs['stic_contacts_relationships_contacts_name'] ?? '',
            'contact_id' => $relContactId,
            'project_name' => $attrs['stic_contacts_relationships_project_name'] ?? '',
            'project_id' => $projId,
            'project' => $project ? [
                'name' => $project['name'] ?? '',
                'status' => $project['status'] ?? '',
                'priority' => $project['priority'] ?? '',
                'estimated_start_date' => $project['estimated_start_date'] ?? '',
                'estimated_end_date' => $project['estimated_end_date'] ?? '',
                'stic_location_c' => $project['stic_location_c'] ?? '',
                'description' => $project['description'] ?? '',
            ] : null,
        ];
    }

    return ['success' => true, 'count' => count($result), 'data' => $result];
}

/**
 * Retrieve all available enum/dropdown values for the
 * `relationship_type` field on the stic_Contacts_Relationships module.
 *
 * Uses the metadata endpoint to inspect the field definition and extract
 * the `option_items` list. Useful for understanding valid relationship
 * type classifications.
 *
 * @return array Structured result with `success` and `data` keys.
 *               `data` is an associative array of `key => label` pairs.
 */
function fetchRelationshipTypes(): array
{
    $token = getAccessToken();
    $data = apiGet('/Api/V8/meta/fields/stic_Contacts_Relationships', $token);

    $items = [];
    $field = $data['data']['attributes']['relationship_type'] ?? null;
    if ($field && !empty($field['option_items'])) {
        $items = $field['option_items'];
    }
    return ['success' => true, 'data' => $items];
}

/**
 * Retrieve the full list of available modules from the SuiteCRM V8 API.
 *
 * Calls the `/Api/V8/meta/modules` endpoint and returns the raw response,
 * which includes module metadata, labels, and ACL information for each
 * module registered in the system.
 *
 * @return array The raw JSON:API response body from the modules metadata endpoint.
 */
function fetchModules(): array
{
    $token = getAccessToken();
    $data = apiGet('/Api/V8/meta/modules', $token);

    return $data;
}

/**
 * Fetch comprehensive details for a single contact by UUID.
 *
 * Only a subset of fields relevant to the SinergiaCRM use case is requested
 * via sparse fieldsets (`fields[Contacts]`) to keep responses compact.
 * Includes personal info, contact methods, identification, address, and
 * employment-related custom fields.
 *
 * @param string $contactId The UUID of the contact to retrieve.
 * @return array            Structured result with `success` and `data` keys.
 *                          `data` contains a flat array of the requested contact fields.
 */
function fetchContact(string $contactId): array
{
    $token = getAccessToken();
    $data = apiGet("/Api/V8/module/Contacts/$contactId", $token, [
        'fields[Contacts]' => implode(',', [
            'first_name',
            'last_name',
            'name',
            'birthdate',
            'phone_mobile',
            'phone_home',
            'phone_work',
            'phone_other',
            'phone_fax',
            'email1',
            'email2',
            'stic_identification_number_c',
            'stic_identification_type_c',
            'stic_gender_c',
            'stic_language_c',
            'primary_address_street',
            'primary_address_city',
            'primary_address_state',
            'primary_address_postalcode',
            'primary_address_country',
            'stic_employment_status_c',
            'stic_acquisition_channel_c',
            'title',
            'department',
            'date_entered',
            'date_modified',
            'description',
        ]),
    ]);

    $attrs = $data['data']['attributes'] ?? [];

    return ['success' => true, 'data' => [
        'id' => $data['data']['id'] ?? '',
        'first_name' => $attrs['first_name'] ?? '',
        'last_name' => $attrs['last_name'] ?? '',
        'full_name' => $attrs['name'] ?? '',
        'birthdate' => $attrs['birthdate'] ?? '',
        'phone_mobile' => $attrs['phone_mobile'] ?? '',
        'phone_home' => $attrs['phone_home'] ?? '',
        'phone_work' => $attrs['phone_work'] ?? '',
        'phone_other' => $attrs['phone_other'] ?? '',
        'phone_fax' => $attrs['phone_fax'] ?? '',
        'email1' => $attrs['email1'] ?? '',
        'email2' => $attrs['email2'] ?? '',
        'stic_identification_number_c' => $attrs['stic_identification_number_c'] ?? '',
        'stic_identification_type_c' => $attrs['stic_identification_type_c'] ?? '',
        'stic_gender_c' => $attrs['stic_gender_c'] ?? '',
        'stic_language_c' => $attrs['stic_language_c'] ?? '',
        'primary_address_street' => $attrs['primary_address_street'] ?? '',
        'primary_address_city' => $attrs['primary_address_city'] ?? '',
        'primary_address_state' => $attrs['primary_address_state'] ?? '',
        'primary_address_postalcode' => $attrs['primary_address_postalcode'] ?? '',
        'primary_address_country' => $attrs['primary_address_country'] ?? '',
        'stic_employment_status_c' => $attrs['stic_employment_status_c'] ?? '',
        'stic_acquisition_channel_c' => $attrs['stic_acquisition_channel_c'] ?? '',
        'title' => $attrs['title'] ?? '',
        'department' => $attrs['department'] ?? '',
        'date_entered' => $attrs['date_entered'] ?? '',
        'date_modified' => $attrs['date_modified'] ?? '',
        'description' => $attrs['description'] ?? '',
    ]];
}

/**
 * ──────── Router ────────
 *
 * The `?action=` parameter determines whether this script acts as an API
 * endpoint (JSON response) or renders the HTML UI. Each action maps to a
 * dedicated handler function above.
 *
 * Supported actions:
 *   - getRelationships     (requires contact_id)
 *   - getContact           (requires contact_id)
 *   - getRelationshipTypes
 *   - getModules
 *   - getDropdown          (requires list_key)
 *
 * Unrecognised or malformed actions return HTTP 400.
 * Runtime exceptions (auth/API failures) return HTTP 500.
 */

$action = $_GET['action'] ?? null;

/**
 * API mode — an `action` parameter is present.
 * Return JSON responses and terminate after processing.
 */
if ($action) {
    header('Content-Type: application/json; charset=utf-8');
    try {

        /** ---- getRelationships: fetch active relationships for a contact ---- */
        if ($action === 'getRelationships' && !empty($_GET['contact_id'])) {
            echo json_encode(fetchRelationships($_GET['contact_id']), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        /** ---- getContact: fetch full contact details by ID ---- */
        elseif ($action === 'getContact' && !empty($_GET['contact_id'])) {
            echo json_encode(fetchContact($_GET['contact_id']), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        /** ---- getRelationshipTypes: retrieve relationship_type enum values ---- */
        elseif ($action === 'getRelationshipTypes') {
            echo json_encode(fetchRelationshipTypes(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        /** ---- getModules: list all available modules ---- */
        elseif ($action === 'getModules') {
            echo json_encode(fetchModules(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        /**
         * ---- getDropdown: look up a dropdown list by its list_key ----
         *
         * This action searches across a predefined set of modules
         * (stic_Contacts_Relationships, Contacts, Accounts, Project, Leads,
         * Opportunities) to find a field whose `options` or field name matches
         * the requested `list_key`. If a match is found, the option_items are
         * returned. If not, a second pass checks known field-to-list mappings
         * (e.g. Contacts→salutation→salutation_dom). Falls back to an error
         * message if the list_key cannot be resolved.
         */
        elseif ($action === 'getDropdown') {
            header('Content-Type: application/json; charset=utf-8');
            try {
                $token = getAccessToken();
                $listKey = $_GET['list_key'] ?? '';
                if (empty($listKey)) {
                    echo json_encode(['error' => 'Missing list_key parameter']);
                    exit;
                }

                /** --- First pass: scan known module field metadata for the list_key --- */
                $found = null;
                $searchedModules = [];
                $modules = ['stic_Contacts_Relationships', 'Contacts', 'Accounts', 'Project', 'Leads', 'Opportunities'];
                foreach ($modules as $mod) {
                    try {
                        $fdata = apiGet("/Api/V8/meta/fields/$mod", $token);
                        $fattrs = $fdata['data']['attributes'] ?? [];
                        foreach ($fattrs as $fname => $f) {
                            $opts = $f['options'] ?? $f['option_items'] ?? null;
                            if ($opts) {
                                /** Match by options key, field name, or field_name_list convention */
                                if ($f['options'] === $listKey || $fname . '_list' === $listKey || $fname === $listKey) {
                                    $found = ['module' => $mod, 'field' => $fname, 'items' => $f['option_items'] ?? []];
                                    break 2;
                                }
                            }
                        }
                    } catch (RuntimeException $e) { /* skip */ }
                }

                if ($found) {
                    echo json_encode(['success' => true, 'list_key' => $listKey, 'module' => $found['module'], 'field' => $found['field'], 'data' => $found['items']], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                } else {
                    /** --- Second pass: try known field→list_key heuristics --- */
                    $guesses = [
                        'Contacts' => ['salutation', 'stic_gender_c', 'stic_language_c', 'stic_identification_type_c'],
                        'Accounts' => ['account_type', 'industry', 'stic_identification_type_c'],
                    ];
                    $found2 = null;
                    foreach ($guesses as $mod => $fields) {
                        try {
                            $fdata = apiGet("/Api/V8/meta/fields/$mod", $token);
                            $fattrs = $fdata['data']['attributes'] ?? [];
                            foreach ($fields as $fn) {
                                $f = $fattrs[$fn] ?? null;
                                if ($f && ($f['options'] ?? '') === $listKey) {
                                    $found2 = ['module' => $mod, 'field' => $fn, 'items' => $f['option_items'] ?? []];
                                    break 2;
                                }
                            }
                        } catch (RuntimeException $e) { /* skip */ }
                    }

                    if ($found2) {
                        echo json_encode(['success' => true, 'list_key' => $listKey, 'module' => $found2['module'], 'field' => $found2['field'], 'data' => $found2['items']], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    } else {
                        /** Neither pass found a match — return a descriptive error */
                        echo json_encode(['error' => "Dropdown list '$listKey' not found in known module fields. Try looking up a field name instead (e.g. 'salutation' for salutation_dom)."], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    }
                }
            } catch (RuntimeException $e) {
                http_response_code(500);
                echo json_encode(['error' => $e->getMessage()]);
            }
            exit;
        }

        /** ---- Unrecognised action or missing required parameters ---- */
        else {
            http_response_code(400);
            echo json_encode(['error' => 'Missing or invalid parameters']);
        }
    } catch (RuntimeException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

/**
 * ──────── HTML UI mode ────────
 *
 * No `action` parameter was provided, so we render the interactive
 * browser client. The page includes:
 *   - A connection settings card (showing current config, with inline edit)
 *   - Four tool cards:
 *       1. Active Contacts Relationships by Contact
 *       2. Contact Details by ID
 *       3. Dropdown List Values
 *       4. Available Modules
 *   - Inline JavaScript that calls the API endpoints above via fetch().
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SinergiaCRM API V8 Client</title>
    <style>
        /* ──────── Global reset & base layout ──────── */
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 1100px;
            margin: 2rem auto;
            padding: 0 1.5rem;
            color: #222;
            background: #f8f9fa;
        }

        h1 {
            font-size: 1.4rem;
            margin-bottom: 0.3rem;
        }

        .subtitle {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 2rem;
        }

        /* ──────── Two-column card grid (top row) ──────── */
        .cards {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        @media (max-width: 768px) {
            .cards {
                grid-template-columns: 1fr;
            }
        }

        /* ──────── Two-column card grid (bottom row) ──────── */
        .cards-bottom {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        @media (max-width: 768px) {
            .cards-bottom {
                grid-template-columns: 1fr;
            }
        }

        /* ──────── Card component ──────── */
        .card {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 1.5rem;
        }

        .card h2 {
            font-size: 1.1rem;
            margin: 0 0 0.75rem;
        }

        .card p.desc {
            color: #666;
            font-size: 0.85rem;
            margin: 0 0 1rem;
        }

        /* ──────── Input + button row ──────── */
        .input-group {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .input-group input {
            flex: 1;
            padding: 0.55rem 0.75rem;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 0.9rem;
        }

        .input-group input:focus {
            outline: none;
            border-color: #4a90d9;
            box-shadow: 0 0 0 2px rgba(74, 144, 217, 0.2);
        }

        button {
            padding: 0.55rem 1.2rem;
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            cursor: pointer;
            font-weight: 500;
        }

        /* ──────── Button variants ──────── */
        .btn-primary {
            background: #4a90d9;
            color: #fff;
        }

        .btn-primary:hover {
            background: #3a7bc8;
        }

        .btn-primary:disabled {
            background: #a0c4e8;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: #5a9e6f;
            color: #fff;
        }

        .btn-secondary:hover {
            background: #4a8e5f;
        }

        .btn-secondary:disabled {
            background: #a8d4b4;
            cursor: not-allowed;
        }

        .btn-accent {
            background: #e07b39;
            color: #fff;
        }

        .btn-accent:hover {
            background: #c96a2e;
        }

        .btn-accent:disabled {
            background: #f0c4a8;
            cursor: not-allowed;
        }

        .btn-purple {
            background: #7b4fbf;
            color: #fff;
        }

        .btn-purple:hover {
            background: #6a3fa5;
        }

        .btn-purple:disabled {
            background: #c4a8e0;
            cursor: not-allowed;
        }

        /* ──────── Result container ──────── */
        .result {
            margin-top: 1rem;
        }

        .loading {
            color: #888;
            font-style: italic;
            padding: 1rem 0;
        }

        .error {
            background: #fff0f0;
            border: 1px solid #fcc;
            color: #c00;
            padding: 0.75rem;
            border-radius: 6px;
            font-size: 0.85rem;
        }

        /* ──────── Contact info card ──────── */
        .info-card {
            background: #f0f7ff;
            border: 1px solid #c8ddf0;
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 0.75rem;
        }

        .info-card h3 {
            margin: 0 0 0.5rem;
            font-size: 0.95rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 4px 1rem;
            font-size: 0.85rem;
        }

        .info-grid .key {
            color: #666;
            text-align: right;
        }

        .info-grid .val {
            color: #222;
            word-break: break-all;
        }

        /* ──────── Relationship card ──────── */
        .rel-card {
            background: #f9f9f9;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 0.75rem;
        }

        .rel-card .rel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .rel-card .rel-name {
            font-weight: 600;
        }

        .rel-card .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        /* ──────── Badge states ──────── */
        .badge-active {
            background: #d4edda;
            color: #155724;
        }

        .badge-type {
            background: #e8e0f0;
            color: #5a3e85;
        }

        .rel-detail {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2px 1rem;
            font-size: 0.82rem;
        }

        .rel-detail .k {
            color: #888;
        }

        /* ──────── Project sub-section inside a relationship card ──────── */
        .project-section {
            margin-top: 0.75rem;
            border-top: 1px dashed #ddd;
            padding-top: 0.75rem;
        }

        .project-section h4 {
            font-size: 0.85rem;
            margin: 0 0 0.4rem;
            color: #555;
        }

        .summary {
            color: #666;
            font-size: 0.85rem;
            margin-bottom: 0.75rem;
        }

        /* ──────── Enum / dropdown list display ──────── */
        .enum-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .enum-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.55rem 0.75rem;
            border-bottom: 1px solid #eee;
            font-size: 0.85rem;
        }

        .enum-item:last-child {
            border-bottom: none;
        }

        .enum-key {
            font-family: monospace;
            background: #eee;
            padding: 2px 6px;
            border-radius: 3px;
            color: #333;
            font-size: 0.8rem;
            min-width: 120px;
            text-align: center;
        }

        .enum-value {
            color: #444;
        }

        /* ──────── Module table ──────── */
        .module-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
            margin: 0.5rem 0;
        }

        .module-table th {
            text-align: left;
            padding: 0.4rem 0.6rem;
            background: #f0f0f0;
            color: #555;
            font-weight: 600;
            position: sticky;
            top: 0;
        }

        .module-table td {
            padding: 0.35rem 0.6rem;
            border-bottom: 1px solid #eee;
        }

        .module-table tr:hover td {
            background: #fafafa;
        }

        .module-scroll {
            max-height: 420px;
            overflow-y: auto;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
        }

        .search-box {
            margin-bottom: 0.75rem;
        }

        .search-box input {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 0.85rem;
        }

        /* ──────── Connection settings config card ──────── */
        .config-card {
            background: #fff; border: 1px solid #e0e0e0; border-radius: 8px;
            padding: 1.25rem 1.5rem; margin-bottom: 1.5rem
        }
        .config-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem }
        .config-header h2 { font-size: 1rem; margin: 0; color: #333 }
        .config-title-row { display: flex; align-items: center; gap: 8px }
        .config-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem }
        @media (max-width: 768px) { .config-grid { grid-template-columns: 1fr } }
        .config-item { display: flex; flex-direction: column }
        .config-label { font-size: 0.72rem; color: #999; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px }
        .config-value { font-size: 0.85rem; color: #333; word-break: break-all }
        .config-value code { background: #eee; padding: 1px 5px; border-radius: 3px; font-size: 0.78rem }
        .config-summary { margin-bottom: 0.75rem }
        .override-badge {
            display: inline-block; background: #fff3e0; color: #e65100;
            font-size: 0.68rem; padding: 1px 7px; border-radius: 3px; font-weight: 500
        }
        .override-note { margin-top: 0.5rem; font-size: 0.75rem; color: #e65100 }
        .override-note code { background: #fff8e1; padding: 1px 4px; border-radius: 2px; font-size: 0.7rem }
        .settings-toggle {
            font-size: 0.8rem; color: #1976d2; cursor: pointer; user-select: none;
            padding: 4px 10px; border: 1px solid #1976d2; border-radius: 4px;
            transition: all .15s
        }
        .settings-toggle:hover, .settings-toggle.active { background: #1976d2; color: #fff }
        .settings-form { display: none; margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid #eee }
        .settings-form.open { display: block }
        .field-group { margin-bottom: 0.75rem }
        .field-group label { display: block; font-size: 0.8rem; font-weight: 600; color: #555; margin-bottom: 4px }
        .field-group input {
            width: 100%; padding: 0.5rem 0.65rem; border: 1px solid #ccc;
            border-radius: 5px; font-size: 0.85rem; font-family: inherit
        }
        .field-group input:focus { outline: none; border-color: #1976d2; box-shadow: 0 0 0 2px rgba(25,118,210,.15) }
        .field-help { font-size: 0.72rem; color: #999; margin-top: 3px }
        .field-help code { background: #eee; padding: 1px 4px; border-radius: 2px; font-size: 0.68rem }
        .btn-row { display: flex; gap: 0.5rem; margin-top: 1rem }
        .btn-sm { padding: 0.45rem 1rem; font-size: 0.82rem; border-radius: 5px; border: none; cursor: pointer; font-weight: 500 }
        .btn-save { background: #1976d2; color: #fff }
        .btn-save:hover { background: #1565c0 }
        .btn-clear { background: #e0e0e0; color: #555 }
        .btn-clear:hover { background: #ccc }
        .save-msg { font-size: 0.8rem; color: #2e7d32; margin-top: 0.5rem }
    </style>
</head>

<body>
    <!-- ──────── Page header ──────── -->
    <h1>SinergiaCRM API V8 Client</h1>
    <p class="subtitle">
        Browser-based V8 REST API client — explore contacts, relationships, enum values, and modules.
    </p>

    <!-- ──────── Connection settings card ──────── -->
    <div class="config-card">
        <div class="config-header">
            <div class="config-title-row">
                <h2>Connection Settings</h2>
                <?php if ($hasOverrides): ?><span class="override-badge">overridden</span><?php endif; ?>
            </div>
            <span class="settings-toggle" onclick="document.getElementById('settingsForm').classList.toggle('open'); this.classList.toggle('active')">
                &#9881; Edit
            </span>
        </div>
        <div class="config-summary">
            <!-- Read-only display of current connection parameters -->
            <div class="config-grid">
                <div class="config-item">
                    <span class="config-label">CRM URL</span>
                    <span class="config-value"><?= htmlspecialchars(SUITECRM_BASE_URL) ?></span>
                </div>
                <div class="config-item">
                    <span class="config-label">Client ID</span>
                    <span class="config-value"><code><?= htmlspecialchars(OAUTH2_CLIENT_ID) ?></code></span>
                </div>
                <div class="config-item">
                    <span class="config-label">Auth method</span>
                    <span class="config-value"><code>client_credentials</code> OAuth2 grant</span>
                </div>
            </div>
            <?php if ($hasOverrides): ?>
            <div class="override-note">Overrides active from <code>config-override.json</code></div>
            <?php endif; ?>
        </div>
        <!-- Collapsible settings edit form -->
        <form method="post" id="settingsForm" class="settings-form">
            <div class="field-group">
                <label for="baseUrl">SuiteCRM Base URL</label>
                <input type="text" name="SUITECRM_BASE_URL" id="baseUrl" value="<?= htmlspecialchars(SUITECRM_BASE_URL) ?>" placeholder="http://localhost:8000/sinergiacrm">
                <div class="field-help">Root URL of your SinergiaCRM instance. Example: <code>https://mycrm.example.com/sinergiacrm</code></div>
            </div>
            <div class="field-group">
                <label for="clientId">OAuth2 Client ID</label>
                <input type="text" name="OAUTH2_CLIENT_ID" id="clientId" value="<?= htmlspecialchars(OAUTH2_CLIENT_ID) ?>" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                <div class="field-help">The OAuth2 client UUID with <code>client_credentials</code> grant type enabled.</div>
            </div>
            <div class="field-group">
                <label for="clientSecret">OAuth2 Client Secret</label>
                <input type="password" name="OAUTH2_CLIENT_SECRET" id="clientSecret" value="" placeholder="<?= OAUTH2_CLIENT_SECRET ? 'Secret configured — type a new one to override' : 'Enter client secret' ?>">
                <div class="field-help"><?= OAUTH2_CLIENT_SECRET ? 'A secret is configured in <code>.env</code>. Leave blank to keep it, or type a new value to override.' : 'Required for the <code>client_credentials</code> OAuth2 grant. Kept in <code>.env</code> — do not commit.' ?></div>
            </div>
            <div class="btn-row">
                <!-- Persists overrides to config-override.json -->
                <button type="submit" name="save_settings" class="btn-sm btn-save">Save Override</button>
                <?php if ($hasOverrides): ?>
                <!-- Deletes config-override.json to revert to .env defaults -->
                <button type="submit" name="clear_settings" class="btn-sm btn-clear">Revert to .env Defaults</button>
                <?php endif; ?>
            </div>
            <?php if ($saveMsg): ?><div class="save-msg"><?= htmlspecialchars($saveMsg) ?></div><?php endif; ?>
        </form>
    </div>

    <!-- ──────── Tool cards: top row ──────── -->
    <div class="cards">
        <!-- Card 1: Active Contacts Relationships lookup -->
        <div class="card">
            <h2>Active Contacts Relationships by Contact</h2>
            <p class="desc">Enter a contact ID to fetch all active <code>stic_Contacts_Relationships</code> with related project details.</p>
            <div class="input-group">
                <input type="text" id="relContactIdInput" placeholder="Contact ID (e.g. a830067e-5e85-11f1-b59b-b216769ad5d8)">
                <button class="btn-primary" id="btnRelationships" onclick="searchRelationships()">Search</button>
            </div>
            <div id="relResult" class="result"></div>
        </div>

        <!-- Card 2: Contact Details lookup -->
        <div class="card">
            <h2>Contact Details by ID</h2>
            <p class="desc">Enter a contact ID to fetch basic information (name, phone, email, identification, etc).</p>
            <div class="input-group">
                <input type="text" id="contactIdInput" placeholder="Contact ID (e.g. 0000088f-0c84-56ae-8561-6a0cd328fd97)">
                <button class="btn-secondary" id="btnContact" onclick="searchContact()">Search</button>
            </div>
            <div id="contactResult" class="result"></div>
        </div>
    </div>

    <!-- ──────── Tool cards: bottom row ──────── -->
    <div class="cards-bottom">
        <!-- Card 3: Dropdown list value lookup -->
        <div class="card">
            <h2>Dropdown List Values</h2>
            <p class="desc">Look up any dropdown list by its key (e.g. <code>stic_contacts_relationships_types_list</code>, <code>account_type_dom</code>, <code>gender_list</code>). Enter a list key to see all its values.</p>
            <div class="input-group">
                <input type="text" id="dropdownKey" placeholder="Dropdown list key" value="stic_contacts_relationships_types_list">
                <button class="btn-accent" id="btnDropdown" onclick="fetchDropdown()">Look Up</button>
            </div>
            <div id="dropdownResult" class="result"></div>
        </div>

        <!-- Card 4: Available modules list -->
        <div class="card">
            <h2>Available Modules</h2>
            <p class="desc">Fetch all available modules from <code>/Api/V8/meta/modules</code> with labels and ACL permissions.</p>
            <button class="btn-purple" id="btnModules" onclick="fetchModules()">Fetch Modules</button>
            <div id="moduleResult" class="result"></div>
        </div>
    </div>

    <script>
        /**
         * Fetch and display active stic_Contacts_Relationships for a contact.
         * Calls the `?action=getRelationships` endpoint with the entered contact ID.
         * Each relationship card includes an embedded project section when applicable.
         */
        async function searchRelationships() {
            const contactId = document.getElementById('relContactIdInput').value.trim();
            const btn = document.getElementById('btnRelationships');
            const result = document.getElementById('relResult');
            if (!contactId) {
                result.innerHTML = '<div class="error">Please enter a contact ID.</div>';
                return;
            }

            btn.disabled = true;
            result.innerHTML = '<div class="loading">Loading...</div>';

            try {
                const resp = await fetch(`?action=getRelationships&contact_id=${encodeURIComponent(contactId)}`);
                const data = await resp.json();
                if (!data.success) {
                    result.innerHTML = `<div class="error">${esc(data.error || 'Unknown error')}</div>`;
                    return;
                }
                if (!data.data.length) {
                    result.innerHTML = '<div class="summary">No active relationships found for this contact.</div>';
                    return;
                }

                let html = `<div class="summary">Found ${data.count} active relationship(s)</div>`;
                data.data.forEach(rel => {
                    html += renderRel(rel);
                });
                result.innerHTML = html;
            } catch (e) {
                result.innerHTML = `<div class="error">Request failed: ${esc(e.message)}</div>`;
            } finally {
                btn.disabled = false;
            }
        }

        /**
         * Fetch and display full contact details by UUID.
         * Calls the `?action=getContact` endpoint and renders an info card
         * with all available contact fields.
         */
        async function searchContact() {
            const contactId = document.getElementById('contactIdInput').value.trim();
            const btn = document.getElementById('btnContact');
            const result = document.getElementById('contactResult');
            if (!contactId) {
                result.innerHTML = '<div class="error">Please enter a contact ID.</div>';
                return;
            }

            btn.disabled = true;
            result.innerHTML = '<div class="loading">Loading...</div>';

            try {
                const resp = await fetch(`?action=getContact&contact_id=${encodeURIComponent(contactId)}`);
                const data = await resp.json();
                if (!data.success) {
                    result.innerHTML = `<div class="error">${esc(data.error || 'Unknown error')}</div>`;
                    return;
                }

                const c = data.data;
                let html = '<div class="info-card">';
                html += `<h3>${esc(c.full_name || c.first_name + ' ' + c.last_name)}</h3>`;
                html += '<div class="info-grid">';
                html += row('ID', c.id);
                html += row('First Name', c.first_name);
                html += row('Last Name', c.last_name);
                html += row('Birthdate', c.birthdate);
                html += row('Title', c.title);
                html += row('Department', c.department);
                html += row('Mobile Phone', c.phone_mobile);
                html += row('Home Phone', c.phone_home);
                html += row('Work Phone', c.phone_work);
                html += row('Other Phone', c.phone_other);
                html += row('Fax', c.phone_fax);
                html += row('Email (primary)', c.email1);
                html += row('Email (secondary)', c.email2);
                html += row('ID Number', c.stic_identification_number_c);
                html += row('ID Type', c.stic_identification_type_c);
                html += row('Gender', c.stic_gender_c);
                html += row('Language', c.stic_language_c);
                html += row('Employment', c.stic_employment_status_c);
                html += row('Acq. Channel', c.stic_acquisition_channel_c);
                html += row('Address', c.primary_address_street);
                html += row('City', c.primary_address_city);
                html += row('State', c.primary_address_state);
                html += row('Postal Code', c.primary_address_postalcode);
                html += row('Country', c.primary_address_country);
                html += row('Date Created', c.date_entered);
                html += row('Date Modified', c.date_modified);
                html += row('Description', c.description);
                html += '</div></div>';
                result.innerHTML = html;
            } catch (e) {
                result.innerHTML = `<div class="error">Request failed: ${esc(e.message)}</div>`;
            } finally {
                btn.disabled = false;
            }
        }

        /**
         * Fetch and display the `relationship_type` enum values from the
         * stic_Contacts_Relationships module metadata.
         * Calls the `?action=getRelationshipTypes` endpoint.
         *
         * NOTE: This function is defined but no button in the current UI
         * invokes it; it remains available for programmatic use.
         */
        async function fetchEnumTypes() {
            const btn = document.getElementById('btnEnumTypes');
            const result = document.getElementById('enumResult');

            btn.disabled = true;
            result.innerHTML = '<div class="loading">Loading...</div>';

            try {
                const resp = await fetch('?action=getRelationshipTypes');
                const data = await resp.json();
                if (!data.success) {
                    result.innerHTML = `<div class="error">${esc(data.error || 'Unknown error')}</div>`;
                    return;
                }

                let html = '<div class="summary">Available <code>relationship_type</code> enum values:</div>';
                html += '<ul class="enum-list">';
                let count = 0;
                for (const [key, label] of Object.entries(data.data)) {
                    html += `<li class="enum-item"><span class="enum-key">${esc(key || '(empty)')}</span><span class="enum-value">${esc(label)}</span></li>`;
                    count++;
                }
                html += '</ul>';
                html += `<div class="summary" style="margin-top:0.5rem">${count} enum item(s) total</div>`;
                result.innerHTML = html;
            } catch (e) {
                result.innerHTML = `<div class="error">Request failed: ${esc(e.message)}</div>`;
            } finally {
                btn.disabled = false;
            }
        }

        /**
         * Fetch and display all available modules from the SuiteCRM V8 API.
         * Calls the `?action=getModules` endpoint and renders a filterable
         * table with module name, label, and ACL permissions.
         */
        async function fetchModules() {
            const btn = document.getElementById('btnModules');
            const result = document.getElementById('moduleResult');

            btn.disabled = true;
            result.innerHTML = '<div class="loading">Loading...</div>';

            try {
                const resp = await fetch('?action=getModules');
                const data = await resp.json();
                const modules = data.data?.attributes;
                if (!modules) {
                    result.innerHTML = '<div class="error">No modules returned</div>';
                    return;
                }

                const entries = Object.entries(modules);
                let html = `<div class="summary">${entries.length} modules available</div>`;
                html += '<div class="search-box"><input type="text" id="moduleFilter" placeholder="Filter modules..." oninput="filterModuleTable()"></div>';
                html += '<div class="module-scroll"><table class="module-table" id="moduleTable">';
                html += '<thead><tr><th>Module</th><th>Label</th><th>ACL</th></tr></thead><tbody>';
                for (const [key, val] of entries) {
                    const label = val.label || '';
                    const access = val.access || [];
                    html += `<tr data-name="${esc(key)}"><td><code>${esc(key)}</code></td><td>${esc(label)}</td><td>${esc(access.join(', '))}</td></tr>`;
                }
                html += '</tbody></table></div>';
                result.innerHTML = html;
            } catch (e) {
                result.innerHTML = `<div class="error">Request failed: ${esc(e.message)}</div>`;
            } finally {
                btn.disabled = false;
            }
        }

        /**
         * Fetch and display a dropdown list by its list_key.
         * Calls the `?action=getDropdown` endpoint and renders an enum-style
         * list of key/value pairs returned by the server-side lookup.
         */
        async function fetchDropdown() {
            const key = document.getElementById('dropdownKey').value.trim();
            const btn = document.getElementById('btnDropdown');
            const result = document.getElementById('dropdownResult');
            if (!key) { result.innerHTML = '<div class="error">Enter a dropdown list key.</div>'; return; }
            btn.disabled = true;
            result.innerHTML = '<div class="loading">Looking up...</div>';
            try {
                const resp = await fetch(`?action=getDropdown&list_key=${encodeURIComponent(key)}`);
                const data = await resp.json();
                if (data.error) {
                    result.innerHTML = `<div class="error">${esc(data.error)}</div>`;
                    return;
                }
                let html = `<div class="summary">List <code>${esc(data.list_key)}</code> (module <code>${esc(data.module)}</code>, field <code>${esc(data.field)}</code>)</div>`;
                html += '<ul class="enum-list">';
                let count = 0;
                for (const [k, v] of Object.entries(data.data || {})) {
                    html += `<li class="enum-item"><span class="enum-key">${esc(k || '(empty)')}</span><span class="enum-value">${esc(v)}</span></li>`;
                    count++;
                }
                html += '</ul>';
                html += `<div class="summary" style="margin-top:0.5rem">${count} item(s)</div>`;
                result.innerHTML = html;
            } catch (e) {
                result.innerHTML = `<div class="error">Request failed: ${esc(e.message)}</div>`;
            } finally {
                btn.disabled = false;
            }
        }

        /**
         * Live-filter the module table rows by the text typed into the search box.
         * Rows whose module name (data-name attribute) does not contain the filter
         * string (case-insensitive) are hidden.
         */
        function filterModuleTable() {
            const filter = (document.getElementById('moduleFilter')?.value || '').toLowerCase();
            const rows = document.querySelectorAll('#moduleTable tbody tr');
            rows.forEach(row => {
                const name = (row.getAttribute('data-name') || '').toLowerCase();
                row.style.display = name.includes(filter) ? '' : 'none';
            });
        }

        /**
         * Render a single relationship record as an HTML card.
         * Includes relationship metadata, badges (Active, relationship type),
         * and — when a project is linked — a nested project details section.
         *
         * @param {Object} rel - The relationship data object from the API response.
         * @returns {string} HTML markup for the relationship card.
         */
        function renderRel(rel) {
            let html = '<div class="rel-card">';
            html += `<div class="rel-header"><span class="rel-name">${esc(rel.name || '(unnamed)')}</span>`;
            html += '<span class="badge badge-active">Active</span>';
            if (rel.relationship_type) html += ` <span class="badge badge-type">${esc(rel.relationship_type)}</span>`;
            html += '</div><div class="rel-detail">';
            html += `<div class="k">ID</div><div>${esc(rel.id)}</div>`;
            html += `<div class="k">Contact</div><div>${esc(rel.contact_name)} (${esc(rel.contact_id)})</div>`;
            html += `<div class="k">Start Date</div><div>${esc(rel.start_date || '—')}</div>`;
            html += `<div class="k">End Date</div><div>${esc(rel.end_date || '—')}</div>`;
            html += `<div class="k">Role</div><div>${esc(rel.role || '—')}</div>`;
            html += `<div class="k">End Reason</div><div>${esc(rel.end_reason || '—')}</div>`;
            html += `<div class="k">Other End Reasons</div><div>${esc(rel.other_end_reasons || '—')}</div>`;
            html += '</div>';

            /** Render nested project details if available */
            if (rel.project) {
                const p = rel.project;
                html += '<div class="project-section"><h4>Related Project</h4><div class="rel-detail">';
                html += `<div class="k">Name</div><div>${esc(p.name || '—')}</div>`;
                html += `<div class="k">Status</div><div>${esc(p.status || '—')}</div>`;
                html += `<div class="k">Priority</div><div>${esc(p.priority || '—')}</div>`;
                html += `<div class="k">Est. Start</div><div>${esc(p.estimated_start_date || '—')}</div>`;
                html += `<div class="k">Est. End</div><div>${esc(p.estimated_end_date || '—')}</div>`;
                html += `<div class="k">Location</div><div>${esc(p.stic_location_c || '—')}</div>`;
                html += `<div class="k">Description</div><div>${esc(p.description || '—')}</div>`;
                html += '</div></div>';
            }
            html += '</div>';
            return html;
        }

        /**
         * Helper to produce a single row in the contact info grid.
         * Returns an empty string if the value is falsy, so empty fields
         * are omitted from the rendered output.
         *
         * @param {string} label - The field label (e.g. "First Name").
         * @param {*}      val   - The field value.
         * @returns {string} HTML markup for the key/value row.
         */
        function row(label, val) {
            if (!val) return '';
            return `<div class="key">${esc(label)}</div><div class="val">${esc(String(val))}</div>`;
        }

        /**
         * Escape a string for safe embedding in HTML.
         * Creates a text node in a detached <div> and returns its innerHTML,
         * ensuring any HTML special characters are encoded to entities.
         *
         * @param {string} s - The raw string to escape.
         * @returns {string} The HTML-escaped version of the input.
         */
        function esc(s) {
            const div = document.createElement('div');
            div.appendChild(document.createTextNode(s));
            return div.innerHTML;
        }
    </script>
</body>

</html>
