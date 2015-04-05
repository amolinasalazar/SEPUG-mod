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
 * This file is responsible for producing the downloadable versions of a survey
 * module.
 *
 * @package   mod-sepug
 * @copyright 2014 Alejandro Molina Salazar
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once ("../../config.php");
require_once("lib.php");

// Check that all the parameters have been provided.
//$id    = required_param('id', PARAM_INT);    // Course Module ID
$cmid = required_param('cmid', PARAM_INT);    // Course Module ID
$cid = required_param('cid', PARAM_INT);    // Course ID
$type  = optional_param('type', 'xls', PARAM_ALPHA);
$group = optional_param('group', 0, PARAM_INT);

// Ignoramos el curso 1
if($cid == 1){
	print_error('notvalidcourse','sepug');
}

/*if (! $cm = get_coursemodule_from_id('sepug', $id)) {
    print_error('invalidcoursemodule');
}*/

if (! $cm = get_coursemodule_from_id('sepug', $cmid)) {
    print_error('invalidcoursemodule');
}

/*if (! $course = $DB->get_record("course", array("id"=>$cm->course))) {
    print_error('coursemisconf');
}*/

if (! $course = $DB->get_record("course", array("id"=>$cid))) {
    print_error('coursemisconf');
}

$context = context_course::instance($course->id);
//$context = context_module::instance($cm->id);

$PAGE->set_url('/mod/sepug/download.php', array('cmid'=>$cmid, 'type'=>$type, 'group'=>$group));

require_login($course);
//require_login($course, false, $cm);
require_capability('mod/sepug:download', $context) ;

/*if (! $survey = $DB->get_record("sepug", array("id"=>$cm->instance))) {
    print_error('invalidsurveyid', 'sepug');
}*/
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

//add_to_log($course->id, "sepug", "download", $PAGE->url->out(), "$survey->id", $cm->id);

/// Check to see if groups are being used in this survey

//$groupmode = groups_get_activity_groupmode($cm);   // Groups are being used

/*if ($groupmode and $group) {
    $users = get_users_by_capability($context, 'mod/sepug:participate', '', '', '', '', $group, null, false);
} else {
    $users = get_users_by_capability($context, 'mod/sepug:participate', '', '', '', '', '', null, false);
    $group = false;
}*/

// The order of the questions
/*$order = explode(",", $survey->questions);

// Get the actual questions from the database
$questions = $DB->get_records_list("sepug_questions", "id", $order);

// Get an ordered array of questions
$orderedquestions = array();

$virtualscales = false;
foreach ($order as $qid) {
    $orderedquestions[$qid] = $questions[$qid];
    // Check if this question is using virtual scales
    if (!$virtualscales && $questions[$qid]->type < 0) {
        $virtualscales = true;
    }
}
$nestedorder = array();//will contain the subquestions attached to the main questions
$preparray = array();

foreach ($orderedquestions as $qid=>$question) {
    //$orderedquestions[$qid]->text = get_string($question->text, "survey");
    if (!empty($question->multi)) {
        $actualqids = explode(",", $questions[$qid]->multi);
        foreach ($actualqids as $subqid) {
            if (!empty($orderedquestions[$subqid]->type)) {
                $orderedquestions[$subqid]->type = $questions[$qid]->type;
            }
        }
    } else {
        $actualqids = array($qid);
    }
    if ($virtualscales && $questions[$qid]->type < 0) {
        $nestedorder[$qid] = $actualqids;
    } else if (!$virtualscales && $question->type >= 0) {
        $nestedorder[$qid] = $actualqids;
    } else {
        //todo andrew this was added by me. Is it correct?
        $nestedorder[$qid] = array();
    }
}

$reversednestedorder = array();
foreach ($nestedorder as $qid=>$subqidarray) {
    foreach ($subqidarray as $subqui) {
        $reversednestedorder[$subqui] = $qid;
    }
}

//need to get info on the sub-questions from the db and merge the arrays of questions
$allquestions = array_merge($questions, $DB->get_records_list("sepug_questions", "id", array_keys($reversednestedorder)));

//array_merge() messes up the keys so reinstate them
$questions = array();
foreach($allquestions as $question) {
    $questions[$question->id] = $question;

    //while were iterating over the questions get the question text
    $questions[$question->id]->text = get_string($questions[$question->id]->text, "sepug");
}
unset($allquestions);

// Get and collate all the results in one big array
if (! $surveyanswers = $DB->get_records("survey_answers", array("survey"=>$survey->id), "time ASC")) {
    print_error('cannotfindanswer', 'sepug');
}

$results = array();

foreach ($surveyanswers as $surveyanswer) {
    if (!$group || isset($users[$surveyanswer->userid])) {
        //$questionid = $reversednestedorder[$surveyanswer->question];
        $questionid = $surveyanswer->question;
        if (!array_key_exists($surveyanswer->userid, $results)) {
            $results[$surveyanswer->userid] = array('time'=>$surveyanswer->time);
        }
        $results[$surveyanswer->userid][$questionid]['answer1'] = $surveyanswer->answer1;
        $results[$surveyanswer->userid][$questionid]['answer2'] = $surveyanswer->answer2;
    }
}*/

