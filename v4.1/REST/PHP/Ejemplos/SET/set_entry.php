<?php

// Link to SuiteCRM documentation
// https://docs.suitecrm.com/developer/api/api-v4.1-methods/#_set_entry


// LIST OF EXAMPLES
// It is recommended to run the examples one at a time. To do this we can comment on the active example and uncomment another one.

// In addition, SET type calls modify the database so it is recommended:
// - Use the TEST environment to perform tests.
// - Be careful with what we do
// - Make sure all required fields are being entered


// EXAMPLE 1
// Create a contact indicating the first and last name

    // $module = 'Contacts';
    // $data = array();
    // $data['first_name'] = 'Test';
    // $data['last_name'] = 'API';


// EXAMPLE 2
// Update email with value <EMAIL> in contact with id = <ID>

    // $module = 'Contacts';
    // $data = array();
    // $data['id'] = '<ID>';
    // $data['email1'] = '<EMAIL>';


// EXAMPLE 3
// Create a contact indicating a creator user with id = <USER_ID> different from the user who connects to the API.
// It will be necessary:
// - Indicate the `id` of the user in the `created_by` field
// - Set `set_created_by` to `false`

    // $module = 'Contacts';
    // $data = array();
    // $data['first_name'] = 'Test';
    // $data['last_name'] = 'API';
    // $data['created_by'] = '<USER_ID>';
    // $data['set_created_by'] = false;


// EXAMPLE 4
// Create a registration and relate it to an event whose id = <EVENT_ID> and a contact whose id = <CONTACT_ID>

    // $module = 'stic_Registrations';
    // $data = array();        
    // $data['registration_date'] = '2023-10-02 22:00:00';  
    // $data['status'] = 'confirmed';
    // $data['participation_type'] = 'attended';
    // $data['attendees'] = 1;
    // $data['stic_registrations_contactscontacts_ida'] = '<CONTACT_ID>';
    // $data['stic_registrations_stic_eventsstic_events_ida'] = '<EVENT_ID>';
    // $data['assigned_user_id'] = 2;


// EXAMPLE 5 (Campo de tipo relacionado)
// Create a job offer with an interlocutor
// To store a value in a related field we have to indicate the value in the linked field (id_name).

    // $module = 'stic_Job_Offers';
    // $data = array();
    // $data['name'] = 'Oferta 1';
    // $data['description'] = 'Oferta 1';
    // // $data['interlocutor'] = '3361d7f3-b3f4-a638-d62b-630e3d9c33ec'; // Campo de tipo Relacionado (Lo podríamos omitir de este array)
    // $data['contact_id_c'] = '3361d7f3-b3f4-a638-d62b-630e3d9c33ec'; // Campo vinculado



// EXAMPLE 6    
// Create a reservation
// Dates must be configured in UTC (Coordinated Universal Time) and in database format (Y-m-d H:i:s)

    // $module = 'stic_Bookings';
    // $data = array();   
    
    // a) All-day reservation made by a user in the GMT+0 zone
    
        // $data['start_date'] = '2022-10-24 00:00:00'; 
        // $data['end_date'] = '2022-10-25 00:00:00';    
        // $data['assigned_user_id'] = 2;
        // $data['status'] = 'pending';
        // $data['all_day'] = 1;

    // b) All-day reservation made by a user in the GMT+2 zone
    
        // $data['start_date'] = '2022-10-23 22:00:00';  
        // $data['end_date'] = '2022-10-24 22:00:00';    
        // $data['assigned_user_id'] = 2;
        // $data['status'] = 'pending';
        // $data['all_day'] = 1;


    // c) Hourly reservation for October 11, 2023 from 11 a.m. to 12 p.m. by a user in the GMT+0 zone
    
        // $data['start_date'] = '2023-19-11 11:00:00';  
        // $data['end_date'] = '2023-19-11 12:00:00';    
        // $data['assigned_user_id'] = 2;
        // $data['status'] = 'pending';
        // $data['all_day'] = 0;


    // d) Hourly reservation for October 11, 2023 from 11 a.m. to 12 p.m. by a user in the GMT+2 zone
        // $data['start_date'] = '2023-19-11 09:00:00';  
        // $data['end_date'] = '2023-19-11 10:00:00';    
        // $data['assigned_user_id'] = 2;
        // $data['status'] = 'pending';
        // $data['all_day'] = 0;


// EXAMPLE 7
// Update the password of the private area with the value <PASSWORD> in the contact whose id = <ID>

    // $module = 'Contacts';
    // $data = array();
    // $data['id'] = '<ID>';
    // $data['stic_pa_password_c'] = '<PASSWORD>';



// Execute the call to the corresponding API client function
$apiClient->setEntry($module, $data);