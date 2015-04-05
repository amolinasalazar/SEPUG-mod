<?php

// This file replaces:
//   * STATEMENTS section in db/install.xml
//   * lib.php/modulename_install() post installation hook
//   * partially defaults.php

function xmldb_sepug_install() {
    global $DB;

/// insert survey data
    $records = array(
		array_combine(array('course', 'template', 'days', 'timecreated', 'timemodified', 'name', 'intro', 'questions', 'timeopen','timeclosestudents', 'timeclose','catgrado','catposgrado'), array(0, 0, 0, 985017600, 985017600, 'coldp15name', 'coldp15_intro', '27,21,22,23,24,25,26', 0, 0, 0, 1, 0)),
		array_combine(array('course', 'template', 'days', 'timecreated', 'timemodified', 'name', 'intro', 'questions','timeopen','timeclosestudents','timeclose','catgrado','catposgrado'), array(0, 0, 0, 985017600, 985017600, 'coldp15name_postgrado', 'coldp15_intro', '28,21,22,23,24,25,26', 0, 0, 0, 0, 1)),
    );
    foreach ($records as $record) {
        $DB->insert_record('sepug', $record, false);
    }

    $records = array(
		// SEPUG: inserting questions
		array_combine(array('text', 'shorttext', 'type', 'options'), array('coldp15_1', 'coldp15_1short', 1, 'scalenumbers5')),
		array_combine(array('text', 'shorttext', 'type', 'options'), array('coldp15_2', 'coldp15_2short', 1, 'scalenumbers5')),
		array_combine(array('text', 'shorttext', 'type', 'options'), array('coldp15_3', 'coldp15_3short', 1, 'scalenumbers5')),
		array_combine(array('text', 'shorttext', 'type', 'options'), array('coldp15_4', 'coldp15_4short', 1, 'scalenumbers5')),
		array_combine(array('text', 'shorttext', 'type', 'options'), array('coldp15_5', 'coldp15_5short', 1, 'scalenumbers5')),
		array_combine(array('text', 'shorttext', 'type', 'options'), array('coldp15_6', 'coldp15_6short', 1, 'scalenumbers5')),
		array_combine(array('text', 'shorttext', 'type', 'options'), array('coldp15_7', 'coldp15_7short', 1, 'scalenumbers5')),
		array_combine(array('text', 'shorttext', 'type', 'options'), array('coldp15_8', 'coldp15_8short', 1, 'scalenumbers5')),
		array_combine(array('text', 'shorttext', 'type', 'options'), array('coldp15_9', 'coldp15_9short', 1, 'scalenumbers5')),
		array_combine(array('text', 'shorttext', 'type', 'options'), array('coldp15_10', 'coldp15_10short', 1, 'scalenumbers5')),
		array_combine(array('text', 'shorttext', 'type', 'options'), array('coldp15_11', 'coldp15_11short', 1, 'scalenumbers5')),
		array_combine(array('text', 'shorttext', 'type', 'options'), array('coldp15_12', 'coldp15_12short', 1, 'scalenumbers5')),
		array_combine(array('text', 'shorttext', 'type', 'options'), array('coldp15_13', 'coldp15_13short', 1, 'scalenumbers5')),
		array_combine(array('text', 'shorttext', 'type', 'options'), array('coldp15_14', 'coldp15_14short', 1, 'scalenumbers5')),
		array_combine(array('text', 'shorttext', 'type', 'options'), array('coldp15_15', 'coldp15_15short', 1, 'scalenumbers5')),
		array_combine(array('text', 'shorttext', 'type', 'options'), array('coldp15_16', 'coldp15_16short', 1, 'scalenumbers5')),
		array_combine(array('text', 'shorttext', 'type', 'options'), array('coldp15_17', 'coldp15_17short', 1, 'scalenumbers5')),
		array_combine(array('text', 'shorttext', 'type', 'options'), array('coldp15_18', 'coldp15_18short', 1, 'scalenumbers5')),
		array_combine(array('text', 'shorttext', 'type', 'options'), array('coldp15_19', 'coldp15_19short', 1, 'scalenumbers5')),
		array_combine(array('text', 'shorttext', 'type', 'options'), array('coldp15_20', 'coldp15_20short', 1, 'scalenumbers5')),
		array_combine(array('text', 'shorttext', 'type', 'options'), array('edad', '', 2, 'edadoptions')),
		array_combine(array('text', 'shorttext', 'type', 'options'), array('sexo', '', 2, 'sexooptions')),
		array_combine(array('text', 'shorttext', 'type', 'options'), array('interes', '', 2, 'niveloptions')),
		array_combine(array('text', 'shorttext', 'type', 'options'), array('dificultad', '', 2, 'niveloptions')),
		array_combine(array('text', 'shorttext', 'type', 'options'), array('asistencia', '', 2, 'asistenciaoptions')),
		array_combine(array('text', 'shorttext', 'type', 'options'), array('tutorias', '', 2, 'tutoriasoptions')),
		array_combine(array('text', 'shorttext', 'multi', 'intro', 'type', 'options'), array('coldp15m', 'coldp15mshort', '1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,20', '', 1, 'scalenumbers5')),
		array_combine(array('text', 'shorttext', 'multi', 'intro', 'type', 'options'), array('coldp15m_postgrado', 'coldp15mshort_postgrado', '1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20', '', 1, 'scalenumbers5')),
		// FIN
    );
    foreach ($records as $record) {
        $DB->insert_record('sepug_questions', $record, false);
    }

}
