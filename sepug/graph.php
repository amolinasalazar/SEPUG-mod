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
 * This file implement functions to generate statistics graphs.
 *
 * @package   mod-sepug
 * @copyright 2015 Alejandro Molina Salazar
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 
    require_once("../../config.php");
    require_once("$CFG->libdir/graphlib.php");
    require_once("lib.php");

	$cid   = required_param('cid', PARAM_INT);    // Course ID
    $type  = required_param('type', PARAM_FILE);  // Graph Type

    $url = new moodle_url('/mod/sepug/graph.php', array('cid'=>$cid, 'type'=>$type));
    $PAGE->set_url($url);

	if (! $course = $DB->get_record("course", array("id"=>$cid))) {
        print_error('coursemisconf');
    }

	require_login($course);
	
	$context = context_course::instance($course->id);
	
	if (!has_capability('mod/sepug:readresponses', $context)) {
        print_error('nopermissiontoshow');
    } 

    switch ($type) {

		case "question.png":
		 
			// Obtain related categories of the course
			$main_categories = sepug_related_categories($cid);
			
			// Obtain course mean and group
			$x_data = array();
			$mean_array = array();
			$stats = $DB->get_records("sepug_prof_stats",array("courseid"=>$cid));
			$result = array_pop($stats);
			$mean_array[] = $result->mean;
			$x_data[] = get_string("curso","sepug");
			
			// It's GRADO or POSTGRADO
			$survey = $DB->get_record("sepug", array("sepuginstance"=>1));
			if(!$DB->get_records_sql("SELECT * FROM {course_categories} WHERE id = ".$course->category." AND path LIKE '/".$survey->catgrado."%' AND visible = 1")){
				$grado = 0;
			}
			else{
				$grado = 1;
			}
			
			// Obtain values of the last question of all surveys i each category
			foreach($main_categories as $cat){
				$question_max = $DB->get_record_sql("SELECT MAX(question) AS maxq FROM {sepug_global_stats} WHERE catname = '".$cat->name."' AND grado =".$grado);
				$result = $DB->get_record("sepug_global_stats",array("question"=>(int)$question_max->maxq, "catname"=>$cat->name, "grado"=>$grado));
				$mean_array[] = $result->mean;
			}
			
			// Create the bars graph
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
			
			$graph->parameter['y_max_left']= 5; // Y max value
			$graph->parameter['y_resolution_left']= 2; // round
			$graph->parameter['y_decimal_left']= 1; // number of Y decimal values
			$graph->parameter['x_axis_angle']  = 40; // rotation angle of X labels
			
			$graph->x_data = $x_data;
			$graph->y_data['mean'] = $mean_array;
			$graph->y_format['mean'] = array('colour' => 'ltltorange', 'bar' => 'fill',
													'shadow_offset' => '4', 'legend' => get_string("mean", "sepug"), 'bar_size' => 0.3);
			$graph->y_order = array('mean');
			$graph->draw();

		break;

		default:
		break;
   }

   exit;
