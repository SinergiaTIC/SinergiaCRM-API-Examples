# SinergiaCRM-API-Clients
Repositorio con diferentes clientes API para SinergiaCRM

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






## SinergiaCRM API v8

### Postman
Leer v8/Postman/README.md


