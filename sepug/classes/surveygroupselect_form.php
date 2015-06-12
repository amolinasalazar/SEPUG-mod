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
 * Class that helps to create a select element form with a list of groups of a given course.
 *
 * @package   mod-sepug
 * @copyright 2015 Alejandro Molina Salazar
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->libdir.'/formslib.php');

class surveygroupselect_form extends moodleform {
    //Add elements to form
    public function definition() {
        global $CFG;
 
        $mform = $this->_form;

		$attributes = 'onChange="M.core_formchangechecker.set_form_submitted(); this.form.submit()"';
        $mform->addElement('select', 'group', get_string('grouptext', 'sepug'), $this->_customdata['groups'], $attributes);
		$mform->setType('group', PARAM_INT);
		$mform->setDefault('group',0);
		
		// hidden elements
        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);
		$mform->addElement('hidden', 'cid');
        $mform->setType('cid', PARAM_INT);
		
		// buttons
        $mform->addElement('submit', 'add_item', 'aceptar', array('class' => 'hiddenifjs'));
    }
    //Custom validation should be added here
    function validation($data, $files) {
        return array();
    }
}

