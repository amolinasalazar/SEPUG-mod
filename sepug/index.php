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

    require_once("../../config.php");
    require_once("lib.php");

    $id = required_param('id', PARAM_INT);    // Course Module ID

    $PAGE->set_url('/mod/sepug/index.php', array('id'=>$id));

    if (!$course = $DB->get_record('course', array('id'=>$id))) {
        print_error('invalidcourseid');
    }

    require_course_login($course);
    $PAGE->set_pagelayout('incourse');

    add_to_log($course->id, "sepug", "view all", "index.php?id=$course->id", "");

    $strsurveys = get_string("modulenameplural", "sepug");
    $strname = get_string("name");
    $strstatus = get_string("status");
    $strdone  = get_string("done", "sepug");
    $strnotdone  = get_string("notdone", "sepug");

    $PAGE->navbar->add($strsurveys);
    $PAGE->set_title($strsurveys);
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();

    if (! $surveys = get_all_instances_in_course("sepug", $course)) {
        notice(get_string('thereareno', 'moodle', $strsurveys), "../../course/view.php?id=$course->id");
    }

    $usesections = course_format_uses_sections($course->format);

    $table = new html_table();
    $table->width = '100%';

    if ($usesections) {
        $strsectionname = get_string('sectionname', 'format_'.$course->format);
        $table->head  = array ($strsectionname, $strname, $strstatus);
    } else {
        $table->head  = array ($strname, $strstatus);
    }

    $currentsection = '';

    foreach ($surveys as $survey) {
        if (isloggedin() and sepug_already_done($survey->id, $USER->id)) {
            $ss = $strdone;
        } else {
            $ss = $strnotdone;
        }
        $printsection = "";
        if ($usesections) {
            if ($survey->section !== $currentsection) {
                if ($survey->section) {
                    $printsection = get_section_name($course, $survey->section);
                }
                if ($currentsection !== "") {
                    $table->data[] = 'hr';
                }
                $currentsection = $survey->section;
            }
        }
        //Calculate the href
        if (!$survey->visible) {
            //Show dimmed if the mod is hidden
            $tt_href = "<a class=\"dimmed\" href=\"view.php?id=$survey->coursemodule\">".format_string($survey->name,true)."</a>";
        } else {
            //Show normal if the mod is visible
            $tt_href = "<a href=\"view.php?id=$survey->coursemodule\">".format_string($survey->name,true)."</a>";
        }

        if ($usesections) {
            $table->data[] = array ($printsection, $tt_href, "<a href=\"view.php?id=$survey->coursemodule\">$ss</a>");
        } else {
            $table->data[] = array ($tt_href, "<a href=\"view.php?id=$survey->coursemodule\">$ss</a>");
        }
    }

    echo "<br />";
    echo html_writer::table($table);
    echo $OUTPUT->footer();


