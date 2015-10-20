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
 * Backported functions that in a future exists.
 *
 * @package    local_mobile
 * @copyright  2014 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->dirroot/user/lib.php");

if (!function_exists('user_remove_user_device')) {
    /**
     * Remove a user device from the Moodle database (for PUSH notifications usually).
     *
     * @param string $uuid The device UUID.
     * @param string $appid The app id. If empty all the devices matching the UUID for the user will be removed.
     * @return bool true if removed, false if the device didn't exists in the database
     * @since Moodle 2.9
     */
    function user_remove_user_device($uuid, $appid = "") {
        global $DB, $USER;

        $conditions = array('uuid' => $uuid, 'userid' => $USER->id);
        if (!empty($appid)) {
            $conditions['appid'] = $appid;
        }

        if (!$DB->count_records('user_devices', $conditions)) {
            return false;
        }

        $DB->delete_records('user_devices', $conditions);

        return true;
    }
}

if (!function_exists('get_course_and_cm_from_instance')) {

    /**
     * @since Moodle 2.8
     */
    function get_course_and_cm_from_instance($instanceorid, $modulename, $courseorid = 0, $userid = 0) {
        global $DB;

        // Get data from parameter.
        if (is_object($instanceorid)) {
            $instanceid = $instanceorid->id;
            if (isset($instanceorid->course)) {
                $courseid = (int)$instanceorid->course;
            } else {
                $courseid = 0;
            }
        } else {
            $instanceid = (int)$instanceorid;
            $courseid = 0;
        }

        // Get course from last parameter if supplied.
        $course = null;
        if (is_object($courseorid)) {
            $course = $courseorid;
        } else if ($courseorid) {
            $courseid = (int)$courseorid;
        }

        if (!$course) {
            if ($courseid) {
                // If course ID is known, get it using normal function.
                $course = get_course($courseid);
            } else {
                // Get course record in a single query based on instance id.
                $pagetable = '{' . $modulename . '}';
                $course = $DB->get_record_sql("
                        SELECT c.*
                          FROM $pagetable instance
                          JOIN {course} c ON c.id = instance.course
                         WHERE instance.id = ?", array($instanceid), MUST_EXIST);
            }
        }

        // Get cm from get_fast_modinfo.
        $modinfo = get_fast_modinfo($course, $userid);
        $instances = $modinfo->get_instances_of($modulename);
        if (!array_key_exists($instanceid, $instances)) {
            throw new moodle_exception('invalidmoduleid', 'error', $instanceid);
        }
        return array($course, $instances[$instanceid]);
    }
}

require_once($CFG->dirroot . '/mod/chat/lib.php');
require_once($CFG->dirroot . '/mod/choice/lib.php');


if (!function_exists('chat_get_latest_messages')) {

    /**
     * Return a list of the latest messages in the given chat session.
     *
     * @param  stdClass $chatuser     chat user session data
     * @param  int      $chatlasttime last time messages were retrieved
     * @return array    list of messages
     * @since  Moodle 3.0
     */
    function chat_get_latest_messages($chatuser, $chatlasttime) {
        global $DB;

        $params = array('groupid' => $chatuser->groupid, 'chatid' => $chatuser->chatid, 'lasttime' => $chatlasttime);

        $groupselect = $chatuser->groupid ? " AND (groupid=" . $chatuser->groupid . " OR groupid=0) " : "";

        return $DB->get_records_select('chat_messages_current', 'chatid = :chatid AND timestamp > :lasttime ' . $groupselect,
                                        $params, 'timestamp ASC');
    }
}

if (!function_exists('choice_get_my_response')) {
    /**
     * Return my responses on a specific choice.
     * @param object $choice
     * @return array
     */
    function choice_get_my_response($choice) {
        global $DB, $USER;
        return $DB->get_records('choice_answers', array('choiceid' => $choice->id, 'userid' => $USER->id));
    }
}

if (!function_exists('choice_get_all_responses')) {
    /**
     * Get all the responses on a given choice.
     *
     * @param stdClass $choice Choice record
     * @return array of choice answers records
     * @since  Moodle 3.0
     */
    function choice_get_all_responses($choice) {
        global $DB;
        return $DB->get_records('choice_answers', array('choiceid' => $choice->id));
    }
}

if (!function_exists('choice_can_view_results')) {
    /**
     * Return true if we are allowd to see choice results as student
     * @param object $choice Choice
     * @param rows|null $current my choice responses
     * @param bool|null $choiceopen choice open
     * @return bool True if we can see results, false if not.
     */
    function choice_can_view_results($choice, $current = null, $choiceopen = null) {

        if (is_null($choiceopen)) {
            $timenow = time();
            if ($choice->timeclose != 0 && $timenow > $choice->timeclose) {
                $choiceopen = false;
            } else {
                $choiceopen = true;
            }
        }
        if (is_null($current)) {
            $current = choice_get_my_response($choice);
        }

        if ($choice->showresults == CHOICE_SHOWRESULTS_ALWAYS or
           ($choice->showresults == CHOICE_SHOWRESULTS_AFTER_ANSWER and !empty($current)) or
           ($choice->showresults == CHOICE_SHOWRESULTS_AFTER_CLOSE and !$choiceopen)) {
            return true;
        }
        return false;
    }
}

require_once($CFG->libdir . "/modinfolib.php");

if (!function_exists('get_course_and_cm_from_cmid')) {
    /**
     * Efficiently retrieves the $course (stdclass) and $cm (cm_info) objects, given
     * a cmid. If module name is also provided, it will ensure the cm is of that type.
     *
     * Usage:
     * list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'forum');
     *
     * Using this method has a performance advantage because it works by loading
     * modinfo for the course - which will then be cached and it is needed later
     * in most requests. It also guarantees that the $cm object is a cm_info and
     * not a stdclass.
     *
     * The $course object can be supplied if already known and will speed
     * up this function - although it is more efficient to use this function to
     * get the course if you are starting from a cmid.
     *
     * To avoid security problems and obscure bugs, you should always specify
     * $modulename if the cmid value came from user input.
     *
     * By default this obtains information (for example, whether user can access
     * the activity) for current user, but you can specify a userid if required.
     *
     * @param stdClass|int $cmorid Id of course-module, or database object
     * @param string $modulename Optional modulename (improves security)
     * @param stdClass|int $courseorid Optional course object if already loaded
     * @param int $userid Optional userid (default = current)
     * @return array Array with 2 elements $course and $cm
     * @throws moodle_exception If the item doesn't exist or is of wrong module name
     */
    function get_course_and_cm_from_cmid($cmorid, $modulename = '', $courseorid = 0, $userid = 0) {
        global $DB;
        if (is_object($cmorid)) {
            $cmid = $cmorid->id;
            if (isset($cmorid->course)) {
                $courseid = (int)$cmorid->course;
            } else {
                $courseid = 0;
            }
        } else {
            $cmid = (int)$cmorid;
            $courseid = 0;
        }

        // Validate module name if supplied.
        if ($modulename && !core_component::is_valid_plugin_name('mod', $modulename)) {
            throw new coding_exception('Invalid modulename parameter');
        }

        // Get course from last parameter if supplied.
        $course = null;
        if (is_object($courseorid)) {
            $course = $courseorid;
        } else if ($courseorid) {
            $courseid = (int)$courseorid;
        }

        if (!$course) {
            if ($courseid) {
                // If course ID is known, get it using normal function.
                $course = get_course($courseid);
            } else {
                // Get course record in a single query based on cmid.
                $course = $DB->get_record_sql("
                        SELECT c.*
                          FROM {course_modules} cm
                          JOIN {course} c ON c.id = cm.course
                         WHERE cm.id = ?", array($cmid), MUST_EXIST);
            }
        }

        // Get cm from get_fast_modinfo.
        $modinfo = get_fast_modinfo($course, $userid);
        $cm = $modinfo->get_cm($cmid);
        if ($modulename && $cm->modname !== $modulename) {
            throw new moodle_exception('invalidcoursemodule', 'error');
        }
        return array($course, $cm);
    }
}

require_once($CFG->libdir . '/externallib.php');
if (!class_exists("external_util")) {

    /**
     * Utility functions for the external API.
     *
     * @package    core_webservice
     * @copyright  2015 Juan Leyva
     * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     * @since Moodle 3.0
     */
    class external_util extends external_api{

        /**
         * Validate a list of courses, returning the complete course objects for valid courses.
         *
         * @param  array $courseids A list of course ids
         * @return array            An array of courses and the validation warnings
         */
        public static function validate_courses($courseids) {
            // Delete duplicates.
            $courseids = array_unique($courseids);
            $courses = array();
            $warnings = array();

            foreach ($courseids as $cid) {
                // Check the user can function in this context.
                try {
                    $context = context_course::instance($cid);
                    self::validate_context($context);
                    $courses[$cid] = get_course($cid);
                } catch (Exception $e) {
                    $warnings[] = array(
                        'item' => 'course',
                        'itemid' => $cid,
                        'warningcode' => '1',
                        'message' => 'No access rights in course context'
                    );
                }
            }

            return array($courses, $warnings);
        }

    }
}

if (!function_exists('external_format_string')) {
    /**
     * Format the string to be returned properly as requested by the either the web service server,
     * either by an internally call.
     * The caller can change the format (raw) with the external_settings singleton
     * All web service servers must set this singleton when parsing the $_GET and $_POST.
     *
     * @param string $str The string to be filtered. Should be plain text, expect
     * possibly for multilang tags.
     * @param boolean $striplinks To strip any link in the result text. Moodle 1.8 default changed from false to true! MDL-8713
     * @param int $contextid The id of the context for the string (affects filters).
     * @param array $options options array/object or courseid
     * @return string text
     * @since Moodle 3.0
     */
    function external_format_string($str, $contextid, $striplinks = true, $options = array()) {

        // Get settings (singleton).
        $settings = external_settings::get_instance();
        if (empty($contextid)) {
            throw new coding_exception('contextid is required');
        }

        if (!$settings->get_raw()) {
            $context = context::instance_by_id($contextid);
            $options['context'] = $context;
            $options['filter'] = $settings->get_filter();
            $str = format_string($str, $striplinks, $options);
        }

        return $str;
    }
}

require_once($CFG->dirroot . '/mod/lti/locallib.php');

if (!function_exists('lti_get_launch_data')) {
    /**
     * Return the launch data required for opening the external tool.
     *
     * @param  stdClass $instance the external tool activity settings
     * @return array the endpoint URL and parameters (including the signature)
     * @since  Moodle 3.0
     */
    function lti_get_launch_data($instance) {
        global $PAGE, $CFG;
        if (empty($instance->typeid)) {
            $tool = lti_get_tool_by_url_match($instance->toolurl, $instance->course);
            if ($tool) {
                $typeid = $tool->id;
            } else {
                $typeid = null;
            }
        } else {
            $typeid = $instance->typeid;
        }
        if ($typeid) {
            $typeconfig = lti_get_type_config($typeid);
        } else {
            //There is no admin configuration for this tool. Use configuration in the lti instance record plus some defaults.
            $typeconfig = (array)$instance;
            $typeconfig['sendname'] = $instance->instructorchoicesendname;
            $typeconfig['sendemailaddr'] = $instance->instructorchoicesendemailaddr;
            $typeconfig['customparameters'] = $instance->instructorcustomparameters;
            $typeconfig['acceptgrades'] = $instance->instructorchoiceacceptgrades;
            $typeconfig['allowroster'] = $instance->instructorchoiceallowroster;
            $typeconfig['forcessl'] = '0';
        }
        //Default the organizationid if not specified
        if (empty($typeconfig['organizationid'])) {
            $urlparts = parse_url($CFG->wwwroot);
            $typeconfig['organizationid'] = $urlparts['host'];
        }
        if (!empty($instance->resourcekey)) {
            $key = $instance->resourcekey;
        } else if (!empty($typeconfig['resourcekey'])) {
            $key = $typeconfig['resourcekey'];
        } else {
            $key = '';
        }
        if (!empty($instance->password)) {
            $secret = $instance->password;
        } else if (!empty($typeconfig['password'])) {
            $secret = $typeconfig['password'];
        } else {
            $secret = '';
        }
        $endpoint = !empty($instance->toolurl) ? $instance->toolurl : $typeconfig['toolurl'];
        $endpoint = trim($endpoint);
        //If the current request is using SSL and a secure tool URL is specified, use it
        if (lti_request_is_using_ssl() && !empty($instance->securetoolurl)) {
            $endpoint = trim($instance->securetoolurl);
        }
        //If SSL is forced, use the secure tool url if specified. Otherwise, make sure https is on the normal launch URL.
        if ($typeconfig['forcessl'] == '1') {
            if (!empty($instance->securetoolurl)) {
                $endpoint = trim($instance->securetoolurl);
            }
            $endpoint = lti_ensure_url_is_https($endpoint);
        } else {
            if (!strstr($endpoint, '://')) {
                $endpoint = 'http://' . $endpoint;
            }
        }
        $orgid = $typeconfig['organizationid'];
        $course = $PAGE->course;
        $requestparams = lti_build_request($instance, $typeconfig, $course);
        $launchcontainer = lti_get_launch_container($instance, $typeconfig);
        $returnurlparams = array('course' => $course->id, 'launch_container' => $launchcontainer, 'instanceid' => $instance->id);
        if ( $orgid ) {
            $requestparams["tool_consumer_instance_guid"] = $orgid;
        }
        if (empty($key) || empty($secret)) {
            $returnurlparams['unsigned'] = '1';
        }
        // Add the return URL. We send the launch container along to help us avoid frames-within-frames when the user returns.
        $url = new moodle_url('/mod/lti/return.php', $returnurlparams);
        $returnurl = $url->out(false);
        if ($typeconfig['forcessl'] == '1') {
            $returnurl = lti_ensure_url_is_https($returnurl);
        }
        $requestparams['launch_presentation_return_url'] = $returnurl;
        if (!empty($key) && !empty($secret)) {
            $parms = lti_sign_parameters($requestparams, $endpoint, "POST", $key, $secret);
            $endpointurl = new moodle_url($endpoint);
            $endpointparams = $endpointurl->params();
            // Strip querystring params in endpoint url from $parms to avoid duplication.
            if (!empty($endpointparams) && !empty($parms)) {
                foreach (array_keys($endpointparams) as $paramname) {
                    if (isset($parms[$paramname])) {
                        unset($parms[$paramname]);
                    }
                }
            }
        } else {
            //If no key and secret, do the launch unsigned.
            $parms = $requestparams;
        }
        return array($endpoint, $parms);
    }
}

require_once($CFG->dirroot . '/mod/survey/lib.php');

if (!function_exists('survey_order_questions')) {

    /**
     * Helper function for ordering a set of questions by the given ids.
     *
     * @param  array $questions     array of questions objects
     * @param  array $questionorder array of questions ids indicating the correct order
     * @return array                list of questions ordered
     * @since Moodle 3.0
     */
    function survey_order_questions($questions, $questionorder) {

        $finalquestions = array();
        foreach ($questionorder as $qid) {
            $finalquestions[] = $questions[$qid];
        }
        return $finalquestions;
    }
}


if (!function_exists('survey_translate_question')) {

    /**
     * Translate the question texts and options.
     *
     * @param  stdClass $question question object
     * @return stdClass question object with all the text fields translated
     * @since Moodle 3.0
     */
    function survey_translate_question($question) {

        if ($question->text) {
            $question->text = get_string($question->text, "survey");
        }

        if ($question->shorttext) {
            $question->shorttext = get_string($question->shorttext, "survey");
        }

        if ($question->intro) {
            $question->intro = get_string($question->intro, "survey");
        }

        if ($question->options) {
            $question->options = get_string($question->options, "survey");
        }
        return $question;
    }
}

if (!function_exists('survey_get_questions')) {

    /**
     * Returns the questions for a survey (ordered).
     *
     * @param  stdClass $survey survey object
     * @return array list of questions ordered
     * @since Moodle 3.0
     * @throws  moodle_exception
     */
    function survey_get_questions($survey) {
        global $DB;

        $questionids = explode(',', $survey->questions);
        if (! $questions = $DB->get_records_list("survey_questions", "id", $questionids)) {
            throw new moodle_exception('cannotfindquestion', 'survey');
        }

        return survey_order_questions($questions, $questionids);
    }
}

if (!function_exists('survey_get_subquestions')) {

    /**
     * Returns subquestions for a given question (ordered).
     *
     * @param  stdClass $question questin object
     * @return array list of subquestions ordered
     * @since Moodle 3.0
     */
    function survey_get_subquestions($question) {
        global $DB;

        $questionids = explode(',', $question->multi);
        $questions = $DB->get_records_list("survey_questions", "id", $questionids);

        return survey_order_questions($questions, $questionids);
    }
}

if (!function_exists('survey_save_answers')) {

    /**
     * Save the answer for the given survey
     *
     * @param  stdClass $survey   a survey object
     * @param  array $answersrawdata the answers to be saved
     * @param  stdClass $course   a course object (required for trigger the submitted event)
     * @param  stdClass $context  a context object (required for trigger the submitted event)
     * @since Moodle 3.0
     */
    function survey_save_answers($survey, $answersrawdata, $course, $context) {
        global $DB, $USER;

        $answers = array();

        // Sort through the data and arrange it.
        // This is necessary because some of the questions may have two answers, eg Question 1 -> 1 and P1.
        foreach ($answersrawdata as $key => $val) {
            if ($key <> "userid" && $key <> "id") {
                if (substr($key, 0, 1) == "q") {
                    $key = clean_param(substr($key, 1), PARAM_ALPHANUM);   // Keep everything but the 'q', number or P number.
                }
                if (substr($key, 0, 1) == "P") {
                    $realkey = (int) substr($key, 1);
                    $answers[$realkey][1] = $val;
                } else {
                    $answers[$key][0] = $val;
                }
            }
        }

        // Now store the data.
        $timenow = time();
        $answerstoinsert = array();
        foreach ($answers as $key => $val) {
            if ($key != 'sesskey') {
                $newdata = new stdClass();
                $newdata->time = $timenow;
                $newdata->userid = $USER->id;
                $newdata->survey = $survey->id;
                $newdata->question = $key;
                if (!empty($val[0])) {
                    $newdata->answer1 = $val[0];
                } else {
                    $newdata->answer1 = "";
                }
                if (!empty($val[1])) {
                    $newdata->answer2 = $val[1];
                } else {
                    $newdata->answer2 = "";
                }

                $answerstoinsert[] = $newdata;
            }
        }

        if (!empty($answerstoinsert)) {
            foreach ($answerstoinsert as $answertoinsert) {
                $DB->insert_record("survey_answers", $answertoinsert);
            }
        }

    }
}
