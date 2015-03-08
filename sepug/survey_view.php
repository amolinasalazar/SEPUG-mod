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

/**
 * This file is responsible for displaying the survey
 *
 * @package   mod-sepug
 * @copyright 2014 Alejandro Molina Salazar
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 
    require_once("../../config.php");
    require_once("lib.php");

    $cmid = required_param('cmid', PARAM_INT);    // Course Module ID
	$cid = required_param('cid', PARAM_INT);    // Course ID

    if (! $cm = get_coursemodule_from_id('sepug', $cmid)) {
        print_error('invalidcoursemodule');
    }

    /*if (! $course = $DB->get_record("course", array("id"=>$cm->course))) {
        print_error('coursemisconf');
    }*/
	
	
	if (! $course = $DB->get_record("course", array("id"=>$cid))) {
        print_error('coursemisconf');
    }
	
    $PAGE->set_url('/mod/sepug/survey_view.php', array('cmid'=>$cmid, 'cid'=>$cid));
    require_login($course);
    //$context = context_module::instance($cm->id);
	$context = context_course::instance($course->id);

    require_capability('mod/sepug:participate', $context);

    /*if (! $survey = $DB->get_record("sepug", array("course"=>$cid))) {
        print_error('invalidsurveyid', 'sepug');
    }*/
	if (! $survey = $DB->get_record("sepug", array("id"=>$cm->instance))) {
        print_error('invalidsurveyid', 'sepug');
    }
	
    $trimmedintro = trim($survey->intro);
	$tmpid = sepug_get_template($cid);
    if (empty($trimmedintro)) {
        //$tempo = $DB->get_field("sepug", "intro", array("id"=>$survey->template));
		$tempo = $DB->get_field("sepug", "intro", array("id"=>$tmpid));
        $survey->intro = get_string($tempo, "sepug");
    }
	
	// Obtenemos plantilla segun el curso en el que este (de momento siempre 1)
    if (! $template = $DB->get_record("sepug", array("id"=>$tmpid))) {
        print_error('invalidtmptid', 'sepug');
    }

// Update 'viewed' state if required by completion system
/*require_once($CFG->libdir . '/completionlib.php');
$completion = new completion_info($course);
$completion->set_module_viewed($cm);*/

    $showscales = ($template->name != 'ciqname');

    $strsurvey = get_string("modulename", "sepug");
    $PAGE->set_title($survey->name);
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();

/// Check to see if groups are being used in this survey
    if ($groupmode = groups_get_activity_groupmode($cm)) {   // Groups are being used
        $currentgroup = groups_get_activity_group($cm);
    } else {
        $currentgroup = 0;
    }
    $groupingid = $cm->groupingid;

    if (has_capability('mod/sepug:readresponses', $context) or ($groupmode == VISIBLEGROUPS)) {
        $currentgroup = 0;
    }

	//$currentgroup = 0;
    if (has_capability('mod/sepug:readresponses', $context)) {
        $numusers = sepug_count_responses($survey->id, $currentgroup, $groupingid);
        echo "<div class=\"reportlink\"><a href=\"report.php?id=$cm->id\">". // PENDIENTE DE CAMBIAR
              get_string("viewsurveyresponses", "sepug", $numusers)."</a></div>";
    } else if (!$cm->visible) {
        notice(get_string("activityiscurrentlyhidden"));
    }

    if (!is_enrolled($context)) {
        echo $OUTPUT->notification(get_string("guestsnotallowed", "sepug"));
    }


//  Check the survey hasn't already been filled out.

    //if (sepug_already_done($survey->id, $USER->id)) {
	if (sepug_already_done($cid, $USER->id)) {

        //add_to_log($course->id, "sepug", "view graph", "view.php?id=$cm->id", $survey->id, $cm->id);
        //$numusers = survey_count_responses($survey->id, $currentgroup, $groupingid);
		$numusers = survey_count_responses($cid, $currentgroup, $groupingid);

        if ($showscales) {
            echo $OUTPUT->heading(get_string("surveycompleted", "sepug"));
            echo $OUTPUT->heading(get_string("peoplecompleted", "sepug", $numusers));
            echo '<div class="resultgraph">';
            //sepug_print_graph("id=$cm->id&amp;sid=$USER->id&amp;group=$currentgroup&amp;type=student.png");
			sepug_print_graph("cid=$cid&amp;sid=$USER->id&amp;group=$currentgroup&amp;type=student.png");
            echo '</div>';

        } else {

            echo $OUTPUT->box(format_module_intro('sepug', $survey, $cm->id), 'generalbox', 'intro');
            echo $OUTPUT->spacer(array('height'=>30, 'width'=>1), true);  // should be done with CSS instead

            $questions = $DB->get_records_list("sepug_questions", "id", explode(',', $survey->questions));
            $questionorder = explode(",", $survey->questions);
            foreach ($questionorder as $key => $val) {
                $question = $questions[$val];
                if ($question->type == 0 or $question->type == 1) {
                    if ($answer = sepug_get_user_answer($survey->id, $question->id, $USER->id)) {
                        $table = new html_table();
                        $table->head = array(get_string($question->text, "sepug"));
                        $table->align = array ("left");
                        $table->data[] = array(s($answer->answer1));//no html here, just plain text
                        echo html_writer::table($table);
                        echo $OUTPUT->spacer(array('height'=>30, 'width'=>1), true);
                    }
                }
            }
        }

        echo $OUTPUT->footer();
        exit;
    }

//  Start the survey form
    //add_to_log($course->id, "sepug", "view form", "view.php?id=$cm->id", $survey->id, $cm->id);

    echo "<form method=\"post\" action=\"save.php\" id=\"surveyform\">";
    echo '<div>';
    echo "<input type=\"hidden\" name=\"cmid\" value=\"$cmid\" />";
	echo "<input type=\"hidden\" name=\"cid\" value=\"$cid\" />";
    echo "<input type=\"hidden\" name=\"sesskey\" value=\"".sesskey()."\" />";

    echo $OUTPUT->box(format_module_intro('sepug', $survey, $cm->id), 'generalbox boxaligncenter bowidthnormal', 'intro');
    echo '<div>'. get_string('allquestionrequireanswer', 'sepug'). '</div>';

// Get all the major questions and their proper order
    /*if (! $questions = $DB->get_records_list("sepug_questions", "id", explode(',', $survey->questions))) {
        print_error('cannotfindquestion', 'sepug');
    }*/
	// Obtenemos las preguntas de las plantillas y no de la instanciacion del survey
	if (! $questions = $DB->get_records_list("sepug_questions", "id", explode(',', $template->questions))) {
        print_error('cannotfindquestion', 'sepug');
    }
    //$questionorder = explode( ",", $survey->questions);
	$questionorder = explode( ",", $template->questions);

// Cycle through all the questions in order and print them

    global $qnum;  //TODO: ugly globals hack for survey_print_*()
    global $checklist; //TODO: ugly globals hack for survey_print_*()
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

	// Llamada al modulo JS que comprueba si todas las preguntas estan contestadas
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


