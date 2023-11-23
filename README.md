# SinergiaCRM-API-Examples
Repositorio de código que ofrece un conjunto de ejemplos para los diferentes métodos ofrecidos por la API de SinergiaCRM, con las siguientes características: 

- Protocolo REST
- Versión 4.1 de la API
- Programado en PHP

Este repositorio facilita un primer contacto con los métodos ofrecidos por la API y permite realizar pruebas de forma ágil. Los pasos a seguir para poder disponer de este conjuntos de ejempolos son: 

1. Descargar el código del repositorio de Github.
2. Instalarlo en un ordenador local o en un servidor de la entidad donde exista un entorno de ejecución PHP. 
3. Configurar las credenciales de acceso a la instancia del CRM a la que queramos conectarnos y otras configuraciones.
4. Probar los diferentes ejemplos o implementar ejemplos personalizados para nuevos casos de uso. 


## Descargar el conjunto de ejemplos API
En la URL raíz de este repositorio: https://github.com/SinergiaTIC/SinergiaCRM-API-Examples: 

1. Pulsar en el botón verde llamado **<> Code**
2. Pulsar en el enlace **Download ZIP**


## Estructura del código
Una vez descargado el repositorio nos encontraremos con la siguiente estructura de carpetas y ficheros: 

### app.php 
Fichero de entrada donde:

- Configuraremos la URL de conexión, las credenciales de acceso (usuario y contraseña), el idioma y otras características.
- Se instancia un cliente API. 
- Se activan y desactivan las diferentes llamadas a los métodos de la API. Por defecto vienen activadas las llamadas a los métodos de Login y Logout.

### ApiClient.php 
Fichero que define una clase donde está programada la lógica de conexión con el CRM y los métodos para realizar peticiones y recibir respuestas. 

### Ejemplos
Carpeta que contiene otras 3 carpetas para categorizar los diferentes ejemplos:
	
- GET → Ejemplos de métodos que piden datos al CRM
- SET → Ejemplos de métodos que piden al CRM que almacene datos que envían
- assets → Carpeta de recursos donde dejar imágenes que queramos almacenar en el CRM: Imágenes, documentos en diferentes formatos, etc. 


## Preparar el entorno de ejecución
El conjunto de ejemplos está programado en el lenguaje de programación PHP y, en consecuencia, vamos a necesitar un entorno de ejecución que disponga de un servidor web con las correspondientes librerías de PHP. 

Este entorno podemos habilitarlo:
- En nuestro ordenador local a través de diferentes formas. Desde SinergiaCRM recomendamos la opción de instalar XAMPP ya que es un proyecto de software libre con un entorno amigable. 
- En un servidor con infraestructura para poder ejecutar aplicaciones PHP.


## Configuración
Una vez desplegado en un entorno de ejecución PHP podemos empezar a configurar las variables que nos encontramos al comienzo del fichero **app.php** 

### URL
Podremos conectarnos tanto a la instancia de PRO como a la de TEST. Además, ya que en SinergiaCRM estamos añadiendo métodos API personalizados, la URL se diferencia de la que encontraremos en la documentación oficial de SuiteCRM. 

    $url = '<INSTANCIA_URL>/TEST/custom/service/v4_1_SticCustom/rest.php';

### Credenciales
Tendremos que indicar un usuario activo en el CRM y su contraseña

    $username = '<USUARIO_CRM>';
    $password = '<CONTRASEÑA_USUARIO_CRM>';

### Idioma 
Indicaremos el idioma en el que queremos interactuar con la API del CRM. Por defecto está en español pero podemos indicar cualquiera de los idiomas que tengamos operativos en el CRM: Español (es_ES), Catalán (ca_ES), Gallego (gl_ES) e Inglés(en_us)

    $language = 'es_ES'; 

### Notificar al salvar
Indica si se enviará un correo, o no, cuando se guarde y asigne un nuevo registro a un usuario. Por defecto está desactivado.  

    $notifyOnSave = false;

### Verbose

Indicaremos si queremos mostrar información en pantalla, o no, de las llamadas a los métodos que realicemos: URL de conexión, método API llamado, parámetros del método y respuesta recibida. Por defecto está activado. 

    $verbose = true;


