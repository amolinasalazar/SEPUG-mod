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
 * Definition of log events
 *
 * @package   mod_sepug
 * @category  log
 * @copyright 2014 Alejandro Molina Salazar
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$logs = array(
    array('module'=>'sepug', 'action'=>'add', 'mtable'=>'sepug', 'field'=>'name'),
    array('module'=>'sepug', 'action'=>'update', 'mtable'=>'sepug', 'field'=>'name'),
    array('module'=>'sepug', 'action'=>'download', 'mtable'=>'sepug', 'field'=>'name'),
    array('module'=>'sepug', 'action'=>'view form', 'mtable'=>'sepug', 'field'=>'name'),
    array('module'=>'sepug', 'action'=>'view graph', 'mtable'=>'sepug', 'field'=>'name'),
    array('module'=>'sepug', 'action'=>'view report', 'mtable'=>'sepug', 'field'=>'name'),
    array('module'=>'sepug', 'action'=>'submit', 'mtable'=>'sepug', 'field'=>'name'),
);