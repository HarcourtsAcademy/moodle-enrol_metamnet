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
        return $DB->get_record('enrol', array('id'=>$enrolid), '*');
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
     * Get all user enrolments for locally enrolled users not enrolled remotely
     *
     * @param stdClass[] $localuserenrolments array of user_enrolment objects
     * @param stdClass[] $remoteuserenrolments array of mnetservice_enrol_enrolments objects
     * @return int[]|null array of local userids not enrolled remotely
     */
    protected function get_local_users_to_enrol($localuserenrolments, $remoteuserenrolments) {
        $adduserids = null;
        $addusers = array_udiff($localuserenrolments, $remoteuserenrolments, 'compare_by_userid');
        
        if (!empty($addusers)) {
            foreach ($addusers as $user) {
                $adduserids[$user->userid] = $user->userid;
            }
        }
        
        return $adduserids;
    }
    
    /**
     * Get remote MNet course enrolments
     *
     * @param stdClass $remotecourse remote course (mnetservice_enrol_courses)
     * @return stdClass[]|null remote enrolments
     */
    protected function get_remote_course_enrolments($remotecourse) {
        global $DB;
        
        $this->check_cache($remotecourse->hostid, $remotecourse->remoteid, true);
        
        return $DB->get_records('mnetservice_enrol_enrolments', array(
                                        'hostid'=>$remotecourse->hostid,
                                        'remotecourseid'=>$remotecourse->remoteid),
                                '', '*');
        
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
     * Get all user enrolments for remotely enrolled users not enrolled locally
     *
     * @param stdClass[] $remoteuserenrolments array of mnetservice_enrol_enrolments objects
     * @param stdClass[] $localuserenrolments array of user_enrolment objects
     * @return int[]|null array of all local user ids not enrolled remotely
     */
    protected function get_remote_users_to_unenrol($localuserenrolments, $remoteuserenrolments) {
        $removeuserids = null;
        $removeusers = array_udiff($remoteuserenrolments, $localuserenrolments, 'compare_by_userid');
        
        if (!empty($removeusers)) {
            foreach ($removeusers as $user) {
                $removeuserids[$user->userid] = $user->userid;
            }
        }
        
        return $removeuserids;
    }

    /**
     * Get all user enrolments for a single user or all users
     *
     * @param int[] $enrolmentinstanceids array of enrolment instance ids
     * @param int $userid
     * @return stdClass[]|null array of all user enrolments
     */
    protected function get_user_enrolments($enrolmentinstanceids, $userid = null) {
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
        
        error_log('Remote enrolling $userids from ' . $remotecourse->id . ': ' . print_r($userids, true));

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
     * Unenrol the user(s) in the remote MNet course
     *
     * @param int[] $userids
     * @param stdClass $remotecourse (mnetservice_enrol_courses)
     * @return bool true if successful
     */
    protected function remote_unenrol($userids, $remotecourse) {
        global $DB;
        
        error_log('Remote un-enrolling $userids from ' . $remotecourse->id . ': ' . print_r($userids, true));

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
     * Sync one meta mnet enrolment instance.
     *
     * @param stdClass $enrolinstance one enrolment instance
     * @return void
     */
    public function sync_instance($enrolinstance) {
        
        if (!enrol_is_enabled('metamnet')) {
            // Ignore if the plugin is disabled.
            return false;
        }

        // Get all enrolment instances for the course
        $courseenrolmentinstances = $this->get_enrolment_instances($enrolinstance->courseid);
        
        $enrolmentinstanceids = $this->filter_enrolment_ids($courseenrolmentinstances);
//        error_log('$enrolment_instance_ids: ' . print_r($enrolmentinstanceids, true));
        
        // Get active (non-metamnet) user enrolments for all users
        $userenrolments = $this->get_user_enrolments($enrolmentinstanceids);
//        error_log('$userenrolments: ' . print_r($userenrolments, true));
        
        // Get remote cached remote enrolments
        $remotecourse = $this->get_remote_course($enrolinstance->customint1);
        error_log('$remotecourse: ' . print_r($remotecourse, true));
        
        $remoteenrolments = $this->get_remote_course_enrolments($remotecourse);
        
//        error_log('$remoteenrolments: ' . print_r($remoteenrolments, true));
        
        $addusers = $this->get_local_users_to_enrol($userenrolments, $remoteenrolments);
        $removeusers = $this->get_remote_users_to_unenrol($userenrolments, $remoteenrolments);
        
//        error_log('$addusers: ' . print_r($addusers, true));
//        error_log('$removeusers: ' . print_r($removeusers, true));
        
        // enrol the users to add in all metamnet courses
        if (!empty($addusers)) {
            $this->remote_enrol($addusers, $remotecourse);
        }
        
        // unenrol users to remove from all metamnet courses
        if (!empty($removeusers)) {
            $this->remote_unenrol($removeusers, $remotecourse);
        }
    }
    
    /**
     * Sync all meta mnet enrolment instances.
     *
     * @param int $enrolid one enrolment id, empty mean all
     * @return int 0 means ok, 1 means error, 2 means plugin disabled
     */
    public function sync_instances($enrolid = NULL) {

        if (!enrol_is_enabled('metamnet')) {
            // Ignore if the plugin is disabled.
            return 2;
        }

        if (empty($enrolid)) {
            $allinstances = $this->get_all_metamnet_enrolment_instances();
        } else {
            $allinstances = $this->get_enrolment_instance($enrolid);
        }
        
        foreach ($allinstances as $instance) {
            $this->sync_instance($instance);
        }

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
        error_log('$enrolment_instance_ids: ' . print_r($enrolmentinstanceids, true));
        
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
 * Compare function for arrays of user enrolments objects
 *
 * @param stdClass[] $a array of user_enrolments or mnetservice_enrol_enrolments objects
 * @param stdClass[] $b array of user_enrolments or mnetservice_enrol_enrolments objects
 * @return int
 */
function compare_by_userid($a, $b) {
    if ($a->userid < $b->userid) {
        return -1;
    } elseif ($a->userid > $b->userid) {
        return 1;
    } else {
        return 0;
    }
}
