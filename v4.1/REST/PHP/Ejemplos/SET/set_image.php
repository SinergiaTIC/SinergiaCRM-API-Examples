<?php

// LIST OF EXAMPLES
// It is recommended to run the examples one at a time. To do this we can comment on the active example and uncomment another one.

// In addition, SET type calls modify the database so it is recommended:
// - Use the TEST environment to perform tests.
// - Be careful with what we do
// - Make sure all required fields are being entered


// EXAMPLE 1
// Sets the image test_image.jpg (existing in the assets folder) in the <FIELD> field of the record whose id = <ID> of the module <MODULE>

    // $fileName = 'test_image.jpg';
    // $file = 'Ejemplos/assets/'.$fileName;
    // $contents = file_get_contents($file);

    // $params = array(
    //     'image_data' => array( 
    //         'id' => '<ID>',
    //         'module' => '<MODULE>',
    //         'field' => '<FIELD>',
    //         'file' => base64_encode($contents),
    //         'filename' => $fileName,
    //     ),
    // );


// Execute the call to the corresponding API client function
$apiClient->setImage($params);
