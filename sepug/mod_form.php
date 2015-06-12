<?php
/*
	@ Universidad de Granada. Granada @ 2015
	@ Alejandro Molina Salazar (amolinasalazar@gmail.com). Granada @ 2015
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

/**
 * This file is responsible for show the configuration form of the SEPUG instance.
 *
 * @package   mod-sepug
 * @copyright 2015 Alejandro Molina Salazar
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once ($CFG->dirroot.'/course/moodleform_mod.php');

class mod_sepug_mod_form extends moodleform_mod {

    function definition() {
        global $CFG, $DB;
		
		$update  = optional_param('update', '0', PARAM_INT);

        $mform =& $this->_form;
		
		// Check if there is not a SEPUG instance already created
		if ($DB->record_exists("sepug", array("sepuginstance"=>1)) && $update==0) {
			print_error('sepug_already_created', 'sepug');
		}

		// Set 'sepuginstance' field to 1
		$mform->addElement('hidden', 'sepuginstance', '1');
		$mform->setType('sepuginstance', PARAM_INT);

		//-------------------------------------------------------------------------------
		// GENERAL
        $mform->addElement('header', 'general', get_string('general', 'form'));

		// Name
        $mform->addElement('text', 'name', get_string('name'), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
		
		// Description
        $this->add_intro_editor(false, get_string('customintro', 'sepug'));
		
		//-------------------------------------------------------------------------------
		// AVAILABILITY
        $mform->addElement('header', 'timinghdr', get_string('availability'));
		
		// Time open
		$mform->addElement('date_time_selector', 'timeopen', get_string('sepugopen', 'sepug'),
            array('optional' => false));
		$mform->addHelpButton('timeopen', 'sepugopen', 'sepug');
		
		// Time close for student
		$mform->addElement('date_time_selector', 'timeclosestudents', get_string('sepugclosestudents', 'sepug'),
            array('optional' => false));
		$mform->addHelpButton('timeclosestudents', 'sepugclosestudents', 'sepug');

		// Time close
        $mform->addElement('date_time_selector', 'timeclose', get_string('sepugclose', 'sepug'),
            array('optional' => false));
		$mform->addHelpButton('timeclose', 'sepugclose', 'sepug');
		
		//-------------------------------------------------------------------------------
		// CONFIGURATION
        $mform->addElement('header', 'config', get_string('config', 'sepug'));
	
		// Retrieve the max. category depth level
		$maxdepth = $DB->get_record_sql('SELECT MAX(depth) AS maxdepth FROM {course_categories}');    
		
		$options = array();
		for($i=1; $i<=$maxdepth->maxdepth; $i++){
			$options[$i] = get_string("level", "sepug")." ".$i;
		}

        $mform->addElement('select', 'depthlimit', get_string("depth_limit", "sepug"), $options);
		$mform->addHelpButton('depthlimit', 'depth_limit', 'sepug');
		
		// Retrieve category names of first level
		$firstdepthcat = $DB->get_records("course_categories", array("depth"=>1));
		
		$options = array();
		foreach($firstdepthcat as $cat){
			$options[$cat->id] = $cat->name;
		}
		
		// Grado category select
		$mform->addElement('select', 'catgrado', get_string("catgrado", "sepug"), $options);
		$mform->addHelpButton('catgrado', 'catgrado', 'sepug');
		
		// Posgrado category select
		$mform->addElement('select', 'catposgrado', get_string("catposgrado", "sepug"), $options);
		$mform->addHelpButton('catposgrado', 'catposgrado', 'sepug');
		
		//-------------------------------------------------------------------------------
		// description
        $this->standard_coursemodule_elements();
		//-------------------------------------------------------------------------------
        // buttons
        $this->add_action_buttons();
    }
}