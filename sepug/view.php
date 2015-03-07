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
 * This file is responsible for displaying a select form to complete surveys or to see the results
 *
 * @package   mod-sepug
 * @copyright 2014 Alejandro Molina Salazar
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

    require_once("../../config.php");
    require_once("lib.php");
	require_once('sepug_surveyselect_form_class.php');
	
	global $USER, $DB;

    $id = required_param('id', PARAM_INT);    // Course Module ID

    if (! $cm = get_coursemodule_from_id('sepug', $id)) {
        print_error('invalidcoursemodule');
    }

    if (! $course = $DB->get_record("course", array("id"=>$cm->course))) {
        print_error('coursemisconf');
    }
	
    $PAGE->set_url('/mod/sepug/view.php', array('id'=>$id));
    require_login($course, false, $cm);
    $context = context_module::instance($cm->id);

    require_capability('mod/sepug:participate', $context);

	// Si no esta creada la instancia de SEPUG
    if (! $survey = $DB->get_record("sepug", array("id"=>$cm->instance))) {
        print_error('invalidsurveyid', 'sepug');
    }
	
	// Update 'viewed' state if required by completion system
	require_once($CFG->libdir . '/completionlib.php');
	$completion = new completion_info($course);
	$completion->set_module_viewed($cm);
	/*
	// Si es un usuario invitado, no le damos acceso	
	if (!is_enrolled($context)) {
        echo $OUTPUT->notification(get_string("guestsnotallowed", "sepug"));
    }
	
	if (!$cm->visible) {
        notice(get_string("activityiscurrentlyhidden"));
    }
*/

    /*$PAGE->set_title('hola');
    $PAGE->set_heading('hola');
    echo $OUTPUT->header();
	// texto grande
	echo $OUTPUT->heading(get_string("surveycompleted", "sepug"));
	// Pie pagina
    echo $OUTPUT->footer();*/
	
	
	
	
	

	$PAGE->set_title(get_string("modulename","sepug"));
    $PAGE->set_heading(get_string("modulename","sepug"));
	echo $OUTPUT->header();
	echo $OUTPUT->heading(get_string("modulename_full", "sepug"));
	echo $OUTPUT->box(get_string("view_intro", "sepug"), 'generalbox', 'intro');
	
	// Si sepug NO esta activo para alumnos
    $checktime = time();
    if (($survey->timeopen > $checktime) OR ($survey->timeclose < $checktime)) {
			
		/*
		// Todos ID de los cursos de moodle
		$courses = $DB->get_records("course", array(), '', 'id');
		foreach($courses as $cid){
			$cntxt = get_context_instance(CONTEXT_COURSE, $cid);
			if(is_enrolled($cntxt, $USER->id, '', true)){
				$students = get_role_users(5 , $cntxt);
				$teachers = get_role_users(2 , $cntxt);
				
			}
			//$enrolled = is_enrolled($context, $USER->id, '', true); devuelve solo alumnos y profesores
		}*/
		
		echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
        echo $OUTPUT->notification(get_string('sepug_is_not_open', 'sepug'));
        echo $OUTPUT->continue_button($CFG->wwwroot.'/course/view.php?id=1');
        echo $OUTPUT->box_end();
        echo $OUTPUT->footer();
        exit;
    }
	else{
		
		// Obtiene todos los cursos en los que esta matriculado - r: array asociativo(ids cursos)
		$courses = enrol_get_all_users_courses($USER->id, true, null, 'visible DESC, sortorder ASC');
		
		// Si no esta matriculado en ningun curso o solo al curso general (id=1), no es profesor ni alumno
		if(empty($courses) or (count($courses)==1 and array_keys($courses) == 1)){
			echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
			echo $OUTPUT->notification(get_string('no_courses', 'sepug'));
			echo $OUTPUT->continue_button($CFG->wwwroot.'/course/view.php?id=1');
			echo $OUTPUT->box_end();
			echo $OUTPUT->footer();
			exit;
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
				}
				// Si no lo es, pero si es estudiante
				else if($rol->roleid == 5){
					array_push($stud_courses, $cid);
				}
			}
		}
		
		// Si es estudiante
		if(!empty($stud_courses)){
			$checktime = time();
			// pero se encuentra fuera de plazo
			if ($survey->timeclosestudents < $checktime){
				echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
				echo $OUTPUT->notification(get_string('sepug_is_not_open', 'sepug'));
				echo $OUTPUT->continue_button($CFG->wwwroot.'/course/view.php?id='.$course->id);
				echo $OUTPUT->box_end();
				echo $OUTPUT->footer();
				exit;
			}
			else{
				// Informa del periodo de cierre para los alumnos
				$timeclosestudents = date('d', $survey->timeclosestudents).' del '.date('m', $survey->timeclosestudents).' a las '.date('H', $survey->timeclosestudents).':'.date('i', $survey->timeclosestudents).' horas';
				echo $OUTPUT->notification(get_string('closestudentsdate', 'sepug', $timeclosestudents));
				
				$courses_list[0] = 'Cursos...';
				foreach($stud_courses as $cid){
					if($course = $DB->get_record("course", array("id"=>$cid))){
						$courses_list[$cid] = $course->fullname;
					}
				}
				
				// imprimirselect, cursos no ha hecho ya sepug_already_done($survey->id, $USER->id)
				$mform = new surveyselect_form('survey_view.php', array('courses'=>$courses_list));
				$mform->set_data(array('cmid'=>$id));
				//$add_item_form = new feedback_edit_add_question_form('edit_item.php');
				echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
				echo '<div class="mdl-align">';
				$mform->display();
				echo '</div>';
				echo $OUTPUT->box_end();
				echo $OUTPUT->footer();
				
				
				//echo html_writer::start_tag('form', array('id' => 'selectform', 'method' => 'post', 'action' => ''));
				//echo html_writer::select($options, 'selectmenu', '0', false, array('onchange' => 'this.form.submit()'));
				//echo html_writer::end_tag('form');
				
				/*
				//imprimirselect, cursos no ha hecho ya sepug_already_done($survey->id, $USER->id) y que no sea profesor de ellos
				// si select vacio (xk seas profesor de todas las asignaturas o xk ya hayas completado todos cuestionarios...)
				echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
				echo '<div class="mdl-align">';
				echo '<form>';
				echo '<fieldset>';
				echo '<label>Lista cursos que estudias: </label>';
				echo '<select name="courses_list"/>';
				foreach($stud_courses as $cid){
					if($course = $DB->get_record("course", array("id"=>$cid))){
						echo '<option value="'.$cid.'">'.$course->fullname.'</option>';
					}
				}
				echo '</select>';
				echo '<button type="submit">'.get_string('go_to_survey', 'sepug').'</button>';
				//echo $OUTPUT->help_icon('mapcourse', 'feedback');
				echo '</fieldset>';
				echo '</form>';
				echo '<br />';
				echo '</div>';
				echo $OUTPUT->box_end();
				echo $OUTPUT->footer();
				*/
			}
		}
		
		// Si es profesor
		if(!empty($prof_courses)){
			$checktime = time();
			// pero todavia no estan listos los resultados
			if ($survey->timeclosestudents > $checktime){
				// Informa del periodo del cierre para los profesores
				$timeclose = date('d', $survey->timeclose).' del '.date('m', $survey->timeclose).' a las '.date('H', $survey->timeclose).':'.date('i', $survey->timeclose).' horas';
				echo $OUTPUT->notification(get_string('closedate', 'sepug', $timeclose));
			
			
				echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
				echo $OUTPUT->notification(get_string('no_results', 'sepug'));
				echo $OUTPUT->continue_button($CFG->wwwroot.'/course/view.php?id='.$course->id);
				echo $OUTPUT->box_end();
				echo $OUTPUT->footer();
				exit;
			}
			else{
			
				foreach($prof_courses as $cid){
					if($course = $DB->get_record("course", array("id"=>$cid))){
						$courses_list[$cid] = $course->fullname;
					}
				}
			
				$mform = new surveyselect_form(null, array('courses'=>$courses_list));
				echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
				echo '<div class="mdl-align">';
				$mform->display();
				echo '</div>';
				echo $OUTPUT->box_end();
				echo $OUTPUT->footer();
			
				// si estan listos, imprimir select igual para ir resultados
				echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
				echo '<div class="mdl-align">';
				echo '<form>';
				echo '<fieldset>';
				echo '<label>Lista cursos de los que eres profesor/a: </label>';
				echo '<select name="courses_list"/>';
				foreach($prof_courses as $cid){
					if($course = $DB->get_record("course", array("id"=>$cid))){
						echo '<option value="'.$cid.'">'.$course->fullname.'</option>';
					}
				}
				echo '</select>';
				echo '<button type="submit">'.get_string('go_to_results', 'sepug').'</button>';
				//echo $OUTPUT->help_icon('mapcourse', 'feedback');
				echo '</fieldset>';
				echo '</form>';
				echo '<br />';
				echo '</div>';
				echo $OUTPUT->box_end();
				echo $OUTPUT->footer();
			}
		}
	}	

