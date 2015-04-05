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
 * @package   mod-sepug
 * @copyright 2014 Alejandro Molina Salazar
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Graph size
 * @global int $SURVEY_GHEIGHT
 */
global $SEPUG_GHEIGHT;
$SEPUG_GHEIGHT = 600;//600
/**
 * Graph size
 * @global int $SURVEY_GWIDTH
 */
global $SEPUG_GWIDTH;
$SEPUG_GWIDTH  = 1100;//800
/**
 * Question Type
 * @global array $SURVEY_QTYPE
 */
global $SURVEY_QTYPE;
$SURVEY_QTYPE = array (
        "-3" => "Virtual Actual and Preferred",
        "-2" => "Virtual Preferred",
        "-1" => "Virtual Actual",
         "0" => "Text",
         "1" => "Actual",
         "2" => "Preferred",
         "3" => "Actual and Preferred",
        );


define("SURVEY_COLDP15", "1");
//define("SURVEY_COLLES_ACTUAL",           "1");
//define("SURVEY_COLLES_PREFERRED",        "2");
//define("SURVEY_COLLES_PREFERRED_ACTUAL", "3");
//define("SURVEY_ATTLS",                   "4");
//define("SURVEY_CIQ",                     "5");

// Preguntas segun dimension
global $DIM_PLANIF, $DIM_COMP_DOC, $DIM_EV_APREND, $DIM_AMB, $CAT_NAMES;
$DIM_PLANIF = array(1,2,4,5,18);
$DIM_COMP_DOC = array(6,8,9,10,11,12,14);
$DIM_EV_APREND = array(3,16,17);
$DIM_AMB = array(13,15);
$CAT_NAMES = array("ciclo lectivo","universidad","titulaciones",0);


// STANDARD FUNCTIONS ////////////////////////////////////////////////////////
/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @global object
 * @param object $survey
 * @return int|bool
 */
function sepug_add_instance($survey) {
    global $DB;

    /*if (!$template = $DB->get_record("sepug", array("id"=>$survey->template))) {
        return 0;
    }*/

    //$survey->questions    = $template->questions;
    $survey->timecreated  = time();
    $survey->timemodified = $survey->timecreated;

    return $DB->insert_record("sepug", $survey);

}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @global object
 * @param object $survey
 * @return bool
 */
