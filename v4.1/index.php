<?php

/**
 * SinergiaCRM API v4.1 — Demo Client
 *
 * Web-based client for the SinergiaCRM v4.1 REST API.
 * Uses username/password authentication (not OAuth2).
 *
 * Defaults are read from .env. Optional UI overrides live in the current
 * browser's localStorage and are sent with each API request only.
 * The UI provides tool cards for common API operations including:
 *   - Dropdown list lookups from app_list_strings
 *   - Record retrieval by module and ID
 *   - Module field definitions
 *   - Related record / relationship queries
 *   - Language definition retrieval
 *   - Record creation and updates via set_entry
 *
 * @package    SinergiaCRM
 * @subpackage API-Examples
 * @version    4.1
 */

/**
 * Load configuration from the .env file (or legacy PHP config as fallback).
 *
 * Reads key=value pairs line-by-line from .env, skipping empty lines
 * and lines starting with '#' (comments). Returns a merged array with
 * sensible defaults for any missing keys.
 *
 * @return array{ crm_url: string, api_path: string, crm_user: string, crm_pass: string, language: string }
 */
function loadV4Config(): array
{
    $envFile = __DIR__ . '/.env';
    $vars = [];
    if (file_exists($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            [$key, $value] = explode('=', $line, 2);
            $vars[trim($key)] = trim($value);
        }
    }

    /**
     * Return configuration with defaults for any missing keys.
     *   - crm_url:    Base URL of the SinergiaCRM instance
     *   - api_path:   REST endpoint path (relative to crm_url)
     *   - crm_user:   CRM username for authentication
     *   - crm_pass:   CRM password (MD5-hashed before sending)
     *   - language:   Default language locale (e.g. es_ES, en_us)
     */
    return [
        'crm_url'   => $vars['CRM_URL'] ?? 'http://sw-webserver/sinergiacrm',
        'api_path'  => $vars['API_PATH'] ?? '/custom/service/v4_1_SticCustom/rest.php',
        'crm_user'  => $vars['CRM_USER'] ?? '',
        'crm_pass'  => $vars['CRM_PASSWORD'] ?? '',
        'language'  => $vars['API_LANGUAGE'] ?? 'es_ES',
    ];
}

/** @var array $config Configuration defaults from .env */
$config = loadV4Config();

