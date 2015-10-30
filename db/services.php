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
 * External functions and service definitions.
 *
 * @package    local_mobile
 * @copyright  2014 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$functions = array(

    'local_mobile_core_completion_mark_course_self_completed' => array(
        'classname'   => 'local_mobile_external',
        'methodname'  => 'core_completion_mark_course_self_completed',
        'classpath'   => 'local/mobile/externallib.php',
        'description' => 'Update the course completion status for the current user (if course self-completion is enabled).',
        'type'        => 'write',
    ),

    'local_mobile_core_course_search_courses' => array(
        'classname'     => 'local_mobile_external',
        'methodname'    => 'core_course_search_courses',
        'classpath'     => 'local/mobile/externallib.php',
        'description'   => 'Search courses by (name, module, block, tag)',
        'type'          => 'read',
        'capabilities'  => '',
    ),

    'local_mobile_core_group_get_activity_allowed_groups' => array(
        'classname'     => 'local_mobile_external',
        'methodname'    => 'core_group_get_activity_allowed_groups',
        'classpath'     => 'local/mobile/externallib.php',
        'description'   => 'Gets a list of groups that the user is allowed to access within the specified activity.',
        'type'          => 'read',
        'capabilities'  => ''
    ),

    'local_mobile_core_group_get_activity_groupmode' => array(
        'classname'     => 'local_mobile_external',
        'methodname'    => 'core_group_get_activity_groupmode',
        'classpath'     => 'local/mobile/externallib.php',
        'description'   => 'Returns effective groupmode used in a given activity.',
        'type'          => 'read',
        'capabilities'  => ''
    ),

    'local_mobile_enrol_self_enrol_user' => array(
        'classname'   => 'local_mobile_external',
        'methodname'  => 'enrol_self_enrol_user',
        'classpath'   => 'local/mobile/externallib.php',
        'description' => 'Self enrol the current user in the given course.',
        'type'        => 'write'
    ),

    'local_mobile_mod_assign_view_grading_table' => array(
        'classname'     => 'local_mobile_external',
        'methodname'    => 'mod_assign_view_grading_table',
        'classpath'     => 'local/mobile/externallib.php',
        'description'   => 'Simulate the view.php web interface page: trigger events, completion, etc...',
        'type'          => 'write',
        'capabilities'  => 'mmod/assign:view, mod/assign:viewgrades'
    ),

    'local_mobile_mod_book_view_book' => array(
        'classname'     => 'local_mobile_external',
        'methodname'    => 'mod_book_view_book',
        'classpath'     => 'local/mobile/externallib.php',
        'description'   => 'Simulate the view.php web interface folder: trigger events, completion, etc...',
        'type'          => 'write',
        'capabilities'  => 'mod/book:read'
    ),

    'local_mobile_mod_chat_get_chat_latest_messages' => array(
        'classname'     => 'local_mobile_external',
        'methodname'    => 'mod_chat_get_chat_latest_messages',
        'classpath'     => 'local/mobile/externallib.php',
        'description'   => 'Get the latest messages from the given chat session.',
        'type'          => 'read',
        'capabilities'  => 'mod/chat:chat'
    ),

    'local_mobile_mod_chat_get_chat_users' => array(
        'classname'     => 'local_mobile_external',
        'methodname'    => 'mod_chat_get_chat_users',
        'classpath'     => 'local/mobile/externallib.php',
        'description'   => 'Get the list of users in the given chat session.',
        'type'          => 'read',
        'capabilities'  => 'mod/chat:chat'
    ),

    'local_mobile_mod_chat_get_chats_by_courses' => array(
        'classname'     => 'local_mobile_external',
        'methodname'    => 'mod_chat_get_chats_by_courses',
        'classpath'     => 'local/mobile/externallib.php',
        'description'   => 'Retrieve chat activities by courses.',
        'type'          => 'read',
        'capabilities'  => 'mod/chat:chat'
    ),

    'local_mobile_mod_chat_login_user' => array(
        'classname'     => 'local_mobile_external',
        'methodname'    => 'mod_chat_login_user',
        'classpath'     => 'local/mobile/externallib.php',
        'description'   => 'Log a user into a chat room in the given chat.',
        'type'          => 'write',
        'capabilities'  => 'mod/chat:chat'
    ),


    'local_mobile_mod_chat_send_chat_message' => array(
        'classname'     => 'local_mobile_external',
        'methodname'    => 'mod_chat_send_chat_message',
        'classpath'     => 'local/mobile/externallib.php',
        'description'   => 'Send a message on the given chat session.',
        'type'          => 'write',
        'capabilities'  => 'mod/chat:chat'
    ),

    'local_mobile_mod_chat_view_chat' => array(
        'classname'     => 'local_mobile_external',
        'methodname'    => 'mod_chat_view_chat',
        'classpath'     => 'local/mobile/externallib.php',
        'description'   => 'Simulate the view.php web interface folder: trigger events, completion, etc...',
        'type'          => 'write',
        'capabilities'  => 'mod/chat:chat'
    ),

    'local_mobile_mod_choice_delete_choice_responses' => array(
        'classname'   => 'local_mobile_external',
        'methodname'  => 'mod_choice_delete_choice_responses',
        'classpath'   => 'local/mobile/externallib.php',
        'description' => 'Delete the given submitted responses in a choice',
        'type'        => 'write',
        'capabilities'  => 'mod/choice:choose'
    ),

    'local_mobile_mod_choice_get_choice_options' => array(
        'classname'     => 'local_mobile_external',
        'methodname'    => 'mod_choice_get_choice_options',
        'classpath'     => 'local/mobile/externallib.php',
        'description'   => 'Retrieve options for a specific choice.',
        'type'          => 'read',
        'capabilities'  => 'mod/choice:choose'
    ),

    'local_mobile_mod_choice_get_choice_results' => array(
        'classname'     => 'local_mobile_external',
        'methodname'    => 'mod_choice_get_choice_results',
        'classpath'     => 'local/mobile/externallib.php',
        'description'   => 'Retrieve users results for a specific choice.',
        'type'          => 'read',
        'capabilities'  => ''
    ),

    'local_mobile_mod_choice_get_choices_by_courses' => array(
        'classname'     => 'local_mobile_external',
        'methodname'    => 'mod_choice_get_choices_by_courses',
        'classpath'     => 'local/mobile/externallib.php',
        'description'   => 'Retrieve choice activities by courses.',
        'type'          => 'read',
        'capabilities'  => ''
    ),

    'local_mobile_mod_choice_submit_choice_response' => array(
        'classname'     => 'local_mobile_external',
        'methodname'    => 'mod_choice_submit_choice_response',
        'classpath'     => 'local/mobile/externallib.php',
        'description'   => 'Submit responses to a specific choice item.',
        'type'          => 'write',
        'capabilities'  => 'mod/choice:choose'
    ),

    'local_mobile_mod_choice_view_choice' => array(
        'classname'     => 'local_mobile_external',
        'methodname'    => 'mod_choice_view_choice',
        'classpath'     => 'local/mobile/externallib.php',
        'description'   => 'Simulate the view.php web interface folder: trigger events, completion, etc...',
        'type'          => 'write',
        'capabilities'  => ''
    ),

    'local_mobile_mod_folder_view_folder' => array(
        'classname'     => 'local_mobile_external',
        'methodname'    => 'mod_folder_view_folder',
        'classpath'     => 'local/mobile/externallib.php',
        'description'   => 'Simulate the view.php web interface folder: trigger events, completion, etc...',
        'type'          => 'write',
        'capabilities'  => 'mod/folder:view'
    ),

    'local_mobile_mod_forum_add_discussion' => array(
        'classname'     => 'local_mobile_external',
        'methodname'    => 'mod_forum_add_discussion',
        'classpath'     => 'local/mobile/externallib.php',
        'description'   => 'Add a new discussion into an existing forum.',
        'type'          => 'write',
        'capabilities'  => 'mod/forum:startdiscussion'
    ),

    'local_mobile_mod_forum_add_discussion_post' => array(
        'classname'     => 'local_mobile_external',
        'methodname'    => 'mod_forum_add_discussion_post',
        'classpath'     => 'local/mobile/externallib.php',
        'description'   => 'Create new posts into an existing discussion.',
        'type'          => 'write',
        'capabilities'  => 'mod/forum:replypost'
    ),

    'local_mobile_mod_forum_get_forums_by_courses' => array(
        'classname'     => 'local_mobile_external',
        'methodname'    => 'mod_forum_get_forums_by_courses',
        'classpath'     => 'local/mobile/externallib.php',
        'description'   => 'Returns a list of forum instances in a provided set of courses, if
            no courses are provided then all the forum instances the user has access to will be
            returned.',
        'type'          => 'read',
        'capabilities'  => 'mod/forum:viewdiscussion'
    ),

    'local_mobile_mod_imscp_view_imscp' => array(
        'classname'     => 'local_mobile_external',
        'methodname'    => 'mod_imscp_view_imscp',
        'classpath'     => 'local/mobile/externallib.php',
        'description'   => 'Simulate the view.php web interface folder: trigger events, completion, etc...',
        'type'          => 'write',
        'capabilities'  => 'mod/imscp:view'
    ),

    'local_mobile_mod_lti_get_ltis_by_courses' => array(
        'classname'     => 'local_mobile_external',
        'methodname'    => 'mod_lti_get_ltis_by_courses',
        'classpath'     => 'local/mobile/externallib.php',
        'description'   => 'Returns a list of external tool instances in a provided set of courses, if
                            no courses are provided then all the external tool instances the user has access to will be returned.',
        'type'          => 'read',
        'capabilities'  => 'mod/lti:view'
    ),

    'local_mobile_mod_lti_get_tool_launch_data' => array(
        'classname'     => 'local_mobile_external',
        'methodname'    => 'mod_lti_get_tool_launch_data',
        'classpath'     => 'local/mobile/externallib.php',
        'description'   => 'Return the launch data for a given external tool.',
        'type'          => 'read',
        'capabilities'  => 'mod/lti:view'
    ),

    'local_mobile_mod_lti_view_lti' => array(
        'classname'     => 'local_mobile_external',
        'methodname'    => 'mod_lti_view_lti',
        'classpath'     => 'local/mobile/externallib.php',
        'description'   => 'Trigger the course module viewed event and update the module completion status.',
        'type'          => 'write',
        'capabilities'  => 'mod/lti:view'
    ),

    'local_mobile_mod_page_view_page' => array(
        'classname'     => 'local_mobile_external',
        'methodname'    => 'mod_page_view_page',
        'classpath'     => 'local/mobile/externallib.php',
        'description'   => 'Simulate the view.php web interface page: trigger events, completion, etc...',
        'type'          => 'write',
        'capabilities'  => 'mod/page:view'
    ),

    'local_mobile_mod_resource_view_resource' => array(
        'classname'     => 'local_mobile_external',
        'methodname'    => 'mod_resource_view_resource',
        'classpath'     => 'local/mobile/externallib.php',
        'description'   => 'Simulate the view.php web interface page: trigger events, completion, etc...',
        'type'          => 'write',
        'capabilities'  => 'mod/resource:view'
    ),

    'local_mobile_mod_survey_get_questions' => array(
        'classname'     => 'local_mobile_external',
        'methodname'    => 'mod_survey_get_questions',
        'classpath'     => 'local/mobile/externallib.php',
        'description'   => 'Get the complete list of questions for the survey, including subquestions.',
        'type'          => 'read',
        'capabilities'  => 'mod/survey:participate'
    ),

    'local_mobile_mod_survey_get_surveys_by_courses' => array(
        'classname'     => 'local_mobile_external',
        'methodname'    => 'mod_survey_get_surveys_by_courses',
        'classpath'     => 'local/mobile/externallib.php',
        'description'   => 'Returns a list of survey instances in a provided set of courses,
                            if no courses are provided then all the survey instances the user has access to will be returned.',
        'type'          => 'read',
        'capabilities'  => ''
    ),

    'local_mobile_mod_survey_submit_answers' => array(
        'classname'     => 'local_mobile_external',
        'methodname'    => 'mod_survey_submit_answers',
        'classpath'     => 'local/mobile/externallib.php',
        'description'   => 'Submit the answers for a given survey.',
        'type'          => 'write',
        'capabilities'  => 'mod/survey:participate'
    ),

    'local_mobile_mod_survey_view_survey' => array(
        'classname'     => 'local_mobile_external',
        'methodname'    => 'mod_survey_view_survey',
        'classpath'     => 'local/mobile/externallib.php',
        'description'   => 'Trigger the course module viewed event and update the module completion status.',
        'type'          => 'write',
        'capabilities'  => 'mod/survey:participate'
    ),

    'local_mobile_mod_url_view_url' => array(
        'classname'     => 'local_mobile_external',
        'methodname'    => 'mod_url_view_url',
        'classpath'     => 'local/mobile/externallib.php',
        'description'   => 'Simulate the view.php web interface page: trigger events, completion, etc...',
        'type'          => 'write',
        'capabilities'  => 'mod/url:view'
    ),
);

