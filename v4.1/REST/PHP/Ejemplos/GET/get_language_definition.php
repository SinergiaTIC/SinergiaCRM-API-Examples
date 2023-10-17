<?php

// Link to SuiteCRM documentation
// https://docs.suitecrm.com/developer/api/api-v4.1-methods/#_get_language_definition


// LIST OF EXAMPLES
// It is recommended to run the examples one at a time. To do this we can comment on the active example and uncomment another one.

// EXAMPLE 1
// Get the labels or text strings associated with the People module.

    // $params = array(
    //     'modules' => 'Contacts',
    //     'md5' => false,
    // );


// EXAMPLE 2
// Get the identifiers and values of the elements of all the CRM drop-down lists.

    // $params = array(
    //     'modules' => 'app_list_strings',
    //     'md5' => false,
    // );


// Execute the call to the corresponding API client function
$apiClient->getLanguageDefinition($params);