<?php
/*
	© Universidad de Granada. Granada – 2014
	© Alejandro Molina Salazar (amolinasalazar@gmail.com). Granada – 2014
    This program is free software: you can redistribute it and/or 
    modify it under the terms of the GNU General Public License as 
    published by the Free Software Foundation, either version 3 of 
    the License.
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses>.
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once ($CFG->dirroot.'/course/moodleform_mod.php');

class mod_sepug_mod_form extends moodleform_mod {

    function definition() {
        global $CFG, $DB;

        $mform =& $this->_form;
		
        $strrequired = get_string('required');
		
		// Comprobar que no hay un cuestionario previamente creado en Moodle
		/*if (!$options = $DB->get_records_menu("sepug", array("template"=>0), "name", "id, name")) {
            print_error('cannotfindsurveytmpt', 'sepug');
		}*/
		
		// Si no se esta creando en el curso con id=1, error
		/*if (!$options = $DB->get_records_menu("sepug", array("template"=>0), "name", "id, name")) {
            print_error('cannotfindsurveytmpt', 'sepug');
		}*/
		
		
		
		
		

		//-------------------------------------------------------------------------------
		// GENERAL
        $mform->addElement('header', 'general', get_string('general', 'form'));

		// nombre
        $mform->addElement('text', 'name', get_string('name'), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
		
		
		// tipo cuestionario
        /*if (!$options = $DB->get_records_menu("sepug", array("template"=>0), "name", "id, name")) {
            print_error('cannotfindsurveytmpt', 'sepug');
        }
        foreach ($options as $id => $name) {
            $options[$id] = get_string($name, "sepug");
        }
        $options = array(''=>get_string('choose').'...') + $options;
        $mform->addElement('select', 'template', get_string("surveytype", "sepug"), $options);
        $mform->addRule('template', $strrequired, 'required', null, 'client');
        $mform->addHelpButton('template', 'surveytype', 'sepug');
		*/
		
		// descripcion
        $this->add_intro_editor(false, get_string('customintro', 'sepug'));
		
		//-------------------------------------------------------------------------------
		// DISPONIBILIDAD
        $mform->addElement('header', 'timinghdr', get_string('availability'));
		
		// fecha activacion
		$mform->addElement('date_time_selector', 'timeopen', get_string('sepugopen', 'sepug'),
            array('optional' => false));
		$mform->addHelpButton('timeopen', 'sepugopen', 'sepug');
		
		// cerrar alumnos y crear resultados
		$mform->addElement('date_time_selector', 'timeclosestudents', get_string('sepugclosestudents', 'sepug'),
            array('optional' => false));
		$mform->addHelpButton('timeclosestudents', 'sepugclosestudents', 'sepug');

		// cerrar
        $mform->addElement('date_time_selector', 'timeclose', get_string('sepugclose', 'sepug'),
            array('optional' => false));
		$mform->addHelpButton('timeclose', 'sepugclose', 'sepug');
		
		//-------------------------------------------------------------------------------
		// aniadir descripcion 
        $this->standard_coursemodule_elements();
		//-------------------------------------------------------------------------------
        // buttons
        $this->add_action_buttons();
    }


}

