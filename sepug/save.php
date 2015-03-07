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
 * This file is responsible for saving the results of a users survey and displaying
 * the final message.
 *
 * @package   mod-sepug
 * @copyright 2014 Alejandro Molina Salazar
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

    require_once('../../config.php');
    require_once('lib.php');


// Make sure this is a legitimate posting

    if (!$formdata = data_submitted() or !confirm_sesskey()) {
        print_error('cannotcallscript');
    }

    $id = required_param('id', PARAM_INT);    // Course Module ID

    if (! $cm = get_coursemodule_from_id('sepug', $id)) {
        print_error('invalidcoursemodule');
    }

    if (! $course = $DB->get_record("course", array("id"=>$cm->course))) {
        print_error('coursemisconf');
    }

    $PAGE->set_url('/mod/sepug/save.php', array('id'=>$id));
    require_login($course, false, $cm);

    $context = context_module::instance($cm->id);
    require_capability('mod/sepug:participate', $context);

    if (! $survey = $DB->get_record("sepug", array("id"=>$cm->instance))) {
        print_error('invalidsurveyid', 'sepug');
    }

    add_to_log($course->id, "sepug", "submit", "view.php?id=$cm->id", "$survey->id", "$cm->id");

    $strsurveysaved = get_string('surveysaved', 'sepug');

    $PAGE->set_title($strsurveysaved);
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();

    if (sepug_already_done($survey->id, $USER->id)) {
        notice(get_string("alreadysubmitted", "sepug"), $_SERVER["HTTP_REFERER"]);
        exit;
    }


// Sort through the data and arrange it
// This is necessary because some of the questions
// may have two answers, eg Question 1 -> 1 and P1

    $answers = array();

    foreach ($formdata as $key => $val) {
        if ($key <> "userid" && $key <> "id") {
            if ( substr($key,0,1) == "q") {
                $key = clean_param(substr($key,1), PARAM_ALPHANUM);   // keep everything but the 'q', number or Pnumber
            }
            if ( substr($key,0,1) == "P") {
                $realkey = (int) substr($key,1);
                $answers[$realkey][1] = $val;
            } else {
                $answers[$key][0] = $val;
            }
        }
    }


// Now store the data.

    $timenow = time();
    foreach ($answers as $key => $val) {
        if ($key != 'sesskey') {
            $newdata = new stdClass();
            $newdata->time = $timenow;
            $newdata->userid = $USER->id;
            $newdata->survey = $survey->id;
            $newdata->question = $key;
            if (!empty($val[0])) {
                $newdata->answer1 = $val[0];
            } else {
                $newdata->answer1 = "";
            }
            if (!empty($val[1])) {
                $newdata->answer2 = $val[1];
            } else {
                $newdata->answer2 = "";
            }

            $DB->insert_record("sepug_answers", $newdata);
        }
    }

// Print the page and finish up.

    notice(get_string("thanksforanswers","sepug", $USER->firstname), "$CFG->wwwroot/course/view.php?id=$course->id");

    exit;



