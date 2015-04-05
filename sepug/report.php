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

// Check that all the parameters have been provided.

    //$id      = required_param('id', PARAM_INT);           // Course Module ID
	$cmid = required_param('cmid', PARAM_INT);    // Course Module ID
	$cid = required_param('cid', PARAM_INT);    // Course ID
    $action  = optional_param('action', '', PARAM_ALPHA); // What to look at
    $qid     = optional_param('qid', 0, PARAM_RAW);       // Question IDs comma-separated list
    $student = optional_param('student', 0, PARAM_INT);   // Student ID
    $notes   = optional_param('notes', '', PARAM_RAW);    // Save teachers notes

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

    /*if (! $course = $DB->get_record("course", array("id"=>$cm->course))) {
        print_error('coursemisconf');
    }*/
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

    //$context = context_module::instance($cm->id);
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

    /*if (! $template = $DB->get_record("sepug", array("id"=>$survey->template))) {
        print_error('invalidtmptid', 'sepug');
    }*/
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

    //add_to_log($course->id, "sepug", "view report", "report.php?id=$cm->id", "$survey->id", $cm->id);

    switch ($action) {
        case 'download':
            $PAGE->navbar->add(get_string('downloadresults', 'sepug'));
            break;
        case 'summary':
        case 'scales':
        /*case 'questions':
            $PAGE->navbar->add($strreport);
            $PAGE->navbar->add(${'str'.$action});
            break;*/
        /*case 'students':
            $PAGE->navbar->add($strreport);
            $PAGE->navbar->add(get_string('participants'));
            break;*/
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

    if ($currentgroup) {
        $users = get_users_by_capability($context, 'mod/sepug:participate', '', '', '', '', $currentgroup, null, false);
    } else if (!empty($cm->groupingid)) {
        $groups = groups_get_all_groups($courseid, 0, $cm->groupingid);
        $groups = array_keys($groups);
        $users = get_users_by_capability($context, 'mod/sepug:participate', '', '', '', '', $groups, null, false);
    } else {
        $users = get_users_by_capability($context, 'mod/sepug:participate', '', '', '', '', '', null, false);
        $group = false;
    }

    $groupingid = $cm->groupingid;

    echo $OUTPUT->box_start("generalbox boxaligncenter");
    if ($showscales) {
        //echo "<a href=\"report.php?action=summary&amp;id=$id\">$strsummary</a>";
		echo "<a href=\"report.php?action=summary&amp;cmid=$cmid&amp;cid=$cid\">$strsummary</a>";
        //echo "&nbsp;&nbsp;&nbsp;&nbsp;<a href=\"report.php?action=scales&amp;id=$id\">$strscales</a>";
        //echo "&nbsp;&nbsp;&nbsp;&nbsp;<a href=\"report.php?action=questions&amp;id=$id\">$strquestions</a>";
		//echo "&nbsp;&nbsp;&nbsp;&nbsp;<a href=\"report.php?action=questions&amp;cmid=$cmid&amp;cid=$cid\">$strquestions</a>";
        //echo "&nbsp;&nbsp;&nbsp;&nbsp;<a href=\"report.php?action=students&amp;id=$id\">".get_string('participants')."</a>";
        if (has_capability('mod/sepug:download', $context)) {
            //echo "&nbsp;&nbsp;&nbsp;&nbsp;<a href=\"report.php?action=download&amp;id=$id\">$strdownload</a>";
			echo "&nbsp;&nbsp;&nbsp;&nbsp;<a href=\"report.php?action=download&amp;cmid=$cmid&amp;cid=$cid\">$strdownload</a>";
        }
        if (empty($action)) {
            $action = "summary";
        }
    } else {
        //echo "<a href=\"report.php?action=questions&amp;id=$id\">$strquestions</a>";
		echo "<a href=\"report.php?action=questions&amp;cmid=$cmid&amp;cid=$cid\">$strquestions</a>";
        //echo "&nbsp;&nbsp;&nbsp;&nbsp;<a href=\"report.php?action=students&amp;id=$id\">".get_string('participants')."</a>";
        if (has_capability('mod/sepug:download', $context)) {
            //echo "&nbsp;&nbsp;&nbsp;&nbsp;<a href=\"report.php?action=download&amp;id=$id\">$strdownload</a>";
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
		
		//$string = get_string("summarytext2", "sepug", $course->fullname).", ".get_string("summarytext3", "sepug", sepug_count_responses($cid));
		echo "<h3>".get_string("summarytext2", "sepug", $course->fullname)."</h3>";
		echo "<h3>".get_string("summarytext3", "sepug", sepug_count_responses($cid))."</h3></br></br>";
		
		// aqui habra que comprobar si el periodo del COLDP ha finalizado, en vez de que si hay alguna respuesta
		 //if (! $results = sepug_get_responses($survey->id, $currentgroup, $groupingid) ) {
		 if (! $results = sepug_get_responses($cid, $currentgroup, $groupingid) ) {
            echo $OUTPUT->notification(get_string("nobodyyet","sepug"));

        } else {
		
			/*echo "<div class='reportsummary'><a href=\"report.php?action=scales&amp;id=$id\">";
            sepug_print_graph("id=$id&amp;group=$currentgroup&amp;type=overall.png");
            echo "</a></div>";*/
			
			// Obtenemos todas las preguntas en orden
			//$questions = $DB->get_records_list("sepug_questions", "id", explode(',', $survey->questions));
			//$questionorder = explode(",", $survey->questions);
			$questions = $DB->get_records_list("sepug_questions", "id", explode(',', $template->questions));
			$questionorder = explode(",", $template->questions);
			
			
			// COMPROBAR QUE HAY RESULTADOS
			sepug_insert_prof_stats($cid);
			sepug_insert_global_stats();
			sepug_print_frequency_table($survey,$course);
            echo "<br/>";
			sepug_print_dimension_table($survey,$course);
			echo "<br/>";
			sepug_print_global_results_graph("cid=$cid&amp;type=question.png");
			
			
        }

        break;
		
		
		
		/*
      case "scales":
        echo $OUTPUT->heading($strscales);

        if (! $results = sepug_get_responses($survey->id, $currentgroup, $groupingid) ) {
            echo $OUTPUT->notification(get_string("nobodyyet","sepug"));

        } else {

            $questions = $DB->get_records_list("sepug_questions", "id", explode(',', $survey->questions));
            $questionorder = explode(",", $survey->questions);

            foreach ($questionorder as $key => $val) {
                $question = $questions[$val];
                if ($question->type < 0) {  // We have some virtual scales.  Just show them.
                    $virtualscales = true;
                    break;
                }
            }

            foreach ($questionorder as $key => $val) {
                $question = $questions[$val];
                if ($question->multi) {
                    if (!empty($virtualscales) && $question->type > 0) {  // Don't show non-virtual scales if virtual
                        continue;
                    }
                    echo "<p class=\"centerpara\"><a title=\"$strseemoredetail\" href=\"report.php?action=questions&amp;id=$id&amp;qid=$question->multi\">";
                    sepug_print_graph("id=$id&amp;qid=$question->id&amp;group=$currentgroup&amp;type=multiquestion.png");
                    echo "</a></p><br />";
                }
            }
        }

        break;
		*/
/*
      case "questions":

		// Segun si solo se quiere mostrar un grupo de preguntas por tema o todas..
        if ($qid) {     // just get one multi-question
            $questions = $DB->get_records_select("sepug_questions", "id in ($qid)");
            $questionorder = explode(",", $qid);

            if ($scale = $DB->get_records("sepug_questions", array("multi"=>$qid))) {
                $scale = array_pop($scale);
                echo $OUTPUT->heading("$scale->text - $strselectedquestions");
            } else {
                echo $OUTPUT->heading($strselectedquestions);
            }

        } else {        // get all top-level questions
            //$questions = $DB->get_records_list("sepug_questions", "id", explode(',',$survey->questions));
            //$questionorder = explode(",", $survey->questions);
			$questions = $DB->get_records_list("sepug_questions", "id", explode(',', $template->questions));
			$questionorder = explode(",", $template->questions);

            echo $OUTPUT->heading($strallquestions);
        }

        //if (! $results = sepug_get_responses($survey->id, $currentgroup, $groupingid) ) {
		if (! $results = sepug_get_responses($cid, $currentgroup, $groupingid) ) {
            echo $OUTPUT->notification(get_string("nobodyyet","sepug"));

        } else {

            foreach ($questionorder as $key => $val) {
                $question = $questions[$val];
                if ($question->type < 0) {  // We have some virtual scales.  DON'T show them.
                    $virtualscales = true;
                    break;
                }
            }

            foreach ($questionorder as $key => $val) {
                $question = $questions[$val];

                if ($question->type == 2) {  // no imprimimos graficos para preguntas tipo 2
                    continue;
                }
                $question->text = get_string($question->text, "sepug");

                if ($question->multi) {
                    echo "<h3>$question->text:</h3>";

                    $subquestions = $DB->get_records_list("sepug_questions", "id", explode(',', $question->multi));
                    $subquestionorder = explode(",", $question->multi);
                    foreach ($subquestionorder as $key => $val) {
                        $subquestion = $subquestions[$val];
                        if ($subquestion->type > 0) {
                            echo "<p class=\"centerpara\">";
                            //sepug_print_graph("id=$id&amp;qid=$subquestion->id&amp;group=$currentgroup&amp;type=question.png");
							sepug_print_graph("cid=$cid&amp;qid=$subquestion->id&amp;group=$currentgroup&amp;type=question.png");
                            echo "</p>";
                        }
                    }
                } else if ($question->type > 0 ) {
                    echo "<p class=\"centerpara\">";
                    //sepug_print_graph("id=$id&amp;qid=$question->id&amp;group=$currentgroup&amp;type=question.png");
					sepug_print_graph("cid=$cid&amp;qid=$question->id&amp;group=$currentgroup&amp;type=question.png");
                    echo "</p>";

                } else {
                    $table = new html_table();
                    $table->head = array($question->text);
                    $table->align = array ("left");

                    $contents = '<table cellpadding="15" width="100%">';

                    //if ($aaa = sepug_get_user_answers($survey->id, $question->id, $currentgroup, "sa.time ASC")) {
					if ($aaa = sepug_get_user_answers($cid, $question->id, $currentgroup, "sa.time ASC")) {
                        foreach ($aaa as $a) {
                            $contents .= "<tr>";
                            $contents .= '<td class="fullnamecell">'.fullname($a).'</td>';
                            $contents .= '<td valign="top">'.$a->answer1.'</td>';
                            $contents .= "</tr>";
                        }
                    }
                    $contents .= "</table>";

                    $table->data[] = array($contents);

                    echo html_writer::table($table);

                    echo $OUTPUT->spacer(array('height'=>30)); // should be done with CSS instead
                }
            }
        }

        break;
*/
			/*
      case "question":
        if (!$question = $DB->get_record("sepug_questions", array("id"=>$qid))) {
            print_error('cannotfindquestion', 'sepug');
        }
        $question->text = get_string($question->text, "sepug");

        $answers =  explode(",", get_string($question->options, "sepug"));

        echo $OUTPUT->heading("$strquestion: $question->text");


        $strname = get_string("name", "sepug");
        $strtime = get_string("time", "sepug");
        $stractual = get_string("actual", "sepug");
        $strpreferred = get_string("preferred", "sepug");
        $strdateformat = get_string("strftimedatetime");

        $table = new html_table();
        $table->head = array("", $strname, $strtime, $stractual, $strpreferred);
        $table->align = array ("left", "left", "left", "left", "right");
        $table->size = array (35, "", "", "", "");

        if ($aaa = sepug_get_user_answers($survey->id, $question->id, $currentgroup)) {
            foreach ($aaa as $a) {
                if ($a->answer1) {
                    $answer1 =  "$a->answer1 - ".$answers[$a->answer1 - 1];
                } else {
                    $answer1 =  "&nbsp;";
                }
                if ($a->answer2) {
                    $answer2 = "$a->answer2 - ".$answers[$a->answer2 - 1];
                } else {
                    $answer2 = "&nbsp;";
                }
                $table->data[] = array(
                       $OUTPUT->user_picture($a, array('courseid'=>$course->id)),
                       "<a href=\"report.php?id=$id&amp;action=student&amp;student=$a->userid\">".fullname($a)."</a>",
                       userdate($a->time),
                       $answer1, $answer2);

            }
        }

        echo html_writer::table($table);

        break;
		*/
		/*
      case "students":

         echo $OUTPUT->heading(get_string("analysisof", "sepug", get_string('participants')));

         if (! $results = sepug_get_responses($survey->id, $currentgroup, $groupingid) ) {
             echo $OUTPUT->notification(get_string("nobodyyet","sepug"));
         } else {
             sepug_print_all_responses($cm->id, $results, $course->id);
         }

        break;

      case "student":
         if (!$user = $DB->get_record("user", array("id"=>$student))) {
             print_error('invaliduserid');
         }

         echo $OUTPUT->heading(get_string("analysisof", "sepug", fullname($user)));

         if ($notes != '' and confirm_sesskey()) {
             if (sepug_get_analysis($survey->id, $user->id)) {
                 if (! sepug_update_analysis($survey->id, $user->id, $notes)) {
                     echo $OUTPUT->notification("An error occurred while saving your notes.  Sorry.");
                 } else {
                     echo $OUTPUT->notification(get_string("savednotes", "sepug"));
                 }
             } else {
                 if (! sepug_add_analysis($survey->id, $user->id, $notes)) {
                     echo $OUTPUT->notification("An error occurred while saving your notes.  Sorry.");
                 } else {
                     echo $OUTPUT->notification(get_string("savednotes", "sepug"));
                 }
             }
         }

         echo "<p <p class=\"centerpara\">";
         echo $OUTPUT->user_picture($user, array('courseid'=>$course->id));
         echo "</p>";

         $questions = $DB->get_records_list("sepug_questions", "id", explode(',', $survey->questions));
         $questionorder = explode(",", $survey->questions);

         if ($showscales) {
             // Print overall summary
             echo "<p <p class=\"centerpara\">>";
             sepug_print_graph("id=$id&amp;sid=$student&amp;type=student.png");
             echo "</p>";

             // Print scales

             foreach ($questionorder as $key => $val) {
                 $question = $questions[$val];
                 if ($question->type < 0) {  // We have some virtual scales.  Just show them.
                     $virtualscales = true;
                     break;
                 }
             }

             foreach ($questionorder as $key => $val) {
                 $question = $questions[$val];
                 if ($question->multi) {
                     if ($virtualscales && $question->type > 0) {  // Don't show non-virtual scales if virtual
                         continue;
                     }
                     echo "<p class=\"centerpara\">";
                     echo "<a title=\"$strseemoredetail\" href=\"report.php?action=questions&amp;id=$id&amp;qid=$question->multi\">";
                     sepug_print_graph("id=$id&amp;qid=$question->id&amp;sid=$student&amp;type=studentmultiquestion.png");
                     echo "</a></p><br />";
                 }
             }
         }

         // Print non-scale questions

         foreach ($questionorder as $key => $val) {
             $question = $questions[$val];
             if ($question->type == 0 or $question->type == 1) {
                 if ($answer = sepug_get_user_answer($survey->id, $question->id, $user->id)) {
                    $table = new html_table();
                     $table->head = array(get_string($question->text, "sepug"));
                     $table->align = array ("left");
                     $table->data[] = array(s($answer->answer1)); // no html here, just plain text
                     echo html_writer::table($table);
                     echo $OUTPUT->spacer(array('height'=>30));
                 }
             }
         }

         if ($rs = sepug_get_analysis($survey->id, $user->id)) {
            $notes = $rs->notes;
         } else {
            $notes = "";
         }
         echo "<hr noshade=\"noshade\" size=\"1\" />";
         echo "<div class='studentreport'>";
         echo "<form action=\"report.php\" method=\"post\">";
         echo "<h3>$strnotes:</h3>";
         echo "<blockquote>";
         echo "<textarea name=\"notes\" rows=\"10\" cols=\"60\">";
         p($notes);
         echo "</textarea><br />";
         echo "<input type=\"hidden\" name=\"action\" value=\"student\" />";
         echo "<input type=\"hidden\" name=\"sesskey\" value=\"".sesskey()."\" />";
         echo "<input type=\"hidden\" name=\"student\" value=\"$student\" />";
         echo "<input type=\"hidden\" name=\"id\" value=\"$cm->id\" />";
         echo "<input type=\"submit\" value=\"".get_string("savechanges")."\" />";
         echo "</blockquote>";
         echo "</form>";
         echo "</div>";


         break;
		*/
      case "download":
        echo $OUTPUT->heading($strdownload);

        require_capability('mod/sepug:download', $context);

        echo '<p class="centerpara">'.get_string("downloadinfo", "sepug").'</p>';

        echo $OUTPUT->container_start('reportbuttons');
        $options = array();
        $options["cid"] = $cid;
		$options["cmid"] = $cmid;
        //$options["group"] = $currentgroup;

        $options["type"] = "ods";
        echo $OUTPUT->single_button(new moodle_url("download.php", $options), get_string("downloadods"));

        $options["type"] = "xls";
        echo $OUTPUT->single_button(new moodle_url("download.php", $options), get_string("downloadexcel"));

        $options["type"] = "txt";
        echo $OUTPUT->single_button(new moodle_url("download.php", $options), get_string("downloadtext"));
        echo $OUTPUT->container_end();

        break;

    }
    echo $OUTPUT->footer();

