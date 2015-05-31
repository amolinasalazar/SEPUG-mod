<?php
/*
	© Universidad de Granada. Granada – 2014
	© Rosana Montes Soldado y Alejandro Molina Salazar (amolinasalazar@gmail.com). Granada – 2014
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
 
require_once($CFG->libdir . "/externallib.php");

class mod_sepug_external extends external_api {
	
	/**
     * Describes the parameters for get_sepug_instance.
     * @return external_function_parameters
     */
    public static function get_sepug_instance_parameters() {
		return new external_function_parameters(array());
    }
	
	/**
     * Returns the details of the sepug instance, if it exists
     *
     * @return array sepug instance details
     */
	 public static function get_sepug_instance() {
        global $DB;
		
		// Retrieve the sepug instance
		if (!$sepuginstance = $DB->get_record("sepug", array("sepuginstance"=>1))){
            return null;
        }
		
		//--SECURITY CHECKS--// 
		
		// Course context validation
		$modinfo = get_fast_modinfo($sepuginstance->course);
		$cm = $modinfo->get_instances_of('sepug'); //Course module
		$context = context_module::instance($cm[$sepuginstance->id]->id);
		try {
			self::validate_context($context);
		} catch (Exception $e) {
            return null;
		}
	
		return $sepuginstance;
    }
	/**
     * Describes the get_sepug_instance return value.
     *
     * @return external_single_structure
     */
    public static function get_sepug_instance_returns() {
        return new external_single_structure(
			array(
				'id' => new external_value(PARAM_INT, 'Sepug id'),
				'course' => new external_value(PARAM_INT, 'Course id'),
				'name' => new external_value(PARAM_TEXT, 'Course full name'),
				'timecreated' => new external_value(PARAM_INT, 'Time created'),
				'timemodified' => new external_value(PARAM_INT, 'Time modified'),
				'timeopen' => new external_value(PARAM_INT, 'Time to open'),
				'timeclosestudents' => new external_value(PARAM_INT, 'Time to close for students'),
				'timeclose' => new external_value(PARAM_INT, 'Time to close definitely')
			), 'sepug'
		);
    }

    /**
     * Describes the parameters for get_sepug_instance.
     * @return external_function_parameters
     */
    public static function get_not_submitted_enrolled_courses_as_student_parameters() {
		return new external_function_parameters(array());
    }

    /**
     * Returns the details of the sepug instance, if it exists
     *
     * @return array sepug instance details
     */
	public static function get_not_submitted_enrolled_courses_as_student() {
        global $DB, $CFG, $USER;
		require_once($CFG->dirroot . "/mod/sepug/lib.php");
		
		//--SECURITY CHECKS--// 
		
		// Check if exists a SEPUG instance
		if (!$sepug = $DB->get_record("sepug", array("sepuginstance"=>1))) {
            return array();
        }
		
		// Check if SEPUG is activated for students
		$checktime = time();
		if (($sepug->timeopen > $checktime) OR ($sepug->timeclose < $checktime) 
			OR ($sepug->timeclosestudents < $checktime)){
			return array();
		}
		
		//--ACTION--//
		
		// Array to store the feedbacks to return.
        $courses = sepug_get_enrolled_valid_courses($sepug);
		
		// Si no esta matriculado en ningun curso o solo al curso general (id=1), no es profesor ni alumno
		if(empty($courses) or (count($courses)==1 and array_keys($courses) == 1)){
			return array();
		}
		
		// Si esta matriculado en algo
		$stud_courses = array();
		$prof_courses = array();
		foreach($courses as $course){
			$cid = $course->id;
			$cntxt = get_context_instance(CONTEXT_COURSE, $cid);
			// Obtenemos todos los roles de este contexto - r: array asoc.(ids rol)
			$roles = get_user_roles($cntxt, $USER->id, false, 'c.contextlevel DESC, r.sortorder ASC');
			foreach($roles as $rol){
				// Si es profesor de este curso
				if($rol->roleid == 3){
					array_push($prof_courses, $cid);
					if(in_array($cid, $stud_courses)){
						array_pop($stud_courses);
					}
				}
				// Si no lo es, pero si es estudiante
				else if($rol->roleid == 5 && !in_array($cid, $prof_courses)){
					array_push($stud_courses, $cid);
				}
			}
		}
		
		// Montamos la lista de cursos para el select
		//$courses_list[0] = 'Cursos...';
		$courses_list = array();
		foreach($stud_courses as $cid){
			// Comprobamos que ese curso no tenga grupos internos..
			$groups = groups_get_user_groups($cid,$USER->id);
			if(empty($groups[0])){
				if($course = $DB->get_record("course", array("id"=>$cid)) and !sepug_already_done($cid, $USER->id)){
					
					$return = new stdClass();
					$return->id = (int)$cid;
					$return->fullname = $course->fullname;
					$return->groupid = 0;
					$return->groupname = "";
					
					$courses_list[] = (array)$return;
					
					//$courses_list[] = array("id"=>$cid, "fullname"=>$course->fullname);
					//$courses_list['id'] = $cid;
					//$courses_list['fullname'] = $course->fullname;
					//$courses_list[$cid] = $course->fullname;
				}
			}
			else{
				//$ya_introducido = false;
				foreach($groups[0] as $group){
					if($course = $DB->get_record("course", array("id"=>$cid)) and !sepug_already_done($cid, $USER->id, $group) /*and
					!$ya_introducido*/){
						
						$return = new stdClass();
						$return->id = (int)$cid;
						$return->fullname = $course->fullname;
						$return->groupid = $group;
						$group_name = $DB->get_record("groups", array("id"=>$group),"name");
						$return->groupname = $group_name->name;
	
						$courses_list[] = (array)$return;
						
						//$courses_list[] = array("id"=>$cid, "fullname"=>$course->fullname);
						//$courses_list[$cid] = $course->fullname;
						//$ya_introducido = true;
					}
				}
			}
		}
		
		return $courses_list;
    }

    /**
     * Describes the get_sepug_instance return value.
     *
     * @return external_multiple_structure
     */
    public static function get_not_submitted_enrolled_courses_as_student_returns() {
		return new external_multiple_structure(
			new external_single_structure(
                array(
					'id' => new external_value(PARAM_INT, 'Course ID'),
					'fullname' => new external_value(PARAM_TEXT, 'Course full name'),
					'groupid' => new external_value(PARAM_INT, 'Group ID'),
					'groupname' => new external_value(PARAM_TEXT, 'Group name')
				)
            )
        );
    }
	
	/**
     * Describes the parameters for get_sepug_instance.
     * @return external_function_parameters
     */
    public static function get_survey_questions_parameters() {
		return new external_function_parameters(
			array('courseid' => new external_value(PARAM_INT, 'Course ID'))
        );
    }
	
	/**
     * Returns the details of the sepug instance, if it exists
     *
     * @return array sepug instance details
     */
	 public static function get_survey_questions($courseid) {
        global $CFG, $DB, $USER;
		require_once($CFG->dirroot . "/mod/sepug/lib.php");
        global $FILTRO_CURSOS;
		
		//--SECURITY CHECKS--// 
		
		//Parameter validation
        $params = self::validate_parameters(self::get_survey_questions_parameters(), array('courseid' => $courseid));

		// Course context validation
		$context = context_course::instance($params['courseid'], IGNORE_MISSING);
		try {
			self::validate_context($context);
		} catch (Exception $e) {
            $exceptionparam = new stdClass();
            $exceptionparam->message = $e->getMessage();
            $exceptionparam->courseid = $params['courseid'];
            throw new moodle_exception('errorcoursecontextnotvalid', 'webservice', '', $exceptionparam);
		}
		
		require_capability('mod/sepug:participate', $context);
		
		// Check if exists a SEPUG instance
		if (!$sepug = $DB->get_record("sepug", array("sepuginstance"=>1))) {
            throw new moodle_exception('sepugnotfound', 'sepug');
        }
		
		// Check if SEPUG is activated for students
		$checktime = time();
		if (($sepug->timeopen > $checktime) OR ($sepug->timeclose < $checktime) 
			OR ($sepug->timeclosestudents < $checktime)){
			throw new moodle_exception('sepug_is_not_open', 'sepug');
		}

		// Check if the user is enrolled to this course
		if (!is_enrolled($context)) {
			throw new moodle_exception("guestsnotallowed", "sepug");
		}
			
		// We get all the roles in this context - r: array asoc.(ids rol)
		$roles = get_user_roles($context, $USER->id, false, 'c.contextlevel DESC, r.sortorder ASC');
		$studentrole = false;
		$editingteacherrole = false;
		foreach($roles as $rol){
			if($rol->roleid == 3){
				$editingteacherrole=true;
			}
			if($rol->roleid == 5){
				$studentrole = true;
			}
		}
		// If the user is not student...
		if(!$studentrole || $editingteacherrole){
			throw new moodle_exception('onlystudents', 'sepug');
		}
		
		// Check if the courses filter accept this course
		if($FILTRO_CURSOS && !sepug_courseid_validator($params['courseid'])){
			throw new moodle_exception('coursesfilterexception', 'sepug');
		}	
		
		// Check if the survey is not already 
		if (sepug_already_done($params['courseid'], $USER->id)) {
			throw new moodle_exception("alreadysubmitted", 'sepug');
		}
		
		
		//--ACTION--//
		
		// Primero obtenemos el template
		$tmpid = sepug_get_template($params['courseid']);
		if (! $template = $DB->get_record("sepug", array("id"=>$tmpid))) {
			throw new moodle_exception('invalidtmptid', 'sepug');
		}
		
		// Obtenemos las preguntas de la plantilla
		if (! $questions = $DB->get_records_list("sepug_questions", "id", explode(',', $template->questions))) {
			throw new moodle_exception('cannotfindquestion', 'sepug');
		}
		$questionorder = explode( ",", $template->questions);
		
		// Array to store the survey questions to return.
		$arritems = array();

		// Cycle through all the questions in order and save them
		foreach ($questionorder as $key => $val) {
			$question = $questions["$val"];
			$question->id = $val;

			if ($question->type >= 0) {
				
				if($question->multi){
					
					$subquestions = $DB->get_records_list("sepug_questions", "id", explode(',', $question->multi));
					
					foreach ($subquestions as $q) {
			
						// Create object to return.
						$item = new stdClass();
						$item->id = (int)$q->id;
						$item->type = $q->type;
						if ($q->text) {
							$item->text = get_string($q->text, "sepug");
						}

						/*if ($q->shorttext) {
							$item->shorttext = get_string($q->shorttext, "sepug");
						}*/

						if ($q->options) {
							$item->options = get_string($q->options, "sepug");
						}
					
						// Add the single item to the array of items
						$arritems[] = (array)$item;
					}
						
						
				}
					
				
				else{
					
					// Create object to return.
					$item = new stdClass();
					$item->id = (int)$question->id;
					$item->type = $question->type;
					
					if ($question->text) {
						$item->text = get_string($question->text, "sepug");
					}

					/*if ($question->shorttext) {
						$item->shorttext = get_string($question->shorttext, "sepug");
					}*/

					if ($question->options) {
						$item->options = get_string($question->options, "sepug");
					}
					
					// Add the single item to the array of items
					$arritems[] = (array)$item;
				}
					
					
			}
		}

		return $arritems;
    }

	/**
     * Describes the get_sepug_instance return value.
     *
     * @return external_multiple_structure
     */
    public static function get_survey_questions_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'Item id'),
                    'type' => new external_value(PARAM_INT, 'Type'),
                    'text' => new external_value(PARAM_TEXT, 'Text'),
                    /*'shorttext' => new external_value(PARAM_TEXT, 'Shorttext'),*/
                    'options' => new external_value(PARAM_RAW, 'Options (answers)'),
					'items'
                )
            )
        );
    }
	
	
	
	/**
     * Describes the parameters for get_sepug_instance.
     * @return external_function_parameters
     */
    public static function submit_survey_parameters() {
		return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'feedback ID'),
				'groupid' => new external_value(PARAM_INT, 'group ID'),
                'itemvalues' => new external_multiple_structure(
					new external_single_structure(
						array(
							'questionid' => new external_value(PARAM_INT, 'question ID'),
							'time' => new external_value(PARAM_INT, 'Time'),
							'answer' => new external_value(PARAM_TEXT, 'item value')
						),'survey answers'
					)
				)
			)
		);
    }
	
	/**
     * Returns the details of the sepug instance, if it exists
     *
     * @return array sepug instance details
     */
	public static function submit_survey($courseid, $groupid, $itemvalues) {
        global $DB, $USER, $CFG;
		require_once($CFG->dirroot . "/mod/sepug/lib.php");
        global $FILTRO_CURSOS;

		//--SECURITY CHECKS--// 
		
		//Parameter validation
        $params = self::validate_parameters(self::submit_survey_parameters(), array('courseid' => $courseid, 'groupid' => $groupid, 'itemvalues'=>$itemvalues));
		
		// Course context validation
		$context = context_course::instance($params['courseid'], IGNORE_MISSING);
		try {
			self::validate_context($context);
		} catch (Exception $e) {
            $exceptionparam = new stdClass();
            $exceptionparam->message = $e->getMessage();
            $exceptionparam->courseid = $params['courseid'];
            throw new moodle_exception('errorcoursecontextnotvalid', 'webservice', '', $exceptionparam);
		}
		
		require_capability('mod/sepug:participate', $context);
		
		// Check if exists a SEPUG instance
		if (!$sepug = $DB->get_record("sepug", array("sepuginstance"=>1))) {
            throw new moodle_exception('sepugnotfound', 'sepug');
        }
		
		// Check if SEPUG is activated for students
		$checktime = time();
		if (($sepug->timeopen > $checktime) OR ($sepug->timeclose < $checktime) 
			OR ($sepug->timeclosestudents < $checktime)){
			throw new moodle_exception('sepug_is_not_open', 'sepug');
		}

		// Check if the user is enrolled to this course
		if (!is_enrolled($context)) {
			throw new moodle_exception("guestsnotallowed", "sepug");
		}
		
		// We get all the roles in this context - r: array asoc.(ids rol)
		$roles = get_user_roles($context, $USER->id, false, 'c.contextlevel DESC, r.sortorder ASC');
		$studentrole = false;
		$editingteacherrole = false;
		foreach($roles as $rol){
			if($rol->roleid == 3){
				$editingteacherrole=true;
			}
			if($rol->roleid == 5){
				$studentrole = true;
			}
		}
		// If the user is not student...
		if(!$studentrole || $editingteacherrole){
			throw new moodle_exception('onlystudents', 'sepug');
		}
		
		// Check if the courses filter accept this course
		if($FILTRO_CURSOS && !sepug_courseid_validator($params['courseid'])){
			throw new moodle_exception('coursesfilterexception', 'sepug');
		}	
		
		// Check if the survey is not already done
		if (sepug_already_done($params['courseid'], $USER->id)) {
			throw new moodle_exception("alreadysubmitted", 'sepug');
		}
		
		$idarray = sepug_get_questions_ID($params['courseid'], true);
		$IDsalready = array();
		foreach($params['itemvalues'] as $item){
			
			// Check if the question ID is valid and is not already processed
			if(!in_array($item['questionid'],$idarray) or in_array($item['questionid'], $IDsalready)){
				throw new moodle_exception('responsevaluenotvalid', 'sepug');
			}
			
			// Check if the response values are valid
			if(!sepug_response_value_validator($item['answer'], $item['questionid'])){
				throw new moodle_exception('responsevaluenotvalid', 'sepug');
			}
			
			$IDsalready[] = $item['questionid'];
		}
		
		//--ACTION--//
		
		$transaction = $DB->start_delegated_transaction();

		foreach ($params['itemvalues'] as $item) {
			
			$newdata = new stdClass();
			$newdata->time = $item['time'];
			$newdata->userid = $USER->id;
			$newdata->courseid = $params['courseid'];
			$newdata->question = $item['questionid'];
			$newdata->groupid = $params['groupid'];
			if (!empty($item['answer'])) {
				$newdata->answer1 = $item['answer'];
			} else {
				$newdata->answer1 = "";
			}

			$DB->insert_record("sepug_answers", $newdata);
		}
		
		$transaction->allow_commit();
		
		return null;
    }
	
	/**
     * Describes the get_sepug_instance return value.
     *
     * @return null
     */
	public static function submit_survey_returns() {
        return null;
    }
	
	
	
	
}