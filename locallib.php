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

        if (!$usecache or empty($lastfetchenrolments) or (time() - $lastfetchenrolments > 600)) {
            // Fetch fresh data from remote if forced or every 10 minutes.
            $usecache = false;
            $result = $this->mnetservice->req_course_enrolments($hostid, $courseid, $usecache);
            if ($result !== true) {
                trigger_error($this->mnetservice->format_error_message($result), E_USER_WARNING);
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
            // Skip metamnet enrolment instances.
            if ($instance->enrol == 'metamnet') {
                continue;
            }
            // Avoid duplicates.
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
        return $DB->get_records('enrol', array('enrol' => 'metamnet', 'status' => ENROL_INSTANCE_ENABLED), '', '*');
    }

    /**
     * Get an enrolment instances from the id
     *
     * @param int $enrolid the enrolment id
     * @return stdClass|null the enrolment instance
     */
    protected function get_enrolment_instance($enrolid) {
        global $DB;
        return $DB->get_record('enrol', array('id' => $enrolid, 'status' => ENROL_INSTANCE_ENABLED), '*');
    }

    /**
     * Get all enrolment instances for a course
     *
     * @param int $courseid one course id, empty mean all
     * @return stdClass[]|null array of all enrolment instances for the course(s)
     */
    protected function get_enrolment_instances($courseid) {
        global $DB;
        return $DB->get_records('enrol', array('courseid' => $courseid), '', '*');
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
            // All instances and all users (excluding non-Harcourts users).
            $sql = 'SELECT
                        CONCAT(mec.hostid, "-", ue.userid, "-", mec.remoteid) AS id,
                        mec.hostid,
                        ue.userid,
                        mec.remoteid AS remotecourseid,
                        e2.customint2 AS emailnotify
                    FROM
                        {enrol} e1
                            JOIN
                        {user_enrolments} ue ON e1.id = ue.enrolid
                            JOIN
                        {enrol} e2
                            JOIN
                        {mnetservice_enrol_courses} mec ON e2.customint1 = mec.id
                            JOIN
                        {user} u ON u.id = ue.userid
                    WHERE
                        e1.courseid = e2.courseid
                            AND e2.enrol = "metamnet"
                            AND ue.status = 0
                            AND e1.status = 0
                            AND e2.status = 0
                            AND SUBSTRING(u.username, 1, 3) != "ac_"
                            AND e2.customint3 < ue.timecreated
                    GROUP BY id';
            $params = array();

        } else {
            // Limited to the given user (excluding non-Harcourts users).
            $sql = 'SELECT
                    CONCAT(mec.hostid, "-", ue.userid, "-", mec.remoteid) AS id,
                    mec.hostid,
                    ue.userid,
                    mec.remoteid AS remotecourseid,
                    e2.customint2 AS emailnotify
                FROM
                    {enrol} e1
                        JOIN
                    {user_enrolments} ue ON e1.id = ue.enrolid
                        JOIN
                    {enrol} e2
                        JOIN
                    {mnetservice_enrol_courses} mec ON e2.customint1 = mec.id
                        JOIN
                    {user} u ON u.id = ue.userid
                WHERE
                    e1.courseid = e2.courseid
                        AND e2.enrol = "metamnet"
                        AND ue.status = 0
                        AND e1.status = 0
                        AND e2.status = 0
                        AND ue.userid = :userid
                        AND SUBSTRING(u.username, 1, 3) != "ac_"
                        AND e2.customint3 < ue.timecreated
                GROUP BY id';
                $params = array('userid' => $userid);
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
            // For all users.
            $sql = 'SELECT
                        id, hostid, remotecourseid
                    FROM
                        {mnetservice_enrol_enrolments}
                    GROUP BY
                        hostid, remotecourseid';
            $params = null; // Used in two DB queries below.

        } else {
            // All instances, only the given userid.
            $sql = 'SELECT
                        id, hostid, remotecourseid
                    FROM
                        {mnetservice_enrol_enrolments}
                    WHERE
                        userid = :userid
                    GROUP BY
                        hostid, remotecourseid';
            $params = array('userid' => $userid); // Used in two DB queries below.
        }

        $remotecourses = $DB->get_records_sql($sql, $params);

        foreach ($remotecourses as $course) {
            $this->check_cache($course->hostid, $course->remotecourseid);
        }

        return $DB->get_records('mnetservice_enrol_enrolments', $params);

    }

    /**
     * Get the remote host and course ids
     *
     * @param int $mnetcourseid of the remote course (mnetservice_enrol_courses)
     * @return stdClass|null remote course
     */
    protected function get_remote_course($mnetcourseid) {
        global $DB;
        return $DB->get_record('mnetservice_enrol_courses', array('id' => $mnetcourseid), '*');
    }

    /**
     * Get all user enrolments for a course with the given enrolment instance
     *
     * @param   int $enrolmentinstanceid Enrolment instance ID
     *
     * @return  stdClass[]|null array of all user enrolments for the course
     */
    protected function get_course_enrolments($enrolmentinstanceid) {
        global $DB;

        if (!empty($enrolmentinstanceid)) {
            $sql = "SELECT
                        *
                    FROM
                        {enrol} e1
                            JOIN
                        {enrol} e2 ON e1.courseid = e2.courseid
                            JOIN
                        {user_enrolments} ue ON ue.enrolid = e1.id
                    WHERE
                        e1.status = :e1instanceenabled
                            AND e2.id = :enrolmentinstanceid
                            AND ue.status = :userenrolmentactive";
            $params = array('enrolmentinstanceid' => $enrolmentinstanceid,
                            'e1instanceenabled' => ENROL_INSTANCE_ENABLED,
                            'userenrolmentactive' => ENROL_USER_ACTIVE
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
            $enrolmentemail = new enrol_metamnet\email\enrolmentemail();

            foreach ($userids as $userid) {
                $user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);
                $result = $this->mnetservice->req_enrol_user($user, $remotecourse);
                if ($result !== true) {
                    trigger_error($this->mnetservice->format_error_message($result), E_USER_WARNING);
                    
                } else {
                    // Email the user a link to the remote course
                    $remotehost = $DB->get_record('mnet_host', array('id' => $enrolment->hostid), '*', MUST_EXIST);
                    $enrolmentemail->send_email($user, $remotehost, $remotecourse);
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
     * @param array $enrolments containing mnetservice_enrol_enrolments *like* objects
     * @return bool true if successful, false otherwise
     */
    protected function remote_enrol_enrolments(array $enrolments) {
        global $DB;

        if (empty($enrolments)) {
            return false;
        }
        
        $enrolmentemail = new enrol_metamnet\email\enrolmentemail();

        foreach ($enrolments as $enrolment) {
            $user = $DB->get_record('user', array('id' => $enrolment->userid), '*', MUST_EXIST);
            $remotecourse = $DB->get_record('mnetservice_enrol_courses', array(
                                            'hostid' => $enrolment->hostid,
                                            'remoteid' => $enrolment->remotecourseid), '*', MUST_EXIST);
            $result = $this->mnetservice->req_enrol_user($user, $remotecourse);
            
            if ($result !== true) {
                trigger_error($this->mnetservice->format_error_message($result), E_USER_WARNING);
                continue;
            }
            
            // Email the user a link to the remote course
            if (!empty($enrolment->emailnotify) && $enrolment->emailnotify) {
                $remotehost = $DB->get_record('mnet_host', array('id' => $enrolment->hostid), '*', MUST_EXIST);
                $enrolmentemail->send_email($user, $remotehost, $remotecourse);
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
                $user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);
                $result = $this->mnetservice->req_unenrol_user($user, $remotecourse);
                if ($result !== true) {
                    trigger_error($this->mnetservice->format_error_message($result), E_USER_WARNING);
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
            $user = $DB->get_record('user', array('id' => $enrolment->userid), '*', MUST_EXIST);
            $remotecourse = $DB->get_record('mnetservice_enrol_courses', array(
                                            'hostid' => $enrolment->hostid,
                                            'remoteid' => $enrolment->remotecourseid), '*', MUST_EXIST);
            $result = $this->mnetservice->req_unenrol_user($user, $remotecourse);
            if ($result !== true) {
                trigger_error($this->mnetservice->format_error_message($result), E_USER_WARNING);
            }
        }
        return true;
    }

    /**
     * Sync a single metamnet enrolment instances
     *
     * @param int $instanceid   The instance ID of the enrolment instance to
     *                          limit the results to.
     *
     * @return int 0 means ok, 1 means error, 2 means plugin disabled
     */
    public function sync_instance($instanceid) {
        global $DB;

        if (!enrol_is_enabled('metamnet')) {
            // Ignore if the plugin is disabled.
            return 2;
        }

        // Get all students in the course with the given enrolment instance.
        $userenrolments = $this->get_course_enrolments($instanceid);

        if (empty($userenrolments)) {
            return 0;
        }

        foreach ($userenrolments as $userenrolment) {
            $user = $DB->get_record('user', array('id' => $userenrolment->userid), '*', MUST_EXIST);

            $correctenrolments = $this->get_correct_course_enrolments($user->id);
            $remoteenrolments = $this->get_remote_course_enrolments($user->id);
            
            $addenrolments = array_udiff($correctenrolments, $remoteenrolments, 'compare_by_hostusercourse');
            $removeenrolments = array_udiff($remoteenrolments, $correctenrolments, 'compare_by_hostusercourse');

            $this->remote_enrol_enrolments($addenrolments);
            $this->remote_unenrol_enrolments($removeenrolments);
        }

        return 0;
    }

    /**
     * Sync meta mnet enrolment instances either all instances or just those for
     * the given user.
     *
     * @param int|null $user   User ID of the user to limit the results to.
     *                         Leave empty for all users
     *
     * @return int 0 means ok, 1 means error, 2 means plugin disabled
     */
    public function sync_instances($userid = null) {
        
        if (!enrol_is_enabled('metamnet')) {
            // Ignore if the plugin is disabled.
            return 2;
        }
        
        // Get all enrolment instances in courses with metamnet instances.
        $correctenrolments = $this->get_correct_course_enrolments($userid);

        $remoteenrolments = $this->get_remote_course_enrolments($userid);

        $addenrolments = array_udiff($correctenrolments, $remoteenrolments, 'compare_by_hostusercourse');
        $removeenrolments = array_udiff($remoteenrolments, $correctenrolments, 'compare_by_hostusercourse');
        
        $this->remote_enrol_enrolments($addenrolments);
        $this->remote_unenrol_enrolments($removeenrolments);

        return 0;
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
