
// SinergiaCRM URL
var api_url = "<SINERGIACRM_URL>/custom/service/v4_1_SticCustom/rest.php";

// CRM User
var user_name = '<CRM_USER>';

// User password
var password = '<CRM_USER_PASSWORD>';


// Login Params
var params = {
    user_auth:{
        user_name:user_name,
        password:password,
        encryption:'PLAIN'
    },
    application: 'SinergiaCRM RestAPI Example'
};
var jsonData = JSON.stringify(params);


// AJAX Request 
$.ajax(
    {
        url: api_url,
        type: "POST",
        dataType: "json",
        data: { 
            method: "login", 
            input_type: "JSON", 
            response_type: "JSON", 
            rest_data: jsonData 
        },

        success: function(result) {
                if(result.id) {
                    alert("sucessfully LOGIN Your session ID is : " + result.id);
                }
                else{
                    alert("Error");
                }
        },

        error: function(result) {
            alert("Error");
        }
    }
);