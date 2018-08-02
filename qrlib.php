<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Set of functions for qrtopics course format.
 *
 * @package format_qrtopics
 * @copyright 2018
 * @author Francesco Pisano
 * @license
 */

    /**
     * Create quiz and add to section.
     *
     * @param int $courseid course id
     * @param int $numOfSections number of sections 
     */ 
function quiz_get_course_quiz($courseid, $numOfSections) {  
	// How to set up special 1-per-course quiz
    global $CFG, $DB, $OUTPUT, $USER;
	
	    if ($quizzes = $DB->get_records_select("quiz", "course = ?", array($courseid), "id ASC")) {
        // There should always only be ONE, but with the right combination of
        // errors there might be more.  In this case, just return the oldest one (lowest ID).
        foreach ($quizzes as $quiz) {
			return $quiz;  }  // ie the first one
    }
	
    // Doesn't exist, so create one now.
	$quiz = new stdClass();
	$quiz->course = $courseid;
    $quiz->introformat = 1;
	$quiz->intro = "";
	$quiz->timeopen = 0;
	$quiz->timeclose = 0;
	$quiz->timelimit = 0;
	$quiz->overduehandling = "autosubmit";
	$quiz->graceperiod = 0;
	$quiz->preferredbehaviour = "deferredfeedback";
	$quiz->canredoquestions = 0;
	$quiz->attempts = 1;
	$quiz->attemptonlast = 0;
	$quiz->grademethod  = 4;
	$quiz->decimalpoints = 2;
	$quiz->questiondecimalpoints = -1;
	$quiz->reviewattempt = 65536;
	$quiz->reviewcorrectness = 0;
	$quiz->reviewmarks = 0;
	$quiz->reviewspecificfeedback = 0;
	$quiz->reviewgeneralfeedback = 0;
	$quiz->reviewrightanswer = 0;
	$quiz->reviewoverallfeedback = 0;
	$quiz->questionsperpage = 10;
	$quiz->navmethod = "sequential";
	$quiz->shuffleanswers = 1;
	$quiz->sumgrades = pow(2,$numOfSections)-1;   
	$quiz->grade = pow(2,$numOfSections)-1;
	$quiz->timecreated = 0;
	$quiz->password = "";
	$quiz->subnet = "";
	$quiz->browsersecurity = "-";
	$quiz->delay1 = 0;
	$quiz->delay2 = 0;
	$quiz->showuserpicture = 0;
	$quiz->showblocks = 0;
	$quiz->completionattemptsexhausted = 0;
	$quiz->completionpass = 0;
	$quiz->allowofflineattempts = 0;
    $quiz->name = get_string('quizname', 'format_qrtopics');
    $quiz->timemodified = time();
    $quiz->id = $DB->insert_record("quiz", $quiz);
	
    if (! $module = $DB->get_record("modules", array("name" => "quiz"))) {
        echo $OUTPUT->notification("Could not find quiz module!!");
        return false;
    }
	
    $mod = new stdClass();
    $mod->course = $courseid;
    $mod->module = 16;
    $mod->instance = $quiz->id;
    $mod->section = 0;
    
	$mod->added = time();
    unset($mod->id);

    $cmid = $DB->insert_record("course_modules", $mod);
	$mod->coursemodule = $cmid;
	rebuild_course_cache($mod->course, true);
	
	$sectionid = course_add_cm_to_section($courseid, $mod->coursemodule, 0);
	$DB->insert_record('quiz_sections', array('quizid' => $quiz->id, 'firstslot' => 1, 'heading' => '', 'shufflequestions' => 0));
	
	return $DB->get_record("quiz", array("id" => "$quiz->id"));
}  

    /**
     * Create quiz, question category, grade items and grade category.
     *
     * @param int $courseid course id
     * @param int $numOfSections number of sections 
     */ 