/** Apply validated browser-local overrides to this API request only. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['api_action'])) {
    $encodedConfig = $_SERVER['HTTP_X_V4_API_CONFIG'] ?? '';
    if ($encodedConfig !== '' && strlen($encodedConfig) <= 16000) {
        $decodedConfig = base64_decode($encodedConfig, true);
        $requestConfig = $decodedConfig === false ? null : json_decode(rawurldecode($decodedConfig), true);
        if (is_array($requestConfig)) {
            if (isset($requestConfig['crm_url']) && is_string($requestConfig['crm_url']) && strlen($requestConfig['crm_url']) <= 2048) {
                $parts = parse_url($requestConfig['crm_url']);
                if ($parts !== false && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
                    && !empty($parts['host']) && empty($parts['user']) && empty($parts['pass'])) {
                    $config['crm_url'] = $requestConfig['crm_url'];
                }
            }
            if (isset($requestConfig['api_path']) && is_string($requestConfig['api_path'])
                && strlen($requestConfig['api_path']) <= 512 && $requestConfig['api_path'] !== '' && $requestConfig['api_path'][0] === '/') {
                $config['api_path'] = $requestConfig['api_path'];
            }
            foreach (['crm_user' => 256, 'crm_pass' => 4096, 'language' => 32] as $key => $maxLength) {
                if (isset($requestConfig[$key]) && is_string($requestConfig[$key]) && strlen($requestConfig[$key]) <= $maxLength) {
                    $config[$key] = $requestConfig[$key];
                }
            }
        }
    }
}

$browserDefaults = [
    'crm_url'  => $config['crm_url'],
    'api_path' => $config['api_path'],
    'crm_user' => $config['crm_user'],
    'language' => $config['language'],
];

/**
 * ---------------------------------------------------------------------------
 * API call handler
 * ---------------------------------------------------------------------------
 *
 * All AJAX tool-card requests POST to this same file with an `api_action`
 * field.  The server:
 *   1. Authenticates against the v4.1 REST endpoint using the configured
 *      username and MD5-hashed password.
 *   2. Dispatches the requested action to the corresponding REST method.
 *   3. Calls logout to clean up the session.
 *   4. Returns the API response as JSON.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['api_action'])) {

    /** @var string Set response content type to UTF-8 JSON */
    header('Content-Type: application/json; charset=utf-8');

    /** @var string $url Full REST endpoint URL (crm_url + api_path) */
    $url = rtrim($config['crm_url'], '/') . $config['api_path'];

    /** @var string $username CRM username from config */
    $username = $config['crm_user'];

    /** @var string $password CRM plain-text password (will be MD5-hashed) */
    $password = $config['crm_pass'];

    /** @var string $language Language locale for the session */
    $language = $config['language'];

    /*
     * -----------------------------------------------------------------------
     * Step 1 — Authenticate (login)
     * -----------------------------------------------------------------------
     * The v4.1 REST API requires a successful login call before any
     * subsequent operations.  The password is MD5-hashed in transit.
     * On success the response contains an 'id' which is the session token.
     */
    $loginParams = [
        'user_auth' => [
            'user_name' => $username,
            'password' => md5($password),
        ],
        'application_name' => 'SinergiaCRM API v4.1 Demo',
        'name_value_list' => [
            ['name' => 'notifyonsave', 'value' => false],
            ['name' => 'language', 'value' => $language],
        ],
    ];

    /** @var array|null $loginRes Login response from the REST endpoint */
    $loginRes = restCall($url, 'login', $loginParams);

    /*
     * If login fails (no response, or no session ID in the response),
     * return a JSON error immediately and stop processing.
     */
    if (!$loginRes || empty($loginRes['id'])) {
        echo json_encode(['error' => 'Login failed: ' . ($loginRes['description'] ?? 'Unknown error')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** @var string $sessionId Session token returned by a successful login */
    $sessionId = $loginRes['id'];

    /** @var string $action The requested API operation from the client */
    $action = $_POST['api_action'];

    /** @var array|null $response Accumulated response data to send back to the client */
    $response = null;

    /*
     * -----------------------------------------------------------------------
     * Step 2 — Dispatch the requested action
     * -----------------------------------------------------------------------
     * Each case maps a client action name to its corresponding v4.1 REST
     * method and passes the relevant POST parameters.
     */
    switch ($action) {

        /**
         * getLanguageDefinition
         *
         * Fetches language strings for the configured locale.
         * Requests the 'app_list_strings' module which contains dropdown
         * values and translatable labels used throughout the CRM.
         */
        case 'getLanguageDefinition':
            $response = restCall($url, 'get_language_definition', [
                'session' => $sessionId,
                'modules' => 'app_list_strings',
                'MD5' => false,
            ]);
            break;

        /**
         * getDropdownList
         *
         * Looks up a specific dropdown list key inside app_list_strings.
         * First fetches the full language definition via get_language_definition,
         * then performs an exact (case-insensitive) match on the requested key.
         * If no exact match is found, a fuzzy search across all keys provides
         * suggestions back to the user.
         */
        case 'getDropdownList':
            /** @var string $listKey The dropdown list key to search for (e.g. 'account_type_dom') */
            $listKey = $_POST['list_key'] ?? '';

            /** @var array|null $langRes Language definition response (re-fetched for key lookup) */
            $langRes = restCall($url, 'get_language_definition', [
                'session' => $sessionId,
                'modules' => 'app_list_strings',
                'MD5' => false,
            ]);

            $response = ['list_key' => $listKey];

            /** @var array $appList All app_list_strings entries keyed by list name */
            $appList = $langRes['app_list_strings'] ?? [];

            /** @var bool $found Whether an exact (or very close) match was located */
            $found = false;

            /*
             * Iterate over every key in the language definition looking for
             * a case-insensitive exact match or a substring match.
             */
            foreach ($appList as $k => $v) {
                if (strcasecmp($k, $listKey) === 0 || stripos($k, $listKey) !== false) {
                    $response['matched_key'] = $k;
                    $response['data'] = $v;
                    $found = true;
                    break;
                }
            }

            /*
             * When no exact match is found, collect all keys that contain
             * the search term as a substring and present them as suggestions
             * along with their entry counts.
             */
            if (!$found) {
                // Try partial match across all keys
                $matches = [];
                foreach ($appList as $k => $v) {
                    if (stripos($k, $listKey) !== false) {
                        $matches[$k] = count($v);
                    }
                }
                if ($matches) {
                    $response['suggestions'] = $matches;
                    $response['error'] = "List '$listKey' not found exactly. Similar keys: " . implode(', ', array_keys($matches));
                } else {
                    $response['error'] = "List '$listKey' not found in app_list_strings.";
                }
            }
            break;

        /**
         * getEntry
         *
         * Retrieves a single record (all fields) from the specified module
         * using the record's UUID.
         */
        case 'getEntry':
            $response = restCall($url, 'get_entry', [
                'session' => $sessionId,
                'module_name' => $_POST['module'] ?? 'Contacts',
                'id' => $_POST['record_id'] ?? '',
                'select_fields' => [],
                'link_name_to_fields_array' => [],
            ]);
            break;

        /**
         * getRelationships
         *
         * Fetches related records for a given module record via a
         * relationship link field.  Supports pagination (offset/limit)
         * and optionally filters out deleted records.
         */
        case 'getRelationships':
            $response = restCall($url, 'get_relationships', [
                'session' => $sessionId,
                'module_name' => $_POST['module'] ?? 'Contacts',
                'module_id' => $_POST['record_id'] ?? '',
                'link_field_name' => $_POST['link_field'] ?? '',
                'related_module_query' => '',
                'related_fields' => [],
                'related_module_link_name_to_fields_array' => [],
                'deleted' => 0,
                'order_by' => '',
                'offset' => 0,
                'limit' => 100,
            ]);
            break;

        /**
         * setRelationship
         *
         * Links one or more existing records to a parent record through a
         * relationship link field.
        */
        case 'setRelationship':
            $moduleValue = $_POST['module'] ?? '';
            $moduleIdValue = $_POST['record_id'] ?? '';
            $linkFieldValue = $_POST['link_field'] ?? '';
            $moduleName = is_string($moduleValue) ? trim($moduleValue) : '';
            $moduleId = is_string($moduleIdValue) ? trim($moduleIdValue) : '';
            $linkField = is_string($linkFieldValue) ? trim($linkFieldValue) : '';
            $relatedIds = $_POST['related_ids'] ?? [];
            if (is_string($relatedIds)) {
                $relatedIds = explode(',', $relatedIds);
            }
            if (!is_array($relatedIds)) {
                $relatedIds = [];
            }
            $relatedIds = array_values(array_filter(array_map(
                static function ($id) { return is_scalar($id) ? trim((string) $id) : ''; },
                $relatedIds
            ), static function ($id) { return $id !== '' && strlen($id) <= 64; }));

            if ($moduleName === '' || $moduleId === '' || $linkField === '' || !$relatedIds || count($relatedIds) > 100) {
                http_response_code(400);
                $response = ['error' => 'Provide a module, record ID, link field, and between 1 and 100 related IDs.'];
                break;
            }

            $response = restCall($url, 'set_relationship', [
                'session' => $sessionId,
                'module_name' => $moduleName,
                'module_id' => $moduleId,
                'link_field_name' => $linkField,
                'related_ids' => $relatedIds,
            ]);
            break;

        /**
         * getModuleFields
         *
         * Returns the field definitions (name, type, options, required)
         * for all fields in the specified module.
         */
        case 'getModuleFields':
            $response = restCall($url, 'get_module_fields', [
                'session' => $sessionId,
                'module_name' => $_POST['module'] ?? 'Contacts',
                'fields' => [],
            ]);
            break;

        /**
         * getAvailableModules
         *
         * Lists all modules available through the REST API.
         * The 'all' filter returns every accessible module.
         */
        case 'getAvailableModules':
            $response = restCall($url, 'get_available_modules', [
                'session' => $sessionId,
                'filter' => 'all',
            ]);
            break;

        /**
         * setEntry
         *
         * Creates a new record or updates an existing one.  Field values are
         * submitted as name-value pairs from the form.  If a record ID is
         * provided, that record is updated; otherwise a new record is created.
         */
        case 'setEntry':
            /** @var array $nameValues Array of ['name' => field, 'value' => value] pairs */
            $nameValues = [];
            foreach (($_POST['field_values'] ?? []) as $k => $v) {
                $nameValues[] = ['name' => $k, 'value' => $v];
            }
            $response = restCall($url, 'set_entry', [
                'session' => $sessionId,
                'module_name' => $_POST['module'] ?? 'Contacts',
                'name_value_list' => $nameValues,
            ]);
            break;

        /**
         * default
         *
         * Catch-all for any unrecognized action string sent by the client.
         * Returns a descriptive error so the caller can debug the issue.
         */
        default:
            $response = ['error' => 'Unknown action'];
    }

    /*
     * -----------------------------------------------------------------------
     * Step 3 — Clean up (logout) and return the response
     * -----------------------------------------------------------------------
     * Always log out to release the server-side session, even if the
     * preceding call failed (the logout is a best-effort operation).
     */
    restCall($url, 'logout', ['session' => $sessionId]);

    /** Output the JSON response with pretty-printing for readability */
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * ---------------------------------------------------------------------------
 * Helper: restCall
 * ---------------------------------------------------------------------------
 *
 * Sends a JSON-RPC-style request to the SinergiaCRM v4.1 REST endpoint
 * and decodes the JSON response body.
 *
 * @param string $url    Full REST endpoint URL
 * @param string $method API method name (e.g. 'login', 'get_entry', 'set_entry')
 * @param array  $params Parameters to encode as the rest_data payload
 *
 * @return array|null Decoded JSON response as an associative array, or null on failure
 */
function restCall(string $url, string $method, array $params): ?array
{
    /** Initialize a cURL handle for the target endpoint */
    $ch = curl_init($url);

    /** Build the multipart POST body expected by the v4.1 REST API */
    $post = [
        'method' => $method,
        'input_type' => 'JSON',
        'response_type' => 'JSON',
        'rest_data' => json_encode($params),
    ];

    /** Configure cURL for a POST request with a 15-second timeout */
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_0,
    ]);

    /** Execute the request and capture the raw response */
    $raw = curl_exec($ch);

    /** Close the cURL handle to free resources */
    curl_close($ch);

    /*
     * The raw response may contain HTTP headers prepended to the JSON body.
     * Split on the standard HTTP double-CRLF boundary and use the last chunk
     * (the JSON body) for decoding.
     */
    $parts = explode("\r\n\r\n", $raw, 2);
    return json_decode($parts[count($parts) - 1], true);
}

/**
 * ---------------------------------------------------------------------------
 * Determine whether a password has been configured (used by the UI).
 * ---------------------------------------------------------------------------
 */

/** @var bool $hasPass True if a CRM password is set in the current configuration */
$hasPass = !empty($config['crm_pass']);
$browserDefaults = [
    'crm_url'  => $config['crm_url'],
    'api_path' => $config['api_path'],
    'crm_user' => $config['crm_user'],
    'language' => $config['language'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SinergiaCRM API v4.1</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0 }
        [hidden] { display: none !important }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 960px; margin: 2rem auto; padding: 0 1.5rem;
            color: #222; background: #f8f9fa
        }
        h1 { font-size: 1.3rem; margin-bottom: 0.3rem; color: #1976d2 }
        .subtitle { color: #666; font-size: 0.85rem; margin-bottom: 1.5rem }
        .subtitle code { background: #eee; padding: 1px 5px; border-radius: 3px; font-size: 0.78rem }

        /* Config card */
        .config-card {
            background: #fff; border: 1px solid #e0e0e0; border-radius: 8px;
            padding: 1.25rem 1.5rem; margin-bottom: 1.5rem
        }
        .config-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem }
        .config-header h2 { font-size: 0.95rem; margin: 0; color: #333 }
        .config-title-row { display: flex; align-items: center; gap: 8px }
        .config-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem }
        @media (max-width: 768px) { .config-grid { grid-template-columns: 1fr } }
        .config-item { display: flex; flex-direction: column }
        .config-label { font-size: 0.68rem; color: #999; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px }
        .config-value { font-size: 0.82rem; color: #333; word-break: break-all }
        .config-value code { background: #eee; padding: 1px 5px; border-radius: 3px; font-size: 0.72rem }
        .override-badge {
            display: inline-block; background: #fff3e0; color: #e65100;
            font-size: 0.65rem; padding: 1px 6px; border-radius: 3px; font-weight: 500
        }
        .override-note { margin-top: 0.5rem; font-size: 0.7rem; color: #e65100 }
        .override-note code { background: #fff8e1; padding: 1px 4px; border-radius: 2px; font-size: 0.65rem }
        .settings-toggle {
            font-size: 0.75rem; color: #1976d2; cursor: pointer; user-select: none;
            padding: 3px 10px; border: 1px solid #1976d2; border-radius: 4px;
            transition: all .15s; white-space: nowrap
        }
        .settings-toggle:hover, .settings-toggle.active { background: #1976d2; color: #fff }
        .settings-form { display: none; margin-top: 10px; padding-top: 10px; border-top: 1px solid #eee }
        .settings-form.open { display: block }
        .field-group { margin-bottom: 10px }
        .field-group label { display: block; font-size: 0.78rem; font-weight: 600; color: #555; margin-bottom: 3px }
        .field-group input, .field-group select {
            width: 100%; padding: 7px 9px; border: 1px solid #ccc;
            border-radius: 5px; font-size: 0.82rem; font-family: inherit
        }
        .field-group input:focus, .field-group select:focus { outline: none; border-color: #1976d2; box-shadow: 0 0 0 2px rgba(25,118,210,.15) }
        .field-help { font-size: 0.68rem; color: #999; margin-top: 3px }
        .field-help code { background: #eee; padding: 1px 4px; border-radius: 2px; font-size: 0.65rem }
        .btn-row { display: flex; gap: 6px; margin-top: 8px }
        .btn-sm { padding: 5px 14px; font-size: 0.78rem; border-radius: 4px; border: none; cursor: pointer; font-weight: 500 }
        .btn-save { background: #1976d2; color: #fff }
        .btn-save:hover { background: #1565c0 }
        .btn-clear { background: #e0e0e0; color: #555 }
        .btn-clear:hover { background: #ccc }
        .save-msg { font-size: 0.75rem; color: #2e7d32; margin-top: 6px }
        .page-nav { display: flex; justify-content: space-between; gap: 1rem; margin-bottom: 1rem }
        .page-nav a { color: #1976d2; font-size: 0.82rem; text-decoration: none }
        .page-nav a:hover { text-decoration: underline }

        /* Tool cards */
        .cards { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem }
        @media (max-width: 768px) { .cards { grid-template-columns: 1fr } }
        .tool-card {
            background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 1.25rem
        }
        .tool-card h3 { font-size: 0.95rem; margin-bottom: 0.4rem; color: #333 }
        .tool-card p.desc { font-size: 0.78rem; color: #666; margin-bottom: 0.75rem; line-height: 1.5 }
        .input-group { display: flex; gap: 0.5rem; margin-bottom: 0.5rem }
        .input-group input { flex: 1; padding: 6px 8px; border: 1px solid #ccc; border-radius: 5px; font-size: 0.82rem }
        .input-group input:focus { outline: none; border-color: #1976d2 }
        button {
            padding: 6px 1rem; border: none; border-radius: 5px; font-size: 0.82rem;
            cursor: pointer; font-weight: 500
        }
        .btn-primary { background: #1976d2; color: #fff }
        .btn-primary:hover { background: #1565c0 }
        .btn-primary:disabled { background: #a0c4e8; cursor: not-allowed }
        .btn-green { background: #5a9e6f; color: #fff }
        .btn-green:hover { background: #4a8e5f }
        .btn-green:disabled { background: #a8d4b4; cursor: not-allowed }
        .btn-accent { background: #e07b39; color: #fff }
        .btn-accent:hover { background: #c96a2e }
        .btn-accent:disabled { background: #f0c4a8; cursor: not-allowed }

        .result { margin-top: 0.75rem }
        .loading { color: #888; font-style: italic; font-size: 0.82rem }
        .error { background: #fff0f0; border: 1px solid #fcc; color: #c00; padding: 0.6rem; border-radius: 5px; font-size: 0.8rem }
        .json-result {
            background: #263238; color: #89ddff; padding: 10px 14px; border-radius: 5px;
            font-size: 0.72rem; white-space: pre-wrap; overflow-x: auto; max-height: 400px; overflow-y: auto;
            font-family: monospace; line-height: 1.5
        }
        .summary { font-size: 0.78rem; color: #555; margin-bottom: 0.5rem }
    </style>
</head>
<body>
    <nav class="page-nav" aria-label="Example navigation">
        <a href="../index.php">← All API examples</a>
        <a href="https://github.com/SinergiaTIC/SinergiaCRM-API-Examples/blob/main/v4.1/README.md" target="_blank" rel="noopener">Read Docs ↗</a>
    </nav>
    <!--
        -------------------------------------------------------------------
        Page Header
        -------------------------------------------------------------------
        Displays the title, API endpoint info, and a subtitle describing
        the authentication method used.
    -->
    <h1>SinergiaCRM API v4.1</h1>
    <p class="subtitle">
        REST client using <code>v4_1_SticCustom</code> endpoint with username/password authentication.
    </p>

    <!--
        -------------------------------------------------------------------
        Connection Settings Card
        -------------------------------------------------------------------
        Shows the active configuration (CRM URL, API path, username).
            Browser-local overrides are marked. Clicking "Edit" opens the form
            where this browser's settings can be saved to localStorage.
    -->
    <div class="config-card">
        <!-- Card header with title and toggle button -->
        <div class="config-header">
            <div class="config-title-row">
                <h2>Connection Settings</h2>
                <span class="override-badge" id="overrideBadge" hidden>browser override</span>
            </div>
            <span class="settings-toggle" onclick="document.getElementById('settingsForm').classList.toggle('open'); this.classList.toggle('active')">
                &#9881; Edit
            </span>
        </div>

        <!-- Grid displaying the current configuration values read-only -->
        <div class="config-grid">
            <div class="config-item">
                <span class="config-label">CRM URL</span>
                <span class="config-value" id="displayCrmUrl"><?= htmlspecialchars($config['crm_url']) ?></span>
            </div>
            <div class="config-item">
                <span class="config-label">API Path</span>
                <span class="config-value"><code id="displayApiPath"><?= htmlspecialchars($config['api_path']) ?></code></span>
            </div>
            <div class="config-item">
                <span class="config-label">Username</span>
                <span class="config-value" id="displayCrmUser"><?= htmlspecialchars($config['crm_user']) ?></span>
            </div>
        </div>

        <div class="override-note" id="overrideNote" hidden>Settings are stored only in this browser's localStorage.</div>

        <!--
            Inline settings form (hidden by default, toggled via CSS class 'open').
            Stores browser-local values; API requests carry them in a per-request header.
        -->
        <form id="settingsForm" class="settings-form">
            <div class="field-group">
                <label for="crm_url">CRM URL</label>
                <input type="text" name="crm_url" id="crm_url" value="<?= htmlspecialchars($config['crm_url']) ?>" placeholder="http://localhost:8000/sinergiacrm">
            </div>
            <div class="field-group">
                <label for="api_path">API Path</label>
                <input type="text" name="api_path" id="api_path" value="<?= htmlspecialchars($config['api_path']) ?>" placeholder="/custom/service/v4_1_SticCustom/rest.php">
                <div class="field-help">REST endpoint path relative to the CRM URL.</div>
            </div>
            <div class="field-group">
                <label for="crm_user">CRM Username</label>
                <input type="text" name="crm_user" id="crm_user" value="<?= htmlspecialchars($config['crm_user']) ?>">
            </div>
            <div class="field-group">
                <label for="crm_pass">CRM Password</label>
                <input type="password" name="crm_pass" id="crm_pass" value="" placeholder="<?= $hasPass ? 'Password configured — type a new one to override' : 'Enter password' ?>">
                <div class="field-help"><?= $hasPass ? 'A password is configured in <code>.env</code>. Leave blank to keep it.' : 'Required for REST authentication.' ?></div>
            </div>
            <div class="field-group">
                <label for="language">Language</label>
                <select name="language" id="language">
                    <option value="es_ES" <?= ($config['language'] ?? '') === 'es_ES' ? 'selected' : '' ?>>Español (es_ES)</option>
                    <option value="ca_ES" <?= ($config['language'] ?? '') === 'ca_ES' ? 'selected' : '' ?>>Català (ca_ES)</option>
                    <option value="gl_ES" <?= ($config['language'] ?? '') === 'gl_ES' ? 'selected' : '' ?>>Galego (gl_ES)</option>
                    <option value="en_us" <?= ($config['language'] ?? '') === 'en_us' ? 'selected' : '' ?>>English (en_us)</option>
                </select>
            </div>
            <div class="btn-row">
                <button type="button" id="saveSettings" class="btn-sm btn-save">Save for this browser</button>
                <button type="button" id="clearSettings" class="btn-sm btn-clear" hidden>Revert to .env Defaults</button>
            </div>
            <div class="save-msg" id="saveMsg" role="status"></div>
        </form>
    </div>

    <script type="application/json" id="v41ConfigDefaults"><?= json_encode($browserDefaults, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

    <!--
        -------------------------------------------------------------------
        Tool card modules
        -------------------------------------------------------------------
        The module list is shared across multiple GET / read-oriented
        tool cards so users can switch between common modules.
    -->
    <?php $toolModules = ['Contacts', 'Accounts', 'Project', 'stic_Contacts_Relationships']; ?>

    <!--
        -------------------------------------------------------------------
        Row 1: Dropdown List Lookup  |  Get Record by ID
        -------------------------------------------------------------------
    -->
    <div class="cards">
        <!--
            Dropdown List Lookup card
            Allows searching for any dropdown list key in the CRM's
            app_list_strings and returns the matched values.
        -->
        <div class="tool-card">
            <h3>Dropdown List Lookup</h3>
            <p class="desc">Look up any dropdown list by its key from the CRM's <code>app_list_strings</code>. Examples: <code>stic_contacts_relationships_types_list</code>, <code>account_type_dom</code>, <code>gender_list</code>.</p>
            <div class="input-group">
                <input type="text" id="dropdownKey" placeholder="Dropdown list key" value="stic_contacts_relationships_types_list">
            </div>
            <button class="btn-accent" id="btnDropdown" onclick="apiCall('getDropdownList', 'dropdownResult', { list_key: document.getElementById('dropdownKey').value.trim() })">Look Up</button>
            <div id="dropdownResult" class="result"></div>
        </div>

        <!--
            Get Record by ID card
            Fetches a single record's fields by module + UUID.
        -->
        <div class="tool-card">
            <h3>Get Record by ID</h3>
            <p class="desc">Fetch a single record. The response contains all fields and their values.</p>
            <div class="input-group">
                <select id="entryModule">
                    <?php foreach ($toolModules as $m): ?><option value="<?= $m ?>"><?= $m ?></option><?php endforeach; ?>
                </select>
                <input type="text" id="entryId" placeholder="Record ID (UUID)">
            </div>
            <button class="btn-green" id="btnEntry" onclick="apiCallEntry()">Get Record</button>
            <div id="entryResult" class="result"></div>
        </div>
    </div>

    <!-- Set Relationship -->
    <div class="cards">
        <div class="tool-card">
            <h3>Set Relationship</h3>
            <p class="desc">Link one or more existing records to a record through a relationship link field. Example: Contacts → <code>accounts</code>.</p>
            <div class="input-group">
                <select id="setRelModule" aria-label="Base module">
                    <?php foreach ($toolModules as $m): ?><option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option><?php endforeach; ?>
                </select>
                <input type="text" id="setRelRecordId" placeholder="Base record ID">
            </div>
            <div class="input-group">
                <input type="text" id="setRelLinkField" placeholder="Link field (e.g. accounts)">
                <input type="text" id="setRelRelatedIds" placeholder="Related IDs, comma-separated">
            </div>
            <button class="btn-green" id="btnSetRelationship" onclick="apiCallSetRelationship()">Set Relationship</button>
            <div id="setRelResult" class="result"></div>
        </div>
    </div>

    <!--
        -------------------------------------------------------------------
        Row 2: Get Module Fields  |  Get Relationships
        -------------------------------------------------------------------
    -->
    <div class="cards">
        <!--
            Get Module Fields card
            Retrieves field definitions (name, type, options) for a module.
        -->
        <div class="tool-card">
            <h3>Get Module Fields</h3>
            <p class="desc">Retrieve the field definitions (name, type, options) for a given module.</p>
            <div class="input-group">
                <select id="fieldsModule">
                    <?php foreach ($toolModules as $m): ?><option value="<?= $m ?>"><?= $m ?></option><?php endforeach; ?>
                </select>
            </div>
            <button class="btn-accent" id="btnFields" onclick="apiCall('getModuleFields', 'fieldsResult', { module: document.getElementById('fieldsModule').value })">Get Fields</button>
            <div id="fieldsResult" class="result"></div>
        </div>

        <!--
            Get Relationships card
            Fetches related records via a relationship link, given a module and record ID.
        -->
        <div class="tool-card">
            <h3>Get Relationships</h3>
            <p class="desc">Fetch related records for a given module and record ID.</p>
            <div class="input-group">
                <select id="relModule" style="flex:0.8">
                    <?php foreach ($toolModules as $m): ?><option value="<?= $m ?>"><?= $m ?></option><?php endforeach; ?>
                </select>
                <input type="text" id="relId" placeholder="Record ID">
                <input type="text" id="relLink" placeholder="Link field" style="flex:0.8">
            </div>
            <button class="btn-accent" id="btnRels" onclick="apiCallRelationships()">Get Relationships</button>
            <div id="relResult" class="result"></div>
        </div>
    </div>

    <!--
        -------------------------------------------------------------------
        Row 3: Get Language Definition  |  Set Entry (Create / Update)
        -------------------------------------------------------------------
    -->
    <div class="cards">
        <!--
            Get Language Definition card
            Downloads the full language string definition for the configured
            locale.  Useful for inspecting translated dropdown values.
        -->
        <div class="tool-card">
            <h3>Get Language Definition</h3>
            <p class="desc">Fetch the language strings for the configured language. Useful for getting translated dropdown values and labels.</p>
            <button class="btn-primary" id="btnLang" onclick="apiCall('getLanguageDefinition', 'langResult')">Get Language</button>
            <div id="langResult" class="result"></div>
        </div>

        <!--
            Set Entry (Create / Update) card
            Sends name-value pairs to create a new record or update an
            existing one.  Leave the ID empty to create; provide an ID to update.
        -->
        <div class="tool-card">
            <h3>Set Entry (Create / Update)</h3>
            <p class="desc">Create or update a record by sending name-value pairs.</p>
            <div class="input-group">
                <select id="setModule" style="flex:0.7">
                    <option value="Contacts">Contacts</option>
                    <option value="Accounts">Accounts</option>
                </select>
                <input type="text" id="setId" placeholder="Record ID (empty = create)">
            </div>
            <div class="input-group">
                <input type="text" id="setField1" placeholder="Field name (e.g. last_name)">
                <input type="text" id="setVal1" placeholder="Value">
            </div>
            <div class="input-group">
                <input type="text" id="setField2" placeholder="Field name">
                <input type="text" id="setVal2" placeholder="Value (optional)">
            </div>
            <button class="btn-green" id="btnSet" onclick="apiCallSet()">Set Entry</button>
            <div id="setResult" class="result"></div>
        </div>
    </div>

    <!--
        -------------------------------------------------------------------
        Client-side JavaScript
        -------------------------------------------------------------------
        All tool cards use AJAX (fetch POST to the same file) to invoke the
        server-side API handler.  Each function builds a FormData payload,
        disables the button during the request, and renders the JSON response
        inside a <pre> block.
    -->

    <script>
        const v41StorageKey = 'sinergiacrm.apiV41.configOverrides.v1';
        const v41Defaults = JSON.parse(document.getElementById('v41ConfigDefaults').textContent);
        let v41Overrides = {};
        try {
            const saved = JSON.parse(localStorage.getItem(v41StorageKey) || '{}');
            if (saved && typeof saved === 'object' && !Array.isArray(saved)) {
                ['crm_url', 'api_path', 'crm_user', 'crm_pass', 'language'].forEach((key) => {
                    if (typeof saved[key] === 'string') v41Overrides[key] = saved[key];
                });
            }
        } catch (error) {
            document.getElementById('saveMsg').textContent = 'Browser storage unavailable; using .env defaults.';
        }

        function renderV41Settings() {
            const cfg = { ...v41Defaults, ...v41Overrides };
            document.getElementById('crm_url').value = cfg.crm_url || '';
            document.getElementById('api_path').value = cfg.api_path || '';
            document.getElementById('crm_user').value = cfg.crm_user || '';
            document.getElementById('crm_pass').value = v41Overrides.crm_pass || '';
            document.getElementById('language').value = cfg.language || 'es_ES';
            document.getElementById('displayCrmUrl').textContent = cfg.crm_url || '';
            document.getElementById('displayApiPath').textContent = cfg.api_path || '';
            document.getElementById('displayCrmUser').textContent = cfg.crm_user || '';
            const hasOverrides = Object.keys(v41Overrides).length > 0;
            document.getElementById('overrideBadge').hidden = !hasOverrides;
            document.getElementById('overrideNote').hidden = !hasOverrides;
            document.getElementById('clearSettings').hidden = !hasOverrides;
        }

        document.getElementById('saveSettings').addEventListener('click', () => {
            const next = {
                crm_url: document.getElementById('crm_url').value.trim(),
                api_path: document.getElementById('api_path').value.trim(),
                crm_user: document.getElementById('crm_user').value.trim(),
                language: document.getElementById('language').value,
            };
            const password = document.getElementById('crm_pass').value;
            if (password !== '') next.crm_pass = password;
            try {
                localStorage.setItem(v41StorageKey, JSON.stringify(next));
                v41Overrides = next;
                renderV41Settings();
                document.getElementById('saveMsg').textContent = 'Settings saved in this browser only.';
            } catch (error) {
                document.getElementById('saveMsg').textContent = 'Could not save settings in this browser.';
            }
        });

        document.getElementById('clearSettings').addEventListener('click', () => {
            try { localStorage.removeItem(v41StorageKey); } catch (error) {}
            v41Overrides = {};
            renderV41Settings();
            document.getElementById('saveMsg').textContent = 'Browser overrides cleared; using .env defaults.';
        });

        function v41ApiFetch(url, options = {}) {
            const headers = new Headers(options.headers || {});
            headers.set('X-V4-API-Config', btoa(encodeURIComponent(JSON.stringify(v41Overrides))));
            return fetch(url, { ...options, headers });
        }

        renderV41Settings();

        /**
         * Generic API call helper used by most tool cards.
         *
         * Sends a POST request to the same page with the api_action
         * field plus any extra parameters.  Renders the JSON response
         * into the specified result container.
         *
         * @param {string} action     The api_action value (maps to a REST method server-side)
         * @param {string} resultId   DOM ID of the result container element
         * @param {object} extraParams Additional key/value pairs to include in the POST body
         *
         * @returns {Promise<void>}
         */
        async function apiCall(action, resultId, extraParams = {}) {
            const btn = document.getElementById(
                action === 'getAvailableModules' ? 'btnModules' :
                action === 'getModuleFields' ? 'btnFields' :
                action === 'getLanguageDefinition' ? 'btnLang' :
                action === 'getDropdownList' ? 'btnDropdown' : 'btnModules');
            const result = document.getElementById(resultId);

            /** Disable the button and show a loading indicator while the request is in flight */
            btn.disabled = true;
            result.innerHTML = '<div class="loading">Authenticating and executing...</div>';

            /** Build the POST form data payload */
            const fd = new FormData();
            fd.append('api_action', action);
            for (const [k, v] of Object.entries(extraParams)) {
                fd.append(k, v);
            }

            try {
                /** POST to the same URL (empty action = self) */
                const resp = await v41ApiFetch('', { method: 'POST', body: fd });
                const data = await resp.json();

                /** Render the JSON response inside a dark-themed <pre> block */
                result.innerHTML = `<div class="summary">${action} response:</div><pre class="json-result">${esc(JSON.stringify(data, null, 2))}</pre>`;
            } catch (e) {
                /** Render any network or parsing errors in a red error box */
                result.innerHTML = `<div class="error">Request failed: ${esc(e.message)}</div>`;
            } finally {
                /** Always re-enable the button when done */
                btn.disabled = false;
            }
        }

        /**
         * Specialized handler for the Get Record by ID tool card.
         *
         * Validates that a record ID has been entered, builds the FormData
         * with module and record_id, and delegates to the server-side API handler.
         *
         * @returns {Promise<void>}
         */
        async function apiCallEntry() {
            const btn = document.getElementById('btnEntry');
            const result = document.getElementById('entryResult');
            const module = document.getElementById('entryModule').value;
            const id = document.getElementById('entryId').value.trim();

            /** Guard: a record ID is required */
            if (!id) { result.innerHTML = '<div class="error">Please enter a record ID.</div>'; return; }

            /** Disable the button and show loading state */
            btn.disabled = true;
            result.innerHTML = '<div class="loading">Authenticating and executing...</div>';

            const fd = new FormData();
            fd.append('api_action', 'getEntry');
            fd.append('module', module);
            fd.append('record_id', id);

            try {
                const resp = await v41ApiFetch('', { method: 'POST', body: fd });
                const data = await resp.json();
                result.innerHTML = `<div class="summary">get_entry (${module}) response:</div><pre class="json-result">${esc(JSON.stringify(data, null, 2))}</pre>`;
            } catch (e) {
                result.innerHTML = `<div class="error">Request failed: ${esc(e.message)}</div>`;
            } finally {
                btn.disabled = false;
            }
        }

        /**
         * Specialized handler for the Get Relationships tool card.
         *
         * Validates that a record ID is entered, then posts module, record_id,
         * and link_field to the server-side API handler.
         *
         * @returns {Promise<void>}
         */
        async function apiCallRelationships() {
            const btn = document.getElementById('btnRels');
            const result = document.getElementById('relResult');
            const module = document.getElementById('relModule').value;
            const id = document.getElementById('relId').value.trim();
            const link = document.getElementById('relLink').value.trim();

            /** Guard: a record ID is required */
            if (!id) { result.innerHTML = '<div class="error">Please enter a record ID.</div>'; return; }

            /** Disable the button and show loading state */
            btn.disabled = true;
            result.innerHTML = '<div class="loading">Authenticating and executing...</div>';

            const fd = new FormData();
            fd.append('api_action', 'getRelationships');
            fd.append('module', module);
            fd.append('record_id', id);
            fd.append('link_field', link);

            try {
                const resp = await v41ApiFetch('', { method: 'POST', body: fd });
                const data = await resp.json();
                result.innerHTML = `<div class="summary">get_relationships (${module}) response:</div><pre class="json-result">${esc(JSON.stringify(data, null, 2))}</pre>`;
            } catch (e) {
                result.innerHTML = `<div class="error">Request failed: ${esc(e.message)}</div>`;
            } finally {
                btn.disabled = false;
            }
        }

        /** Link one or more related record IDs through a v4.1 relationship field. */
        async function apiCallSetRelationship() {
            const btn = document.getElementById('btnSetRelationship');
            const result = document.getElementById('setRelResult');
            const module = document.getElementById('setRelModule').value;
            const recordId = document.getElementById('setRelRecordId').value.trim();
            const linkField = document.getElementById('setRelLinkField').value.trim();
            const relatedIds = document.getElementById('setRelRelatedIds').value
                .split(/[\s,]+/)
                .map((id) => id.trim())
                .filter(Boolean);

            if (!recordId || !linkField || relatedIds.length === 0) {
                result.innerHTML = '<div class="error">Enter the base record ID, link field, and at least one related ID.</div>';
                return;
            }
            if (relatedIds.length > 100) {
                result.innerHTML = '<div class="error">A maximum of 100 related IDs can be linked per request.</div>';
                return;
            }

            btn.disabled = true;
            result.innerHTML = '<div class="loading">Authenticating and setting relationship...</div>';
            const fd = new FormData();
            fd.append('api_action', 'setRelationship');
            fd.append('module', module);
            fd.append('record_id', recordId);
            fd.append('link_field', linkField);
            relatedIds.forEach((id) => fd.append('related_ids[]', id));

            try {
                const resp = await v41ApiFetch('', { method: 'POST', body: fd });
                const data = await resp.json();
                result.innerHTML = `<div class="summary">set_relationship (${module}.${linkField}) response:</div><pre class="json-result">${esc(JSON.stringify(data, null, 2))}</pre>`;
            } catch (e) {
                result.innerHTML = `<div class="error">Request failed: ${esc(e.message)}</div>`;
            } finally {
                btn.disabled = false;
            }
        }

        /**
         * Specialized handler for the Set Entry (Create / Update) tool card.
         *
         * Collects up to two name-value pairs from the form inputs, validates
         * that at least one field is filled, and posts to the server-side handler.
         * If a record ID is provided, the existing record is updated; otherwise
         * a new record is created.
         *
         * @returns {Promise<void>}
         */
        async function apiCallSet() {
            const btn = document.getElementById('btnSet');
            const result = document.getElementById('setResult');
            const module = document.getElementById('setModule').value;
            const id = document.getElementById('setId').value.trim();

            const fd = new FormData();
            fd.append('api_action', 'setEntry');
            fd.append('module', module);
            if (id) fd.append('record_id', id);

            /** Collect field values from the form inputs */
            const f1 = document.getElementById('setField1').value.trim();
            const v1 = document.getElementById('setVal1').value.trim();
            const f2 = document.getElementById('setField2').value.trim();
            const v2 = document.getElementById('setVal2').value.trim();

            if (f1) { fd.append('field_values[' + f1 + ']', v1); }
            if (f2) { fd.append('field_values[' + f2 + ']', v2); }

            /** Guard: at least one field name + value pair is required */
            if (!f1 && !f2) { result.innerHTML = '<div class="error">Enter at least one field name and value.</div>'; return; }

            /** Disable the button and show loading state */
            btn.disabled = true;
            result.innerHTML = '<div class="loading">Authenticating and executing...</div>';

            try {
                const resp = await v41ApiFetch('', { method: 'POST', body: fd });
                const data = await resp.json();
                result.innerHTML = `<div class="summary">set_entry (${module}) response:</div><pre class="json-result">${esc(JSON.stringify(data, null, 2))}</pre>`;
            } catch (e) {
                result.innerHTML = `<div class="error">Request failed: ${esc(e.message)}</div>`;
            } finally {
                btn.disabled = false;
            }
        }

        /**
         * Escape a string for safe HTML insertion.
         *
         * Creates a text node from the string value and returns its HTML
         * representation, preventing XSS when rendering user-provided or
         * API-returned content as innerHTML.
         *
         * @param {string} s The string value to escape
         *
         * @returns {string} HTML-escaped string
         */
        function esc(s) {
            const div = document.createElement('div');
            div.appendChild(document.createTextNode(String(s)));
            return div.innerHTML;
        }
    </script>
</body>
</html>
