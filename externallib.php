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

}