## Realizar peticiones 
Una vez realizada la configuración podremos lanzar nuestra primera petición API llamando a la URL de nuestro ordenador local (localhost o 127.0.0.1) o de nuestro servidor indicando la carpeta donde lo hayamos instalado y el fichero **app.php**. Por ejemplo, si fuese en una instalación local escribimos la siguiente URL en un navegador:  

    http://localhost/<carpeta_de_instalación>/app.php

Por defecto sólo están activadas las llamadas a los métodos de **login** y **logout**. Además, como la variable **$verbose** está activada, deberíamos ver en el navegador:


    URL: <INSTANCIA_URL>/TEST/custom/service/v4_1_SticCustom/rest.php
    API método: LOGIN
    ARGUMENTOS de la llamada:
    array(4) {
        ["method"]=> string(5) "login"
        ["input_type"]=> string(4) "JSON"
        ["response_type"]=> string(4) "JSON"
        ["rest_data"]=> string(217) "{
            "user_auth":{
                "user_name":"<USUARIO_CRM>",
                "password":"<CONTRASEÑA_USUARIO_CRM>"
            },
            "application_name":"API Examples v4.1",
            "name_value_list":[
                {"name":"notifyonsave","value":null},
                {"name":"language","value":"es_ES"}
            ]
        }"
    }

    RESULTADO

    ID de sesión = hj8ikeanb57o6r9qj1ucp2b86g


    ==================================


    URL: <INSTANCIA_URL>/TEST/custom/service/v4_1_SticCustom/rest.php
    API método: LOGOUT
    ARGUMENTOS de la llamada:
    array(4) {
        ["method"]=> string(6) "logout"
        ["input_type"]=> string(4) "JSON"
        ["response_type"]=> string(4) "JSON"
        ["rest_data"]=> string(40) "{"session":"hj8ikeanb57o6r9qj1ucp2b86g"}"
    }

    RESULTADO

    Sesión cerrada: hj8ikeanb57o6r9qj1ucp2b86g


Si queremos realizar una llamada a otro método de la API de SinergiaCRM tendremos que realizar dicha llamada entre el LOGIN y el LOGOUT. 


### Activar / Desactivar peticiones de ejemplo
Como vimos en el apartado de la estructura, disponemos de un conjunto de ejemplos que podemos activar y desactivar atendiendo a la siguiente metodología: 


Accedemos al fichero de ejemplo del método que queramos probar, por ejemplo, *Ejemplos/GET/get_entry_list.php* y activamos unos de los ejemplos descomentando el apartado de código, por ejemplo, el *EJEMPLO 1*:

    // EJEMPLO 1
    // Obtener el nombre, apellidos, email, edad, tipo de documento y identificador de documento
    // de los 10 primeros registros no eliminados del módulo de Personas.

    $params = array(
    'module_name' => 'Contacts',
    'query' => "",
    'order_by' => '',
    'offset' => 0,
    'select_fields' => array(
        'name',
        'last_name',
        'email1',
        'stic_age_c',
        'stic_identification_type_c',
        'stic_identification_number_c'
    ),
    'link_name_to_fields_array' => [],
    'max_results' => 10,
    'deleted' => 0,
    );


En el fichero **app.php** activamos la llamada a ese fichero descomentando la línea:


    // LISTADO DE EJEMPLOS
    // Se recomienda ejecutar lo ejemplos de uno en uno. Para ello podemos comentar el ejemplo activo y descomentar otro.

    // GETTERS
    // include_once 'Ejemplos/GET/get_available_modules.php';
    // include_once 'Ejemplos/GET/get_document_revision.php';
    include_once 'Ejemplos/GET/get_entry_list.php';
    // include_once 'Ejemplos/GET/get_entry.php';
    // include_once 'Ejemplos/GET/get_image.php';
    // include_once 'Ejemplos/GET/get_language_definition.php';
    // include_once 'Ejemplos/GET/get_module_fields.php';
    // include_once 'Ejemplos/GET/get_relationships.php';

    // SETTERS
    // include_once 'Ejemplos/SET/set_entry.php';
    // include_once 'Ejemplos/SET/set_document_revision.php';
    // include_once 'Ejemplos/SET/set_relationship.php';
    // include_once 'Ejemplos/SET/set_image.php';


