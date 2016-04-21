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

        if ($instance) {
            $where = 'WHERE c.id = :courseid';
            $params = array('courseid' => $instance->customint1);
            $existing = array();
        } else {
            $where = '';
            $params = array();
            $existing = $DB->get_records('enrol', array('enrol' => 'metamnet', 'courseid' => $course->id), '', 'customint1, id');
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
                $mform->addElement('radio', 'cusomtint1', s($course->fullname) . ' (' . s($course->rolename) . ')', null, $course->id);
            }
            
            $mform->addElement('html', $OUTPUT->single_button(new moodle_url($PAGE->url, array('usecache'=>0, 'sesskey'=>sesskey())),
                    get_string('refetch', 'mnetservice_enrol'), 'get'));
        }
        
        /*
        $mform->addElement('select', 'link', get_string('linkedcourse', 'enrol_meta'), $courses);
        $mform->addRule('link', get_string('required'), 'required', null, 'client');

        $mform->addElement('select', 'customint2', get_string('addgroup', 'enrol_meta'), $groups);

        $mform->addElement('hidden', 'id', null);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'enrolid');
        $mform->setType('enrolid', PARAM_INT);
         */

        error_log("here45");

        $data = array('id' => $course->id);

        $submit = get_string('addinstance', 'enrol');
        if ($instance) {
            $data['customint1'] = $instance->customint1;
            $data['enrolid'] = $instance->id;
            $submit = null;
        }

        //$mform->add_action_buttons();
        $this->add_action_buttons(true, $submit);
        $this->set_data($data);
    }

    function validation($data, $files) {
        global $DB, $CFG;

        $errors = parent::validation($data, $files);

        if ($this->_customdata['instance']) {
            // Nothing to validate in case of editing.
            return $errors;
        }

        // todo: write add instance validation.

        return $errors;
    }
}