// Output the file as a valid ODS spreadsheet if required
//$coursecontext = context_course::instance($course->id);
//$courseshortname = format_string($course->shortname, true, array('context' => $coursecontext));
$courseshortname = format_string($course->shortname, true, array('context' => $context));

if ($type == "ods") {
    require_once("$CFG->libdir/odslib.class.php");

	/// Calculate file name
    $downloadfilename = clean_filename(strip_tags($courseshortname.' '.format_string($survey->name, true))).'.ods';
/// Creating a workbook
    $workbook = new MoodleODSWorkbook("-");
/// Sending HTTP headers
    $workbook->send($downloadfilename);
/// Creating the first worksheet
    $myxls = $workbook->add_worksheet(textlib::substr(strip_tags(format_string($survey->name,true)), 0, 31));

	// Escribimos una etiqueta por cada grupo que haya en la asignatura
    $info_header = array("Asignatura","Grupo","Alumnos Encuestados");
	$info_data = array($course->fullname,"",(string)sepug_count_responses($cid));
    $col=0;
    for($i=0; $i<3; $i++) {
        $myxls->write_string(0,$i,$info_header[$i]);
		$myxls->write_string(1,$i,$info_data[$i]);
    }

	// Imprimimos la tabla de frecuencias
	// sepug_print_frequency_table
	$courseid = $course->id;
	
	// Obtenemos resultados de la BD
	if ($stats = $DB->get_records("sepug_prof_stats", array("courseid"=>$courseid))) {
		
		// Obtenemos las frecuencias de los resultados
		$frequencies = sepug_frequency_values($courseid);
		
		// Nos quedamos solo con las categorias globales que esten relacionadas con este curso, una por nivel de profundidad
		$main_categories = sepug_related_categories($courseid);
		
		// Preparamos los arrays para insertar las cabeceras
		$header1 = array("Tabla de Frequencias","","","","","","",get_string("curso","sepug"),"");
		$header2 = array(get_string("questions", "sepug"), get_string("scaleNS", "sepug"), get_string("scale1", "sepug"),
		get_string("scale2", "sepug"),get_string("scale3", "sepug"),get_string("scale4", "sepug"),get_string("scale5", "sepug"), 
		get_string("mean", "sepug"), get_string("deviation", "sepug"));
		foreach($main_categories as $cat){
			array_push($header1,$cat->name,"");
			array_push($header2, get_string("mean", "sepug"), get_string("deviation", "sepug"));
		}

		for($i=0; $i<count($header1); $i++) {
			$myxls->write_string(3,$i,$header1[$i]);
			$myxls->write_string(4,$i,$header2[$i]);
		}
		
		// Averiguamos si es de GRADO/POSTGRADO
		if(! $DB->get_records_sql("SELECT * FROM {course_categories} WHERE id = ".$course->category." AND path LIKE '/".$survey->catgrado."%' AND visible = 1")){
			$grado = false;
		}
		else{
			$grado = true;
		}
		
		$row = 5;
		foreach ($stats as $stat){
			
			$question = $DB->get_record("sepug_questions", array("id"=>$stat->question));
			
			$data = array(get_string("$question->shorttext","sepug"), $frequencies[$stat->question][0], $frequencies[$stat->question][1], 
			$frequencies[$stat->question][2], $frequencies[$stat->question][3], $frequencies[$stat->question][4], $frequencies[$stat->question][5], 
			$stat->mean, $stat->deviation);	
			
			foreach($main_categories as $cat){
				// Si la categoria pertenece a GRADO o POSTGRADO
				$select = "path LIKE '/".$survey->catgrado."%'";
				// NOTA: no usar record_exists_select, siempre devuelve true
				//if($DB->record_exists_select("course_categories", $select, array("visible"=>1, "id"=>$course->category))){
				if($grado){
					$gstats = $DB->get_record("sepug_global_stats",array("question"=>$stat->question, "catname"=>$cat->name, "grado"=>1));
				}
				else{
					$gstats = $DB->get_record("sepug_global_stats",array("question"=>$stat->question, "catname"=>$cat->name, "grado"=>0));
				}
				 
				$data[] = $gstats->mean;
				$data[] = $gstats->deviation;				
			}
			
			for($i=0; $i<count($data); $i++){
				$myxls->write_string($row,$i,$data[$i]);
			}
			$row++;
		}	
	}
	
	// Imprimimos la tabla de dimensiones
	// sepug_print_dimension_table
	global $DIM_PLANIF, $DIM_COMP_DOC, $DIM_EV_APREND, $DIM_AMB;
	
	// Preparamos los arrays para insertar las cabeceras
    $header1  = array("Tabla de Resultados segun Dimension",get_string("curso","sepug"),"",get_string("universidad","sepug"),"");
	$header2 = array(get_string("dimension","sepug"),get_string("mean","sepug"),get_string("deviation","sepug"),
	get_string("mean","sepug"),get_string("deviation","sepug"));
	
	$row++; //separamos ambas tablas
	for($i=0; $i<count($header1); $i++) {
		$myxls->write_string($row,$i,$header1[$i]);
		$myxls->write_string($row+1,$i,$header2[$i]);
	}
	
	// Obtenemos la categoria padre a nivel 1 de profundidad del curso
	if ($cat_course = $DB->get_record("course_categories", array("id"=>$course->category))){
		// Si el parent es 0, la categoria ya esta a nivel 1
		if($cat_course->parent != 0){
			$parent_id = $cat_course->path[1];
			$parent_cat = $DB->get_record("course_categories", array("id"=>$parent_id),"name");
		}
		else{
			$parent_cat = $cat_course;
		}
	}

	if($grado){
		$gstats = $DB->get_records("sepug_global_stats",array("catname"=>$parent_cat->name,"grado"=>1));
	}
	else{
		$gstats = $DB->get_records("sepug_global_stats",array("catname"=>$parent_cat->name,"grado"=>0));
	}
	
	if (!$stats || !$gstats) {
		return 1;
	}
	else{
		
		// Por cada dimension, obtenemos la media y desviacion
		$mean_array = array();
		$deviation_array = array();
		$gmean_array = array();
		$gdeviation_array = array();
		
		// Valores por curso
		list($mean_array[],$deviation_array[]) = sepug_get_dim_results($stats, $DIM_PLANIF);
		list($mean_array[],$deviation_array[]) = sepug_get_dim_results($stats, $DIM_COMP_DOC);
		list($mean_array[],$deviation_array[]) = sepug_get_dim_results($stats, $DIM_EV_APREND);
		list($mean_array[],$deviation_array[]) = sepug_get_dim_results($stats, $DIM_AMB);
		
		// Valores globales
		list($gmean_array[],$gdeviation_array[]) = sepug_get_dim_results($gstats, $DIM_PLANIF);
		list($gmean_array[],$gdeviation_array[]) = sepug_get_dim_results($gstats, $DIM_COMP_DOC);
		list($gmean_array[],$gdeviation_array[]) = sepug_get_dim_results($gstats, $DIM_EV_APREND);
		list($gmean_array[],$gdeviation_array[]) = sepug_get_dim_results($gstats, $DIM_AMB);
		
		//$data = array($mean_array[],$deviation_array[],$gmean_array[],$gdeviation_array[]);
		// Escribimos los resultados en el fichero
		$row = $row+2;
		for($i=0; $i<count($mean_array); $i++) {
			$dim = "dim".($i+1);
			$data = array(get_string($dim,"sepug"),$mean_array[$i],$deviation_array[$i],$gmean_array[$i],$gdeviation_array[$i]);
			$myxls->write_string($row,0,$data[0]);
			$myxls->write_string($row,1,$data[1]);
			$myxls->write_string($row,2,$data[2]);
			$myxls->write_string($row,3,$data[3]);
			$myxls->write_string($row,4,$data[4]);
			$row++;
		}
	}
	
   
   
   
   
   
    $workbook->close();
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	/*
/// Calculate file name
    $downloadfilename = clean_filename(strip_tags($courseshortname.' '.format_string($survey->name, true))).'.ods';
/// Creating a workbook
    $workbook = new MoodleODSWorkbook("-");
/// Sending HTTP headers
    $workbook->send($downloadfilename);
/// Creating the first worksheet
    $myxls = $workbook->add_worksheet(textlib::substr(strip_tags(format_string($survey->name,true)), 0, 31));

    $header = array("surveyid","surveyname","userid","firstname","lastname","email","idnumber","time", "notes");
    $col=0;
    foreach ($header as $item) {
        $myxls->write_string(0,$col++,$item);
    }

    foreach ($nestedorder as $key => $nestedquestions) {
        foreach ($nestedquestions as $key2 => $qid) {
            $question = $questions[$qid];
            if ($question->type == "0" || $question->type == "1" || $question->type == "3" || $question->type == "-1")  {
                $myxls->write_string(0,$col++,"$question->text");
            }
            if ($question->type == "2" || $question->type == "3")  {
                $myxls->write_string(0,$col++,"$question->text (preferred)");
            }
        }
    }

//      $date = $workbook->addformat();
//      $date->set_num_format('mmmm-d-yyyy h:mm:ss AM/PM'); // ?? adjust the settings to reflect the PHP format below

    $row = 0;
    foreach ($results as $user => $rest) {
        $col = 0;
        $row++;
        if (! $u = $DB->get_record("user", array("id"=>$user))) {
            print_error('invaliduserid');
        }
        if ($n = $DB->get_record("sepug_analysis", array("survey"=>$survey->id, "userid"=>$user))) {
            $notes = $n->notes;
        } else {
            $notes = "No notes made";
        }
        $myxls->write_string($row,$col++,$survey->id);
        $myxls->write_string($row,$col++,strip_tags(format_text($survey->name,true)));
        $myxls->write_string($row,$col++,$user);
        $myxls->write_string($row,$col++,$u->firstname);
        $myxls->write_string($row,$col++,$u->lastname);
        $myxls->write_string($row,$col++,$u->email);
        $myxls->write_string($row,$col++,$u->idnumber);
        $myxls->write_string($row,$col++, userdate($results[$user]["time"], "%d-%b-%Y %I:%M:%S %p") );
//          $myxls->write_number($row,$col++,$results[$user]["time"],$date);
        $myxls->write_string($row,$col++,$notes);

        foreach ($nestedorder as $key => $nestedquestions) {
            foreach ($nestedquestions as $key2 => $qid) {
                $question = $questions[$qid];
                if ($question->type == "0" || $question->type == "1" || $question->type == "3" || $question->type == "-1")  {
                    $myxls->write_string($row,$col++, $results[$user][$qid]["answer1"] );
                }
                if ($question->type == "2" || $question->type == "3")  {
                    $myxls->write_string($row, $col++, $results[$user][$qid]["answer2"] );
                }
            }
        }
    }
    $workbook->close();
*/
    exit;
}

