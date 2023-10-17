<?php

// Link to SuiteCRM documentation
// https://docs.suitecrm.com/developer/api/api-v4.1-methods/#_get_entry


// LIST OF EXAMPLES
// It is recommended to run the examples one at a time. To do this we can comment on the active example and uncomment another one.


// EXAMPLE 1
// Get the first name, last name, email, age, document type and document identifier of the contact whose id = <ID>

    // $params = array(
    //     'module_name' => 'Contacts',
    //     'id' => '<ID>',
    //     'select_fields' => array(
    //         'name',
    //         'last_name',
    //         'email1', 
    //         'stic_age_c', 
    //         'stic_identification_type_c', 
    //         'stic_identification_number_c'
    //     ),
    //     'deleted' => 0,
    // );


// EXAMPLE 2
// Get the name, type, status, description and location name of the event whose id = <ID>

    // $params = array(
    //     'module_name' => 'stic_Events',
    //     'id' => '<ID>',
    //     'select_fields' => array(
    //         'name',
    //         'type',
    //         'status', 
    //         'description', 
    //         'stic_events_fp_event_locations_name'
    //     ),
    //     'deleted' => 0,
    // );


// EXAMPLE 3
// Get the name of the registration whose id = <ID>, and also the id and name of the related event.
    // $params = array( 
    //     'module_name' => 'stic_Registrations', 
    //     'id' => '<ID>', 
    //     'select_fields' => array(
    //          'name'
    //     ),
    //     'link_name_to_fields_array' => array(
    //         array(
    //             'name' => 'stic_registrations_stic_events',
    //             'value' => array(
    //                 'id',
    //                 'name',
    //             )
    //         )
    //     ),    
    //     'deleted' => 0,
    // );


// EXAMPLE 4
// Get the id, name and last name of the contact related to the registration whose id = <ID>

    // $params = array( 
    //     'module_name' => 'stic_Registrations', 
    //     'id' => '<ID>', 
    //     'select_fields' => array(),
    //     'link_name_to_fields_array' => array(
    //         array(
    //             'name' => 'stic_registrations_contacts',
    //             'value' => array(
    //                 'id',
    //                 'first_name',
    //                 'last_name',
    //             )
    //         )
    //     ),    
    //     'deleted' => 0,
    // );


// EXAMPLE 5
// Get the name, status and color of the resource whose id is <ID>, and also the name and start and end date of the related reservation.

    // $params = array(
    //     'module_name' => 'stic_Resources',
    //     'id' => '<ID>',
    //     'select_fields' => array(
    //         'name',
    //         'status',
    //         'color',
    //     ),
    //     'link_name_to_fields_array' => array(
    //         array(
    //             'name' => 'stic_resources_stic_bookings',
    //             'value' => array(
    //                 'name',
    //                 'start_date',
    //                 'end_date', 
    //             )
    //         )
    //     ), 
    //     'max_results' => 10,
    //     'deleted' => 0,
    // );


// EXAMPLE 6 (Related field)
// Get the name and interlocutor of the job offer whose id = <ID>
// To get the ID of the interlocutor (related field) we have to indicate the name of the linked field (id_name)

    // $params = array(
    //     'module_name' => 'stic_Job_Offers',
    //     'id' => '<ID>',
    //     'select_fields' => array(
    //         'name',
    //         'interlocutor', // Devuelve el valor del campo name.
    //         'contact_id_c', // Campo vinculado al campo relacionado interlocutor (Devuelve el ID del interlocutor)
    //     ),
    //     'deleted' => 0,
    // );


// Execute the call to the corresponding API client function
$apiClient->getEntry($params);