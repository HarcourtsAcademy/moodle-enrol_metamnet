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
 * The metammnet_enrolled event class.
 *
 * @property-read array $other {
 *      Event logged when a student is enrolled in a remote MNet course.
 * }
 *
 * @since     Moodle 2014051207.00
 * @package   enrol_metamnet
 * @author    Tim Butler
 * @copyright (c) 2016 Harcourts International Limited {@link http://www.harcourtsacademy.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

namespace enrol_metamnet\event;

defined('MOODLE_INTERNAL') || die();

class metamnet_enrolled extends \core\event\base {
    protected function init() {
        $this->data['crud'] = 'c'; // c(reate), r(ead), u(pdate), d(elete)
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'mnetservice_enrol_enrolments';
    }

    public static function get_name() {
        return get_string('metamnetenrolled', 'enrol_metamnet');
    }

    public function get_description() {
        return "The user with id {$this->userid} "
        . "was enrolled in the course {$this->objectid} "
        . "on the host {$this->other} "
        . "when they were enrolled in the course {$this->courseid}.";
    }
    
    public function get_url() {
        global $DB;
        
        $remotecourse = $DB->get_record('mnetservice_enrol_courses',
                array('hostid' => $this->other, 'remoteid' => $this->objectid));
        
        return new \moodle_url('/mnet/service/enrol/course.php',
                array('host' => $this->other,
                      'course' => $remotecourse->id,
                      'usecache' => 0,
                      'sesskey' => sesskey()));
    }
}