function create_qr_quiz($idCourse, $numOfSections) {
	global $DB;
	
	$strgroups  = get_string('groups');
	$strgroupmy = get_string('groupmy');
		
	if ($quiz = quiz_get_course_quiz($idCourse, $numOfSections)) {
		$cm = get_coursemodule_from_instance('quiz', $quiz->id);
		$modcontext = context_module::instance($cm->id);
	} else {
		echo $OUTPUT->notification('Could not find or create a Quiz here');
	} 
	
	// If doesn't exist, add Category.
	$category = new stdClass();
	$context = context_course::instance($idCourse);
	$contextname = $context->get_context_name(false, true);
	$category->contextid = $context->id;
	
	$categories = $DB->get_records_select("question_categories", "contextid = ?", array($category->contextid), "id ASC");
	if (sizeof($categories) == 0) {
		$category->name = get_string('defaultfor', 'question', $contextname);
		$category->info = get_string('defaultinfofor', 'question', $contextname);
		$category->parent = 0;
		// By default, all categories get this number, and are sorted alphabetically.
		$category->sortorder = 999;
		$category->stamp = make_unique_id_code();
		// Add to database.
		$category->id = $DB->insert_record('question_categories', $category);
	}
		
	// If doesn't exist, add Grade Cat.
	$grade_cat = new stdClass();	
	$grade_cat -> courseid = $idCourse;
	$grade_cat -> depth = 1;
	$grade_cat -> fullname = '?';
	$grade_cat -> aggregation = 13;
	$grade_cat -> keephigh = 0;
	$grade_cat -> droplow = 0;
	$grade_cat -> aggregateonlygraded = 1;
	$grade_cat -> aggregateoutcomes = 0;
	$grade_cat -> timecreated = time();
	$grade_cat -> timemodified = time();
	$grade_cat -> hidden = 0;
	
	$gradeCats = $DB->get_records_select("grade_categories", "courseid = ?", array($idCourse), "id ASC");
	if (sizeof($gradeCats) == 0) {
		// Add to database.
		$grade_cat->id = $DB->insert_record('grade_categories', $grade_cat);
		$grade_cat -> path = "/".$grade_cat->id ."/";
		$DB->update_record('grade_categories', $grade_cat);
	}
	
	// If doesn't exist, add Grade Item.
	$gradeItems = $DB->get_records_select("grade_items", "iteminstance = ?", array($quiz->id), "id ASC");
	if (sizeof($gradeItems) == 0) {
		$grade_item = new stdClass();
		$grade_item->courseid = $idCourse;
		$grade_item->categoryid = NULL;
		$items = $DB->get_records_sql('SELECT * FROM {grade_categories} WHERE courseid = ?', array($idCourse));
		foreach ($items as $item) {
			$grade_item->categoryid = $item->id; 
		}
		$grade_item->itemname = $quiz->name;
		$grade_item->itemtype = 'mod';
		$grade_item->itemmodule = 'quiz'; 
		$grade_item->iteminstance = $quiz->id;
		$grade_item->itemnumber = 0;
		$grade_item->gradetype = 1;
		$grade_item->grademin = 0;
		$grade_item->grademax = pow(2,$numOfSections)-1;
		$grade_item->locked = 0;
		$grade_item->iteminfo = '';
		$grade_item->timecreated = time();
		$grade_item->timemodified = time();
		$grade_item->locked = 0;
		$grade_item->sortorder = 2;
		
		$gradeCourse = new stdClass();
		$gradeCourse->courseid = $idCourse;
		$gradeCourse->itemtype = 'course';
		$gradeCourse->iteminstance = $grade_item->categoryid;
		$gradeCourse->iteminfo = NULL;
		$gradeCourse->timecreated = time();
		$gradeCourse->timemodified = time();
		
		$gradeCourse->id = $DB->insert_record('grade_items', $gradeCourse);
		$grade_item->id = $DB->insert_record('grade_items', $grade_item);
		
		// Add label to check if new course or not.
		$labelmod = new stdClass();
		$labelmod->course = $idCourse;
		$labelmod->name = 'QRTformat-'.$idCourse;
		$labelmod->intro = $quiz->id;
		$labelmod->timemodified = time();
		$labelmod->id = $DB->insert_record("label", $labelmod);
		// Create instruction for course.
		add_quizguide($idCourse);
	}
}
 

    /**
     * Update maximum grade of quiz
     *
     * @param int $quizId quiz id
     * @param int $numOfSections number of sections 
     */ 