Volvemos a acceder a la URL desde un navegador:

    http://localhost/<carpeta_de_instalación>/app.php


### Recibir y renderizar la respuesta

Ya hemos visto que, a través de activar los diferentes ejemplos y la variable **$verbose**, disponemos de una manera para poder visualizar en pantalla los parámetros de la llamada y el resultado devuelto por los diferentes métodos de la API. 

Además, en los ejemplos que tienen que ver con el tratamiento de ficheros: 

- Ejemplos/GET/get_document_revision.php
- Ejemplos/GET/get_image.php

podemos activar un tratamiento más avanzado de cómo gestionar la respuesta JSON del CRM para acceder a la fotografía o el archivo relacionado (**EJEMPLO 2**)

## Métodos API personalizados por SinergiaCRM

Desde SinergiaCRM estamos añadiendo diferentes métodos personalizados, listados a continuación, a la API del CRM. 

Antes de ver los métodos y tal y como comentamos en el apartado de configuración, esto supone tener que indicar la siguiente URL en vez de la que está documentada por SuiteCRM:

    $url = '<INSTANCIA_URL>/TEST/custom/service/v4_1_SticCustom/rest.php';


### get_image

- Obtener la imágen asociada al campo **FIELD** del registro cuyo id = **ID**
- Carpeta de ejemplos: **Ejemplos/GET/get_image.php**

### set_image

- Establece la imágen *test_image.jpg* (existente en la carpeta *assets*) en el campo **FIELD** del registro cuyo id = **ID** del módulo **MODULE**
- Carpeta de ejemplos: **Ejemplos/SET/set_image.php**


## Documentación técnica

### Parámetros a indicar en las peticiones API

Aunque vayamos a configurar un valor vacío en alguno de los parámetros a indicar en la llamada a los diferentes métodos de la API, deberemos configurar todos los parámetros que se indican en la documentación. 

En caso de no indicar alguno de los parámetros puede provocar qué, o bien no funcione la llamada, o bien no devuelva el resultado correcto. Dependerá de si el parámetro es necesario para poder realizar la consulta y de la posición que ocupe en el array que contiene los valores de los parámetros.  





### Indicar usuario creador al crear un registro

Por defecto, al crear un registro a través del método **set_entry**, el usuario creador será el usuario con el que nos estamos conectando a la API.

Si queremos que el usuario creador sea otro tendremos que: 
- Indicar el `id` del usuario en el campo `created_by` 
- Configurar `set_created_by` a `false` en la llamada al método **set_entry**

Podemos acceder a un ejemplo del uso de una subquery en el fichero **Ejemplos/GET/set_entry.php**, en el **EJEMPLO 3**.





### Campos de tipo String y valor por defecto

Al guardar un registro desde la interfaz de usuario, cuando no se indica un valor en los campos de tipo cadena, estos quedan con valor de cadena vacía (''). Sin embargo, al guardar un registro a través de la API, si no se indica un valor, estos campos quedan con el valor NULL. 

Se soluciona indicando el valor de cadena vacía: 

    $contactData['stic_language_c'] = '';





### Formato para campos de tipo fecha o fecha y hora

El valor indicado deberá cumplir con los siguientes criterios:

- En formato de base de datos (Y-m-d H:i:s)
- En UTC (Tiempo universal coordinado)





### Campos de tipo relacionados

Un campo de tipo Relacionado es un campo que está vinculado con otro campo de texto (oculto en la interfaz) donde se almacena el ID del registro relacionado. 

Podemos ver el nombre del campo vinculado a un campo relacionado a través del inspector de código del navegador que estemos utilizando. Para ello iremos a la vista de edición o de detalle de un registro del módulo al que pertenece el campo relacionado y, tras abrir el inspector y pulsar en el seleccionador de elementos (Ctrl + Shift + c), seleccionamos el input del campo relacionado. Por ejemplo, veremos que el campo **Interlocutor** del módulo de Ofertas laborales está vinculado con el **campo contact_id_c**.  

