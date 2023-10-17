<?php

// LIST OF EXAMPLES
// It is recommended to run the examples one at a time. To do this we can comment on the active example and uncomment another one.

// EXAMPLE 1
// Get the image associated with the <FIELD> field of the record whose id = <ID>

    // $params = array(
    //     'image_data' => array( 
    //         'id' => '<ID>',
    //         'field' => '<FIELD>',
    //     ),
    // );

    // // Execute the call to the corresponding API client function
    // $result = $apiClient->getImage($params);


// EXAMPLE 2 (Es necesario activar el EJEMPLO 1)
// Render the received binary image

    // echo '<img src="data:'.$result->image_data->mime_type.';base64,' . $result->image_data->data .'" >';
