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

    /**
     * Form definition
     *
     * @return null|void
     */
    protected function definition() {
        global $CFG, $DB, $OUTPUT, $PAGE;

        $mform  = $this->_form;
        $course = $this->_customdata['course'];
        $instance = $this->_customdata['instance'];
        $this->course = $course;

        // Use cached list of courses.
        $usecache = optional_param('usecache', true, PARAM_BOOL);

        if (!$usecache) {
            // Our local database will be changed.
            require_sesskey();
        }

        $mform->addElement('header', 'general', get_string('pluginname', 'enrol_meta'));

        $service = mnetservice_enrol::get_instance();

        if (!$service->is_available()) {
            $mform->addElement('html', $OUTPUT->box(get_string('mnetdisabled', 'mnet'), 'noticebox'));
            return;
        }

        $mform->addElement('checkbox', 'customint2', get_string('setting_notifications', 'enrol_metamnet'),
                get_string('setting_email', 'enrol_metamnet'), array('checked' => true));
        if (!empty($instance->customint2) && is_bool($instance->customint2)) {
            $mform->setDefault('customint2', $instance->customint2);
        }
        $mform->setType('customint2', PARAM_INT);

        $mform->addElement('date_selector', 'customint3', get_string('startdate', 'enrol_metamnet'));
        if (!empty($instance->customint3) && is_int($instance->customint3)) {
            $mform->setDefault('customint3', $instance->customint3);
        } else {
            $mform->setDefault('customint3', time() + 3600 * 24);
        }

        $roamingusers = get_users_by_capability(context_system::instance(), 'moodle/site:mnetlogintoremote', 'u.id');
        if (empty($roamingusers)) {
            $capname = get_string('site:mnetlogintoremote', 'role');
            $url = new moodle_url('/admin/roles/manage.php');
            $mform->addElement('html', notice(get_string('noroamingusers', 'mnetservice_enrol', $capname), $url));
        }
        unset($roamingusers);

        // Remote hosts that may publish remote enrolment service and we are subscribed to it.
        $hosts = $service->get_remote_publishers();

        if (empty($hosts)) {
            $mform->addElement('html', $OUTPUT->box(get_string('nopublishers', 'mnetservice_enrol'), 'noticebox'));
            return;
        }

        // Get existing meta mnet enrollments to prevent duplicates.
        $existing = $DB->get_fieldset_select('enrol', 'customint1', 'enrol = "metamnet" and courseid=' . $course->id);

        foreach ($hosts as $host) {
            $mform->addElement('html', '<h3><a href="' . $host->hosturl . '">' . s($host->hostname) . '</a></h3>');

            $courses = $service->get_remote_courses($host->id, $usecache);
            if (is_string($courses)) {
                $mform->addElement('html', $service->format_error_message($courses));
            }

            if (empty($courses)) {
                $a = (object)array('hostname' => s($host->hostname), 'hosturl' => s($host->hosturl));
                $mform->addElement('html',
                        $OUTPUT->box(get_string('availablecoursesonnone', 'mnetservice_enrol', $a), 'noticebox'));
            }

            $icon = html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('i/course'), 'alt' => get_string('category')));
            $prevcat = null;
            foreach ($courses as $course) {
                $course = (object)$course;
                if ($prevcat !== $course->categoryid) {
                    $mform->addElement('html', '<h4>' . $icon . s($course->categoryname) . '</h4>');
                    $prevcat = $course->categoryid;
                }
                if ((!$instance && in_array($course->id, $existing)) ||
                        ($instance && in_array($course->id, $existing) && $course->id != $instance->customint1)) {
                    $mform->addElement('radio', 'disabled' . $course->id, s($course->fullname)
                            . ' (' . s($course->rolename) . ')', get_string('setting_inuse', 'enrol_metamnet'), $course->id);
                    $mform->freeze('disabled' . $course->id);
                } else {
                    $mform->addElement('radio', 'customint1', s($course->fullname)
                            . ' (' . s($course->rolename) . ')', null, $course->id);
                }
            }

        }
        $mform->setType('customint1', PARAM_INT);

        $mform->addElement('html', '<a href="'
                . new moodle_url($PAGE->url, array('usecache' => 0, 'sesskey' => sesskey())) . '" class="btn">'
                . get_string('refetch', 'mnetservice_enrol') . '</a>');

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

    /**
     * Validates the submitted form for errors.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     *
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     *
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    public function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);

        if (!isset($data['customint1'])) {
            $errors['errors'] = get_string('setting_course', 'enrol_metamnet');
        } else {
            // Check if the remote course exists and display an error if it doesn't.
            $remotecourse = $DB->get_record('mnetservice_enrol_courses', array('id' => $data['customint1']));

            if (empty($remotecourse)) {
                $errors['errors'] = get_string('setting_course', 'enrol_metamnet');
            }
        }

        return $errors;
    }
}

