=== GUIA DE USO DEL MÓDULO SEPUG: SISTEMA DE EVALUACIÓN DEL PROFESORADO DE LA UNIVERSIDAD DE GRANADA v1.0 ===

Para usar el módulo SEPUG, primero debes haber realizado la instalación y configuración del mismo que se detalla en el documento MANUAL.md.

La actividad SEPUG creada será el punto de acceso para todos los usuarios de la plataforma. El módulo se encarga de asignar un cuestionario de satisfacción a cada asignatura y grupo, cambiando automáticamente la plantilla de la encuesta para asignaturas de Grado o asignaturas de Postgrado. Cada estudiante podrá completar tantas encuestas como cursos este matriculado y grupos internos posea cada uno. Para ello, habrá un periodo de apertura de los cuestionarios que dará vía libre a los estudiantes de completar cada encuesta. Una vez termine ese proceso, comenzará el periodo de cierre para alumnos y de apertura para los profesores que deseen visualizar y descargar sus informes personales de resultados. En este periodo, un administrador o usuario con los permisos adecuados, podrá descargar un fichero con información general sobre todos los resultados recogidos por la base de datos.

Podemos dividir esta guía en esos periodos clave en los que se divide el ciclo de vida del módulo:

== Periodo activo para estudiantes ==

Solo los estudiantes pueden interactuar con SEPUG en este periodo. Accediendo a través de la actividad creada, los estudiantes podrán a través de una lista desplegable, seleccionar algún curso en el cual este matriculado y sea válido. Automáticamente, se redirigirá al cuestionario adecuado donde el usuario deberá completar el cuestionario y enviarlo. Si todo ha ido bien, se mostrará a continuación un mensaje que confirmará que los resultados han sido guardados.

Según se vayan completando encuestas, irán desapareciendo los cursos de la lista desplegable en la pantalla inicial. Además, también se mostrará un mensaje de información sobre el periodo activo que poseen los estudiantes para completar encuestas.

== Periodo activo para profesores ==

Al finalizar el primer periodo, debería de comenzar el periodo de visualización de los informe de resultados. En este periodo, los estudiantes no podrán completar ninguna encuesta más y esta vez serán los profesores los que visualicen una lista desplegable con todos sus cursos. 

Un informe de resultados será mostrado por cada grupo que el curso disponga internamente. Cada informe consiste en dos tablas y un gráfico con información estadística personal y global. La cantidad de información mostrada dependerá del número de categorías del que disponga el sitio Moodle y del nivel de profundidad elegido en las opciones de configuración. Además de visualizar online los resultados, se dispone de la opción de descargar un fichero en formato ODS con toda la información recogida del curso.

Los administradores o usuarios con permiso mod/sepug:global_download que accedan a la actividad en este periodo, podrán encontrar en el menú principal un nuevo botón para descargar un fichero ODS con las estadísticas globales de todos los resultados recogidos por la plataforma.

== Periodo de cierre ==

La actividad puede estar cerrada tanto cuando todavía no haya llegado el primer periodo de apertura, como cuando finalice el segundo. En este periodo, no se podrá interactuar con la actividad, pero podrá reabrirse si se desea desde el menú de configuración. Toda la información también permanece en la base de datos de Moodle, si se quiere liberar toda esa información, simplemente se puede eliminar la actividad de donde este instanciada.


== FAQ ==

Q: He creado una actividad SEPUG en un curso y ahora quiero crear otra para generar dos puntos de entrada al módulo, ¿es posible?

A: No se pueden crear dos actividades SEPUG en una misma plataforma Moodle, si se intenta dará un error. Esto es así para evitar confusiones a los usuarios y a los gestores del mismo módulo (por ejemplo, no duplicar configuraciones). Por ello, se recomienda asignar la actividad siempre en el curso general que se encuentra en la página principal de Moodle, para que puedan acceder todos los usuarios a través del mismo sitio.

Q: ¿Las respuestas son anónimas? ¿En algún momento se vulnera la privacidad de los usuarios?

A: Todos los resultados, informes y gráficos que SEPUG genera son totalmente anónimos, incluso el fichero de datos globales tampoco contiene datos concretos sobre cursos o grupos específicos. El módulo está diseñado para que solo los profesores sean capaces de visualizar la información relacionada a sus cursos y que ellos en ningún momento puedan saber que alumnos han contestado los cuestionarios. Solamente el administrador de la base de datos de la plataforma Moodle podrá acceder a datos sensibles.

Q: Soy profesor de un curso y a la vez estoy matriculado como estudiante en el mismo, ¿puedo contestar al cuestionario de mi curso como estudiante?

A: No, se prohibe para garantizar la consistencia de los resultados finales del curso.

Q: El periodo de visualización de resultados está activo, pero no puedo acceder a ningún informe porque los resultados aún no han sido procesados.. ¿Qué ha pasado?
 
A: Una vez que finaliza el periodo de completado de encuestas por parte de los estudiantes, el módulo SEPUG debe de computar toda la información recogida durante ese tiempo. Ya que es un proceso bastante pesado, esa tarea está asignada para comenzar automáticamente disparada por el proceso cron.php una vez que comience el segundo periodo. Si los resultados no están listo, puede ser que esta tarea siga en proceso o puede ser que no haya sido nunca ejecutada (por ejemplo, porque "cron"" no este activo en tu sitio Moodle: https://docs.moodle.org/all/es/Cron ). Para solucionarlo, puedes llamar manualmente a cron.php desde http://su.sitio.moodle/admin/cron.php . Observa el log que se genera y comprueba que la función sepug_cron haya sido ejecutada con éxito.

Q: Es el segundo periodo y los resultados ya han sido generados, ahora se quiere cambiar las fechas para volver a abrir el primer periodo de nuevo, ¿es esto posible?

A: Se puede volver a abrir el primer periodo y los estudiantes podrán volver a contestar encuestas, pero una vez que los resultados sean generados, no podrán volver a generarse. Es decir, aunque los estudiantes completen cuestionarios, estos no se reflejaran en los informes. Esto es así, ya que se quiere restringir la generación de resultados a solo una vez por actividad SEPUG, repetir el proceso puede generar grandes cargas al servidor y puede desvirtuar los resultados.

Q: La actividad SEPUG está en periodo de cierre, después de haber pasado por su ciclo de vida completo. Ahora quiero abrir el segundo periodo de nuevo para que los profesores tengan otra oportunidad de descargar los informes de resultados, ¿es posible?

A: Si, simplemente debe alargarse el periodo de cierre total de la actividad desde el menú de configuración. Los datos permanecen hasta que se elimine por completo la actividad.

Q: He usado una actividad SEPUG para recoger y obtener resultados del primer cuatrimestre lectivo, ¿puedo reutilizar la misma actividad para el segundo cuatrimestre?

A: No, debe eliminarse la actividad actual e iniciarse una nueva. Al eliminar SEPUG se limpiaran todos los datos que hubiera en la base de datos y evitaremos problemas al mezclarlos con los nuevos datos que se fueran a recoger.
