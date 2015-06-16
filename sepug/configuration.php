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
 * Configuration file contains global data that is used by the module.
 *
 * @package   mod-sepug
 * @copyright 2015 Alejandro Molina Salazar
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 
defined('MOODLE_INTERNAL') || die();

// -- GLOBAL DATA --

// Graph size: height
global $SEPUG_GHEIGHT;
$SEPUG_GHEIGHT = 600;//600

// Graph size: weight
global $SEPUG_GWIDTH;
$SEPUG_GWIDTH  = 1100;//800

// Question type
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
		
// Dimension questions
global $DIM_PLANIF, $DIM_COMP_DOC, $DIM_EV_APREND, $DIM_AMB;
$DIM_PLANIF = array(1,2,4,5,18); // Planificacion de la docencia y el Plan Docente
$DIM_COMP_DOC = array(6,8,9,10,11,12,14); // Competencias docentes
$DIM_EV_APREND = array(3,16,17); // Evaluacion de los aprendizajes
$DIM_AMB = array(13,15); // Ambiente de clase y relacion profesor-a con alumno-a

// Course filter
global $FILTRO_CURSOS, $FILTRO;
$FILTRO_CURSOS = true;
$FILTRO = "SELECT a.id, a.* from {course} a WHERE ( ( translate(substr(a.idnumber,-4,4),'0123456789',' ') is not null and substr(a.idnumber,-2,2)<>'SG') ) or shortname in ('amingotest')";


// -- DEFINES --

define("SURVEY_COLDP15", "1");