function sepug_update_instance($survey) {
    global $DB;

    /*if (!$template = $DB->get_record("sepug", array("id"=>$survey->template))) {
        return 0;
    }*/

    $survey->id           = $survey->instance;
    //$survey->questions    = $template->questions;
    $survey->timemodified = time();

    return $DB->update_record("sepug", $survey);
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @global object
 * @param int $id
 * @return bool
 */
function sepug_delete_instance($id) {
    global $DB;

    if (! $survey = $DB->get_record("sepug", array("id"=>$id))) {
        return false;
    }

    $result = true;

    if (! $DB->delete_records("sepug_analysis", array("survey"=>$survey->id))) {
        $result = false;
    }

    if (! $DB->delete_records("sepug_answers", array("survey"=>$survey->id))) {
        $result = false;
    }

    if (! $DB->delete_records("sepug", array("id"=>$survey->id))) {
        $result = false;
    }

    return $result;
}

/**
 * @global object
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $survey
 * @return $result
 */
function sepug_user_outline($course, $user, $mod, $survey) {
    global $DB;

    if ($answers = $DB->get_records("sepug_answers", array('survey'=>$survey->id, 'userid'=>$user->id))) {
        $lastanswer = array_pop($answers);

        $result = new stdClass();
        $result->info = get_string("done", "sepug");
        $result->time = $lastanswer->time;
        return $result;
    }
    return NULL;
}

/**
 * @global stdObject
 * @global object
 * @uses SURVEY_CIQ
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $survey
 */
function sepug_user_complete($course, $user, $mod, $survey) {
    global $CFG, $DB, $OUTPUT;

    if (survey_already_done($survey->id, $user->id)) {
        if ($survey->template == SURVEY_CIQ) { // print out answers for critical incidents
            $table = new html_table();
            $table->align = array("left", "left");

            $questions = $DB->get_records_list("sepug_questions", "id", explode(',', $survey->questions));
            $questionorder = explode(",", $survey->questions);

            foreach ($questionorder as $key=>$val) {
                $question = $questions[$val];
                $questiontext = get_string($question->shorttext, "sepug");

                if ($answer = sepug_get_user_answer($survey->id, $question->id, $user->id)) {
                    $answertext = "$answer->answer1";
                } else {
                    $answertext = "No answer";
                }
                $table->data[] = array("<b>$questiontext</b>", $answertext);
            }
            echo html_writer::table($table);

        } else {

            sepug_print_graph("id=$mod->id&amp;sid=$user->id&amp;type=student.png");
        }

    } else {
        print_string("notdone", "sepug");
    }
}

/**
 * @global stdClass
 * @global object
 * @param object $course
 * @param mixed $viewfullnames
 * @param int $timestamp
 * @return bool
 */
function sepug_print_recent_activity($course, $viewfullnames, $timestart) {
    global $CFG, $DB, $OUTPUT;

    $modinfo = get_fast_modinfo($course);
    $ids = array();
    foreach ($modinfo->cms as $cm) {
        if ($cm->modname != 'sepug') {
            continue;
        }
        if (!$cm->uservisible) {
            continue;
        }
        $ids[$cm->instance] = $cm->instance;
    }

    if (!$ids) {
        return false;
    }

    $slist = implode(',', $ids); // there should not be hundreds of glossaries in one course, right?

    $rs = $DB->get_recordset_sql("SELECT sa.userid, sa.survey, MAX(sa.time) AS time,
                                         u.firstname, u.lastname, u.email, u.picture
                                    FROM {sepug_answers} sa
                                    JOIN {user} u ON u.id = sa.userid
                                   WHERE sa.survey IN ($slist) AND sa.time > ?
                                GROUP BY sa.userid, sa.survey, u.firstname, u.lastname, u.email, u.picture
                                ORDER BY time ASC", array($timestart));
    if (!$rs->valid()) {
        $rs->close(); // Not going to iterate (but exit), close rs
        return false;
    }

    $surveys = array();

    foreach ($rs as $survey) {
        $cm = $modinfo->instances['survey'][$survey->survey];
        $survey->name = $cm->name;
        $survey->cmid = $cm->id;
        $surveys[] = $survey;
    }
    $rs->close();

    if (!$surveys) {
        return false;
    }

    echo $OUTPUT->heading(get_string('newsurveyresponses', 'sepug').':');
    foreach ($surveys as $survey) {
        $url = $CFG->wwwroot.'/mod/sepug/view.php?id='.$survey->cmid;
        print_recent_activity_note($survey->time, $survey, $survey->name, $url, false, $viewfullnames);
    }

    return true;
}

// SQL FUNCTIONS ////////////////////////////////////////////////////////

/**
 * @global object
 * @param sting $log
 * @return array
 */
function sepug_log_info($log) {
    global $DB;
    return $DB->get_record_sql("SELECT s.name, u.firstname, u.lastname, u.picture
                                  FROM {sepug} s, {user} u
                                 WHERE s.id = ?  AND u.id = ?", array($log->info, $log->userid));
}

/**
 * @global object
 * @param int $surveyid
 * @param int $groupid
 * @param int $groupingid
 * @return array
 */
/*function sepug_get_responses($surveyid, $groupid, $groupingid) {
    global $DB;

    $params = array('surveyid'=>$surveyid, 'groupid'=>$groupid, 'groupingid'=>$groupingid);

    if ($groupid) {
        $groupsjoin = "JOIN {groups_members} gm ON u.id = gm.userid AND gm.groupid = :groupid ";

    } else if ($groupingid) {
        $groupsjoin = "JOIN {groups_members} gm ON u.id = gm.userid
                       JOIN {groupings_groups} gg ON gm.groupid = gg.groupid AND gg.groupingid = :groupingid ";
    } else {
        $groupsjoin = "";
    }

    $userfields = user_picture::fields('u');
    return $DB->get_records_sql("SELECT $userfields, MAX(a.time) as time
                                   FROM {sepug_answers} a
                                   JOIN {user} u ON a.userid = u.id
                            $groupsjoin
                                  WHERE a.survey = :surveyid
                               GROUP BY $userfields
                               ORDER BY time ASC", $params);
}*/

/** SEPUG FUNCTION
 * @global object
 * @param int $courseid
 * @param int $groupid
 * @param int $groupingid
 * @return array
 */
function sepug_get_responses($courseid, $groupid, $groupingid) {
    global $DB;

    $params = array('courseid'=>$courseid, 'groupid'=>$groupid, 'groupingid'=>$groupingid);

    if ($groupid) {
        $groupsjoin = "JOIN {groups_members} gm ON u.id = gm.userid AND gm.groupid = :groupid ";

    } else if ($groupingid) {
        $groupsjoin = "JOIN {groups_members} gm ON u.id = gm.userid
                       JOIN {groupings_groups} gg ON gm.groupid = gg.groupid AND gg.groupingid = :groupingid ";
    } else {
        $groupsjoin = "";
    }

    $userfields = user_picture::fields('u');
    return $DB->get_records_sql("SELECT $userfields, MAX(a.time) as time
                                   FROM {sepug_answers} a
                                   JOIN {user} u ON a.userid = u.id
                            $groupsjoin
                                  WHERE a.courseid = :courseid
                               GROUP BY $userfields
                               ORDER BY time ASC", $params);
}

/**
 * @global object
 * @param int $survey
 * @param int $user
 * @return array
 */
function sepug_get_analysis($survey, $user) {
    global $DB;

    return $DB->get_record_sql("SELECT notes
                                  FROM {sepug_analysis}
                                 WHERE survey=? AND userid=?", array($survey, $user));
}

/**
 * @global object
 * @param int $survey
 * @param int $user
 * @param string $notes
 */
function sepug_update_analysis($survey, $user, $notes) {
    global $DB;

    return $DB->execute("UPDATE {sepug_analysis}
                            SET notes=?
                          WHERE survey=?
                            AND userid=?", array($notes, $survey, $user));
}

/**
 * @global object
 * @param int $surveyid
 * @param int $groupid
 * @param string $sort
 * @return array
 */
function sepug_get_user_answers($surveyid, $questionid, $groupid, $sort="sa.answer1,sa.answer2 ASC") {
    global $DB;

    $params = array('surveyid'=>$surveyid, 'questionid'=>$questionid);

    if ($groupid) {
        $groupfrom = ', {groups_members} gm';
        $groupsql  = 'AND gm.groupid = :groupid AND u.id = gm.userid';
        $params['groupid'] = $groupid;
    } else {
        $groupfrom = '';
        $groupsql  = '';
    }

    $userfields = user_picture::fields('u');
    return $DB->get_records_sql("SELECT sa.*, $userfields
                                   FROM {sepug_answers} sa,  {user} u $groupfrom
                                  WHERE sa.survey = :surveyid
                                        AND sa.question = :questionid
                                        AND u.id = sa.userid $groupsql
                               ORDER BY $sort", $params);
}

/**
 * @global object
 * @param int $surveyid
 * @param int $questionid
 * @param int $userid
 * @return array
 */
function sepug_get_user_answer($surveyid, $questionid, $userid) {
    global $DB;

    return $DB->get_record_sql("SELECT sa.*
                                  FROM {sepug_answers} sa
                                 WHERE sa.survey = ?
                                       AND sa.question = ?
                                       AND sa.userid = ?", array($surveyid, $questionid, $userid));
}

// MODULE FUNCTIONS ////////////////////////////////////////////////////////
/**
 * @global object
 * @param int $survey
 * @param int $user
 * @param string $notes
 * @return bool|int
 */
function sepug_add_analysis($survey, $user, $notes) {
    global $DB;

    $record = new stdClass();
    $record->survey = $survey;
    $record->userid = $user;
    $record->notes = $notes;

    return $DB->insert_record("sepug_analysis", $record, false);
}

/**
 * @global object
 * @param int $survey
 * @param int $user
 * @return bool
 */
/*function sepug_already_done($survey, $user) {
    global $DB;

    return $DB->record_exists("sepug_answers", array("survey"=>$survey, "userid"=>$user));
}*/

/** SEPUG FUNCTION
 * @global object
 * @param int $survey
 * @param int $user
 * @return bool
 */
function sepug_already_done($courseid, $user) {
    global $DB;

    return $DB->record_exists("sepug_answers", array("courseid"=>$courseid, "userid"=>$user));
}
/**
 * @param int $surveyid
 * @param int $groupid
 * @param int $groupingid
 * @return int
 */
/*function sepug_count_responses($surveyid, $groupid, $groupingid) {
    if ($responses = sepug_get_responses($surveyid, $groupid, $groupingid)) {
        return count($responses);
    } else {
        return 0;
    }
}*/

/** SEPUG FUNC
 * @param int $courseid
 * @param int $groupid
 * @param int $groupingid
 * @return int
 */
function sepug_count_responses($courseid, $groupid=false, $groupingid=false) {
    if ($responses = sepug_get_responses($courseid, $groupid, $groupingid)) {
        return count($responses);
    } else {
        return 0;
    }
}
/** SEPUG FUNCTION
 * @param array $numlist
 */
function sepug_mean($numlist, $numvalues) {
	//return array_sum(($numlist) / count($numlist));
	if($numvalues!=0)
		return (array_sum($numlist) / $numvalues);
	else
		return 0;
}

/** SEPUG FUNCTION
 * @param array $frequencies
 */
function sepug_deviation($frequencies, $mean) {
	
	$all_results = array();
	
	foreach($frequencies as $key=>$value){
		for($i=0; $i<$value && $key!=0; $i++){
			$all_results[] = pow(($key-$mean),2);
		}
		//$x = pow(($x-$mean),2);
	}
	return sqrt(array_sum($all_results)/count($all_results)); 
}

/** SEPUG FUNCTION
 * @param array $array_freq
 */
function sepug_freq_sum_values($array_freq) {
	$sum = 0;
	//$array = array();
	/*foreach $array_freq as $key=>$freq{
		$sum += $key*$freq;
	}*/
	
	foreach ($array_freq as $key=>$freq){
		$array_freq[$key] = $key*$freq;
	}
	
	return $array_freq;
}

/** SEPUG FUNCTION
 * @param array $matrix_freq
 */
function sepug_freq_sum_all_values($matrix_freq) {
	$sum = 0;
	
	foreach ($matrix_freq as $array_freq){
		$sum += sepug_freq_sum_values($array_freq);
	}
	
	return $sum;
}

/** SEPUG FUNCTION
 * @param int $courseid
 */
function sepug_get_template($courseid) {
	global $CFG, $DB;
	require_once($CFG->dirroot.'/lib/coursecatlib.php');
	
	//Obtenemos las categoria padre de postgrado
	$survey = $DB->get_record("sepug", array("sepuginstance"=>"1"), "catposgrado");
	
	// Detectamos si el curso es de GRADO o de POSGRADO para saber que plantilla usar
	// Buscamos si es de postgrado (menos busqueda), si no lo es, debe ser de grado ya que
	// en view.php solo mostramos los cursos que pertenezcan a uno de los dos grupos
	$cat_class = coursecat::get($survey->catposgrado);
	$courses_cat = $cat_class->get_courses(array('recursive' => 1));
	$posgradocourse = false;
	if(!empty($courses_cat)){
		foreach($courses_cat as $course_cat){
			if($courseid == $course_cat->id){
				$posgradocourse = true;
			}
		}
	}
	
	if($posgradocourse){
		$tmpid = $DB->get_record("sepug",array("sepuginstance"=>0, "catposgrado"=>1),"id");
	}
	else{
		$tmpid = $DB->get_record("sepug",array("sepuginstance"=>0, "catgrado"=>1),"id");
	}
	
	return $tmpid->id;
}

/**
 * @param int $cmid
 * @param array $results
 * @param int $courseid
 */
function sepug_print_all_responses($cmid, $results, $courseid) {
    global $OUTPUT;
    $table = new html_table();
    $table->head  = array ("", get_string("name"),  get_string("time"));
    $table->align = array ("", "left", "left");
    $table->size = array (35, "", "" );

    foreach ($results as $a) {
        $table->data[] = array($OUTPUT->user_picture($a, array('courseid'=>$courseid)),
               html_writer::link("report.php?action=student&student=$a->id&id=$cmid", fullname($a)),
               userdate($a->time));
    }

    echo html_writer::table($table);
}

/** SEPUG FUNCTION
 * @param int $courseid
 * @return array Devuelve array multidimensional con key=>question id y valor un array con las frecuencias de esa pregunta
 */
function sepug_frequency_values($courseid) {
	global $DB;
	
	$freq_matrix = array();
	
	// Primero obtenemos el template
	$tmpid = sepug_get_template($courseid);
	if (! $template = $DB->get_record("sepug", array("id"=>$tmpid))) {
        print_error('invalidtmptid', 'sepug');
    }
	
	// Y de el, obtenemos las preguntas y su orden
	$questions = $DB->get_records_list("sepug_questions", "id", explode(',', $template->questions));
	$questionorder = explode(",", $template->questions);

	// Recorremos todas las preguntas..
	foreach ($questionorder as $key => $val) {
		$question = $questions[$val];

		// Si son del tipo < 0, las ignoramos
		if ($question->type < 0) {  // We have some virtual scales.  DON'T show them.
			continue;
		}
		$question->text = get_string($question->text, "sepug");

		// Tipo multi, obtenemos cada una de ellas
		if ($question->multi) {

			$subquestions = $DB->get_records_list("sepug_questions", "id", explode(',', $question->multi));
			$subquestionorder = explode(",", $question->multi);
			foreach ($subquestionorder as $key => $val) {
				$subquestion = $subquestions[$val];
				if ($subquestion->type > 0) {
				
					// Por cada subpregunta, calculamos la frecuencia de los resultados..
					
					// Obtenemos las opciones disponibles para cada pregunta y preparamos un array para almacenar las frecuencias
					$subquestion->options = get_string($subquestion->options, "sepug");
				    $options = explode(",",$subquestion->options);

					while (list($key,) = each($options)) {
					   $buckets1[$key] = 0;
					   //$buckets2[$key] = 0;
					}
					
					
					// Obtenemos las respuestas para todos los usuarios que hallan contestado una determinada pregunta y un determinado cuestionario
					//if ($aaa = $DB->get_records('sepug_answers', array('survey'=>$cm->instance, 'question'=>$subquestion->id))) {
					if ($aaa = $DB->get_records('sepug_answers', array('courseid'=>$courseid, 'question'=>$subquestion->id))) {
					    foreach ($aaa as $aa) {
						    //if (!$group or isset($users[$aa->userid])) {
							   if ($a1 = $aa->answer1) {
								   $buckets1[$a1 - 1]++;
							   }  
							   /*if ($a2 = $aa->answer2) {
								   $buckets2[$a2 - 1]++;
							   }*/
						    //}
					    }
						
						// Almacenamos en una matriz todos los resultados
						//$freq_matrix[] = $buckets1;
						$freq_matrix[$subquestion->id] = (array)$buckets1;
				    }
					
					
					
					
				}
			}
		} 
	}
	return $freq_matrix;
}

/** SEPUG FUNCTION
 * Suponiendo un report por curso-profesor
 * @param int $courseid
 */
function sepug_insert_prof_stats($courseid) {
	global $DB;

	// Obtenemos frequencias de resultados y numero de respuestas por curso
	$freq_matrix = sepug_frequency_values($courseid);
	$responses = sepug_count_responses($courseid, 0, 0);

	// Por cada question, calculamos media y desviacion
	foreach ($freq_matrix as $key=>$freq_array){
		
		$sum_array = sepug_freq_sum_values($freq_array);
		
		$record = new stdClass();
		$record->courseid = $courseid;
		$record->question = $key;
		$record->mean = sepug_mean($sum_array, $responses);
		$record->deviation = sepug_deviation($freq_array,$record->mean);
		
		// Insertamos datos en DB
		if (!$stats = $DB->get_record("sepug_prof_stats", array("question"=>$key,"courseid"=>$courseid))) {
			$DB->insert_record("sepug_prof_stats", $record);
		}
		else{
			$record->id = $stats->id;
			$DB->update_record("sepug_prof_stats", $record);
		}
	}
	return 0;
}

/** SEPUG FUNCTION (por nombre de categorias)
 * @param int $courseid
 */
function sepug_compute_results_by_categories($categories, $grado = true){
	global $DB;
	
	$computed_cat = array();
	// Por cada categoria...
	foreach($categories as $cat){
		
		// Si ya hemos explorado las categorias con ese nombre, salimos
		if(!in_array($cat->name, $computed_cat)){
			
			$computed_cat[] = $cat->name;
		
			
			/*if (!$cat_same_name = $DB->get_records("course_categories", array("name"=>$cat->name))) {
				return 1;
			}*/
			
			// Obtenemos todas las categorias que tengan el mismo nombre que esta y que se encuentren en el conjunto
			$cat_same_name = array();
			foreach($categories as $cat2){
				if($cat->name == $cat2->name){
					$cat_same_name[] = $cat2;
				}
			}
			
			// Por cada categoria, buscamos recursivamente todos los cursos asociados
			$courses_list = array();
			foreach($cat_same_name as $cat_sm){
				$cat_class = coursecat::get($cat_sm->id);
				$courses = $cat_class->get_courses(array('recursive' => 1));
				// Si tiene cursos, los aniadimos a la lista
				if(!empty($courses)){
					foreach($courses as $course){
						if(!in_array($course->id,$courses_list)){
							// Si no hay datos para ese curso, lo ignoramos
							if(sepug_count_responses($course->id, 0, 0)!=0){
								$courses_list[] = $course->id; // $course_list tiene todos los cursos de una categoria general
							}
						}
					}
				}
			}
			
			// Si existe algun curso en esas categorias
			if(!empty($courses_list)){
			
				// Obtenemos los resultados de todos los cursos por cada pregunta y calculamos media y desviacion
				//$questions = array(1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20);//TEMPORALLLLLLLLLLLLLLLLLLLLLLLLLLL
				
				if($grado){
					
					$template = $DB->get_record("sepug",array("sepuginstance"=>0, "catgrado"=>1),"questions");
					
					if (!$questions = $DB->get_records_list("sepug_questions", "id", explode(',', $template->questions))) {
						print_error('cannotfindquestion', 'sepug');
					}
					
					//$scaled_questions = 0;
					foreach($questions as $question){
						if($question->options == "scalenumbers5"){
							$scaled_questions = $question->multi; 
						}
					}
					
					$questions = explode(",",$scaled_questions);

				}
				else{
					$template = $DB->get_record("sepug",array("sepuginstance"=>0, "catposgrado"=>1),"questions");
					
					if (!$questions = $DB->get_records_list("sepug_questions", "id", explode(',', $template->questions))) {
						print_error('cannotfindquestion', 'sepug');
					}
					
					//$scaled_questions = 0;
					foreach($questions as $question){
						if($question->options == "scalenumbers5"){
							$scaled_questions = $question->multi; 
						}
					}
					
					$questions = explode(",",$scaled_questions);
				}

				foreach($questions as $question){
					$mean_array=array();
					$deviation_array=array();
					foreach($courses_list as $cid){
						// Si hay datos sobre esa pregunta en ese curso, los a�adimos a los arrays
						if ($stat = $DB->get_record("sepug_prof_stats", array("courseid"=>$cid,"question"=>$question))) {
							$mean_array[]=$stat->mean;
							$deviation_array[]=$stat->deviation;
						}
					}
					
					$record = new stdClass();
					$record->question = $question;
					$record->catname = $cat->name;
					$record->mean = sepug_mean($mean_array, count($mean_array));
					$dev_sum = 0;
					foreach($deviation_array as $dev){
						$dev_sum += pow($dev,2);
					}
					$record->deviation = sqrt($dev_sum);
					if($grado){
						$record->grado = 1;
					}
					else{
						$record->grado = 0;
					}	
					
					// Insertamos datos en DB
					if (!$global_stat = $DB->get_record("sepug_global_stats", array("question"=>$question, "catname"=>$cat->name, "grado"=>$record->grado))) {
						$DB->insert_record("sepug_global_stats", $record);
					}
					else{
						$record->id = $global_stat->id;
						$DB->update_record("sepug_global_stats", $record);
					}
					
				}//endforeach
			}//endif
		}
	}
	
	return 0;
}





/** SEPUG FUNCTION (por nombre de categorias)
 * @param int $courseid
 */
function sepug_insert_global_stats(){
	global $DB, $CFG;
	require_once($CFG->dirroot.'/lib/coursecatlib.php');
	
	// Obtenemos todas las categorias
	/*if (!$categories = $DB->get_records("course_categories", array("visible"=>1))) {
		return 1;
	}*/
	// Hallamos la instancia actual de sepug
	$sepug = $DB->get_record("sepug", array("sepuginstance"=>1));
	
	// Obtenemos el conjunto de categorias de GRADO
	$select = "path LIKE '/".$sepug->catgrado."%'";
	$cat_grado = $DB->get_records_select("course_categories", $select, array("visible"=>1));
	
	sepug_compute_results_by_categories($cat_grado, true);
	
	// Obtenemos el conjunto de categorias de POSTGRADO
	$select = "path LIKE '/".$sepug->catposgrado."%'";
	$cat_posgrado = $DB->get_records_select("course_categories", $select, array("visible"=>1));
	
	sepug_compute_results_by_categories($cat_posgrado, false);
	
	// EN VEZ DE OBTENER TODAS, PIYAMOS SOLO LAS DE CAQTGRADO Y CATPOSGRADO SQL PATH /2/
	// LUEGO ORDENAMOS ESAS CATEGORIAS EN DOS CONJUNTOS: GRADO Y EN POSGRADO
	// obtener todas las categorias del CONJUNTO DETERMINADO con el mismo nombre
	// aqui no tenemos en cuenta los grupos
	// DETECTAMOS SI ES GRADO O POSG function (conjuntocat, grado/postgrado)
	
	return 0;
}


/** SEPUG FUNCTION

 */
function sepug_related_categories($cid){
	global $DB;
	
	$course = $DB->get_record("course", array("id"=>$cid));
	$main_categories = array();
	if ($cat_course = $DB->get_record("course_categories", array("id"=>$course->category))){
		$parent_id = $cat_course->parent;
		$depth = $cat_course->depth;
		//for($i=0; $i<$depth; $i++){
		$depthlimit = $DB->get_record("sepug", array("sepuginstance"=>1), "depthlimit");
		for($i=0; $i<$depth; $i++){
			$cat = $DB->get_record("course_categories", array("id"=>$cat_course->id));
			// Solo cogemos las categorias especificadas en la configuracion
			if($depthlimit->depthlimit >= $cat->depth){
			//$main_categories[] = $DB->get_record("course_categories", array("id"=>$cat_course->id),"name");
				$main_categories[] = $cat;
			}
			if($parent_id!=0){
				$parent_id = $cat_course->parent;
				$cat_course = $DB->get_record("course_categories", array("id"=>$parent_id));
			}
		}
	}
	
	return $main_categories;
}



/** SEPUG FUNCTION
 * @param array $cm
 * @param array $results
 * @param array $questions
 * @param array $questionorder
 * @param int $courseid
 */
function sepug_print_frequency_table($survey, $course) {
    global $OUTPUT, $DB;
	
	$courseid = $course->id;
	echo '<div>'. get_string('porfrecuencia', 'sepug'). '</div>';
	$table = new html_table();
	
	// Obtenemos resultados de la BD
	if ($stats = $DB->get_records("sepug_prof_stats", array("courseid"=>$courseid))) {
		
		// Obtenemos las frecuencias de los resultados
		$frequencies = sepug_frequency_values($courseid);
		
		// Obtenemos las categorias unicas
		//$main_categories = $DB->get_records_sql("SELECT DISTINCT catname FROM {sepug_global_stats}");
		
		// No quedamos solo con las categorias globales que esten relacionadas con este curso, una por nivel de profundidad
		$main_categories = sepug_related_categories($courseid);
		
		// Preparamos la tabla
		$head = array("",get_string("curso","sepug"));
		$headspan = array (7,2);
		$data_head = array(get_string("questions", "sepug"), get_string("scaleNS", "sepug"), get_string("scale1", "sepug"),
		get_string("scale2", "sepug"),get_string("scale3", "sepug"),get_string("scale4", "sepug"),get_string("scale5", "sepug"), 
		get_string("mean", "sepug"), get_string("deviation", "sepug"));
		$size = array("","","","","","","","","");
		$align = array ("left","center","center","center","center","center","center","center","center");
		foreach($main_categories as $cat){
			//$head[] = $cat->catname;
			$head[] = $cat->name;
			$headspan[] = 2;
			array_push($data_head, get_string("mean", "sepug"), get_string("deviation", "sepug"));
			array_push($align, "center", "center");
			array_push($size,"","");
		}
		$table->head  = $head;
		$table->headspan = $headspan;
		$table->data[]  = $data_head;
		$table->align = $align;
		$table->size = $size;
		
		// Obtenemos si es GRADO/POSTGRADO
		if(! $DB->get_records_sql("SELECT * FROM {course_categories} WHERE id = ".$course->category." AND path LIKE '/".$survey->catgrado."%' AND visible = 1")){
			$grado = false;
		}
		else{
			$grado = true;
		}
		
		foreach ($stats as $stat){
			
			$question = $DB->get_record("sepug_questions", array("id"=>$stat->question));
			
			$data = array(get_string("$question->shorttext","sepug"), $frequencies[$stat->question][0], $frequencies[$stat->question][1], 
			$frequencies[$stat->question][2], $frequencies[$stat->question][3], $frequencies[$stat->question][4], $frequencies[$stat->question][5], 
			$stat->mean, $stat->deviation);	
			
			// Obtenemos los resultados globales de las encuestas
			//$global_stats = $DB->get_records("sepug_global_stats",array("question"=>$stat->question));
			
			/*foreach($global_stats as $gstats){
				$data[] = $gstats->mean;
				$data[] = $gstats->deviation;
			}*/
			
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
			$table->data[] = $data;
		}	
	}
	
    echo html_writer::table($table);
}


/** SEPUG FUNCTION
 */
function sepug_get_dim_results($stats, $dim) {
		
	// Recogemos los datos de las preguntas que pertenezcan a esa dimension
	$mean_array = array();
	$deviation_array = array();
	
	foreach ($stats as $stat){
		if(in_array($stat->question, $dim)){
			$mean_array[] = $stat->mean;
			$deviation_array[] = $stat->deviation;
		}
	}
	
	$mean = sepug_mean($mean_array, count($mean_array));
	$dev_sum = 0;
	foreach($deviation_array as $dev){
		$dev_sum += pow($dev,2);
	}
	$deviation = round(sqrt($dev_sum),2);
	
	return array($mean, $deviation);
}

/** SEPUG FUNCTION
 * @param int $courseid
 */
function sepug_print_dimension_table($survey, $course) {
    global $OUTPUT, $DB;
	global $DIM_PLANIF, $DIM_COMP_DOC, $DIM_EV_APREND, $DIM_AMB;
	$courseid = $course->id;
	
	echo '<div>'. get_string('pordimension', 'sepug'). '</div>';
	
	// Preparamos la tabla
    $table = new html_table();
    $table->head  = array ("",get_string("curso","sepug"),get_string("universidad","sepug"));
	$table->headspan  = array (1,2,2);
    $table->align = array ("left","center","center","center","center");
    $table->size = array ("","","","","");
	$table->data[] = array(get_string("dimension","sepug"),get_string("mean","sepug"),get_string("deviation","sepug"),
	get_string("mean","sepug"),get_string("deviation","sepug"));
	
	// Obtenemos la categoria padre a nivel 1 de profundidad del curso
	$course = $DB->get_record("course", array("id"=>$courseid));
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
	
	// Obtenemos resultados de la BD (aunque no hace falta ya que estamos escogiendo la categoria padre 
	// que no debe estar duplicada en la BD, incluimos en los parametros si es de GRADO/POSTGRADO)
	$stats = $DB->get_records("sepug_prof_stats", array("courseid"=>$courseid));
	if(!$DB->get_records_sql("SELECT * FROM {course_categories} WHERE id = ".$course->category." AND path LIKE '/".$survey->catgrado."%' AND visible = 1")){
		$gstats = $DB->get_records("sepug_global_stats",array("catname"=>$parent_cat->name,"grado"=>0));
	}
	else{
		$gstats = $DB->get_records("sepug_global_stats",array("catname"=>$parent_cat->name,"grado"=>1));
	}
	
	if (!$stats || !$gstats) {
		return 1;
	}
	else{
		
		//$stats = $DB->get_records("sepug_prof_stats", array("courseid"=>$courseid));
		//$gstats = $DB->get_records("sepug_global_stats",array("catname"=>$parent_cat->name));
		//$stats = $DB->get_records("sepug_prof_stats", array("courseid"=>$courseid));
		//$gstats = $DB->get_records("sepug_global_stats",array("catname"=>$parent_cat->name,"grado"=>));
		
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
		
		// Completamos la tabla de resultados
		$table->data[] = array(get_string("dim1","sepug"),$mean_array[0],$deviation_array[0],$gmean_array[0],$gdeviation_array[0]);
		$table->data[] = array(get_string("dim2","sepug"),$mean_array[1],$deviation_array[1],$gmean_array[1],$gdeviation_array[1]);
		$table->data[] = array(get_string("dim3","sepug"),$mean_array[2],$deviation_array[2],$gmean_array[2],$gdeviation_array[2]);
		$table->data[] = array(get_string("dim4","sepug"),$mean_array[3],$deviation_array[3],$gmean_array[3],$gdeviation_array[3]);

	}

    echo html_writer::table($table);
}


/**
 * @global object
 * @param int $templateid
 * @return string
 */
function sepug_print_global_results_graph($url){
	global $CFG, $SEPUG_GHEIGHT, $SEPUG_GWIDTH;
	//require_once("$CFG->libdir/graphlib.php");
	
	
	// Obtenemos las categorias relacionadas con el curso
	/*$main_categories = sepug_related_categories(2);
	
	$mean_array = array();
	$deviation_array = array();
	// Hallamos los valores de la ultima pregunta de las encuestas en cada categoria
	foreach($main_categories as $cat){
		$gstats = $DB->get_records("sepug_global_stats",array("catname"=>$cat->name));
		$result = array_pop($gstats);
		$mean_array[] = $result->mean;
		$deviation_array[] =  $result->deviation;
	}
	
	$x_data = array();
	for ($i=0; $i<count($main_categories); $i++){
		$x_data[] = $main_categories[$i]->name; 
	}
	
	// PRUEBAS
    print "<div style=\"border:5px solid red;\">";
    print " PRUEBA var dump <br />\n";
    print "<pre>";
    var_dump($x_data);
    print "</pre></div>";*/
	
	
		 
	echo "<img class='resultgraph' align=\"middle\" height=\"$SEPUG_GHEIGHT\" width=\"$SEPUG_GWIDTH\"".
         " src=\"$CFG->wwwroot/mod/sepug/graph.php?$url\" alt=\"".get_string("sepuggraph", "sepug")."\" />"; 
	
}

/**
 * @global object
 * @param int $templateid
 * @return string
 */
function sepug_get_template_name($templateid) {
    global $DB;

    if ($templateid) {
        if ($ss = $DB->get_record("surveys", array("id"=>$templateid))) {
            return $ss->name;
        }
    } else {
        return "";
    }
}


/**
 * @param string $name
 * @param array $numwords
 * @return string
 */
function sepug_shorten_name ($name, $numwords) {
    $words = explode(" ", $name);
    $output = '';
    for ($i=0; $i < $numwords; $i++) {
        $output .= $words[$i]." ";
    }
    return $output;
}

/**
 * @todo Check this function
 *
 * @global object
 * @global object
 * @global int
 * @global void This is never defined
 * @global object This is defined twice?
 * @param object $question
 */
function sepug_print_multi($question) {
    global $USER, $DB, $qnum, $checklist, $DB, $OUTPUT; //TODO: this is sloppy globals abuse

    $stripreferthat = get_string("ipreferthat", "sepug");
    $strifoundthat = get_string("ifoundthat", "sepug");
    $strdefault    = get_string('notyetanswered', 'sepug');
    $strresponses  = get_string('responses', 'sepug');

    echo $OUTPUT->heading($question->text, 3, 'questiontext');
    echo "\n<table width=\"90%\" cellpadding=\"4\" cellspacing=\"1\" border=\"0\" class=\"surveytable\">";

    $options = explode( ",", $question->options);
    $numoptions = count($options);

    // COLLES Actual (which is having questions of type 1) and COLLES Preferred (type 2)
    // expect just one answer per question. COLLES Actual and Preferred (type 3) expects
    // two answers per question. ATTLS (having a single question of type 1) expects one
    // answer per question. CIQ is not using multiquestions (i.e. a question with subquestions).
    // Note that the type of subquestions does not really matter, it's the type of the
    // question itself that determines everything.
    $oneanswer = ($question->type == 1 || $question->type == 2) ? true : false;

    // COLLES Preferred (having questions of type 2) will use the radio elements with the name
    // like qP1, qP2 etc. COLLES Actual and ATTLS have radios like q1, q2 etc.
    if ($question->type == 2) {
        $P = "P";
    } else {
        $P = "";
    }

    echo "<tr class=\"smalltext\"><th scope=\"row\">$strresponses</th>";
    echo "<th scope=\"col\" class=\"hresponse\">". get_string('notyetanswered', 'sepug'). "</th>";
    while (list ($key, $val) = each ($options)) {
        echo "<th scope=\"col\" class=\"hresponse\">$val</th>\n";
    }
    echo "</tr>\n";

    echo "<tr><th scope=\"col\" colspan=\"7\">$question->intro</th></tr>\n";

    $subquestions = $DB->get_records_list("sepug_questions", "id", explode(',', $question->multi));

    foreach ($subquestions as $q) {
        $qnum++;
        if ($oneanswer) {
            $rowclass = sepug_question_rowclass($qnum);
        } else {
            $rowclass = sepug_question_rowclass(round($qnum / 2));
        }
        if ($q->text) {
            $q->text = get_string($q->text, "sepug");
        }

        echo "<tr class=\"$rowclass rblock\">";
        if ($oneanswer) {
            echo "<th scope=\"row\" class=\"optioncell\">";
            echo "<b class=\"qnumtopcell\">$qnum</b> &nbsp; ";
            echo $q->text ."</th>\n";

            $default = get_accesshide($strdefault);
            echo "<td class=\"whitecell\"><label for=\"q$P$q->id\"><input type=\"radio\" name=\"q$P$q->id\" id=\"q$P" . $q->id . "_D\" value=\"0\" checked=\"checked\" />$default</label></td>";

            for ($i=1;$i<=$numoptions;$i++) {
                $hiddentext = get_accesshide($options[$i-1]);
                $id = "q$P" . $q->id . "_$i";
                echo "<td><label for=\"$id\"><input type=\"radio\" name=\"q$P$q->id\" id=\"$id\" value=\"$i\" />$hiddentext</label></td>";
            }
            $checklist["q$P$q->id"] = 0;

        } else {
            echo "<th scope=\"row\" class=\"optioncell\">";
            echo "<b class=\"qnumtopcell\">$qnum</b> &nbsp; ";
            $qnum++;
            echo "<span class=\"preferthat\">$stripreferthat</span> &nbsp; ";
            echo "<span class=\"option\">$q->text</span></th>\n";

            $default = get_accesshide($strdefault);
            echo '<td class="whitecell"><label for="qP'.$q->id.'"><input type="radio" name="qP'.$q->id.'" id="qP'.$q->id.'" value="0" checked="checked" />'.$default.'</label></td>';


            for ($i=1;$i<=$numoptions;$i++) {
                $hiddentext = get_accesshide($options[$i-1]);
                $id = "qP" . $q->id . "_$i";
                echo "<td><label for=\"$id\"><input type=\"radio\" name=\"qP$q->id\" id=\"$id\" value=\"$i\" />$hiddentext</label></td>";
            }
            echo "</tr>";

            echo "<tr class=\"$rowclass rblock\">";
            echo "<th scope=\"row\" class=\"optioncell\">";
            echo "<b class=\"qnumtopcell\">$qnum</b> &nbsp; ";
            echo "<span class=\"foundthat\">$strifoundthat</span> &nbsp; ";
            echo "<span class=\"option\">$q->text</span></th>\n";

            $default = get_accesshide($strdefault);
            echo '<td class="whitecell"><label for="q'. $q->id .'"><input type="radio" name="q'.$q->id. '" id="q'. $q->id .'" value="0" checked="checked" />'.$default.'</label></td>';

            for ($i=1;$i<=$numoptions;$i++) {
                $hiddentext = get_accesshide($options[$i-1]);
                $id = "q" . $q->id . "_$i";
                echo "<td><label for=\"$id\"><input type=\"radio\" name=\"q$q->id\" id=\"$id\" value=\"$i\" />$hiddentext</label></td>";
            }

            $checklist["qP$q->id"] = 0;
            $checklist["q$q->id"] = 0;
        }
        echo "</tr>\n";
    }
    echo "</table>";
}


/**
 * @global object
 * @global int
 * @param object $question
 */
function sepug_print_single($question) {
    global $DB, $qnum, $OUTPUT, $checklist;

    $rowclass = sepug_question_rowclass(0);

    $qnum++;

    echo "<br />\n";
    echo "<table width=\"90%\" cellpadding=\"4\" cellspacing=\"0\">\n";
    echo "<tr class=\"$rowclass\">";
    echo "<th scope=\"row\" class=\"optioncell\"><label for=\"q$question->id\"><b class=\"qnumtopcell\">$qnum</b> &nbsp; ";
    echo "<span class=\"questioncell\">$question->text</span></label></th>\n";
    echo "<td class=\"questioncell smalltext\">\n";


    if ($question->type == 0) {           // Plain text field
        echo "<textarea rows=\"3\" cols=\"30\" name=\"q$question->id\" id=\"q$question->id\">$question->options</textarea>";

    } else if ($question->type > 0) {     // Choose one of a number
		
		// Aniadimos pregunta al array checklist, para poder confirmar en view.php y sepug.js que la pregunta ha sido contestada
		$checklist["q$question->id"] = 0;
		
        $strchoose = get_string("choose");
        echo "<select name=\"q$question->id\" id=\"q$question->id\">";
        echo "<option value=\"0\" selected=\"selected\">$strchoose...</option>";
        $options = explode( ",", $question->options);
        foreach ($options as $key => $val) {
            $key++;
            echo "<option value=\"$key\">$val</option>";
        }
        echo "</select>";

    } else if ($question->type < 0) {     // Choose several of a number
        $options = explode( ",", $question->options);
        echo $OUTPUT->notification("This question type not supported yet");
    }

    echo "</td></tr></table>";

}

/**
 *
 * @param int $qnum
 * @return string
 */
function sepug_question_rowclass($qnum) {

    if ($qnum) {
        return $qnum % 2 ? 'r0' : 'r1';
    } else {
        return 'r0';
    }
}

/**
 * @global object
 * @global int
 * @global int
 * @param string $url
 */
function sepug_print_graph($url) {
    global $CFG, $SEPUG_GHEIGHT, $SEPUG_GWIDTH;

    echo "<img class='resultgraph' height=\"$SURVEY_GHEIGHT\" width=\"$SURVEY_GWIDTH\"".
         " src=\"$CFG->wwwroot/mod/sepug/graph.php?$url\" alt=\"".get_string("surveygraph", "sepug")."\" />"; 
}

/**
 * @return array
 */
function sepug_get_view_actions() {
    return array('download','view all','view form','view graph','view report');
}

/**
 * @return array
 */
function sepug_get_post_actions() {
    return array('submit');
}


/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the survey.
 *
 * @param object $mform form passed by reference
 */
function sepug_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'surveyheader', get_string('modulenameplural', 'sepug'));
    $mform->addElement('checkbox', 'reset_survey_answers', get_string('deleteallanswers','sepug'));
    $mform->addElement('checkbox', 'reset_survey_analysis', get_string('deleteanalysis','sepug'));
    $mform->disabledIf('reset_survey_analysis', 'reset_survey_answers', 'checked');
}

/**
 * Course reset form defaults.
 * @return array
 */
function sepug_reset_course_form_defaults($course) {
    return array('reset_survey_answers'=>1, 'reset_survey_analysis'=>1);
}

/**
 * Actual implementation of the reset course functionality, delete all the
 * survey responses for course $data->courseid.
 *
 * @global object
 * @param $data the data submitted from the reset course.
 * @return array status array
 */
function sepug_reset_userdata($data) {
    global $DB;

    $componentstr = get_string('modulenameplural', 'sepug');
    $status = array();

    $surveyssql = "SELECT ch.id
                     FROM {sepug} ch
                    WHERE ch.course=?";
    $params = array($data->courseid);

    if (!empty($data->reset_sepug_answers)) {
        $DB->delete_records_select('sepug_answers', "survey IN ($surveyssql)", $params);
        $DB->delete_records_select('sepug_analysis', "survey IN ($surveyssql)", $params);
        $status[] = array('component'=>$componentstr, 'item'=>get_string('deleteallanswers', 'sepug'), 'error'=>false);
    }

    if (!empty($data->reset_sepug_analysis)) {
        $DB->delete_records_select('sepug_analysis', "survey IN ($surveyssql)", $params);
        $status[] = array('component'=>$componentstr, 'item'=>get_string('deleteallanswers', 'sepug'), 'error'=>false);
    }

    // no date shifting
    return $status;
}

/**
 * Returns all other caps used in module
 *
 * @return array
 */
function sepug_get_extra_capabilities() {
    return array('moodle/site:accessallgroups');
}

/**
 * @uses FEATURE_GROUPS
 * @uses FEATURE_GROUPINGS
 * @uses FEATURE_GROUPMEMBERSONLY
 * @uses FEATURE_MOD_INTRO
 * @uses FEATURE_COMPLETION_TRACKS_VIEWS
 * @uses FEATURE_GRADE_HAS_GRADE
 * @uses FEATURE_GRADE_OUTCOMES
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know
 */
function sepug_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                  return true;
        case FEATURE_GROUPINGS:               return true;
        case FEATURE_GROUPMEMBERSONLY:        return true;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_GRADE_HAS_GRADE:         return false;
        case FEATURE_GRADE_OUTCOMES:          return false;
        case FEATURE_BACKUP_MOODLE2:          return true;
        case FEATURE_SHOW_DESCRIPTION:        return true;

        default: return null;
    }
}

/**
 * This function extends the settings navigation block for the site.
 *
 * It is safe to rely on PAGE here as we will only ever be within the module
 * context when this is called
 *
 * @param navigation_node $settings
 * @param navigation_node $surveynode
 */
function sepug_extend_settings_navigation($settings, $surveynode) {
    global $PAGE;

    if (has_capability('mod/sepug:readresponses', $PAGE->cm->context)) {
        $responsesnode = $surveynode->add(get_string("responsereports", "sepug"));

        $url = new moodle_url('/mod/sepug/report.php', array('id' => $PAGE->cm->id, 'action'=>'summary'));
        $responsesnode->add(get_string("summary", "sepug"), $url);

        $url = new moodle_url('/mod/sepug/report.php', array('id' => $PAGE->cm->id, 'action'=>'scales'));
        $responsesnode->add(get_string("scales", "sepug"), $url);

        $url = new moodle_url('/mod/sepug/report.php', array('id' => $PAGE->cm->id, 'action'=>'questions'));
        $responsesnode->add(get_string("question", "sepug"), $url);

        $url = new moodle_url('/mod/sepug/report.php', array('id' => $PAGE->cm->id, 'action'=>'students'));
        $responsesnode->add(get_string('participants'), $url);

        if (has_capability('mod/sepug:download', $PAGE->cm->context)) {
            $url = new moodle_url('/mod/sepug/report.php', array('id' => $PAGE->cm->id, 'action'=>'download'));
            $surveynode->add(get_string('downloadresults', 'sepug'), $url);
        }
    }
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function sepug_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $module_pagetype = array('mod-survey-*'=>get_string('page-mod-survey-x', 'sepug'));
    return $module_pagetype;
}
