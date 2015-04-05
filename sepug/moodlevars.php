<?php
    /**
    * moodlevars.php
    * 
    * This page helps you to figure out moodle variables...
    * Place it in a subdirectory under your moodle install.
    * I created a folder called /ztests in my main moodle
    * folder and placed the file there...
    * http://www.yourmoodlesite.com/moodle/ztests/moodlevars.php
    */
	global $DB, $CFG;

	//require_once($CFG->libdir . '/coursecatlib.php');
    require_once("../config.php") ;
	require_once($CFG->dirroot.'/lib/coursecatlib.php');
	
    require_login();
	
	
	
	//$aux= enrol_get_all_users_courses(2, true, null, 'visible DESC, sortorder ASC');
	
	//$cntxt = get_context_instance(CONTEXT_COURSE, 4);
	//$aux = get_user_roles($cntxt, 2, false, 'c.contextlevel DESC, r.sortorder ASC');
	
	//$aux = get_user_roles_in_course(8, 3);
	
	//$cm = context_module::instance(4);
	//$cm = get_coursemodule_from_id('sepug', 39);
	
	//$cm = get_course_category_tree(2);
	//$cm = get_child_categories(2);
	//$cm = get_courses('recursive'=>1);
	
	//$coursecat::get_courses();
	//$cat1 = coursecat::create(array('name' => 'Cat1'));
	$cat1 = coursecat::get(2);
	$cm = $cat1->get_courses(array('recursive' => 1));
	
	
	
	// PRUEBAS
    print "<div style=\"border:5px solid red;\">";
    print " PRUEBA var dump <br />\n";
    print "<pre>";
    var_dump($cm);
    print "</pre></div>";
    
    //put in standard moodle header:
    print_header('variable page', 'gsl text heading', '.',''  ,'' , false );
    
    //$USER
    print "<div style=\"border:5px solid blue;\">";
    print " USER var dump <br />\n";
    print "<pre>";
    var_dump($USER);
    print "</pre></div>";
    
    //$CFG
    print "<div style=\"border:5px solid green;\">";
    print " CFG var dump <br />\n";
    print "<pre>";
    var_dump($CFG);
    print "</pre></div>";
    
    //$COURSE
    print "<div style=\"border:5px solid orange;\">";
    print "COURSE var dump <br />\n";
    print "<pre>";
    var_dump($COURSE);
    print "</pre></div>";
    
    //put in your normal moodle footer:
    print_footer();
    
?>