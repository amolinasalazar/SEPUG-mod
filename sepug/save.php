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
 * This file is responsible for saving the results of a users survey and displaying
 * the final message.
 *
 * @package   mod-sepug
 * @copyright 2015 Alejandro Molina Salazar
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

    require_once('../../config.php');
    require_once('lib.php');
	
	global $FILTRO_CURSOS;

	// Make sure this is a legitimate posting
    if (!$formdata = data_submitted() or !confirm_sesskey()) {
        print_error('cannotcallscript');
    }

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

	$PAGE->set_url('/mod/sepug/save.php', array('cid'=>$cid, 'cmid'=>$cmid));
    require_login($course);

	$context = context_course::instance($course->id);
    require_capability('mod/sepug:participate', $context);

	if (! $survey = $DB->get_record("sepug", array("id"=>$cm->instance))) {
        print_error('invalidsurveyid', 'sepug');
    }
	
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
	
	// Check if SEPUG is activated for students
    $checktime = time();
    if (($survey->timeopen > $checktime) OR ($survey->timeclose < $checktime) 
		OR ($survey->timeclosestudents < $checktime)){
		print_error('sepug_is_not_open', 'sepug');
	}
	
	// If we had set a course filter and the course is not valid
	if($FILTRO_CURSOS && !sepug_courseid_validator($cid)){
		print_error('coursesfilterexception', 'sepug');
	}

    $PAGE->set_title(get_string('surveysaved', 'sepug'));
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();

	if (sepug_already_done($cid, $USER->id)) {
        notice(get_string("alreadysubmitted", "sepug"), $_SERVER["HTTP_REFERER"]);
        exit;
    }

	// Sort through the data and arrange it
    $answers = array();

    foreach ($formdata as $key => $val) {
        if ($key <> "userid" && $key <> "id" && $key <> "cmid" && $key <> "cid" && $key <> "sesskey" && $key <>"group") {
            if ( substr($key,0,1) == "q") {
                $key = clean_param(substr($key,1), PARAM_ALPHANUM);
            }
            if ( substr($key,0,1) == "P") {
                $realkey = (int) substr($key,1);
                $answers[$realkey][1] = $val;
            } else {
                $answers[$key][0] = $val;
            }
        }
    }
	
	// Check and validate the response values before insert them into the DB
	$idarray = sepug_get_questions_ID($cid, true);
	$IDsalready = array();
	foreach($answers as $key => $val){
		
		// Check if the question ID is valid and is not already processed
		if(!in_array($key,$idarray) or in_array($key, $IDsalready)){
			print_error('responsevaluenotvalid', 'sepug');
		}
		
		// Check if the response values are valid
		if(!sepug_response_value_validator($val[0], $key)){
			print_error('responsevaluenotvalid', 'sepug');
		}
		
		$IDsalready[] = $key;
	}

	// Now store the data.
	$timenow = time();
    foreach ($answers as $key => $val) {
		$newdata = new stdClass();
		$newdata->time = $timenow;
		$newdata->userid = $USER->id;
		$newdata->courseid = $cid;
		$newdata->question = $key;
		$newdata->groupid = $group;
		if (!empty($val[0])) {
			$newdata->answer1 = $val[0];
		} else {
			$newdata->answer1 = "";
		}

		$DB->insert_record("sepug_answers", $newdata);
    }

	// Print the page and finish up.
	notice(get_string("thanksforanswers","sepug", $USER->firstname), "$CFG->wwwroot/mod/sepug/view.php?id=$cmid");

    exit;