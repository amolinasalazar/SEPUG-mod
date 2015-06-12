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
 * This file is responsible for producing the survey reports
 *
 * @package   mod-sepug
 * @copyright 2015 Alejandro Molina Salazar
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

    require_once("../../config.php");
    require_once("lib.php");
	require_once('classes/surveygroupselect_form.php');
	
	global $FILTRO_CURSOS;

	// Check that all the parameters have been provided.
	$cmid = required_param('cmid', PARAM_INT);    // Course Module ID
	$cid = required_param('cid', PARAM_INT);    // Course ID
    $action  = optional_param('action', '', PARAM_ALPHA); // What to look at
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

    $url = new moodle_url('/mod/sepug/report.php', array('cmid'=>$cmid, 'cid'=>$cid));
    if ($action !== '') {
        $url->param('action', $action);
    }
    $PAGE->set_url($url);

    require_login($course);

	$context = context_course::instance($course->id);

    require_capability('mod/sepug:readresponses', $context);

    if (! $survey = $DB->get_record("sepug", array("id"=>$cm->instance))) {
        print_error('invalidsurveyid', 'sepug');
    }
	
	// If $USER is not enrolled in this course
    if (!is_enrolled($context)) {
        echo $OUTPUT->notification(get_string("guestsnotallowed", "sepug"));
    }
		
	// We get all the roles in this context - r: array asoc.(ids rol)
	$roles = get_user_roles($context, $USER->id, false, 'c.contextlevel DESC, r.sortorder ASC');
	$editingteacherrole = false;
	foreach($roles as $rol){
		if($rol->roleid == 3){
			$editingteacherrole=true;
		}
	}
	// If $USER is not an editingteacher
	if(!$editingteacherrole){
		print_error('onlyprof', 'sepug');
	}
	
	// Check if SEPUG is activated for teachers
    $checktime = time();
    if (($survey->timeopen > $checktime) OR ($survey->timeclose < $checktime) 
		OR ($survey->timeclosestudents > $checktime)) {
		print_error('sepug_is_not_open', 'sepug');
	}
	
	// If we had set a course filter and the course is not valid
	if($FILTRO_CURSOS && !sepug_courseid_validator($cid)){
		print_error('invalidcoursemodule');
	}	

	$tmpid = sepug_get_template($cid);
	if (! $template = $DB->get_record("sepug", array("id"=>$tmpid))) {
        print_error('invalidtmptid', 'sepug');
    }

    $strreport = get_string("report", "sepug");
    $strsummary = get_string("summary", "sepug");
    $strdownload = get_string("download", "sepug");

    switch ($action) {
        case 'download':
            $PAGE->navbar->add(get_string('downloadresults', 'sepug'));
        break;

        default:
            $PAGE->navbar->add($strreport);
        break;
    }

    $PAGE->set_title("$course->shortname: ".format_string($survey->name));
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();
    echo $OUTPUT->box_start("generalbox boxaligncenter");
	echo "<a href=\"report.php?action=summary&amp;cmid=$cmid&amp;cid=$cid\">$strsummary</a>";
	
	if (has_capability('mod/sepug:download', $context)) {
		echo "&nbsp;&nbsp;&nbsp;&nbsp;<a href=\"report.php?action=download&amp;cmid=$cmid&amp;cid=$cid&amp;group=$group\">$strdownload</a>";
	}
	if (empty($action)) {
		$action = "summary";
	}
    echo $OUTPUT->box_end();
    echo $OUTPUT->spacer(array('height'=>30, 'width'=>30, 'br'=>true)); // should be done with CSS instead

    switch ($action) {

		case "summary":
			echo $OUTPUT->heading(get_string("summarytext1", "sepug"),1);
			
			// Print group select, if it's necessary
			$groups_list[0] = 'Grupo general';
			// Check if this course has groups..
			$groups = groups_get_all_groups($cid);
			foreach($groups as $gr){
				$groups_list[$gr->id] = $gr->name;
			}
			if(count($groups_list)>1){
				$mform = new surveygroupselect_form('report.php', array('groups'=>$groups_list));
				$mform->set_data(array('cid'=>$cid));
				$mform->set_data(array('cmid'=>$cmid));
				$mform->display();
			}

			if (sepug_count_responses($cid, $group)==0) {
				echo $OUTPUT->notification(get_string("nobodyyet","sepug"));

			} else {
				// If we have no data, we have nothing to show
				if (!$DB->record_exists("sepug_prof_stats", array("courseid"=>$cid, "groupid"=>$group)) ) {
					echo $OUTPUT->notification(get_string("no_results","sepug"));
				}
				else{
					echo "<h3>".get_string("summarytext2", "sepug", $course->fullname)."</h3>";
					if($group==0){
						echo "<h3>".get_string("summarytext4", "sepug", "no asignado")."</h3>";
					}
					else{
						echo "<h3>".get_string("summarytext4", "sepug", groups_get_group_name($group))."</h3>";
					}
					echo "<h3>".get_string("summarytext3", "sepug", sepug_count_responses($cid, $group))."</h3></br></br>";
					
					sepug_print_frequency_table($survey,$course,$group);
					echo "<br/>";
					sepug_print_dimension_table($survey,$course,$group);
					echo "<br/>";
					sepug_print_global_results_graph("cid=$cid&amp;type=question.png");
				}
			}
        break;
		
		case "download":
			echo $OUTPUT->heading($strdownload);

			require_capability('mod/sepug:download', $context);

			echo '<p class="centerpara">'.get_string("downloadinfo", "sepug").'</p>';
			
			// If we have no data, we cannot generate data files
			if (sepug_count_responses($cid, $group)==0) {
				echo $OUTPUT->notification(get_string("nobodyyet","sepug"));
			}
			elseif(!$DB->record_exists("sepug_prof_stats", array("courseid"=>$cid, "groupid"=>$group)) ) {
				echo $OUTPUT->notification(get_string("no_results","sepug"));
			}
			else{
				echo $OUTPUT->container_start('reportbuttons');
				$options = array();
				$options["cid"] = $cid;
				$options["cmid"] = $cmid;
				$options["group"] = $group;

				$options["type"] = "ods";
				echo '<div class="mdl-align">';
				echo $OUTPUT->single_button(new moodle_url("download.php", $options), get_string("downloadods"));
				echo '</div>';
				echo $OUTPUT->container_end();
			}
        break;
    }
    echo $OUTPUT->footer();

