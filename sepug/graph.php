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
    require_once("$CFG->libdir/graphlib.php");
    require_once("lib.php");

	$cid   = required_param('cid', PARAM_INT);    // Course ID
    $type  = required_param('type', PARAM_FILE);  // Graph Type
    $group = optional_param('group', 0, PARAM_INT);  // Group ID
    $sid   = optional_param('sid', false, PARAM_INT);  // Student ID
    $qid   = optional_param('qid', 0, PARAM_INT);  // Group ID

    $url = new moodle_url('/mod/sepug/graph.php', array('cid'=>$cid, 'type'=>$type));
    if ($group !== 0) {
        $url->param('group', $group);
    }
    if ($sid !== false) {
        $url->param('sid', $sid);
    }
    if ($qid !== 0) {
        $url->param('qid', $qid);
    }
    $PAGE->set_url($url);

	if (! $course = $DB->get_record("course", array("id"=>$cid))) {
        print_error('coursemisconf');
    }

    if ($sid) {
        if (!$user = $DB->get_record("user", array("id"=>$sid))) {
            print_error('invaliduserid');
        }
    }

	require_login($course);
	
	$context = context_course::instance($course->id);

    if (!has_capability('mod/sepug:readresponses', $context)) {
        if ($type != "student.png" or $sid != $USER->id ) {
            print_error('nopermissiontoshow');
        } else if ($groupmode and !groups_is_member($group)) {
            print_error('nopermissiontoshow');
        }
    }

    $stractual = get_string("actual", "sepug");
    $stractualclass = get_string("actualclass", "sepug");

    $strpreferred = get_string("preferred", "sepug");
    $strpreferredclass = get_string("preferredclass", "sepug");

    if ($sid || isset($user)) {
        $stractualstudent = get_string("actualstudent", "sepug", fullname($user));
        $strpreferredstudent = get_string("preferredstudent", "sepug", fullname($user));
    }

    $virtualscales = false; //set default value for case clauses

    switch ($type) {

     case "question.png":
	 
	 // Obtenemos las categorias relacionadas con el curso
	$main_categories = sepug_related_categories($cid);
	
	// Obtenemos la media del curso y grupo actual (METER GRUPO)
	$x_data = array();
	$mean_array = array();
	$stats = $DB->get_records("sepug_prof_stats",array("courseid"=>$cid));
	$result = array_pop($stats);
	$mean_array[] = $result->mean;
	$x_data[] = get_string("curso","sepug");
	
	// Localizamos si las categorias son de GRADO o POSTGRADO
	$survey = $DB->get_record("sepug", array("sepuginstance"=>1));
	if(!$DB->get_records_sql("SELECT * FROM {course_categories} WHERE id = ".$course->category." AND path LIKE '/".$survey->catgrado."%' AND visible = 1")){
		$grado = 0;
	}
	else{
		$grado = 1;
	}
	
	// Hallamos los valores de la ultima pregunta de las encuestas en cada categoria
	foreach($main_categories as $cat){
		$question_max = $DB->get_record_sql("SELECT MAX(question) AS maxq FROM {sepug_global_stats} WHERE catname = '".$cat->name."' AND grado =".$grado);
		$result = $DB->get_record("sepug_global_stats",array("question"=>(int)$question_max->maxq, "catname"=>$cat->name, "grado"=>$grado));
		$mean_array[] = $result->mean;
	}
	
	// Creamos el grafico de barras
    $graph = new graph($SEPUG_GWIDTH,$SEPUG_GHEIGHT);
    $graph->parameter['title'] = get_string("global_graph_title","sepug");
	
	
	for ($i=0; $i<count($main_categories); $i++){
		$x_data[] = $main_categories[$i]->name; 
	}

	$graph->parameter['bar_size']    = 0.15;
	$graph->parameter['legend']        = 'outside-top';
	$graph->parameter['legend_border'] = 'black';
	$graph->parameter['legend_offset'] = 4;
	
	$graph->parameter['legend_size'] = 12;
	$graph->parameter['label_size'] = 12;
	$graph->parameter['axis_size'] = 10;
	
	$graph->parameter['y_max_left']= 5; // valor maximo y
	$graph->parameter['y_resolution_left']= 2; // redondeo
	$graph->parameter['y_decimal_left']= 1; // cuantos valores decimales en y
	$graph->parameter['x_axis_angle']  = 40; // angulo rotacion de los label de x
	
	$graph->x_data = $x_data;
    $graph->y_data['mean'] = $mean_array;
	$graph->y_format['mean'] = array('colour' => 'ltltorange', 'bar' => 'fill',
                                            'shadow_offset' => '4', 'legend' => get_string("mean", "sepug"), 'bar_size' => 0.3);
	$graph->y_order = array('mean');
	$graph->draw();

    break;



     case "multiquestion.png":

       $question  = $DB->get_record("sepug_questions", array("id"=>$qid));
       $question->text = get_string($question->text, "sepug");
       $question->options = get_string($question->options, "sepug");

       $options = explode(",",$question->options);
       $questionorder = explode( ",", $question->multi);

       $qqq = $DB->get_records_list("sepug_questions", "id", explode(',',$question->multi));

       foreach ($questionorder as $i => $val) {
           $names[$i] = get_string($qqq["$val"]->shorttext, "sepug");
           $buckets1[$i] = 0;
           $buckets2[$i] = 0;
           $count1[$i] = 0;
           $count2[$i] = 0;
           $indexof[$val] = $i;
           $stdev1[$i] = 0;
           $stdev2[$i] = 0;
       }

		$aaa = $DB->get_records_select("sepug_answers", "((courseid = ?) AND (question in ($question->multi)))", array($cid));
	
       if ($aaa) {
           foreach ($aaa as $a) {
               if (!$group or isset($users[$a->userid])) {
                   $index = $indexof[$a->question];
                   if ($a->answer1) {
                       $buckets1[$index] += $a->answer1;
                       $count1[$index]++;
                   }
                   if ($a->answer2) {
                       $buckets2[$index] += $a->answer2;
                       $count2[$index]++;
                   }
               }
           }
       }

       foreach ($questionorder as $i => $val) {
           if ($count1[$i]) {
               $buckets1[$i] = (float)$buckets1[$i] / (float)$count1[$i];
           }
           if ($count2[$i]) {
               $buckets2[$i] = (float)$buckets2[$i] / (float)$count2[$i];
           }
       }

       if ($aaa) {
           foreach ($aaa as $a) {
               if (!$group or isset($users[$a->userid])) {
                   $index = $indexof[$a->question];
                   if ($a->answer1) {
                       $difference = (float) ($a->answer1 - $buckets1[$index]);
                       $stdev1[$index] += ($difference * $difference);
                   }
                   if ($a->answer2) {
                       $difference = (float) ($a->answer2 - $buckets2[$index]);
                       $stdev2[$index] += ($difference * $difference);
                   }
               }
           }
       }

       foreach ($questionorder as $i => $val) {
           if ($count1[$i]) {
               $stdev1[$i] = sqrt( (float)$stdev1[$i] / ((float)$count1[$i]));
           }
           if ($count2[$i]) {
               $stdev2[$i] = sqrt( (float)$stdev2[$i] / ((float)$count2[$i]));
           }
           $buckets1[$i] = $buckets1[$i] - 1;
           $buckets2[$i] = $buckets2[$i] - 1;
       }



       $maxbuckets1 = max($buckets1);
       $maxbuckets2 = max($buckets2);


       $graph = new graph($SURVEY_GWIDTH,$SURVEY_GHEIGHT);
       $graph->parameter['title'] = "$question->text";

       $graph->x_data               = $names;
       $graph->y_data['answers1']   = $buckets1;
       $graph->y_format['answers1'] = array('colour' => 'ltblue', 'line' => 'line',  'point' => 'square',
                                            'shadow_offset' => 4, 'legend' => $stractual);
       $graph->y_data['answers2']   = $buckets2;
       $graph->y_format['answers2'] = array('colour' => 'ltorange', 'line' => 'line', 'point' => 'square',
                                                'shadow_offset' => 4, 'legend' => $strpreferred);
       $graph->y_data['stdev1']   = $stdev1;
       $graph->y_format['stdev1'] = array('colour' => 'ltltblue', 'bar' => 'fill',
                                            'shadow_offset' => '4', 'legend' => 'none', 'bar_size' => 0.3);
       $graph->y_data['stdev2']   = $stdev2;
       $graph->y_format['stdev2'] = array('colour' => 'ltltorange', 'bar' => 'fill',
                                            'shadow_offset' => '4', 'legend' => 'none', 'bar_size' => 0.2);
       $graph->offset_relation['stdev1'] = 'answers1';
       $graph->offset_relation['stdev2'] = 'answers2';

       $graph->parameter['bar_size']    = 0.15;

       $graph->parameter['legend']        = 'outside-top';
       $graph->parameter['legend_border'] = 'black';
       $graph->parameter['legend_offset'] = 4;

       $graph->y_tick_labels = $options;

       if (($maxbuckets1 > 0.0) && ($maxbuckets2 > 0.0)) {
              $graph->y_order = array('stdev1', 'answers1', 'stdev2', 'answers2');
       } else if ($maxbuckets1 > 0.0) {
           $graph->y_order = array('stdev1', 'answers1');
       } else {
           $graph->y_order = array('stdev2', 'answers2');
       }

       $graph->parameter['y_max_left']= count($options) - 1;
       $graph->parameter['y_axis_gridlines']= count($options);
       $graph->parameter['y_resolution_left']= 1;
       $graph->parameter['y_decimal_left']= 1;
       $graph->parameter['x_axis_angle']  = 20;

       $graph->draw();

       break;



     case "overall.png":

       $qqq = $DB->get_records_list("sepug_questions", "id", explode(',', $survey->questions));


       foreach ($qqq as $key => $qq) {
           if ($qq->multi) {
               $qqq[$key]->text = get_string($qq->text, "sepug");
               $qqq[$key]->options = get_string($qq->options, "sepug");
               if ($qq->type < 0) {
                   $virtualscales = true;
               }
           }
       }
       foreach ($qqq as $qq) {         // if any virtual, then use JUST virtual, else use JUST nonvirtual
           if ($qq->multi) {
               if ($virtualscales && $qq->type < 0) {
                   $question[] = $qq;
               } else if (!$virtualscales && $qq->type > 0) {
                   $question[] = $qq;
               }
           }
       }
       $numquestions = count($question);

       $options = explode(",",$question[0]->options);
       $numoptions = count($options);

       for ($i=0; $i<$numquestions; $i++) {
           $names[$i] = $question[$i]->text;
           $buckets1[$i] = 0.0;
           $buckets2[$i] = 0.0;
           $stdev1[$i] = 0.0;
           $stdev2[$i] = 0.0;
           $count1[$i] = 0;
           $count2[$i] = 0;
           $subquestions = $question[$i]->multi;   // otherwise next line doesn't work
           //$aaa = $DB->get_records_select("sepug_answers", "((survey = ?) AND (question in ($subquestions)))", array($cm->instance));
		   $aaa = $DB->get_records_select("sepug_answers", "((courseid = ?) AND (question in ($subquestions)))", array($cid));

           if ($aaa) {
               foreach ($aaa as $a) {
                   if (!$group or isset($users[$a->userid])) {
                       if ($a->answer1) {
                           $buckets1[$i] += $a->answer1;
                           $count1[$i]++;
                       }
                       if ($a->answer2) {
                           $buckets2[$i] += $a->answer2;
                           $count2[$i]++;
                       }
                   }
               }
           }

           if ($count1[$i]) {
               $buckets1[$i] = (float)$buckets1[$i] / (float)$count1[$i];
           }
           if ($count2[$i]) {
               $buckets2[$i] = (float)$buckets2[$i] / (float)$count2[$i];
           }

           // Calculate the standard devaiations
           if ($aaa) {
               foreach ($aaa as $a) {
                   if (!$group or isset($users[$a->userid])) {
                       if ($a->answer1) {
                           $difference = (float) ($a->answer1 - $buckets1[$i]);
                           $stdev1[$i] += ($difference * $difference);
                       }
                       if ($a->answer2) {
                           $difference = (float) ($a->answer2 - $buckets2[$i]);
                           $stdev2[$i] += ($difference * $difference);
                       }
                   }
               }
           }

           if ($count1[$i]) {
               $stdev1[$i] = sqrt( (float)$stdev1[$i] / ((float)$count1[$i]));
           }
           if ($count2[$i]) {
               $stdev2[$i] = sqrt( (float)$stdev2[$i] / ((float)$count2[$i]));
           }

           $buckets1[$i] = $buckets1[$i] - 1;         // Hack because there should not be ANY 0 values in the data.
           $buckets2[$i] = $buckets2[$i] - 1;

       }

       $maxbuckets1 = max($buckets1);
       $maxbuckets2 = max($buckets2);


       $graph = new graph($SURVEY_GWIDTH,$SURVEY_GHEIGHT);
       $graph->parameter['title'] = strip_tags(format_string($survey->name,true));

       $graph->x_data               = $names;

       $graph->y_data['answers1']   = $buckets1;
       $graph->y_format['answers1'] = array('colour' => 'ltblue', 'line' => 'line',  'point' => 'square',
                                            'shadow_offset' => 4, 'legend' => $stractual);
       $graph->y_data['answers2']   = $buckets2;
       $graph->y_format['answers2'] = array('colour' => 'ltorange', 'line' => 'line', 'point' => 'square',
                                                'shadow_offset' => 4, 'legend' => $strpreferred);

       $graph->y_data['stdev1']   = $stdev1;
       $graph->y_format['stdev1'] = array('colour' => 'ltltblue', 'bar' => 'fill',
                                            'shadow_offset' => '4', 'legend' => 'none', 'bar_size' => 0.3);
       $graph->y_data['stdev2']   = $stdev2;
       $graph->y_format['stdev2'] = array('colour' => 'ltltorange', 'bar' => 'fill',
                                            'shadow_offset' => '4', 'legend' => 'none', 'bar_size' => 0.2);
       $graph->offset_relation['stdev1'] = 'answers1';
       $graph->offset_relation['stdev2'] = 'answers2';

       $graph->parameter['legend']        = 'outside-top';
       $graph->parameter['legend_border'] = 'black';
       $graph->parameter['legend_offset'] = 4;

       $graph->y_tick_labels = $options;

       if (($maxbuckets1 > 0.0) && ($maxbuckets2 > 0.0)) {
              $graph->y_order = array('stdev1', 'answers1', 'stdev2', 'answers2');
       } else if ($maxbuckets1 > 0.0) {
           $graph->y_order = array('stdev1', 'answers1');
       } else {
           $graph->y_order = array('stdev2', 'answers2');
       }

       $graph->parameter['y_max_left']= $numoptions - 1;
       $graph->parameter['y_axis_gridlines']= $numoptions;
       $graph->parameter['y_resolution_left']= 1;
       $graph->parameter['y_decimal_left']= 1;
       $graph->parameter['x_axis_angle']  = 0;
       $graph->parameter['x_inner_padding']  = 6;

       $graph->draw();

       break;



     case "student.png":

       $qqq = $DB->get_records_list("sepug_questions", "id", explode(',', $survey->questions));

       foreach ($qqq as $key => $qq) {
           if ($qq->multi) {
               $qqq[$key]->text = get_string($qq->text, "sepug");
               $qqq[$key]->options = get_string($qq->options, "sepug");
               if ($qq->type < 0) {
                   $virtualscales = true;
               }
           }
       }
       foreach ($qqq as $qq) {         // if any virtual, then use JUST virtual, else use JUST nonvirtual
           if ($qq->multi) {
               if ($virtualscales && $qq->type < 0) {
                   $question[] = $qq;
               } else if (!$virtualscales && $qq->type > 0) {
                   $question[] = $qq;
               }
           }
       }
       $numquestions= count($question);

       $options = explode(",",$question[0]->options);
       $numoptions = count($options);

       for ($i=0; $i<$numquestions; $i++) {
           $names[$i] = $question[$i]->text;
           $buckets1[$i] = 0.0;
           $buckets2[$i] = 0.0;
           $count1[$i] = 0;
           $count2[$i] = 0;
           $studbuckets1[$i] = 0.0;
           $studbuckets2[$i] = 0.0;
           $studcount1[$i] = 0;
           $studcount2[$i] = 0;
           $stdev1[$i] = 0.0;
           $stdev2[$i] = 0.0;

           $subquestions = $question[$i]->multi;   // otherwise next line doesn't work
           //$aaa = $DB->get_records_select("sepug_answers","((survey = ?) AND (question in ($subquestions)))", array($cm->instance));
		   $aaa = $DB->get_records_select("sepug_answers","((courseid = ?) AND (question in ($subquestions)))", array($cid));

           if ($aaa) {
               foreach ($aaa as $a) {
                   if (!$group or isset($users[$a->userid])) {
                       if ($a->userid == $sid) {
                           if ($a->answer1) {
                               $studbuckets1[$i] += $a->answer1;
                               $studcount1[$i]++;
                           }
                           if ($a->answer2) {
                               $studbuckets2[$i] += $a->answer2;
                               $studcount2[$i]++;
                           }
                       }
                       if ($a->answer1) {
                           $buckets1[$i] += $a->answer1;
                           $count1[$i]++;
                       }
                       if ($a->answer2) {
                           $buckets2[$i] += $a->answer2;
                           $count2[$i]++;
                       }
                   }
               }
           }

           if ($count1[$i]) {
               $buckets1[$i] = (float)$buckets1[$i] / (float)$count1[$i];
           }
           if ($count2[$i]) {
               $buckets2[$i] = (float)$buckets2[$i] / (float)$count2[$i];
           }
           if ($studcount1[$i]) {
               $studbuckets1[$i] = (float)$studbuckets1[$i] / (float)$studcount1[$i];
           }
           if ($studcount2[$i]) {
               $studbuckets2[$i] = (float)$studbuckets2[$i] / (float)$studcount2[$i];
           }

           // Calculate the standard devaiations
           foreach ($aaa as $a) {
               if (!$group or isset($users[$a->userid])) {
                   if ($a->answer1) {
                       $difference = (float) ($a->answer1 - $buckets1[$i]);
                       $stdev1[$i] += ($difference * $difference);
                   }
                   if ($a->answer2) {
                       $difference = (float) ($a->answer2 - $buckets2[$i]);
                       $stdev2[$i] += ($difference * $difference);
                   }
               }
           }

           if ($count1[$i]) {
               $stdev1[$i] = sqrt( (float)$stdev1[$i] / ((float)$count1[$i]));
           }
           if ($count2[$i]) {
               $stdev2[$i] = sqrt( (float)$stdev2[$i] / ((float)$count2[$i]));
           }

           $buckets1[$i] = $buckets1[$i] - 1;         // Hack because there should not be ANY 0 values in the data.
           $buckets2[$i] = $buckets2[$i] - 1;
           $studbuckets1[$i] = $studbuckets1[$i] - 1;
           $studbuckets2[$i] = $studbuckets2[$i] - 1;

       }

       $maxbuckets1 = max($buckets1);
       $maxbuckets2 = max($buckets2);


       $graph = new graph($SURVEY_GWIDTH,$SURVEY_GHEIGHT);
       $graph->parameter['title'] = strip_tags(format_string($survey->name,true));

       $graph->x_data               = $names;

       $graph->y_data['answers1']   = $buckets1;
       $graph->y_format['answers1'] = array('colour' => 'ltblue', 'line' => 'line',  'point' => 'square',
                                            'shadow_offset' => 0.1, 'legend' => $stractualclass);
       $graph->y_data['answers2']   = $buckets2;
       $graph->y_format['answers2'] = array('colour' => 'ltorange', 'line' => 'line', 'point' => 'square',
                                                'shadow_offset' => 0.1, 'legend' => $strpreferredclass);
       $graph->y_data['studanswers1']   = $studbuckets1;
       $graph->y_format['studanswers1'] = array('colour' => 'blue', 'line' => 'line',  'point' => 'square',
                                            'shadow_offset' => 4, 'legend' => $stractualstudent);
       $graph->y_data['studanswers2']   = $studbuckets2;
       $graph->y_format['studanswers2'] = array('colour' => 'orange', 'line' => 'line', 'point' => 'square',
                                                'shadow_offset' => 4, 'legend' => $strpreferredstudent);
       $graph->y_data['stdev1']   = $stdev1;
       $graph->y_format['stdev1'] = array('colour' => 'ltltblue', 'bar' => 'fill',
                                            'shadow_offset' => 0.1, 'legend' => 'none', 'bar_size' => 0.3);
       $graph->y_data['stdev2']   = $stdev2;
       $graph->y_format['stdev2'] = array('colour' => 'ltltorange', 'bar' => 'fill',
                                            'shadow_offset' => 0.1, 'legend' => 'none', 'bar_size' => 0.2);
       $graph->offset_relation['stdev1'] = 'answers1';
       $graph->offset_relation['stdev2'] = 'answers2';

       $graph->y_tick_labels = $options;

       $graph->parameter['bar_size']    = 0.15;

       $graph->parameter['legend']        = 'outside-top';
       $graph->parameter['legend_border'] = 'black';
       $graph->parameter['legend_offset'] = 4;

       if (($maxbuckets1 > 0.0) && ($maxbuckets2 > 0.0)) {
              $graph->y_order = array('stdev1', 'stdev2', 'answers1', 'answers2', 'studanswers1', 'studanswers2');
       } else if ($maxbuckets1 > 0.0) {
           $graph->y_order = array('stdev1', 'answers1', 'studanswers1');
       } else {
           $graph->y_order = array('stdev2', 'answers2', 'studanswers2');
       }

       $graph->parameter['y_max_left']= $numoptions - 1;
       $graph->parameter['y_axis_gridlines']= $numoptions;
       $graph->parameter['y_resolution_left']= 1;
       $graph->parameter['y_decimal_left']= 1;
       $graph->parameter['x_axis_angle']  = 20;

       $graph->draw();
       break;



     case "studentmultiquestion.png":

       $question  = $DB->get_record("sepug_questions", array("id"=>$qid));
       $question->text = get_string($question->text, "sepug");
       $question->options = get_string($question->options, "sepug");

       $options = explode(",",$question->options);
       $questionorder = explode( ",", $question->multi);

       $qqq = $DB->get_records_list("survey_questions", "id", explode(',', $question->multi));

       foreach ($questionorder as $i => $val) {
           $names[$i] = get_string($qqq[$val]->shorttext, "sepug");
           $buckets1[$i] = 0;
           $buckets2[$i] = 0;
           $count1[$i] = 0;
           $count2[$i] = 0;
           $indexof[$val] = $i;
           $studbuckets1[$i] = 0.0;
           $studbuckets2[$i] = 0.0;
           $studcount1[$i] = 0;
           $studcount2[$i] = 0;
           $stdev1[$i] = 0.0;
           $stdev2[$i] = 0.0;
       }

       //$aaa = $DB->get_records_select("sepug_answers", "((survey = ?) AND (question in ($question->multi)))", array($cm->instance));
	   $aaa = $DB->get_records_select("sepug_answers", "((courseid = ?) AND (question in ($question->multi)))", array($cid));

       if ($aaa) {
           foreach ($aaa as $a) {
               if (!$group or isset($users[$a->userid])) {
                   $index = $indexof[$a->question];
                       if ($a->userid == $sid) {
                           if ($a->answer1) {
                               $studbuckets1[$index] += $a->answer1;
                               $studcount1[$index]++;
                           }
                           if ($a->answer2) {
                               $studbuckets2[$index] += $a->answer2;
                               $studcount2[$index]++;
                           }
                       }
                   if ($a->answer1) {
                       $buckets1[$index] += $a->answer1;
                       $count1[$index]++;
                   }
                   if ($a->answer2) {
                       $buckets2[$index] += $a->answer2;
                       $count2[$index]++;
                   }
               }
           }
       }

       foreach ($questionorder as $i => $val) {
           if ($count1[$i]) {
               $buckets1[$i] = (float)$buckets1[$i] / (float)$count1[$i];
           }
           if ($count2[$i]) {
               $buckets2[$i] = (float)$buckets2[$i] / (float)$count2[$i];
           }
           if ($studcount1[$i]) {
               $studbuckets1[$i] = (float)$studbuckets1[$i] / (float)$studcount1[$i];
           }
           if ($studcount2[$i]) {
               $studbuckets2[$i] = (float)$studbuckets2[$i] / (float)$studcount2[$i];
           }
       }

       foreach ($aaa as $a) {
           if (!$group or isset($users[$a->userid])) {
               $index = $indexof[$a->question];
               if ($a->answer1) {
                   $difference = (float) ($a->answer1 - $buckets1[$index]);
                   $stdev1[$index] += ($difference * $difference);
               }
               if ($a->answer2) {
                   $difference = (float) ($a->answer2 - $buckets2[$index]);
                   $stdev2[$index] += ($difference * $difference);
               }
           }
       }

       foreach ($questionorder as $i => $val) {
           if ($count1[$i]) {
               $stdev1[$i] = sqrt( (float)$stdev1[$i] / ((float)$count1[$i]));
           }
           if ($count2[$i]) {
               $stdev2[$i] = sqrt( (float)$stdev2[$i] / ((float)$count2[$i]));
           }
           $buckets1[$i] = $buckets1[$i] - 1;         // Hack because there should not be ANY 0 values in the data.
           $buckets2[$i] = $buckets2[$i] - 1;
           $studbuckets1[$i] = $studbuckets1[$i] - 1;
           $studbuckets2[$i] = $studbuckets2[$i] - 1;
       }



       $maxbuckets1 = max($buckets1);
       $maxbuckets2 = max($buckets2);


       $graph = new graph($SURVEY_GWIDTH,$SURVEY_GHEIGHT);
       $graph->parameter['title'] = "$question->text";

       $graph->x_data               = $names;
       $graph->y_data['answers1']   = $buckets1;
       $graph->y_format['answers1'] = array('colour' => 'ltblue', 'line' => 'line',  'point' => 'square',
                                            'shadow_offset' => 0.1, 'legend' => $stractualclass);
       $graph->y_data['answers2']   = $buckets2;
       $graph->y_format['answers2'] = array('colour' => 'ltorange', 'line' => 'line', 'point' => 'square',
                                                'shadow_offset' => 0.1, 'legend' => $strpreferredclass);
       $graph->y_data['studanswers1']   = $studbuckets1;
       $graph->y_format['studanswers1'] = array('colour' => 'blue', 'line' => 'line',  'point' => 'square',
                                            'shadow_offset' => 4, 'legend' => $stractualstudent);
       $graph->y_data['studanswers2']   = $studbuckets2;
       $graph->y_format['studanswers2'] = array('colour' => 'orange', 'line' => 'line', 'point' => 'square',
                                                'shadow_offset' => 4, 'legend' => $strpreferredstudent);
       $graph->y_data['stdev1']   = $stdev1;
       $graph->y_format['stdev1'] = array('colour' => 'ltltblue', 'bar' => 'fill',
                                            'shadow_offset' => 0.1, 'legend' => 'none', 'bar_size' => 0.3);
       $graph->y_data['stdev2']   = $stdev2;
       $graph->y_format['stdev2'] = array('colour' => 'ltltorange', 'bar' => 'fill',
                                            'shadow_offset' => 0.1, 'legend' => 'none', 'bar_size' => 0.2);
       $graph->offset_relation['stdev1'] = 'answers1';
       $graph->offset_relation['stdev2'] = 'answers2';

       $graph->parameter['bar_size']    = 0.15;

       $graph->parameter['legend']        = 'outside-top';
       $graph->parameter['legend_border'] = 'black';
       $graph->parameter['legend_offset'] = 4;

       $graph->y_tick_labels = $options;

       if (($maxbuckets1 > 0.0) && ($maxbuckets2 > 0.0)) {
           $graph->y_order = array('stdev1', 'stdev2', 'answers1', 'answers2', 'studanswers1', 'studanswers2');
       } else if ($maxbuckets1 > 0.0) {
           $graph->y_order = array('stdev1', 'answers1', 'studanswers1');
       } else {
           $graph->y_order = array('stdev2', 'answers2', 'studanswers2');
       }

       $graph->parameter['y_max_left']= count($options)-1;
       $graph->parameter['y_axis_gridlines']= count($options);
       $graph->parameter['y_resolution_left']= 1;
       $graph->parameter['y_decimal_left']= 1;
       $graph->parameter['x_axis_angle']  = 20;

       $graph->draw();

       break;

     default:
       break;
   }

   exit;



