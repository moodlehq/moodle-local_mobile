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

}