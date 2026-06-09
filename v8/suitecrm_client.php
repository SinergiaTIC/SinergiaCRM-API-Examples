#!/usr/bin/env php
<?php
/**
 * SuiteCRM / SinergiaCRM API V8 Client (PHP CLI)
 * Connects using client_credentials grant, gets an access token, and lists contacts.
 *
 * Usage: php suitecrm_client.php [--all]
 *
 * Configuration is read from .env in the same directory.
 * Copy .env.example to .env and set your credentials.
 */

define('APP_DIR', __DIR__);
define('JSONAPI_MIME', 'application/vnd.api+json');

function loadEnv(): array
{
    $envFile = APP_DIR . '/.env';
    $vars = [];
    if (file_exists($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $vars[trim($key)] = trim($value);
        }
    }
    return $vars;
}

$env = loadEnv();
if (empty($env)) {
    fwrite(STDERR, "Error: .env file not found or empty. Copy .env.example to .env and configure it.\n");
    exit(1);
}

define('SUITECRM_BASE_URL', rtrim($env['SUITECRM_BASE_URL'] ?? 'http://localhost:8000', '/'));
define('OAUTH2_CLIENT_ID', $env['OAUTH2_CLIENT_ID'] ?? '');
define('OAUTH2_CLIENT_SECRET', $env['OAUTH2_CLIENT_SECRET'] ?? '');

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

function fetchContacts(string $token, int $pageSize = 20, ?int $maxPages = null): array
{
    $contacts = [];
    $pageNumber = 1;

    while (true) {
        $data = apiGet('/Api/V8/module/Contacts', $token, [
            'page[number]' => $pageNumber,
            'page[size]' => $pageSize,
        ]);
        $pageContacts = $data['data'] ?? [];
        if (empty($pageContacts)) {
            break;
        }
        $contacts = array_merge($contacts, $pageContacts);
        $totalPages = $data['meta']['total-pages'] ?? 1;

        echo sprintf("  Page %d/%d (%d contacts)\n", $pageNumber, $totalPages, count($pageContacts));

        if ($pageNumber >= $totalPages || ($maxPages !== null && $pageNumber >= $maxPages)) {
            break;
        }
        $pageNumber++;
    }

    return [$contacts, $totalPages];
}

echo "=== SuiteCRM API V8 Client ===\n";
echo "Base URL: " . SUITECRM_BASE_URL . "\n\n";

echo "Step 1: Getting access token...\n";
$token = getAccessToken();
echo "  Token: " . substr($token, 0, 50) . "...\n\n";

$allFlag = in_array('--all', $argv ?? []);
$maxPages = $allFlag ? null : 3;
$pageSize = 20;

$label = $allFlag ? 'all pages' : "up to $maxPages pages";
echo "Step 2: Fetching contacts ($label, $pageSize per page)...\n";
[$contacts, $totalPages] = fetchContacts($token, $pageSize, $maxPages);
echo "\n";

$estimatedTotal = $totalPages * $pageSize;
echo sprintf("Contacts fetched: %d of ~%d total\n\n", count($contacts), $estimatedTotal);

$i = 1;
foreach ($contacts as $contact) {
    $attrs = $contact['attributes'] ?? [];
    $contactId = $contact['id'] ?? 'N/A';
    $firstName = $attrs['first_name'] ?? '';
    $lastName = $attrs['last_name'] ?? '';
    $email = $attrs['email1'] ?? '';
    $fullName = $attrs['name'] ?? trim("$firstName $lastName");

    echo sprintf("  %d. ID: %s\n", $i++, $contactId);
    echo sprintf("     Name: %s\n", $fullName);
    if ($email) {
        echo sprintf("     Email: %s\n", $email);
    }
    echo "\n";
}

if (!$allFlag && $estimatedTotal > count($contacts)) {
    echo sprintf("Showing %d of ~%d contacts.\nUse --all to fetch all pages.\n", count($contacts), $estimatedTotal);
}
