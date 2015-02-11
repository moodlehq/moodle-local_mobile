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

    'local_mobile_core_user_remove_user_device' => array(
        'classname'     => 'local_mobile_external',
        'methodname'    => 'core_user_remove_user_device',
        'classpath'     => 'local/mobile/externallib.php',
        'description'   => 'Remove user devices.',
        'type'          => 'write',
    ),
    'local_mobile_gradereport_user_get_grades_table' => array(
        'classname'   => 'local_mobile_external',
        'methodname'  => 'gradereport_user_get_grades_table',
        'classpath'   => 'local/mobile/externallib.php',
        'description' => 'Get the user/s report grades table for a course',
        'type'        => 'read',
        'capabilities'=> '',
    ),
    'local_mobile_core_message_search_contacts' => array(
        'classname'   => 'local_mobile_external',
        'methodname'  => 'core_message_search_contacts',
        'classpath'   => 'local/mobile/externallib.php',
        'description' => 'Search for contacts',
        'type'        => 'read',
        'capabilities'=> '',
    ),
    'local_mobile_core_message_get_blocked_users' => array(
        'classname'     => 'local_mobile_external',
        'methodname'    => 'core_message_get_blocked_users',
        'classpath'     => 'local/mobile/externallib.php',
        'description'   => 'Retrieve a list of users blocked',
        'type'          => 'read',
        'capabilities'  => '',
    ),
    'local_mobile_core_group_get_course_user_groups' => array(
        'classname'   => 'local_mobile_external',
        'methodname'  => 'core_group_get_course_user_groups',
        'classpath'   => 'local/mobile/externallib.php',
        'description' => 'Returns all groups in specified course for the specified user.',
        'type'        => 'read',
        'capabilities'=> 'moodle/course:managegroups',
    ),
);

$services = array(
   'Moodle Mobile additional features service'  => array(
        'functions' => array (
            'core_webservice_get_site_info',
            'core_enrol_get_users_courses',
            'core_notes_create_notes',
            'core_message_send_instant_messages',
            'core_enrol_get_enrolled_users',
            'core_user_get_course_user_profiles',
            'moodle_enrol_get_users_courses',
            'moodle_enrol_get_enrolled_users',
            'moodle_user_get_users_by_id',
            'moodle_webservice_get_siteinfo',
            'moodle_notes_create_notes',
            'moodle_user_get_course_participants_by_id',
            'moodle_user_get_users_by_courseid',
            'moodle_message_send_instantmessages',
            'core_course_get_contents',
            'core_get_component_strings',
            'core_message_get_messages',
            'core_calendar_get_calendar_events',
            'core_user_add_user_device',
            'core_grades_get_grades',
            'message_airnotifier_is_system_configured',
            'message_airnotifier_are_notification_preferences_configured',
            'mod_forum_get_forums_by_courses',
            'mod_forum_get_forum_discussions_paginated',
            'mod_forum_get_forum_discussion_posts',
            'core_files_get_files',
            'core_message_get_messages',
            'core_message_create_contacts',
            'core_message_delete_contacts',
            'core_message_block_contacts',
            'core_message_unblock_contacts',
            'core_message_get_contacts',
            'core_message_search_contacts',
            'local_mobile_core_message_search_contacts',
            'local_mobile_gradereport_user_get_grades_table',
            'local_mobile_core_message_get_blocked_users',
            'local_mobile_core_group_get_course_user_groups',
            'local_mobile_core_user_remove_user_device'
        ),
        'enabled' => 0,
        'restrictedusers' => 0,
        'shortname' => 'local_mobile',
        'downloadfiles' => 1,
        'uploadfiles' => 1
    ),
);