function update_grade_max($quizId, $numOfSections) {
	global $DB;
	
	$updateGrade = new stdClass();
	$updateQuiz = new stdClass();
	
	$getitem = $DB->get_record("grade_items", array('iteminstance' => $quizId));
	$updateGrade->id = $getitem->id;
	$updateGrade->grademax = pow(2,$numOfSections)-1;
	$updateQuiz->id = $quizId;
	$updateQuiz->sumgrades = $updateGrade->grademax;   
	$updateQuiz->grade = $updateGrade->grademax;
	
	$DB->update_record('quiz', $updateQuiz);
	$DB->update_record('grade_items', $updateGrade);
}

    /**
     * Create a multichoise question and add question to quiz.
	 * You can set "0 points" answers number;
     *
     * @param int $quiz quiz object
     * @param int $category category of course questions
	 * @param int $nSection number of the section linked to the question
     * @param int $context context for category
	 */ 
function create_qr_question($quiz, $category, $nSection, $context) {
	global $USER, $DB, $OUTPUT, $CFG;
	
	$pointsanswers = 3; // Set to 1 if you want a question like "true false" question.
		
	$categoryid = $category->id;
	$categoryC = $DB->get_record('question_categories', array('id' => $categoryid));
	if (!$categoryC) {
		print_error('invalidcategoryid', 'error');
	}
	
	$catcontext = context::instance_by_id($category->contextid);
	require_capability('moodle/question:useall', $catcontext);
	
	// Create question.
	$form = new stdClass();
	$form->questiontext['text'] = get_string('questioninfo', 'format_qrtopics');
	$form->category = $category->id.','.$context->id;
	$form->defaultmark = 1;
	$form->name = "";
	$form->hidden = 1;
	$form->stamp = make_unique_id_code();
	$form->context = $context;
	$form->other['content']=1;
	$question = new stdClass();
	$question->other['content']=1;
	$question->parent = isset($form->parent) ? $form->parent : 0;
	$question->penalty = 0;
	$question->qtype = 'multichoice';
	// Save question on database.
	$question = question_bank::get_qtype('random')->save_question($question, $form);
	// Update question.
	$update = new stdClass();
	$update->id = $question->id;
	$update->name = get_string('questionname', 'format_qrtopics').$nSection;
	$update->defaultmark = pow(2,($nSection-1)); 												
	$update->questiontext = get_string('questioninfo', 'format_qrtopics');
	$update->parent = 0;
	$update->questiontextformat = 1;
	$update->generalfeedbackformat = 1;
	$DB->update_record('question', $update);

	// Add question to mdl_question_answers and mdl_qtype_multichoice_options.
	$questiondata = new stdClass();
	$questionAns = new stdClass();
	$questionAns->question = $question->id;
	$questionAns->answerformat = 0;
	$questionAns->feedback = '';
	$questionAns->feedbackformat = 1;	

		// No points case.
	$questionAns->answer = get_string('rightanswer', 'format_qrtopics'); 
	$questionAns->fraction = 0.0;
	$questionAns->id = $DB->insert_record('question_answers', $questionAns);
		// 100% points case.
	for ($q = 0; $q < $pointsanswers; $q++) {
		$questionAns->answer = get_string('wronganswer', 'format_qrtopics'); 
		$questionAns->fraction = 1.0;
		$questionAns->id = $DB->insert_record('question_answers', $questionAns);	
	}
	
	$multioptions = new stdClass();
	$multioptions->questionid = $question->id;
	$multioptions->layout = 0; 
	$multioptions->single = 1;
	$multioptions->shuffleanswers = 1;
	$multioptions->correctfeedback = '';
	$multioptions->correctfeedbackformat = 0;
	$multioptions->partiallycorrectfeedback = '';
	$multioptions->partiallycorrectfeedbackformat = 0;
	$multioptions->incorrectfeedback = '';
	$multioptions->incorrectfeedbackformat = 0;
	$multioptions->answernumbering = 'abc';
	$multioptions->shownumcorrect = 0;
	$multioptions->id = $DB->insert_record('qtype_multichoice_options', $multioptions);
	
	// Add to quiz.
	require_once($CFG->dirroot . '/mod/quiz/locallib.php');
	quiz_add_quiz_question($question->id, $quiz, 0);

	// Update quiz.
	quiz_update_sumgrades($quiz);
}
 
    /**
     * Set or update restrictions to all topics based on the quiz result
     *
     * @param int $idCourse course id
     * @param int $grade_item grade record linked to quiz
	 */
