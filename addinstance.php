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
 * Adds new instance of enrol_metamnet to specified course.
 *
 * @package     enrol_metamnet
 * @author      Tim Butler
 * @copyright   2016 Harcourts International Limited {@link http://www.harcourtsacademy.com}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once("$CFG->dirroot/enrol/metamnet/addinstance_form.php");
require_once("$CFG->dirroot/enrol/metamnet/locallib.php");

$courseid = required_param('courseid', PARAM_INT); // course id
$message = optional_param('message', null, PARAM_TEXT);
$instanceid = optional_param('enrolid', 0, PARAM_INT);

$course = $DB->get_record('course', array('id'=>$courseid), '*', MUST_EXIST);
$context = context_course::instance($course->id, MUST_EXIST);

$PAGE->set_url('/enrol/metamnet/addinstance.php', array('courseid'=>$course->id));
$PAGE->set_pagelayout('admin');

navigation_node::override_active_url(new moodle_url('/enrol/instances.php', array('id'=>$course->id)));

require_login($course);
require_capability('moodle/course:enrolconfig', $context);

$enrol = enrol_get_plugin('metamnet');
if ($instanceid) {
    require_capability('enrol/metamnet:config', $context);
    $instance = $DB->get_record('enrol', array('courseid' => $course->id, 'enrol' => 'metamnet',
        'id' => $instanceid), '*', MUST_EXIST);

} else {
    if (!$enrol->get_newinstance_link($course->id)) {
        redirect(new moodle_url('/enrol/instances.php', array('id' => $course->id)));
    }
    $instance = null;
}

$mform = new enrol_metamnet_addinstance_form(null, array('course' => $course, 'instance' => $instance));

// get existing meta mnet enrollments to prevent duplicates
$existing = $DB->get_fieldset_select('enrol', 'customint1', 'enrol = "metamnet"');

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/enrol/instances.php', array('id'=>$course->id)));

} else if ($data = $mform->get_data()) {
    if ($instance) {
        if ($data->customint1 != $instance->customint1) {
            $DB->update_record('enrol', array('id' => $instance->id, 'customint1' => $data->customint1));
            enrol_metamnet_sync($instance->id);
        }
    } else if (!in_array($data->customint1, $existing)) {
        $enrolid = $enrol->add_instance($course, array('customint1' => $data->customint1));
        if ($enrolid) {
            enrol_metamnet_sync($enrolid);
        }
    }
    redirect(new moodle_url('/enrol/instances.php', array('id' => $course->id)));
}

$PAGE->set_heading($course->fullname);
$PAGE->set_title(get_string('pluginname', 'enrol_metamnet'));

echo $OUTPUT->header();

if ($message === 'added') {
    echo $OUTPUT->notification(get_string('instanceadded', 'enrol'), 'notifysuccess');
}

$mform->display();

echo $OUTPUT->footer();
