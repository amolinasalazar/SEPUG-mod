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
require_once("$CFG->libdir/odslib.class.php");

global $FILTRO_CURSOS;

// Check that all the parameters have been provided.
$cmid = required_param('cmid', PARAM_INT);    // Course Module ID
$cid = optional_param('cid', '1', PARAM_INT);    // Course ID
$type  = optional_param('type', 'ods', PARAM_ALPHA);
$group = optional_param('group', 0, PARAM_INT);

if (! $cm = get_coursemodule_from_id('sepug', $cmid)) {
    print_error('invalidcoursemodule');
}

if (! $survey = $DB->get_record("sepug", array("id"=>$cm->instance))) {
	print_error('invalidsurveyid', 'sepug');
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

if($type =='ods'){
	
	// ----COMPROBACIONES----
	
	// Ignoramos el curso 1
	if($cid == 1){
		print_error('notvalidcourse','sepug');
	}
	
	if (! $course = $DB->get_record("course", array("id"=>$cid))) {
		print_error('coursemisconf');
	}
	$context = context_course::instance($course->id);

	$PAGE->set_url('/mod/sepug/download.php', array('cmid'=>$cmid, 'cid'=>$cid, 'type'=>$type, 'group'=>$group));

	require_login($course);

	require_capability('mod/sepug:download', $context) ;

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
	
	if (!$DB->record_exists("sepug_prof_stats", array("courseid"=>$cid, "groupid"=>$group)) ) {
		echo $OUTPUT->notification(get_string("no_results","sepug"));
	}
	else{
		// ----FICHERO----

		$courseshortname = format_string($course->shortname, true, array('context' => $context));
		/// Calculate file name
		if($group==0){
			$downloadfilename = clean_filename(strip_tags($courseshortname.' '.format_string($survey->name, true))).'.ods';
		}
		else{
			$downloadfilename = clean_filename(strip_tags($courseshortname.' '.groups_get_group_name($group).' '.format_string($survey->name, true))).'.ods';
		}
		/// Creating a workbook
		$workbook = new MoodleODSWorkbook("-");
		/// Sending HTTP headers
		$workbook->send($downloadfilename);
		/// Creating the first worksheet
		$myxls = $workbook->add_worksheet(textlib::substr(strip_tags(format_string($survey->name,true)), 0, 31));

		// Escribimos una etiqueta por cada grupo que haya en la asignatura
		$info_header = array("Asignatura","Grupo","Alumnos Encuestados");
		$info_data = array($course->fullname,($group!=0) ? groups_get_group_name($group) : 'general',(string)sepug_count_responses($cid, $group));
		$col=0;
		for($i=0; $i<3; $i++) {
			$myxls->write_string(0,$i,$info_header[$i]);
			$myxls->write_string(1,$i,$info_data[$i]);
		}

		// Imprimimos la tabla de frecuencias
		// sepug_print_frequency_table
		$courseid = $course->id;
		
		// Obtenemos resultados de la BD
		if ($stats = $DB->get_records("sepug_prof_stats", array("courseid"=>$courseid, "groupid" => $group))) {
			
			// Obtenemos las frecuencias de los resultados
			$frequencies = sepug_frequency_values($courseid,$group);
			
			// Nos quedamos solo con las categorias globales que esten relacionadas con este curso, una por nivel de profundidad
			$main_categories = sepug_related_categories($courseid);
			
			// Preparamos los arrays para insertar las cabeceras
			$header1 = array("Tabla de Frequencias","","","","","","",get_string("curso","sepug"),"");
			$header2 = array(get_string("questions", "sepug"), get_string("scaleNS", "sepug"), get_string("scale1", "sepug"),
			get_string("scale2", "sepug"),get_string("scale3", "sepug"),get_string("scale4", "sepug"),get_string("scale5", "sepug"), 
			strip_tags(format_text(get_string("mean", "sepug"),true)), strip_tags(format_text(get_string("deviation", "sepug"),true)));
			foreach($main_categories as $cat){
				array_push($header1, $cat->name, "");
				array_push($header2, strip_tags(format_text(get_string("mean", "sepug"),true)), strip_tags(format_text(get_string("deviation", "sepug"),true)));
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
				
				$data = array(strip_tags(format_text(get_string("$question->shorttext","sepug"),true)), $frequencies[$stat->question][0], $frequencies[$stat->question][1], 
				$frequencies[$stat->question][2], $frequencies[$stat->question][3], $frequencies[$stat->question][4], $frequencies[$stat->question][5], 
				strip_tags(format_text($stat->mean,true)), strip_tags(format_text($stat->deviation,true)));	
				
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
		$header1  = array("Tabla de Resultados según Dimensión",get_string("curso","sepug"),"",get_string("universidad","sepug"),"");
		$header2 = array(strip_tags(format_text(get_string("dimension","sepug"),true)),strip_tags(format_text(get_string("mean","sepug"),true)),
		strip_tags(format_text(get_string("deviation","sepug"),true)),
		strip_tags(format_text(get_string("mean","sepug"),true)),strip_tags(format_text(get_string("deviation","sepug"),true)));
		
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
				$data = array(strip_tags(format_text(get_string($dim,"sepug"),true)),$mean_array[$i],$deviation_array[$i],$gmean_array[$i],$gdeviation_array[$i]);
				$myxls->write_string($row,0,$data[0]);
				$myxls->write_string($row,1,$data[1]);
				$myxls->write_string($row,2,$data[2]);
				$myxls->write_string($row,3,$data[3]);
				$myxls->write_string($row,4,$data[4]);
				$row++;
			}
		}
	   
		$workbook->close();
	}

	exit;
	
}
elseif($type=='global'){	

	// ----COMPROBACIONES----
	
	if (! $cm = get_coursemodule_from_id('sepug', $cmid)) {
        print_error('invalidcoursemodule');
    }

    if (! $course = $DB->get_record("course", array("id"=>$cm->course))) {
        print_error('coursemisconf');
    }
	
	$PAGE->set_url('/mod/sepug/download.php', array('cmid'=>$cmid, 'type'=>$type, 'group'=>$group));
    require_login($course, false, $cm);
    
	$context = context_course::instance($course->id);

    require_capability('mod/sepug:global_download', $context);
	
	// Si no hay resultados, no generamos ningun fichero
	if (!$DB->record_exists("sepug_global_stats", array())) {
        echo $OUTPUT->notification(get_string("no_results","sepug"));
    } 
	else{
	
		// ----FICHERO----
		
		/// Calculate file name
		$downloadfilename = clean_filename(format_string('informeglobal.ods',true));
		/// Creating a workbook
		$workbook = new MoodleODSWorkbook("-");
		/// Sending HTTP headers
		$workbook->send($downloadfilename);
		/// Creating the first worksheet
		$myxls = $workbook->add_worksheet(textlib::substr(strip_tags(format_string($survey->name,true)), 0, 31));
		
		
		$row_max_grado = 0;
		// GRADO
		// Obtenemos las categorias unicas
		if($grado_categories = $DB->get_records_sql("SELECT DISTINCT catname FROM {sepug_global_stats} WHERE grado = 1")){
			// Cabecera general de GRADO
			$myxls->write_string(0,0,"GRADO");
			
			// Preparamos los arrays para insertar las cabeceras
			$header1 = array("Tabla de Resultados Globales");
			$header2 = array(get_string("questions", "sepug"));
			foreach($grado_categories as $cat){
				array_push($header1, $cat->catname, "");
				array_push($header2, strip_tags(format_text(get_string("mean", "sepug"),true)), 
				strip_tags(format_text(get_string("deviation", "sepug"),true)));
			}
			for($i=0; $i<count($header1); $i++) {
				$myxls->write_string(2,$i,$header1[$i]);
				$myxls->write_string(3,$i,$header2[$i]);
			}
			
			$question_col = false;
			$col_offset = 1;
			foreach($grado_categories as $cat){
				$row=4;
				
				// Obtenemos resultados de la BD
				if ($stats = $DB->get_records("sepug_global_stats", array("catname"=>$cat->catname, "grado"=>1))) {
					
					// Imprimimos primero todas las preguntas de la columna 0, SOLO LA PRIMERA VEZ
					if(!$question_col){
						$questions = $DB->get_records("sepug_global_stats",array("catname"=>$cat->catname,"grado"=>1),"question");
						foreach($questions as $question) {
							$q_text = $DB->get_record("sepug_questions",array("id"=>$question->question),"shorttext");
							$myxls->write_string($row,0,strip_tags(format_text(get_string("$q_text->shorttext","sepug"),true)));
							$row++;
						}
						$question_col = true;
						$row_max_grado = $row;
						$row=4;
					}

					$data = array();
					foreach ($stats as $stat){
						 
						$data[] = $stat->mean;
						$data[] = $stat->deviation;				
						
						
						for($i=0; $i<count($data); $i++){
							$myxls->write_string($row,$i+$col_offset,$data[$i]);
						}
						$row++;
						$data = array();
					}
				$col_offset+=2;
				}
			}
		}
		
		// POSTGRADO
		$row_max_grado++;
		// Obtenemos las categorias unicas
		if($postgrado_categories = $DB->get_records_sql("SELECT DISTINCT catname FROM {sepug_global_stats} WHERE grado = 0")){
			// Cabecera general de POSTGRADO
			$myxls->write_string($row_max_grado,0,"POSTGRADO");
			
			// Preparamos los arrays para insertar las cabeceras
			$header1 = array("Tabla de Resultados Globales");
			$header2 = array(get_string("questions", "sepug"));
			foreach($postgrado_categories as $cat){
				array_push($header1, $cat->catname, "");
				array_push($header2, strip_tags(format_text(get_string("mean", "sepug"),true)), 
				strip_tags(format_text(get_string("deviation", "sepug"),true)));
			}
			for($i=0; $i<count($header1); $i++) {
				$myxls->write_string($row_max_grado+2,$i,$header1[$i]);
				$myxls->write_string($row_max_grado+3,$i,$header2[$i]);
			}
			
			$question_col = false;
			$col_offset = 1;
			foreach($postgrado_categories as $cat){
				$row = $row_max_grado+4;
				
				// Obtenemos resultados de la BD
				if ($stats = $DB->get_records("sepug_global_stats", array("catname"=>$cat->catname, "grado"=>0))) {
					
					// Imprimimos primero todas las preguntas de la columna 0, SOLO LA PRIMERA VEZ
					if(!$question_col){
						$questions = $DB->get_records("sepug_global_stats",array("catname"=>$cat->catname,"grado"=>0),"question");
						foreach($questions as $question) {
							$q_text = $DB->get_record("sepug_questions",array("id"=>$question->question),"shorttext");
							$myxls->write_string($row,0,strip_tags(format_text(get_string("$q_text->shorttext","sepug"),true)));
							$row++;
						}
						$question_col = true;
						$row = $row_max_grado+4;
					}

					$data = array();
					foreach ($stats as $stat){
						 
						$data[] = $stat->mean;
						$data[] = $stat->deviation;				
						
						
						for($i=0; $i<count($data); $i++){
							$myxls->write_string($row,$i+$col_offset,$data[$i]);
						}
						$row++;
						$data = array();
					}
				$col_offset+=2;
				}
			}
		}
		
		$workbook->close();

		exit;
	}
}


exit;
