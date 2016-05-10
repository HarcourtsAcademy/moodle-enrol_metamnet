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
 * Event observer for Meta MNet enrolment plugin.
 *
 * @package     enrol_metamnet
 * @author      Tim Butler
 * @copyright   2016 Harcourts International Limited {@link http://www.harcourtsacademy.com}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/enrol/metamnet/locallib.php');

class enrol_metamnet_observer {

    /**
     * Triggered via user_enrolment_created event.
     *
     * @param \core\event\user_enrolment_created $event
     * @return bool true on success.
     */
    public static function user_enrolment_created(\core\event\user_enrolment_created $event) {
        if (!enrol_is_enabled('metamnet')) {
            // Ignore if the plugin is disabled.
            return true;
        }
        
        $enrolmetamnethandler = new enrol_metamnet_handler();
        $enrolmetamnethandler->sync_course_instances($event->courseid, $event->relateduserid);
        
        return true;
    }

    /**
     * Triggered via user_enrolment_deleted event.
     *
     * @param \core\event\user_enrolment_deleted $event
     * @return bool true on success.
     */
    public static function user_enrolment_deleted(\core\event\user_enrolment_deleted $event) {
        if (!enrol_is_enabled('metamnet')) {
            // Ignore if the plugin is disabled.
            return true;
        }

        $enrolmetamnethandler = new enrol_metamnet_handler();
        $enrolmetamnethandler->sync_course_instances($event->courseid, $event->relateduserid);

        return true;
    }

    /**
     * Triggered via user_enrolment_updated event.
     *
     * @param \core\event\user_enrolment_updated $event
     * @return bool true on success
     */
    public static function user_enrolment_updated(\core\event\user_enrolment_updated $event) {
        if (!enrol_is_enabled('metamnet')) {
            // Ignore if the plugin is disabled.
            return true;
        }
        
        $enrolmetamnethandler = new enrol_metamnet_handler();
        $enrolmetamnethandler->sync_course_instances($event->courseid, $event->relateduserid);

        return true;
    }

    /**
     * Triggered via course_deleted event.
     *
     * @param \core\event\course_deleted $event
     * @return bool true on success
     */
    public static function course_deleted(\core\event\course_deleted $event) {

        if (!enrol_is_enabled('metamnet')) {
            // Ignore if the plugin is disabled.
            return true;
        }

        // Sync everything because deleting a course leave no trace of it or
        // its enrolment instances when this event is observed.
        $enrolmetamnethelper = new enrol_metamnet_helper();
        $enrolmetamnethelper->sync_instances();

        return true;
    }

}
