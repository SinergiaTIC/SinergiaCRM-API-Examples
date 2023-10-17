<?php

// Link to SuiteCRM documentation
// https://docs.suitecrm.com/developer/api/api-v4.1-methods/#_set_relationship


// LIST OF EXAMPLES
// It is recommended to run the examples one at a time. To do this we can comment on the active example and uncomment another one.

// In addition, SET type calls modify the database so it is recommended:
// - Use the TEST environment to perform tests.
// - Be careful with what we do
// - Make sure all required fields are being entered


// EXAMPLE 1
// Establish a relationship between a contact with id = <CONTACT_ID> and a contact relationship with id =  <COINTACT_RELATIONSHIP_ID>

    // $contact_id = '<CONTACT_ID>';
    // $contact_relation_id = ' <COINTACT_RELATIONSHIP_ID>';
    // $params = array(
    //     'module_name' => 'Contacts',
    //     'module_id' => $contact_id,
    //     'link_field_name' => 'stic_contacts_relationships_contacts',
    //     'related_ids' => [$contact_relation_id],
    //);


// EXAMPLE 2
// Establish a relationship between a reservation with id = <RESERVATION_ID> and a resource with id = <RESOURCE_ID>

    // $booking_id = ' <RESERVATION_ID>';
    // $resource_id = ' <RESOURCE_ID>';
    // $params = array(
    //     'module_name' => 'stic_Bookings',
    //     'module_id' => $booking_id,
    //     'link_field_name' => 'stic_resources_stic_bookings',
    //     'related_ids' => [$resource_id],
    // );


// EXAMPLE 3
// Establish the relationship between an event with id = <EVENT_ID> and two registrations with ids = <REGISTRATION_ID_1> and <REGISTRATION_ID_2>

    // $eventID = '<EVENT_ID>';    
    // $registrationID1 = ' <REGISTRATION_ID_1>';
    // $registrationID2 = ' <REGISTRATION_ID_2>';
            
    // $params = array(
    //     'module_name' => 'stic_Events',
    //     'module_id' => $eventID,
    //     'link_field_name' => 'stic_registrations_stic_events',
    //     'related_ids' => [$registrationID1, $registrationID2],
    // );


// EXAMPLE 4
//SPECIAL CASE - account-contacts - Establish a relationship between a contact and an organization (See Special Relationships section in the README.md file)

    // a) Base module: Contacts

        // $contact_id = '<CONTACT_ID>';
        // $accounts_id = ' <ACCOUNT_ID>';
        // $params = array(
        //     'module_name' => 'Contacts',
        //     'module_id' => $contact_id,
        //     'link_field_name' => 'accounts',
        //     'related_ids' => [$accounts_id],
        // );

    // b) Base module: Accounts

        // $accounts_id = ' <ACCOUNT_ID>';
        // $contact_id = '<CONTACT_ID>';
        // $params = array(
        //     'module_name' => 'Accounts',
        //     'module_id' => $accounts_id,
        //     'link_field_name' => 'contacts',
        //     'related_ids' => [$contact_id],
        // );

        

// Execute the call to the corresponding API client function
$apiClient->setRelationship($params);