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
 * it may fail sometimes, so we always do a full sync in a
 * scheduled task too.
 */
class enrol_metamnet_handler {

    /**
     * Synchronise Meta MNet enrolments of this user in this course
     * 
     * @param int $courseid
     * @param int $userid
     * @return void
     */
    public function sync_course_instances($courseid, $userid) {
        
        $helper = new enrol_metamnet_helper();
        $helper->sync_user_in_course($courseid, $userid);
    }
}

/**
 * Helper for Meta MNet enrolment plugin.
 *
 */
class enrol_metamnet_helper {
    
    public $mnetservice;
    
    public function __construct() {
        $this->mnetservice = mnetservice_enrol::get_instance();
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
            if ($instance->enrol == 'metamnet' and $instance->status == ENROL_INSTANCE_ENABLED) {
                $metamnetenrolmentinstances[] = $instance;
            }
        }

        return $metamnetenrolmentinstances;
    }
    
    /**
     * Get all active metamnet enrolment instances for all courses
     *
     * @return stdClass[]|null array of all enrolment instances for all courses
     */
    protected function get_all_metamnet_enrolment_instances() {
        global $DB;
        return $DB->get_records('enrol', array('enrol'=>'metamnet', 'status'=>ENROL_INSTANCE_ENABLED), '', '*');
    }

    /**
     * Get an enrolment instances from the id
     *
     * @param int $enrolid the enrolment id
     * @return stdClass|null the enrolment instance
     */
    protected function get_enrolment_instance($enrolid) {
        global $DB;
        return $DB->get_record('enrol', array('id'=>$enrolid, 'status'=>ENROL_INSTANCE_ENABLED), '*');
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
     * Get all enrolment instance ids for enrolment instances in courses
     * that have active metamnet enrolment instances and that are not
     * metamnet enrolment instances
     * 
     * @return int[]|null of instance ids
     */
    protected function get_enrolment_instances_not_metamnet() {
        global $DB;
        
        $sql = "SELECT 
                    id
                FROM
                    {enrol} e
                WHERE
                    e.status = 0 AND e.enrol != 'metamnet'
                        AND courseid IN (SELECT DISTINCT
                            (courseid)
                        FROM
                            {enrol} e
                        WHERE
                            e.status = 0 AND e.enrol = 'metamnet')";
        return $DB->get_fieldset_sql($sql);
    }
    
    /**
     * Gets an array of mnetservice_enrol_enrolment *like* objects containing
     * the host id, remote course id and user id of remote enrolments that should
     * exist.
     * 
     * @param int|null $userid  User ID of the user to limit the results to.
     *                          Leave empty for all users.
     * 
     * @return stdClass[]|null array of mnetservice_enrol_enrolment *like* objects
     */
    protected function get_correct_course_enrolments($userid = null) {
        global $DB;
        
        if (empty($userid)) {
            // All users
            $sql = 'SELECT 
                        CONCAT(mec.hostid, "-", ue.userid, "-", mec.remoteid) AS id,
                        mec.hostid,
                        ue.userid,
                        mec.remoteid AS remotecourseid
                    FROM
                        {enrol} e1
                            JOIN
                        {user_enrolments} ue ON e1.id = ue.enrolid
                            JOIN
                        {enrol} e2
                            JOIN
                        {mnetservice_enrol_courses} mec ON e2.customint1 = mec.id
                    WHERE
                        e1.courseid = e2.courseid
                            AND e2.enrol = "metamnet"
                            AND ue.status = 0
                            AND e1.status = 0
                            AND e2.status = 0
                    GROUP BY id';
            $params = array();
        } else {
            // Limited to the given user
            $sql = 'SELECT 
                    CONCAT(mec.hostid, "-", ue.userid, "-", mec.remoteid) AS id,
                    mec.hostid,
                    ue.userid,
                    mec.remoteid AS remotecourseid
                FROM
                    {enrol} e1
                        JOIN
                    {user_enrolments} ue ON e1.id = ue.enrolid
                        JOIN
                    {enrol} e2
                        JOIN
                    {mnetservice_enrol_courses} mec ON e2.customint1 = mec.id
                WHERE
                    e1.courseid = e2.courseid
                        AND e2.enrol = "metamnet"
                        AND ue.status = 0
                        AND e1.status = 0
                        AND e2.status = 0
                        AND ue.userid = :userid
                GROUP BY id';
                $params = array('userid'=>$userid);
        }
        
        return $DB->get_records_sql($sql, $params);
    }
    
    /**
     * Get all remote MNet course enrolments
     *
     * @param int|null $userid  User ID of the user to limit the results to.
     *                          Leave empty for all users
     * 
     * @return stdClass[]|null  All remote enrolments
     */
    protected function get_remote_course_enrolments($userid = null) {
        global $DB;
        
        if (empty($userid)) {
            // For all users
            $sql = "SELECT 
                        id, hostid, remotecourseid
                    FROM
                        {mnetservice_enrol_enrolments}
                    GROUP BY
                        hostid, remotecourseid";
            $params = array();
        } else {
            // Only the given userid
            $sql = "SELECT 
                        id, hostid, remotecourseid
                    FROM
                        {mnetservice_enrol_enrolments}
                    WHERE
                        userid = :userid
                    GROUP BY
                        hostid, remotecourseid";
            $params = array('userid'=>$userid);
        }

        $remotecourses = $DB->get_records_sql($sql, $params);
        
        foreach ($remotecourses as $course) {
            $this->check_cache($course->hostid, $course->remotecourseid);
        }
        
        return $DB->get_records('mnetservice_enrol_enrolments');
        
    }

    /**
    * Get the remote host and course ids
    *
    * @param int $mnetcourseid of the remote course (mnetservice_enrol_courses)
    * @return stdClass|null remote course
    */
    protected function get_remote_course($mnetcourseid) {
        global $DB;
        return $DB->get_record('mnetservice_enrol_courses', array('id'=>$mnetcourseid), '*');
    }

    /**
     * Get all user enrolments for a single user or all users
     *
     * @param int[] $enrolmentinstanceids array of enrolment instance ids
     * @param int $userid
     * @return stdClass[]|null array of all user enrolments
     */
    protected function get_user_enrolments(array $enrolmentinstanceids, $userid = null) {
        global $DB;
        
        if (!empty($userid)) {
            $sql = "SELECT *
                    FROM {user_enrolments} ue
                    WHERE ue.enrolid in (:enrolids)
                      AND ue.userid = :userid
                      AND ue.status = :status";
            $params = array('enrolids'=>implode(',', $enrolmentinstanceids),
                            'userid'=>$userid,
                            'status'=>ENROL_USER_ACTIVE
                            );
        } else {
            $sql = "SELECT *
                    FROM {user_enrolments} ue
                    WHERE ue.enrolid in (:enrolids)
                      AND ue.status = :status";
            $params = array('enrolids'=>implode(',', $enrolmentinstanceids),
                            'status'=>ENROL_USER_ACTIVE
                            );
        }

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Enrol the user(s) in the remote MNet course
     *
     * @param int[] $userids
     * @param stdClass $remotecourse (mnetservice_enrol_courses)
     * @return bool true if successful
     */
    protected function remote_enrol($userids, $remotecourse) {
        global $DB;
        
        if (is_array($userids)) {
            foreach($userids as $userid) {
                $user = $DB->get_record('user', array('id'=>$userid), '*', MUST_EXIST);
                $result = $this->mnetservice->req_enrol_user($user, $remotecourse);
                if ($result !== true) {
                    error_log($this->mnetservice->format_error_message($result));
                } else {
                    return $result;
                }
            }
        } else {
            return false;
        }
    }
    
    /**
     * Enrols multiple local students in remote courses
     * 
     * @param array $enrolments containing mnetservice_enrol_enrolments *like* objections
     * @return bool true if successful
     */
    protected function remote_enrol_enrolments(array $enrolments) {
        global $DB;
        
        if (empty($enrolments)) {
            return false;
        }
                
        foreach ($enrolments as $enrolment) {
            $user = $DB->get_record('user', array('id'=>$enrolment->userid), '*', MUST_EXIST);
            $remotecourse = $DB->get_record('mnetservice_enrol_courses', array(
                                            'hostid'=>$enrolment->hostid, 
                                            'remoteid'=>$enrolment->remotecourseid), '*', MUST_EXIST);
            $result = $this->mnetservice->req_enrol_user($user, $remotecourse);
            if ($result !== true) {
                error_log($this->mnetservice->format_error_message($result));
            }
        }
        
        return true;
    }

    /**
     * Unenrol the user(s) in the remote MNet course
     *
     * @param int[] $userids
     * @param stdClass $remotecourse (mnetservice_enrol_courses)
     * @return bool true if successful
     */
    protected function remote_unenrol($userids, $remotecourse) {
        global $DB;
        
        if (is_array($userids)) {
            foreach ($userids as $userid) {
                $user = $DB->get_record('user', array('id'=>$userid), '*', MUST_EXIST);
                $result = $this->mnetservice->req_unenrol_user($user, $remotecourse);
                if ($result !== true) {
                    error_log($this->mnetservice->format_error_message($result));
                } else {
                    return $result;
                }
            }
        } else {
            return false;
        }

    }
    
    /**
     * Unenrols multiple local students from remote courses
     * 
     * @param array $enrolments containing mnetservice_enrol_enrolments *like* objections
     * @return bool true if successful
     */
    protected function remote_unenrol_enrolments(array $enrolments) {
        global $DB;
        
        if (empty($enrolments)) {
            return false;
        }
        
        foreach ($enrolments as $enrolment) {
            $user = $DB->get_record('user', array('id'=>$enrolment->userid), '*', MUST_EXIST);
            $remotecourse = $DB->get_record('mnetservice_enrol_courses', array(
                                            'hostid'=>$enrolment->hostid, 
                                            'remoteid'=>$enrolment->remotecourseid), '*', MUST_EXIST);
            $result = $this->mnetservice->req_unenrol_user($user, $remotecourse);
            if ($result !== true) {
                error_log($this->mnetservice->format_error_message($result));
            }
        }
        return true;
    }
    
    /**
     * Sync meta mnet enrolment instances either all instances or just those for
     * the given user.
     * 
     * @param stdClass|null $user   User ID of the user to limit the results to.
     *                              Leave empty for all users
     *
     * @return int 0 means ok, 1 means error, 2 means plugin disabled
     */
    public function sync_instances($user = null) {

        if (!enrol_is_enabled('metamnet')) {
            // Ignore if the plugin is disabled.
            return 2;
        }
        
        $userid = null;
        if (is_object($user)) {
            $userid = $user->id;
        }
        
        // Get all enrolment instances in courses with metamnet instances
        $correctenrolments = $this->get_correct_course_enrolments($userid);
        
        $remoteenrolments = $this->get_remote_course_enrolments($userid);
        
        $addenrolments = array_udiff($correctenrolments, $remoteenrolments, 'compare_by_hostusercourse');
        $removeenrolments = array_udiff($remoteenrolments, $correctenrolments, 'compare_by_hostusercourse');
        
        $this->remote_enrol_enrolments($addenrolments);
        $this->remote_unenrol_enrolments($removeenrolments);

        return 0;

    }
    
    /**
     * Sync a user in a course with a remote mnet course
     *
     * @param int $courseid of the local course
     * @param int $userid of the local user
     * @return null
     */
    public function sync_user_in_course($courseid, $userid) {
        // Get all enrolment instances for the course
        $courseenrolmentinstances = $this->get_enrolment_instances($courseid);
        $metamnetenrolinstances = $this->filter_metamnet_enrolment_instances($courseenrolmentinstances);
        if (empty($metamnetenrolinstances)) {
            // Skip if there are no metamnet enrolment instances
            return;
        }
        
        $enrolmentinstanceids = $this->filter_enrolment_ids($courseenrolmentinstances);
        
        // Get active (non-metamnet) user enrolments for the user in the course
        $userenrolments = $this->get_user_enrolments($enrolmentinstanceids, $userid);
        
        if (empty($userenrolments)) {
            // unenrol the user from all metamnet enrolled courses
            foreach ($metamnetenrolinstances as $metamnetinstance) {
                $remotecourse = $this->get_remote_course($metamnetinstance->customint1);
                $this->remote_unenrol(array($userid), $remotecourse);
            }
        } else {
            // enrol the user in all metamnet enrolled courses
            foreach ($metamnetenrolinstances as $metamnetinstance) {
                $remotecourse = $this->get_remote_course($metamnetinstance->customint1);
                $this->remote_enrol(array($userid), $remotecourse);
            }
        }
    }
}

/**
 * Compare function for arrays of remote user enrolments
 *
 * @param stdClass[] $a array of objects
 * @param stdClass[] $b array of objects
 * @return int
 */
function compare_by_hostusercourse($a, $b) {
    $ahostusercourse = $a->hostid . '-' . $a->userid . '-' . $a->remotecourseid;
    $bhostusercourse = $b->hostid . '-' . $b->userid . '-' . $b->remotecourseid;
    
    return strcmp($ahostusercourse, $bhostusercourse);
}
