<?php

/*
	� Universidad de Granada. Granada � 2014
	� Alejandro Molina Salazar (amolinasalazar@gmail.com). Granada � 2014
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
 * @copyright 2014 Alejandro Molina Salazar
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
    $qid     = optional_param('qid', 0, PARAM_RAW);       // Question IDs comma-separated list
    $student = optional_param('student', 0, PARAM_INT);   // Student ID
    $notes   = optional_param('notes', '', PARAM_RAW);    // Save teachers notes
	$group = optional_param('group', 0, PARAM_INT); // Group ID

    $qids = explode(',', $qid);
    $qids = clean_param_array($qids, PARAM_INT);
    $qid = implode (',', $qids);
	
	// Ignoramos el curso 1
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
    if ($qid !== 0) {
        $url->param('qid', $qid);
    }
    if ($student !== 0) {
        $url->param('student', $student);
    }
    if ($notes !== '') {
        $url->param('notes', $notes);
    }
    $PAGE->set_url($url);

    require_login($course);

	$context = context_course::instance($course->id);

    require_capability('mod/sepug:readresponses', $context);

    if (! $survey = $DB->get_record("sepug", array("id"=>$cm->instance))) {
        print_error('invalidsurveyid', 'sepug');
    }
	
	// Si no esta matriculado en este curso
    if (!is_enrolled($context)) {
        echo $OUTPUT->notification(get_string("guestsnotallowed", "sepug"));
    }
		
	// Obtenemos todos los roles de este contexto - r: array asoc.(ids rol)
	$roles = get_user_roles($context, $USER->id, false, 'c.contextlevel DESC, r.sortorder ASC');
	foreach($roles as $rol){
		// Si no es profesor de este curso
		if($rol->roleid != 3){
			print_error('onlyprof', 'sepug');
		}
	}
	
	// Si sepug NO esta activo para profesores
    $checktime = time();
    if (($survey->timeopen > $checktime) OR ($survey->timeclose < $checktime) 
		OR ($survey->timeclosestudents > $checktime)) {
		print_error('sepug_is_not_open', 'sepug');
	}
	
	// Pasamos filtro de cursos si procede
	if($FILTRO_CURSOS && !sepug_courseid_validator($cid)){
		print_error('invalidcoursemodule');
	}	

	$tmpid = sepug_get_template($cid);
	if (! $template = $DB->get_record("sepug", array("id"=>$tmpid))) {
        print_error('invalidtmptid', 'sepug');
    }

    $showscales = ($template->name != 'ciqname');

    $strreport = get_string("report", "sepug");
    $strsurvey = get_string("modulename", "sepug");
    $strsurveys = get_string("modulenameplural", "sepug");
    $strsummary = get_string("summary", "sepug");
    $strscales = get_string("scales", "sepug");
    $strquestion = get_string("question", "sepug");
    $strquestions = get_string("questions", "sepug");
    $strdownload = get_string("download", "sepug");
    $strallscales = get_string("allscales", "sepug");
    $strallquestions = get_string("allquestions", "sepug");
    $strselectedquestions = get_string("selectedquestions", "sepug");
    $strseemoredetail = get_string("seemoredetail", "sepug");
    $strnotes = get_string("notes", "sepug");

    switch ($action) {
        case 'download':
            $PAGE->navbar->add(get_string('downloadresults', 'sepug'));
            break;
        case 'summary':
        case 'scales':
        case '':
            $PAGE->navbar->add($strreport);
            $PAGE->navbar->add($strsummary);
            break;
        default:
            $PAGE->navbar->add($strreport);
            break;
    }

    $PAGE->set_title("$course->shortname: ".format_string($survey->name));
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();

/// Check to see if groups are being used in this survey
    if ($groupmode = groups_get_activity_groupmode($cm)) {   // Groups are being used
        $menuaction = $action == "student" ? "students" : $action;
        $currentgroup = groups_get_activity_group($cm, true);
        groups_print_activity_menu($cm, $CFG->wwwroot . "/mod/sepug/report.php?id=$cm->id&amp;action=$menuaction&amp;qid=$qid");
    } else {
        $currentgroup = 0;
    }

    $groupingid = $cm->groupingid;

    echo $OUTPUT->box_start("generalbox boxaligncenter");
    if ($showscales) {
		echo "<a href=\"report.php?action=summary&amp;cmid=$cmid&amp;cid=$cid\">$strsummary</a>";
        
        if (has_capability('mod/sepug:download', $context)) {
			echo "&nbsp;&nbsp;&nbsp;&nbsp;<a href=\"report.php?action=download&amp;cmid=$cmid&amp;cid=$cid&amp;group=$group\">$strdownload</a>";
        }
        if (empty($action)) {
            $action = "summary";
        }
    } else {
		echo "<a href=\"report.php?action=questions&amp;cmid=$cmid&amp;cid=$cid\">$strquestions</a>";
        if (has_capability('mod/sepug:download', $context)) {
			echo "&nbsp;&nbsp;&nbsp;&nbsp;<a href=\"report.php?action=download&amp;cmid=$cmid&amp;cid=$cid\">$strdownload</a>";
        }
        if (empty($action)) {
            $action = "questions";
        }
    }
    echo $OUTPUT->box_end();

    echo $OUTPUT->spacer(array('height'=>30, 'width'=>30, 'br'=>true)); // should be done with CSS instead


/// Print the menu across the top

    $virtualscales = false;

    switch ($action) {

	// Aqui carga la pesta�a RESUMEN
      case "summary":
        echo $OUTPUT->heading(get_string("summarytext1", "sepug"),1);
		
		// Imprimir seleccionable de grupo, si es que hay grupos en esta asignatura
		$groups_list[0] = 'Grupo general';
		// Comprobamos que ese curso no tenga grupos internos..
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
			
			// COMPROBAR QUE HAY RESULTADOS
			//sepug_insert_prof_stats($cid, $group);
			//sepug_insert_global_stats();
			
			// Si los datos no estan procesados no podemos mostrar nada
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
		
		// Si no hay resultados, no generamos ningun fichero
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

