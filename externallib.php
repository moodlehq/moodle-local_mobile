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

class local_mobile_external extends external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function mod_resource_view_resource_parameters() {
        return new external_function_parameters(
            array(
                'resourceid' => new external_value(PARAM_INT, 'resource instance id')
            )
        );
    }

    /**
     * Simulate the resource/view.php web interface page: trigger events, completion, etc...
     *
     * @param int $resourceid the resource instance id
     * @return array of warnings and status result
     * @since Moodle 3.0
     * @throws moodle_exception
     */
    public static function mod_resource_view_resource($resourceid) {
        global $DB, $CFG;
        require_once($CFG->dirroot . "/mod/resource/lib.php");

        $params = self::validate_parameters(self::mod_resource_view_resource_parameters(),
                                            array(
                                                'resourceid' => $resourceid
                                            ));
        $warnings = array();

        // Request and permission validation.
        $resource = $DB->get_record('resource', array('id' => $params['resourceid']), 'id', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($resource, 'resource');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/resource:view', $context);

        // Completion.
        $completion = new completion_info($course);
        $completion->set_module_viewed($cm);

        // Trigger course_module_viewed event.
        $params = array(
            'context' => $context,
            'objectid' => $resource->id
        );

        $event = \mod_resource\event\course_module_viewed::create($params);
        $event->add_record_snapshot('course_modules', $cm);
        $event->add_record_snapshot('course', $course);
        $event->add_record_snapshot('resource', $resource);
        $event->trigger();

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function mod_resource_view_resource_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function mod_url_view_url_parameters() {
        return new external_function_parameters(
            array(
                'urlid' => new external_value(PARAM_INT, 'url instance id')
            )
        );
    }

    /**
     * Simulate the url/view.php web interface page: trigger events, completion, etc...
     *
     * @param int $urlid the url instance id
     * @return array of warnings and status result
     * @since Moodle 3.0
     * @throws moodle_exception
     */
    public static function mod_url_view_url($urlid) {
        global $DB, $CFG;
        require_once($CFG->dirroot . "/mod/url/lib.php");

        $params = self::validate_parameters(self::mod_url_view_url_parameters(),
                                            array(
                                                'urlid' => $urlid
                                            ));
        $warnings = array();

        // Request and permission validation.
        $url = $DB->get_record('url', array('id' => $params['urlid']), 'id', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($url, 'url');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/url:view', $context);

        // Trigger course_module_viewed event.
        $params = array(
            'context' => $context,
            'objectid' => $url->id
        );

        $event = \mod_url\event\course_module_viewed::create($params);
        $event->add_record_snapshot('course_modules', $cm);
        $event->add_record_snapshot('course', $course);
        $event->add_record_snapshot('url', $url);
        $event->trigger();

        // Completion.
        $completion = new completion_info($course);
        $completion->set_module_viewed($cm);

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function mod_url_view_url_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function mod_page_view_page_parameters() {
        return new external_function_parameters(
            array(
                'pageid' => new external_value(PARAM_INT, 'page instance id')
            )
        );
    }

    /**
     * Simulate the page/view.php web interface page: trigger events, completion, etc...
     *
     * @param int $pageid the page instance id
     * @return array of warnings and status result
     * @since Moodle 3.0
     * @throws moodle_exception
     */
    public static function mod_page_view_page($pageid) {
        global $DB, $CFG;
        require_once($CFG->dirroot . "/mod/page/lib.php");

        $params = self::validate_parameters(self::mod_page_view_page_parameters(),
                                            array(
                                                'pageid' => $pageid
                                            ));
        $warnings = array();

        // Request and permission validation.
        $page = $DB->get_record('page', array('id' => $params['pageid']), 'id', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($page, 'page');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/page:view', $context);

        // Trigger course_module_viewed event.
        $params = array(
            'context' => $context,
            'objectid' => $page->id
        );

        $event = \mod_page\event\course_module_viewed::create($params);
        $event->add_record_snapshot('course_modules', $cm);
        $event->add_record_snapshot('course', $course);
        $event->add_record_snapshot('page', $page);
        $event->trigger();

        // Completion.
        $completion = new completion_info($course);
        $completion->set_module_viewed($cm);

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function mod_page_view_page_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function mod_assign_view_grading_table_parameters() {
        return new external_function_parameters(
            array(
                'assignid' => new external_value(PARAM_INT, 'assign instance id')
            )
        );
    }

    /**
     * Simulate the web interface grading table view.
     *
     * @param int $assignid the assign instance id
     * @return array of warnings and status result
     * @since Moodle 3.0
     * @throws moodle_exception
     */
    public static function mod_assign_view_grading_table($assignid) {
        global $DB, $CFG;
        require_once($CFG->dirroot . "/mod/assign/locallib.php");

        $params = self::validate_parameters(self::mod_assign_view_grading_table_parameters(),
                                            array(
                                                'assignid' => $assignid
                                            ));
        $warnings = array();

        // Request and permission validation.
        $assign = $DB->get_record('assign', array('id' => $params['assignid']), 'id', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($assign, 'assign');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/assign:view', $context);

        $assign = new assign($context, null, null);
        $assign->require_view_grades();
        \mod_assign\event\grading_table_viewed::create_from_assign($assign)->trigger();

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function mod_assign_view_grading_table_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function mod_folder_view_folder_parameters() {
        return new external_function_parameters(
            array(
                'folderid' => new external_value(PARAM_INT, 'folder instance id')
            )
        );
    }

    /**
     * Simulate the folder/view.php web interface page: trigger events, completion, etc...
     *
     * @param int $folderid the folder instance id
     * @return array of warnings and status result
     * @since Moodle 3.0
     * @throws moodle_exception
     */
    public static function mod_folder_view_folder($folderid) {
        global $DB, $CFG;
        require_once($CFG->dirroot . "/mod/folder/lib.php");

        $params = self::validate_parameters(self::mod_folder_view_folder_parameters(),
                                            array(
                                                'folderid' => $folderid
                                            ));
        $warnings = array();

        // Request and permission validation.
        $folder = $DB->get_record('folder', array('id' => $params['folderid']), 'id', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($folder, 'folder');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/folder:view', $context);

        // Trigger course_module_viewed event.
        $params = array(
            'context' => $context,
            'objectid' => $folder->id
        );

        $event = \mod_folder\event\course_module_viewed::create($params);
        $event->add_record_snapshot('course_modules', $cm);
        $event->add_record_snapshot('course', $course);
        $event->add_record_snapshot('folder', $folder);
        $event->trigger();

        // Completion.
        $completion = new completion_info($course);
        $completion->set_module_viewed($cm);

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function mod_folder_view_folder_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function mod_book_view_book_parameters() {
        return new external_function_parameters(
            array(
                'bookid' => new external_value(PARAM_INT, 'book instance id'),
                'chapterid' => new external_value(PARAM_INT, 'chapter id', VALUE_DEFAULT, 0)
            )
        );
    }

    /**
     * Simulate the book/view.php web interface page: trigger events, completion, etc...
     *
     * @param int $bookid the book instance id
     * @param int $chapterid the book chapter id
     * @return array of warnings and status result
     * @since Moodle 3.0
     * @throws moodle_exception
     */
    public static function mod_book_view_book($bookid, $chapterid = 0) {
        global $DB, $CFG;
        require_once($CFG->dirroot . "/mod/book/lib.php");
        require_once($CFG->dirroot . "/mod/book/locallib.php");

        $params = self::validate_parameters(self::mod_book_view_book_parameters(),
                                            array(
                                                'bookid' => $bookid,
                                                'chapterid' => $chapterid
                                            ));
        $bookid = $params['bookid'];
        $chapterid = $params['chapterid'];

        $warnings = array();

        // Request and permission validation.
        $book = $DB->get_record('book', array('id' => $bookid), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($book, 'book');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/book:read', $context);

        $chapters = book_preload_chapters($book);
        $firstchapterid = 0;
        $lastchapterid = 0;

        foreach ($chapters as $ch) {
            if ($ch->hidden) {
                continue;
            }
            if (!$firstchapterid) {
                $firstchapterid = $ch->id;
            }
            $lastchapterid = $ch->id;
        }

        if (!$chapterid) {
            // Trigger the module viewed events since we are displaying the book.
            \mod_book\event\course_module_viewed::create_from_book($book, $context)->trigger();
            $chapterid = $firstchapterid;
        }

        // Check if book is empty (warning).
        if (!$chapterid) {
            $warnings[] = array(
                'item' => 'book',
                'itemid' => $book->id,
                'warningcode' => '1',
                'message' => get_string('nocontent', 'mod_book')
            );
        } else {
            $chapter = $DB->get_record('book_chapters', array('id' => $chapterid, 'bookid' => $book->id));
            if (!$chapter) {
                throw new moodle_exception('errorchapter', 'mod_book');
            }

            // Trigger the chapter viewed event.
            $islastchapter = ($chapter->id == $lastchapterid) ? true : false;

            \mod_book\event\chapter_viewed::create_from_chapter($book, $context, $chapter)->trigger();

            if ($islastchapter) {
                // We cheat a bit here in assuming that viewing the last page means the user viewed the whole book.
                $completion = new completion_info($course);
                $completion->set_module_viewed($cm);
            }
        }

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function mod_book_view_book_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function mod_imscp_view_imscp_parameters() {
        return new external_function_parameters(
            array(
                'imscpid' => new external_value(PARAM_INT, 'imscp instance id')
            )
        );
    }

    /**
     * Simulate the imscp/view.php web interface page: trigger events, completion, etc...
     *
     * @param int $imscpid the imscp instance id
     * @return array of warnings and status result
     * @since Moodle 3.0
     * @throws moodle_exception
     */
    public static function mod_imscp_view_imscp($imscpid) {
        global $DB, $CFG;
        require_once($CFG->dirroot . "/mod/imscp/lib.php");

        $params = self::validate_parameters(self::mod_imscp_view_imscp_parameters(),
                                            array(
                                                'imscpid' => $imscpid
                                            ));
        $warnings = array();

        // Request and permission validation.
        $imscp = $DB->get_record('imscp', array('id' => $params['imscpid']), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($imscp, 'imscp');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/imscp:view', $context);

        // Trigger course_module_viewed event.
        $params = array(
            'context' => $context,
            'objectid' => $imscp->id
        );

        $event = \mod_imscp\event\course_module_viewed::create($params);
        $event->add_record_snapshot('course_modules', $cm);
        $event->add_record_snapshot('course', $course);
        $event->add_record_snapshot('imscp', $imscp);
        $event->trigger();

        // Completion.
        $completion = new completion_info($course);
        $completion->set_module_viewed($cm);

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function mod_imscp_view_imscp_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function mod_chat_login_user_parameters() {
        return new external_function_parameters(
            array(
                'chatid' => new external_value(PARAM_INT, 'chat instance id'),
                'groupid' => new external_value(PARAM_INT, 'group id, 0 means that the function will determine the user group',
                                                VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Log the current user into a chat room in the given chat.
     *
     * @param int $chatid the chat instance id
     * @param int $groupid the user group id
     * @return array of warnings and the chat unique session id
     * @since Moodle 3.0
     * @throws moodle_exception
     */
    public static function mod_chat_login_user($chatid, $groupid = 0) {
        global $DB;

        $params = self::validate_parameters(self::mod_chat_login_user_parameters(),
                                            array(
                                                'chatid' => $chatid,
                                                'groupid' => $groupid
                                            ));
        $warnings = array();

        // Request and permission validation.
        $chat = $DB->get_record('chat', array('id' => $params['chatid']), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($chat, 'chat');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/chat:chat', $context);

        if (!empty($params['groupid'])) {
            $groupid = $params['groupid'];
            // Determine is the group is visible to user.
            if (!groups_group_visible($groupid, $course, $cm)) {
                throw new moodle_exception('notingroup');
            }
        } else {
            // Check to see if groups are being used here.
            if ($groupmode = groups_get_activity_groupmode($cm)) {
                $groupid = groups_get_activity_group($cm);
            } else {
                $groupid = 0;
            }
        }

        // Get the unique chat session id.
        // Since we are going to use the chat via Web Service requests we set the ajax version (since it's the most similar).
        if (!$chatsid = chat_login_user($chat->id, 'ajax', $groupid, $course)) {
            throw moodle_exception('cantlogin', 'chat');
        }

        $result = array();
        $result['chatsid'] = $chatsid;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function mod_chat_login_user_returns() {
        return new external_single_structure(
            array(
                'chatsid' => new external_value(PARAM_ALPHANUMEXT, 'unique chat session id'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function mod_chat_get_chat_users_parameters() {
        return new external_function_parameters(
            array(
                'chatsid' => new external_value(PARAM_ALPHANUMEXT, 'chat session id (obtained via mod_chat_login_user)')
            )
        );
    }

    /**
     * Get the list of users in the given chat session.
     *
     * @param int $chatsid the chat instance id
     * @return array of warnings and the user lists
     * @since Moodle 3.0
     * @throws moodle_exception
     */
    public static function mod_chat_get_chat_users($chatsid) {
        global $DB;

        $params = self::validate_parameters(self::mod_chat_get_chat_users_parameters(),
                                            array(
                                                'chatsid' => $chatsid
                                            ));
        $warnings = array();

        // Request and permission validation.
        if (!$chatuser = $DB->get_record('chat_users', array('sid' => $params['chatsid']))) {
            throw new moodle_exception('notlogged', 'chat');
        }
        $chat = $DB->get_record('chat', array('id' => $chatuser->chatid), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($chat, 'chat');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/chat:chat', $context);

        // First, delete old users from the chats.
        chat_delete_old_users();

        $users = chat_get_users($chatuser->chatid, $chatuser->groupid, $cm->groupingid);
        $returnedusers = array();

        foreach ($users as $user) {
            $usercontext = context_user::instance($user->id, IGNORE_MISSING);
            $profileimageurl = '';

            if ($usercontext) {
                $profileimageurl = moodle_url::make_webservice_pluginfile_url(
                                    $usercontext->id, 'user', 'icon', null, '/', 'f1')->out(false);
            }

            $returnedusers[] = array(
                'id' => $user->id,
                'fullname' => fullname($user),
                'profileimageurl' => $profileimageurl
            );
        }

        $result = array();
        $result['users'] = $returnedusers;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function mod_chat_get_chat_users_returns() {
        return new external_single_structure(
            array(
                'users' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'user id'),
                            'fullname' => new external_value(PARAM_NOTAGS, 'user full name'),
                            'profileimageurl' => new external_value(PARAM_URL, 'user picture URL'),
                        )
                    ),
                    'list of users'
                ),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function mod_chat_send_chat_message_parameters() {
        return new external_function_parameters(
            array(
                'chatsid' => new external_value(PARAM_ALPHANUMEXT, 'chat session id (obtained via mod_chat_login_user)'),
                'messagetext' => new external_value(PARAM_RAW, 'the message text'),
                'beepid' => new external_value(PARAM_RAW, 'the beep id', VALUE_DEFAULT, ''),

            )
        );
    }

    /**
     * Send a message on the given chat session.
     *
     * @param int $chatsid the chat instance id
     * @param string $messagetext the message text
     * @param string $beepid the beep message id
     * @return array of warnings and the new message id (0 if the message was empty)
     * @since Moodle 3.0
     * @throws moodle_exception
     */
    public static function mod_chat_send_chat_message($chatsid, $messagetext, $beepid = '') {
        global $DB;

        $params = self::validate_parameters(self::mod_chat_send_chat_message_parameters(),
                                            array(
                                                'chatsid' => $chatsid,
                                                'messagetext' => $messagetext,
                                                'beepid' => $beepid
                                            ));
        $warnings = array();

        // Request and permission validation.
        if (!$chatuser = $DB->get_record('chat_users', array('sid' => $params['chatsid']))) {
            throw new moodle_exception('notlogged', 'chat');
        }
        $chat = $DB->get_record('chat', array('id' => $chatuser->chatid), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($chat, 'chat');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/chat:chat', $context);

        $chatmessage = clean_text($params['messagetext'], FORMAT_MOODLE);

        if (!empty($params['beepid'])) {
            $chatmessage = 'beep ' . $params['beepid'];
        }

        if (!empty($chatmessage)) {
            // Send the message.
            $messageid = chat_send_chatmessage($chatuser, $chatmessage, 0, $cm);
            // Update ping time.
            $chatuser->lastmessageping = time() - 2;
            $DB->update_record('chat_users', $chatuser);
        } else {
            $messageid = 0;
        }

        $result = array();
        $result['messageid'] = $messageid;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function mod_chat_send_chat_message_returns() {
        return new external_single_structure(
            array(
                'messageid' => new external_value(PARAM_INT, 'message sent id'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function mod_chat_get_chat_latest_messages_parameters() {
        return new external_function_parameters(
            array(
                'chatsid' => new external_value(PARAM_ALPHANUMEXT, 'chat session id (obtained via mod_chat_login_user)'),
                'chatlasttime' => new external_value(PARAM_INT, 'last time messages were retrieved', VALUE_DEFAULT, 0)
            )
        );
    }

    /**
     * Get the latest messages from the given chat session.
     *
     * @param int $chatsid the chat instance id
     * @param int $chatlasttime last time messages were retrieved
     * @return array of warnings and the new message id (0 if the message was empty)
     * @since Moodle 3.0
     * @throws moodle_exception
     */
    public static function mod_chat_get_chat_latest_messages($chatsid, $chatlasttime = 0) {
        global $DB, $CFG;

        $params = self::validate_parameters(self::mod_chat_get_chat_latest_messages_parameters(),
                                            array(
                                                'chatsid' => $chatsid,
                                                'chatlasttime' => $chatlasttime
                                            ));
        $warnings = array();

        // Request and permission validation.
        if (!$chatuser = $DB->get_record('chat_users', array('sid' => $params['chatsid']))) {
            throw new moodle_exception('notlogged', 'chat');
        }
        $chat = $DB->get_record('chat', array('id' => $chatuser->chatid), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($chat, 'chat');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/chat:chat', $context);

        $chatlasttime = $params['chatlasttime'];
        if ((time() - $chatlasttime) > $CFG->chat_old_ping) {
            chat_delete_old_users();
        }

        // Set default chat last time (to not retrieve all the conversations).
        if ($chatlasttime == 0) {
            $chatlasttime = time() - $CFG->chat_old_ping;
        }

        if ($latestmessage = chat_get_latest_message($chatuser->chatid, $chatuser->groupid)) {
            $chatnewlasttime = $latestmessage->timestamp;
        } else {
            $chatnewlasttime = 0;
        }

        $messages = chat_get_latest_messages($chatuser, $chatlasttime);
        $returnedmessages = array();

        foreach ($messages as $message) {

            // FORMAT_MOODLE is mandatory in the chat plugin.
            list($messageformatted, $format) = external_format_text($message->message, FORMAT_MOODLE, $context->id, 'mod_chat',
                                                                    '', 0);

            $returnedmessages[] = array(
                'id' => $message->id,
                'userid' => $message->userid,
                'system' => (bool) $message->system,
                'message' => $messageformatted,
                'timestamp' => $message->timestamp,
            );
        }

        // Update our status since we are active in the chat.
        $DB->set_field('chat_users', 'lastping', time(), array('id' => $chatuser->id));

        $result = array();
        $result['messages'] = $returnedmessages;
        $result['chatnewlasttime'] = $chatnewlasttime;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function mod_chat_get_chat_latest_messages_returns() {
        return new external_single_structure(
            array(
                'messages' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'message id'),
                            'userid' => new external_value(PARAM_INT, 'user id'),
                            'system' => new external_value(PARAM_BOOL, 'true if is a system message (like user joined)'),
                            'message' => new external_value(PARAM_RAW, 'message text'),
                            'timestamp' => new external_value(PARAM_INT, 'timestamp for the message'),
                        )
                    ),
                    'list of users'
                ),
                'chatnewlasttime' => new external_value(PARAM_INT, 'new last time'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function mod_chat_view_chat_parameters() {
        return new external_function_parameters(
            array(
                'chatid' => new external_value(PARAM_INT, 'chat instance id')
            )
        );
    }

    /**
     * Simulate the chat/view.php web interface page: trigger events, completion, etc...
     *
     * @param int $chatid the chat instance id
     * @return array of warnings and status result
     * @since Moodle 3.0
     * @throws moodle_exception
     */
    public static function mod_chat_view_chat($chatid) {
        global $DB, $CFG;

        $params = self::validate_parameters(self::mod_chat_view_chat_parameters(),
                                            array(
                                                'chatid' => $chatid
                                            ));
        $warnings = array();

        // Request and permission validation.
        $chat = $DB->get_record('chat', array('id' => $params['chatid']), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($chat, 'chat');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/chat:chat', $context);

        // Trigger course_module_viewed event.
        $params = array(
            'context' => $context,
            'objectid' => $chat->id
        );

        $event = \mod_chat\event\course_module_viewed::create($params);
        $event->add_record_snapshot('course_modules', $cm);
        $event->add_record_snapshot('course', $course);
        $event->add_record_snapshot('chat', $chat);
        $event->trigger();

        // Completion.
        $completion = new completion_info($course);
        $completion->set_module_viewed($cm);

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function mod_chat_view_chat_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Describes the parameters for get_chats_by_courses.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.0
     */
    public static function mod_chat_get_chats_by_courses_parameters() {
        return new external_function_parameters (
            array(
                'courseids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'course id'),
                    'Array of course ids', VALUE_DEFAULT, array()
                ),
            )
        );
    }
    /**
     * Returns a list of chats in a provided list of courses,
     * if no list is provided all chats that the user can view will be returned.
     *
     * @param array $courseids the course ids
     * @return array of chats details
     * @since Moodle 3.0
     */
    public static function mod_chat_get_chats_by_courses($courseids = array()) {
        global $CFG;
        $params = self::validate_parameters(self::mod_chat_get_chats_by_courses_parameters(), array('courseids' => $courseids));
        $warnings = array();
        if (!empty($params['courseids'])) {
            $courses = array();
            $courseids = $params['courseids'];
        } else {
            $courses = enrol_get_my_courses();
            $courseids = array_keys($courses);
        }
        // Array to store the chats to return.
        $arrchats = array();
        // Ensure there are courseids to loop through.
        if (!empty($courseids)) {
            // Array of the courses we are going to retrieve the chats from.
            $arraycourses = array();
            // Go through the courseids.
            foreach ($courseids as $cid) {
                // Check the user can function in this context.
                try {
                    $context = context_course::instance($cid);
                    self::validate_context($context);
                    // Check if this course was already loaded (by enrol_get_my_courses).
                    if (!isset($courses[$cid])) {
                        $courses[$cid] = get_course($cid);
                    }
                    $arraycourses[$cid] = $courses[$cid];
                } catch (Exception $e) {
                    $warnings[] = array(
                        'item' => 'course',
                        'itemid' => $cid,
                        'warningcode' => '1',
                        'message' => 'No access rights in course context '.$e->getMessage()
                    );
                }
            }
            // Get the chats in this course, this function checks users visibility permissions.
            // We can avoid then additional validate_context calls.
            $chats = get_all_instances_in_courses("chat", $arraycourses);
            foreach ($chats as $chat) {
                $chatcontext = context_module::instance($chat->coursemodule);
                // Entry to return.
                $chatdetails = array();
                // First, we return information that any user can see in the web interface.
                $chatdetails['id'] = $chat->id;
                $chatdetails['coursemodule']      = $chat->coursemodule;
                $chatdetails['course']            = $chat->course;
                $chatdetails['name']              = $chat->name;
                // Format intro.
                list($chatdetails['intro'], $chatdetails['introformat']) =
                    external_format_text($chat->intro, $chat->introformat,
                                            $chatcontext->id, 'mod_chat', 'intro', null);

                if (has_capability('mod/chat:chat', $chatcontext)) {
                    $chatdetails['chatmethod']    = $CFG->chat_method;
                    $chatdetails['keepdays']      = $chat->keepdays;
                    $chatdetails['studentlogs']   = $chat->studentlogs;
                    $chatdetails['chattime']      = $chat->chattime;
                    $chatdetails['schedule']      = $chat->schedule;
                }

                if (has_capability('moodle/course:manageactivities', $chatcontext)) {
                    $chatdetails['timemodified']  = $chat->timemodified;
                    $chatdetails['section']       = $chat->section;
                    $chatdetails['visible']       = $chat->visible;
                    $chatdetails['groupmode']     = $chat->groupmode;
                    $chatdetails['groupingid']    = $chat->groupingid;
                }
                $arrchats[] = $chatdetails;
            }
        }
        $result = array();
        $result['chats'] = $arrchats;
        $result['warnings'] = $warnings;
        return $result;
    }
    /**
     * Describes the get_chats_by_courses return value.
     *
     * @return external_single_structure
     * @since Moodle 3.0
     */
    public static function mod_chat_get_chats_by_courses_returns() {
        return new external_single_structure(
            array(
                'chats' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Chat id'),
                            'coursemodule' => new external_value(PARAM_INT, 'Course module id'),
                            'course' => new external_value(PARAM_TEXT, 'Course id'),
                            'name' => new external_value(PARAM_TEXT, 'Chat name'),
                            'intro' => new external_value(PARAM_RAW, 'The Chat intro'),
                            'introformat' => new external_format_value('intro'),
                            'chatmethod' => new external_value(PARAM_ALPHA, 'chat method (sockets, daemon)', VALUE_OPTIONAL),
                            'keepdays' => new external_value(PARAM_INT, 'keep days', VALUE_OPTIONAL),
                            'studentlogs' => new external_value(PARAM_INT, 'student logs visible to everyone', VALUE_OPTIONAL),
                            'chattime' => new external_value(PARAM_RAW, 'chat time', VALUE_OPTIONAL),
                            'schedule' => new external_value(PARAM_INT, 'schedule type', VALUE_OPTIONAL),
                            'timemodified' => new external_value(PARAM_RAW, 'time of last modification', VALUE_OPTIONAL),
                            'section' => new external_value(PARAM_INT, 'course section id', VALUE_OPTIONAL),
                            'visible' => new external_value(PARAM_BOOL, 'visible', VALUE_OPTIONAL),
                            'groupmode' => new external_value(PARAM_INT, 'group mode', VALUE_OPTIONAL),
                            'groupingid' => new external_value(PARAM_INT, 'group id', VALUE_OPTIONAL),
                        ), 'Chats'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function mod_choice_view_choice_parameters() {
        return new external_function_parameters(
            array(
                'choiceid' => new external_value(PARAM_INT, 'choice instance id')
            )
        );
    }

    /**
     * Simulate the choice/view.php web interface page: trigger events, completion, etc...
     *
     * @param int $choiceid the choice instance id
     * @return array of warnings and status result
     * @since Moodle 3.0
     * @throws moodle_exception
     */
    public static function mod_choice_view_choice($choiceid) {
        global $CFG;

        $params = self::validate_parameters(self::mod_choice_view_choice_parameters(),
                                            array(
                                                'choiceid' => $choiceid
                                            ));
        $warnings = array();

        // Request and permission validation.
        if (!$choice = choice_get_choice($params['choiceid'])) {
            throw new moodle_exception("invalidcoursemodule", "error");
        }
        list($course, $cm) = get_course_and_cm_from_instance($choice, 'choice');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        // Trigger course_module_viewed event.
        $params = array(
            'context' => $context,
            'objectid' => $choice->id
        );

        $event = \mod_choice\event\course_module_viewed::create($params);
        $event->add_record_snapshot('course_modules', $cm);
        $event->add_record_snapshot('course', $course);
        $event->add_record_snapshot('choice', $choice);
        $event->trigger();

        // Completion.
        $completion = new completion_info($course);
        $completion->set_module_viewed($cm);

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function mod_choice_view_choice_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Describes the parameters for mod_choice_get_choices_by_courses.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.0
     */
    public static function mod_choice_get_choice_results_parameters() {
        return new external_function_parameters (array('choiceid' => new external_value(PARAM_INT, 'choice instance id')));
    }
    /**
     * Returns user's results for a specific choice
     * and a list of those users that did not answered yet.
     *
     * @param int $choiceid the choice instance id
     * @return array of responses details
     * @since Moodle 3.0
     */
    public static function mod_choice_get_choice_results($choiceid) {
        global $USER;

        $params = self::validate_parameters(self::mod_choice_get_choice_results_parameters(), array('choiceid' => $choiceid));

        if (!$choice = choice_get_choice($params['choiceid'])) {
            throw new moodle_exception("invalidcoursemodule", "error");
        }
        list($course, $cm) = get_course_and_cm_from_instance($choice, 'choice');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        $groupmode = groups_get_activity_groupmode($cm);
        // Check if we have to include responses from inactive users.
        $onlyactive = $choice->includeinactive ? false : true;
        $users = choice_get_response_data($choice, $cm, $groupmode, $onlyactive);
        // Show those who haven't answered the question.
        if (!empty($choice->showunanswered)) {
            $choice->option[0] = get_string('notanswered', 'choice');
            $choice->maxanswers[0] = 0;
        }
        $results = prepare_choice_show_results($choice, $course, $cm, $users);

        $options = array();
        foreach ($results->options as $optionid => $option) {

            $userresponses = array();
            $numberofuser = 0;
            $percentageamount = 0;
            if (property_exists($option, 'user') and
                (has_capability('mod/choice:readresponses', $context) or choice_can_see_results($choice))) {
                $numberofuser = count($option->user);
                $percentageamount = ((float)$numberofuser / (float)$results->numberofuser) * 100.0;
                if ($choice->publish) {
                    foreach ($option->user as $userresponse) {
                        $response = array();
                        $response['userid'] = $userresponse->id;
                        $response['fullname'] = fullname($userresponse);
                        $usercontext = context_user::instance($userresponse->id, IGNORE_MISSING);
                        if ($usercontext) {
                            $profileimageurl = moodle_url::make_webservice_pluginfile_url($usercontext->id, 'user', 'icon', null,
                                                                                         '/', 'f1')->out(false);
                        } else {
                            $profileimageurl = '';
                        }
                        $response['profileimageurl'] = $profileimageurl;
                        // Add optional properties.
                        foreach (array('answerid', 'timemodified') as $field) {
                            if (property_exists($userresponse, 'answerid')) {
                                $response[$field] = $userresponse->$field;
                            }
                        }
                        $userresponses[] = $response;
                    }
                }
            }

            $options[] = array('id'               => $optionid,
                               'text'             => $option->text,
                               'maxanswer'        => $option->maxanswer,
                               'userresponses'    => $userresponses,
                               'numberofuser'     => $numberofuser,
                               'percentageamount' => $percentageamount
                              );
        }
        $warnings = array();
        return array('options' => $options,
                     'warnings' => $warnings
                    );
    }

    /**
     * Describes the mod_choice_get_choice_results return value.
     *
     * @return external_single_structure
     * @since Moodle 3.0
     */
    public static function mod_choice_get_choice_results_returns() {
        return new external_single_structure(
            array(
                'options' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'choice instance id'),
                            'text' => new external_value(PARAM_TEXT, 'text of the choice'),
                            'maxanswer' => new external_value(PARAM_INT, 'maximum number of answers'),
                            'userresponses' => new external_multiple_structure(
                                 new external_single_structure(
                                     array(
                                        'userid' => new external_value(PARAM_INT, 'user id'),
                                        'fullname' => new external_value(PARAM_NOTAGS, 'user full name'),
                                        'profileimageurl' => new external_value(PARAM_URL, 'profile user image url'),
                                        'answerid' => new external_value(PARAM_INT, 'answer id', VALUE_OPTIONAL),
                                        'timemodified' => new external_value(PARAM_INT, 'time of modification', VALUE_OPTIONAL),
                                     ), 'User responses'
                                 )
                            ),
                            'numberofuser' => new external_value(PARAM_INT, 'number of users answers'),
                            'percentageamount' => new external_value(PARAM_FLOAT, 'percentage of users answers')
                        ), 'Options'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

        /**
     * Describes the parameters for mod_choice_get_choice_options.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.0
     */
    public static function mod_choice_get_choice_options_parameters() {
        return new external_function_parameters (array('choiceid' => new external_value(PARAM_INT, 'choice instance id')));
    }

    /**
     * Returns options for a specific choice
     *
     * @param int $choiceid the choice instance id
     * @return array of options details
     * @since Moodle 3.0
     */
    public static function mod_choice_get_choice_options($choiceid) {
        global $USER;
        $warnings = array();
        $params = self::validate_parameters(self::mod_choice_get_choice_options_parameters(), array('choiceid' => $choiceid));

        if (!$choice = choice_get_choice($params['choiceid'])) {
            throw new moodle_exception("invalidcoursemodule", "error");
        }
        list($course, $cm) = get_course_and_cm_from_instance($choice, 'choice');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/choice:choose', $context);

        $groupmode = groups_get_activity_groupmode($cm);
        $onlyactive = $choice->includeinactive ? false : true;
        $allresponses = choice_get_response_data($choice, $cm, $groupmode, $onlyactive);

        $timenow = time();
        $choiceopen = true;
        $showpreview = false;

        if ($choice->timeclose != 0) {
            if ($choice->timeopen > $timenow) {
                $choiceopen = false;
                $warnings[1] = get_string("notopenyet", "choice", userdate($choice->timeopen));
                if ($choice->showpreview) {
                    $warnings[2] = get_string('previewonly', 'choice', userdate($choice->timeopen));
                    $showpreview = true;
                }
            }
            if ($timenow > $choice->timeclose) {
                $choiceopen = false;
                $warnings[3] = get_string("expired", "choice", userdate($choice->timeclose));
            }
        }
        $optionsarray = array();

        if ($choiceopen or $showpreview) {

            $options = choice_prepare_options($choice, $USER, $cm, $allresponses);

            foreach ($options['options'] as $option) {
                $optionarr = array();
                $optionarr['id']            = $option->attributes->value;
                $optionarr['text']          = $option->text;
                $optionarr['maxanswers']    = $option->maxanswers;
                $optionarr['displaylayout'] = $option->displaylayout;
                $optionarr['countanswers']  = $option->countanswers;
                foreach (array('checked', 'disabled') as $field) {
                    if (property_exists($option->attributes, $field) and $option->attributes->$field == 1) {
                        $optionarr[$field] = 1;
                    } else {
                        $optionarr[$field] = 0;
                    }
                }
                // When showpreview is active, we show options as disabled.
                if ($showpreview or ($optionarr['checked'] == 1 and !$choice->allowupdate)) {
                    $optionarr['disabled'] = 1;
                }
                $optionsarray[] = $optionarr;
            }
        }
        foreach ($warnings as $key => $message) {
                    $warnings[$key] = array(
                        'item' => 'choice',
                        'itemid' => $cm->id,
                        'warningcode' => $key,
                        'message' => $message
                    );
        }
        return array(
                     'options' => $optionsarray,
                     'warnings' => $warnings
        );
    }

    /**
     * Describes the mod_choice_get_choice_results return value.
     *
     * @return external_multiple_structure
     * @since Moodle 3.0
     */
    public static function mod_choice_get_choice_options_returns() {
        return new external_single_structure(
            array(
                'options' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'option id'),
                            'text' => new external_value(PARAM_TEXT, 'text of the choice'),
                            'maxanswers' => new external_value(PARAM_INT, 'maximum number of answers'),
                            'displaylayout' => new external_value(PARAM_BOOL, 'true for orizontal, otherwise vertical'),
                            'countanswers' => new external_value(PARAM_INT, 'number of answers'),
                            'checked' => new external_value(PARAM_BOOL, 'we already answered'),
                            'disabled' => new external_value(PARAM_BOOL, 'option disabled'),
                            )
                    ), 'Options'
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for submit_choice_response.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.0
     */
    public static function mod_choice_submit_choice_response_parameters() {
        return new external_function_parameters (
                       array(
                           'choiceid' => new external_value(PARAM_INT, 'choice instance id'),
                           'responses' => new external_multiple_structure(
                               new external_value(PARAM_INT, 'answer id'),
                               'Array of response ids'
                           ),
                       )
                   );
    }

    /**
     * Submit choice responses
     *
     * @param int $choiceid the choice instance id
     * @param array $responses ids
     * @return array $answers ids
     * @since Moodle 3.0
     */
    public static function mod_choice_submit_choice_response($choiceid, $responses) {
        global $USER;

        $warnings = array();
        $params = self::validate_parameters(self::mod_choice_submit_choice_response_parameters(), array(
                                                                                         'choiceid' => $choiceid,
                                                                                         'responses' => $responses
                                                                                        ));
        if (!$choice = choice_get_choice($params['choiceid'])) {
            throw new moodle_exception("invalidcoursemodule", "error");
        }
        list($course, $cm) = get_course_and_cm_from_instance($choice, 'choice');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/choice:choose', $context);

        $timenow = time();
        if ($choice->timeclose != 0) {
            if ($choice->timeopen > $timenow) {
                throw new moodle_exception("notopenyet", "choice", '', userdate($choice->timeopen));
            } else if ($timenow > $choice->timeclose) {
                throw new moodle_exception("expired", "choice", '', userdate($choice->timeclose));
            }
        }
        if (!choice_get_my_choice_response($choice) or $choice->allowupdate) {
            // When a single response is given, we convert the array to a simple variable
            // in order to avoid choice_user_submit_response to check with allowmultiple even
            // for a single response.
            if (count($params['responses']) == 1) {
                $params['responses'] = reset($params['responses']);
            }
            choice_user_submit_response($params['responses'], $choice, $USER->id, $course, $cm);
        } else {
            throw new moodle_exception('missingrequiredcapability', 'webservice', '', 'allowupdate');
        }
        $answers = choice_get_my_choice_response($choice);

        return array(
                     'answers' => $answers,
                     'warnings' => $warnings
               );
    }

    /**
     * Describes the submit_choice_response return value.
     *
     * @return external_multiple_structure
     * @since Moodle 3.0
     */
    public static function mod_choice_submit_choice_response_returns() {
        return new external_single_structure(
            array(
                'answers' => new external_multiple_structure(
                     new external_single_structure(
                         array(
                             'id'           => new external_value(PARAM_INT, 'answer id'),
                             'choiceid'     => new external_value(PARAM_INT, 'choiceid'),
                             'userid'       => new external_value(PARAM_INT, 'user id'),
                             'optionid'     => new external_value(PARAM_INT, 'optionid'),
                             'timemodified' => new external_value(PARAM_INT, 'time of last modification')
                         ), 'Answers'
                     )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

        /**
     * Describes the parameters for mod_choice_get_choices_by_courses.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.0
     */
    public static function mod_choice_get_choices_by_courses_parameters() {
        return new external_function_parameters (
            array(
                'courseids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'course id'),
                    'Array of course ids', VALUE_DEFAULT, array()
                ),
            )
        );
    }

    /**
     * Returns a list of choices in a provided list of courses,
     * if no list is provided all choices that the user can view will be returned.
     *
     * @param array $courseids the course ids
     * @return array of choices details
     * @since Moodle 3.0
     */
    public static function mod_choice_get_choices_by_courses($courseids = array()) {
        global $CFG;
        $params = self::validate_parameters(self::mod_choice_get_choices_by_courses_parameters(), array('courseids' => $courseids));
        $warnings = array();
        if (!empty($params['courseids'])) {
            $courses = array();
            $courseids = $params['courseids'];
        } else {
            $courses = enrol_get_my_courses();
            $courseids = array_keys($courses);
        }
        // Array to store the choices to return.
        $arrchoices = array();
        // Ensure there are courseids to loop through.
        if (!empty($courseids)) {
            // Array of the courses we are going to retrieve the choices from.
            $arraycourses = array();
            // Go through the courseids.
            foreach ($courseids as $cid) {
                // Check the user can function in this context.
                try {
                    $context = context_course::instance($cid);
                    self::validate_context($context);
                    // Check if this course was already loaded (by enrol_get_my_courses).
                    if (!isset($courses[$cid])) {
                        $courses[$cid] = get_course($cid);
                    }
                    $arraycourses[$cid] = $courses[$cid];
                } catch (Exception $e) {
                    $warnings[] = array(
                        'item' => 'course',
                        'itemid' => $cid,
                        'warningcode' => '1',
                        'message' => 'No access rights in course context '.$e->getMessage()
                    );
                }
            }
            // Get the choices in this course, this function checks users visibility permissions.
            // We can avoid then additional validate_context calls.
            $choices = get_all_instances_in_courses("choice", $arraycourses);
            foreach ($choices as $choice) {

                $choicecontext = context_module::instance($choice->coursemodule);
                // Entry to return.
                $choicedetails = array();
                // First, we return information that any user can see in the web interface.
                $choicedetails['id'] = $choice->id;
                $choicedetails['coursemodule'] = $choice->coursemodule;
                $choicedetails['course'] = $choice->course;
                $choicedetails['name']  = $choice->name;
                // Format intro.
                list($choicedetails['intro'], $choicedetails['introformat']) =
                    external_format_text($choice->intro, $choice->introformat,
                                            $choicecontext->id, 'mod_choice', 'intro', null);
                if (has_capability('mod/choice:choose', $choicecontext)) {
                    $choicedetails['publish']  = $choice->publish;
                    $choicedetails['showresults']  = $choice->showresults;
                    $choicedetails['showpreview']  = $choice->showpreview;
                }
                    $choicedetails['timeopen']  = $choice->timeopen;
                    $choicedetails['timeclose']  = $choice->timeclose;
                    $choicedetails['display']  = $choice->display;
                    $choicedetails['allowupdate']  = $choice->allowupdate;
                    $choicedetails['allowmultiple']  = $choice->allowmultiple;
                    $choicedetails['limitanswers']  = $choice->limitanswers;
                    $choicedetails['showunanswered']  = $choice->showunanswered;
                    $choicedetails['includeinactive']  = $choice->includeinactive;

                if (has_capability('moodle/course:manageactivities', $choicecontext)) {
                    $choicedetails['timemodified']  = $choice->timemodified;
                    $choicedetails['completionsubmit']  = $choice->completionsubmit;
                    $choicedetails['section']  = $choice->section;
                    $choicedetails['visible']  = $choice->visible;
                    $choicedetails['groupmode']  = $choice->groupmode;
                    $choicedetails['groupingid']  = $choice->groupingid;
                }
                $arrchoices[] = $choicedetails;
            }
        }
        $result = array();
        $result['choices'] = $arrchoices;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the mod_choice_get_choices_by_courses return value.
     *
     * @return external_single_structure
     * @since Moodle 3.0
     */
    public static function mod_choice_get_choices_by_courses_returns() {
        return new external_single_structure(
            array(
                'choices' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'choice instance id'),
                            'coursemodule' => new external_value(PARAM_INT, 'Course module id'),
                            'course' => new external_value(PARAM_TEXT, 'Course id'),
                            'name' => new external_value(PARAM_TEXT, 'Choice name'),
                            'intro' => new external_value(PARAM_RAW, 'The Choice intro'),
                            'introformat' => new external_format_value('intro'),
                            'publish' => new external_value(PARAM_BOOL, 'Is puplished', VALUE_OPTIONAL),
                            'showresults' => new external_value(PARAM_INT, '0 never, 1 after answer, 2 after close, 3 always',
                                                                VALUE_OPTIONAL),
                            'display' => new external_value(PARAM_BOOL, 'display (vertical, orizontal)', VALUE_OPTIONAL),
                            'allowupdate' => new external_value(PARAM_BOOL, 'allow update', VALUE_OPTIONAL),
                            'allowmultiple' => new external_value(PARAM_BOOL, 'allow multiple choices', VALUE_OPTIONAL),
                            'showunanswered' => new external_value(PARAM_BOOL, 'show users who not unswered yet', VALUE_OPTIONAL),
                            'includeinactive' => new external_value(PARAM_BOOL, 'include inactive users', VALUE_OPTIONAL),
                            'limitanswers' => new external_value(PARAM_BOOL, 'limit unswers', VALUE_OPTIONAL),
                            'timeopen' => new external_value(PARAM_RAW, 'date/time of opening validity', VALUE_OPTIONAL),
                            'timeclose' => new external_value(PARAM_RAW, 'date/time of closing validity', VALUE_OPTIONAL),
                            'showpreview' => new external_value(PARAM_BOOL, 'show preview before timeopen', VALUE_OPTIONAL),
                            'timemodified' => new external_value(PARAM_RAW, 'time of last modification', VALUE_OPTIONAL),
                            'completionsubmit' => new external_value(PARAM_BOOL, 'completion submit', VALUE_OPTIONAL),
                            'section' => new external_value(PARAM_INT, 'course section id', VALUE_OPTIONAL),
                            'visible' => new external_value(PARAM_BOOL, 'visible', VALUE_OPTIONAL),
                            'groupmode' => new external_value(PARAM_INT, 'group mode', VALUE_OPTIONAL),
                            'groupingid' => new external_value(PARAM_INT, 'group id', VALUE_OPTIONAL),
                        ), 'Choices'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

}