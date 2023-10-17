<?php

// Link to SuiteCRM documentation
// https://docs.suitecrm.com/developer/api/api-v4.1-methods/#_get_relationships


// LIST OF EXAMPLES
// It is recommended to run the examples one at a time. To do this we can comment on the active example and uncomment another one.

// EXAMPLE 1
// Get the id and name of the location related to the event whose id = <ID>

// $params = array(
//     'module_name' => 'stic_Events',
//     "module_id" => '<ID>', 
//     "link_field_name" => "stic_events_fp_event_locationsfp_event_locations_ida",
//     "related_module_query" => "",
//     "related_fields" => array(
//         'id', 
//         'name'),
//     "related_module_link_name_to_fields_array" => array(),
//     "deleted" => 0,
// );


// EXAMPLE 2
// Get the id, name and last name of the contact related to the registration whose id = <ID>

    // $params = array(
    //     'module_name' => 'stic_Registrations',
    //     "module_id" => '<ID>',
    //     "link_field_name" => "stic_registrations_contactscontacts_ida",
    //     "related_module_query" => "",
    //     "related_fields" => array(
    //         'id', 
    //         'first_name', 
    //         'last_name'),
    //     "related_module_link_name_to_fields_array" => array(),
    //     "deleted" => 0,
    // );


// EXAMPLE 3
// Get the id and name of the security groups that relate to the contact whose id = <ID>

    // $params = array(
    //     'module_name' => 'Contacts',
    //     "module_id" => '<ID>', 
    //     "link_field_name" => "SecurityGroups",
    //     "related_module_query" => "",
    //     "related_fields" => array(
    //         'id', 
    //         'name'),
    //     "related_module_link_name_to_fields_array" => array(),
    //     "deleted" => 0,
    // );


// EXAMPLE 4
// Get the id and name of the documents related to the contact whose id = <ID>
    // $params = array(
    //     'module_name' => 'Contacts',
    //     "module_id" => '<ID>', 
    //     "link_field_name" => "documents",
    //     "related_module_query" => "",
    //     "related_fields" => array(
    //         'id', 
    //         'name'
    //     ),
    //     "related_module_link_name_to_fields_array" => array(),
    //     "deleted" => 0,
    // );


// EXAMPLE 5
// Starting from the scenario in which Events and Documents modules have a personalized relationship.
// Get the identifier of the document revision related to the event whose id = <ID>

    // $params = array(
    //     'module_name' => 'stic_Events',
    //     "module_id" => '<ID>', 
    //     "link_field_name" => "stic_events_documents_1documents_idb",
    //     "related_module_query" => "",
    //     "related_fields" => array(
    //         'document_revision_id'),
    //     "related_module_link_name_to_fields_array" => array(),
    //     "deleted" => 0,
    // );


// EXAMPLE 6
// Get the id and name of the children (filter on related_module_query) of the contact whose id = <ID>

    // $params = array(
    //     'module_name' => 'Contacts',
    //     "module_id" => 'c8ed0b7c-be21-0f7f-0f15-62129cab13a3', 
    //     "link_field_name" => "stic_personal_environment_contacts",
    //     "related_module_query" => "relationship_type = 'son'",
    //     "related_fields" => array(
    //         'id', 
    //         'name'
    //     ),
    //     "related_module_link_name_to_fields_array" => array(),
    //     "deleted" => 0,
    // );


// EXAMPLE 7
// Get the id, name and status of the candidates in "interviewed" status of the contatc whose id = <ID>

    // $params = array(
    //     'module_name' => 'Contacts',
    //     "module_id" => '<ID>',         
    //     "link_field_name" => "stic_job_applications_contacts",
    //     "related_module_query" => "status = 'interviewed'",
    //     "related_fields" => array(
    //         'id', 
    //         'name',
    //         'status',
    //     ),
    //     "related_module_link_name_to_fields_array" => array(),
    //     "deleted" => 0,
    // );


// Execute the call to the corresponding API client function
$apiClient->getRelationships($params);