Relacionando esta información con la API, cuando queramos obtener o almacenar el ID de un registro en un campo de tipo Relacionado, en vez de operar con el campo relacionado tenemos que hacerlo con el campo vinculado. Podemos acceder a un ejemplo en **Ejemplos/GET/get_entry.php**, en el **EJEMPLO 6**, y otro en **Ejemplos/SET/set_entry.php**, en el **EJEMPLO 5**. 





### Relaciones

En el elemento **link_field_names** del método **set_relationship** tenemos que indicar el nombre de la relación que hay entre los módulos de los registros que queremos relacionar. El nombre de la relación lo podemos obtener de dos maneras:

1. Accediendo a la vista de edición del registro del módulo base, inspeccionando desde el navegador el input HTML donde indicar el registro relacionado, copiando el valor de la propiedad ID del input HTML y eliminando el sufijo `_name`.

2. Accediendo a Admin / Estudio / Módulo base / Relaciones y buscando la relación con el módulo del registro a relacionar.


En el caso de que exista más de una relación entre ambos módulos y haya que averiguar con que relación de las existente queremos operar, usaremos la primera opción. 




### Relaciones especiales

**accounts_contacts**. La relación **accounts_contacts** es una relación que tiene un tratamiento especial ya que el nombre a indicar en las conexiones vía API no se corresponde con el nombre de la relación, sino que existen dos maneras de hacerlo dependiendo del módulo que tomemos como módulo base:

- Si tomamos el módulo de Personas la relación tiene el nombre de **accounts**
- Si tomamos el de Organizaciones tiene el nombre de **contacts** 

Podemos acceder a un ejemplo en el fichero **Ejemplos/SET/set_relationship.php**, en el **EJEMPLO 4**.

Por último, en el momento de crear la persona se puede indicar el campo **account_id** y se relacionará correctamente con la organización, ahorrando así una llamada a la API.





### Nombre de tablas en el parámetro QUERY

En el parámetro **query** hay que indicar el nombre de la tabla de base de datos a la que pertenece el campo que va a ser utilizado. Hay algunas excepciones con algunos campos pero lo recomendable es indicarlo para evitar el posible error.

Para saber el nombre de la tabla a la que pertenece un campo hay que ver si los campos que vayamos a utilizar como filtros:
- Son campos que estaban definidos desde el principio en el módulo (campos básicos del módulo y que son almacenados en la **tabla principal**) 

- O son campos personalizados añadidos posteriormente (campos que suelen llevar el sufijo `_c` en el nombre y que son almacenados en la **tabla custom** del módulo, cuyo nombre lleva el sufijo `_cstm`).


Para saber si es un campo básico o custom podemos:

- Inspeccionar el nombre del campo desde una de las vistas (edición, detalle..) y ver si tiene el sufijo `_c` en el nombre o identificador del elemento HTML. 

- Acceder a la apartado de **Campos** del módulo desde **Estudio** y ver si tiene el sufijo `_c`. En el listado de campos también podemos diferenciar los campos personalizados por que tienen un `*` al comienzo.


Por último, el nombre de la tabla suele ser el nombre del módulo con todas las letras en minúscula, es decir:

- Contacts --> contacts
- stic_Contacts_Relationships --> stic_contacts_relationships

Y como ya hemos comentado, añadiendo el sufijo `_cstm` para obtener el nombre de la tabla personalizada correspondiente:

- Contacts --> contacts_cstm
- stic_Contacts_Relationships --> stic_contacts_relationships_cstm





### API Subqueries

Algunos métodos de la API permiten incluir un parámetro **query** que permite filtrar los resultados obtenidos. En este parámetro **query** se puede incluir una subquery, aunque esta sólo funcionará sobre las tablas indicadas en el fichero **include/SugarSQLValidate.php**:

    protected $subquery_allowed_tables = array(
        'email_addr_bean_rel' => true,
        'email_addresses' => true,
        'emails' => true,
        'emails_beans' => true,
        'emails_text' => true,
        'teams' => true,
        'team_sets_teams' => true,
    );

Podemos acceder a un ejemplo del uso de una subquery en el fichero **Ejemplos/GET/get_entry_list.php**, en el **EJEMPLO 3**.
