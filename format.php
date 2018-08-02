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
 * topics course format.  Display the whole course as "topics" made of modules.
 *
 * @package format_qrtopics
 * @copyright 2018
 * @author Francesco Pisano
 * @license
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/filelib.php');
require_once($CFG->libdir.'/completionlib.php'); 

// Horrible backwards compatible parameter aliasing..
if ($topic = optional_param('topic', 0, PARAM_INT)) {
    $url = $PAGE->url;
    $url->param('section', $topic);
    debugging('Outdated topic param passed to course/view.php', DEBUG_DEVELOPER);
    redirect($url);
}
// End backwards-compatible aliasing..

$context = context_course::instance($course->id);
// Retrieve course format option fields and add them to the $course object.
$course = course_get_format($course)->get_course();

if (($marker >=0) && has_capability('moodle/course:setcurrentsection', $context) && confirm_sesskey()) {
    $course->marker = $marker;
    course_set_marker($course->id, $marker);
}

/**
 * Customization of the course format.
 *
 * After sections creation, create and add quiz to course (one question per topic).
 * Also check course changes after course creation.
 */
include($CFG->dirroot . '/course/format/qrtopics/qrlib.php');
$idCourse = $course->id;
$sections = $DB->get_records_sql('SELECT * FROM {course_sections} WHERE course = ?', array($idCourse));
$numOfSections = sizeof($sections)-1;
$contextname = $context->get_context_name(false, true);

if (!$DB->record_exists("label", array('name' => 'QRTformat-'.$idCourse))) {
	create_qr_quiz($idCourse, $numOfSections);
	$quiz = $DB->get_record("quiz", array('course' => $idCourse, 'name' => get_string('quizname', 'format_qrtopics')));
	$category = $DB->get_record("question_categories", array('contextid' => $context->id));
	$grade_item = $DB->get_record("grade_items", array('courseid' => $idCourse, 'itemname' => $quiz->name));
	foreach ($sections as $sectionX) {
		$nSection = $sectionX->section;
		$idSection = $sectionX->id;
		if ($nSection != 0) {
			create_qr_question($quiz, $category, $nSection, $context);
		}
	}
	set_restrictions($idCourse, $grade_item);
	// Alert for instructions.
	if (current_language()=='it') {
			$lang = 'it';
	} else {
		$lang = 'en';
	}
	$alertmessage = file_get_contents($CFG->dirroot . '/course/format/qrtopics/resources/alerts/'.$lang.'_alert.html');
	echo "<script type='text/javascript'>alert('$alertmessage');</script>";
} else {
	$label = $DB->get_record("label", array('name' => 'QRTformat-'.$idCourse));
	$quiz = $DB->get_record("quiz", array('id' => $label->intro));
	$grade_item = $DB->get_record("grade_items", array('courseid' => $idCourse, 'iteminstance' => $quiz->id));
	$category = $DB->get_record("question_categories", array('contextid' => $context->id));
	// Check for students quiz attempts.
	$result = $DB->get_records_sql('SELECT * FROM {quiz_attempts} WHERE quiz = ? AND userid != ?', array($quiz->id, $USER->id));
	if (sizeof($result)==0) {
		$questions = $DB->get_records_sql('SELECT * FROM {quiz_slots} WHERE quizid = ?', array($quiz->id));
		if (sizeof($questions)<$numOfSections) {
			$count = $numOfSections - sizeof($questions);
			while ($count != 0) {
				create_qr_question($quiz, $category, sizeof($sections)-$count, $context);
				$count--;
			}
		} else if (sizeof($questions)>$numOfSections) {
			$count = sizeof($questions) - $numOfSections;
			while ($count != 0) {
				delete_question($category);
				$count--;
			}
		}
		update_grade_max($quiz->id, $numOfSections);
		set_restrictions($idCourse, $grade_item);
	}
}

// Clear all cache.
rebuild_course_cache($idCourse);

// Make sure section 0 is created.
course_create_sections_if_missing($course, 0);

$renderer = $PAGE->get_renderer('format_qrtopics');

if (!empty($displaysection)) {
    $renderer->print_single_section_page($course, null, null, null, null, $displaysection);
} else {
    $renderer->print_multiple_section_page($course, null, null, null, null);
}

// Include course format js module
$PAGE->requires->js('/course/format/qrtopics/format.js');
