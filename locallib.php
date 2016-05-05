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
 * Local classes and functions for Meta MNet enrolment plugin.
 *
 * @package     enrol_metamnet
 * @author      Tim Butler
 * @copyright   2016 Harcourts International Limited {@link http://www.harcourtsacademy.com}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


/**
 * Event handler for Meta MNet enrolment plugin.
 *
 * We try to keep everything in sync via listening to events,
 * it may fail sometimes, so we always do a full sync in cron too.
 */
class enrol_metamnet_handler {

    /**
     * Synchronise Meta MNet enrolments of this user in this course
     * @static
     * @param int $courseid
     * @param int $userid
     * @return void
     */
    protected static function sync_course_instances($courseid, $userid) {
        global $DB;
        /* Todo: write sync_course_instances() function.
         */
        
        // Get metamnet enrolment instances for the course
        $metamnet_enrol_instances = get_metamnet_enrolment_instances($courseid);
        if (empty($metamnet_enrol_instances)) {
            return;
        }
        
        error_log('$metamnet_enrol_instances: ' . print_r($metamnet_enrol_instances, true));
        
        // Get active user enrolments for the user in the course
//        $user_enrolments = $DB->get_records('user_enrolments', array('userid'=>$userid, 'status'=>ENROL_USER_ACTIVE), '', '*');
        
        // If there are no meta mnet user enrolments then prepare the user
        // enrolment for remote enrolment
        
        // If there are is only a meta mnet user enrolments and no other,
        // prepare the user enrolment for remote unenrolment

        // Get the host and remote course from the mnetservice_enrol_courses table
        
        
    }
}

/**
 * Sync all meta mnet course links.
 *
 * @param int $enrolid one enrolment id, empty mean all
 * @return int 0 means ok, 1 means error, 2 means plugin disabled
 */
function enrol_metamnet_sync($enrolid = NULL) {
    global $DB;
    
    if (empty($enrolid)) {
        return 1;
    }
    
    if (!enrol_is_enabled('metamnet')) {
        return 2;
    }
    
    // todo: sync all enrolments when $enrolid == NULL
    
    // Prepare all user enrolments for cron sync
    
    // Get existing user enrolments
    $userenrolments = $DB->get_records('user_enrolments', array('enrolid' => $enrolid), '', '*');
    
    /* Update all existing user enrolments with the following information 
     * - status: ENROL_USER_ACTIVE (enrollib)
     * - timestart: 0
     * - timeend: 0
     * - modifierid: 0
     * - timecreated: now()
     * - timemodified: now()
     * 
     */

    // Update existing enrollments
    
    // Create new enrolmentsmys
    
    return 0;

}

/**
 * Get all metmnet enrolment instances for a course
 *
 * @param int $courseid one course id, empty mean all
 * @return stdClass[]|null array of metamnet enrolment instances for the course(s)
 */
function get_metamnet_enrolment_instances($courseid) {
    global $DB;
    return $DB->get_records('enrol', array('courseid'=>$courseid, 'enrol'=>'metamnet'), '', '*');
}