function set_restrictions($idCourse, $grade_item) {
	global $USER, $DB, $OUTPUT, $CFG;
	// Select sections to add restrictions.
	$sections = $DB->get_records_sql('SELECT * FROM {course_sections} WHERE course = ?', array($idCourse));
	$numOfSections = sizeof($sections)-1;
	
	foreach ($sections as $sectionX) {
		$nSection = $sectionX->section;
		$idSection = $sectionX->id;
		if ($nSection != 0) { 
			// Set restrictions.
			$blocks = (pow(2,$numOfSections))/(pow(2,($nSection-1)));
			$skip = 100.00/($blocks);
			$step = 0;
			$restriction = '{"op":"|","c":[';
			for ($i = 0; $i<$blocks ; $i++) {
				$step = $step + $skip; $min = $step;
				$step = $step + $skip; $max = $step + 0.01;
				if ($max < 100) {
					$resTopic = '{"type":"grade","id":'.$grade_item->id.',"min":'.$min.',"max":'.$max.'},';
				} else {
					$resTopic = '{"type":"grade","id":'.$grade_item->id.',"min":'.$min.'}],"show":false}';
				}
				$restriction = $restriction.$resTopic;
				$i++; 
			}
			$uniqueattendance = new stdclass;
			$uniqueattendance->id = $idSection;									
			$uniqueattendance->availability = $restriction;		       				
			$DB->update_record('course_sections', $uniqueattendance);
		}
	}
}

    /**
     * Delete the last question from the quiz
     *
     * @param int $category category of course questions
	 */
function delete_question($category) {
	global $DB;

	$idQ = 0;
	$quests = $DB->get_records_sql('SELECT * FROM {question} WHERE category = ?', array($category->id));
	foreach ($quests as $quest) {
		$idQ = $quest->id;
	}
	$DB->delete_records("question", array('category' => $category->id, 'id' => $idQ));
	$tfs = $DB->get_records_sql('SELECT * FROM {question_truefalse} WHERE question = ?', array($idQ));
	foreach ($tfs as $tf) {
		$DB->delete_records("question_answers", array('question' => $idQ, 'id' => $tf->trueanswer));
		$DB->delete_records("question_answers", array('question' => $idQ, 'id' => $tf->falseanswer));
	}
	$DB->delete_records("question_truefalse", array('question' => $idQ));
	$DB->delete_records("quiz_slots", array('questionid' => $idQ));
}

    /**
     * Create a quiz guide for teacher in section 0.
     *
     * @param int $idCourse course id
     */ 
function add_quizguide($idCourse) {
	global $DB, $CFG;
	
	$guide = new stdClass();
	$guide->course = $idCourse;
	// Set here the name page.
	$course = $DB->get_record("course", array('id' => $idCourse));
	$guide->name = get_string('guidename', 'format_'.$course->format);
	// Set here description. May be empty.
	$guide->intro = 'Instructions for teacher';
	$guide->introformat = 1;
	// Guide language. English (EN) or Italian (IT) availables.
	if (current_language()=='it') {
			$lang = 'it';
	} else {
		$lang = 'en';
	}
	// Here path of html guide to modify course or quiz.
	$guidepath = $CFG->dirroot . '/course/format/qrtopics/resources/guides/'.$lang.'_quizguide.html';
	$guide->content = file_get_contents($guidepath);
	$guide->contentformat = 1;
	$guide->legacyfiles = 0;
	$guide->legacyfileslast = NULL;
	$guide->display = 5;
	$guide->displayoptions = 'a:2:{s:12:"printheading";s:1:"0";s:10:"printintro";s:1:"0";}';
	$guide->revision = 1;
	$guide->timemodified = time();
	$guide->id = $DB->insert_record("page", $guide);

	$pagemod = new stdClass();
	$pagemod->course = $idCourse;
	$pagemod->module = 15;
	$pagemod->instance = $guide->id;
	// Set visible = 1 if you want to show guide to stedents.
	$pagemod->visible = 0;
	$pagemod->section = 0;
	$pagemod->added = time();
	unset($pagemod->id);
	$cmid = $DB->insert_record("course_modules", $pagemod);
	$pagemod->coursemodule = $cmid;
	rebuild_course_cache($pagemod->course, true);
	$sectionid = course_add_cm_to_section($idCourse, $pagemod->coursemodule, 0);
}


