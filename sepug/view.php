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
 * This file is responsible for displaying a select form to complete surveys or to see the results.
 *
 * @package   mod-sepug
 * @copyright 2015 Alejandro Molina Salazar
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

    require_once("../../config.php");
    require_once("lib.php");
	require_once('classes/surveyselect_form.php');
	
	global $USER, $DB, $FILTRO_CURSOS;

    $id = required_param('id', PARAM_INT);    // Course Module ID

    if (! $cm = get_coursemodule_from_id('sepug', $id)) {
        print_error('invalidcoursemodule');
    }

    if (! $course = $DB->get_record("course", array("id"=>$cm->course))) {
        print_error('coursemisconf');
    }
	
    $PAGE->set_url('/mod/sepug/view.php', array('id'=>$id));
    require_login($course, false, $cm);

	$context = context_course::instance($course->id);

    if (! $survey = $DB->get_record("sepug", array("id"=>$cm->instance))) {
        print_error('invalidsurveyid', 'sepug');
    }
	
	// Update 'viewed' state if required by completion system
	require_once($CFG->libdir . '/completionlib.php');
	$completion = new completion_info($course);
	$completion->set_module_viewed($cm);

	$PAGE->set_title(get_string("modulename","sepug"));
    $PAGE->set_heading(get_string("modulename","sepug"));
	echo $OUTPUT->header();
	echo $OUTPUT->heading(get_string("modulename_full", "sepug"));
	echo $OUTPUT->box(get_string("view_intro", "sepug"), 'generalbox', 'intro');
	
	// If SEPUG is NOT activated for students
    $checktime = time();
    if (($survey->timeopen > $checktime) OR ($survey->timeclose < $checktime)) {
		echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
        echo $OUTPUT->notification(get_string('sepug_is_not_open', 'sepug'));
        echo $OUTPUT->continue_button($CFG->wwwroot.'/course/view.php?id='.$cm->course);
        echo $OUTPUT->box_end();
        echo $OUTPUT->footer();
        exit;
    }
	else{
		
		$courses = sepug_get_enrolled_valid_courses($survey);
		
		// If $USER is not enrolled in any course or only to the course ID = 1
		if(empty($courses) or (count($courses)==1 and array_keys($courses) == 1)){
			echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
			echo $OUTPUT->notification(get_string('no_courses', 'sepug'));
			echo $OUTPUT->continue_button($CFG->wwwroot.'/course/view.php?id='.$cm->course);
			echo $OUTPUT->box_end();
			echo $OUTPUT->footer();
			exit;
		}
		
		// If $USER is enrolled in one or more courses
		$stud_courses = array();
		$prof_courses = array();
		foreach($courses as $course){
			$cid = $course->id;
			$cntxt = get_context_instance(CONTEXT_COURSE, $cid);
			// We get all the roles in this context - r: array asoc.(ids rol)
			$roles = get_user_roles($cntxt, $USER->id, false, 'c.contextlevel DESC, r.sortorder ASC');
			foreach($roles as $rol){
				// If is editingteacher
				if($rol->roleid == 3){
					array_push($prof_courses, $cid);
				}
				// If is student
				else if($rol->roleid == 5){
					array_push($stud_courses, $cid);
				}
			}
		}
		
		// If is student
		if(!empty($stud_courses)){
			$checktime = time();
			// but is out of date
			if ($survey->timeclosestudents < $checktime){
				echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
				echo $OUTPUT->notification(get_string('sepug_close_for_students', 'sepug'));
				echo $OUTPUT->box_end();
			}
			else{
			
				// We generate a select list of courses
				$courses_list[0] = 'Cursos...';
				foreach($stud_courses as $cid){
					// Check if the course has groups
					$groups = groups_get_user_groups($cid,$USER->id);
					if(empty($groups[0])){
						if($course = $DB->get_record("course", array("id"=>$cid)) and !sepug_already_done($cid, $USER->id)){
							$courses_list[$cid] = $course->fullname;
						}
					}
					else{
						$ya_introducido = false;
						foreach($groups[0] as $group){
							if($course = $DB->get_record("course", array("id"=>$cid)) and !sepug_already_done($cid, $USER->id, $group) and
							!$ya_introducido){
								$courses_list[$cid] = $course->fullname;
								$ya_introducido = true;
							}
						}
					}
				}
				
				// If the $USER have already submitted all the surveys
				if(count($courses_list)==1){
					echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
					echo $OUTPUT->notification(get_string('all_surveys_are_done', 'sepug'));
					echo $OUTPUT->box_end();
				}
				else{
					// Print select
					$mform = new surveyselect_form('survey_view.php', array('courses'=>$courses_list));
					$mform->set_data(array('cmid'=>$id));
					echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
					echo $OUTPUT->heading(get_string("access_surveys", "sepug"));
					echo '<div class="mdl-align">';
					echo '<fieldset>';
					$mform->display();
					echo '</fieldset>';
					echo '</div>';
					echo $OUTPUT->box_end();
					
					// Info about the date to close
					$timeclosestudents = date('d', $survey->timeclosestudents).' del '.date('m', $survey->timeclosestudents).' a las '.date('H', $survey->timeclosestudents).':'.date('i', $survey->timeclosestudents).' horas';
					echo $OUTPUT->notification(get_string('closestudentsdate', 'sepug', $timeclosestudents));	
				}
			}
		}
		
		// If is editingteacher
		if(!empty($prof_courses)){
			$checktime = time();
			// but we have no results
			if ($survey->timeclosestudents > $checktime){
				echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
				echo $OUTPUT->notification(get_string('no_results', 'sepug'));
				echo $OUTPUT->box_end();
			}
			else{
				
				// We generate a select list of courses
				$courses_list[0] = 'Cursos...';
				foreach($prof_courses as $cid){
					if($course = $DB->get_record("course", array("id"=>$cid))){
						$courses_list[$cid] = $course->fullname;
					}
				}
			
				// Print select
				$mform = new surveyselect_form('report.php', array('courses'=>$courses_list));
				$mform->set_data(array('cmid'=>$id));
				echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
				echo $OUTPUT->heading(get_string("show_results", "sepug"));
				echo '<div class="mdl-align">';
				echo '<fieldset>';
				$mform->display();
				echo '</fieldset>';
				echo '</div>';
				echo $OUTPUT->box_end();
				
				// Info about the date to close
				$timeclose = date('d', $survey->timeclose).' del '.date('m', $survey->timeclose).' a las '.date('H', $survey->timeclose).':'.date('i', $survey->timeclose).' horas';
				echo $OUTPUT->notification(get_string('closedate', 'sepug', $timeclose));
			}
		}
		
		// If SEPUG is activated for teachers and the $USER has the capability to download the global data file, 
		// print a button for that.
		$checktime = time();
		if (($survey->timeopen < $checktime) AND ($survey->timeclose > $checktime) 
			AND ($survey->timeclosestudents < $checktime)) {
		
			if(has_capability('mod/sepug:global_download', $context)){
				echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
				
				$options = array();
				$options["cmid"] = $id;
				$options["type"] = "global";
				echo '<div class="mdl-align">';
				echo $OUTPUT->single_button(new moodle_url("download.php", $options), get_string("globaldownload","sepug"));
				echo '</div>';
				echo $OUTPUT->box_end();
			}
			
		}
		echo $OUTPUT->footer();
	}	

