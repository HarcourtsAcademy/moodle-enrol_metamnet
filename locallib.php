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

require_once($CFG->dirroot.'/mnet/service/enrol/locallib.php');

/**
 * Event handler for Meta MNet enrolment plugin.
 *
 * We try to keep everything in sync via listening to events,
 * it may fail sometimes, so we always do a full sync in cron too.
 */
class enrol_metamnet_handler {
    
    public $mnetservice;
    
    public function __construct() {
        $this->mnetservice = mnetservice_enrol::get_instance();
    }

    /**
     * Synchronise Meta MNet enrolments of this user in this course
     * @static
     * @param int $courseid
     * @param int $userid
     * @return void
     */
    public function sync_course_instances($courseid, $userid) {
        
        // Get all enrolment instances for the course
        $courseenrolmentinstances = $this->get_enrolment_instances($courseid);
        $metamnetenrolinstances = $this->filter_metamnet_enrolment_instances($courseenrolmentinstances);
        if (empty($metamnetenrolinstances)) {
            // Skip if there are no metamnet enrolment instances
            return;
        }
        
        $enrolmentinstanceids = $this->filter_enrolment_ids($courseenrolmentinstances);
        error_log('$enrolment_instance_ids: ' . print_r($enrolmentinstanceids, true));
        
        // Get active (non-metamnet) user enrolments for the user in the course
        $userenrolments = $this->get_user_enrolments_from_ids($userid, $enrolmentinstanceids);
        
        if (empty($userenrolments)) {
            // unenrol the user from all metamnet enrolled courses
            foreach ($metamnetenrolinstances as $metamnetinstance) {
                $this->remote_unenrol(array($userid), $metamnetinstance->customint1);
            }
        } else {
            // enrol the user from all metamnet enrolled courses
            foreach ($metamnetenrolinstances as $metamnetinstance) {
                $this->remote_enrol(array($userid), $metamnetinstance->customint1);
            }
        }
    }
    
    /**
     * Fetches updated course enrolments from remote courses
     *
     * @param int $hostid of MNet host
     * @param int $courseid of MNet course
     * @param bool $usecache true to force remote refresh
     * @return null
     */
    protected function check_cache($hostid, $courseid, $usecache = true) {
        $lastfetchenrolments = get_config('mnetservice_enrol', 'lastfetchenrolments');

        if (!$usecache or empty($lastfetchenrolments) or (time()-$lastfetchenrolments > 600)) {
            // fetch fresh data from remote if forced or every 10 minutes
            $usecache = false;
            $result = $this->mnetservice->req_course_enrolments($hostid, $courseid, $usecache);
            if ($result !== true) {
                error_log($this->mnetservice->format_error_message($result));
            }
        }

        return;
    }
    
    /**
     * Get all **non-metamnet** enrolment ids from an array of enrolment instances
     *
     * @param stdClass[] array of all enrolment instances
     * @return stdClass[]|null array of all enrolment instance ids
     */
    protected function filter_enrolment_ids($courseenrolmentinstances) {
        $enrolmentids = array();

        foreach ($courseenrolmentinstances as $instance) {
            // skip metamnet enrolment instances
            if ($instance->enrol == 'metamnet') {
                continue;
            }
            // avoid duplicates
            $enrolmentids[$instance->id] = $instance->id;
        }

        return $enrolmentids;
    }

    /**
     * Get all metamnet enrolment instances for a course
     *
     * @param stdClass[] $courseenrolmentinstances
     * @return stdClass[]|null array of metamnet enrolment instances
     */
    protected function filter_metamnet_enrolment_instances($courseenrolmentinstances) {
        $metamnetenrolmentinstances = array();

        foreach ($courseenrolmentinstances as $instance) {
            if ($instance->enrol == 'metamnet') {
                $metamnetenrolmentinstances[] = $instance;
            }
        }

        return $metamnetenrolmentinstances;
    }

    /**
     * Get all enrolment instances for a course
     *
     * @param int $courseid one course id, empty mean all
     * @return stdClass[]|null array of all enrolment instances for the course(s)
     */
    protected function get_enrolment_instances($courseid) {
        global $DB;
        return $DB->get_records('enrol', array('courseid'=>$courseid), '', '*');
    }

