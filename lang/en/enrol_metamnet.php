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
 * Strings for component 'enrol_metamnet', language 'en'.
 *
 * @package     enrol_metamnet
 * @author      Tim Butler
 * @copyright   2016 Harcourts International Limited {@link http://www.harcourtsacademy.com}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Meta MNet Enrolment';
$string['pluginname_desc'] = 'Meta MNet Enrolment plugin synchronises enrolments across Moodle MNet hosts.';
$string['metamnetsync'] = 'Meta MNet Enrolment Sync';

$string['setting_notifications']  = 'Notify users when remote enrolled';
$string['setting_email']          = 'By email';
$string['setting_inuse']          = '(In use)';
$string['setting_course']         = 'Please select a course.';

// Email contents

$string['email_subject']        = 'Bonus Academy Real Estate Training online course';
$string['email_heading']        = 'Congratulations!';
$string['email_subheading']     = 'You have received a bonus Academy Real Estate Training online course.';
$string['email_aboutus']        = 'We provide real estate training in class and online for all roles including office administrators, property managers, sales consultants, managers, business owners and more.';
$string['email_findus']         = '<a href="http://www.harcourtsacademy.com/">Find us online</a>';
$string['email_footer']         = 'This email was sent to you because you received a bonus Academy Real Estate Training online course.';
$string['email_addressheader']  = 'Our mailing address is:';
$string['email_address']        = '31 Amy Johnson Place Eagle Farm, QLD 4009 Australia';
$string['email_copyright']      = '© Copyright Harcourts International';

$string['email_rawtext']        = '
Hi {$a->firstname}

Congratulations! You have received a bonus
Academy Real Estate Training online course.

{$a->textcourselinks}

-------------------------------------------------------------------

We provide real estate training in class and online for all roles
including office administrators, property managers,
sales consultants, managers, business owners and more.

                          Find us online
               (http://www.harcourtsacademy.com/)
-------------------------------------------------------------------

(c) Copyright Harcourts International

Our mailing address is:
31 Amy Johnson Place
Eagle Farm, QLD 4009
Australia';
