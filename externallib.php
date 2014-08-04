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
 * External functions backported.
 *
 * @package    local_mobile
 * @copyright  2014 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/externallib.php");

class local_mobile_external extends external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.7
     */
    public static function core_grades_get_grades_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'id of course'),
                'component' => new external_value(
                    PARAM_COMPONENT, 'A component, for example mod_forum or mod_quiz', VALUE_DEFAULT, ''),
                'activityid' => new external_value(PARAM_INT, 'The activity ID', VALUE_DEFAULT, null),
                'userids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'user ID'),
                    'An array of user IDs, leave empty to just retrieve grade item information', VALUE_DEFAULT, array()
                )
            )
        );
    }

    /**
     * Retrieve grade items and, optionally, student grades
     *
     * @param  int $courseid        Course id
     * @param  string $component    Component name
     * @param  int $activityid      Activity id
     * @param  array  $userids      Array of user ids
     * @return array                Array of grades
     * @since Moodle 2.7
     */
    public static function core_grades_get_grades($courseid, $component = null, $activityid = null, $userids = array()) {
        global $CFG, $USER, $DB;
        require_once($CFG->libdir  . "/gradelib.php");
        require_once($CFG->dirroot . "/local/mobile/locallib.php");

        $params = self::validate_parameters(self::core_grades_get_grades_parameters(),
            array('courseid' => $courseid, 'component' => $component, 'activityid' => $activityid, 'userids' => $userids));

        $coursecontext = context_course::instance($params['courseid']);

        try {
            self::validate_context($coursecontext);
        } catch (Exception $e) {
            $exceptionparam = new stdClass();
            $exceptionparam->message = $e->getMessage();
            $exceptionparam->courseid = $params['courseid'];
            throw new moodle_exception('errorcoursecontextnotvalid' , 'webservice', '', $exceptionparam);
        }

        $course = $DB->get_record('course', array('id' => $params['courseid']), '*', MUST_EXIST);

        $access = false;
        if (has_capability('moodle/grade:viewall', $coursecontext)) {
            // Can view all user's grades in this course.
            $access = true;

        } else if ($course->showgrades && count($params['userids']) == 1) {
            // Course showgrades == students/parents can access grades.

            if ($params['userids'][0] == $USER->id and has_capability('moodle/grade:view', $coursecontext)) {
                // Student can view their own grades in this course.
                $access = true;

            } else if (has_capability('moodle/grade:viewall', context_user::instance($params['userids'][0]))) {
                // User can view the grades of this user. Parent most probably.
                $access = true;
            }
        }

        if (!$access) {
            throw new moodle_exception('nopermissiontoviewgrades', 'error');
        }

        $itemtype = null;
        $itemmodule = null;
        if (!empty($params['component'])) {
            list($itemtype, $itemmodule) = normalize_component($params['component']);
        }

        $cm = null;
        if (!empty($itemmodule) && !empty($activityid)) {
            if (! $cm = get_coursemodule_from_id($itemmodule, $activityid)) {
                throw new moodle_exception('invalidcoursemodule');
            }
        }

        $cminstanceid = null;
        if (!empty($cm)) {
            $cminstanceid = $cm->instance;
        }

        $grades = local_mobile_grade_get_grades($params['courseid'], $itemtype, $itemmodule, $cminstanceid, $params['userids']);

        $acitivityinstances = null;
        if (empty($cm)) {
            // If we're dealing with multiple activites load all the module info.
            $modinfo = get_fast_modinfo($params['courseid']);
            $acitivityinstances = $modinfo->get_instances();
        }

        foreach ($grades->items as $gradeitem) {
            if (!empty($cm)) {
                // If they only requested one activity we will already have the cm.
                $modulecm = $cm;
            } else if (!empty($gradeitem->itemmodule)) {
                $modulecm = $acitivityinstances[$gradeitem->itemmodule][$gradeitem->iteminstance];
            } else {
                // Course grade item.
                continue;
            }

            // Make student feedback ready for output.
            foreach ($gradeitem->grades as $studentgrade) {
                if (!empty($studentgrade->feedback)) {
                    list($studentgrade->feedback, $categoryinfo->feedbackformat) =
                        external_format_text($studentgrade->feedback, $studentgrade->feedbackformat,
                        $modulecm->id, $params['component'], 'feedback', null);
                }
            }
        }

        // Convert from objects to arrays so all web service clients are supported.
        // While we're doing that we also remove grades the current user can't see due to hiding.
        $gradesarray = array();
        $canviewhidden = has_capability('moodle/grade:viewhidden', context_course::instance($params['courseid']));

        $gradesarray['items'] = array();

        foreach ($grades->items as $gradeitem) {
            // Switch the stdClass instance for a grade item instance so we can call is_hidden() and use the ID.
            $gradeiteminstance = self::core_grades_get_grade_item(
                $course->id, $gradeitem->itemtype, $gradeitem->itemmodule, $gradeitem->iteminstance, $gradeitem->itemmodule);

            if (!$canviewhidden && $gradeiteminstance->is_hidden()) {
                continue;
            }

            $gradeitemarray = (array)$gradeitem;
            $gradeitemarray['grades'] = array();

            if (!empty($gradeitem->grades)) {
                foreach ($gradeitem->grades as $studentid => $studentgrade) {
                    if (!$canviewhidden) {
                        // Need to load the grade_grade object to check visibility.
                        $gradegradeinstance = grade_grade::fetch(
                            array(
                                'userid' => $studentid,
                                'itemid' => $gradeiteminstance->id
                            )
                        );
                        // The grade grade may be legitimately missing if the student has no grade.
                        if (!empty($gradegradeinstance ) && $gradegradeinstance->is_hidden()) {
                            continue;
                        }
                    }

                    $gradeitemarray['grades'][$studentid] = (array)$studentgrade;
                    // Add the student ID as some WS clients can't access the array key.
                    $gradeitemarray['grades'][$studentid]['userid'] = $studentid;
                }
            }

            // If they requested grades for multiple activities load the cm object now.
            $modulecm = $cm;
            if (empty($modulecm) && !empty($gradeiteminstance->itemmodule)) {
                $modulecm = $acitivityinstances[$gradeiteminstance->itemmodule][$gradeiteminstance->iteminstance];
            }
            if ($gradeiteminstance->itemtype == 'course') {
                $gradesarray['items']['course'] = $gradeitemarray;
                $gradesarray['items']['course']['activityid'] = 'course';
            } else {
                $gradesarray['items'][$modulecm->id] = $gradeitemarray;
                // Add the activity ID as some WS clients can't access the array key.
                $gradesarray['items'][$modulecm->id]['activityid'] = $modulecm->id;
            }
        }

        $gradesarray['outcomes'] = array();
        foreach ($grades->outcomes as $outcome) {
            $modulecm = $cm;
            if (empty($modulecm)) {
                $modulecm = $acitivityinstances[$outcome->itemmodule][$outcome->iteminstance];
            }
            $gradesarray['outcomes'][$modulecm->id] = (array)$outcome;
            $gradesarray['outcomes'][$modulecm->id]['activityid'] = $modulecm->id;

            $gradesarray['outcomes'][$modulecm->id]['grades'] = array();
            if (!empty($outcome->grades)) {
                foreach ($outcome->grades as $studentid => $studentgrade) {
                    if (!$canviewhidden) {
                        // Need to load the grade_grade object to check visibility.
                        $gradeiteminstance = self::core_grades_get_grade_item(
                            $course->id, $outcome->itemtype, $outcome->itemmodule, $outcome->iteminstance, $outcome->itemnumber);
                        $gradegradeinstance = grade_grade::fetch(
                            array(
                                'userid' => $studentid,
                                'itemid' => $gradeiteminstance->id
                            )
                        );
                        // The grade grade may be legitimately missing if the student has no grade.
                        if (!empty($gradegradeinstance ) && $gradegradeinstance->is_hidden()) {
                            continue;
                        }
                    }
                    $gradesarray['outcomes'][$modulecm->id]['grades'][$studentid] = (array)$studentgrade;

                    // Add the student ID into the grade structure as some WS clients can't access the key.
                    $gradesarray['outcomes'][$modulecm->id]['grades'][$studentid]['userid'] = $studentid;
                }
            }
        }

        return $gradesarray;
    }

    /**
     * Get a grade item
     * @param  int $courseid        Course id
     * @param  string $itemtype     Item type
     * @param  string $itemmodule   Item module
     * @param  int $iteminstance    Item instance
     * @param  int $itemnumber      Item number
     * @return grade_item           A grade_item instance
     */
    private static function core_grades_get_grade_item($courseid, $itemtype, $itemmodule = null, $iteminstance = null, $itemnumber = null) {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        $gradeiteminstance = null;
        if ($itemtype == 'course') {
            $gradeiteminstance = grade_item::fetch(array('courseid' => $courseid, 'itemtype' => $itemtype));
        } else {
            $gradeiteminstance = grade_item::fetch(
                array('courseid' => $courseid, 'itemtype' => $itemtype,
                    'itemmodule' => $itemmodule, 'iteminstance' => $iteminstance, 'itemnumber' => $itemnumber));
        }
        return $gradeiteminstance;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 2.7
     */
    public static function core_grades_get_grades_returns() {
        return new external_single_structure(
            array(
                'items'  => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'activityid' => new external_value(
                                PARAM_ALPHANUM, 'The ID of the activity or "course" for the course grade item'),
                            'itemnumber'  => new external_value(PARAM_INT, 'Will be 0 unless the module has multiple grades'),
                            'scaleid' => new external_value(PARAM_INT, 'The ID of the custom scale or 0'),
                            'name' => new external_value(PARAM_RAW, 'The module name'),
                            'grademin' => new external_value(PARAM_FLOAT, 'Minimum grade'),
                            'grademax' => new external_value(PARAM_FLOAT, 'Maximum grade'),
                            'gradepass' => new external_value(PARAM_FLOAT, 'The passing grade threshold'),
                            'locked' => new external_value(PARAM_BOOL, 'Is the grade item locked?'),
                            'hidden' => new external_value(PARAM_BOOL, 'Is the grade item hidden?'),
                            'grades' => new external_multiple_structure(
                                new external_single_structure(
                                    array(
                                        'userid' => new external_value(
                                            PARAM_INT, 'Student ID'),
                                        'grade' => new external_value(
                                            PARAM_FLOAT, 'Student grade'),
                                        'locked' => new external_value(
                                            PARAM_INT, '0 means not locked, timestamp means locked until that date'),
                                        'hidden' => new external_value(
                                            PARAM_INT, '0 means not hidden, 1 hidden, timestamp hidden until that date'),
                                        'overridden' => new external_value(
                                            PARAM_INT, '0 means not overridden, timestamp means overriden until that date'),
                                        'feedback' => new external_value(
                                            PARAM_RAW, 'Feedback from the grader'),
                                        'feedbackformat' => new external_value(
                                            PARAM_INT, 'The format of the feedback'),
                                        'usermodified' => new external_value(
                                            PARAM_INT, 'The ID of the last user to modify this student grade'),
                                        'datesubmitted' => new external_value(
                                            PARAM_INT, 'A timestamp indicating when the student submitted the activity'),
                                        'dategraded' => new external_value(
                                            PARAM_INT, 'A timestamp indicating when the assignment was grades'),
                                        'str_grade' => new external_value(
                                            PARAM_RAW, 'A string representation of the grade'),
                                        'str_long_grade' => new external_value(
                                            PARAM_RAW, 'A nicely formatted string representation of the grade'),
                                        'str_feedback' => new external_value(
                                            PARAM_RAW, 'A string representation of the feedback from the grader'),
                                    )
                                )
                            ),
                        )
                    )
                ),
                'outcomes'  => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'activityid' => new external_value(
                                PARAM_ALPHANUM, 'The ID of the activity or "course" for the course grade item'),
                            'itemnumber'  => new external_value(PARAM_INT, 'Will be 0 unless the module has multiple grades'),
                            'scaleid' => new external_value(PARAM_INT, 'The ID of the custom scale or 0'),
                            'name' => new external_value(PARAM_RAW, 'The module name'),
                            'locked' => new external_value(PARAM_BOOL, 'Is the grade item locked?'),
                            'hidden' => new external_value(PARAM_BOOL, 'Is the grade item hidden?'),
                            'grades' => new external_multiple_structure(
                                new external_single_structure(
                                    array(
                                        'userid' => new external_value(
                                            PARAM_INT, 'Student ID'),
                                        'grade' => new external_value(
                                            PARAM_FLOAT, 'Student grade'),
                                        'locked' => new external_value(
                                            PARAM_BOOL, 'Is the student\'s grade locked?'),
                                        'hidden' => new external_value(
                                            PARAM_BOOL, 'Is the student\'s grade hidden?'),
                                        'feedback' => new external_value(
                                            PARAM_RAW, 'Feedback from the grader'),
                                        'feedbackformat' => new external_value(
                                            PARAM_INT, 'The feedback format'),
                                        'usermodified' => new external_value(
                                            PARAM_INT, 'The ID of the last user to modify this student grade'),
                                        'str_grade' => new external_value(
                                            PARAM_RAW, 'A string representation of the grade'),
                                        'str_feedback' => new external_value(
                                            PARAM_TEXT, 'A string representation of the feedback from the grader'),
                                    )
                                )
                            ),
                        )
                    ), 'An array of outcomes associated with the grade items', VALUE_OPTIONAL
                )
            )
        );

    }


    /**
     * Get messages parameters description.
     *
     * @return external_function_parameters
     */
    public static function core_message_get_messages_parameters() {
        return new external_function_parameters(
            array(
                'useridto' => new external_value(PARAM_INT, 'the user id who received the message, 0 for any user', VALUE_REQUIRED),
                'useridfrom' => new external_value(PARAM_INT,
                            'the user id who send the message, 0 for any user. -10 or -20 for no-reply or support user',
                            VALUE_DEFAULT, 0),
                'type' => new external_value(PARAM_ALPHA,
                            'type of message to return, expected values are: notifications, conversations and both',
                            VALUE_DEFAULT, 'both'),
                'read' => new external_value(PARAM_BOOL, 'true for getting read messages, false for unread', VALUE_DEFAULT, true),
                'newestfirst' => new external_value(PARAM_BOOL,
                            'true for ordering by newest first, false for oldest first', VALUE_DEFAULT, true),
                'limitfrom' => new external_value(PARAM_INT, 'limit from', VALUE_DEFAULT, 0),
                'limitnum' => new external_value(PARAM_INT, 'limit number', VALUE_DEFAULT, 0)            )
        );
    }

    /**
     * Get messages function implementation.
     * @param  int      $useridto       the user id who received the message
     * @param  int      $useridfrom     the user id who send the message. -10 or -20 for no-reply or support user
     * @param  string   $type           type of message tu return, expected values: notifications, conversations and both
     * @param  bool     $read           true for retreiving read messages, false for unread
     * @param  bool     $newestfirst    true for ordering by newest first, false for oldest first
     * @param  int      $limitfrom      limit from
     * @param  int      $limitnum       limit num
     * @return external_description
     */
    public static function core_message_get_messages($useridto, $useridfrom = 0, $type = 'both' , $read = true,
                                        $newestfirst = true, $limitfrom = 0, $limitnum = 0) {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . "/message/lib.php");

        $warnings = array();

        $params = array(
            'useridto' => $useridto,
            'useridfrom' => $useridfrom,
            'type' => $type,
            'read' => $read,
            'newestfirst' => $newestfirst,
            'limitfrom' => $limitfrom,
            'limitnum' => $limitnum
        );

        $params = self::validate_parameters(self::core_message_get_messages_parameters(), $params);

        $context = context_system::instance();
        self::validate_context($context);

        $useridto = $params['useridto'];
        $useridfrom = $params['useridfrom'];
        $type = $params['type'];
        $read = $params['read'];
        $newestfirst = $params['newestfirst'];
        $limitfrom = $params['limitfrom'];
        $limitnum = $params['limitnum'];

        $allowedvalues = array('notifications', 'conversations', 'both');
        if (!in_array($type, $allowedvalues)) {
            throw new invalid_parameter_exception('Invalid value for type parameter (value: ' . $type . '),' .
                'allowed values are: ' . implode(',', $allowedvalues));
        }

        // Check if private messaging between users is allowed.
        if (empty($CFG->messaging)) {
            // If we are retreiving only conversations, and messaging is disabled, throw an exception.
            if ($type == "conversations") {
                throw new moodle_exception('disabled', 'message');
            }
            if ($type == "both") {
                $warning = array();
                $warning['item'] = 'message';
                $warning['itemid'] = $USER->id;
                $warning['warningcode'] = '1';
                $warning['message'] = 'Private messages (conversations) are not enabled in this site.
                    Only notifications will be returned';
                $warnings[] = $warning;
            }
        }

        if (!empty($useridto)) {
            if (core_user::is_real_user($useridto)) {
                $userto = core_user::get_user($useridto, '*', MUST_EXIST);
            } else {
                throw new moodle_exception('invaliduser');
            }
        }

        if (!empty($useridfrom)) {
            // We use get_user here because the from user can be the noreply or support user.
            $userfrom = core_user::get_user($useridfrom, '*', MUST_EXIST);
        }

        // Check if the current user is the sender/receiver or just a privileged user.
        if ($useridto != $USER->id and $useridfrom != $USER->id and
             !has_capability('moodle/site:readallmessages', $context)) {
            throw new moodle_exception('accessdenied', 'admin');
        }

        // Get messages.
        $messagetable = $read ? '{message_read}' : '{message}';
        $usersql = "";
        $joinsql = "";
        $params = array('deleted' => 0);

        // Empty useridto means that we are going to retrieve messages send by the useridfrom to any user.
        if (empty($useridto)) {
            $userfields = get_all_user_name_fields(true, 'u', '', 'userto');
            $joinsql = "JOIN {user} u ON u.id = mr.useridto";
            $usersql = "mr.useridfrom = :useridfrom AND u.deleted = :deleted";
            $params['useridfrom'] = $useridfrom;
        } else {
            $userfields = get_all_user_name_fields(true, 'u', '', 'userfrom');
            // Left join because useridfrom may be -10 or -20 (no-reply and support users).
            $joinsql = "LEFT JOIN {user} u ON u.id = mr.useridfrom";
            $usersql = "mr.useridto = :useridto AND (u.deleted IS NULL OR u.deleted = :deleted)";
            $params['useridto'] = $useridto;
            if (!empty($useridfrom)) {
                $usersql .= " AND mr.useridfrom = :useridfrom";
                $params['useridfrom'] = $useridfrom;
            }
        }

        // Now, if retrieve notifications, conversations or both.
        $typesql = "";
        if ($type != 'both') {
            $typesql = "AND mr.notification = :notification";
            $params['notification'] = ($type == 'notifications') ? 1 : 0;
        }

        // Finally the sort direction.
        $orderdirection = $newestfirst ? 'DESC' : 'ASC';

        $sql = "SELECT mr.*, $userfields
                  FROM $messagetable mr
                     $joinsql
                 WHERE  $usersql
                        $typesql
                 ORDER BY mr.timecreated $orderdirection";

        if ($messages = $DB->get_records_sql($sql, $params, $limitfrom, $limitnum)) {
            $canviewfullname = has_capability('moodle/site:viewfullnames', $context);

            // In some cases, we don't need to get the to/from user objects from the sql query.
            $userfromfullname = '';
            $usertofullname = '';

            // In this case, the useridto field is not empty, so we can get the user destinatary fullname from there.
            if (!empty($useridto)) {
                $usertofullname = fullname($userto, $canviewfullname);
                // The user from may or may not be filled.
                if (!empty($useridfrom)) {
                    $userfromfullname = fullname($userfrom, $canviewfullname);
                }
            } else {
                // If the useridto field is empty, the useridfrom must be filled.
                $userfromfullname = fullname($userfrom, $canviewfullname);
            }
            foreach ($messages as $mid => $message) {

                // We need to get the user from the query.
                if (empty($userfromfullname)) {
                    // Check for non-reply and support users.
                    if (core_user::is_real_user($message->useridfrom)) {
                        $user = new stdclass();
                        $user = username_load_fields_from_object($user, $message, 'userfrom');
                        $message->userfromfullname = fullname($user, $canviewfullname);
                    } else {
                        $user = core_user::get_user($message->useridfrom);
                        $message->userfromfullname = fullname($user, $canviewfullname);
                    }
                } else {
                    $message->userfromfullname = $userfromfullname;
                }

                // We need to get the user from the query.
                if (empty($usertofullname)) {
                    $user = new stdclass();
                    $user = username_load_fields_from_object($user, $message, 'userto');
                    $message->usertofullname = fullname($user, $canviewfullname);
                } else {
                    $message->usertofullname = $usertofullname;
                }

                if (!isset($message->timeread)) {
                    $message->timeread = 0;
                }

                $message->text = message_format_message_text($message);
                $messages[$mid] = (array) $message;
            }
        }

        $results = array(
            'messages' => $messages,
            'warnings' => $warnings
        );

        return $results;
    }

    /**
     * Get messages return description.
     *
     * @return external_single_structure
     */
    public static function core_message_get_messages_returns() {
        return new external_single_structure(
            array(
                'messages' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'mMssage id'),
                            'useridfrom' => new external_value(PARAM_INT, 'User from id'),
                            'useridto' => new external_value(PARAM_INT, 'User to id'),
                            'subject' => new external_value(PARAM_TEXT, 'The message subject'),
                            'text' => new external_value(PARAM_RAW, 'The message text formated'),
                            'fullmessage' => new external_value(PARAM_RAW, 'The message'),
                            'fullmessageformat' => new external_value(PARAM_INT, 'The message message format'),
                            'fullmessagehtml' => new external_value(PARAM_RAW, 'The message in html'),
                            'smallmessage' => new external_value(PARAM_RAW, 'The shorten message'),
                            'notification' => new external_value(PARAM_INT, 'Is a notification?'),
                            'contexturl' => new external_value(PARAM_RAW, 'Context URL'),
                            'contexturlname' => new external_value(PARAM_TEXT, 'Context URL link name'),
                            'timecreated' => new external_value(PARAM_INT, 'Time created'),
                            'timeread' => new external_value(PARAM_INT, 'Time read'),
                            'usertofullname' => new external_value(PARAM_TEXT, 'User to full name'),
                            'userfromfullname' => new external_value(PARAM_TEXT, 'User from full name')
                        ), 'message'
                    )
                ),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of get_files parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.2
     */
    public static function core_files_get_files_parameters() {
        return new external_function_parameters(
            array(
                'contextid'    => new external_value(PARAM_INT, 'context id Set to -1 to use contextlevel and instanceid.'),
                'component'    => new external_value(PARAM_TEXT, 'component'),
                'filearea'     => new external_value(PARAM_TEXT, 'file area'),
                'itemid'       => new external_value(PARAM_INT, 'associated id'),
                'filepath'     => new external_value(PARAM_PATH, 'file path'),
                'filename'     => new external_value(PARAM_TEXT, 'file name'),
                'modified'     => new external_value(PARAM_INT, 'timestamp to return files changed after this time.', VALUE_DEFAULT, null),
                'contextlevel' => new external_value(PARAM_ALPHA, 'The context level for the file location.', VALUE_DEFAULT, null),
                'instanceid'   => new external_value(PARAM_INT, 'The instance id for where the file is located.', VALUE_DEFAULT, null)

            )
        );
    }

    /**
     * Return moodle files listing
     *
     * @param int $contextid context id
     * @param int $component component
     * @param int $filearea file area
     * @param int $itemid item id
     * @param string $filepath file path
     * @param string $filename file name
     * @param int $modified timestamp to return files changed after this time.
     * @param string $contextlevel The context level for the file location.
     * @param int $instanceid The instance id for where the file is located.
     * @return array
     * @since Moodle 2.2
     */
    public static function core_files_get_files($contextid, $component, $filearea, $itemid, $filepath, $filename, $modified = null,
                                     $contextlevel = null, $instanceid = null) {
        global $CFG;
        require_once($CFG->dirroot . "/local/mobile/locallib.php");

        $parameters = array(
            'contextid'    => $contextid,
            'component'    => $component,
            'filearea'     => $filearea,
            'itemid'       => $itemid,
            'filepath'     => $filepath,
            'filename'     => $filename,
            'modified'     => $modified,
            'contextlevel' => $contextlevel,
            'instanceid'   => $instanceid);
        $fileinfo = self::validate_parameters(self::core_files_get_files_parameters(), $parameters);

        $browser = get_file_browser();

        // We need to preserve backwards compatibility. Zero will use the system context and minus one will
        // use the addtional parameters to determine the context.
        // TODO MDL-40489 get_context_from_params should handle this logic.
        if ($fileinfo['contextid'] == 0) {
            $context = context_system::instance();
        } else {
            if ($fileinfo['contextid'] == -1) {
                unset($fileinfo['contextid']);
            }
            $context = local_mobile_get_context_from_params($fileinfo);
        }
        self::validate_context($context);

        if (empty($fileinfo['component'])) {
            $fileinfo['component'] = null;
        }
        if (empty($fileinfo['filearea'])) {
            $fileinfo['filearea'] = null;
        }
        if (empty($fileinfo['filename'])) {
            $fileinfo['filename'] = null;
        }
        if (empty($fileinfo['filepath'])) {
            $fileinfo['filepath'] = null;
        }

        $return = array();
        $return['parents'] = array();
        $return['files'] = array();
        $list = array();

        if ($file = $browser->get_file_info(
            $context, $fileinfo['component'], $fileinfo['filearea'], $fileinfo['itemid'],
                $fileinfo['filepath'], $fileinfo['filename'])) {
            $level = $file->get_parent();
            while ($level) {
                $params = $level->get_params();
                $params['filename'] = $level->get_visible_name();
                array_unshift($return['parents'], $params);
                $level = $level->get_parent();
            }
            $children = $file->get_children();
            foreach ($children as $child) {

                $params = $child->get_params();
                $timemodified = $child->get_timemodified();

                if ($child->is_directory()) {
                    if ((is_null($modified)) or ($modified < $timemodified)) {
                        $node = array(
                            'contextid' => $params['contextid'],
                            'component' => $params['component'],
                            'filearea'  => $params['filearea'],
                            'itemid'    => $params['itemid'],
                            'filepath'  => $params['filepath'],
                            'filename'  => $child->get_visible_name(),
                            'url'       => null,
                            'isdir'     => true,
                            'timemodified' => $timemodified
                           );
                           $list[] = $node;
                    }
                } else {
                    if ((is_null($modified)) or ($modified < $timemodified)) {
                        $node = array(
                            'contextid' => $params['contextid'],
                            'component' => $params['component'],
                            'filearea'  => $params['filearea'],
                            'itemid'    => $params['itemid'],
                            'filepath'  => $params['filepath'],
                            'filename'  => $child->get_visible_name(),
                            'url'       => $child->get_url(),
                            'isdir'     => false,
                            'timemodified' => $timemodified
                        );
                           $list[] = $node;
                    }
                }
            }
        }
        $return['files'] = $list;
        return $return;
    }

    /**
     * Returns description of get_files returns
     *
     * @return external_single_structure
     * @since Moodle 2.2
     */
    public static function core_files_get_files_returns() {
        return new external_single_structure(
            array(
                'parents' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'contextid' => new external_value(PARAM_INT, ''),
                            'component' => new external_value(PARAM_COMPONENT, ''),
                            'filearea'  => new external_value(PARAM_AREA, ''),
                            'itemid'    => new external_value(PARAM_INT, ''),
                            'filepath'  => new external_value(PARAM_TEXT, ''),
                            'filename'  => new external_value(PARAM_TEXT, ''),
                        )
                    )
                ),
                'files' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'contextid' => new external_value(PARAM_INT, ''),
                            'component' => new external_value(PARAM_COMPONENT, ''),
                            'filearea'  => new external_value(PARAM_AREA, ''),
                            'itemid'   => new external_value(PARAM_INT, ''),
                            'filepath' => new external_value(PARAM_TEXT, ''),
                            'filename' => new external_value(PARAM_TEXT, ''),
                            'isdir'    => new external_value(PARAM_BOOL, ''),
                            'url'      => new external_value(PARAM_TEXT, ''),
                            'timemodified' => new external_value(PARAM_INT, ''),
                        )
                    )
                )
            )
        );
    }

}