    /**
     * Get a remote MNet course
     *
     * @param int $hostid the MNet host id
     * @param int $courseid the MNet course id
     * @return stdClass|null the remote course
     */
    protected function get_remote_course($hostid, $courseid) {
        global $DB;
        return $DB->get_record('mnetservice_enrol_courses', array('remoteid'=>$courseid, 'hostid'=>$hostid), '*', MUST_EXIST);
    }

    /**
    * Get the remote host and course ids
    *
    * @param int $mnetcourseid of the remote course in the mnetservice_enrol_courses table
    * @return int[]|null array containing the remote host and course ids
    */
    protected function get_remote_host_and_course_ids($mnetcourseid) {
        global $DB;
        return $DB->get_record('mnetservice_enrol_courses', array('id'=>$mnetcourseid),'hostid,remoteid', MUST_EXIST);
    }

    /**
     * Get all user enrolments from enrolment ids
     *
     * @param int $userid
     * @param int[] $enrolmentinstanceids array of enrolment instance ids
     * @return stdClass[]|null array of all user enrolments
     */
    protected function get_user_enrolments_from_ids($userid, $enrolmentinstanceids) {
        global $DB;

        $sql = "SELECT *
                FROM {user_enrolments} ue
                WHERE ue.enrolid in (:enrolids)
                  AND ue.userid = :userid
                  AND ue.status = :status";
        return $DB->get_records_sql($sql,
                            array(
                                'enrolids'=>implode(',', $enrolmentinstanceids),
                                'userid'=>$userid,
                                'status'=>ENROL_USER_ACTIVE
                            ));
    }

    /**
     * Enrol the user(s) in the remote MNet course
     *
     * @param int[] $enroluserids
     * @param int $mnetcourseid id of the course (mnetservice_enrol_courses)
     * @return bool true if successful
     */
    protected function remote_enrol($enroluserids, $mnetcourseid) {
        global $DB;
        
        error_log('Remote enrolling $enroluserids from ' . $mnetcourseid . ': ' . print_r($enroluserids, true));

        $mnetserviceenrolcourses = $this->get_remote_host_and_course_ids($mnetcourseid);
        error_log('$mnetserviceenrolcourses: ' . print_r($mnetserviceenrolcourses, true));

        $this->check_cache($mnetserviceenrolcourses->hostid, $mnetserviceenrolcourses->remoteid);

        $remotecourse = $this->get_remote_course($mnetserviceenrolcourses->hostid,
                                          $mnetserviceenrolcourses->remoteid);

        error_log('$remotecourse: ' . print_r($remotecourse, true));

        foreach($enroluserids as $enroluser) {
            $user = $DB->get_record('user', array('id'=>$enroluser), '*', MUST_EXIST);
            $result = $this->mnetservice->req_enrol_user($user, $remotecourse);
            if ($result !== true) {
                error_log($this->mnetservice->format_error_message($result));
            }
        }
    }

    /**
     * Unenrol the user(s) in the remote MNet course
     *
     * @param int[] $unenroluserids
     * @param int $mnetcourseid id of the course (mnetservice_enrol_courses)
     * @return bool true if successful
     */
    protected function remote_unenrol($unenroluserids, $mnetcourseid) {
        global $DB;
        
        error_log('Remote un-enrolling $unenroluserids from ' . $mnetcourseid . ': ' . print_r($unenroluserids, true));

        $mnetserviceenrolcourses = $this->get_remote_host_and_course_ids($mnetcourseid);
        error_log('$mnetserviceenrolcourses: ' . print_r($mnetserviceenrolcourses, true));

        $this->check_cache($mnetserviceenrolcourses->hostid, $mnetserviceenrolcourses->remoteid);

        $remotecourse = $this->get_remote_course($mnetserviceenrolcourses->hostid,
                                          $mnetserviceenrolcourses->remoteid);

        error_log('$remotecourse: ' . print_r($remotecourse, true));

        foreach($unenroluserids as $unenroluser) {
            $user = $DB->get_record('user', array('id'=>$unenroluser), '*', MUST_EXIST);
            $result = $this->mnetservice->req_unenrol_user($user, $remotecourse);
            if ($result !== true) {
                error_log($this->mnetservice->format_error_message($result));
            }
        }

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

