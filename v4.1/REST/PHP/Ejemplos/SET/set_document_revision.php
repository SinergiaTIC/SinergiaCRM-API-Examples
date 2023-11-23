<?php

// Link to SuiteCRM documentation
// https://docs.suitecrm.com/developer/api/api-v4.1-methods/#_set_document_revision

// LIST OF EXAMPLES
// It is recommended to run the examples one at a time. To do this we can comment on the active example and uncomment another one.

// In addition, SET type calls modify the database so it is recommended:
// - Use the TEST environment to perform tests.
// - Be careful with what we do
// - Make sure all required fields are being entered


// EXAMPLE 1
// Sets the document revision test_image.jpg (existing in the assets folder) in the document whose id = <ID>
// To create a document it is possible to activate example 8 of Examples/SET/set_entry.php

    // $fileName = 'test_image.jpg';
    // $file = 'Ejemplos/assets/'.$fileName;
    // $contents = file_get_contents($file);

    // $params = array(
    //     'note' => array( 
    //         'id' => '<ID>',
    //         'file' => base64_encode($contents),
    //         'filename' => $fileName,
    //     ),
    // );


// Execute the call to the corresponding API client function
$apiClient->setDocumentRevision($params);
