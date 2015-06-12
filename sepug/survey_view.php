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
 * This file is responsible for displaying the survey form.
 *
 * @package   mod-sepug
 * @copyright 2015 Alejandro Molina Salazar
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 
    require_once("../../config.php");
    require_once("lib.php");
	require_once('classes/surveygroupselect_form.php');
	
	global $FILTRO_CURSOS;

    $cmid = required_param('cmid', PARAM_INT);    // Course Module ID
	$cid = required_param('cid', PARAM_INT);    // Course ID
	$group = optional_param('group', 0, PARAM_INT); // Group ID
	
	// Skip course id = 1
	if($cid == 1){
		print_error('notvalidcourse','sepug');
	}

    if (! $cm = get_coursemodule_from_id('sepug', $cmid)) {
        print_error('invalidcoursemodule');
    }
	
	if (! $course = $DB->get_record("course", array("id"=>$cid))) {
        print_error('coursemisconf');
    }
	
    $PAGE->set_url('/mod/sepug/survey_view.php', array('cmid'=>$cmid, 'cid'=>$cid));
    require_login($course);
	$context = context_course::instance($course->id);

    require_capability('mod/sepug:participate', $context);

	if (! $survey = $DB->get_record("sepug", array("id"=>$cm->instance))) {
        print_error('invalidsurveyid', 'sepug');
    }
	
	// Check if SEPUG is activated for students
    $checktime = time();
    if (($survey->timeopen > $checktime) OR ($survey->timeclose < $checktime) 
		OR ($survey->timeclosestudents < $checktime)){
		print_error('sepug_is_not_open', 'sepug');
	}
	
	// Get template ID (GRADO or POSTGRADO)
    $trimmedintro = trim($survey->intro);
	$tmpid = sepug_get_template($cid);
    if (empty($trimmedintro)) {
		$tempo = $DB->get_field("sepug", "intro", array("id"=>$tmpid));
        $survey->intro = get_string($tempo, "sepug");
    }
	
	// Get template by the ID
    if (! $template = $DB->get_record("sepug", array("id"=>$tmpid))) {
        print_error('invalidtmptid', 'sepug');
    }

    $PAGE->set_title($survey->name);
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();

	// If $USER is not enrolled in this course
    if (!is_enrolled($context)) {
        echo $OUTPUT->notification(get_string("guestsnotallowed", "sepug"));
    }
		
	// We get all the roles in this context - r: array asoc.(ids rol)
	$roles = get_user_roles($context, $USER->id, false, 'c.contextlevel DESC, r.sortorder ASC');
	$studentrole = false;
	$editingteacherrole = false;
	foreach($roles as $rol){
		if($rol->roleid == 3){
			$editingteacherrole=true;
		}
		if($rol->roleid == 5){
			$studentrole = true;
		}
	}
	// If $USER is not student of this course or if is an editing teacher also
	if(!$studentrole || $editingteacherrole){
		print_error('onlystudents', 'sepug');
	}
	
	// If we had set a course filter and the course is not valid
	if($FILTRO_CURSOS && !sepug_courseid_validator($cid)){
		print_error('coursesfilterexception', 'sepug');
	}	
	
	if (sepug_already_done($cid, $USER->id, $group)) {
		print_error("surveycompleted", "sepug");
        echo $OUTPUT->footer();
        exit;
    }

    echo $OUTPUT->box(format_module_intro('sepug', $survey, $cm->id), 'generalbox boxaligncenter bowidthnormal', 'intro');
	
	// Print group select, if it's necessary
	$groups_list[0] = 'Grupos...';
	// Check if this course has groups..
	$groups = groups_get_user_groups($cid,$USER->id);
	foreach($groups[0] as $gr){
		$group_name = $DB->get_record("groups", array("id"=>$gr),"name");
		$groups_list[$gr] = $group_name->name;
	}
	if(count($groups_list)>1){
		$mform = new surveygroupselect_form('survey_view.php', array('groups'=>$groups_list));
		$mform->set_data(array('cid'=>$cid));
		$mform->set_data(array('cmid'=>$cmid));
		$mform->display();
	}
	
	// If the course has no groups or the group is already selected
	if(!($group==0 AND count($groups_list)>1)){
		
		echo "<form method=\"post\" action=\"save.php\" id=\"surveyform\">";
		echo '<div>';
		echo "<input type=\"hidden\" name=\"cmid\" value=\"$cmid\" />";
		echo "<input type=\"hidden\" name=\"cid\" value=\"$cid\" />";
		echo "<input type=\"hidden\" name=\"sesskey\" value=\"".sesskey()."\" />";
		echo "<input type=\"hidden\" name=\"group\" value=\"$group\" />";
		echo '<div>'. get_string('allquestionrequireanswer', 'sepug'). '</div>';

		// Retrieve all the questions from the template
		if (! $questions = $DB->get_records_list("sepug_questions", "id", explode(',', $template->questions))) {
			print_error('cannotfindquestion', 'sepug');
		}
		$questionorder = explode( ",", $template->questions);

		// Cycle through all the questions in order and print them
		global $qnum;  
		global $checklist; 
		$qnum = 0;
		$checklist = array();
		foreach ($questionorder as $key => $val) {
			$question = $questions["$val"];
			$question->id = $val;

			if ($question->type >= 0) {

				if ($question->text) {
					$question->text = get_string($question->text, "sepug");
				}

				if ($question->shorttext) {
					$question->shorttext = get_string($question->shorttext, "sepug");
				}

				if ($question->intro) {
					$question->intro = get_string($question->intro, "sepug");
				}

				if ($question->options) {
					$question->options = get_string($question->options, "sepug");
				}

				if ($question->multi) {
					sepug_print_multi($question);
				} else {
					sepug_print_single($question);
				}
			}
		}

		if (!is_enrolled($context)) {
			echo '</div>';
			echo "</form>";
			echo $OUTPUT->footer();
			exit;
		}

		// Call sepug.js that checks if we have answered all the questions
		$checkarray = Array('questions'=>Array());
		if (!empty($checklist)) {
		   foreach ($checklist as $question => $default) {
			   $checkarray['questions'][] = Array('question'=>$question, 'default'=>$default);
		   }
		}
		$PAGE->requires->data_for_js('surveycheck', $checkarray);
		$module = array(
			'name'      => 'mod_sepug',
			'fullpath'  => '/mod/sepug/sepug.js',
			'requires'  => array('yui2-event'),
		);
		$PAGE->requires->string_for_js('questionsnotanswered', 'sepug');
		$PAGE->requires->js_init_call('M.mod_sepug.init', $checkarray, true, $module);

		echo '<br />';
		echo '<input type="submit" value="'.get_string("clicktocontinue", "sepug").'" />';
		echo '</div>';
		echo "</form>";

		echo $OUTPUT->footer();
	}
	else{
		echo $OUTPUT->footer();
	}


