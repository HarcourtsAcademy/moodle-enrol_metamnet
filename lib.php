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
 * Meta MNet course enrolment plugin.
 *
 * @package     enrol_metamnet
 * @author      Tim Butler
 * @copyright   2016 Harcourts International Limited {@link http://www.harcourtsacademy.com}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/enrol/metamnet/locallib.php");

class enrol_metamnet_plugin extends enrol_plugin {

    /**
     * Returns localised name of enrol instance
     *
     * @param stdClass $instance (null is accepted too)
     * @return string
     */
    public function get_instance_name($instance) {
        global $DB;

        if (empty($instance)) {
            $enrol = $this->get_name();
            return get_string('pluginname', 'enrol_'.$enrol);
        } else if (empty($instance->name)) {
            $enrol = $this->get_name();
            $mnetcourse = $DB->get_record('mnetservice_enrol_courses', array('id' => $instance->customint1));
            if ($mnetcourse) {
                $coursename = format_string($mnetcourse->fullname);
                return get_string('pluginname', 'enrol_' . $enrol) . ' (' . $coursename . ')';
            }
            return get_string('pluginname', 'enrol_'.$enrol);
        } else {
            return format_string($instance->name);
        }
    }

    /**
     * Returns link to page which may be used to add new instance of enrolment plugin in course.
     * @param int $courseid
     * @return moodle_url page url
     */
    public function get_newinstance_link($courseid) {
        $context = context_course::instance($courseid, MUST_EXIST);
        if (!has_capability('moodle/course:enrolconfig', $context) or !has_capability('enrol/metamnet:config', $context)) {
            return null;
        }
        // Multiple instances supported - multiple remote courses linked.
        return new moodle_url('/enrol/metamnet/addinstance.php', array('courseid' => $courseid));
    }

    /**
     * Does this plugin allow manual unenrolment of a specific user? : No
     *
     * @param stdClass $instance course enrol instance
     * @param stdClass $ue record from user_enrolments table
     *
     * @return bool true means user with 'enrol/xxx:unenrol' may unenrol this user
     *              false means nobody may touch this user enrolment
     */
    public function allow_unenrol_user(stdClass $instance, stdClass $ue) {
        return false;
    }

    /**
     * Is it possible to delete enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_delete_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/metamnet:config', $context);
    }

    /**
     * Is it possible to hide/show enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_hide_show_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/metamnet:config', $context);
    }

    /**
     * Returns edit icons for the page with list of instances.
     * @param stdClass $instance
     * @return array
     */
    public function get_action_icons(stdClass $instance) {
        global $OUTPUT;

        if ($instance->enrol !== 'metamnet') {
            throw new coding_exception('invalid enrol instance!');
        }
        $context = context_course::instance($instance->courseid);

        $icons = array();

        if (has_capability('enrol/metamnet:config', $context)) {
            $editlink = new moodle_url("/enrol/metamnet/addinstance.php",
                array('courseid' => $instance->courseid, 'enrolid' => $instance->id));
            $icons[] = $OUTPUT->action_icon($editlink, new pix_icon('t/edit', get_string('edit'), 'core',
                array('class' => 'iconsmall')));
        }

        return $icons;
    }

    /**
     * Forces synchronisation of user enrolments.
     *
     * This is important especially for external enrol plugins,
     * this function is called for all enabled enrol plugins
     * right after every user login.
     *
     * @param object $user user record
     * @return void
     */
    public function sync_user_enrolments($user) {
        $helper = new enrol_metamnet_helper();
        $helper->sync_instances($user->id);
    }

    /**
     * Forces synchronisation of all meta mnet enrolments.
     *
     * @return void
     */
    public function sync() {
        $helper = new enrol_metamnet_helper();
        $helper->sync_instances();
    }

}
