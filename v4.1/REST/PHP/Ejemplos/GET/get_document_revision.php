<?php

// Link to SuiteCRM documentation
// https://docs.suitecrm.com/developer/api/api-v4.1-methods/#_get_document_revision


// LIST OF EXAMPLES
// It is recommended to run the examples one at a time. To do this we can comment on the active example and uncomment another one.

// EXAMPLE 1
// Get the information related to the document version whose ID = <DOCUMENT_REVISION_ID>

    // $documentRevisionId = '<DOCUMENT_REVISION_ID>';
    // $params = array(
    //     'i' => $documentRevisionId,
    // );

    // // Execute the call to the corresponding API client function
    // $result = $apiClient->getDocumentRevision($params);


// EXAMPLE 2 (It is necessary to activate EXAMPLE 1)
// Render the received binary image

    // echo '<img src="data:image/gif;base64,' . $result->document_revision->file .'" >';

    // // In order to render the image correctly it is necessary to know the file_mime_type. 
    // // To collect this data we have to use the get_entry method with the ID of the same document version. 
    // $params = array(
    //     'module_name' => 'DocumentRevisions',
    //     'id' => $documentRevisionId,
    //     'select_fields' => array('file_mime_type'),
    //     'deleted' => 0,
    // );


    // // Execute the API client getEntry() function call
    // $resultMime = $apiClient->getEntry($params);

    // // Render the received binary image
    // echo '<a download="' . $result->document_revision->filename .'" href="data:'. $resultMime->entry_list[0]->name_value_list->file_mime_type->value .';base64,' . $result->document_revision->file .'" title="Descargar" > Descargar Fichero</a>';