$services = array(
   'Moodle Mobile additional features service'  => array(
        'functions' => array (

            'core_calendar_get_calendar_events',
            'core_comment_get_comments',
            'core_completion_get_activities_completion_status',
            'core_completion_get_course_completion_status',
            'core_completion_update_activity_completion_status_manually',
            'core_course_get_contents',
            'core_course_get_courses',
            'core_course_view_course',
            'core_enrol_get_enrolled_users',
            'core_enrol_get_users_courses',
            'core_get_component_strings',   // Don't remove this, the app relies on this to check the min version.
            'core_group_get_course_user_groups',
            'core_files_get_files',
            'core_message_block_contacts',
            'core_message_create_contacts',
            'core_message_delete_contacts',
            'core_message_get_blocked_users',
            'core_message_get_contacts',
            'core_message_get_messages',
            'core_message_mark_message_read',
            'core_message_search_contacts',
            'core_message_send_instant_messages',
            'core_message_unblock_contacts',
            'core_notes_create_notes',
            'core_notes_get_course_notes',
            'core_notes_view_notes',
            'core_rating_get_item_ratings',
            'core_user_add_user_device',
            'core_user_add_user_private_files',
            'core_user_get_course_user_profiles',
            'core_user_get_users_by_field',
            'core_user_get_users_by_id',
            'core_user_remove_user_device',
            'core_user_view_user_list',
            'core_user_view_user_profile',
            'core_webservice_get_site_info',
            'gradereport_user_get_grades_table',
            'gradereport_user_view_grade_report',
            'message_airnotifier_are_notification_preferences_configured',
            'message_airnotifier_is_system_configured',
            'mod_assign_get_assignments',
            'mod_assign_get_submissions',
            'mod_data_get_databases_by_courses',
            'mod_forum_get_forum_discussions_paginated',
            'mod_forum_get_forum_discussion_posts',
            'mod_forum_view_forum',
            'mod_forum_view_forum_discussion',
            'local_mobile_core_completion_mark_course_self_completed',
            'local_mobile_core_course_search_courses',
            'local_mobile_core_group_get_activity_allowed_groups',
            'local_mobile_core_group_get_activity_groupmode',
            'local_mobile_enrol_self_enrol_user',
            'local_mobile_mod_assign_view_grading_table',
            'local_mobile_mod_book_view_book',
            'local_mobile_mod_chat_get_chat_latest_messages',
            'local_mobile_mod_chat_get_chat_users',
            'local_mobile_mod_chat_get_chats_by_courses',
            'local_mobile_mod_chat_login_user',
            'local_mobile_mod_chat_send_chat_message',
            'local_mobile_mod_chat_view_chat',
            'local_mobile_mod_choice_delete_choice_responses',
            'local_mobile_mod_choice_get_choice_options',
            'local_mobile_mod_choice_get_choice_results',
            'local_mobile_mod_choice_get_choices_by_courses',
            'local_mobile_mod_choice_submit_choice_response',
            'local_mobile_mod_choice_view_choice',
            'local_mobile_mod_folder_view_folder',
            'local_mobile_mod_forum_add_discussion',
            'local_mobile_mod_forum_add_discussion_post',
            'local_mobile_mod_forum_get_forums_by_courses',
            'local_mobile_mod_imscp_view_imscp',
            'local_mobile_mod_lti_get_ltis_by_courses',
            'local_mobile_mod_lti_get_tool_launch_data',
            'local_mobile_mod_lti_view_lti',
            'local_mobile_mod_page_view_page',
            'local_mobile_mod_resource_view_resource',
            'local_mobile_mod_survey_get_questions',
            'local_mobile_mod_survey_get_surveys_by_courses',
            'local_mobile_mod_survey_submit_answers',
            'local_mobile_mod_survey_view_survey',
            'local_mobile_mod_url_view_url',
        ),
        'enabled' => 0,
        'restrictedusers' => 0,
        'shortname' => 'local_mobile',
        'downloadfiles' => 1,
        'uploadfiles' => 1
    ),
);