// Output the file as a valid Excel spreadsheet if required

if ($type == "xls") {
    require_once("$CFG->libdir/excellib.class.php");

/// Calculate file name
    $downloadfilename = clean_filename(strip_tags($courseshortname.' '.format_string($survey->name,true))).'.xls';
/// Creating a workbook
    $workbook = new MoodleExcelWorkbook("-");
/// Sending HTTP headers
    $workbook->send($downloadfilename);
/// Creating the first worksheet
    $myxls = $workbook->add_worksheet(textlib::substr(strip_tags(format_string($survey->name,true)), 0, 31));

    $header = array("surveyid","surveyname","userid","firstname","lastname","email","idnumber","time", "notes");
    $col=0;
    foreach ($header as $item) {
        $myxls->write_string(0,$col++,$item);
    }

    foreach ($nestedorder as $key => $nestedquestions) {
        foreach ($nestedquestions as $key2 => $qid) {
            $question = $questions[$qid];

            if ($question->type == "0" || $question->type == "1" || $question->type == "3" || $question->type == "-1")  {
                $myxls->write_string(0,$col++,"$question->text");
            }
            if ($question->type == "2" || $question->type == "3")  {
                $myxls->write_string(0,$col++,"$question->text (preferred)");
            }
        }
    }

//      $date = $workbook->addformat();
//      $date->set_num_format('mmmm-d-yyyy h:mm:ss AM/PM'); // ?? adjust the settings to reflect the PHP format below

    $row = 0;
    foreach ($results as $user => $rest) {
        $col = 0;
        $row++;
        if (! $u = $DB->get_record("user", array("id"=>$user))) {
            print_error('invaliduserid');
        }
        if ($n = $DB->get_record("sepug_analysis", array("survey"=>$survey->id, "userid"=>$user))) {
            $notes = $n->notes;
        } else {
            $notes = "No notes made";
        }
        $myxls->write_string($row,$col++,$survey->id);
        $myxls->write_string($row,$col++,strip_tags(format_text($survey->name,true)));
        $myxls->write_string($row,$col++,$user);
        $myxls->write_string($row,$col++,$u->firstname);
        $myxls->write_string($row,$col++,$u->lastname);
        $myxls->write_string($row,$col++,$u->email);
        $myxls->write_string($row,$col++,$u->idnumber);
        $myxls->write_string($row,$col++, userdate($results[$user]["time"], "%d-%b-%Y %I:%M:%S %p") );
//          $myxls->write_number($row,$col++,$results[$user]["time"],$date);
        $myxls->write_string($row,$col++,$notes);

        foreach ($nestedorder as $key => $nestedquestions) {
            foreach ($nestedquestions as $key2 => $qid) {
                $question = $questions[$qid];
                if (($question->type == "0" || $question->type == "1" || $question->type == "3" || $question->type == "-1")
                    && array_key_exists($qid, $results[$user]) ){
                $myxls->write_string($row,$col++, $results[$user][$qid]["answer1"] );
            }
                if (($question->type == "2" || $question->type == "3")
                    && array_key_exists($qid, $results[$user]) ){
                $myxls->write_string($row, $col++, $results[$user][$qid]["answer2"] );
            }
        }
    }
    }
    $workbook->close();

    exit;
}

