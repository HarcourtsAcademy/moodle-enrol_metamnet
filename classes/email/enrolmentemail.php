<?php

/**
 * Email to notify students they have been enrolled remote Moodle courses
 *
 * @package   enrol_metamnet
 * @author    Tim Butler
 * @copyright (c) 2016 Harcourts International Limited {@link http://www.harcourtsacademy.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

namespace enrol_metamnet\email;

require_once($CFG->libdir . '/weblib.php'); // curl

defined('MOODLE_INTERNAL') || die();

class enrolmentemail {
    
    /**
     * Constructs the enrolment email.
     *
     * @return void
     */
    public function __construct() {

    }
    
    /**
     * Creates the email headers.
     *
     * @return array The email headers
     */
    public function get_email_headers() {
        global $CFG;

        // Create the email headers
        $urlinfo    = parse_url($CFG->wwwroot);
        $hostname   = $urlinfo['host'];

        return array (  // Headers to make emails easier to track
            'Return-Path: <>',
            'List-Id: "Snipcart Order Complete" <enrol_snipcart@'.$hostname.'>',
            'List-Help: '.$CFG->wwwroot,
            'Message-ID: <'.hash('sha256',time()).'@'.$hostname.'>',
            );
    }
    
    /**
     * Creates the course links for the text email.
     *
     * @return string the course links
     */
    public function create_text_course_links($remotehost, $remotecourse) {
        $courseurl = $remotehost->wwwroot . '/course/view.php?id=' . $remotecourse->remoteid;

        return "\r\n{$remotecourse->fullname} ($courseurl)\r\n";
    }
    
    /**
     * Creates the course list for the html email.
     *
     * @return string the course links
     */
    public function create_html_course_list() {
        global $CFG;
        
        $courselist = '';
        
        foreach ($this->snipcartorder->courses as $course) {
            require_once($CFG->libdir. '/coursecatlib.php');
            $course = new \course_in_list($course);
            
            $courseurl = new \moodle_url('/course/view.php', array('id'=>$course->id));
            
            // get the first course image
            $courseoverviewfiles = $course->get_course_overviewfiles();
            $firstfile = array_shift($courseoverviewfiles);
            
            $isimage = (!empty($firstfile) and $firstfile->is_valid_image());
            
            if ($isimage) {
                $courseimageurl = file_encode_url("$CFG->wwwroot/pluginfile.php",
                        '/'. $firstfile->get_contextid(). '/'. $firstfile->get_component(). '/'.
                        $firstfile->get_filearea(). $firstfile->get_filepath(). $firstfile->get_filename(), true);
            } else {
                $courseimageurl = new \moodle_url('/enrol/snipcart/pix/empty-course-icon.png');
            }
            
            $courselist .= '<!-- BEGIN COURSE // -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" class="mcnCaptionBlock" style="border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;-ms-text-size-adjust: 100%;-webkit-text-size-adjust: 100%;background-color: #FFFFFF;">
    <tbody class="mcnCaptionBlockOuter">
        <tr>
            <td class="mcnCaptionBlockInner" valign="top" style="padding: 9px;mso-table-lspace: 0pt;mso-table-rspace: 0pt;-ms-text-size-adjust: 100%;-webkit-text-size-adjust: 100%;">


<table align="left" border="0" cellpadding="0" cellspacing="0" class="mcnCaptionBottomContent" width="false" style="border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;-ms-text-size-adjust: 100%;-webkit-text-size-adjust: 100%;">
    <tbody><tr>
        <td class="mcnCaptionBottomImageContent" align="center" valign="top" style="padding: 0 9px 9px 9px;mso-table-lspace: 0pt;mso-table-rspace: 0pt;-ms-text-size-adjust: 100%;-webkit-text-size-adjust: 100%;">

            <a href="' . $courseurl . '" target="_blank" style="word-wrap: break-word;-ms-text-size-adjust: 100%;-webkit-text-size-adjust: 100%;color: #6DC6DD;font-weight: normal;text-decoration: underline;"><img width="48" height="48" src="' . $courseimageurl . '" style="width: 48px;height: 48px;margin: 0px;border: 0;outline: none;text-decoration: none;-ms-interpolation-mode: bicubic;vertical-align: bottom;"></a>


        </td>
    </tr>
    <tr>
        <td class="mcnTextContent" valign="top" style="padding: 0 9px 0 9px;mso-table-lspace: 0pt;mso-table-rspace: 0pt;-ms-text-size-adjust: 100%;-webkit-text-size-adjust: 100%;color: #606060;font-family: Helvetica;font-size: 15px;line-height: 150%;text-align: center;" width="564">
            <a href="' . $courseurl . '" target="_blank" style="word-wrap: break-word;-ms-text-size-adjust: 100%;-webkit-text-size-adjust: 100%;color: #6DC6DD;font-weight: normal;text-decoration: underline;"><strong>' .$course->fullname . '</strong></a>
        </td>
    </tr>
</tbody></table>
            </td>
        </tr>
    </tbody>
</table>                
                                    <!-- // END COURSE -->';
        }
        
        return $courselist;
    }
    
    /**
     * Creates the content that appears in the text and html emails
     *
     * @param stdclass $touser The email recipient
     * 
     * @return string[] array containing the email variables
     */
    public function create_email_variables($touser, $remotehost, $remotecourse) {
        $a = array(
            'subject'=>get_string('email_subject', 'enrol_metamnet'),
            'logourl'=> new \moodle_url('/enrol/metamnet/pix/logo.png'),
            'firstname'=>$touser->firstname,
            'heading'=>get_string('email_heading', 'enrol_metamnet'),
            'subheading'=>get_string('email_subheading', 'enrol_metamnet'),
            'textcourselinks'=>$this->create_text_course_links($remotehost, $remotecourse),
            'htmlcourselist'=>$this->create_html_course_list(),
            'copyright'=>get_string('copyright', 'enrol_metamnet'),
            'mailingaddress'=>get_string('email_address', 'enrol_metamnet'),
        );
        return $a;
    }
    
    /**
     * Creates the html email
     * 
     * @param string[] $a array containing email variables
     *
     * @return string containing the html email
     */
    public function create_html_email($a) {
        $html = '';
        include 'enrolmentemail_html.php';
        return $html;
    }
    
    /**
     * Sends the course enrolment notification email to the student
     *
     * @param stdclass $touser The email recipient
     * @param stdClass $remotehost A single mnet_host object
     * @param stdClass $remotecourse A single mnetservice_enrol_courses object
     * 
     * @return bool true if successful, false otherwise
     */
    public function send_email($touser, $remotehost, $remotecourse) {
        global $CFG;
        
        error_log('send_email to: ' . print_r($touser->id, true));
        
        if (empty($touser)) {
            error_log('$touser parameter is required. ' . print_r(debug_backtrace(), TRUE), 1, $CFG->supportemail);
            return false;
        }
        
        if (empty($remotecourse)) {
            error_log('$remotecourse parameter is required.' . print_r(debug_backtrace(), TRUE), 1, $CFG->supportemail);
            return false;
        }
        
        // Create the email to send
        $email = new \stdClass();

        $email->customheaders   = $this->get_email_headers();
        $email->subject         = get_string('email_subject', 'enrol_metamnet');
        
        $a                      = $this->create_email_variables($touser, $remotehost,  $remotecourse);
        $email->text            = get_string('email_rawtext', 'enrol_metamnet', $a);
        $email->html            = $this->create_html_email($a);

        // Send it from the support email address
        $fromuser = new \stdClass();
        $fromuser->id = -1;
        $fromuser->email = $CFG->supportemail;
        $fromuser->mailformat = 1;
        $fromuser->maildisplay = 1;
        $fromuser->customheaders = $email->customheaders;
        
        $mailresult = email_to_user($touser, $fromuser, $email->subject, $email->text, $email->html);
        
        error_log('$mailresult: ' . print_r($mailresult, true));

        if (!$mailresult){
            error_log("Error: "
                    . "Could not send out email for remote course {$remotecourse->id} "
                    . "to the student ({$touser->email}). Error: $mailresult .. not trying again.");
            return false;
        }

        return true;
    }
    
    
}
