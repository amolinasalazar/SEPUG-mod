=== MANUAL DE INSTALACIÓN Y CONFIGURACION DEL MÓDULO SEPUG: SISTEMA DE EVALUACIÓN DEL PROFESORADO DE LA UNIVERSIDAD DE GRANADA v1.0 ===

Advertencia: este plugin ha sido desarrollado para Moodle 2.5.5+, tanto la instalación como el funcionamiento de dicho plugin
no está garantizado para una versión de Moodle que no sea la misma.

== PRECONFIGURACIÓN (OPCIONAL) ==

Antes de proceder a la instalación, podemos cambiar algunos parámetros del módulo:

 - $SEPUG_GHEIGTH y $SEPUG_GWIDTH indican el tamaño del grafico de resultados en pixeles.
 
 - $DIM_PLANIF, $DIM_COMP_DOC, $DIM_EV_APREND, $DIM_AMB indican los ID's de las preguntas que forman una dimensión o área, las cuales se procesan y muestran en el informe de resultados.
 
 - $FILTRO_CURSOS, $FILTRO activa/desactiva el filtro de cursos a procesar por el módulo que se realiza mediante una consulta SQL.
 
Este paso es opcional, se recomienda no realizar cambios si no es completamente necesario.

== INSTALACIÓN ==

Para instalar el módulo SEPUG, hay que seguir los mismos pasos que se desarrollarían para la instalación manual de cualquier plugin en Moodle. Podemos encontrar una guía online genérica en https://docs.moodle.org/all/es/Instalar_plugins donde se detalla el proceso y posibles errores que pueden surgir durante la instalación. Aun así, en este manual se detalla el proceso completo para facilitar la tarea al usuario:

1º Copia manual del módulo:

Debe copiarse la carpeta "sepug", la cual contiene el código fuente del módulo, en el directorio "mod" que se encuentra en /ruta/a/moodle/mod/.

2º Activar módulo en Moodle:

Como administrador, acceder a Configuraciones > Administración del sitio > Notificaciones y actualizar la base de datos de Moodle para completar la instalación. Normalmente, la vista de "Notificaciones" deberá aparecer automáticamente cuando Moodle detecte que la carpeta "sepug" ha sido añadida.

3º Comprobar permisos:

Para asegurarse de que todo estudiante puede acceder a los cuestionarios o que todos los profesores pueden visualizar los resultados, es recomendable comprobar de que los usuarios dispongan de los permisos suficientes y correctos.

== CONFIGURACION ==

Una vez correctamente instalado, debemos instanciar una nueva actividad SEPUG. Este procedimiento debe hacerlo un administrador o usuario que disponga del permiso "mod/sepug:addinstance". El curso donde la actividad sea creada no importa, pero para dar acceso a todos los usuarios del sitio Moodle, se recomienda hacerlo en el curso general (id curso = 1) que se encuentra por defecto en la página principal de la web.

Cuando la actividad esté creada, deberemos acceder a la configuración de la actividad (Administración > Ajustes de la página > Activar edición; y luego click en el icono Actualizar). Se mostrarán una serie de parámetros que podemos modificar según necesidad:

 - [General] Nombre y descripción de la actividad. Por defecto, se mostrará una descripción explicativa sobre los cuestionarios.
 
 - [Disponibilidad] Activación y desactivación de los cuestionarios. Se trata de tres fechas obligatorias que marcarán los periodos de apertura de las encuestas a los estudiantes y, el cierre y apertura de los resultados para los profesores.
 
 - [Configuración] Nivel profundidad indica hasta que altura de categorías mostraremos resultados en los informes para el profesorado. Además, habrá que marcar cual es la categoría padre que responde a Grado y cual a Postgrado.
 
Al finalizar nuestra configuración, debemos guardar los cambios para que surjan efecto.
 
== ACTIVAR FUNCIONES WEB SERVICES (Opcional) ==

Este paso será solamente necesario si se desea utilizar la aplicación móvil "OpinaUGR".

Siguiendo los pasos de "Usuarios como clientes con ficha" en "Administración del sitio > Extensiones > Servicios Web > Vista general" o en https://docs.moodle.org/25/en/Using_web_services resulta sencillo activar el uso de Web Services en nuestro sitio Moodle. A continuación se resumen los pasos principales, incluyendo la instalación del plugin:

1º Activación de las Web Services:

En "Administración del sitio > Características avanzadas", habilitamos los servicios web.

2º Activación del protocolo REST

En "Administración del sitio > Extensiones > Servicios Web > Administrar protocolos", habilitamos el protocolo REST.

3º (Si "fbplugin" estaba instalado) Creación de un servicio personalizado:

Si se desea que "OpinaUGR" localice tanto las encuestas de "mod_feedback" como de "mod_SEPUG", debemos de crear manualmente un servicio que incluya las funciones de ambos plugins. Si no, simplemente podremos usar el servicio creado "Service for SEPUG" que ya dispone de las funciones agregadas.

Desde Administración del sitio > Extensiones > Servicios Web > Servicios Externos, podemos agregar un nuevo servicio personalizado. Esta lista de nueve funciones debe ser agregada:

- core_enrol_get_users_courses	

- core_webservice_get_site_info	

- local_fbplugin_get_feedback_questions	

- local_fbplugin_get_feedbacks_by_courses	

- local_fbplugin_complete_feedback	

- mod_sepug_get_sepug_instance	

- mod_sepug_get_not_submitted_enrolled_courses_as_student	

- mod_sepug_get_survey_questions	

- mod_sepug_submit_survey

4º Configuración del "shortname" del servicio:

En esta versión de Moodle, no existe la asignación de "shortnames" por web. La inserción de este dato debe de ser manual, manipulando directamente la base de datos. En futuras versiones, es posible que la asignación sea más sencilla: https://tracker.moodle.org/browse/MDL-29807

Para asignar un shortname al nuevo servicio instalado con el plugin, se debe acceder a la tabla "mdl_external_services"
de la base de datos y asignar manualmente un nombre al servicio "Service for SEPUG" o al que hallamos creado manualmente. La app esta implementada para que funcione con el nombre "opinaws", así que cualquier otro nombre diferente hará que la app no conecte con el plugin instalado.

Nota: modificar la app para cambiar el shortname por defecto es fácil, simplemente hay que modificar la variable global "WS_short_name"
que se encuentra declarada en el archivo "configuration.js" de la aplicación "OpinaUGR".

5º Habilitando permisos:

Debemos activar dos permisos necesarios para que los usuarios puedan usar el nuevo servicio.

- Permite la creación de claves de seguridad por los usuarios: moodle/webservice:createtoken

- Permite el uso del protocolo REST: webservice/rest:use

Podemos hacer este paso de varias maneras, pero se recomienda añadir estos permisos al rol "Usuario Identificado" para que cualquier persona con cuenta en Moodle, pueda acceder sin errores a la aplicación (disponga o no de encuestas que completar). Otras posibilidades son la de añadir estos permisos a otros roles como el de "Estudiante" o crear un rol propio que añada estos permisos y asignarlo a los usuarios.

Recomendaciones:

Se recomienda que se habilite HTTPS con un certificado válido, para evitar problemas de seguridad.

Problemas:

Para cualquier problema, contactar con Alejandro Molina Salazar (amolinasalazar@gmail.com).

Más información:

https://docs.moodle.org/25/en/Using_web_services
https://docs.moodle.org/dev/Creating_a_web_service_client
