<?php

// Link to SuiteCRM documentation
// https://docs.suitecrm.com/developer/api/api-v4.1-methods/#_get_available_modules


// LIST OF EXAMPLES
// It is recommended to run the examples one at a time. To do this we can comment on the active example and uncomment another one.

// EXAMPLE 1
// Get all CRM modules

    // $params = array(
    //     'filter' => 'all', // Available options: 'default', 'mobile' y 'all'
    // );


// Execute the call to the corresponding API client function
$apiClient->getAvailableModules($params);