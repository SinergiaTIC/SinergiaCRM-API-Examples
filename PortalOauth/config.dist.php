<?php
/**
 * Configuration template — copy to config.php and edit.
 * Run: cp config.dist.php config.php
 */

return [
    // Public CRM URL (browser-accessible)
    'crm_url'       => getenv('CRM_URL')          ?: 'http://localhost:8000/sinergiacrm',

    // Internal URL for curl calls from PHP (Docker container can't reach localhost:8000). If remote, just use the same as 'crm_url'.

    'crm_internal'  => getenv('CRM_INTERNAL_URL') ?: 'http://sw-webserver/sinergiacrm',

    // OAuth2 Client UUID — create in CRM: Administration → OAuth2 Clients
    'client_id'     => getenv('OAUTH_CLIENT_ID')  ?: 'your-oauth2-client-uuid',

    // Must match the redirect_uri on the OAuth2 Client exactly
    'redirect_uri'  => getenv('OAUTH_REDIRECT_URI') ?: 'http://localhost:8000/SinergiaCRM-API-Examples/PortalOauth/callback.php',
];
