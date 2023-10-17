<?php

////////////////////    CONFIG  /////////////////////////

// SinergiaCRM URL
$url = '<SINERGIACRM_URL>/custom/service/v4_1_SticCustom/rest.php';

// CRM User
$username = '<CRM_USER>';

// User password
$password = '<CRM_USER_PASSWORD>';

// Language in which we want to interact with the CRM API
$language = 'es_ES'; // es_ES, ca_ES, gl_ES, en_us

// Indicates whether to send an email or not when saving and assigning a new record to a user
$notifyOnSave = false;

// Indicates whether or not we want to show information about the requests on the screen
$verbose = true;



////////////////////    LOGIC APP    /////////////////////////

// Create the API client, start a session with the CRM and get a session ID
include_once 'APIClient.php';
$apiClient = new APIClient($url, $verbose);
$apiClient->sessionId = $apiClient->login($username, $password, $language, $notifyOnSave);



// LIST OF EXAMPLES
// It is recommended to run the examples one at a time. To do this we can comment on the active example and uncomment another one.

// GETTERS
// include_once 'Ejemplos/GET/get_available_modules.php';
// include_once 'Ejemplos/GET/get_document_revision.php';
// include_once 'Ejemplos/GET/get_entry_list.php';
// include_once 'Ejemplos/GET/get_entry.php';
// include_once 'Ejemplos/GET/get_image.php';
// include_once 'Ejemplos/GET/get_language_definition.php';
// include_once 'Ejemplos/GET/get_module_fields.php';
// include_once 'Ejemplos/GET/get_relationships.php';

// SETTERS
// include_once 'Ejemplos/SET/set_entry.php';
// include_once 'Ejemplos/SET/set_relationship.php';
// include_once 'Ejemplos/SET/set_image.php';



// Logout the session
$apiClient->logout();

?>