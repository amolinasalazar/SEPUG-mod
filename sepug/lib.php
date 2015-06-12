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
 * @package   mod-sepug
 * @copyright 2015 Alejandro Molina Salazar
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 // Global data
require_once("configuration.php");

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

    $survey->id           = $survey->instance;
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

	// Delete all SEPUG DB, except templates and questions
    if (! $DB->delete_records("sepug_prof_stats")) {
        $result = false;
    }
	
	if (! $DB->delete_records("sepug_global_stats")) {
        $result = false;
    }

    if (! $DB->delete_records("sepug_answers")) {
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


/** SEPUG
 * @global object $DB
 * @return object All valid courses that the filter accept
 */
function sepug_get_valid_courses() {
    global $DB, $FILTRO;
	
	return $DB->get_records_sql($FILTRO);
}

/** SEPUG
 * @global object $DB
 * @param int templateid
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


// MODULE FUNCTIONS ////////////////////////////////////////////////////////

/** SEPUG
 * @global object $DB
 * @global string $FILTRO
 * @param int $courseid
 * @return bool
 */
function sepug_courseid_validator($courseid) {
    global $DB, $FILTRO;
	
	$valid = false;
	$valid_courses = $DB->get_records_sql($FILTRO);
	if(in_array($courseid, array_keys($valid_courses))){
		$valid = true;
	}
	
	return $valid;
}


/** SEPUG
 * @global object $DB
 * @global string $FILTRO
 * @param object $courses
 * @return object $courses Removed marked as not valid by the filter
 */
function sepug_courses_validator($courses) {
    global $DB, $FILTRO;

	$valid_courses = $DB->get_records_sql($FILTRO);
	foreach($courses as $course){
		if(!in_array($course->id, array_keys($valid_courses))){
			unset($courses[$course->id]);
		}
	}
	return $courses;
}


/** SEPUG
 * @global object $DB
 * @global object $USER
 * @global bool $FILTRO_CURSOS
 * @param object $survey
 * @return object $courses Enrolled valid courses
 */
function sepug_get_enrolled_valid_courses($survey){
	global $DB, $USER, $FILTRO_CURSOS;
	
	// We get all courses where user is enrolled in - r: array asoc.(ids courses)
	$enrolled_courses = enrol_get_all_users_courses($USER->id, true, null, 'visible DESC, sortorder ASC');

	// If we had set a course filter and the course is not valid
	if($FILTRO_CURSOS){
		$enrolled_courses = sepug_courses_validator($enrolled_courses);
	}
	
	// and finally, remove the courses that not belong to GRADO or POSTGRADO
	$courses = array();
	foreach($enrolled_courses as $course){
		$select = "path LIKE '/".$survey->catgrado."%' OR path LIKE '/".$survey->catposgrado."%'";
		if($DB->record_exists_select("course_categories", $select, array("visible"=>1, "id"=>$course->category))){
			$courses[] = $course;
		}
	}
	
	return $courses;
}


/** SEPUG
 * @global object $DB
 * @param int $courseid
 * @param int $user
 * @param int $group
 * @return bool
 */
function sepug_already_done($courseid, $user, $group=0) {
    global $DB;
	
	if($group==0){
		return $DB->record_exists("sepug_answers", array("courseid"=>$courseid, "userid"=>$user));
	}
	else{
		return $DB->record_exists("sepug_answers", array("courseid"=>$courseid, "userid"=>$user, "groupid"=>$group));
	}
}


/** SEPUG
 * @global object $DB
 * @param int $courseid
 * @param int $group
 * @return int
 */
function sepug_count_responses($courseid, $group=0) {
	global $DB;
	
	if($group == -1){
		$n_resp = $DB->get_record_sql("SELECT COUNT(DISTINCT userid) AS num_resp FROM {sepug_answers} WHERE courseid = ?",
			array($courseid));
		return $n_resp->num_resp;
	}
	$n_resp = $DB->get_record_sql("SELECT COUNT(DISTINCT userid) AS num_resp FROM {sepug_answers} WHERE courseid = ? AND groupid = ?",
		array($courseid, $group));
		
    return $n_resp->num_resp;
}


/** SEPUG
 * @param array $numlist
 * @param int $numvalues
 * @param int $NS_count
 * @return float
 */
function sepug_mean($numlist, $numvalues, $NS_count = 0) {

	if(($numvalues-$NS_count)!=0)
		return (array_sum($numlist) / ($numvalues-$NS_count));
	else
		return 0;
}


/** SEPUG
 * @param array $frequencies
 * @param int $mean
 * @return float
 */
function sepug_deviation($frequencies, $mean) {
	
	$all_results = array();
	
	foreach($frequencies as $key=>$value){
		for($i=0; $i<$value && $key!=0; $i++){
			$all_results[] = pow(($key-$mean),2);
		}
	}
	
	if(count($all_results)!=0){
		return sqrt(array_sum($all_results)/count($all_results));
	}
	
	return 0;
}


/** SEPUG
 * @param array $array_freq
 * @return array $array_freq
 */
function sepug_freq_sum_values($array_freq) {
	$sum = 0;
	
	foreach ($array_freq as $key=>$freq){
		$array_freq[$key] = $key*$freq;
	}
	
	return $array_freq;
}


/** SEPUG
 * @global object $CFG
 * @global object $DB
 * @param int $courseid
 * @return int Template ID
 */
function sepug_get_template($courseid) {
	global $CFG, $DB;
	require_once($CFG->dirroot.'/lib/coursecatlib.php');
	
	// Obtain the father category of postgrado
	$survey = $DB->get_record("sepug", array("sepuginstance"=>"1"), "catposgrado");
	
	// We have to detect if the course is from GRADO or POSTGRADO.
	// To perform that, we search first if it's from POSTGRADO because it will be faster.
	// In view.php we only show the courses that belong to one of the two groups.
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


/** SEPUG
 * @global object $DB
 * @param int $courseid
 * @param bool $externalcall To change error messages
 * @return array Array of questions ID
 */
function sepug_get_questions_ID($courseid, $externalcall=false) {
	global $DB;
	
	// First, obtain the template
	$tmpid = sepug_get_template($courseid);
	if (! $template = $DB->get_record("sepug", array("id"=>$tmpid))) {
		if(!$externalcall){
			print_error('invalidtmptid', 'sepug');
		}
		else{
			throw new moodle_exception('invalidtmptid', 'sepug');
		}
	}
	
	// Retrieve all the question of the template
	if (! $questions = $DB->get_records_list("sepug_questions", "id", explode(',', $template->questions))) {
		if(!$externalcall){
			print_error('cannotfindquestion', 'sepug');
		}
		else{
			throw new moodle_exception('cannotfindquestion', 'sepug');
		}
	}
	
	$idarray = array();
	foreach ($questions as $question) {

		if ($question->type >= 0) {
			
			if($question->multi){
				
				$subquestions = $DB->get_records_list("sepug_questions", "id", explode(',', $question->multi));
					
				foreach ($subquestions as $subquestion) {
					$idarray[] = $subquestion->id;
				}
			}
			else{
				$idarray[] = $question->id;
			}	
		}
	}
	
	return $idarray;
}


/** SEPUG
 * @global object $DB
 * @param int $value
 * @param int $questionid To change error messages
 * @return bool 
 */
function sepug_response_value_validator($value, $questionid){
	global $DB;
	
	$question = $DB->get_record("sepug_questions", array("id"=>$questionid));
	
	$numresponses = count(explode(",",get_string($question->options, "sepug")));
	
	if($value<=$numresponses AND $value>=1){
		return true;
	}
	
	return false;
}


/** SEPUG
 * @global $DB
 * @param int $courseid
 * @param int $group
 * @return array Multidimensional array with key=>question, key as ID and question as frequency value of this question
 */
function sepug_frequency_values($courseid, $group=0) {
	global $DB;
	
	$freq_matrix = array();
	
	// First, obtain the template
	$tmpid = sepug_get_template($courseid);
	if (! $template = $DB->get_record("sepug", array("id"=>$tmpid))) {
        print_error('invalidtmptid', 'sepug');
    }
	
	// From it, we obtain questions and the order
	$questions = $DB->get_records_list("sepug_questions", "id", explode(',', $template->questions));
	$questionorder = explode(",", $template->questions);

	// For each question..
	foreach ($questionorder as $key => $val) {
		$question = $questions[$val];

		// Skip question type < 0
		if ($question->type < 0) {  
			continue;
		}
		$question->text = get_string($question->text, "sepug");

		// If is type multi, obtain all the questions
		if ($question->multi) {

			$subquestions = $DB->get_records_list("sepug_questions", "id", explode(',', $question->multi));
			$subquestionorder = explode(",", $question->multi);
			foreach ($subquestionorder as $key => $val) {
				$subquestion = $subquestions[$val];
				if ($subquestion->type > 0) {
				
					// For each subquestion, calculate result frequency
					
					// We obtain the available options for each question and prepare an array to store the frequencies
					$subquestion->options = get_string($subquestion->options, "sepug");
				    $options = explode(",",$subquestion->options);

					while (list($key,) = each($options)) {
					   $buckets1[$key] = 0;
					}

					// Obtain the answers for all users that have answered a given question in a given survey
					if ($aaa = $DB->get_records('sepug_answers', array('courseid'=>$courseid, 'question'=>$subquestion->id, 'groupid'=>$group))) {
					    foreach ($aaa as $aa) {
							   if ($a1 = $aa->answer1) {
								   $buckets1[$a1 - 1]++;
							   }
					    }
						
						// Store all the results in an array matrix
						$freq_matrix[$subquestion->id] = (array)$buckets1;
				    }
				}
			}
		} 
	}
	return $freq_matrix;
}


/** SEPUG
 * @global object $DB
 * @param int $courseid
 * @param int $group
 * @return int
 */
function sepug_insert_prof_stats($courseid, $group=0) {
	global $DB;
	
	// If we have no data, return error
	if (!$DB->record_exists("sepug_answers", array("courseid"=>$courseid, "groupid"=>$group)) ) {
        return 1;
    } 

	// Obtain result frequencies and number of answers for course
	$freq_matrix = sepug_frequency_values($courseid, $group);
	$responses = sepug_count_responses($courseid, $group);
	
	// For each question, calculate mean and desviation
	foreach ($freq_matrix as $key=>$freq_array){
		
		$sum_array = sepug_freq_sum_values($freq_array);
		$NS_count = $freq_array[0];
		
		$record = new stdClass();
		$record->courseid = $courseid;
		$record->question = $key;
		$record->mean = sepug_mean($sum_array, $responses, $NS_count);
		$record->deviation = sepug_deviation($freq_array,$record->mean);
		$record->groupid = $group;
		
		// Insert data in DB
		if (!$stats = $DB->get_record("sepug_prof_stats", array("question"=>$key,"courseid"=>$courseid, "groupid"=>$group))) {
			$DB->insert_record("sepug_prof_stats", $record);
		}
		else{
			$record->id = $stats->id;
			$DB->update_record("sepug_prof_stats", $record);
		}
	}
	return 0;
}


/** SEPUG (por nombre de categorias)
 * @global object $DB
 * @param array $categories
 * @param bool $grado
 * @return int
 */
function sepug_compute_results_by_categories($categories, $grado = true){
	global $DB;
	
	$computed_cat = array();

	foreach($categories as $cat){
		
		// If we already explored all categories with same name, exit
		if(!in_array($cat->name, $computed_cat)){
			
			$computed_cat[] = $cat->name;
			
			// Obtain all categories that have same name and are located in the same level
			$cat_same_name = array();
			foreach($categories as $cat2){
				if($cat->name == $cat2->name){
					$cat_same_name[] = $cat2;
				}
			}
			
			// For each category, search recursively all related courses
			$courses_list = array();
			foreach($cat_same_name as $cat_sm){
				$cat_class = coursecat::get($cat_sm->id);
				$courses = $cat_class->get_courses(array('recursive' => 1));
				// If has courses, add to the list
				if(!empty($courses)){
					foreach($courses as $course){
						if(!in_array($course->id,$courses_list)){
							// If there is no data for that course, skip it
							if(sepug_count_responses($course->id, -1)!=0){
								$courses_list[] = $course->id; // $course_list tiene todos los cursos de una categoria general
							}
						}
					}
				}
			}
			
			// If exists a course in these categories
			if(!empty($courses_list)){
			
				// Obtain results of all courses for each question and calculate mean and desviation
				if($grado){
					
					$template = $DB->get_record("sepug",array("sepuginstance"=>0, "catgrado"=>1),"questions");
					
					if (!$questions = $DB->get_records_list("sepug_questions", "id", explode(',', $template->questions))) {
						print_error('cannotfindquestion', 'sepug');
					}

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
						// If there is data about that question in the course, add it to the array
						if ($stats = $DB->get_records("sepug_prof_stats", array("courseid"=>$cid,"question"=>$question))) {
							foreach($stats as $stat){
								$mean_array[]=$stat->mean;
								$deviation_array[]=$stat->deviation;
							}
						}
					}
					
					$record = new stdClass();
					$record->question = $question;
					$record->catname = $cat->name;
					$record->mean = sepug_mean($mean_array, count($mean_array));
					
					$mean_freq = array();
					foreach($mean_array as $mean){
						$mean_freq[$mean]=0;
					}
					foreach($mean_array as $mean){
						$mean_freq[$mean]++;;
					}

					$record->deviation = sepug_deviation($mean_freq, $record->mean);
					
					if($grado){
						$record->grado = 1;
					}
					else{
						$record->grado = 0;
					}	
					
					// Insert data into the DB
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


/** SEPUG
 * @global object $DB
 * @global object $CFG
 * @param int $courseid
 * @return int
 */
function sepug_insert_global_stats(){
	global $DB, $CFG;
	require_once($CFG->dirroot.'/lib/coursecatlib.php');
	
	// If we have no data, return error
	if (!$DB->record_exists("sepug_prof_stats", array()) ) {
        return 1;
    } 
	
	// Find the actual SEPUG instance
	$sepug = $DB->get_record("sepug", array("sepuginstance"=>1));
	
	// Obtain GRADO categories group
	$select = "path LIKE '/".$sepug->catgrado."%'";
	$cat_grado = $DB->get_records_select("course_categories", $select, array("visible"=>1));
	
	sepug_compute_results_by_categories($cat_grado, true);
	
	// Obtain POSTGRADO categories group
	$select = "path LIKE '/".$sepug->catposgrado."%'";
	$cat_posgrado = $DB->get_records_select("course_categories", $select, array("visible"=>1));
	
	sepug_compute_results_by_categories($cat_posgrado, false);
	
	return 0;
}


/** SEPUG
 * @global object $DB
 * @param int $cid
 * @return array $main_categories
 */
function sepug_related_categories($cid){
	global $DB;
	
	$course = $DB->get_record("course", array("id"=>$cid));
	$main_categories = array();
	if ($cat_course = $DB->get_record("course_categories", array("id"=>$course->category))){
		$parent_id = $cat_course->parent;
		$depth = $cat_course->depth;
		$depthlimit = $DB->get_record("sepug", array("sepuginstance"=>1), "depthlimit");
		for($i=0; $i<$depth; $i++){
			$cat = $DB->get_record("course_categories", array("id"=>$cat_course->id));
			// Only get the specified categories in the module configuration
			if($depthlimit->depthlimit >= $cat->depth){
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


/** SEPUG
 * @global object $OUTPUT
 * @global object $DB
 * @param object $survey
 * @param object $course
 * @param int $group
 * @return int
 */
function sepug_print_frequency_table($survey, $course, $group=0) {
    global $OUTPUT, $DB;
	
	$courseid = $course->id;
	echo '<div>'. get_string('porfrecuencia', 'sepug'). '</div>';
	$table = new html_table();
	
	// Obtain results from DB
	if ($stats = $DB->get_records("sepug_prof_stats", array("courseid"=>$courseid, "groupid"=>$group))) {
		
		// Retrieve results frequencies
		$frequencies = sepug_frequency_values($courseid, $group);
		
		// Just explore related global categories to the course, one per depth level
		$main_categories = sepug_related_categories($courseid);
		
		// Prepare table
		$head = array("",get_string("curso","sepug"));
		$headspan = array (7,2);
		$data_head = array(get_string("questions", "sepug"), get_string("scaleNS", "sepug"), get_string("scale1", "sepug"),
		get_string("scale2", "sepug"),get_string("scale3", "sepug"),get_string("scale4", "sepug"),get_string("scale5", "sepug"), 
		get_string("mean", "sepug"), get_string("deviation", "sepug"));
		$size = array("","","","","","","","","");
		$align = array ("left","center","center","center","center","center","center","center","center");
		foreach($main_categories as $cat){
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
		
		// It's grado or postgrado
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
			
			foreach($main_categories as $cat){
				// Grado/Postgrado
				$select = "path LIKE '/".$survey->catgrado."%'";
				// NOTE: don't use record_exists_select, always return true
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


/** SEPUG
 * @param array $stats
 * @param array $dim
 * @return array
 */
function sepug_get_dim_results($stats, $dim) {
		
	// For each question that belongs to the given dimension
	$mean_array = array();
	$deviation_array = array();
	
	foreach ($stats as $stat){
		if(in_array($stat->question, $dim)){
			if($stat->mean!=0){
				$mean_array[] = $stat->mean;
			}
			if($stat->deviation!=0){
				$deviation_array[] = $stat->deviation;
			}
		}
	}
	
	if(count($mean_array)!=0){
		$mean = sepug_mean($mean_array, count($mean_array));
	}
	else{
		$mean = 0;
	}
	
	if(count($deviation_array)!=0){
		$dev_sum = 0;
		foreach($deviation_array as $dev){
			$dev_sum += pow($dev,2);
		}
		$deviation = round(sqrt($dev_sum),2);
	}
	else{
		$deviation=0;
	}
	
	return array($mean, $deviation);
}


/** SEPUG
 * @global object $OUTPUT
 * @global object $DB
 * @global object $DIM_PLANIF
 * @global object $DIM_COMP_DOC
 * @global object $DIM_EV_APREND
 * @global object $DIM_AMB
 * @param object $survey
 * @param object $course
 * @param int $group
 */
function sepug_print_dimension_table($survey, $course, $group=0) {
    global $OUTPUT, $DB;
	global $DIM_PLANIF, $DIM_COMP_DOC, $DIM_EV_APREND, $DIM_AMB;
	$courseid = $course->id;
	
	echo '<div>'. get_string('pordimension', 'sepug'). '</div>';
	
	// Prepare table
    $table = new html_table();
    $table->head  = array ("",get_string("curso","sepug"),get_string("universidad","sepug"));
	$table->headspan  = array (1,2,2);
    $table->align = array ("left","center","center","center","center");
    $table->size = array ("","","","","");
	$table->data[] = array(get_string("dimension","sepug"),get_string("mean","sepug"),get_string("deviation","sepug"),
	get_string("mean","sepug"),get_string("deviation","sepug"));
	
	// Obtain father category at depth level 1 of the course
	$course = $DB->get_record("course", array("id"=>$courseid));
	if ($cat_course = $DB->get_record("course_categories", array("id"=>$course->category))){
		// If parent is 0, the category is at level 1 already
		if($cat_course->parent != 0){
			$parent_id = $cat_course->path[1];
			$parent_cat = $DB->get_record("course_categories", array("id"=>$parent_id),"name");
		}
		else{
			$parent_cat = $cat_course;
		}
	}
	
	// Obtain results form DB (it's not necessary because we are taking the father category that is unique in the DB, but to be sure, we do it including in the query if is GRADO/POSTGRADO)
	$stats = $DB->get_records("sepug_prof_stats", array("courseid"=>$courseid, "groupid"=>$group));
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
		
		// For each dimension, obtain mean and desviation
		$mean_array = array();
		$deviation_array = array();
		$gmean_array = array();
		$gdeviation_array = array();
		
		// Course values
		list($mean_array[],$deviation_array[]) = sepug_get_dim_results($stats, $DIM_PLANIF);
		list($mean_array[],$deviation_array[]) = sepug_get_dim_results($stats, $DIM_COMP_DOC);
		list($mean_array[],$deviation_array[]) = sepug_get_dim_results($stats, $DIM_EV_APREND);
		list($mean_array[],$deviation_array[]) = sepug_get_dim_results($stats, $DIM_AMB);
		
		// Global values
		list($gmean_array[],$gdeviation_array[]) = sepug_get_dim_results($gstats, $DIM_PLANIF);
		list($gmean_array[],$gdeviation_array[]) = sepug_get_dim_results($gstats, $DIM_COMP_DOC);
		list($gmean_array[],$gdeviation_array[]) = sepug_get_dim_results($gstats, $DIM_EV_APREND);
		list($gmean_array[],$gdeviation_array[]) = sepug_get_dim_results($gstats, $DIM_AMB);
		
		// Write results into the file
		$table->data[] = array(get_string("dim1","sepug"),round($mean_array[0],2),round($deviation_array[0],2),round($gmean_array[0],2),round($gdeviation_array[0],2));
		$table->data[] = array(get_string("dim2","sepug"),round($mean_array[1],2),round($deviation_array[1],2),round($gmean_array[1],2),round($gdeviation_array[1],2));
		$table->data[] = array(get_string("dim3","sepug"),round($mean_array[2],2),round($deviation_array[2],2),round($gmean_array[2],2),round($gdeviation_array[2],2));
		$table->data[] = array(get_string("dim4","sepug"),round($mean_array[3],2),round($deviation_array[3],2),round($gmean_array[3],2),round($gdeviation_array[3],2));

	}

    echo html_writer::table($table);
}


/** SEPUG
 * @global object $CFG
 * @global object $SEPUG_GHEIGHT
 * @global object $SEPUG_GWIDTH
 * @param string $url
 */
function sepug_print_global_results_graph($url){
	global $CFG, $SEPUG_GHEIGHT, $SEPUG_GWIDTH;
		 
	echo "<img class='resultgraph' align=\"middle\" height=\"$SEPUG_GHEIGHT\" width=\"$SEPUG_GWIDTH\"".
         " src=\"$CFG->wwwroot/mod/sepug/graph.php?$url\" alt=\"".get_string("sepuggraph", "sepug")."\" />"; 
}


/**
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

    // GRADO and POSTGRADO only expect one answer per question (contains question types 1 and 2)
    $oneanswer = ($question->type == 1 || $question->type == 2) ? true : false;

    // They use radio button elements
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
		
		// Add question to checklist to check later survey_view.php y sepug.js that the question has an answer
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

function sepug_cron(){
	global $DB;
	
	if(!$survey = $DB->get_record("sepug", array("sepuginstance"=>1))){
		print_error('sepug_not_found', 'sepug');
	}
	else{
	
		// Si es momento de generar resultados, completamos las tablas sepug_prof_stats y sepug_global_stats
		$checktime = time();
		if (($survey->timeopen < $checktime) AND ($survey->timeclose > $checktime) 
			AND ($survey->timeclosestudents < $checktime)) {
			
			// Si no hemos realizado ya el proceso (solo debe hacerse una vez)
			if (!$DB->record_exists("sepug_global_stats", array())) {
				
				// Tenemos que completar los datos de todos los cursos y por cada grupo interno que haya
				$allcourses = sepug_get_valid_courses();
				foreach($allcourses as $course){
					
					$groups = groups_get_all_groups($course->id);
					foreach($groups as $gr){
						sepug_insert_prof_stats($course->id, $gr->id);
					}
					// El grupo 0 (por defecto) siempre se ejecuta
					sepug_insert_prof_stats($course->id, 0);
					
				}
				
				sepug_insert_global_stats();
			}
		}
	}
}
