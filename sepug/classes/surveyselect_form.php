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

require_once($CFG->libdir.'/formslib.php');

class surveyselect_form extends moodleform {
    //Add elements to form
    public function definition() {
        global $CFG;
 
        $mform = $this->_form;

		$attributes = 'onChange="M.core_formchangechecker.set_form_submitted(); this.form.submit()"';
        $mform->addElement('select', 'cid', get_string('selectsurvey_label', 'sepug'), $this->_customdata['courses'], $attributes);
		$mform->setType('cid', PARAM_INT);
		$mform->setDefault('cid',0);
		
		// hidden elements
        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);
		
		// buttons
        $mform->addElement('submit', 'add_item', 'blbl', array('class' => 'hiddenifjs'));
    }
    //Custom validation should be added here
    function validation($data, $files) {
        return array();
    }
}

