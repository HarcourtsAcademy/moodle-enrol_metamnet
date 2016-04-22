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
 * Meta MNet add instance form
 *
 * @package     enrol_metamnet
 * @author      Tim Butler
 * @copyright   2016 Harcourts International Limited {@link http://www.harcourtsacademy.com}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");
require_once($CFG->dirroot.'/mnet/service/enrol/locallib.php');

class enrol_metamnet_addinstance_form extends moodleform {
    protected $course;

    function definition() {
        global $CFG, $DB, $OUTPUT, $PAGE;

        $mform  = $this->_form;
        $course = $this->_customdata['course'];
        $instance = $this->_customdata['instance'];
        $this->course = $course;
        $usecache = optional_param('usecache', true, PARAM_BOOL); // use cached list of courses
        
        if (!$usecache) {
            // our local database will be changed
            require_sesskey();
        }

        $mform->addElement('header','general', get_string('pluginname', 'enrol_meta'));

        $service = mnetservice_enrol::get_instance();

        if (!$service->is_available()) {
            $mform->addElement('html', $OUTPUT->box(get_string('mnetdisabled','mnet'), 'noticebox'));
            return;
        }

        $roamingusers = get_users_by_capability(context_system::instance(), 'moodle/site:mnetlogintoremote', 'u.id');
        if (empty($roamingusers)) {
            $capname = get_string('site:mnetlogintoremote', 'role');
            $url = new moodle_url('/admin/roles/manage.php');
            $mform->addElement('html', notice(get_string('noroamingusers', 'mnetservice_enrol', $capname), $url));
        }
        unset($roamingusers);

        // remote hosts that may publish remote enrolment service and we are subscribed to it
        $hosts = $service->get_remote_publishers();

        if (empty($hosts)) {
            $mform->addElement('html', $OUTPUT->box(get_string('nopublishers', 'mnetservice_enrol'), 'noticebox'));
            return;
        }
        
        // get existing meta mnet enrollments to prevent duplicates
        $existing = $DB->get_fieldset_select('enrol', 'customint1', 'enrol = "metamnet"');
        
        foreach ($hosts as $host) {
            $mform->addElement('html', '<h3><a href="' . $host->hosturl . '">' . s($host->hostname) . '</a></h3>');
        
            $courses = $service->get_remote_courses($host->id, $usecache);
            if (is_string($courses)) {
                $mform->addElement('html', $service->format_error_message($courses));
            }
            
            if (empty($courses)) {
                $a = (object)array('hostname' => s($host->hostname), 'hosturl' => s($host->hosturl));
                $mform->addElement('html', $OUTPUT->box(get_string('availablecoursesonnone','mnetservice_enrol', $a), 'noticebox'));
            }

            $icon = html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('i/course'), 'alt' => get_string('category')));
            $prevcat = null;
            foreach ($courses as $course) {
                $course = (object)$course;
                if ($prevcat !== $course->categoryid) {
                    $mform->addElement('html', '<h4>' . $icon . s($course->categoryname) . '</h4>');
                    $prevcat = $course->categoryid;
                }
                if ((!$instance && in_array($course->id, $existing)) || ($instance && in_array($course->id, $existing) && $course->id != $instance->customint1)) {
                    $mform->addElement('radio', 'disabled' . $course->id, s($course->fullname) . ' (' . s($course->rolename) . ')', get_string('inuse','enrol_metamnet'), $course->id);
                    $mform->freeze('disabled' . $course->id);
                } else {
                    $mform->addElement('radio', 'customint1', s($course->fullname) . ' (' . s($course->rolename) . ')', null, $course->id);
                }
            }
            
        }
        
        $mform->addElement('html', '<a href="' . new moodle_url($PAGE->url, array('usecache'=>0, 'sesskey'=>sesskey())) . '" class="btn">' . 
                get_string('refetch', 'mnetservice_enrol') . '</a>');
        
        $mform->addElement('static', 'errors', '', '');
        
        $enrolid = $instance ? $instance->id : null;
        
        $mform->addElement('hidden', 'enrolid', $enrolid);
        $mform->setType('enrolid', PARAM_INT);
        $mform->addElement('hidden', 'courseid', $this->course->id);
        $mform->setType('courseid', PARAM_INT);

        $submit = get_string('addinstance', 'enrol');
        if ($instance) {
            $submit = null;
        }
        
        $this->add_action_buttons(true, $submit);
        $this->set_data($instance);
    }

    function validation($data, $files) {
        
        $errors = parent::validation($data, $files);
        
        if (!isset($data['customint1'])) {
            $errors['errors'] = "Please select a course."; // todo: Convert to language string
        }
        
        

        // todo: write add instance validation.

        return $errors;
    }
}

