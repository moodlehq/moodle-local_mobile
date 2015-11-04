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
    'local_mobile_get_plugin_settings' => array(
        'classname'   => 'local_mobile_external',
        'methodname'  => 'get_plugin_settings',
        'classpath'   => 'local/mobile/externallib.php',
        'description' => 'Retrieve the plugin settings.',
        'type'        => 'read',
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
            'core_course_search_courses',
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
            'enrol_self_enrol_user',
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
            'core_completion_mark_course_self_completed',
            'core_group_get_activity_allowed_groups',
            'core_group_get_activity_groupmode',
            'local_mobile_get_plugin_settings',
            'mod_assign_view_grading_table',
            'mod_book_view_book',
            'mod_chat_get_chat_latest_messages',
            'mod_chat_get_chat_users',
            'mod_chat_get_chats_by_courses',
            'mod_chat_login_user',
            'mod_chat_send_chat_message',
            'mod_chat_view_chat',
            'mod_choice_delete_choice_responses',
            'mod_choice_get_choice_options',
            'mod_choice_get_choice_results',
            'mod_choice_get_choices_by_courses',
            'mod_choice_submit_choice_response',
            'mod_choice_view_choice',
            'mod_folder_view_folder',
            'mod_forum_add_discussion',
            'mod_forum_add_discussion_post',
            'mod_forum_get_forums_by_courses',
            'mod_imscp_view_imscp',
            'mod_lti_get_ltis_by_courses',
            'mod_lti_get_tool_launch_data',
            'mod_lti_view_lti',
            'mod_page_view_page',
            'mod_resource_view_resource',
            'mod_survey_get_questions',
            'mod_survey_get_surveys_by_courses',
            'mod_survey_submit_answers',
            'mod_survey_view_survey',
            'mod_url_view_url',
        ),
        'enabled' => 0,
        'restrictedusers' => 0,
        'shortname' => 'local_mobile',
        'downloadfiles' => 1,
        'uploadfiles' => 1
    ),
);