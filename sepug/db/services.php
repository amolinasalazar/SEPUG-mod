<?php
/**
 * Web service of SEPUG module for external functions and service definitions.
 *
 * @copyright  Universidad de Granada. Granada – 2015 
 * @author     Alejandro Molina (amolinasalazar@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 
// Web Services functions definitions.
$functions = array(
        'mod_sepug_get_sepug_instance' => array(
                'classname'   => 'mod_sepug_external',
                'methodname'  => 'get_sepug_instance',
                'classpath'   => 'mod/sepug/externallib.php',
                'description' => 'Return sepug instance details.',
                'type'        => 'read'
        ),
		'mod_sepug_get_not_submitted_enrolled_courses_as_student' => array(
                'classname'   => 'mod_sepug_external',
                'methodname'  => 'get_not_submitted_enrolled_courses_as_student',
                'classpath'   => 'mod/sepug/externallib.php',
                'description' => 'Returns all the enrolled courses for the current student user.',
                'type'        => 'read',
        ),
		'mod_sepug_get_survey_questions' => array(
                'classname'   => 'mod_sepug_external',
                'methodname'  => 'get_survey_questions',
                'classpath'   => 'mod/sepug/externallib.php',
                'description' => 'Return the questions of the survey using an ID course as parameter.',
                'type'        => 'read',
				'capabilities'=> 'mod/sepug:participate'
        ),
		'mod_sepug_submit_survey' => array(
                'classname'   => 'mod_sepug_external',
                'methodname'  => 'submit_survey',
                'classpath'   => 'mod/sepug/externallib.php',
                'description' => 'Store in DB the answers of a survey.',
                'type'        => 'write',
				'capabilities'=> 'mod/sepug:participate'
        )
);

// The pre-build services to install.
$services = array(
        'Service for SEPUG' => array(
                'functions' => array ('mod_sepug_get_sepug_instance', 'mod_sepug_get_not_submitted_enrolled_courses_as_student', 'mod_sepug_get_survey_questions', 'mod_sepug_submit_survey', 'core_webservice_get_site_info', 'core_enrol_get_users_courses'),
                'restrictedusers' => 0,
                'enabled'=>1,
        )
);
