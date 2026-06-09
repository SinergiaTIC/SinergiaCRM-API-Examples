<?php
/**
 * Configuration file — edit these values for your CRM.
 * Copy this file to config.php (ignored by git) and set your credentials.
 */

return [
    // URL of the SinergiaCRM instance
    'crm_url' => getenv('CRM_URL') ?: 'http://localhost:8000/sinergiacrm',

    // OAuth2 Client credentials — create in Administration → OAuth2 Clients and Tokens
    'client_id'     => getenv('OAUTH_CLIENT_ID')     ?: 'your-oauth2-client-uuid',

    // This app's callback URL (must match the registered redirect_uri in OAuth2Clients)
    'redirect_uri'  => getenv('OAUTH_REDIRECT_URI')  ?: 'http://localhost:8000/clientOauth/callback.php',
];
