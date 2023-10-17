<pre>
<?php

class APIClient
{
    public $appName = "SinergiaCRM API Examples v4.1";
    public $sessionId;
    public $url;
    public $verbose;

    /**
     * Configure the API client.
     * @param String $url SinergiaCRM URL
     * @param Integer $verbose Indicates whether or not we want to show information about the requests on the screen
     */
    public function __construct($url, $verbose = 0)
    {
        $this->url = $url;
        $this->verbose = $verbose;
    }

    /**
     * Generic function to make requests via HTTP using cURL.
     * @param String $method API method that we are going to call.
     * @param Array $parameters Parameters that the API method we call will receive. 
     * @return Object Server response depending on the method we have called
     */
    public function call($method, $parameters)
    {
        $post = array(
            "method" => $method,
            "input_type" => "JSON",
            "response_type" => "JSON",
            "rest_data" => json_encode($parameters),
        );

        if ($this->verbose) {
            echo "<br />==================================<br /><br />";
            echo "URL: <span style='color:blue'>$this->url</span><br />";            
            echo "API método: <span style='color:blue'>" . strtoupper($method) . "</span><br />";
            echo "ARGUMENTOS de la llamada: <br />";
            echo "<span style='color:blue'>";
            var_dump($post);
            echo "</span>";
        }

        $curl_request = curl_init();
        curl_setopt($curl_request, CURLOPT_URL, $this->url);
        curl_setopt($curl_request, CURLOPT_POST, 1);
        curl_setopt($curl_request, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($curl_request, CURLOPT_HEADER, 1);
        curl_setopt($curl_request, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl_request, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl_request, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($curl_request, CURLOPT_POSTFIELDS, $post);
        $result = curl_exec($curl_request);
        curl_close($curl_request);

        $result = explode("\r\n\r\n", $result, 2);
        // print_r($result);        
        $response = json_decode($result[1]);

        echo "<br /><br />RESULTADO ";
        return $response;
    }


    ////////////////////    SESSION METHODS  /////////////////////////

    /**
     * Configures the call to the login method of the API.
     * @param String $username Name of a CRM user
     * @param String $pass User password
     * @param String $language Language in which we want to interact with the CRM API
     * @param String $notifyonsave Indicates whether to send an email or not when saving and assigning a new record to a user
     * @return String The identifier of the session that was just started
     */
    public function login($username, $pass, $language, $notifyonsave = false)
    {
        $params = array(
            'user_auth' => array(
                'user_name' => $username,
                'password' => md5($pass),
            ),
            'application_name' => $this->appName,
            "name_value_list" => array(
                array(
                    "name" => "notifyonsave",
                    "value" => $notifyonsave,
                ),
                array(
                    "name" => "language",
                    "value" => $language,
                ),
            ),
        );

        $result = $this->call('login', $params);

        if ($result && $result->id) {
            if ($this->verbose) {
                echo "<span style='color:green'>";
                // print_r($result);
                echo "<br /><br />SESSION_ID = " . $result->id;
                echo "</span><br /><br />";
            }
            // Configurar el ID de la sesión en el cliente API
            return $result->id;
        }
    }

    /**
     * Configures the call to the API logout method.
     */
    function logout()
    {
        $parameters = array(
            'session' => $this->sessionId,
        );

        $result = $this->call("logout", $parameters, $this->url);

        if ($result){
            $this->sessionId = '';
        }

        if ($this->verbose) {
            echo "<br /><br />";
            echo "<span style='color:green'>";
            echo "Sesión cerrada: $this->sessionId";
            echo "</span>";
        }
    }


    ////////////////////   GETTERS  /////////////////////////

    /**
     * Configures the call to the API get_available_modules method.
     * @return Object Returns a list of the modules available for use. Also returns the ACL (Access Control List) for each module.
     */
    function getAvailableModules($params)
    {
        $params = array_merge(array('session' => $this->sessionId), $params);
        $result = $this->call("get_available_modules", $params, $this->url);
        if ($this->verbose) {
            echo "<br /><br />LISTADO DE MÓDULO: <br /><br />";
            echo "<span style='color:green'>";
            print_r($result);
            echo "</span>";
            echo "<br />";
        }
        return $result;
    }

    /**
     * Configures the call to the API get_module_fields method. 
     * @return Object Returns the field definitions for a given module.
     */
    function getModuleFields($params)
    {
        $params = array_merge(array('session' => $this->sessionId), $params);
        $result = $this->call("get_module_fields", $params, $this->url);
        if ($this->verbose) {
            echo "- CAMPOS DEL MÓDULO: {$params['module_name']} <br /><br />";
            echo "<span style='color:green'";
            print_r($result);
            echo "</span>";
        }
        return $result;
    }

    /**
     * Configures the call to the API get_language_definition method. 
     * @return Object Returns labels at the application or module level in the corresponding language.
     */
    public function getLanguageDefinition($params)
    {
        $params = array_merge(array('session' => $this->sessionId), $params);
        $result = $this->call("get_language_definition", $params, $this->url);
        if ($this->verbose) {
            if ($params['modules'] == 'app_list_strings') {
                echo "<br /><br />LISTAS DESPLEGABLES: <br /><br />";
            } else {
                echo "<br /><br />MÓDULO: {$params['modules']} <br /><br />";
            }
            echo "<span style='color:green'";
            print_r($result);
            echo "</span>";
        }
        return $result;
    }


    /**
     * Configures the call to the API get_entry_list method. 
     * @return Object Returns the records that meet the indicated parameters.
     */
    function getEntryList($params)
    {
        $params = array_merge(array('session' => $this->sessionId), $params);
        $result = $this->call("get_entry_list", $params, $this->url);
        if ($this->verbose) {
            echo "- REGISTROS DEL MÓDULO: {$params['module_name']} <br /><br />";
            echo "<span style='color:green'";
            print_r($result);
            echo "</span>";
        }
        return $result;
    }

    /**
     * Configures the call to the API get_entry method.  
     * @return Object Returns the record that meets the indicated parameters.
     */
    public function getEntry($params)
    {
        $params = array_merge(array('session' => $this->sessionId), $params);
        $result = $this->call("get_entry", $params, $this->url);
        if ($this->verbose) {
            echo "- INFORMACIÓN DEL REGISTRO<br /><br />";
            echo "<span style='color:green'";
            print_r($result);
            echo "</span>";
        }
        return $result;
    }

    /**
     * Configures the call to the API get_relationships method. 
     * @return Object Returns relationship records that meet the given parameters.
     */
    public function getRelationships($params)
    {
        $params = array_merge(array('session' => $this->sessionId), $params);
        $result = $this->call("get_relationships", $params, $this->url);
        if ($this->verbose) {
            echo "- REGISTROS RELACIONADOS CON EL ID {$params['module_id']} DEL MÓDULO {$params['module_name']} <br /><br />";
            echo "<span style='color:green'";
            print_r($result);
            echo "</span>";
        }
        return $result;
    }

    /**
     * Configures the call to the API get_document_revision method. 
     * @return Object Returns the details of a specific document revision.
     */
    public function getDocumentRevision($params)
    {
        $params = array_merge(array('session' => $this->sessionId), $params);
        $result = $this->call("get_document_revision", $params, $this->url);
        if ($this->verbose) {
            echo "- INFORMACIÓN DE LA REVISIÓN DEL DOCUMENTO {$params['i']}<br /><br />";
            echo "<span style='color:green'";
            print_r($result);
            echo "</span>";
        }
        return $result;
    }

    /**
     * Configures the call to the API get_image method. 
     * @return Object Returns the photo of the contact indicated in the parameters.
     */
    public function getImage($params)
    {
        $params = array_merge(array('session' => $this->sessionId), $params);
        $result = $this->call("get_image", $params, $this->url);
        if ($this->verbose) {
            echo " - INFORMACIÓN DE IMAGEN DEL REGISTRO {$params['id']}<br /><br />";
            echo "<span style='color:green'";
            print_r($result);
            echo "</span>";
        }
        return $result;
    }

    ////////////////////   SETTERS  /////////////////////////

    /**
     * Configures the call to the API set_entry method.  
     * Creates or updates a single record
     */
    public function setEntry($module, $data)
    {
        $i = 0;
        $nameValueListArray = array();
        foreach ($data as $field => $value) {
            $nameValueListArray[$i]['name'] = $field;
            $nameValueListArray[$i]['value'] = $value;
            $i++;
        }

        $params = array(
            "session" => $this->sessionId,
            "module_name" => $module,
            "name_value_list" => $nameValueListArray,
        );
        $result = $this->call("set_entry", $params);
        echo " - SE HA CONFIGURADO EL SIGUIENTE REGISTRO <br /><br />";
        echo "<span style='color:green'";
        print_r($result);
        echo "</span>";
    }


    /**
     * Configures the call to the API set_relationship method. 
     * Sets a relationship between a record and other records.  
     */
    public function setRelationship($params)
    {
        $params = array_merge(array('session' => $this->sessionId), $params);
        $result = $this->call("set_relationship", $params);

        echo "<span style='color:green'";
        print_r($result);
        echo "</span>";
    }

    /**
     * Configures the call to the API set_image method. 
     * Sets a image in one field of the record 
     */
    public function setImage($params)
    {
        $params = array_merge(array('session' => $this->sessionId), $params);
        $result = $this->call("set_image", $params);

        echo "<span style='color:green'";
        print_r($result);
        echo "</span><br /><br />";
    }
}
?>
<pre>