// Otherwise, return the text file.

// Print header to force download

header("Content-Type: application/download\n");

$downloadfilename = clean_filename(strip_tags($courseshortname.' '.format_string($survey->name,true)));
header("Content-Disposition: attachment; filename=\"$downloadfilename.txt\"");

// Print names of all the fields

echo "surveyid    surveyname    userid    firstname    lastname    email    idnumber    time    ";

foreach ($nestedorder as $key => $nestedquestions) {
    foreach ($nestedquestions as $key2 => $qid) {
        $question = $questions[$qid];
    if ($question->type == "0" || $question->type == "1" || $question->type == "3" || $question->type == "-1")  {
        echo "$question->text    ";
    }
    if ($question->type == "2" || $question->type == "3")  {
         echo "$question->text (preferred)    ";
    }
}
}
echo "\n";

// Print all the lines of data.
foreach ($results as $user => $rest) {
    if (! $u = $DB->get_record("user", array("id"=>$user))) {
        print_error('invaliduserid');
    }
    echo $survey->id."\t";
    echo strip_tags(format_string($survey->name,true))."\t";
    echo $user."\t";
    echo $u->firstname."\t";
    echo $u->lastname."\t";
    echo $u->email."\t";
    echo $u->idnumber."\t";
    echo userdate($results[$user]["time"], "%d-%b-%Y %I:%M:%S %p")."\t";

    foreach ($nestedorder as $key => $nestedquestions) {
        foreach ($nestedquestions as $key2 => $qid) {
            $question = $questions[$qid];

            if ($question->type == "0" || $question->type == "1" || $question->type == "3" || $question->type == "-1")  {
                echo $results[$user][$qid]["answer1"]."    ";
            }
            if ($question->type == "2" || $question->type == "3")  {
                echo $results[$user][$qid]["answer2"]."    ";
            }
        }
    }
    echo "\n";
}

exit;
