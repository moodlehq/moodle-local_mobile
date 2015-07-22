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
     * Efficiently retrieves the $course (stdclass) and $cm (cm_info) objects, given
     * an instance id or record and module name.
     *
     * Usage:
     * list($course, $cm) = get_course_and_cm_from_instance($forum, 'forum');
     *
     * Using this method has a performance advantage because it works by loading
     * modinfo for the course - which will then be cached and it is needed later
     * in most requests. It also guarantees that the $cm object is a cm_info and
     * not a stdclass.
     *
     * The $course object can be supplied if already known and will speed
     * up this function - although it is more efficient to use this function to
     * get the course if you are starting from an instance id.
     *
     * By default this obtains information (for example, whether user can access
     * the activity) for current user, but you can specify a userid if required.
     *
     * @param stdclass|int $instanceorid Id of module instance, or database object
     * @param string $modulename Modulename (required)
     * @param stdClass|int $courseorid Optional course object if already loaded
     * @param int $userid Optional userid (default = current)
     * @return array Array with 2 elements $course and $cm
     * @throws moodle_exception If the item doesn't exist or is of wrong module name
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

        // Validate module name if supplied.
        if (!core_component::is_valid_plugin_name('mod', $modulename)) {
            throw new coding_exception('Invalid modulename parameter');
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

if (!function_exists('choice_get_my_choice_response')) {
    /**
     * Return my responses on a specific choice.
     * @param object $choice
     * @return array
     */
    function choice_get_my_choice_response($choice) {
        global $DB, $USER;
        return $DB->get_records('choice_answers', array('choiceid' => $choice->id, 'userid' => $USER->id));
    }
}

if (!function_exists('choice_can_see_results')) {
    /**
     * Return true if we are allowd to see choice results as student
     * @param object $choice Choice
     * @param rows|null $current my choice responses
     * @param bool|null $choiceopen choice open
     * @return bool True if we can see results, false if not.
     */
    function choice_can_see_results($choice, $current = null, $choiceopen = null) {

        if (is_null($choiceopen)) {
            $timenow = time();
            if ($choice->timeclose != 0 && $timenow > $choice->timeclose) {
                $choiceopen = false;
            } else {
                $choiceopen = true;
            }
        }
        if (is_null($current)) {
            $current = choice_get_my_choice_response($choice);
        }

        if ($choice->showresults == CHOICE_SHOWRESULTS_ALWAYS or
           ($choice->showresults == CHOICE_SHOWRESULTS_AFTER_ANSWER and !empty($current)) or
           ($choice->showresults == CHOICE_SHOWRESULTS_AFTER_CLOSE and !$choiceopen)) {
            return true;
        }
        return false;
    }
}
