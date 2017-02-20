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
require_once("$CFG->dirroot/local/mobile/futurelib.php");

class local_mobile_external extends external_api {

    /**
     * Returns description of get_course_enrolment_methods() parameters
     *
     * @return external_function_parameters
     */
    public static function core_enrol_get_course_enrolment_methods_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course id')
            )
        );
    }
    /**
     * Get list of active course enrolment methods for current user.
     *
     * @param int $courseid
     * @return array of course enrolment methods
     */
    public static function core_enrol_get_course_enrolment_methods($courseid) {
        $params = self::validate_parameters(self::core_enrol_get_course_enrolment_methods_parameters(), array('courseid' => $courseid));
        $coursecontext = context_course::instance($params['courseid']);
        $categorycontext = $coursecontext->get_parent_context();
        self::validate_context($categorycontext);
        $result = array();
        $enrolinstances = enrol_get_instances($params['courseid'], true);
        foreach ($enrolinstances as $enrolinstance) {
            if ($enrolplugin = enrol_get_plugin($enrolinstance->enrol)) {
                if ($instanceinfo = $enrolplugin->get_enrol_info($enrolinstance)) {
                    $result[] = (array) $instanceinfo;
                } else if ($enrolinstance->enrol == 'guest') {
                    $result[] = (array) enrol_guest_get_enrol_info($enrolinstance);
                }
            }
        }
        return $result;
    }
    /**
     * Returns description of get_course_enrolment_methods() result value
     *
     * @return external_description
     */
    public static function core_enrol_get_course_enrolment_methods_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'id of course enrolment instance'),
                    'courseid' => new external_value(PARAM_INT, 'id of course'),
                    'type' => new external_value(PARAM_PLUGIN, 'type of enrolment plugin'),
                    'name' => new external_value(PARAM_RAW, 'name of enrolment plugin'),
                    'status' => new external_value(PARAM_RAW, 'status of enrolment plugin'),
                    'wsfunction' => new external_value(PARAM_ALPHANUMEXT, 'webservice function to get more information', VALUE_OPTIONAL),
                )
            )
        );
    }


    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function get_plugin_settings_parameters() {
        return new external_function_parameters(
            array()
        );
    }

    /**
     * Get all the plugin settings.
     * PLEASE DO NOT DELETE THIS FUNCTION.
     * The Mobile app relies in this function to detect if the site is using the local_mobile plugin.
     *
     * @return array of settings
     */
    public static function get_plugin_settings() {

        // Warnings array, it can be empty at the end but is mandatory.
        $warnings = array();
        $settings = array();

        $pluginsettings = get_config('local_mobile');
        foreach ($pluginsettings as $key => $val) {
            $settings[] = array(
                'name' => $key,
                'value' => $val,
            );
        }

        $results = array(
            'settings' => $settings,
            'warnings' => $warnings
        );
        return $results;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function get_plugin_settings_returns() {
        return new external_single_structure(
            array(
                'settings' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_NOTAGS, 'setting name'),
                            'value' => new external_value(PARAM_RAW, 'setting value'),
                        )
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Returns description of get_instance_info() parameters.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function enrol_guest_get_instance_info_parameters() {
        return new external_function_parameters(
                array('instanceid' => new external_value(PARAM_INT, 'Instance id of guest enrolment plugin.'))
            );
    }
    /**
     * Return guest enrolment instance information.
     *
     * @param int $instanceid instance id of guest enrolment plugin.
     * @return array warnings and instance information.
     * @since Moodle 3.1
     */
    public static function enrol_guest_get_instance_info($instanceid) {
        global $DB;
        $params = self::validate_parameters(self::enrol_guest_get_instance_info_parameters(), array('instanceid' => $instanceid));
        $warnings = array();
        // Retrieve guest enrolment plugin.
        $enrolplugin = enrol_get_plugin('guest');
        if (empty($enrolplugin)) {
            throw new moodle_exception('invaliddata', 'error');
        }
        require_login(null, false, null, false, true);
        $enrolinstance = $DB->get_record('enrol', array('id' => $params['instanceid']), '*', MUST_EXIST);
        $course = $DB->get_record('course', array('id' => $enrolinstance->courseid), '*', MUST_EXIST);
        $context = context_course::instance($course->id);
        if (!$course->visible and !has_capability('moodle/course:viewhiddencourses', $context)) {
            throw new moodle_exception('coursehidden');
        }
        $instanceinfo = enrol_guest_get_enrol_info($enrolinstance);

        // Specific instance information.
        $instanceinfo->passwordrequired = $instanceinfo->requiredparam->passwordrequired;
        unset($instanceinfo->requiredparam);
        $result = array();
        $result['instanceinfo'] = $instanceinfo;
        $result['warnings'] = $warnings;
        return $result;
    }
    /**
     * Returns description of get_instance_info() result value.
     *
     * @return external_description
     * @since Moodle 3.1
     */
    public static function enrol_guest_get_instance_info_returns() {
        return new external_single_structure(
            array(
                'instanceinfo' => new external_single_structure(
                    array(
                        'id' => new external_value(PARAM_INT, 'Id of course enrolment instance'),
                        'courseid' => new external_value(PARAM_INT, 'Id of course'),
                        'type' => new external_value(PARAM_PLUGIN, 'Type of enrolment plugin'),
                        'name' => new external_value(PARAM_RAW, 'Name of enrolment plugin'),
                        'status' => new external_value(PARAM_BOOL, 'Is the enrolment enabled?'),
                        'passwordrequired' => new external_value(PARAM_BOOL, 'Is a password required?'),
                    )
                ),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_forum_can_add_discussion_parameters() {
        return new external_function_parameters(
            array(
                'forumid' => new external_value(PARAM_INT, 'Forum instance ID'),
                'groupid' => new external_value(PARAM_INT, 'The group to check, default to active group.
                                                Use -1 to check if the user can post in all the groups.', VALUE_DEFAULT, null)
            )
        );
    }
    /**
     * Check if the current user can add discussions in the given forum (and optionally for the given group).
     *
     * @param int $forumid the forum instance id
     * @param int $groupid the group to check, default to active group. Use -1 to check if the user can post in all the groups.
     * @return array of warnings and the status (true if the user can add discussions)
     * @since Moodle 3.1
     * @throws moodle_exception
     */
    public static function mod_forum_can_add_discussion($forumid, $groupid = null) {
        global $DB, $CFG;
        require_once($CFG->dirroot . "/mod/forum/lib.php");
        $params = self::validate_parameters(self::mod_forum_can_add_discussion_parameters(),
                                            array(
                                                'forumid' => $forumid,
                                                'groupid' => $groupid,
                                            ));
        $warnings = array();
        // Request and permission validation.
        $forum = $DB->get_record('forum', array('id' => $params['forumid']), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($forum, 'forum');
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        $status = forum_user_can_post_discussion($forum, $params['groupid'], -1, $cm, $context);
        $result = array();
        $result['status'] = $status;
        $result['warnings'] = $warnings;
        return $result;
    }
    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.1
     */
    public static function mod_forum_can_add_discussion_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'True if the user can add discussions, false otherwise.'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Get the browse modes from the display format.
     *
     * This returns some of the terms that can be used when reporting a glossary being viewed.
     *
     * @param  string $format The display format of the glossary.
     * @return array Containing some of all of the following: letter, cat, date, author.
     */
    protected static function mod_glossary_get_browse_modes_from_display_format($format) {
        global $DB;

        $formats = array();
        $dp = $DB->get_record('glossary_formats', array('name' => $format), '*', IGNORE_MISSING);
        if ($dp) {
            $formats = glossary_get_visible_tabs($dp);
        }

        // Always add 'letter'.
        $modes = array('letter');

        if (in_array('category', $formats)) {
            $modes[] = 'cat';
        }
        if (in_array('date', $formats)) {
            $modes[] = 'date';
        }
        if (in_array('author', $formats)) {
            $modes[] = 'author';
        }

        return $modes;
    }

    /**
     * Get the return value of an entry.
     *
     * @param bool $includecat Whether the definition should include category info.
     * @return external_definition
     */
    protected static function mod_glossary_get_entry_return_structure($includecat = false) {
        $params = array(
            'id' => new external_value(PARAM_INT, 'The entry ID'),
            'glossaryid' => new external_value(PARAM_INT, 'The glossary ID'),
            'userid' => new external_value(PARAM_INT, 'Author ID'),
            'userfullname' => new external_value(PARAM_NOTAGS, 'Author full name'),
            'userpictureurl' => new external_value(PARAM_URL, 'Author picture'),
            'concept' => new external_value(PARAM_RAW, 'The concept'),
            'definition' => new external_value(PARAM_RAW, 'The definition'),
            'definitionformat' => new external_format_value('definition'),
            'definitiontrust' => new external_value(PARAM_BOOL, 'The definition trust flag'),
            'attachment' => new external_value(PARAM_BOOL, 'Whether or not the entry has attachments'),
            'attachments' => new external_multiple_structure(
                new external_single_structure(array(
                    'filename' => new external_value(PARAM_FILE, 'File name'),
                    'mimetype' => new external_value(PARAM_RAW, 'Mime type'),
                    'fileurl'  => new external_value(PARAM_URL, 'File download URL')
                )), 'attachments', VALUE_OPTIONAL
            ),
            'timecreated' => new external_value(PARAM_INT, 'Time created'),
            'timemodified' => new external_value(PARAM_INT, 'Time modified'),
            'teacherentry' => new external_value(PARAM_BOOL, 'The entry was created by a teacher, or equivalent.'),
            'sourceglossaryid' => new external_value(PARAM_INT, 'The source glossary ID'),
            'usedynalink' => new external_value(PARAM_BOOL, 'Whether the concept should be automatically linked'),
            'casesensitive' => new external_value(PARAM_BOOL, 'When true, the matching is case sensitive'),
            'fullmatch' => new external_value(PARAM_BOOL, 'When true, the matching is done on full words only'),
            'approved' => new external_value(PARAM_BOOL, 'Whether the entry was approved'),
        );

        if ($includecat) {
            $params['categoryid'] = new external_value(PARAM_INT, 'The category ID. This may be' .
                ' \''. GLOSSARY_SHOW_NOT_CATEGORISED . '\' when the entry is not categorised', VALUE_DEFAULT,
                GLOSSARY_SHOW_NOT_CATEGORISED);
            $params['categoryname'] = new external_value(PARAM_RAW, 'The category name. May be empty when the entry is' .
                ' not categorised, or the request was limited to one category.', VALUE_DEFAULT, '');
        }

        return new external_single_structure($params);
    }

    /**
     * Fill in an entry object.
     *
     * This adds additional required fields for the external function to return.
     *
     * @param  stdClass $entry   The entry.
     * @param  context  $context The context the entry belongs to.
     * @return void
     */
    protected static function mod_glossary_fill_entry_details($entry, $context) {
        global $PAGE;
        $canviewfullnames = has_capability('moodle/site:viewfullnames', $context);

        // Format concept and definition.
        $entry->concept = external_format_string($entry->concept, $context->id);
        list($entry->definition, $entry->definitionformat) = external_format_text($entry->definition, $entry->definitionformat,
            $context->id, 'mod_glossary', 'entry', $entry->id);

        // Author details.
        $user = mod_glossary_entry_query_builder::get_user_from_record($entry);
        $userpicture = new user_picture($user);
        $userpicture->size = 1;
        $entry->userfullname = fullname($user, $canviewfullnames);
        $entry->userpictureurl = $userpicture->get_url($PAGE)->out(false);

        // Fetch attachments.
        $entry->attachment = !empty($entry->attachment) ? 1 : 0;
        $entry->attachments = array();
        if ($entry->attachment) {
            $fs = get_file_storage();
            if ($files = $fs->get_area_files($context->id, 'mod_glossary', 'attachment', $entry->id, 'filename', false)) {
                foreach ($files as $file) {
                    $filename = $file->get_filename();
                    $fileurl = moodle_url::make_webservice_pluginfile_url($context->id, 'mod_glossary', 'attachment',
                        $entry->id, '/', $filename);
                    $entry->attachments[] = array(
                        'filename' => $filename,
                        'mimetype' => $file->get_mimetype(),
                        'fileurl'  => $fileurl->out(false)
                    );
                }
            }
        }
    }

    /**
     * Validate a glossary via ID.
     *
     * @param  int $id The glossary ID.
     * @return array Contains glossary, context, course and cm.
     */
    protected static function mod_glossary_validate_glossary($id) {
        global $DB;
        $glossary = $DB->get_record('glossary', array('id' => $id), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($glossary, 'glossary');
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        return array($glossary, $context, $course, $cm);
    }

    /**
     * Describes the parameters for get_glossaries_by_courses.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_glossary_get_glossaries_by_courses_parameters() {
        return new external_function_parameters (
            array(
                'courseids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'course id'),
                    'Array of course IDs', VALUE_DEFAULT, array()
                ),
            )
        );
    }

    /**
     * Returns a list of glossaries in a provided list of courses.
     *
     * If no list is provided all glossaries that the user can view will be returned.
     *
     * @param array $courseids the course IDs.
     * @return array of glossaries
     * @since Moodle 3.1
     */
    public static function mod_glossary_get_glossaries_by_courses($courseids = array()) {
        $params = self::validate_parameters(self::mod_glossary_get_glossaries_by_courses_parameters(), array('courseids' => $courseids));

        $warnings = array();
        $courses = array();
        $courseids = $params['courseids'];

        if (empty($courseids)) {
            $courses = enrol_get_my_courses();
            $courseids = array_keys($courses);
        }

        // Array to store the glossaries to return.
        $glossaries = array();
        $modes = array();

        // Ensure there are courseids to loop through.
        if (!empty($courseids)) {
            list($courses, $warnings) = external_util::validate_courses($courseids, $courses);

            // Get the glossaries in these courses, this function checks users visibility permissions.
            $glossaries = get_all_instances_in_courses('glossary', $courses);
            foreach ($glossaries as $glossary) {
                $context = context_module::instance($glossary->coursemodule);
                $glossary->name = external_format_string($glossary->name, $context->id);
                list($glossary->intro, $glossary->introformat) = external_format_text($glossary->intro, $glossary->introformat,
                    $context->id, 'mod_glossary', 'intro', null);

                // Make sure we have a number of entries per page.
                if (!$glossary->entbypage) {
                    $glossary->entbypage = $CFG->glossary_entbypage;
                }

                // Add the list of browsing modes.
                if (!isset($modes[$glossary->displayformat])) {
                    $modes[$glossary->displayformat] = self::mod_glossary_get_browse_modes_from_display_format($glossary->displayformat);
                }
                $glossary->browsemodes = $modes[$glossary->displayformat];
            }
        }

        $result = array();
        $result['glossaries'] = $glossaries;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_glossaries_by_courses return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function mod_glossary_get_glossaries_by_courses_returns() {
        return new external_single_structure(array(
            'glossaries' => new external_multiple_structure(
                new external_single_structure(array(
                    'id' => new external_value(PARAM_INT, 'Glossary id'),
                    'coursemodule' => new external_value(PARAM_INT, 'Course module id'),
                    'course' => new external_value(PARAM_INT, 'Course id'),
                    'name' => new external_value(PARAM_RAW, 'Glossary name'),
                    'intro' => new external_value(PARAM_RAW, 'The Glossary intro'),
                    'introformat' => new external_format_value('intro'),
                    'allowduplicatedentries' => new external_value(PARAM_INT, 'If enabled, multiple entries can have the' .
                        ' same concept name'),
                    'displayformat' => new external_value(PARAM_TEXT, 'Display format type'),
                    'mainglossary' => new external_value(PARAM_INT, 'If enabled this glossary is a main glossary.'),
                    'showspecial' => new external_value(PARAM_INT, 'If enabled, participants can browse the glossary by' .
                        ' special characters, such as @ and #'),
                    'showalphabet' => new external_value(PARAM_INT, 'If enabled, participants can browse the glossary by' .
                        ' letters of the alphabet'),
                    'showall' => new external_value(PARAM_INT, 'If enabled, participants can browse all entries at once'),
                    'allowcomments' => new external_value(PARAM_INT, 'If enabled, all participants with permission to' .
                        ' create comments will be able to add comments to glossary entries'),
                    'allowprintview' => new external_value(PARAM_INT, 'If enabled, students are provided with a link to a' .
                        ' printer-friendly version of the glossary. The link is always available to teachers'),
                    'usedynalink' => new external_value(PARAM_INT, 'If site-wide glossary auto-linking has been enabled' .
                        ' by an administrator and this checkbox is ticked, the entry will be automatically linked' .
                        ' wherever the concept words and phrases appear throughout the rest of the course.'),
                    'defaultapproval' => new external_value(PARAM_INT, 'If set to no, entries require approving by a' .
                        ' teacher before they are viewable by everyone.'),
                    'approvaldisplayformat' => new external_value(PARAM_TEXT, 'When approving glossary items you may wish' .
                        ' to use a different display format'),
                    'globalglossary' => new external_value(PARAM_INT, ''),
                    'entbypage' => new external_value(PARAM_INT, 'Entries shown per page'),
                    'editalways' => new external_value(PARAM_INT, 'Always allow editing'),
                    'rsstype' => new external_value(PARAM_INT, 'To enable the RSS feed for this activity, select either' .
                        ' concepts with author or concepts without author to be included in the feed'),
                    'rssarticles' => new external_value(PARAM_INT, 'This setting specifies the number of glossary entry' .
                        ' concepts to include in the RSS feed. Between 5 and 20 generally acceptable'),
                    'assessed' => new external_value(PARAM_INT, 'Aggregate type'),
                    'assesstimestart' => new external_value(PARAM_INT, 'Restrict rating to items created after this'),
                    'assesstimefinish' => new external_value(PARAM_INT, 'Restrict rating to items created before this'),
                    'scale' => new external_value(PARAM_INT, 'Scale ID'),
                    'timecreated' => new external_value(PARAM_INT, 'Time created'),
                    'timemodified' => new external_value(PARAM_INT, 'Time modified'),
                    'completionentries' => new external_value(PARAM_INT, 'Number of entries to complete'),
                    'section' => new external_value(PARAM_INT, 'Section'),
                    'visible' => new external_value(PARAM_INT, 'Visible'),
                    'groupmode' => new external_value(PARAM_INT, 'Group mode'),
                    'groupingid' => new external_value(PARAM_INT, 'Grouping ID'),
                    'browsemodes' => new external_multiple_structure(
                        new external_value(PARAM_ALPHA, 'Modes of browsing allowed')
                    )
                ), 'Glossaries')
            ),
            'warnings' => new external_warnings())
        );
    }

    /**
     * Returns the description of the external function parameters.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_glossary_view_glossary_parameters() {
        return new external_function_parameters(array(
            'id' => new external_value(PARAM_INT, 'Glossary instance ID'),
            'mode' => new external_value(PARAM_ALPHA, 'The mode in which the glossary is viewed'),
        ));
    }

    /**
     * Notify that the course module was viewed.
     *
     * @param int $id The glossary instance ID.
     * @param string $mode The view mode.
     * @return array of warnings and status result
     * @since Moodle 3.1
     * @throws moodle_exception
     */
    public static function mod_glossary_view_glossary($id, $mode) {
        $params = self::validate_parameters(self::mod_glossary_view_glossary_parameters(), array(
            'id' => $id,
            'mode' => $mode
        ));
        $id = $params['id'];
        $mode = $params['mode'];
        $warnings = array();

        // Get and validate the glossary.
        list($glossary, $context, $course, $cm) = self::mod_glossary_validate_glossary($id);

        // Trigger module viewed event.
        glossary_view($glossary, $course, $cm, $context, $mode);

        return array(
            'status' => true,
            'warnings' => $warnings
        );
    }

    /**
     * Returns the description of the external function return value.
     *
     * @return external_description
     * @since Moodle 3.1
     */
    public static function mod_glossary_view_glossary_returns() {
        return new external_single_structure(array(
            'status' => new external_value(PARAM_BOOL, 'True on success'),
            'warnings' => new external_warnings()
        ));
    }

    /**
     * Returns the description of the external function parameters.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_glossary_view_entry_parameters() {
        return new external_function_parameters(array(
            'id' => new external_value(PARAM_INT, 'Glossary entry ID'),
        ));
    }

    /**
     * Notify that the entry was viewed.
     *
     * @param int $id The entry ID.
     * @return array of warnings and status result
     * @since Moodle 3.1
     * @throws moodle_exception
     * @throws invalid_parameter_exception
     */
    public static function mod_glossary_view_entry($id) {
        global $DB, $USER;

        $params = self::validate_parameters(self::mod_glossary_view_entry_parameters(), array('id' => $id));
        $id = $params['id'];
        $warnings = array();

        // Get and validate the glossary.
        $entry = $DB->get_record('glossary_entries', array('id' => $id), '*', MUST_EXIST);
        list($glossary, $context) = self::mod_glossary_validate_glossary($entry->glossaryid);

        if (empty($entry->approved) && $entry->userid != $USER->id && !has_capability('mod/glossary:approve', $context)) {
            throw new invalid_parameter_exception('invalidentry');
        }

        // Trigger view.
        glossary_entry_view($entry, $context);

        return array(
            'status' => true,
            'warnings' => $warnings
        );
    }

    /**
     * Returns the description of the external function return value.
     *
     * @return external_description
     * @since Moodle 3.1
     */
    public static function mod_glossary_view_entry_returns() {
        return new external_single_structure(array(
            'status' => new external_value(PARAM_BOOL, 'True on success'),
            'warnings' => new external_warnings()
        ));
    }

    /**
     * Returns the description of the external function parameters.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_glossary_get_entries_by_letter_parameters() {
        return new external_function_parameters(array(
            'id' => new external_value(PARAM_INT, 'Glossary entry ID'),
            'letter' => new external_value(PARAM_ALPHA, 'A letter, or either keywords: \'ALL\' or \'SPECIAL\'.'),
            'from' => new external_value(PARAM_INT, 'Start returning records from here', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Number of records to return', VALUE_DEFAULT, 20),
            'options' => new external_single_structure(array(
                'includenotapproved' => new external_value(PARAM_BOOL, 'When false, includes the non-approved entries created by' .
                    ' the user. When true, also includes the ones that the user has the permission to approve.', VALUE_DEFAULT, 0)
            ), 'An array of options', VALUE_DEFAULT, array())
        ));
    }

    /**
     * Browse a glossary entries by letter.
     *
     * @param int $id The glossary ID.
     * @param string $letter A letter, or a special keyword.
     * @param int $from Start returning records from here.
     * @param int $limit Number of records to return.
     * @param array $options Array of options.
     * @return array Containing count, entries and warnings.
     * @since Moodle 3.1
     * @throws moodle_exception
     * @throws invalid_parameter_exception
     */
    public static function mod_glossary_get_entries_by_letter($id, $letter, $from, $limit, $options) {
        $params = self::validate_parameters(self::mod_glossary_get_entries_by_letter_parameters(), array(
            'id' => $id,
            'letter' => $letter,
            'from' => $from,
            'limit' => $limit,
            'options' => $options,
        ));
        $id = $params['id'];
        $letter = $params['letter'];
        $from = $params['from'];
        $limit = $params['limit'];
        $options = $params['options'];
        $warnings = array();

        // Get and validate the glossary.
        list($glossary, $context) = self::mod_glossary_validate_glossary($id);

        // Validate the mode.
        $modes = self::mod_glossary_get_browse_modes_from_display_format($glossary->displayformat);
        if (!in_array('letter', $modes)) {
            throw new invalid_parameter_exception('invalidbrowsemode');
        }

        $entries = array();
        list($records, $count) = glossary_get_entries_by_letter($glossary, $context, $letter, $from, $limit, $options);
        foreach ($records as $key => $record) {
            self::mod_glossary_fill_entry_details($record, $context);
            $entries[] = $record;
        }

        return array(
            'count' => $count,
            'entries' => $entries,
            'warnings' => $warnings
        );
    }

    /**
     * Returns the description of the external function return value.
     *
     * @return external_description
     * @since Moodle 3.1
     */
    public static function mod_glossary_get_entries_by_letter_returns() {
        return new external_single_structure(array(
            'count' => new external_value(PARAM_INT, 'The total number of records matching the request.'),
            'entries' => new external_multiple_structure(
                self::mod_glossary_get_entry_return_structure()
            ),
            'warnings' => new external_warnings()
        ));
    }

    /**
     * Returns the description of the external function parameters.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_glossary_get_entries_by_date_parameters() {
        return new external_function_parameters(array(
            'id' => new external_value(PARAM_INT, 'Glossary entry ID'),
            'order' => new external_value(PARAM_ALPHA, 'Order the records by: \'CREATION\' or \'UPDATE\'.',
                VALUE_DEFAULT, 'UPDATE'),
            'sort' => new external_value(PARAM_ALPHA, 'The direction of the order: \'ASC\' or \'DESC\'', VALUE_DEFAULT, 'DESC'),
            'from' => new external_value(PARAM_INT, 'Start returning records from here', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Number of records to return', VALUE_DEFAULT, 20),
            'options' => new external_single_structure(array(
                'includenotapproved' => new external_value(PARAM_BOOL, 'When false, includes the non-approved entries created by' .
                    ' the user. When true, also includes the ones that the user has the permission to approve.', VALUE_DEFAULT, 0)
            ), 'An array of options', VALUE_DEFAULT, array())
        ));
    }

    /**
     * Browse a glossary entries by date.
     *
     * @param int $id The glossary ID.
     * @param string $order The way to order the records.
     * @param string $sort The direction of the order.
     * @param int $from Start returning records from here.
     * @param int $limit Number of records to return.
     * @param array $options Array of options.
     * @return array Containing count, entries and warnings.
     * @since Moodle 3.1
     * @throws moodle_exception
     * @throws invalid_parameter_exception
     */
    public static function mod_glossary_get_entries_by_date($id, $order, $sort, $from, $limit, $options) {
        $params = self::validate_parameters(self::mod_glossary_get_entries_by_date_parameters(), array(
            'id' => $id,
            'order' => core_text::strtoupper($order),
            'sort' => core_text::strtoupper($sort),
            'from' => $from,
            'limit' => $limit,
            'options' => $options,
        ));
        $id = $params['id'];
        $order = $params['order'];
        $sort = $params['sort'];
        $from = $params['from'];
        $limit = $params['limit'];
        $options = $params['options'];
        $warnings = array();

        if (!in_array($order, array('CREATION', 'UPDATE'))) {
            throw new invalid_parameter_exception('invalidorder');
        } else if (!in_array($sort, array('ASC', 'DESC'))) {
            throw new invalid_parameter_exception('invalidsort');
        }

        // Get and validate the glossary.
        list($glossary, $context) = self::mod_glossary_validate_glossary($id);

        // Validate the mode.
        $modes = self::mod_glossary_get_browse_modes_from_display_format($glossary->displayformat);
        if (!in_array('date', $modes)) {
            throw new invalid_parameter_exception('invalidbrowsemode');
        }

        $entries = array();
        list($records, $count) = glossary_get_entries_by_date($glossary, $context, $order, $sort, $from, $limit, $options);
        foreach ($records as $key => $record) {
            self::mod_glossary_fill_entry_details($record, $context);
            $entries[] = $record;
        }

        return array(
            'count' => $count,
            'entries' => $entries,
            'warnings' => $warnings
        );
    }

    /**
     * Returns the description of the external function return value.
     *
     * @return external_description
     * @since Moodle 3.1
     */
    public static function mod_glossary_get_entries_by_date_returns() {
        return new external_single_structure(array(
            'count' => new external_value(PARAM_INT, 'The total number of records matching the request.'),
            'entries' => new external_multiple_structure(
                self::mod_glossary_get_entry_return_structure()
            ),
            'warnings' => new external_warnings()
        ));
    }

    /**
     * Returns the description of the external function parameters.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_glossary_get_categories_parameters() {
        return new external_function_parameters(array(
            'id' => new external_value(PARAM_INT, 'The glossary ID'),
            'from' => new external_value(PARAM_INT, 'Start returning records from here', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Number of records to return', VALUE_DEFAULT, 20)
        ));
    }

    /**
     * Get the categories of a glossary.
     *
     * @param int $id The glossary ID.
     * @param int $from Start returning records from here.
     * @param int $limit Number of records to return.
     * @return array Containing count, categories and warnings.
     * @since Moodle 3.1
     * @throws moodle_exception
     */
    public static function mod_glossary_get_categories($id, $from, $limit) {
        $params = self::validate_parameters(self::mod_glossary_get_categories_parameters(), array(
            'id' => $id,
            'from' => $from,
            'limit' => $limit
        ));
        $id = $params['id'];
        $from = $params['from'];
        $limit = $params['limit'];
        $warnings = array();

        // Get and validate the glossary.
        list($glossary, $context) = self::mod_glossary_validate_glossary($id);

        // Fetch the categories.
        $categories = array();
        list($records, $count) = glossary_get_categories($glossary, $from, $limit);
        foreach ($records as $category) {
            $category->name = external_format_string($category->name, $context->id);
            $categories[] = $category;
        }

        return array(
            'count' => $count,
            'categories' => $categories,
            'warnings' => array(),
        );
    }

    /**
     * Returns the description of the external function return value.
     *
     * @return external_description
     * @since Moodle 3.1
     */
    public static function mod_glossary_get_categories_returns() {
        return new external_single_structure(array(
            'count' => new external_value(PARAM_INT, 'The total number of records.'),
            'categories' => new external_multiple_structure(
                new external_single_structure(array(
                    'id' => new external_value(PARAM_INT, 'The category ID'),
                    'glossaryid' => new external_value(PARAM_INT, 'The glossary ID'),
                    'name' => new external_value(PARAM_RAW, 'The name of the category'),
                    'usedynalink' => new external_value(PARAM_BOOL, 'Whether the category is automatically linked'),
                ))
            ),
            'warnings' => new external_warnings()
        ));
    }

    /**
     * Returns the description of the external function parameters.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_glossary_get_entries_by_category_parameters() {
        return new external_function_parameters(array(
            'id' => new external_value(PARAM_INT, 'The glossary ID.'),
            'categoryid' => new external_value(PARAM_INT, 'The category ID. Use \'' . GLOSSARY_SHOW_ALL_CATEGORIES . '\' for all' .
                ' categories, or \'' . GLOSSARY_SHOW_NOT_CATEGORISED . '\' for uncategorised entries.'),
            'from' => new external_value(PARAM_INT, 'Start returning records from here', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Number of records to return', VALUE_DEFAULT, 20),
            'options' => new external_single_structure(array(
                'includenotapproved' => new external_value(PARAM_BOOL, 'When false, includes the non-approved entries created by' .
                    ' the user. When true, also includes the ones that the user has the permission to approve.', VALUE_DEFAULT, 0)
            ), 'An array of options', VALUE_DEFAULT, array())
        ));
    }

    /**
     * Browse a glossary entries by category.
     *
     * @param int $id The glossary ID.
     * @param int $categoryid The category ID.
     * @param int $from Start returning records from here.
     * @param int $limit Number of records to return.
     * @param array $options Array of options.
     * @return array Containing count, entries and warnings.
     * @since Moodle 3.1
     * @throws moodle_exception
     * @throws invalid_parameter_exception
     */
    public static function mod_glossary_get_entries_by_category($id, $categoryid, $from, $limit, $options) {
        global $DB;

        $params = self::validate_parameters(self::mod_glossary_get_entries_by_category_parameters(), array(
            'id' => $id,
            'categoryid' => $categoryid,
            'from' => $from,
            'limit' => $limit,
            'options' => $options,
        ));
        $id = $params['id'];
        $categoryid = $params['categoryid'];
        $from = $params['from'];
        $limit = $params['limit'];
        $options = $params['options'];
        $warnings = array();

        // Get and validate the glossary.
        list($glossary, $context) = self::mod_glossary_validate_glossary($id);

        // Validate the mode.
        $modes = self::mod_glossary_get_browse_modes_from_display_format($glossary->displayformat);
        if (!in_array('cat', $modes)) {
            throw new invalid_parameter_exception('invalidbrowsemode');
        }

        // Validate the category.
        if (in_array($categoryid, array(GLOSSARY_SHOW_ALL_CATEGORIES, GLOSSARY_SHOW_NOT_CATEGORISED))) {
            // All good.
        } else if (!$DB->record_exists('glossary_categories', array('id' => $categoryid, 'glossaryid' => $id))) {
            throw new invalid_parameter_exception('invalidcategory');
        }

        // Fetching the entries.
        $entries = array();
        list($records, $count) = glossary_get_entries_by_category($glossary, $context, $categoryid, $from, $limit, $options);
        foreach ($records as $key => $record) {
            self::mod_glossary_fill_entry_details($record, $context);
            if ($record->categoryid === null) {
                $record->categoryid = GLOSSARY_SHOW_NOT_CATEGORISED;
            }
            if (isset($record->categoryname)) {
                $record->categoryname = external_format_string($record->categoryname, $context->id);
            }
            $entries[] = $record;
        }

        return array(
            'count' => $count,
            'entries' => $entries,
            'warnings' => $warnings
        );
    }

    /**
     * Returns the description of the external function return value.
     *
     * @return external_description
     * @since Moodle 3.1
     */
    public static function mod_glossary_get_entries_by_category_returns() {
        return new external_single_structure(array(
            'count' => new external_value(PARAM_INT, 'The total number of records matching the request.'),
            'entries' => new external_multiple_structure(
                self::mod_glossary_get_entry_return_structure(true)
            ),
            'warnings' => new external_warnings()
        ));
    }

    /**
     * Returns the description of the external function parameters.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_glossary_get_authors_parameters() {
        return new external_function_parameters(array(
            'id' => new external_value(PARAM_INT, 'Glossary entry ID'),
            'from' => new external_value(PARAM_INT, 'Start returning records from here', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Number of records to return', VALUE_DEFAULT, 20),
            'options' => new external_single_structure(array(
                'includenotapproved' => new external_value(PARAM_BOOL, 'When false, includes self even if all of their entries' .
                    ' require approval. When true, also includes authors only having entries pending approval.', VALUE_DEFAULT, 0)
            ), 'An array of options', VALUE_DEFAULT, array())
        ));
    }

    /**
     * Get the authors of a glossary.
     *
     * @param int $id The glossary ID.
     * @param int $from Start returning records from here.
     * @param int $limit Number of records to return.
     * @param array $options Array of options.
     * @return array Containing count, authors and warnings.
     * @since Moodle 3.1
     * @throws moodle_exception
     */
    public static function mod_glossary_get_authors($id, $from, $limit, $options) {
        global $PAGE;

        $params = self::validate_parameters(self::mod_glossary_get_authors_parameters(), array(
            'id' => $id,
            'from' => $from,
            'limit' => $limit,
            'options' => $options,
        ));
        $id = $params['id'];
        $from = $params['from'];
        $limit = $params['limit'];
        $options = $params['options'];
        $warnings = array();

        // Get and validate the glossary.
        list($glossary, $context) = self::mod_glossary_validate_glossary($id);

        // Fetching the entries.
        list($users, $count) = glossary_get_authors($glossary, $context, $limit, $from, $options);

        $canviewfullnames = has_capability('moodle/site:viewfullnames', $context);
        foreach ($users as $user) {
            $userpicture = new user_picture($user);
            $userpicture->size = 1;

            $author = new stdClass();
            $author->id = $user->id;
            $author->fullname = fullname($user, $canviewfullnames);
            $author->pictureurl = $userpicture->get_url($PAGE)->out(false);
            $authors[] = $author;
        }
        $users->close();

        return array(
            'count' => $count,
            'authors' => $authors,
            'warnings' => array(),
        );
    }

    /**
     * Returns the description of the external function return value.
     *
     * @return external_description
     * @since Moodle 3.1
     */
    public static function mod_glossary_get_authors_returns() {
        return new external_single_structure(array(
            'count' => new external_value(PARAM_INT, 'The total number of records.'),
            'authors' => new external_multiple_structure(
                new external_single_structure(array(
                    'id' => new external_value(PARAM_INT, 'The user ID'),
                    'fullname' => new external_value(PARAM_NOTAGS, 'The fullname'),
                    'pictureurl' => new external_value(PARAM_URL, 'The picture URL'),
                ))
            ),
            'warnings' => new external_warnings()
        ));
    }

    /**
     * Returns the description of the external function parameters.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_glossary_get_entries_by_author_parameters() {
        return new external_function_parameters(array(
            'id' => new external_value(PARAM_INT, 'Glossary entry ID'),
            'letter' => new external_value(PARAM_ALPHA, 'First letter of firstname or lastname, or either keywords:'
                . ' \'ALL\' or \'SPECIAL\'.'),
            'field' => new external_value(PARAM_ALPHA, 'Search and order using: \'FIRSTNAME\' or \'LASTNAME\'', VALUE_DEFAULT,
                'LASTNAME'),
            'sort' => new external_value(PARAM_ALPHA, 'The direction of the order: \'ASC\' or \'DESC\'', VALUE_DEFAULT, 'ASC'),
            'from' => new external_value(PARAM_INT, 'Start returning records from here', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Number of records to return', VALUE_DEFAULT, 20),
            'options' => new external_single_structure(array(
                'includenotapproved' => new external_value(PARAM_BOOL, 'When false, includes the non-approved entries created by' .
                    ' the user. When true, also includes the ones that the user has the permission to approve.', VALUE_DEFAULT, 0)
            ), 'An array of options', VALUE_DEFAULT, array())
        ));
    }

    /**
     * Browse a glossary entries by author.
     *
     * @param int $id The glossary ID.
     * @param string $letter A letter, or a special keyword.
     * @param string $field The field to search from.
     * @param string $sort The direction of the order.
     * @param int $from Start returning records from here.
     * @param int $limit Number of records to return.
     * @param array $options Array of options.
     * @return array Containing count, entries and warnings.
     * @since Moodle 3.1
     * @throws moodle_exception
     * @throws invalid_parameter_exception
     */
    public static function mod_glossary_get_entries_by_author($id, $letter, $field, $sort, $from, $limit, $options) {
        $params = self::validate_parameters(self::mod_glossary_get_entries_by_author_parameters(), array(
            'id' => $id,
            'letter' => $letter,
            'field' => core_text::strtoupper($field),
            'sort' => core_text::strtoupper($sort),
            'from' => $from,
            'limit' => $limit,
            'options' => $options,
        ));
        $id = $params['id'];
        $letter = $params['letter'];
        $field = $params['field'];
        $sort = $params['sort'];
        $from = $params['from'];
        $limit = $params['limit'];
        $options = $params['options'];
        $warnings = array();

        if (!in_array($field, array('FIRSTNAME', 'LASTNAME'))) {
            throw new invalid_parameter_exception('invalidfield');
        } else if (!in_array($sort, array('ASC', 'DESC'))) {
            throw new invalid_parameter_exception('invalidsort');
        }

        // Get and validate the glossary.
        list($glossary, $context) = self::mod_glossary_validate_glossary($id);

        // Validate the mode.
        $modes = self::mod_glossary_get_browse_modes_from_display_format($glossary->displayformat);
        if (!in_array('author', $modes)) {
            throw new invalid_parameter_exception('invalidbrowsemode');
        }

        // Fetching the entries.
        $entries = array();
        list($records, $count) = glossary_get_entries_by_author($glossary, $context, $letter, $field, $sort, $from, $limit,
            $options);
        foreach ($records as $key => $record) {
            self::mod_glossary_fill_entry_details($record, $context);
            $entries[] = $record;
        }

        return array(
            'count' => $count,
            'entries' => $entries,
            'warnings' => $warnings
        );
    }

    /**
     * Returns the description of the external function return value.
     *
     * @return external_description
     * @since Moodle 3.1
     */
    public static function mod_glossary_get_entries_by_author_returns() {
        return new external_single_structure(array(
            'count' => new external_value(PARAM_INT, 'The total number of records matching the request.'),
            'entries' => new external_multiple_structure(
                self::mod_glossary_get_entry_return_structure()
            ),
            'warnings' => new external_warnings()
        ));
    }

    /**
     * Returns the description of the external function parameters.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_glossary_get_entries_by_author_id_parameters() {
        return new external_function_parameters(array(
            'id' => new external_value(PARAM_INT, 'Glossary entry ID'),
            'authorid' => new external_value(PARAM_INT, 'The author ID'),
            'order' => new external_value(PARAM_ALPHA, 'Order by: \'CONCEPT\', \'CREATION\' or \'UPDATE\'', VALUE_DEFAULT,
                'CONCEPT'),
            'sort' => new external_value(PARAM_ALPHA, 'The direction of the order: \'ASC\' or \'DESC\'', VALUE_DEFAULT, 'ASC'),
            'from' => new external_value(PARAM_INT, 'Start returning records from here', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Number of records to return', VALUE_DEFAULT, 20),
            'options' => new external_single_structure(array(
                'includenotapproved' => new external_value(PARAM_BOOL, 'When false, includes the non-approved entries created by' .
                    ' the user. When true, also includes the ones that the user has the permission to approve.', VALUE_DEFAULT, 0)
            ), 'An array of options', VALUE_DEFAULT, array())
        ));
    }

    /**
     * Browse a glossary entries by author.
     *
     * @param int $id The glossary ID.
     * @param int $authorid The author ID.
     * @param string $order The way to order the results.
     * @param string $sort The direction of the order.
     * @param int $from Start returning records from here.
     * @param int $limit Number of records to return.
     * @param array $options Array of options.
     * @return array Containing count, entries and warnings.
     * @since Moodle 3.1
     * @throws moodle_exception
     * @throws invalid_parameter_exception
     */
    public static function mod_glossary_get_entries_by_author_id($id, $authorid, $order, $sort, $from, $limit, $options) {
        $params = self::validate_parameters(self::mod_glossary_get_entries_by_author_id_parameters(), array(
            'id' => $id,
            'authorid' => $authorid,
            'order' => core_text::strtoupper($order),
            'sort' => core_text::strtoupper($sort),
            'from' => $from,
            'limit' => $limit,
            'options' => $options,
        ));
        $id = $params['id'];
        $authorid = $params['authorid'];
        $order = $params['order'];
        $sort = $params['sort'];
        $from = $params['from'];
        $limit = $params['limit'];
        $options = $params['options'];
        $warnings = array();

        if (!in_array($order, array('CONCEPT', 'CREATION', 'UPDATE'))) {
            throw new invalid_parameter_exception('invalidorder');
        } else if (!in_array($sort, array('ASC', 'DESC'))) {
            throw new invalid_parameter_exception('invalidsort');
        }

        // Get and validate the glossary.
        list($glossary, $context) = self::mod_glossary_validate_glossary($id);

        // Validate the mode.
        $modes = self::mod_glossary_get_browse_modes_from_display_format($glossary->displayformat);
        if (!in_array('author', $modes)) {
            throw new invalid_parameter_exception('invalidbrowsemode');
        }

        // Fetching the entries.
        $entries = array();
        list($records, $count) = glossary_get_entries_by_author_id($glossary, $context, $authorid, $order, $sort, $from,
            $limit, $options);
        foreach ($records as $key => $record) {
            self::mod_glossary_fill_entry_details($record, $context);
            $entries[] = $record;
        }

        return array(
            'count' => $count,
            'entries' => $entries,
            'warnings' => $warnings
        );
    }

    /**
     * Returns the description of the external function return value.
     *
     * @return external_description
     * @since Moodle 3.1
     */
    public static function mod_glossary_get_entries_by_author_id_returns() {
        return new external_single_structure(array(
            'count' => new external_value(PARAM_INT, 'The total number of records matching the request.'),
            'entries' => new external_multiple_structure(
                self::mod_glossary_get_entry_return_structure()
            ),
            'warnings' => new external_warnings()
        ));
    }

    /**
     * Returns the description of the external function parameters.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_glossary_get_entries_by_search_parameters() {
        return new external_function_parameters(array(
            'id' => new external_value(PARAM_INT, 'Glossary entry ID'),
            'query' => new external_value(PARAM_NOTAGS, 'The query string'),
            'fullsearch' => new external_value(PARAM_BOOL, 'The query', VALUE_DEFAULT, 1),
            'order' => new external_value(PARAM_ALPHA, 'Order by: \'CONCEPT\', \'CREATION\' or \'UPDATE\'', VALUE_DEFAULT,
                'CONCEPT'),
            'sort' => new external_value(PARAM_ALPHA, 'The direction of the order: \'ASC\' or \'DESC\'', VALUE_DEFAULT, 'ASC'),
            'from' => new external_value(PARAM_INT, 'Start returning records from here', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Number of records to return', VALUE_DEFAULT, 20),
            'options' => new external_single_structure(array(
                'includenotapproved' => new external_value(PARAM_BOOL, 'When false, includes the non-approved entries created by' .
                    ' the user. When true, also includes the ones that the user has the permission to approve.', VALUE_DEFAULT, 0)
            ), 'An array of options', VALUE_DEFAULT, array())
        ));
    }

    /**
     * Browse a glossary entries using the search.
     *
     * @param int $id The glossary ID.
     * @param string $query The search query.
     * @param bool $fullsearch Whether or not full search is required.
     * @param string $order The way to order the results.
     * @param string $sort The direction of the order.
     * @param int $from Start returning records from here.
     * @param int $limit Number of records to return.
     * @param array $options Array of options.
     * @return array Containing count, entries and warnings.
     * @since Moodle 3.1
     * @throws moodle_exception
     * @throws invalid_parameter_exception
     */
    public static function mod_glossary_get_entries_by_search($id, $query, $fullsearch, $order, $sort, $from, $limit, $options) {
        $params = self::validate_parameters(self::mod_glossary_get_entries_by_search_parameters(), array(
            'id' => $id,
            'query' => $query,
            'fullsearch' => $fullsearch,
            'order' => core_text::strtoupper($order),
            'sort' => core_text::strtoupper($sort),
            'from' => $from,
            'limit' => $limit,
            'options' => $options,
        ));
        $id = $params['id'];
        $query = $params['query'];
        $fullsearch = $params['fullsearch'];
        $order = $params['order'];
        $sort = $params['sort'];
        $from = $params['from'];
        $limit = $params['limit'];
        $options = $params['options'];
        $warnings = array();

        if (!in_array($order, array('CONCEPT', 'CREATION', 'UPDATE'))) {
            throw new invalid_parameter_exception('invalidorder');
        } else if (!in_array($sort, array('ASC', 'DESC'))) {
            throw new invalid_parameter_exception('invalidsort');
        }

        // Get and validate the glossary.
        list($glossary, $context) = self::mod_glossary_validate_glossary($id);

        // Fetching the entries.
        $entries = array();
        list($records, $count) = glossary_get_entries_by_search($glossary, $context, $query, $fullsearch, $order, $sort, $from,
            $limit, $options);
        foreach ($records as $key => $record) {
            self::mod_glossary_fill_entry_details($record, $context);
            $entries[] = $record;
        }

        return array(
            'count' => $count,
            'entries' => $entries,
            'warnings' => $warnings
        );
    }

    /**
     * Returns the description of the external function return value.
     *
     * @return external_description
     * @since Moodle 3.1
     */
    public static function mod_glossary_get_entries_by_search_returns() {
        return new external_single_structure(array(
            'count' => new external_value(PARAM_INT, 'The total number of records matching the request.'),
            'entries' => new external_multiple_structure(
                self::mod_glossary_get_entry_return_structure()
            ),
            'warnings' => new external_warnings()
        ));
    }

    /**
     * Returns the description of the external function parameters.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_glossary_get_entries_by_term_parameters() {
        return new external_function_parameters(array(
            'id' => new external_value(PARAM_INT, 'Glossary entry ID'),
            'term' => new external_value(PARAM_NOTAGS, 'The entry concept, or alias'),
            'from' => new external_value(PARAM_INT, 'Start returning records from here', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Number of records to return', VALUE_DEFAULT, 20),
            'options' => new external_single_structure(array(
                'includenotapproved' => new external_value(PARAM_BOOL, 'When false, includes the non-approved entries created by' .
                    ' the user. When true, also includes the ones that the user has the permission to approve.', VALUE_DEFAULT, 0)
            ), 'An array of options', VALUE_DEFAULT, array())
        ));
    }

    /**
     * Browse a glossary entries using a term matching the concept or alias.
     *
     * @param int $id The glossary ID.
     * @param string $term The term.
     * @param int $from Start returning records from here.
     * @param int $limit Number of records to return.
     * @param array $options Array of options.
     * @return array Containing count, entries and warnings.
     * @since Moodle 3.1
     * @throws moodle_exception
     */
    public static function mod_glossary_get_entries_by_term($id, $term, $from, $limit, $options) {
        $params = self::validate_parameters(self::mod_glossary_get_entries_by_term_parameters(), array(
            'id' => $id,
            'term' => $term,
            'from' => $from,
            'limit' => $limit,
            'options' => $options,
        ));
        $id = $params['id'];
        $term = $params['term'];
        $from = $params['from'];
        $limit = $params['limit'];
        $options = $params['options'];
        $warnings = array();

        // Get and validate the glossary.
        list($glossary, $context) = self::mod_glossary_validate_glossary($id);

        // Fetching the entries.
        $entries = array();
        list($records, $count) = glossary_get_entries_by_term($glossary, $context, $term, $from, $limit, $options);
        foreach ($records as $key => $record) {
            self::mod_glossary_fill_entry_details($record, $context);
            $entries[] = $record;
        }

        return array(
            'count' => $count,
            'entries' => $entries,
            'warnings' => $warnings
        );
    }

    /**
     * Returns the description of the external function return value.
     *
     * @return external_description
     * @since Moodle 3.1
     */
    public static function mod_glossary_get_entries_by_term_returns() {
        return new external_single_structure(array(
            'count' => new external_value(PARAM_INT, 'The total number of records matching the request.'),
            'entries' => new external_multiple_structure(
                self::mod_glossary_get_entry_return_structure()
            ),
            'warnings' => new external_warnings()
        ));
    }

    /**
     * Returns the description of the external function parameters.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_glossary_get_entries_to_approve_parameters() {
        return new external_function_parameters(array(
            'id' => new external_value(PARAM_INT, 'Glossary entry ID'),
            'letter' => new external_value(PARAM_ALPHA, 'A letter, or either keywords: \'ALL\' or \'SPECIAL\'.'),
            'order' => new external_value(PARAM_ALPHA, 'Order by: \'CONCEPT\', \'CREATION\' or \'UPDATE\'', VALUE_DEFAULT,
                'CONCEPT'),
            'sort' => new external_value(PARAM_ALPHA, 'The direction of the order: \'ASC\' or \'DESC\'', VALUE_DEFAULT, 'ASC'),
            'from' => new external_value(PARAM_INT, 'Start returning records from here', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Number of records to return', VALUE_DEFAULT, 20),
            'options' => new external_single_structure(array(), 'An array of options', VALUE_DEFAULT, array())
        ));
    }

    /**
     * Browse a glossary entries using a term matching the concept or alias.
     *
     * @param int $id The glossary ID.
     * @param string $letter A letter, or a special keyword.
     * @param string $order The way to order the records.
     * @param string $sort The direction of the order.
     * @param int $from Start returning records from here.
     * @param int $limit Number of records to return.
     * @return array Containing count, entries and warnings.
     * @since Moodle 3.1
     * @throws moodle_exception
     */
    public static function mod_glossary_get_entries_to_approve($id, $letter, $order, $sort, $from, $limit) {
        $params = self::validate_parameters(self::mod_glossary_get_entries_to_approve_parameters(), array(
            'id' => $id,
            'letter' => $letter,
            'order' => $order,
            'sort' => $sort,
            'from' => $from,
            'limit' => $limit
        ));
        $id = $params['id'];
        $letter = $params['letter'];
        $order = $params['order'];
        $sort = $params['sort'];
        $from = $params['from'];
        $limit = $params['limit'];
        $warnings = array();

        // Get and validate the glossary.
        list($glossary, $context) = self::mod_glossary_validate_glossary($id);

        // Check the permissions.
        require_capability('mod/glossary:approve', $context);

        // Fetching the entries.
        $entries = array();
        list($records, $count) = glossary_get_entries_to_approve($glossary, $context, $letter, $order, $sort, $from, $limit);
        foreach ($records as $key => $record) {
            self::mod_glossary_fill_entry_details($record, $context);
            $entries[] = $record;
        }

        return array(
            'count' => $count,
            'entries' => $entries,
            'warnings' => $warnings
        );
    }

    /**
     * Returns the description of the external function return value.
     *
     * @return external_description
     * @since Moodle 3.1
     */
    public static function mod_glossary_get_entries_to_approve_returns() {
        return new external_single_structure(array(
            'count' => new external_value(PARAM_INT, 'The total number of records matching the request.'),
            'entries' => new external_multiple_structure(
                self::mod_glossary_get_entry_return_structure()
            ),
            'warnings' => new external_warnings()
        ));
    }

    /**
     * Returns the description of the external function parameters.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_glossary_get_entry_by_id_parameters() {
        return new external_function_parameters(array(
            'id' => new external_value(PARAM_INT, 'Glossary entry ID'),
        ));
    }

    /**
     * Get an entry.
     *
     * @param int $id The entry ID.
     * @return array Containing entry and warnings.
     * @since Moodle 3.1
     * @throws moodle_exception
     * @throws invalid_parameter_exception
     */
    public static function mod_glossary_get_entry_by_id($id) {
        global $DB, $USER;

        $params = self::validate_parameters(self::mod_glossary_get_entry_by_id_parameters(), array('id' => $id));
        $id = $params['id'];
        $warnings = array();

        // Get and validate the glossary.
        $entry = $DB->get_record('glossary_entries', array('id' => $id), '*', MUST_EXIST);
        list($glossary, $context) = self::mod_glossary_validate_glossary($entry->glossaryid);

        if (empty($entry->approved) && $entry->userid != $USER->id && !has_capability('mod/glossary:approve', $context)) {
            throw new invalid_parameter_exception('invalidentry');
        }

        $entry = glossary_get_entry_by_id($id);
        self::mod_glossary_fill_entry_details($entry, $context);

        return array(
            'entry' => $entry,
            'warnings' => $warnings
        );
    }

    /**
     * Returns the description of the external function return value.
     *
     * @return external_description
     * @since Moodle 3.1
     */
    public static function mod_glossary_get_entry_by_id_returns() {
        return new external_single_structure(array(
            'entry' => self::mod_glossary_get_entry_return_structure(),
            'warnings' => new external_warnings()
        ));
    }

    /**
     * Describes the parameters for get_wikis_by_courses.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_wiki_get_wikis_by_courses_parameters() {
        return new external_function_parameters (
            array(
                'courseids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'Course ID'), 'Array of course ids.', VALUE_DEFAULT, array()
                ),
            )
        );
    }

    /**
     * Returns a list of wikis in a provided list of courses,
     * if no list is provided all wikis that the user can view will be returned.
     *
     * @param array $courseids The courses IDs.
     * @return array Containing a list of warnings and a list of wikis.
     * @since Moodle 3.1
     */
    public static function mod_wiki_get_wikis_by_courses($courseids = array()) {

        $returnedwikis = array();
        $warnings = array();

        $params = self::validate_parameters(self::mod_wiki_get_wikis_by_courses_parameters(), array('courseids' => $courseids));

        $mycourses = array();
        if (empty($params['courseids'])) {
            $mycourses = enrol_get_my_courses();
            $params['courseids'] = array_keys($mycourses);
        }

        // Ensure there are courseids to loop through.
        if (!empty($params['courseids'])) {

            list($courses, $warnings) = external_util::validate_courses($params['courseids'], $mycourses);

            // Get the wikis in this course, this function checks users visibility permissions.
            // We can avoid then additional validate_context calls.
            $wikis = get_all_instances_in_courses('wiki', $courses);

            foreach ($wikis as $wiki) {

                $context = context_module::instance($wiki->coursemodule);

                // Entry to return.
                $module = array();

                // First, we return information that any user can see in (or can deduce from) the web interface.
                $module['id'] = $wiki->id;
                $module['coursemodule'] = $wiki->coursemodule;
                $module['course'] = $wiki->course;
                $module['name']  = external_format_string($wiki->name, $context->id);

                $viewablefields = [];
                if (has_capability('mod/wiki:viewpage', $context)) {
                    list($module['intro'], $module['introformat']) =
                        external_format_text($wiki->intro, $wiki->introformat, $context->id, 'mod_wiki', 'intro', $wiki->id);

                    $viewablefields = array('firstpagetitle', 'wikimode', 'defaultformat', 'forceformat', 'editbegin', 'editend',
                                            'section', 'visible', 'groupmode', 'groupingid');
                }

                // Check additional permissions for returning optional private settings.
                if (has_capability('moodle/course:manageactivities', $context)) {
                    $additionalfields = array('timecreated', 'timemodified');
                    $viewablefields = array_merge($viewablefields, $additionalfields);
                }

                foreach ($viewablefields as $field) {
                    $module[$field] = $wiki->{$field};
                }

                // Check if user can add new pages.
                $module['cancreatepages'] = wiki_can_create_pages($context);

                $returnedwikis[] = $module;
            }
        }

        $result = array();
        $result['wikis'] = $returnedwikis;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_wikis_by_courses return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function mod_wiki_get_wikis_by_courses_returns() {

        return new external_single_structure(
            array(
                'wikis' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Wiki ID.'),
                            'coursemodule' => new external_value(PARAM_INT, 'Course module ID.'),
                            'course' => new external_value(PARAM_INT, 'Course ID.'),
                            'name' => new external_value(PARAM_RAW, 'Wiki name.'),
                            'intro' => new external_value(PARAM_RAW, 'Wiki intro.', VALUE_OPTIONAL),
                            'introformat' => new external_format_value('Wiki intro format.', VALUE_OPTIONAL),
                            'timecreated' => new external_value(PARAM_INT, 'Time of creation.', VALUE_OPTIONAL),
                            'timemodified' => new external_value(PARAM_INT, 'Time of last modification.', VALUE_OPTIONAL),
                            'firstpagetitle' => new external_value(PARAM_RAW, 'First page title.', VALUE_OPTIONAL),
                            'wikimode' => new external_value(PARAM_TEXT, 'Wiki mode (individual, collaborative).', VALUE_OPTIONAL),
                            'defaultformat' => new external_value(PARAM_TEXT, 'Wiki\'s default format (html, creole, nwiki).',
                                                                            VALUE_OPTIONAL),
                            'forceformat' => new external_value(PARAM_INT, '1 if format is forced, 0 otherwise.',
                                                                            VALUE_OPTIONAL),
                            'editbegin' => new external_value(PARAM_INT, 'Edit begin.', VALUE_OPTIONAL),
                            'editend' => new external_value(PARAM_INT, 'Edit end.', VALUE_OPTIONAL),
                            'section' => new external_value(PARAM_INT, 'Course section ID.', VALUE_OPTIONAL),
                            'visible' => new external_value(PARAM_INT, '1 if visible, 0 otherwise.', VALUE_OPTIONAL),
                            'groupmode' => new external_value(PARAM_INT, 'Group mode.', VALUE_OPTIONAL),
                            'groupingid' => new external_value(PARAM_INT, 'Group ID.', VALUE_OPTIONAL),
                            'cancreatepages' => new external_value(PARAM_BOOL, 'True if user can create pages.'),
                        ), 'Wikis'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for view_wiki.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_wiki_view_wiki_parameters() {
        return new external_function_parameters (
            array(
                'wikiid' => new external_value(PARAM_INT, 'Wiki instance ID.')
            )
        );
    }

    /**
     * Trigger the course module viewed event and update the module completion status.
     *
     * @param int $wikiid The wiki instance ID.
     * @return array of warnings and status result.
     * @since Moodle 3.1
     */
    public static function mod_wiki_view_wiki($wikiid) {

        $params = self::validate_parameters(self::mod_wiki_view_wiki_parameters(),
                                            array(
                                                'wikiid' => $wikiid
                                            ));
        $warnings = array();

        // Get wiki instance.
        if (!$wiki = wiki_get_wiki($params['wikiid'])) {
            throw new moodle_exception('incorrectwikiid', 'wiki');
        }

        // Permission validation.
        list($course, $cm) = get_course_and_cm_from_instance($wiki, 'wiki');
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        // Check if user can view this wiki.
        // We don't use wiki_user_can_view because it requires to have a valid subwiki for the user.
        if (!has_capability('mod/wiki:viewpage', $context)) {
            throw new moodle_exception('cannotviewpage', 'wiki');
        }

        // Trigger course_module_viewed event and completion.
        wiki_view($wiki, $course, $cm, $context);

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the view_wiki return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function mod_wiki_view_wiki_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'Status: true if success.'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Describes the parameters for view_page.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_wiki_view_page_parameters() {
        return new external_function_parameters (
            array(
                'pageid' => new external_value(PARAM_INT, 'Wiki page ID.'),
            )
        );
    }

    /**
     * Trigger the page viewed event and update the module completion status.
     *
     * @param int $pageid The page ID.
     * @return array of warnings and status result.
     * @since Moodle 3.1
     * @throws moodle_exception if page is not valid.
     */
    public static function mod_wiki_view_page($pageid) {

        $params = self::validate_parameters(self::mod_wiki_view_page_parameters(),
                                            array(
                                                'pageid' => $pageid
                                            ));
        $warnings = array();

        // Get wiki page.
        if (!$page = wiki_get_page($params['pageid'])) {
            throw new moodle_exception('incorrectpageid', 'wiki');
        }

        // Get wiki instance.
        if (!$wiki = wiki_get_wiki_from_pageid($params['pageid'])) {
            throw new moodle_exception('incorrectwikiid', 'wiki');
        }

        // Permission validation.
        list($course, $cm) = get_course_and_cm_from_instance($wiki, 'wiki');
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        // Check if user can view this wiki.
        if (!$subwiki = wiki_get_subwiki($page->subwikiid)) {
            throw new moodle_exception('incorrectsubwikiid', 'wiki');
        }
        if (!wiki_user_can_view($subwiki, $wiki)) {
            throw new moodle_exception('cannotviewpage', 'wiki');
        }

        // Trigger page_viewed event and completion.
        wiki_page_view($wiki, $page, $course, $cm, $context);

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the view_page return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function mod_wiki_view_page_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'Status: true if success.'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Describes the parameters for get_subwikis.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_wiki_get_subwikis_parameters() {
        return new external_function_parameters (
            array(
                'wikiid' => new external_value(PARAM_INT, 'Wiki instance ID.')
            )
        );
    }

    /**
     * Returns the list of subwikis the user can see in a specific wiki.
     *
     * @param int $wikiid The wiki instance ID.
     * @return array Containing a list of warnings and a list of subwikis.
     * @since Moodle 3.1
     */
    public static function mod_wiki_get_subwikis($wikiid) {
        global $USER;

        $warnings = array();

        $params = self::validate_parameters(self::mod_wiki_get_subwikis_parameters(), array('wikiid' => $wikiid));

        // Get wiki instance.
        if (!$wiki = wiki_get_wiki($params['wikiid'])) {
            throw new moodle_exception('incorrectwikiid', 'wiki');
        }

        // Validate context and capabilities.
        list($course, $cm) = get_course_and_cm_from_instance($wiki, 'wiki');
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/wiki:viewpage', $context);

        $returnedsubwikis = wiki_get_visible_subwikis($wiki, $cm, $context);
        foreach ($returnedsubwikis as $subwiki) {
            $subwiki->canedit = wiki_user_can_edit($subwiki);
        }

        $result = array();
        $result['subwikis'] = $returnedsubwikis;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_subwikis return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function mod_wiki_get_subwikis_returns() {
        return new external_single_structure(
            array(
                'subwikis' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Subwiki ID.'),
                            'wikiid' => new external_value(PARAM_INT, 'Wiki ID.'),
                            'groupid' => new external_value(PARAM_RAW, 'Group ID.'),
                            'userid' => new external_value(PARAM_INT, 'User ID.'),
                            'canedit' => new external_value(PARAM_BOOL, 'True if user can edit the subwiki.'),
                        ), 'Subwikis'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_subwiki_pages.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_wiki_get_subwiki_pages_parameters() {
        return new external_function_parameters (
            array(
                'wikiid' => new external_value(PARAM_INT, 'Wiki instance ID.'),
                'groupid' => new external_value(PARAM_INT, 'Subwiki\'s group ID, -1 means current group. It will be ignored'
                                        . ' if the wiki doesn\'t use groups.', VALUE_DEFAULT, -1),
                'userid' => new external_value(PARAM_INT, 'Subwiki\'s user ID, 0 means current user. It will be ignored'
                                        .' in collaborative wikis.', VALUE_DEFAULT, 0),
                'options' => new external_single_structure(
                            array(
                                    'sortby' => new external_value(PARAM_ALPHA,
                                            'Field to sort by (id, title, ...).', VALUE_DEFAULT, 'title'),
                                    'sortdirection' => new external_value(PARAM_ALPHA,
                                            'Sort direction: ASC or DESC.', VALUE_DEFAULT, 'ASC'),
                                    'includecontent' => new external_value(PARAM_INT,
                                            'Include each page contents or not.', VALUE_DEFAULT, 1),
                            ), 'Options', VALUE_DEFAULT, array()),
            )
        );
    }

    /**
     * Returns the list of pages from a specific subwiki.
     *
     * @param int $wikiid The wiki instance ID.
     * @param int $groupid The group ID. If not defined, use current group.
     * @param int $userid The user ID. If not defined, use current user.
     * @param array $options Several options like sort by, sort direction, ...
     * @return array Containing a list of warnings and a list of pages.
     * @since Moodle 3.1
     */
    public static function mod_wiki_get_subwiki_pages($wikiid, $groupid = -1, $userid = 0, $options = array()) {
        global $USER, $DB;

        $returnedpages = array();
        $warnings = array();

        $params = self::validate_parameters(self::mod_wiki_get_subwiki_pages_parameters(),
                                            array(
                                                'wikiid' => $wikiid,
                                                'groupid' => $groupid,
                                                'userid' => $userid,
                                                'options' => $options
                                                )
            );

        // Get wiki instance.
        if (!$wiki = wiki_get_wiki($params['wikiid'])) {
            throw new moodle_exception('incorrectwikiid', 'wiki');
        }
        list($course, $cm) = get_course_and_cm_from_instance($wiki, 'wiki');
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        // Determine groupid and userid to use.
        list($groupid, $userid) = self::mod_wiki_determine_group_and_user($cm, $wiki, $params['groupid'], $params['userid']);

        // Get subwiki and validate it.
        $subwiki = self::mod_wiki_get_subwiki($cm, $wiki, $groupid, $userid);

        if ($subwiki->id != -1) {

            // Set sort param.
            $options = $params['options'];
            if (!empty($options['sortby'])) {
                if ($options['sortdirection'] != 'ASC' && $options['sortdirection'] != 'DESC') {
                    // Invalid sort direction. Use default.
                    $options['sortdirection'] = 'ASC';
                }
                $sort = $options['sortby'] . ' ' . $options['sortdirection'];
            }

            $pages = wiki_get_page_list($subwiki->id, $sort);
            $caneditpages = wiki_user_can_edit($subwiki);
            $firstpage = wiki_get_first_page($subwiki->id);

            foreach ($pages as $page) {
                $retpage = array(
                        'id' => $page->id,
                        'subwikiid' => $page->subwikiid,
                        'title' => external_format_string($page->title, $context->id),
                        'timecreated' => $page->timecreated,
                        'timemodified' => $page->timemodified,
                        'timerendered' => $page->timerendered,
                        'userid' => $page->userid,
                        'pageviews' => $page->pageviews,
                        'readonly' => $page->readonly,
                        'caneditpage' => $caneditpages,
                        'firstpage' => $page->id == $firstpage->id
                    );

                // Refresh page cached content if needed.
                if ($page->timerendered + WIKI_REFRESH_CACHE_TIME < time()) {
                    if ($content = wiki_refresh_cachedcontent($page)) {
                        $page = $content['page'];
                    }
                }
                list($cachedcontent, $contentformat) = external_format_text(
                            $page->cachedcontent, FORMAT_HTML, $context->id, 'mod_wiki', 'attachments', $subwiki->id);

                if ($options['includecontent']) {
                    // Return the page content.
                    $retpage['cachedcontent'] = $cachedcontent;
                    $retpage['contentformat'] = $contentformat;
                } else {
                    // Return the size of the content.
                    if (function_exists('mb_strlen') && ((int)ini_get('mbstring.func_overload') & 2)) {
                        $retpage['contentsize'] = mb_strlen($cachedcontent, '8bit');
                    } else {
                        $retpage['contentsize'] = strlen($cachedcontent);
                    }
                }

                $returnedpages[] = $retpage;
            }
        }

        $result = array();
        $result['pages'] = $returnedpages;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_subwiki_pages return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function mod_wiki_get_subwiki_pages_returns() {

        return new external_single_structure(
            array(
                'pages' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Page ID.'),
                            'subwikiid' => new external_value(PARAM_INT, 'Page\'s subwiki ID.'),
                            'title' => new external_value(PARAM_RAW, 'Page title.'),
                            'timecreated' => new external_value(PARAM_INT, 'Time of creation.'),
                            'timemodified' => new external_value(PARAM_INT, 'Time of last modification.'),
                            'timerendered' => new external_value(PARAM_INT, 'Time of last renderization.'),
                            'userid' => new external_value(PARAM_INT, 'ID of the user that last modified the page.'),
                            'pageviews' => new external_value(PARAM_INT, 'Number of times the page has been viewed.'),
                            'readonly' => new external_value(PARAM_INT, '1 if readonly, 0 otherwise.'),
                            'caneditpage' => new external_value(PARAM_BOOL, 'True if user can edit the page.'),
                            'firstpage' => new external_value(PARAM_BOOL, 'True if it\'s the first page.'),
                            'cachedcontent' => new external_value(PARAM_RAW, 'Page contents.', VALUE_OPTIONAL),
                            'contentformat' => new external_format_value('cachedcontent', VALUE_OPTIONAL),
                            'contentsize' => new external_value(PARAM_INT, 'Size of page contents in bytes (doesn\'t include'.
                                                                            ' size of attached files).', VALUE_OPTIONAL),
                        ), 'Pages'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_page_contents.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_wiki_get_page_contents_parameters() {
        return new external_function_parameters (
            array(
                'pageid' => new external_value(PARAM_INT, 'Page ID.')
            )
        );
    }

    /**
     * Get a page contents.
     *
     * @param int $pageid The page ID.
     * @return array of warnings and page data.
     * @since Moodle 3.1
     */
    public static function mod_wiki_get_page_contents($pageid) {

        $params = self::validate_parameters(self::mod_wiki_get_page_contents_parameters(),
                                            array(
                                                'pageid' => $pageid
                                            )
            );
        $warnings = array();

        // Get wiki page.
        if (!$page = wiki_get_page($params['pageid'])) {
            throw new moodle_exception('incorrectpageid', 'wiki');
        }

        // Get wiki instance.
        if (!$wiki = wiki_get_wiki_from_pageid($params['pageid'])) {
            throw new moodle_exception('incorrectwikiid', 'wiki');
        }

        // Permission validation.
        $cm = get_coursemodule_from_instance('wiki', $wiki->id, $wiki->course);
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        // Check if user can view this wiki.
        if (!$subwiki = wiki_get_subwiki($page->subwikiid)) {
            throw new moodle_exception('incorrectsubwikiid', 'wiki');
        }
        if (!wiki_user_can_view($subwiki, $wiki)) {
            throw new moodle_exception('cannotviewpage', 'wiki');
        }

        $returnedpage = array();
        $returnedpage['id'] = $page->id;
        $returnedpage['wikiid'] = $wiki->id;
        $returnedpage['subwikiid'] = $page->subwikiid;
        $returnedpage['groupid'] = $subwiki->groupid;
        $returnedpage['userid'] = $subwiki->userid;
        $returnedpage['title'] = $page->title;

        // Refresh page cached content if needed.
        if ($page->timerendered + WIKI_REFRESH_CACHE_TIME < time()) {
            if ($content = wiki_refresh_cachedcontent($page)) {
                $page = $content['page'];
            }
        }

        list($returnedpage['cachedcontent'], $returnedpage['contentformat']) = local_mobile_external_format_text(
                            $page->cachedcontent, FORMAT_HTML, $context->id, 'mod_wiki', 'attachments', $subwiki->id);
        $returnedpage['caneditpage'] = wiki_user_can_edit($subwiki);

        $result = array();
        $result['page'] = $returnedpage;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_page_contents return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function mod_wiki_get_page_contents_returns() {
        return new external_single_structure(
            array(
                'page' => new external_single_structure(
                    array(
                        'id' => new external_value(PARAM_INT, 'Page ID.'),
                        'wikiid' => new external_value(PARAM_INT, 'Page\'s wiki ID.'),
                        'subwikiid' => new external_value(PARAM_INT, 'Page\'s subwiki ID.'),
                        'groupid' => new external_value(PARAM_INT, 'Page\'s group ID.'),
                        'userid' => new external_value(PARAM_INT, 'Page\'s user ID.'),
                        'title' => new external_value(PARAM_RAW, 'Page title.'),
                        'cachedcontent' => new external_value(PARAM_RAW, 'Page contents.'),
                        'contentformat' => new external_format_value('cachedcontent', VALUE_OPTIONAL),
                        'caneditpage' => new external_value(PARAM_BOOL, 'True if user can edit the page.')
                    ), 'Page'
                ),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Describes the parameters for get_subwiki_files.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_wiki_get_subwiki_files_parameters() {
        return new external_function_parameters (
            array(
                'wikiid' => new external_value(PARAM_INT, 'Wiki instance ID.'),
                'groupid' => new external_value(PARAM_INT, 'Subwiki\'s group ID, -1 means current group. It will be ignored'
                                        . ' if the wiki doesn\'t use groups.', VALUE_DEFAULT, -1),
                'userid' => new external_value(PARAM_INT, 'Subwiki\'s user ID, 0 means current user. It will be ignored'
                                        .' in collaborative wikis.', VALUE_DEFAULT, 0)
            )
        );
    }

    /**
     * Returns the list of files from a specific subwiki.
     *
     * @param int $wikiid The wiki instance ID.
     * @param int $groupid The group ID. If not defined, use current group.
     * @param int $userid The user ID. If not defined, use current user.
     * @return array Containing a list of warnings and a list of files.
     * @since Moodle 3.1
     * @throws moodle_exception
     */
    public static function mod_wiki_get_subwiki_files($wikiid, $groupid = -1, $userid = 0) {

        $returnedfiles = array();
        $warnings = array();

        $params = self::validate_parameters(self::mod_wiki_get_subwiki_files_parameters(),
                                            array(
                                                'wikiid' => $wikiid,
                                                'groupid' => $groupid,
                                                'userid' => $userid
                                                )
            );

        // Get wiki instance.
        if (!$wiki = wiki_get_wiki($params['wikiid'])) {
            throw new moodle_exception('incorrectwikiid', 'wiki');
        }
        list($course, $cm) = get_course_and_cm_from_instance($wiki, 'wiki');
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        // Determine groupid and userid to use.
        list($groupid, $userid) = self::mod_wiki_determine_group_and_user($cm, $wiki, $params['groupid'], $params['userid']);

        // Get subwiki and validate it.
        $subwiki = self::mod_wiki_get_subwiki($cm, $wiki, $groupid, $userid, 'cannotviewfiles');

        // Get subwiki based on group and user.
        if ($subwiki->id != -1) {
            // The subwiki exists, let's get the files.
            $fs = get_file_storage();
            if ($files = $fs->get_area_files($context->id, 'mod_wiki', 'attachments', $subwiki->id, 'filename', false)) {
                foreach ($files as $file) {
                    $filename = $file->get_filename();
                    $fileurl = moodle_url::make_webservice_pluginfile_url(
                                    $context->id, 'mod_wiki', 'attachments', $subwiki->id, '/', $filename);

                    $returnedfiles[] = array(
                        'filename' => $filename,
                        'mimetype' => $file->get_mimetype(),
                        'fileurl'  => $fileurl->out(false),
                        'filepath' => $file->get_filepath(),
                        'filesize' => $file->get_filesize(),
                        'timemodified' => $file->get_timemodified()
                    );
                }
            }
        }

        $result = array();
        $result['files'] = $returnedfiles;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_subwiki_pages return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function mod_wiki_get_subwiki_files_returns() {

        return new external_single_structure(
            array(
                'files' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'filename' => new external_value(PARAM_FILE, 'File name.'),
                            'filepath' => new external_value(PARAM_PATH, 'File path.'),
                            'filesize' => new external_value(PARAM_INT, 'File size.'),
                            'fileurl' => new external_value(PARAM_URL, 'Downloadable file url.'),
                            'timemodified' => new external_value(PARAM_INT, 'Time modified.'),
                            'mimetype' => new external_value(PARAM_RAW, 'File mime type.'),
                        ), 'Files'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Utility function for determining the groupid and userid to use.
     *
     * @param stdClass $cm The course module.
     * @param stdClass $wiki The wiki.
     * @param int $groupid Group ID.
     * @param int $userid User ID.
     * @return array Array containing the courseid and userid.
     * @since  Moodle 3.1
     */
    protected static function mod_wiki_determine_group_and_user($cm, $wiki, $groupid, $userid) {
        global $USER;

        $groupmode = groups_get_activity_groupmode($cm);
        if ($groupmode == NOGROUPS) {
            $groupid = 0;
        } else if ($groupid == -1) {
            // Use current group.
            $groupid = groups_get_activity_group($cm);
            $groupid = !empty($groupid) ? $groupid : 0;
        }

        // Determine user.
        if ($wiki->wikimode == 'collaborative') {
            // Collaborative wikis don't use userid in subwikis.
            $userid = 0;
        } else if (empty($userid)) {
            // Use current user.
            $userid = $USER->id;
        }

        return array($groupid, $userid);
    }

    /**
     * Utility function for getting a subwiki by group and user, validating that the user can view it.
     * If the subwiki doesn't exists in DB yet it'll have id -1.
     *
     * @param stdClass $cm The course module.
     * @param stdClass $wiki The wiki.
     * @param int $groupid Group ID. 0 means the subwiki doesn't use groups.
     * @param int $userid User ID. 0 means the subwiki doesn't use users.
     * @param string $error Error to show if the user cannot view the subwiki. By default, 'cannotviewpage'.
     * @param string $errormodule Module to get the error message from. By default, 'wiki'.
     * @return stdClass Subwiki. If it doesn't exists in DB yet it'll have id -1.
     * @since  Moodle 3.1
     * @throws moodle_exception
     */
    protected static function mod_wiki_get_subwiki($cm, $wiki, $groupid, $userid, $error = 'cannotviewpage', $errormodule = 'wiki') {
        global $USER, $DB;

        // Get subwiki based on group and user.
        if (!$subwiki = wiki_get_subwiki_by_group($cm->instance, $groupid, $userid)) {

            // The subwiki doesn't exist.
            // Validate if user is valid.
            if ($userid != 0) {
                $user = core_user::get_user($userid, '*', MUST_EXIST);
                core_user::require_active_user($user);
            }

            // Validate that groupid is valid.
            if ($groupid != 0 && !groups_group_exists($groupid)) {
                throw new moodle_exception('cannotfindgroup', 'error');
            }

            // Valid data but subwiki not found. We'll simulate a subwiki object to check if the user would be able to see it
            // if it existed. If he's able to see it then we'll return an empty array because the subwiki has no pages.
            $subwiki = new stdClass();
            $subwiki->id = -1;
            $subwiki->wikiid = $wiki->id;
            $subwiki->userid = $userid;
            $subwiki->groupid = $groupid;
        }

        // Check that the user can view the subwiki. This function checks capabilities.
        if (!wiki_user_can_view($subwiki, $wiki)) {
            throw new moodle_exception($error, $errormodule);
        }

        return $subwiki;
    }

    /**
     * Describes the parameters for mod_wiki_get_page_for_editing.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_wiki_get_page_for_editing_parameters() {
        return new external_function_parameters (
            array(
                'pageid' => new external_value(PARAM_INT, 'Page ID to edit.'),
                'section' => new external_value(PARAM_TEXT, 'Section page title.', VALUE_DEFAULT, null)
            )
        );
    }

    /**
     * Locks and retrieves info of page-section to be edited.
     *
     * @param int $pageid The page ID.
     * @param string $section Section page title.
     * @return array of warnings and page data.
     * @since Moodle 3.1
     */
    public static function mod_wiki_get_page_for_editing($pageid, $section = null) {
        global $USER;

        $params = self::validate_parameters(self::mod_wiki_get_page_for_editing_parameters(),
                                            array(
                                                'pageid' => $pageid,
                                                'section' => $section
                                            )
            );

        $warnings = array();

        // Get wiki page.
        if (!$page = wiki_get_page($params['pageid'])) {
            throw new moodle_exception('incorrectpageid', 'wiki');
        }

        // Get wiki instance.
        if (!$wiki = wiki_get_wiki_from_pageid($params['pageid'])) {
            throw new moodle_exception('incorrectwikiid', 'wiki');
        }

        // Get subwiki instance.
        if (!$subwiki = wiki_get_subwiki($page->subwikiid)) {
            throw new moodle_exception('incorrectsubwikiid', 'wiki');
        }

        // Permission validation.
        $cm = get_coursemodule_from_instance('wiki', $wiki->id, $wiki->course);
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        if (!wiki_user_can_edit($subwiki)) {
            throw new moodle_exception('cannoteditpage', 'wiki');
        }

        if (!wiki_set_lock($params['pageid'], $USER->id, $params['section'], true)) {
            throw new moodle_exception('pageislocked', 'wiki');
        }

        $version = wiki_get_current_version($page->id);
        if (empty($version)) {
            throw new moodle_exception('versionerror', 'wiki');
        }

        if (!is_null($params['section'])) {
            $content = wiki_parser_proxy::get_section($version->content, $version->contentformat, $params['section']);
        } else {
            $content = $version->content;
        }

        $pagesection = array();
        $pagesection['content'] = $content;
        $pagesection['contentformat'] = $version->contentformat;
        $pagesection['version'] = $version->version;

        $result = array();
        $result['pagesection'] = $pagesection;
        $result['warnings'] = $warnings;
        return $result;

    }

    /**
     * Describes the mod_wiki_get_page_for_editing return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function mod_wiki_get_page_for_editing_returns() {
        return new external_single_structure(
            array(
                'pagesection' => new external_single_structure(
                    array(
                        'content' => new external_value(PARAM_RAW, 'The contents of the page-section to be edited.'),
                        'contentformat' => new external_value(PARAM_TEXT, 'Format of the original content of the page.'),
                        'version' => new external_value(PARAM_INT, 'Latest version of the page.'),
                        'warnings' => new external_warnings()
                    )
                )
            )
        );
    }

    /**
     * Describes the parameters for mod_wiki_new_page.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_wiki_new_page_parameters() {
        return new external_function_parameters (
            array(
                'title' => new external_value(PARAM_TEXT, 'New page title.'),
                'content' => new external_value(PARAM_RAW, 'Page contents.'),
                'contentformat' => new external_value(PARAM_TEXT, 'Page contents format. If an invalid format is provided, default
                    wiki format is used.', VALUE_DEFAULT, null),
                'subwikiid' => new external_value(PARAM_INT, 'Page\'s subwiki ID.', VALUE_DEFAULT, null),
                'wikiid' => new external_value(PARAM_INT, 'Page\'s wiki ID. Used if subwiki does not exists.', VALUE_DEFAULT,
                    null),
                'userid' => new external_value(PARAM_INT, 'Subwiki\'s user ID. Used if subwiki does not exists.', VALUE_DEFAULT,
                    null),
                'groupid' => new external_value(PARAM_INT, 'Subwiki\'s group ID. Used if subwiki does not exists.', VALUE_DEFAULT,
                    null)
            )
        );
    }

    /**
     * Creates a new page.
     *
     * @param string $title New page title.
     * @param string $content Page contents.
     * @param int $contentformat Page contents format. If an invalid format is provided, default wiki format is used.
     * @param int $subwikiid The Subwiki ID where to store the page.
     * @param int $wikiid Page\'s wiki ID. Used if subwiki does not exists.
     * @param int $userid Subwiki\'s user ID. Used if subwiki does not exists.
     * @param int $groupid Subwiki\'s group ID. Used if subwiki does not exists.
     * @return array of warnings and page data.
     * @since Moodle 3.1
     */
    public static function mod_wiki_new_page($title, $content, $contentformat = null, $subwikiid = null, $wikiid = null, $userid = null,
        $groupid = null) {
        global $USER;

        $params = self::validate_parameters(self::mod_wiki_new_page_parameters(),
                                            array(
                                                'title' => $title,
                                                'content' => $content,
                                                'contentformat' => $contentformat,
                                                'subwikiid' => $subwikiid,
                                                'wikiid' => $wikiid,
                                                'userid' => $userid,
                                                'groupid' => $groupid
                                            )
            );

        $warnings = array();

        // Get wiki and subwiki instances.
        if (!empty($params['subwikiid'])) {
            if (!$subwiki = wiki_get_subwiki($params['subwikiid'])) {
                throw new moodle_exception('incorrectsubwikiid', 'wiki');
            }

            if (!$wiki = wiki_get_wiki($subwiki->wikiid)) {
                throw new moodle_exception('incorrectwikiid', 'wiki');
            }

            // Permission validation.
            $cm = get_coursemodule_from_instance('wiki', $wiki->id, $wiki->course);
            $context = context_module::instance($cm->id);
            self::validate_context($context);

        } else {
            if (!$wiki = wiki_get_wiki($params['wikiid'])) {
                throw new moodle_exception('incorrectwikiid', 'wiki');
            }

            // Permission validation.
            $cm = get_coursemodule_from_instance('wiki', $wiki->id, $wiki->course);
            $context = context_module::instance($cm->id);
            self::validate_context($context);

            // Determine groupid and userid to use.
            list($groupid, $userid) = self::determine_group_and_user($cm, $wiki, $params['groupid'], $params['userid']);

            // Get subwiki and validate it.
            $subwiki = wiki_get_subwiki_by_group_and_user_with_validation($wiki, $groupid, $userid);

            if ($subwiki === false) {
                // User cannot view page.
                throw new moodle_exception('cannoteditpage', 'wiki');
            } else if ($subwiki->id < 0) {
                // Subwiki needed to check edit permissions.
                if (!wiki_user_can_edit($subwiki)) {
                    throw new moodle_exception('cannoteditpage', 'wiki');
                }

                // Subwiki does not exists and it can be created.
                $swid = wiki_add_subwiki($wiki->id, $groupid, $userid);
                if (!$subwiki = wiki_get_subwiki($swid)) {
                    throw new moodle_exception('incorrectsubwikiid', 'wiki');
                }
            }
        }

        // Subwiki needed to check edit permissions.
        if (!wiki_user_can_edit($subwiki)) {
            throw new moodle_exception('cannoteditpage', 'wiki');
        }

        if ($page = wiki_get_page_by_title($subwiki->id, $params['title'])) {
            throw new moodle_exception('pageexists', 'wiki');
        }

        // Ignore invalid formats and use default instead.
        if (!$params['contentformat'] || $wiki->forceformat) {
            $params['contentformat'] = $wiki->defaultformat;
        } else {
            $formats = wiki_get_formats();
            if (!in_array($params['contentformat'], $formats)) {
                $params['contentformat'] = $wiki->defaultformat;
            }
        }

        $newpageid = wiki_create_page($subwiki->id, $params['title'], $params['contentformat'], $USER->id);

        if (!$page = wiki_get_page($newpageid)) {
            throw new moodle_exception('incorrectpageid', 'wiki');
        }

        // Save content.
        $save = wiki_save_page($page, $params['content'], $USER->id);

        if (!$save) {
            throw new moodle_exception('savingerror', 'wiki');
        }

        $result = array();
        $result['pageid'] = $page->id;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the mod_wiki_new_page return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function mod_wiki_new_page_returns() {
        return new external_single_structure(
            array(
                'pageid' => new external_value(PARAM_INT, 'New page id.'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Describes the parameters for mod_wiki_edit_page.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_wiki_edit_page_parameters() {
        return new external_function_parameters (
            array(
                'pageid' => new external_value(PARAM_INT, 'Page ID.'),
                'content' => new external_value(PARAM_RAW, 'Page contents.'),
                'section' => new external_value(PARAM_TEXT, 'Section page title.', VALUE_DEFAULT, null)
            )
        );
    }

    /**
     * Edit a page contents.
     *
     * @param int $pageid The page ID.
     * @param string $content Page contents.
     * @param int $section Section to be edited.
     * @return array of warnings and page data.
     * @since Moodle 3.1
     */
    public static function mod_wiki_edit_page($pageid, $content, $section = null) {
        global $USER;

        $params = self::validate_parameters(self::mod_wiki_edit_page_parameters(),
                                            array(
                                                'pageid' => $pageid,
                                                'content' => $content,
                                                'section' => $section
                                            )
            );
        $warnings = array();

        // Get wiki page.
        if (!$page = wiki_get_page($params['pageid'])) {
            throw new moodle_exception('incorrectpageid', 'wiki');
        }

        // Get wiki instance.
        if (!$wiki = wiki_get_wiki_from_pageid($params['pageid'])) {
            throw new moodle_exception('incorrectwikiid', 'wiki');
        }

        // Get subwiki instance.
        if (!$subwiki = wiki_get_subwiki($page->subwikiid)) {
            throw new moodle_exception('incorrectsubwikiid', 'wiki');
        }

        // Permission validation.
        $cm = get_coursemodule_from_instance('wiki', $wiki->id, $wiki->course);
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        if (!wiki_user_can_edit($subwiki)) {
            throw new moodle_exception('cannoteditpage', 'wiki');
        }

        if (wiki_is_page_section_locked($page->id, $USER->id, $params['section'])) {
            throw new moodle_exception('pageislocked', 'wiki');
        }

        // Save content.
        if (!is_null($params['section'])) {
            $version = wiki_get_current_version($page->id);
            $content = wiki_parser_proxy::get_section($version->content, $version->contentformat, $params['section'], false);
            if (!$content) {
                throw new moodle_exception('invalidsection', 'wiki');
            }

            $save = wiki_save_section($page, $params['section'], $params['content'], $USER->id);
        } else {
            $save = wiki_save_page($page, $params['content'], $USER->id);
        }

        wiki_delete_locks($page->id, $USER->id, $params['section']);

        if (!$save) {
            throw new moodle_exception('savingerror', 'wiki');
        }

        $result = array();
        $result['pageid'] = $page->id;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the mod_wiki_edit_page return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function mod_wiki_edit_page_returns() {
        return new external_single_structure(
            array(
                'pageid' => new external_value(PARAM_INT, 'Edited page id.'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Describes the parameters for get_quizzes_by_courses.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_quiz_get_quizzes_by_courses_parameters() {
        return new external_function_parameters (
            array(
                'courseids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'course id'), 'Array of course ids', VALUE_DEFAULT, array()
                ),
            )
        );
    }

    /**
     * Returns a list of quizzes in a provided list of courses,
     * if no list is provided all quizzes that the user can view will be returned.
     *
     * @param array $courseids Array of course ids
     * @return array of quizzes details
     * @since Moodle 3.1
     */
    public static function mod_quiz_get_quizzes_by_courses($courseids = array()) {
        global $USER, $DB;

        $warnings = array();
        $returnedquizzes = array();

        $params = array(
            'courseids' => $courseids,
        );
        $params = self::validate_parameters(self::mod_quiz_get_quizzes_by_courses_parameters(), $params);

        $mycourses = array();
        if (empty($params['courseids'])) {
            $mycourses = enrol_get_my_courses();
            $params['courseids'] = array_keys($mycourses);
        }

        // Ensure there are courseids to loop through.
        if (!empty($params['courseids'])) {

            list($courses, $warnings) = external_util::validate_courses($params['courseids'], $mycourses);

            // Get the quizzes in this course, this function checks users visibility permissions.
            // We can avoid then additional validate_context calls.
            $quizzes = get_all_instances_in_courses("quiz", $courses);
            foreach ($quizzes as $quiz) {
                $context = context_module::instance($quiz->coursemodule);

                // Update quiz with override information.
                $quiz = quiz_update_effective_access($quiz, $USER->id);

                // Entry to return.
                $quizdetails = array();
                // First, we return information that any user can see in the web interface.
                $quizdetails['id'] = $quiz->id;
                $quizdetails['coursemodule']      = $quiz->coursemodule;
                $quizdetails['course']            = $quiz->course;
                $quizdetails['name']              = external_format_string($quiz->name, $context->id);

                if (has_capability('mod/quiz:view', $context)) {
                    // Format intro.
                    list($quizdetails['intro'], $quizdetails['introformat']) = external_format_text($quiz->intro,
                                                                    $quiz->introformat, $context->id, 'mod_quiz', 'intro', null);

                    $viewablefields = array('timeopen', 'timeclose', 'grademethod', 'section', 'visible', 'groupmode',
                                            'groupingid');

                    $timenow = time();
                    $quizobj = local_mobile_quiz::create($quiz->id, $USER->id);
                    $accessmanager = new local_mobile_quiz_access_manager($quizobj, $timenow, has_capability('mod/quiz:ignoretimelimits',
                                                                $context, null, false));

                    // Fields the user could see if have access to the quiz.
                    if (!$accessmanager->prevent_access()) {
                        // Some times this function returns just empty.
                        $hasfeedback = quiz_has_feedback($quiz);
                        $quizdetails['hasfeedback'] = (!empty($hasfeedback)) ? 1 : 0;
                        $quizdetails['hasquestions'] = (int) $quizobj->has_questions();
                        $quizdetails['autosaveperiod'] = get_config('quiz', 'autosaveperiod');

                        $additionalfields = array('timelimit', 'attempts', 'attemptonlast', 'grademethod', 'decimalpoints',
                                                    'questiondecimalpoints', 'reviewattempt', 'reviewcorrectness', 'reviewmarks',
                                                    'reviewspecificfeedback', 'reviewgeneralfeedback', 'reviewrightanswer',
                                                    'reviewoverallfeedback', 'questionsperpage', 'navmethod', 'sumgrades', 'grade',
                                                    'browsersecurity', 'delay1', 'delay2', 'showuserpicture', 'showblocks',
                                                    'completionattemptsexhausted', 'completionpass', 'overduehandling',
                                                    'graceperiod', 'preferredbehaviour', 'canredoquestions');
                        $viewablefields = array_merge($viewablefields, $additionalfields);
                    }

                    // Fields only for managers.
                    if (has_capability('moodle/course:manageactivities', $context)) {
                        $additionalfields = array('shuffleanswers', 'timecreated', 'timemodified', 'password', 'subnet');
                        $viewablefields = array_merge($viewablefields, $additionalfields);
                    }

                    foreach ($viewablefields as $field) {
                        $quizdetails[$field] = $quiz->{$field};
                    }

                    // Return the password hashed with SHA1 for non-managers.
                    if (!empty($quiz->password) and empty($quizdetails['password'])) {
                        $quizdetails['password'] = sha1($quiz->password);
                    }

                    // Check for allow offline attempts.
                    $quizdetails['allowofflineattempts'] = 0;

                    $dbman = $DB->get_manager();
                    $attemptstable = new xmldb_table('quizaccess_offlineattempts');
                    if ($dbman->table_exists($attemptstable)) {
                        $conditions = array('quizid' => $quiz->id);
                        if ($DB->get_field('quizaccess_offlineattempts', 'allowofflineattempts', $conditions)) {
                            $quizdetails['allowofflineattempts'] = 1;
                        }
                    }
                }
                $returnedquizzes[] = $quizdetails;
            }
        }
        $result = array();
        $result['quizzes'] = $returnedquizzes;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_quizzes_by_courses return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function mod_quiz_get_quizzes_by_courses_returns() {
        return new external_single_structure(
            array(
                'quizzes' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Standard Moodle primary key.'),
                            'course' => new external_value(PARAM_INT, 'Foreign key reference to the course this quiz is part of.'),
                            'coursemodule' => new external_value(PARAM_INT, 'Course module id.'),
                            'name' => new external_value(PARAM_RAW, 'Quiz name.'),
                            'intro' => new external_value(PARAM_RAW, 'Quiz introduction text.', VALUE_OPTIONAL),
                            'introformat' => new external_format_value('intro', VALUE_OPTIONAL),
                            'timeopen' => new external_value(PARAM_INT, 'The time when this quiz opens. (0 = no restriction.)',
                                                                VALUE_OPTIONAL),
                            'timeclose' => new external_value(PARAM_INT, 'The time when this quiz closes. (0 = no restriction.)',
                                                                VALUE_OPTIONAL),
                            'timelimit' => new external_value(PARAM_INT, 'The time limit for quiz attempts, in seconds.',
                                                                VALUE_OPTIONAL),
                            'overduehandling' => new external_value(PARAM_ALPHA, 'The method used to handle overdue attempts.
                                                                    \'autosubmit\', \'graceperiod\' or \'autoabandon\'.',
                                                                    VALUE_OPTIONAL),
                            'graceperiod' => new external_value(PARAM_INT, 'The amount of time (in seconds) after the time limit
                                                                runs out during which attempts can still be submitted,
                                                                if overduehandling is set to allow it.', VALUE_OPTIONAL),
                            'preferredbehaviour' => new external_value(PARAM_ALPHANUMEXT, 'The behaviour to ask questions to use.',
                                                                        VALUE_OPTIONAL),
                            'canredoquestions' => new external_value(PARAM_INT, 'Allows students to redo any completed question
                                                                        within a quiz attempt.', VALUE_OPTIONAL),
                            'attempts' => new external_value(PARAM_INT, 'The maximum number of attempts a student is allowed.',
                                                                VALUE_OPTIONAL),
                            'attemptonlast' => new external_value(PARAM_INT, 'Whether subsequent attempts start from the answer
                                                                    to the previous attempt (1) or start blank (0).',
                                                                    VALUE_OPTIONAL),
                            'grademethod' => new external_value(PARAM_INT, 'One of the values QUIZ_GRADEHIGHEST, QUIZ_GRADEAVERAGE,
                                                                    QUIZ_ATTEMPTFIRST or QUIZ_ATTEMPTLAST.', VALUE_OPTIONAL),
                            'decimalpoints' => new external_value(PARAM_INT, 'Number of decimal points to use when displaying
                                                                    grades.', VALUE_OPTIONAL),
                            'questiondecimalpoints' => new external_value(PARAM_INT, 'Number of decimal points to use when
                                                                            displaying question grades.
                                                                            (-1 means use decimalpoints.)', VALUE_OPTIONAL),
                            'reviewattempt' => new external_value(PARAM_INT, 'Whether users are allowed to review their quiz
                                                                    attempts at various times. This is a bit field, decoded by the
                                                                    mod_quiz_display_options class. It is formed by ORing together
                                                                    the constants defined there.', VALUE_OPTIONAL),
                            'reviewcorrectness' => new external_value(PARAM_INT, 'Whether users are allowed to review their quiz
                                                                        attempts at various times.
                                                                        A bit field, like reviewattempt.', VALUE_OPTIONAL),
                            'reviewmarks' => new external_value(PARAM_INT, 'Whether users are allowed to review their quiz attempts
                                                                at various times. A bit field, like reviewattempt.',
                                                                VALUE_OPTIONAL),
                            'reviewspecificfeedback' => new external_value(PARAM_INT, 'Whether users are allowed to review their
                                                                            quiz attempts at various times. A bit field, like
                                                                            reviewattempt.', VALUE_OPTIONAL),
                            'reviewgeneralfeedback' => new external_value(PARAM_INT, 'Whether users are allowed to review their
                                                                            quiz attempts at various times. A bit field, like
                                                                            reviewattempt.', VALUE_OPTIONAL),
                            'reviewrightanswer' => new external_value(PARAM_INT, 'Whether users are allowed to review their quiz
                                                                        attempts at various times. A bit field, like
                                                                        reviewattempt.', VALUE_OPTIONAL),
                            'reviewoverallfeedback' => new external_value(PARAM_INT, 'Whether users are allowed to review their quiz
                                                                            attempts at various times. A bit field, like
                                                                            reviewattempt.', VALUE_OPTIONAL),
                            'questionsperpage' => new external_value(PARAM_INT, 'How often to insert a page break when editing
                                                                        the quiz, or when shuffling the question order.',
                                                                        VALUE_OPTIONAL),
                            'navmethod' => new external_value(PARAM_ALPHA, 'Any constraints on how the user is allowed to navigate
                                                                around the quiz. Currently recognised values are
                                                                \'free\' and \'seq\'.', VALUE_OPTIONAL),
                            'shuffleanswers' => new external_value(PARAM_INT, 'Whether the parts of the question should be shuffled,
                                                                    in those question types that support it.', VALUE_OPTIONAL),
                            'sumgrades' => new external_value(PARAM_FLOAT, 'The total of all the question instance maxmarks.',
                                                                VALUE_OPTIONAL),
                            'grade' => new external_value(PARAM_FLOAT, 'The total that the quiz overall grade is scaled to be
                                                            out of.', VALUE_OPTIONAL),
                            'timecreated' => new external_value(PARAM_INT, 'The time when the quiz was added to the course.',
                                                                VALUE_OPTIONAL),
                            'timemodified' => new external_value(PARAM_INT, 'Last modified time.',
                                                                    VALUE_OPTIONAL),
                            'password' => new external_value(PARAM_RAW, 'A password that the student must enter before starting or
                                                                continuing a quiz attempt.', VALUE_OPTIONAL),
                            'subnet' => new external_value(PARAM_RAW, 'Used to restrict the IP addresses from which this quiz can
                                                            be attempted. The format is as requried by the address_in_subnet
                                                            function.', VALUE_OPTIONAL),
                            'browsersecurity' => new external_value(PARAM_ALPHANUMEXT, 'Restriciton on the browser the student must
                                                                    use. E.g. \'securewindow\'.', VALUE_OPTIONAL),
                            'delay1' => new external_value(PARAM_INT, 'Delay that must be left between the first and second attempt,
                                                            in seconds.', VALUE_OPTIONAL),
                            'delay2' => new external_value(PARAM_INT, 'Delay that must be left between the second and subsequent
                                                            attempt, in seconds.', VALUE_OPTIONAL),
                            'showuserpicture' => new external_value(PARAM_INT, 'Option to show the user\'s picture during the
                                                                    attempt and on the review page.', VALUE_OPTIONAL),
                            'showblocks' => new external_value(PARAM_INT, 'Whether blocks should be shown on the attempt.php and
                                                                review.php pages.', VALUE_OPTIONAL),
                            'completionattemptsexhausted' => new external_value(PARAM_INT, 'Mark quiz complete when the student has
                                                                                exhausted the maximum number of attempts',
                                                                                VALUE_OPTIONAL),
                            'completionpass' => new external_value(PARAM_INT, 'Whether to require passing grade', VALUE_OPTIONAL),
                            'allowofflineattempts' => new external_value(PARAM_INT, 'Whether to allow the quiz to be attempted
                                                                            offline in the mobile app', VALUE_OPTIONAL),
                            'autosaveperiod' => new external_value(PARAM_INT, 'Auto-save delay', VALUE_OPTIONAL),
                            'hasfeedback' => new external_value(PARAM_INT, 'Whether the quiz has any non-blank feedback text',
                                                                VALUE_OPTIONAL),
                            'hasquestions' => new external_value(PARAM_INT, 'Whether the quiz has questions', VALUE_OPTIONAL),
                            'section' => new external_value(PARAM_INT, 'Course section id', VALUE_OPTIONAL),
                            'visible' => new external_value(PARAM_INT, 'Module visibility', VALUE_OPTIONAL),
                            'groupmode' => new external_value(PARAM_INT, 'Group mode', VALUE_OPTIONAL),
                            'groupingid' => new external_value(PARAM_INT, 'Grouping id', VALUE_OPTIONAL),
                        )
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }


    /**
     * Utility function for validating a quiz.
     *
     * @param int $quizid quiz instance id
     * @return array array containing the quiz, course, context and course module objects
     * @since  Moodle 3.1
     */
    protected static function validate_quiz($quizid) {
        global $DB;

        // Request and permission validation.
        $quiz = $DB->get_record('quiz', array('id' => $quizid), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($quiz, 'quiz');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        return array($quiz, $course, $cm, $context);
    }

    /**
     * Describes the parameters for view_quiz.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_quiz_view_quiz_parameters() {
        return new external_function_parameters (
            array(
                'quizid' => new external_value(PARAM_INT, 'quiz instance id'),
            )
        );
    }

    /**
     * Trigger the course module viewed event and update the module completion status.
     *
     * @param int $quizid quiz instance id
     * @return array of warnings and status result
     * @since Moodle 3.1
     * @throws moodle_exception
     */
    public static function mod_quiz_view_quiz($quizid) {
        global $DB;

        $params = self::validate_parameters(self::mod_quiz_view_quiz_parameters(), array('quizid' => $quizid));
        $warnings = array();

        list($quiz, $course, $cm, $context) = self::validate_quiz($params['quizid']);

        // Trigger course_module_viewed event and completion.
        quiz_view($quiz, $course, $cm, $context);

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the view_quiz return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function mod_quiz_view_quiz_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_user_attempts.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_quiz_get_user_attempts_parameters() {
        return new external_function_parameters (
            array(
                'quizid' => new external_value(PARAM_INT, 'quiz instance id'),
                'userid' => new external_value(PARAM_INT, 'user id, empty for current user', VALUE_DEFAULT, 0),
                'status' => new external_value(PARAM_ALPHA, 'quiz status: all, finished or unfinished', VALUE_DEFAULT, 'finished'),
                'includepreviews' => new external_value(PARAM_BOOL, 'whether to include previews or not', VALUE_DEFAULT, false),

            )
        );
    }

    /**
     * Return a list of attempts for the given quiz and user.
     *
     * @param int $quizid quiz instance id
     * @param int $userid user id
     * @param string $status quiz status: all, finished or unfinished
     * @param bool $includepreviews whether to include previews or not
     * @return array of warnings and the list of attempts
     * @since Moodle 3.1
     * @throws invalid_parameter_exception
     */
    public static function mod_quiz_get_user_attempts($quizid, $userid = 0, $status = 'finished', $includepreviews = false) {
        global $DB, $USER;

        $warnings = array();

        $params = array(
            'quizid' => $quizid,
            'userid' => $userid,
            'status' => $status,
            'includepreviews' => $includepreviews,
        );
        $params = self::validate_parameters(self::mod_quiz_get_user_attempts_parameters(), $params);

        list($quiz, $course, $cm, $context) = self::validate_quiz($params['quizid']);

        if (!in_array($params['status'], array('all', 'finished', 'unfinished'))) {
            throw new invalid_parameter_exception('Invalid status value');
        }

        // Default value for userid.
        if (empty($params['userid'])) {
            $params['userid'] = $USER->id;
        }

        $user = core_user::get_user($params['userid'], '*', MUST_EXIST);
        core_user::require_active_user($user);

        // Extra checks so only users with permissions can view other users attempts.
        if ($USER->id != $user->id) {
            require_capability('mod/quiz:viewreports', $context);
        }

        $attempts = quiz_get_user_attempts($quiz->id, $user->id, $params['status'], $params['includepreviews']);
        foreach ($attempts as $attempt) {
            $attempt = local_mobile_mod_quiz_add_timemodifiedoffline($attempt);
        }

        $result = array();
        $result['attempts'] = $attempts;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes a single attempt structure.
     *
     * @return external_single_structure the attempt structure
     */
    private static function attempt_structure() {
        return new external_single_structure(
            array(
                'id' => new external_value(PARAM_INT, 'Attempt id.', VALUE_OPTIONAL),
                'quiz' => new external_value(PARAM_INT, 'Foreign key reference to the quiz that was attempted.',
                                                VALUE_OPTIONAL),
                'userid' => new external_value(PARAM_INT, 'Foreign key reference to the user whose attempt this is.',
                                                VALUE_OPTIONAL),
                'attempt' => new external_value(PARAM_INT, 'Sequentially numbers this students attempts at this quiz.',
                                                VALUE_OPTIONAL),
                'uniqueid' => new external_value(PARAM_INT, 'Foreign key reference to the question_usage that holds the
                                                    details of the the question_attempts that make up this quiz
                                                    attempt.', VALUE_OPTIONAL),
                'layout' => new external_value(PARAM_RAW, 'Attempt layout.', VALUE_OPTIONAL),
                'currentpage' => new external_value(PARAM_INT, 'Attempt current page.', VALUE_OPTIONAL),
                'preview' => new external_value(PARAM_INT, 'Whether is a preview attempt or not.', VALUE_OPTIONAL),
                'state' => new external_value(PARAM_ALPHA, 'The current state of the attempts. \'inprogress\',
                                                \'overdue\', \'finished\' or \'abandoned\'.', VALUE_OPTIONAL),
                'timestart' => new external_value(PARAM_INT, 'Time when the attempt was started.', VALUE_OPTIONAL),
                'timefinish' => new external_value(PARAM_INT, 'Time when the attempt was submitted.
                                                    0 if the attempt has not been submitted yet.', VALUE_OPTIONAL),
                'timemodified' => new external_value(PARAM_INT, 'Last modified time.', VALUE_OPTIONAL),
                'timemodifiedoffline' => new external_value(PARAM_INT, 'Last modified time via webservices.', VALUE_OPTIONAL),
                'timecheckstate' => new external_value(PARAM_INT, 'Next time quiz cron should check attempt for
                                                        state changes.  NULL means never check.', VALUE_OPTIONAL),
                'sumgrades' => new external_value(PARAM_FLOAT, 'Total marks for this attempt.', VALUE_OPTIONAL),
            )
        );
    }

    /**
     * Describes the get_user_attempts return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function mod_quiz_get_user_attempts_returns() {
        return new external_single_structure(
            array(
                'attempts' => new external_multiple_structure(self::attempt_structure()),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_user_best_grade.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_quiz_get_user_best_grade_parameters() {
        return new external_function_parameters (
            array(
                'quizid' => new external_value(PARAM_INT, 'quiz instance id'),
                'userid' => new external_value(PARAM_INT, 'user id', VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Get the best current grade for the given user on a quiz.
     *
     * @param int $quizid quiz instance id
     * @param int $userid user id
     * @return array of warnings and the grade information
     * @since Moodle 3.1
     */
    public static function mod_quiz_get_user_best_grade($quizid, $userid = 0) {
        global $DB, $USER;

        $warnings = array();

        $params = array(
            'quizid' => $quizid,
            'userid' => $userid,
        );
        $params = self::validate_parameters(self::mod_quiz_get_user_best_grade_parameters(), $params);

        list($quiz, $course, $cm, $context) = self::validate_quiz($params['quizid']);

        // Default value for userid.
        if (empty($params['userid'])) {
            $params['userid'] = $USER->id;
        }

        $user = core_user::get_user($params['userid'], '*', MUST_EXIST);
        core_user::require_active_user($user);

        // Extra checks so only users with permissions can view other users attempts.
        if ($USER->id != $user->id) {
            require_capability('mod/quiz:viewreports', $context);
        }

        $result = array();
        $grade = quiz_get_best_grade($quiz, $user->id);

        if ($grade === null) {
            $result['hasgrade'] = false;
        } else {
            $result['hasgrade'] = true;
            $result['grade'] = $grade;
        }
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_user_best_grade return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function mod_quiz_get_user_best_grade_returns() {
        return new external_single_structure(
            array(
                'hasgrade' => new external_value(PARAM_BOOL, 'Whether the user has a grade on the given quiz.'),
                'grade' => new external_value(PARAM_FLOAT, 'The grade (only if the user has a grade).', VALUE_OPTIONAL),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_combined_review_options.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_quiz_get_combined_review_options_parameters() {
        return new external_function_parameters (
            array(
                'quizid' => new external_value(PARAM_INT, 'quiz instance id'),
                'userid' => new external_value(PARAM_INT, 'user id (empty for current user)', VALUE_DEFAULT, 0),

            )
        );
    }

    /**
     * Combines the review options from a number of different quiz attempts.
     *
     * @param int $quizid quiz instance id
     * @param int $userid user id (empty for current user)
     * @return array of warnings and the review options
     * @since Moodle 3.1
     */
    public static function mod_quiz_get_combined_review_options($quizid, $userid = 0) {
        global $DB, $USER;

        $warnings = array();

        $params = array(
            'quizid' => $quizid,
            'userid' => $userid,
        );
        $params = self::validate_parameters(self::mod_quiz_get_combined_review_options_parameters(), $params);

        list($quiz, $course, $cm, $context) = self::validate_quiz($params['quizid']);

        // Default value for userid.
        if (empty($params['userid'])) {
            $params['userid'] = $USER->id;
        }

        $user = core_user::get_user($params['userid'], '*', MUST_EXIST);
        core_user::require_active_user($user);

        // Extra checks so only users with permissions can view other users attempts.
        if ($USER->id != $user->id) {
            require_capability('mod/quiz:viewreports', $context);
        }

        $attempts = quiz_get_user_attempts($quiz->id, $user->id, 'all', true);

        $result = array();
        $result['someoptions'] = [];
        $result['alloptions'] = [];

        list($someoptions, $alloptions) = quiz_get_combined_reviewoptions($quiz, $attempts);

        foreach (array('someoptions', 'alloptions') as $typeofoption) {
            foreach ($$typeofoption as $key => $value) {
                $result[$typeofoption][] = array(
                    "name" => $key,
                    "value" => (!empty($value)) ? $value : 0
                );
            }
        }

        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_combined_review_options return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function mod_quiz_get_combined_review_options_returns() {
        return new external_single_structure(
            array(
                'someoptions' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_ALPHANUMEXT, 'option name'),
                            'value' => new external_value(PARAM_INT, 'option value'),
                        )
                    )
                ),
                'alloptions' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_ALPHANUMEXT, 'option name'),
                            'value' => new external_value(PARAM_INT, 'option value'),
                        )
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for start_attempt.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_quiz_start_attempt_parameters() {
        return new external_function_parameters (
            array(
                'quizid' => new external_value(PARAM_INT, 'quiz instance id'),
                'preflightdata' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_ALPHANUMEXT, 'data name'),
                            'value' => new external_value(PARAM_RAW, 'data value'),
                        )
                    ), 'Preflight required data (like passwords)', VALUE_DEFAULT, array()
                ),
                'forcenew' => new external_value(PARAM_BOOL, 'Whether to force a new attempt or not.', VALUE_DEFAULT, false),

            )
        );
    }

    /**
     * Starts a new attempt at a quiz.
     *
     * @param int $quizid quiz instance id
     * @param array $preflightdata preflight required data (like passwords)
     * @param bool $forcenew Whether to force a new attempt or not.
     * @return array of warnings and the attempt basic data
     * @since Moodle 3.1
     * @throws moodle_quiz_exception
     */
    public static function mod_quiz_start_attempt($quizid, $preflightdata = array(), $forcenew = false) {
        global $DB, $USER;

        $warnings = array();
        $attempt = array();

        $params = array(
            'quizid' => $quizid,
            'preflightdata' => $preflightdata,
            'forcenew' => $forcenew,
        );
        $params = self::validate_parameters(self::mod_quiz_start_attempt_parameters(), $params);
        $forcenew = $params['forcenew'];

        list($quiz, $course, $cm, $context) = self::validate_quiz($params['quizid']);

        $quizobj = local_mobile_quiz::create($cm->instance, $USER->id);

        // Check questions.
        if (!$quizobj->has_questions()) {
            throw new moodle_quiz_exception($quizobj, 'noquestionsfound');
        }

        // Create an object to manage all the other (non-roles) access rules.
        $timenow = time();
        $accessmanager = $quizobj->get_access_manager($timenow);

        // Validate permissions for creating a new attempt and start a new preview attempt if required.
        list($currentattemptid, $attemptnumber, $lastattempt, $messages, $page) =
            quiz_validate_new_attempt($quizobj, $accessmanager, $forcenew, -1, false);

        // Check access.
        if (!$quizobj->is_preview_user() && $messages) {
            // Create warnings with the exact messages.
            foreach ($messages as $message) {
                $warnings[] = array(
                    'item' => 'quiz',
                    'itemid' => $quiz->id,
                    'warningcode' => '1',
                    'message' => clean_text($message, PARAM_TEXT)
                );
            }
        } else {
            if ($accessmanager->is_preflight_check_required($currentattemptid)) {
                // Need to do some checks before allowing the user to continue.

                $provideddata = array();
                foreach ($params['preflightdata'] as $data) {
                    $provideddata[$data['name']] = $data['value'];
                }

                $errors = $accessmanager->validate_preflight_check($provideddata, [], $currentattemptid);

                if (!empty($errors)) {
                    throw new moodle_quiz_exception($quizobj, array_shift($errors));
                }

                // Pre-flight check passed.
                $accessmanager->notify_preflight_check_passed($currentattemptid);
            }

            if ($currentattemptid) {
                if ($lastattempt->state == quiz_attempt::OVERDUE) {
                    throw new moodle_quiz_exception($quizobj, 'stateoverdue');
                } else {
                    throw new moodle_quiz_exception($quizobj, 'attemptstillinprogress');
                }
            }
            $attempt = quiz_prepare_and_start_new_attempt($quizobj, $attemptnumber, $lastattempt);
            // Update the timemodifiedoffline field.
            $timenow = time();
            $attemptobj = new local_mobile_quiz_attempt($attempt, $quiz, $cm, $course);
            $attemptobj->set_offline_modified_time($timenow);
        }

        $result = array();
        $result['attempt'] = local_mobile_mod_quiz_add_timemodifiedoffline($attempt);
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the start_attempt return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function mod_quiz_start_attempt_returns() {
        return new external_single_structure(
            array(
                'attempt' => self::attempt_structure(),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Utility function for validating a given attempt
     *
     * @param  array $params array of parameters including the attemptid and preflight data
     * @param  bool $checkaccessrules whether to check the quiz access rules or not
     * @param  bool $failifoverdue whether to return error if the attempt is overdue
     * @return  array containing the attempt object and access messages
     * @throws moodle_quiz_exception
     * @since  Moodle 3.1
     */
    protected static function mod_quiz_validate_attempt($params, $checkaccessrules = true, $failifoverdue = true) {
        global $USER;

        $attemptobj = local_mobile_quiz_attempt::create($params['attemptid']);

        $context = context_module::instance($attemptobj->get_cm()->id);
        self::validate_context($context);

        // Check that this attempt belongs to this user.
        if ($attemptobj->get_userid() != $USER->id) {
            throw new moodle_quiz_exception($attemptobj->get_quizobj(), 'notyourattempt');
        }

        // General capabilities check.
        $ispreviewuser = $attemptobj->is_preview_user();
        if (!$ispreviewuser) {
            $attemptobj->require_capability('mod/quiz:attempt');
        }

        // Check the access rules.
        $accessmanager = $attemptobj->get_access_manager(time());
        $messages = array();
        if ($checkaccessrules) {
            // If the attempt is now overdue, or abandoned, deal with that.
            $attemptobj->handle_if_time_expired(time(), true);

            $messages = $accessmanager->prevent_access();
            if (!$ispreviewuser && $messages) {
                throw new moodle_quiz_exception($attemptobj->get_quizobj(), 'attempterror');
            }
        }

        // Attempt closed?.
        if ($attemptobj->is_finished()) {
            throw new moodle_quiz_exception($attemptobj->get_quizobj(), 'attemptalreadyclosed');
        } else if ($failifoverdue && $attemptobj->get_state() == quiz_attempt::OVERDUE) {
            throw new moodle_quiz_exception($attemptobj->get_quizobj(), 'stateoverdue');
        }

        // User submitted data (like the quiz password).
        if ($accessmanager->is_preflight_check_required($attemptobj->get_attemptid())) {
            $provideddata = array();
            foreach ($params['preflightdata'] as $data) {
                $provideddata[$data['name']] = $data['value'];
            }

            $errors = $accessmanager->validate_preflight_check($provideddata, [], $params['attemptid']);
            if (!empty($errors)) {
                throw new moodle_quiz_exception($attemptobj->get_quizobj(), array_shift($errors));
            }
            // Pre-flight check passed.
            $accessmanager->notify_preflight_check_passed($params['attemptid']);
        }

        if (isset($params['page'])) {
            // Check if the page is out of range.
            if ($params['page'] != $attemptobj->force_page_number_into_range($params['page'])) {
                throw new moodle_quiz_exception($attemptobj->get_quizobj(), 'Invalid page number');
            }

            // Prevent out of sequence access.
            if (!$attemptobj->check_page_access($params['page'])) {
                throw new moodle_quiz_exception($attemptobj->get_quizobj(), 'Out of sequence access');
            }

            // Check slots.
            $slots = $attemptobj->get_slots($params['page']);

            if (empty($slots)) {
                throw new moodle_quiz_exception($attemptobj->get_quizobj(), 'noquestionsfound');
            }
        }

        return array($attemptobj, $messages);
    }

    /**
     * Describes a single question structure.
     *
     * @return external_single_structure the question structure
     * @since  Moodle 3.1
     */
    private static function question_structure() {
        return new external_single_structure(
            array(
                'slot' => new external_value(PARAM_INT, 'slot number'),
                'type' => new external_value(PARAM_ALPHANUMEXT, 'question type, i.e: multichoice'),
                'page' => new external_value(PARAM_INT, 'page of the quiz this question appears on'),
                'html' => new external_value(PARAM_RAW, 'the question rendered'),
                'sequencecheck' => new external_value(PARAM_INT, 'the number of real steps in this attempt'),
                'lastactiontime' => new external_value(PARAM_INT, 'the timestamp of the most recent step in this question attempt'),
                'hasautosavedstep' => new external_value(PARAM_BOOL, 'whether this question attempt has autosaved data'),
                'flagged' => new external_value(PARAM_BOOL, 'whether the question is flagged or not'),
                'number' => new external_value(PARAM_INT, 'question ordering number in the quiz', VALUE_OPTIONAL),
                'state' => new external_value(PARAM_ALPHA, 'the state where the question is in', VALUE_OPTIONAL),
                'status' => new external_value(PARAM_RAW, 'current formatted state of the question', VALUE_OPTIONAL),
                'mark' => new external_value(PARAM_RAW, 'the mark awarded', VALUE_OPTIONAL),
                'maxmark' => new external_value(PARAM_FLOAT, 'the maximum mark possible for this question attempt', VALUE_OPTIONAL),
            )
        );
    }

    /**
     * Return questions information for a given attempt.
     *
     * @param  quiz_attempt  $attemptobj  the quiz attempt object
     * @param  bool  $review  whether if we are in review mode or not
     * @param  mixed  $page  string 'all' or integer page number
     * @return array array of questions including data
     */
    private static function get_attempt_questions_data(quiz_attempt $attemptobj, $review, $page = 'all') {
        global $PAGE;

        $questions = array();
        $contextid = $attemptobj->get_quizobj()->get_context()->id;
        $displayoptions = $attemptobj->get_display_options($review);
        $renderer = $PAGE->get_renderer('mod_quiz');

        foreach ($attemptobj->get_slots($page) as $slot) {

            $question = array(
                'slot' => $slot,
                'type' => $attemptobj->get_question_type_name($slot),
                'page' => $attemptobj->get_question_page($slot),
                'flagged' => $attemptobj->is_question_flagged($slot),
                'html' => $attemptobj->render_question($slot, $review, $renderer) . $PAGE->requires->get_end_code(),
                'sequencecheck' => $attemptobj->get_question_attempt($slot)->get_sequence_check_count(),
                'lastactiontime' => $attemptobj->get_question_attempt($slot)->get_last_step()->get_timecreated(),
                'hasautosavedstep' => $attemptobj->get_question_attempt($slot)->has_autosaved_step()
            );

            if ($attemptobj->is_real_question($slot)) {
                $question['number'] = $attemptobj->get_question_number($slot);
                $question['state'] = (string) $attemptobj->get_question_state($slot);
                $question['status'] = $attemptobj->get_question_status($slot, $displayoptions->correctness);
            }
            if ($displayoptions->marks >= question_display_options::MAX_ONLY) {
                $question['maxmark'] = $attemptobj->get_question_attempt($slot)->get_max_mark();
            }
            if ($displayoptions->marks >= question_display_options::MARK_AND_MAX) {
                $question['mark'] = $attemptobj->get_question_mark($slot);
            }

            $questions[] = $question;
        }
        return $questions;
    }

    /**
     * Describes the parameters for get_attempt_data.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_quiz_get_attempt_data_parameters() {
        return new external_function_parameters (
            array(
                'attemptid' => new external_value(PARAM_INT, 'attempt id'),
                'page' => new external_value(PARAM_INT, 'page number'),
                'preflightdata' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_ALPHANUMEXT, 'data name'),
                            'value' => new external_value(PARAM_RAW, 'data value'),
                        )
                    ), 'Preflight required data (like passwords)', VALUE_DEFAULT, array()
                )
            )
        );
    }

    /**
     * Returns information for the given attempt page for a quiz attempt in progress.
     *
     * @param int $attemptid attempt id
     * @param int $page page number
     * @param array $preflightdata preflight required data (like passwords)
     * @return array of warnings and the attempt data, next page, message and questions
     * @since Moodle 3.1
     * @throws moodle_quiz_exceptions
     */
    public static function mod_quiz_get_attempt_data($attemptid, $page, $preflightdata = array()) {

        $warnings = array();

        $params = array(
            'attemptid' => $attemptid,
            'page' => $page,
            'preflightdata' => $preflightdata,
        );
        $params = self::validate_parameters(self::mod_quiz_get_attempt_data_parameters(), $params);

        list($attemptobj, $messages) = self::mod_quiz_validate_attempt($params);

        if ($attemptobj->is_last_page($params['page'])) {
            $nextpage = -1;
        } else {
            $nextpage = $params['page'] + 1;
        }

        $result = array();
        $result['attempt'] = local_mobile_mod_quiz_add_timemodifiedoffline($attemptobj->get_attempt());
        $result['messages'] = $messages;
        $result['nextpage'] = $nextpage;
        $result['warnings'] = $warnings;
        $result['questions'] = self::get_attempt_questions_data($attemptobj, false, $params['page']);

        return $result;
    }

    /**
     * Describes the get_attempt_data return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function mod_quiz_get_attempt_data_returns() {
        return new external_single_structure(
            array(
                'attempt' => self::attempt_structure(),
                'messages' => new external_multiple_structure(
                    new external_value(PARAM_TEXT, 'access message'),
                    'access messages, will only be returned for users with mod/quiz:preview capability,
                    for other users this method will throw an exception if there are messages'),
                'nextpage' => new external_value(PARAM_INT, 'next page number'),
                'questions' => new external_multiple_structure(self::question_structure()),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_attempt_summary.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_quiz_get_attempt_summary_parameters() {
        return new external_function_parameters (
            array(
                'attemptid' => new external_value(PARAM_INT, 'attempt id'),
                'preflightdata' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_ALPHANUMEXT, 'data name'),
                            'value' => new external_value(PARAM_RAW, 'data value'),
                        )
                    ), 'Preflight required data (like passwords)', VALUE_DEFAULT, array()
                )
            )
        );
    }

    /**
     * Returns a summary of a quiz attempt before it is submitted.
     *
     * @param int $attemptid attempt id
     * @param int $preflightdata preflight required data (like passwords)
     * @return array of warnings and the attempt summary data for each question
     * @since Moodle 3.1
     */
    public static function mod_quiz_get_attempt_summary($attemptid, $preflightdata = array()) {

        $warnings = array();

        $params = array(
            'attemptid' => $attemptid,
            'preflightdata' => $preflightdata,
        );
        $params = self::validate_parameters(self::mod_quiz_get_attempt_summary_parameters(), $params);

        list($attemptobj, $messages) = self::mod_quiz_validate_attempt($params, true, false);

        $result = array();
        $result['warnings'] = $warnings;
        $result['questions'] = self::get_attempt_questions_data($attemptobj, false, 'all');

        return $result;
    }

    /**
     * Describes the get_attempt_summary return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function mod_quiz_get_attempt_summary_returns() {
        return new external_single_structure(
            array(
                'questions' => new external_multiple_structure(self::question_structure()),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for save_attempt.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_quiz_save_attempt_parameters() {
        return new external_function_parameters (
            array(
                'attemptid' => new external_value(PARAM_INT, 'attempt id'),
                'data' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_RAW, 'data name'),
                            'value' => new external_value(PARAM_RAW, 'data value'),
                        )
                    ), 'the data to be saved'
                ),
                'preflightdata' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_ALPHANUMEXT, 'data name'),
                            'value' => new external_value(PARAM_RAW, 'data value'),
                        )
                    ), 'Preflight required data (like passwords)', VALUE_DEFAULT, array()
                )
            )
        );
    }

    /**
     * Processes save requests during the quiz. This function is intended for the quiz auto-save feature.
     *
     * @param int $attemptid attempt id
     * @param array $data the data to be saved
     * @param  array $preflightdata preflight required data (like passwords)
     * @return array of warnings and execution result
     * @since Moodle 3.1
     */
    public static function mod_quiz_save_attempt($attemptid, $data, $preflightdata = array()) {
        global $DB;

        $warnings = array();

        $params = array(
            'attemptid' => $attemptid,
            'data' => $data,
            'preflightdata' => $preflightdata,
        );
        $params = self::validate_parameters(self::mod_quiz_save_attempt_parameters(), $params);

        // Add a page, required by validate_attempt.
        list($attemptobj, $messages) = self::mod_quiz_validate_attempt($params);

        $transaction = $DB->start_delegated_transaction();
        // Create the $_POST object required by the question engine.
        $_POST = array();
        foreach ($data as $element) {
            $_POST[$element['name']] = $element['value'];
        }
        $timenow = time();
        $attemptobj->process_auto_save($timenow);
        // Update the timemodifiedoffline field.
        $attemptobj->set_offline_modified_time($timenow);
        $transaction->allow_commit();

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the save_attempt return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function mod_quiz_save_attempt_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for process_attempt.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_quiz_process_attempt_parameters() {
        return new external_function_parameters (
            array(
                'attemptid' => new external_value(PARAM_INT, 'attempt id'),
                'data' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_RAW, 'data name'),
                            'value' => new external_value(PARAM_RAW, 'data value'),
                        )
                    ),
                    'the data to be saved', VALUE_DEFAULT, array()
                ),
                'finishattempt' => new external_value(PARAM_BOOL, 'whether to finish or not the attempt', VALUE_DEFAULT, false),
                'timeup' => new external_value(PARAM_BOOL, 'whether the WS was called by a timer when the time is up',
                                                VALUE_DEFAULT, false),
                'preflightdata' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_ALPHANUMEXT, 'data name'),
                            'value' => new external_value(PARAM_RAW, 'data value'),
                        )
                    ), 'Preflight required data (like passwords)', VALUE_DEFAULT, array()
                )
            )
        );
    }

    /**
     * Process responses during an attempt at a quiz and also deals with attempts finishing.
     *
     * @param int $attemptid attempt id
     * @param array $data the data to be saved
     * @param bool $finishattempt whether to finish or not the attempt
     * @param bool $timeup whether the WS was called by a timer when the time is up
     * @param array $preflightdata preflight required data (like passwords)
     * @return array of warnings and the attempt state after the processing
     * @since Moodle 3.1
     */
    public static function mod_quiz_process_attempt($attemptid, $data, $finishattempt = false, $timeup = false, $preflightdata = array()) {

        $warnings = array();

        $params = array(
            'attemptid' => $attemptid,
            'data' => $data,
            'finishattempt' => $finishattempt,
            'timeup' => $timeup,
            'preflightdata' => $preflightdata,
        );
        $params = self::validate_parameters(self::mod_quiz_process_attempt_parameters(), $params);

        // Do not check access manager rules.
        list($attemptobj, $messages) = self::mod_quiz_validate_attempt($params, false);

        // Create the $_POST object required by the question engine.
        $_POST = array();
        foreach ($params['data'] as $element) {
            $_POST[$element['name']] = $element['value'];
        }
        $timenow = time();
        $finishattempt = $params['finishattempt'];
        $timeup = $params['timeup'];

        $result = array();
        $result['state'] = $attemptobj->process_attempt($timenow, $finishattempt, $timeup, 0);
        // Update the timemodifiedoffline field.
        $attemptobj->set_offline_modified_time($timenow);

        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the process_attempt return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function mod_quiz_process_attempt_returns() {
        return new external_single_structure(
            array(
                'state' => new external_value(PARAM_ALPHANUMEXT, 'state: the new attempt state:
                                                                    inprogress, finished, overdue, abandoned'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Validate an attempt finished for review. The attempt would be reviewed by a user or a teacher.
     *
     * @param  array $params Array of parameters including the attemptid
     * @return  array containing the attempt object and display options
     * @since  Moodle 3.1
     * @throws  moodle_exception
     * @throws  moodle_quiz_exception
     */
    protected static function mod_quiz_validate_attempt_review($params) {

        $attemptobj = local_mobile_quiz_attempt::create($params['attemptid']);
        $attemptobj->check_review_capability();

        $displayoptions = $attemptobj->get_display_options(true);
        if ($attemptobj->is_own_attempt()) {
            if (!$attemptobj->is_finished()) {
                throw new moodle_quiz_exception($attemptobj->get_quizobj(), 'attemptclosed');
            } else if (!$displayoptions->attempt) {
                throw new moodle_exception($attemptobj->cannot_review_message());
            }
        } else if (!$attemptobj->is_review_allowed()) {
            throw new moodle_quiz_exception($attemptobj->get_quizobj(), 'noreviewattempt');
        }
        return array($attemptobj, $displayoptions);
    }

    /**
     * Describes the parameters for get_attempt_review.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_quiz_get_attempt_review_parameters() {
        return new external_function_parameters (
            array(
                'attemptid' => new external_value(PARAM_INT, 'attempt id'),
                'page' => new external_value(PARAM_INT, 'page number, empty for all the questions in all the pages',
                                                VALUE_DEFAULT, -1),
            )
        );
    }

    /**
     * Returns review information for the given finished attempt, can be used by users or teachers.
     *
     * @param int $attemptid attempt id
     * @param int $page page number, empty for all the questions in all the pages
     * @return array of warnings and the attempt data, feedback and questions
     * @since Moodle 3.1
     * @throws  moodle_exception
     * @throws  moodle_quiz_exception
     */
    public static function mod_quiz_get_attempt_review($attemptid, $page = -1) {
        global $PAGE;

        $warnings = array();

        $params = array(
            'attemptid' => $attemptid,
            'page' => $page,
        );
        $params = self::validate_parameters(self::mod_quiz_get_attempt_review_parameters(), $params);

        list($attemptobj, $displayoptions) = self::mod_quiz_validate_attempt_review($params);

        if ($params['page'] !== -1) {
            $page = $attemptobj->force_page_number_into_range($params['page']);
        } else {
            $page = 'all';
        }

        // Prepare the output.
        $result = array();
        $result['attempt'] = local_mobile_mod_quiz_add_timemodifiedoffline($attemptobj->get_attempt());
        $result['questions'] = self::get_attempt_questions_data($attemptobj, true, $page, true);

        $result['additionaldata'] = array();
        // Summary data (from behaviours).
        $summarydata = $attemptobj->get_additional_summary_data($displayoptions);
        foreach ($summarydata as $key => $data) {
            // This text does not need formatting (no need for external_format_[string|text]).
            $result['additionaldata'][] = array(
                'id' => $key,
                'title' => $data['title'], $attemptobj->get_quizobj()->get_context()->id,
                'content' => $data['content'],
            );
        }

        // Feedback if there is any, and the user is allowed to see it now.
        $grade = quiz_rescale_grade($attemptobj->get_attempt()->sumgrades, $attemptobj->get_quiz(), false);

        $feedback = $attemptobj->get_overall_feedback($grade);
        if ($displayoptions->overallfeedback && $feedback) {
            $result['additionaldata'][] = array(
                'id' => 'feedback',
                'title' => get_string('feedback', 'quiz'),
                'content' => $feedback,
            );
        }

        $result['grade'] = $grade;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_attempt_review return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function mod_quiz_get_attempt_review_returns() {
        return new external_single_structure(
            array(
                'grade' => new external_value(PARAM_RAW, 'grade for the quiz (or empty or "notyetgraded")'),
                'attempt' => self::attempt_structure(),
                'additionaldata' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_ALPHANUMEXT, 'id of the data'),
                            'title' => new external_value(PARAM_TEXT, 'data title'),
                            'content' => new external_value(PARAM_RAW, 'data content'),
                        )
                    )
                ),
                'questions' => new external_multiple_structure(self::question_structure()),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for view_attempt.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_quiz_view_attempt_parameters() {
        return new external_function_parameters (
            array(
                'attemptid' => new external_value(PARAM_INT, 'attempt id'),
                'page' => new external_value(PARAM_INT, 'page number'),
                'preflightdata' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_ALPHANUMEXT, 'data name'),
                            'value' => new external_value(PARAM_RAW, 'data value'),
                        )
                    ), 'Preflight required data (like passwords)', VALUE_DEFAULT, array()
                )
            )
        );
    }

    /**
     * Trigger the attempt viewed event.
     *
     * @param int $attemptid attempt id
     * @param int $page page number
     * @return array of warnings and status result
     * @since Moodle 3.1
     */
    public static function mod_quiz_view_attempt($attemptid, $page, $preflightdata = array()) {

        $warnings = array();

        $params = array(
            'attemptid' => $attemptid,
            'page' => $page,
            'preflightdata' => $preflightdata,
        );
        $params = self::validate_parameters(self::mod_quiz_view_attempt_parameters(), $params);
        list($attemptobj, $messages) = self::mod_quiz_validate_attempt($params);

        // Log action.
        $attemptobj->fire_attempt_viewed_event();

        // Update attempt page, throwing an exception if $page is not valid.
        if (!$attemptobj->set_currentpage($params['page'])) {
            throw new moodle_quiz_exception($attemptobj->get_quizobj(), 'Out of sequence access');
        }

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the view_attempt return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function mod_quiz_view_attempt_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for view_attempt_summary.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_quiz_view_attempt_summary_parameters() {
        return new external_function_parameters (
            array(
                'attemptid' => new external_value(PARAM_INT, 'attempt id'),
                'preflightdata' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_ALPHANUMEXT, 'data name'),
                            'value' => new external_value(PARAM_RAW, 'data value'),
                        )
                    ), 'Preflight required data (like passwords)', VALUE_DEFAULT, array()
                )
            )
        );
    }

    /**
     * Trigger the attempt summary viewed event.
     *
     * @param int $attemptid attempt id
     * @return array of warnings and status result
     * @since Moodle 3.1
     */
    public static function mod_quiz_view_attempt_summary($attemptid, $preflightdata = array()) {

        $warnings = array();

        $params = array(
            'attemptid' => $attemptid,
            'preflightdata' => $preflightdata,
        );
        $params = self::validate_parameters(self::mod_quiz_view_attempt_summary_parameters(), $params);
        list($attemptobj, $messages) = self::mod_quiz_validate_attempt($params);

        // Log action.
        $attemptobj->fire_attempt_summary_viewed_event();

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the view_attempt_summary return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function mod_quiz_view_attempt_summary_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for view_attempt_review.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_quiz_view_attempt_review_parameters() {
        return new external_function_parameters (
            array(
                'attemptid' => new external_value(PARAM_INT, 'attempt id'),
            )
        );
    }

    /**
     * Trigger the attempt reviewed event.
     *
     * @param int $attemptid attempt id
     * @return array of warnings and status result
     * @since Moodle 3.1
     */
    public static function mod_quiz_view_attempt_review($attemptid) {

        $warnings = array();

        $params = array(
            'attemptid' => $attemptid,
        );
        $params = self::validate_parameters(self::mod_quiz_view_attempt_review_parameters(), $params);
        list($attemptobj, $displayoptions) = self::mod_quiz_validate_attempt_review($params);

        // Log action.
        $attemptobj->fire_attempt_reviewed_event();

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the view_attempt_review return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function mod_quiz_view_attempt_review_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for view_quiz.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_quiz_get_quiz_feedback_for_grade_parameters() {
        return new external_function_parameters (
            array(
                'quizid' => new external_value(PARAM_INT, 'quiz instance id'),
                'grade' => new external_value(PARAM_FLOAT, 'the grade to check'),
            )
        );
    }

    /**
     * Get the feedback text that should be show to a student who got the given grade in the given quiz.
     *
     * @param int $quizid quiz instance id
     * @param float $grade the grade to check
     * @return array of warnings and status result
     * @since Moodle 3.1
     * @throws moodle_exception
     */
    public static function mod_quiz_get_quiz_feedback_for_grade($quizid, $grade) {
        global $DB;

        $params = array(
            'quizid' => $quizid,
            'grade' => $grade,
        );
        $params = self::validate_parameters(self::mod_quiz_get_quiz_feedback_for_grade_parameters(), $params);
        $warnings = array();

        list($quiz, $course, $cm, $context) = self::validate_quiz($params['quizid']);

        $result = array();
        $result['feedbacktext'] = '';
        $result['feedbacktextformat'] = FORMAT_MOODLE;

        $feedback = quiz_feedback_record_for_grade($params['grade'], $quiz);
        if (!empty($feedback->feedbacktext)) {
            list($text, $format) = external_format_text($feedback->feedbacktext, $feedback->feedbacktextformat, $context->id,
                                                        'mod_quiz', 'feedback', $feedback->id);
            $result['feedbacktext'] = $text;
            $result['feedbacktextformat'] = $format;
        }

        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_quiz_feedback_for_grade return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function mod_quiz_get_quiz_feedback_for_grade_returns() {
        return new external_single_structure(
            array(
                'feedbacktext' => new external_value(PARAM_RAW, 'the comment that corresponds to this grade (empty for none)'),
                'feedbacktextformat' => new external_format_value('feedbacktext', VALUE_OPTIONAL),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_quiz_access_information.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_quiz_get_quiz_access_information_parameters() {
        return new external_function_parameters (
            array(
                'quizid' => new external_value(PARAM_INT, 'quiz instance id')
            )
        );
    }

    /**
     * Return access information for a given quiz.
     *
     * @param int $quizid quiz instance id
     * @return array of warnings and the access information
     * @since Moodle 3.1
     * @throws  moodle_quiz_exception
     */
    public static function mod_quiz_get_quiz_access_information($quizid) {
        global $DB, $USER;

        $warnings = array();

        $params = array(
            'quizid' => $quizid
        );
        $params = self::validate_parameters(self::mod_quiz_get_quiz_access_information_parameters(), $params);

        list($quiz, $course, $cm, $context) = self::validate_quiz($params['quizid']);

        $result = array();
        // Capabilities first.
        $result['canattempt'] = has_capability('mod/quiz:attempt', $context);;
        $result['canmanage'] = has_capability('mod/quiz:manage', $context);;
        $result['canpreview'] = has_capability('mod/quiz:preview', $context);;
        $result['canreviewmyattempts'] = has_capability('mod/quiz:reviewmyattempts', $context);;
        $result['canviewreports'] = has_capability('mod/quiz:viewreports', $context);;

        // Access manager now.
        $quizobj = local_mobile_quiz::create($cm->instance, $USER->id);
        $ignoretimelimits = has_capability('mod/quiz:ignoretimelimits', $context, null, false);
        $timenow = time();
        $accessmanager = new local_mobile_quiz_access_manager($quizobj, $timenow, $ignoretimelimits);

        $result['accessrules'] = $accessmanager->describe_rules();
        $result['activerulenames'] = $accessmanager->get_active_rule_names();
        $result['preventaccessreasons'] = $accessmanager->prevent_access();

        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_quiz_access_information return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function mod_quiz_get_quiz_access_information_returns() {
        return new external_single_structure(
            array(
                'canattempt' => new external_value(PARAM_BOOL, 'Whether the user can do the quiz or not.'),
                'canmanage' => new external_value(PARAM_BOOL, 'Whether the user can edit the quiz settings or not.'),
                'canpreview' => new external_value(PARAM_BOOL, 'Whether the user can preview the quiz or not.'),
                'canreviewmyattempts' => new external_value(PARAM_BOOL, 'Whether the users can review their previous attempts
                                                                or not.'),
                'canviewreports' => new external_value(PARAM_BOOL, 'Whether the user can view the quiz reports or not.'),
                'accessrules' => new external_multiple_structure(
                                    new external_value(PARAM_TEXT, 'rule description'), 'list of rules'),
                'activerulenames' => new external_multiple_structure(
                                    new external_value(PARAM_PLUGIN, 'rule plugin names'), 'list of active rules'),
                'preventaccessreasons' => new external_multiple_structure(
                                            new external_value(PARAM_TEXT, 'access restriction description'), 'list of reasons'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_attempt_access_information.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_quiz_get_attempt_access_information_parameters() {
        return new external_function_parameters (
            array(
                'quizid' => new external_value(PARAM_INT, 'quiz instance id'),
                'attemptid' => new external_value(PARAM_INT, 'attempt id, 0 for the user last attempt if exists', VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Return access information for a given attempt in a quiz.
     *
     * @param int $quizid quiz instance id
     * @param int $attemptid attempt id, 0 for the user last attempt if exists
     * @return array of warnings and the access information
     * @since Moodle 3.1
     * @throws  moodle_quiz_exception
     */
    public static function mod_quiz_get_attempt_access_information($quizid, $attemptid = 0) {
        global $DB, $USER;

        $warnings = array();

        $params = array(
            'quizid' => $quizid,
            'attemptid' => $attemptid,
        );
        $params = self::validate_parameters(self::mod_quiz_get_attempt_access_information_parameters(), $params);

        list($quiz, $course, $cm, $context) = self::validate_quiz($params['quizid']);

        $attempttocheck = 0;
        if (!empty($params['attemptid'])) {
            $attemptobj = local_mobile_quiz_attempt::create($params['attemptid']);
            if ($attemptobj->get_userid() != $USER->id) {
                throw new moodle_quiz_exception($attemptobj->get_quizobj(), 'notyourattempt');
            }
            $attempttocheck = $attemptobj->get_attempt();
        }

        // Access manager now.
        $quizobj = local_mobile_quiz::create($cm->instance, $USER->id);
        $ignoretimelimits = has_capability('mod/quiz:ignoretimelimits', $context, null, false);
        $timenow = time();
        $accessmanager = new local_mobile_quiz_access_manager($quizobj, $timenow, $ignoretimelimits);

        $attempts = quiz_get_user_attempts($quiz->id, $USER->id, 'finished', true);
        $lastfinishedattempt = end($attempts);
        if ($unfinishedattempt = quiz_get_user_attempt_unfinished($quiz->id, $USER->id)) {
            $attempts[] = $unfinishedattempt;

            // Check if the attempt is now overdue. In that case the state will change.
            $quizobj->create_attempt_object($unfinishedattempt)->handle_if_time_expired(time(), false);

            if ($unfinishedattempt->state != quiz_attempt::IN_PROGRESS and $unfinishedattempt->state != quiz_attempt::OVERDUE) {
                $lastfinishedattempt = $unfinishedattempt;
            }
        }
        $numattempts = count($attempts);

        if (!$attempttocheck) {
            $attempttocheck = $unfinishedattempt ? $unfinishedattempt : $lastfinishedattempt;
        }

        $result = array();
        $result['isfinished'] = $accessmanager->is_finished($numattempts, $lastfinishedattempt);
        $result['preventnewattemptreasons'] = $accessmanager->prevent_new_attempt($numattempts, $lastfinishedattempt);

        if ($attempttocheck) {
            $endtime = $accessmanager->get_end_time($attempttocheck);
            $result['endtime'] = ($endtime === false) ? 0 : $endtime;
            $attemptid = $unfinishedattempt ? $unfinishedattempt->id : null;
            $result['ispreflightcheckrequired'] = $accessmanager->is_preflight_check_required($attemptid);
        }

        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_attempt_access_information return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function mod_quiz_get_attempt_access_information_returns() {
        return new external_single_structure(
            array(
                'endtime' => new external_value(PARAM_INT, 'When the attempt must be submitted (determined by rules).',
                                                VALUE_OPTIONAL),
                'isfinished' => new external_value(PARAM_BOOL, 'Whether there is no way the user will ever be allowed to attempt.'),
                'ispreflightcheckrequired' => new external_value(PARAM_BOOL, 'whether a check is required before the user
                                                                    starts/continues his attempt.', VALUE_OPTIONAL),
                'preventnewattemptreasons' => new external_multiple_structure(
                                                new external_value(PARAM_TEXT, 'access restriction description'),
                                                                    'list of reasons'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_quiz_required_qtypes.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_quiz_get_quiz_required_qtypes_parameters() {
        return new external_function_parameters (
            array(
                'quizid' => new external_value(PARAM_INT, 'quiz instance id')
            )
        );
    }

    /**
     * Return the potential question types that would be required for a given quiz.
     * Please note that for random question types we return the potential question types in the category choosen.
     *
     * @param int $quizid quiz instance id
     * @return array of warnings and the access information
     * @since Moodle 3.1
     * @throws  moodle_quiz_exception
     */
    public static function mod_quiz_get_quiz_required_qtypes($quizid) {
        global $DB, $USER;

        $warnings = array();

        $params = array(
            'quizid' => $quizid
        );
        $params = self::validate_parameters(self::mod_quiz_get_quiz_required_qtypes_parameters(), $params);

        list($quiz, $course, $cm, $context) = self::validate_quiz($params['quizid']);

        $quizobj = local_mobile_quiz::create($cm->instance, $USER->id);
        $quizobj->preload_questions();
        $quizobj->load_questions();

        // Question types used.
        $result = array();
        $result['questiontypes'] = $quizobj->get_all_question_types_used(true);
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_quiz_required_qtypes return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function mod_quiz_get_quiz_required_qtypes_returns() {
        return new external_single_structure(
            array(
                'questiontypes' => new external_multiple_structure(
                                    new external_value(PARAM_PLUGIN, 'question type'), 'list of question types used in the quiz'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Generate a warning in a standard structure for a known failure.
     *
     * @param int $assignmentid - The assignment
     * @param string $warningcode - The key for the warning message
     * @param string $detail - A description of the error
     * @return array - Warning structure containing item, itemid, warningcode, message
     */
    private static function mod_assign_generate_warning($assignmentid, $warningcode, $detail) {
        $warningmessages = array(
            'couldnotlock'=>'Could not lock the submission for this user.',
            'couldnotunlock'=>'Could not unlock the submission for this user.',
            'couldnotsubmitforgrading'=>'Could not submit assignment for grading.',
            'couldnotrevealidentities'=>'Could not reveal identities.',
            'couldnotgrantextensions'=>'Could not grant submission date extensions.',
            'couldnotrevert'=>'Could not revert submission to draft.',
            'invalidparameters'=>'Invalid parameters.',
            'couldnotsavesubmission'=>'Could not save submission.',
            'couldnotsavegrade'=>'Could not save grade.'
        );

        $message = $warningmessages[$warningcode];
        if (empty($message)) {
            $message = 'Unknown warning type.';
        }

        return array('item'=>$detail,
                     'itemid'=>$assignmentid,
                     'warningcode'=>$warningcode,
                     'message'=>$message);
    }

    /**
     * Describes the parameters for save_submission
     * @return external_external_function_parameters
     * @since  Moodle 2.6
     */
    public static function mod_assign_save_submission_parameters() {
        global $CFG;
        $instance = new local_mobile_assign(null, null, null);
        $pluginsubmissionparams = array();

        foreach ($instance->get_submission_plugins() as $plugin) {
            if ($plugin->is_visible()) {
                $pluginparams = null;
                if (get_class($plugin) == 'assign_submission_onlinetext') {

                    $editorparams = array(
                      'text' => new external_value(PARAM_RAW, 'The text for this submission.'),
                      'format' => new external_value(PARAM_INT, 'The format for this submission'),
                      'itemid' => new external_value(PARAM_INT, 'The draft area id for files attached to the submission'));

                    $editorstructure = new external_single_structure($editorparams, 'Editor structure', VALUE_OPTIONAL);
                    $pluginparams = array('onlinetext_editor' => $editorstructure);

                } else if (get_class($plugin) == 'assign_submission_file') {
                    $pluginparams = array(
                        'files_filemanager' => new external_value(
                            PARAM_INT,
                            'The id of a draft area containing files for this submission.',
                            VALUE_OPTIONAL
                        )
                    );
                } else {
                    $pluginparams = $plugin->get_external_parameters();
                }

                if (!empty($pluginparams)) {
                    $pluginsubmissionparams = array_merge($pluginsubmissionparams, $pluginparams);
                }
            }
        }

        return new external_function_parameters(
            array(
                'assignmentid' => new external_value(PARAM_INT, 'The assignment id to operate on'),
                'plugindata' => new external_single_structure(
                    $pluginsubmissionparams
                )
            )
        );
    }

    /**
     * Save a student submission for a single assignment
     *
     * @param int $assignmentid The id of the assignment
     * @param array $plugindata - The submitted data for plugins
     * @return array of warnings to indicate any errors
     * @since Moodle 2.6
     */
    public static function mod_assign_save_submission($assignmentid, $plugindata) {
        global $CFG, $USER;

        $params = self::validate_parameters(self::mod_assign_save_submission_parameters(),
                                            array('assignmentid' => $assignmentid,
                                                  'plugindata' => $plugindata));

        $cm = get_coursemodule_from_instance('assign', $params['assignmentid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        $assignment = new assign($context, $cm, null);

        $notices = array();

        if (!$assignment->submissions_open($USER->id)) {
            $notices[] = get_string('duedatereached', 'assign');
        } else {
            $submissiondata = (object)$params['plugindata'];
            $assignment->save_submission($submissiondata, $notices);
        }

        $warnings = array();
        foreach ($notices as $notice) {
            $warnings[] = self::mod_assign_generate_warning($params['assignmentid'],
                                                 'couldnotsavesubmission',
                                                 $notice);
        }

        return $warnings;
    }

    /**
     * Describes the return value for save_submission
     *
     * @return external_single_structure
     * @since Moodle 2.6
     */
    public static function mod_assign_save_submission_returns() {
        return new external_warnings();
    }


    /**
     * Creates an assignment plugin structure.
     *
     * @return external_single_structure the plugin structure
     */
    private static function mod_assign_get_plugin_structure() {
        return new external_single_structure(
            array(
                'type' => new external_value(PARAM_TEXT, 'submission plugin type'),
                'name' => new external_value(PARAM_TEXT, 'submission plugin name'),
                'fileareas' => new external_multiple_structure(
                    new external_single_structure(
                        array (
                            'area' => new external_value (PARAM_TEXT, 'file area'),
                            'files' => new external_multiple_structure(
                                new external_single_structure(
                                    array (
                                        'filepath' => new external_value (PARAM_TEXT, 'file path'),
                                        'fileurl' => new external_value (PARAM_URL, 'file download url',
                                            VALUE_OPTIONAL)
                                    )
                                ), 'files', VALUE_OPTIONAL
                            )
                        )
                    ), 'fileareas', VALUE_OPTIONAL
                ),
                'editorfields' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_TEXT, 'field name'),
                            'description' => new external_value(PARAM_TEXT, 'field description'),
                            'text' => new external_value (PARAM_RAW, 'field value'),
                            'format' => new external_format_value ('text')
                        )
                    )
                    , 'editorfields', VALUE_OPTIONAL
                )
            )
        );
    }

    /**
     * Creates a submission structure.
     *
     * @return external_single_structure the submission structure
     */
    private static function mod_assign_get_submission_structure($required = VALUE_REQUIRED) {
        return new external_single_structure(
            array(
                'id' => new external_value(PARAM_INT, 'submission id'),
                'userid' => new external_value(PARAM_INT, 'student id'),
                'attemptnumber' => new external_value(PARAM_INT, 'attempt number'),
                'timecreated' => new external_value(PARAM_INT, 'submission creation time'),
                'timemodified' => new external_value(PARAM_INT, 'submission last modified time'),
                'status' => new external_value(PARAM_TEXT, 'submission status'),
                'groupid' => new external_value(PARAM_INT, 'group id'),
                'assignment' => new external_value(PARAM_INT, 'assignment id', VALUE_OPTIONAL),
                'latest' => new external_value(PARAM_INT, 'latest attempt', VALUE_OPTIONAL),
                'plugins' => new external_multiple_structure(self::mod_assign_get_plugin_structure(), 'plugins', VALUE_OPTIONAL)
            ), 'submission info', $required
        );
    }

    /**
     * Creates an assign_submissions external_single_structure
     *
     * @return external_single_structure
     * @since Moodle 2.5
     */
    private static function mod_assign_get_submissions_structure() {
        return new external_single_structure(
            array (
                'assignmentid' => new external_value(PARAM_INT, 'assignment id'),
                'submissions' => new external_multiple_structure(self::mod_assign_get_submission_structure())
            )
        );
    }

    /**
     * Creates a grade single structure.
     *
     * @return external_single_structure a grade single structure.
     * @since  Moodle 3.1
     */
    private static function mod_assign_get_grade_structure($required = VALUE_REQUIRED) {
        return new external_single_structure(
            array(
                'id'                => new external_value(PARAM_INT, 'grade id'),
                'assignment'        => new external_value(PARAM_INT, 'assignment id', VALUE_OPTIONAL),
                'userid'            => new external_value(PARAM_INT, 'student id'),
                'attemptnumber'     => new external_value(PARAM_INT, 'attempt number'),
                'timecreated'       => new external_value(PARAM_INT, 'grade creation time'),
                'timemodified'      => new external_value(PARAM_INT, 'grade last modified time'),
                'grader'            => new external_value(PARAM_INT, 'grader'),
                'grade'             => new external_value(PARAM_TEXT, 'grade'),
                'gradefordisplay'   => new external_value(PARAM_RAW, 'grade rendered into a format suitable for display',
                                                            VALUE_OPTIONAL),
            ), 'grade information', $required
        );
    }

   /**
     * Return information (files and text fields) for the given plugins in the assignment.
     *
     * @param  assign $assign the assignment object
     * @param  array $assignplugins array of assignment plugins (submission or feedback)
     * @param  stdClass $item the item object (submission or grade)
     * @return array an array containing the plugins returned information
     */
    private static function mod_assign_get_plugins_data($assign, $assignplugins, $item) {
        global $CFG;

        $plugins = array();
        $fs = get_file_storage();

        foreach ($assignplugins as $assignplugin) {

            if (!$assignplugin->is_enabled() or !$assignplugin->is_visible()) {
                continue;
            }

            $plugin = array(
                'name' => $assignplugin->get_name(),
                'type' => $assignplugin->get_type()
            );
            // Subtype is 'assignsubmission', type is currently 'file' or 'onlinetext'.
            $component = $assignplugin->get_subtype().'_'.$assignplugin->get_type();

            $fileareas = $assignplugin->get_file_areas();
            foreach ($fileareas as $filearea => $name) {
                $fileareainfo = array('area' => $filearea);
                $files = $fs->get_area_files(
                    $assign->get_context()->id,
                    $component,
                    $filearea,
                    $item->id,
                    "timemodified",
                    false
                );
                foreach ($files as $file) {
                    $filepath = $file->get_filepath().$file->get_filename();
                    $fileurl = file_encode_url($CFG->wwwroot . '/webservice/pluginfile.php', '/' . $assign->get_context()->id .
                        '/' . $component. '/'. $filearea . '/' . $item->id . $filepath);
                    $fileinfo = array(
                        'filepath' => $filepath,
                        'fileurl' => $fileurl
                        );
                    $fileareainfo['files'][] = $fileinfo;
                }
                $plugin['fileareas'][] = $fileareainfo;
            }

            $editorfields = $assignplugin->get_editor_fields();
            foreach ($editorfields as $name => $description) {
                $editorfieldinfo = array(
                    'name' => $name,
                    'description' => $description,
                    'text' => $assignplugin->get_editor_text($name, $item->id),
                    'format' => $assignplugin->get_editor_format($name, $item->id)
                );
                $plugin['editorfields'][] = $editorfieldinfo;
            }
            $plugins[] = $plugin;
        }
        return $plugins;
    }

    /**
     * Describes the parameters for mod_assign_view_submission_status.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_assign_view_submission_status_parameters() {
        return new external_function_parameters (
            array(
                'assignid' => new external_value(PARAM_INT, 'assign instance id'),
            )
        );
    }

    /**
     * Trigger the submission status viewed event.
     *
     * @param int $assignid assign instance id
     * @return array of warnings and status result
     * @since Moodle 3.1
     */
    public static function mod_assign_view_submission_status($assignid) {
        global $DB, $CFG;

        $warnings = array();
        $params = array(
            'assignid' => $assignid,
        );
        $params = self::validate_parameters(self::mod_assign_view_submission_status_parameters(), $params);

        // Request and permission validation.
        $assign = $DB->get_record('assign', array('id' => $params['assignid']), 'id', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($assign, 'assign');

        $context = context_module::instance($cm->id);
        // Please, note that is not required to check mod/assign:view because is done by validate_context->require_login.
        self::validate_context($context);

        $assign = new local_mobile_assign($context, $cm, $course);
        \mod_assign\event\submission_status_viewed::create_from_assign($assign)->trigger();

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the mod_assign_view_submission_status return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function mod_assign_view_submission_status_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for mod_assign_get_submission_status.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.1
     */
    public static function mod_assign_get_submission_status_parameters() {
        return new external_function_parameters (
            array(
                'assignid' => new external_value(PARAM_INT, 'assignment instance id'),
                'userid' => new external_value(PARAM_INT, 'user id (empty for current user)', VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Returns information about an assignment submission status for a given user.
     *
     * @param int $assignid assignment instance id
     * @param int $userid user id (empty for current user)
     * @return array of warnings and grading, status, feedback and previous attempts information
     * @since Moodle 3.1
     * @throws required_capability_exception
     */
    public static function mod_assign_get_submission_status($assignid, $userid = 0) {
        global $USER, $DB;

        $warnings = array();

        $params = array(
            'assignid' => $assignid,
            'userid' => $userid,
        );
        $params = self::validate_parameters(self::mod_assign_get_submission_status_parameters(), $params);

        // Request and permission validation.
        $assign = $DB->get_record('assign', array('id' => $params['assignid']), 'id', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($assign, 'assign');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        $assign = new local_mobile_assign($context, $cm, $course);

        // Default value for userid.
        if (empty($params['userid'])) {
            $params['userid'] = $USER->id;
        }
        $user = core_user::get_user($params['userid'], '*', MUST_EXIST);
        core_user::require_active_user($user);

        if (!$assign->can_view_submission($user->id)) {
            throw new required_capability_exception($context, 'mod/assign:viewgrades', 'nopermission', '');
        }

        $gradingsummary = $lastattempt = $feedback = $previousattempts = null;

        // Get the renderable since it contais all the info we need.
        if ($assign->can_view_grades()) {
            $gradingsummary = $assign->get_assign_grading_summary_renderable();
        }

        // Retrieve the rest of the renderable objects.
        if (has_capability('mod/assign:submit', $assign->get_context(), $user)) {
            $lastattempt = $assign->get_assign_submission_status_renderable($user, true);
        }
        $feedback = $assign->get_assign_feedback_status_renderable($user);

        $previousattempts = $assign->get_assign_attempt_history_renderable($user);

        // Now, build the result.
        $result = array();

        // First of all, grading summary, this is suitable for teachers/managers.
        if ($gradingsummary) {
            $result['gradingsummary'] = $gradingsummary;
        }

        // Did we submit anything?
        if ($lastattempt) {
            $submissionplugins = $assign->get_submission_plugins();

            if (empty($lastattempt->submission)) {
                unset($lastattempt->submission);
            } else {
                $lastattempt->submission->plugins = self::mod_assign_get_plugins_data($assign, $submissionplugins, $lastattempt->submission);
            }

            if (empty($lastattempt->teamsubmission)) {
                unset($lastattempt->teamsubmission);
            } else {
                $lastattempt->teamsubmission->plugins = self::mod_assign_get_plugins_data($assign, $submissionplugins,
                                                                                $lastattempt->teamsubmission);
            }

            // We need to change the type of some of the structures retrieved from the renderable.
            if (!empty($lastattempt->submissiongroup)) {
                $lastattempt->submissiongroup = $lastattempt->submissiongroup->id;
            } else {
                unset($lastattempt->submissiongroup);
            }

            if (!empty($lastattempt->usergroups)) {
                $lastattempt->usergroups = array_keys($lastattempt->usergroups);
            } else {
                $lastattempt->usergroups = array();
            }
            // We cannot use array_keys here.
            if (!empty($lastattempt->submissiongroupmemberswhoneedtosubmit)) {
                $lastattempt->submissiongroupmemberswhoneedtosubmit = array_map(
                                                                            function($e){
                                                                                return $e->id;
                                                                            },
                                                                            $lastattempt->submissiongroupmemberswhoneedtosubmit);
            }

            $result['lastattempt'] = $lastattempt;
        }

        // The feedback for our latest submission.
        if ($feedback) {
            if ($feedback->grade) {
                $feedbackplugins = $assign->get_feedback_plugins();
                $feedback->plugins = self::mod_assign_get_plugins_data($assign, $feedbackplugins, $feedback->grade);
            } else {
                unset($feedback->plugins);
                unset($feedback->grade);
            }

            $result['feedback'] = $feedback;
        }

        // Retrieve only previous attempts.
        if ($previousattempts and count($previousattempts->submissions) > 1) {
            // Don't show the last one because it is the current submission.
            array_pop($previousattempts->submissions);

            // Show newest to oldest.
            $previousattempts->submissions = array_reverse($previousattempts->submissions);

            foreach ($previousattempts->submissions as $i => $submission) {
                $attempt = array();

                $grade = null;
                foreach ($previousattempts->grades as $onegrade) {
                    if ($onegrade->attemptnumber == $submission->attemptnumber) {
                        $grade = $onegrade;
                        break;
                    }
                }

                $attempt['attemptnumber'] = $submission->attemptnumber;

                if ($submission) {
                    $submission->plugins = self::mod_assign_get_plugins_data($assign, $previousattempts->submissionplugins, $submission);
                    $attempt['submission'] = $submission;
                }

                if ($grade) {
                    // From object to id.
                    $grade->grader = $grade->grader->id;
                    $feedbackplugins = self::mod_assign_get_plugins_data($assign, $previousattempts->feedbackplugins, $grade);

                    $attempt['grade'] = $grade;
                    $attempt['feedbackplugins'] = $feedbackplugins;
                }
                $result['previousattempts'][] = $attempt;
            }
        }

        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the mod_assign_get_submission_status return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function mod_assign_get_submission_status_returns() {
        return new external_single_structure(
            array(
                'gradingsummary' => new external_single_structure(
                    array(
                        'participantcount' => new external_value(PARAM_INT, 'Number of users who can submit.'),
                        'submissiondraftscount' => new external_value(PARAM_INT, 'Number of submissions in draft status.'),
                        'submissiondraftscount' => new external_value(PARAM_INT, 'Number of submissions in draft status.'),
                        'submissionsenabled' => new external_value(PARAM_BOOL, 'Whether submissions are enabled or not.'),
                        'submissionssubmittedcount' => new external_value(PARAM_INT, 'Number of submissions in submitted status.'),
                        'submissionsneedgradingcount' => new external_value(PARAM_INT, 'Number of submissions that need grading.'),
                        'warnofungroupedusers' => new external_value(PARAM_BOOL, 'Whether we need to warn people that there
                                                                        are users without groups.', VALUE_OPTIONAL),
                    ), 'Grading information.', VALUE_OPTIONAL
                ),
                'lastattempt' => new external_single_structure(
                    array(
                        'submission' => self::mod_assign_get_submission_structure(VALUE_OPTIONAL),
                        'teamsubmission' => self::mod_assign_get_submission_structure(VALUE_OPTIONAL),
                        'submissiongroup' => new external_value(PARAM_INT, 'The submission group id (for group submissions only).',
                                                                VALUE_OPTIONAL),
                        'submissiongroupmemberswhoneedtosubmit' => new external_multiple_structure(
                            new external_value(PARAM_INT, 'USER id.'),
                            'List of users who still need to submit (for group submissions only).',
                            VALUE_OPTIONAL
                        ),
                        'submissionsenabled' => new external_value(PARAM_BOOL, 'Whether submissions are enabled or not.'),
                        'locked' => new external_value(PARAM_BOOL, 'Whether new submissions are locked.'),
                        'graded' => new external_value(PARAM_BOOL, 'Whether the submission is graded.'),
                        'canedit' => new external_value(PARAM_BOOL, 'Whether the user can edit the current submission.'),
                        'cansubmit' => new external_value(PARAM_BOOL, 'Whether the user can submit.'),
                        'extensionduedate' => new external_value(PARAM_INT, 'Extension due date.'),
                        'blindmarking' => new external_value(PARAM_BOOL, 'Whether blind marking is enabled.'),
                        'gradingstatus' => new external_value(PARAM_ALPHANUMEXT, 'Grading status.'),
                        'usergroups' => new external_multiple_structure(
                            new external_value(PARAM_INT, 'Group id.'), 'User groups in the course.'
                        ),
                    ), 'Last attempt information.', VALUE_OPTIONAL
                ),
                'feedback' => new external_single_structure(
                    array(
                        'grade' => self::mod_assign_get_grade_structure(VALUE_OPTIONAL),
                        'gradefordisplay' => new external_value(PARAM_RAW, 'Grade rendered into a format suitable for display.'),
                        'gradeddate' => new external_value(PARAM_INT, 'The date the user was graded.'),
                        'plugins' => new external_multiple_structure(self::mod_assign_get_plugin_structure(), 'Plugins info.', VALUE_OPTIONAL),
                    ), 'Feedback for the last attempt.', VALUE_OPTIONAL
                ),
                'previousattempts' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'attemptnumber' => new external_value(PARAM_INT, 'Attempt number.'),
                            'submission' => self::mod_assign_get_submission_structure(VALUE_OPTIONAL),
                            'grade' => self::mod_assign_get_grade_structure(VALUE_OPTIONAL),
                            'feedbackplugins' => new external_multiple_structure(self::mod_assign_get_plugin_structure(), 'Feedback info.',
                                                                                    VALUE_OPTIONAL),
                        )
                    ), 'List all the previous attempts did by the user.', VALUE_OPTIONAL
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for view_assign.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.2
     */
    public static function mod_assign_view_assign_parameters() {
        return new external_function_parameters (
            array(
                'assignid' => new external_value(PARAM_INT, 'assign instance id'),
            )
        );
    }

    /**
     * Update the module completion status.
     *
     * @param int $assignid assign instance id
     * @return array of warnings and status result
     * @since Moodle 3.2
     */
    public static function mod_assign_view_assign($assignid) {
        $warnings = array();
        $params = array(
            'assignid' => $assignid,
        );
        $params = self::validate_parameters(self::mod_assign_view_assign_parameters(), $params);

        // Request and permission validation.
        $assign = $DB->get_record('assign', array('id' => $assignid), 'id', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($assign, 'assign');

        $context = context_module::instance($cm->id);
        // Please, note that is not required to check mod/assign:view because is done by validate_context->require_login.
        self::validate_context($context);

        $completion = new completion_info($course);
        $completion->set_module_viewed($cm);

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the mod_assign_view_assign return value.
     *
     * @return external_single_structure
     * @since Moodle 3.2
     */
    public static function mod_assign_view_assign_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.2
     */
    public static function core_course_get_user_navigation_options_parameters() {
        return new external_function_parameters(
            array(
                'courseids' => new external_multiple_structure(new external_value(PARAM_INT, 'Course id.')),
            )
        );
    }

    /**
     * Return a list of navigation options in a set of courses that are avaialable or not for the current user.
     *
     * @param array $courseids a list of course ids
     * @return array of warnings and the options availability
     * @since Moodle 3.2
     * @throws moodle_exception
     */
    public static function core_course_get_user_navigation_options($courseids) {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        // Parameter validation.
        $params = self::validate_parameters(self::core_course_get_user_navigation_options_parameters(), array('courseids' => $courseids));
        $courseoptions = array();

        list($courses, $warnings) = external_util::validate_courses($params['courseids'], array(), true);

        if (!empty($courses)) {
            foreach ($courses as $course) {
                // Fix the context for the frontpage.
                if ($course->id == SITEID) {
                    $course->context = context_system::instance();
                }
                $course->context = context_course::instance($course->id);
                $navoptions = course_get_user_navigation_options($course->context, $course);
                $options = array();
                foreach ($navoptions as $name => $available) {
                    $options[] = array(
                        'name' => $name,
                        'available' => $available,
                    );
                }

                $courseoptions[] = array(
                    'id' => $course->id,
                    'options' => $options
                );
            }
        }

        $result = array(
            'courses' => $courseoptions,
            'warnings' => $warnings
        );
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.2
     */
    public static function core_course_get_user_navigation_options_returns() {
        return new external_single_structure(
            array(
                'courses' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Course id'),
                            'options' => new external_multiple_structure(
                                new external_single_structure(
                                    array(
                                        'name' => new external_value(PARAM_ALPHANUMEXT, 'Option name'),
                                        'available' => new external_value(PARAM_BOOL, 'Whether the option is available or not'),
                                    )
                                )
                            )
                        )
                    ), 'List of courses'
                ),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.2
     */
    public static function core_course_get_user_administration_options_parameters() {
        return new external_function_parameters(
            array(
                'courseids' => new external_multiple_structure(new external_value(PARAM_INT, 'Course id.')),
            )
        );
    }

    /**
     * Return a list of administration options in a set of courses that are available or not for the current user.
     *
     * @param array $courseids a list of course ids
     * @return array of warnings and the options availability
     * @since Moodle 3.2
     * @throws moodle_exception
     */
    public static function core_course_get_user_administration_options($courseids) {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        // Parameter validation.
        $params = self::validate_parameters(self::core_course_get_user_administration_options_parameters(), array('courseids' => $courseids));
        $courseoptions = array();

        list($courses, $warnings) = external_util::validate_courses($params['courseids'], array(), true);

        if (!empty($courses)) {
            foreach ($courses as $course) {
                $course->context = context_course::instance($course->id);
                $adminoptions = course_get_user_administration_options($course, $course->context);
                $options = array();
                foreach ($adminoptions as $name => $available) {
                    $options[] = array(
                        'name' => $name,
                        'available' => $available,
                    );
                }

                $courseoptions[] = array(
                    'id' => $course->id,
                    'options' => $options
                );
            }
        }

        $result = array(
            'courses' => $courseoptions,
            'warnings' => $warnings
        );
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.2
     */
    public static function core_course_get_user_administration_options_returns() {
        return self::core_course_get_user_navigation_options_returns();
    }

    /**
     * Returns description of get_config() parameters.
     *
     * @return external_function_parameters
     * @since  Moodle 3.2
     */
    public static function tool_mobile_get_config_parameters() {
        return new external_function_parameters(
            array(
                'section' => new external_value(PARAM_ALPHANUMEXT, 'Settings section name.', VALUE_DEFAULT, ''),
            )
        );
    }

    /**
     * Returns a list of site settings, filtering by section.
     *
     * @param string $section settings section name
     * @return array with the settings and warnings
     * @since  Moodle 3.2
     */
    public static function tool_mobile_get_config($section = '') {
        global $CFG, $SITE;

        $params = self::validate_parameters(self::tool_mobile_get_config_parameters(), array('section' => $section));

        $section = $params['section'];

        $settings = new stdClass;
        $context = context_system::instance();
        $isadmin = has_capability('moodle/site:config', $context);

        if (empty($section) or $section == 'frontpagesettings') {
            require_once($CFG->dirroot . '/course/format/lib.php');
            // First settings that anyone can deduce.
            $settings->fullname = $SITE->fullname;
            $settings->shortname = $SITE->shortname;
            $settings->summary = $SITE->summary;
            $settings->frontpage = $CFG->frontpage;
            $settings->frontpageloggedin = $CFG->frontpageloggedin;
            $settings->maxcategorydepth = $CFG->maxcategorydepth;
            $settings->frontpagecourselimit = $CFG->frontpagecourselimit;
            $settings->numsections = course_get_format($SITE)->get_course()->numsections;
            $settings->newsitems = $SITE->newsitems;
            $settings->commentsperpage = $CFG->commentsperpage;

            // Now, admin settings.
            if ($isadmin) {
                $settings->defaultfrontpageroleid = $CFG->defaultfrontpageroleid;
            }
        }

        if (empty($section) or $section == 'sitepolicies') {
            $settings->disableuserimages = $CFG->disableuserimages;
        }

        if (empty($section) or $section == 'gradessettings') {
            require_once($CFG->dirroot . '/user/lib.php');
            $settings->mygradesurl = user_mygrades_url()->out(false);
        }

        if (empty($section) or $section == 'mobileapp') {
            $settings->tool_mobile_forcelogout = get_config('local_mobile', 'forcelogout');
            $settings->tool_mobile_customlangstrings = get_config('local_mobile', 'customlangstrings');
            $settings->tool_mobile_disabledfeatures = get_config('local_mobile', 'disabledfeatures');
            $settings->tool_mobile_custommenuitems = get_config('local_mobile', 'custommenuitems');
        }

        $result['settings'] = array();
        foreach ($settings as $name => $value) {
            $result['settings'][] = array(
                'name' => $name,
                'value' => $value,
            );
        }

        $result['warnings'] = array();
        return $result;
    }

    /**
     * Returns description of tool_mobile_get_config() result value.
     *
     * @return external_description
     * @since  Moodle 3.2
     */
    public static function tool_mobile_get_config_returns() {
        return new external_single_structure(
            array(
                'settings' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_RAW, 'The name of the setting'),
                            'value' => new external_value(PARAM_RAW, 'The value of the setting'),
                        )
                    ),
                    'Settings'
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for core_badges_get_user_badges.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.1
     */
    public static function core_badges_get_user_badges_parameters() {
        return new external_function_parameters (
            array(
                'userid' => new external_value(PARAM_INT, 'Badges only for this user id, empty for current user', VALUE_DEFAULT, 0),
                'courseid' => new external_value(PARAM_INT, 'Filter badges by course id, empty all the courses', VALUE_DEFAULT, 0),
                'page' => new external_value(PARAM_INT, 'The page of records to return.', VALUE_DEFAULT, 0),
                'perpage' => new external_value(PARAM_INT, 'The number of records to return per page', VALUE_DEFAULT, 0),
                'search' => new external_value(PARAM_RAW, 'A simple string to search for', VALUE_DEFAULT, ''),
                'onlypublic' => new external_value(PARAM_BOOL, 'Whether to return only public badges', VALUE_DEFAULT, false),
            )
        );
    }

    /**
     * Returns the list of badges awarded to a user.
     *
     * @param int $userid       user id
     * @param int $courseid     course id
     * @param int $page         page of records to return
     * @param int $perpage      number of records to return per page
     * @param string  $search   a simple string to search for
     * @param bool $onlypublic  whether to return only public badges
     * @return array array containing warnings and the awarded badges
     * @since  Moodle 3.1
     * @throws moodle_exception
     */
    public static function core_badges_get_user_badges($userid = 0, $courseid = 0, $page = 0, $perpage = 0, $search = '', $onlypublic = false) {
        global $CFG, $USER;
        require_once($CFG->libdir . '/badgeslib.php');

        $warnings = array();

        $params = array(
            'userid' => $userid,
            'courseid' => $courseid,
            'page' => $page,
            'perpage' => $perpage,
            'search' => $search,
            'onlypublic' => $onlypublic,
        );
        $params = self::validate_parameters(self::core_badges_get_user_badges_parameters(), $params);

        if (empty($CFG->enablebadges)) {
            throw new moodle_exception('badgesdisabled', 'badges');
        }

        if (empty($CFG->badges_allowcoursebadges) && $params['courseid'] != 0) {
            throw new moodle_exception('coursebadgesdisabled', 'badges');
        }

        // Default value for userid.
        if (empty($params['userid'])) {
            $params['userid'] = $USER->id;
        }

        // Validate the user.
        $user = core_user::get_user($params['userid'], '*', MUST_EXIST);
        core_user::require_active_user($user);

        $usercontext = context_user::instance($user->id);
        self::validate_context($usercontext);

        if ($USER->id != $user->id) {
            require_capability('moodle/badges:viewotherbadges', $usercontext);
            // We are looking other user's badges, we must retrieve only public badges.
            $params['onlypublic'] = true;
        }

        $userbadges = badges_get_user_badges($user->id, $params['courseid'], $params['page'], $params['perpage'], $params['search'],
                                                $params['onlypublic']);

        $result = array();
        $result['badges'] = array();
        $result['warnings'] = $warnings;

        foreach ($userbadges as $badge) {
            $context = ($badge->type == BADGE_TYPE_SITE) ? context_system::instance() : context_course::instance($badge->courseid);
            $badge->badgeurl = moodle_url::make_webservice_pluginfile_url($context->id, 'badges', 'badgeimage', $badge->id, '/',
                                                                            'f1')->out(false);
            // Return all the information if we are requesting our own badges.
            // Or, if we have permissions for configuring badges in the badge context.
            if ($USER->id == $user->id or has_capability('moodle/badges:configuredetails', $context)) {
                $result['badges'][] = (array) $badge;
            } else {
                $result['badges'][] = array(
                    'name' => $badge->name,
                    'description' => $badge->description,
                    'badgeurl' => $badge->badgeurl,
                    'issuername' => $badge->issuername,
                    'issuerurl' => $badge->issuerurl,
                    'issuercontact' => $badge->issuercontact,
                    'uniquehash' => $badge->uniquehash,
                    'dateissued' => $badge->dateissued,
                    'dateexpire' => $badge->dateexpire,
                );
            }
        }

        return $result;
    }

    /**
     * Describes the core_badges_get_user_badges return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function core_badges_get_user_badges_returns() {
        return new external_single_structure(
            array(
                'badges' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Badge id.', VALUE_OPTIONAL),
                            'name' => new external_value(PARAM_FILE, 'Badge name.'),
                            'description' => new external_value(PARAM_NOTAGS, 'Badge description.'),
                            'badgeurl' => new external_value(PARAM_URL, 'Badge URL.'),
                            'timecreated' => new external_value(PARAM_INT, 'Time created.', VALUE_OPTIONAL),
                            'timemodified' => new external_value(PARAM_INT, 'Time modified.', VALUE_OPTIONAL),
                            'usercreated' => new external_value(PARAM_INT, 'User created.', VALUE_OPTIONAL),
                            'usermodified' => new external_value(PARAM_INT, 'User modified.', VALUE_OPTIONAL),
                            'issuername' => new external_value(PARAM_NOTAGS, 'Issuer name.'),
                            'issuerurl' => new external_value(PARAM_URL, 'Issuer URL.'),
                            'issuercontact' => new external_value(PARAM_RAW, 'Issuer contact.'),
                            'expiredate' => new external_value(PARAM_INT, 'Expire date.', VALUE_OPTIONAL),
                            'expireperiod' => new external_value(PARAM_INT, 'Expire period.', VALUE_OPTIONAL),
                            'type' => new external_value(PARAM_INT, 'Type.', VALUE_OPTIONAL),
                            'courseid' => new external_value(PARAM_INT, 'Course id.', VALUE_OPTIONAL),
                            'message' => new external_value(PARAM_RAW, 'Message.', VALUE_OPTIONAL),
                            'messagesubject' => new external_value(PARAM_TEXT, 'Message subject.', VALUE_OPTIONAL),
                            'attachment' => new external_value(PARAM_INT, 'Attachment.', VALUE_OPTIONAL),
                            'status' => new external_value(PARAM_INT, 'Status.', VALUE_OPTIONAL),
                            'issuedid' => new external_value(PARAM_INT, 'Issued id.', VALUE_OPTIONAL),
                            'uniquehash' => new external_value(PARAM_ALPHANUM, 'Unique hash.'),
                            'dateissued' => new external_value(PARAM_INT, 'Date issued.'),
                            'dateexpire' => new external_value(PARAM_INT, 'Date expire.'),
                            'visible' => new external_value(PARAM_INT, 'Visible.', VALUE_OPTIONAL),
                        )
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Returns a course structure definition
     *
     * @param  boolean $onlypublicdata set to true, to retrieve only fields viewable by anyone when the course is visible
     * @return array the course structure
     * @since  Moodle 3.2
     */
    protected static function core_course_get_course_structure($onlypublicdata = true) {
        $coursestructure = array(
            'id' => new external_value(PARAM_INT, 'course id'),
            'fullname' => new external_value(PARAM_TEXT, 'course full name'),
            'displayname' => new external_value(PARAM_TEXT, 'course display name'),
            'shortname' => new external_value(PARAM_TEXT, 'course short name'),
            'categoryid' => new external_value(PARAM_INT, 'category id'),
            'categoryname' => new external_value(PARAM_TEXT, 'category name'),
            'summary' => new external_value(PARAM_RAW, 'summary'),
            'summaryformat' => new external_format_value('summary'),
            'summaryfiles' => new external_files('summary files in the summary field', VALUE_OPTIONAL),
            'overviewfiles' => new external_files('additional overview files attached to this course'),
            'contacts' => new external_multiple_structure(
                new external_single_structure(
                    array(
                        'id' => new external_value(PARAM_INT, 'contact user id'),
                        'fullname'  => new external_value(PARAM_NOTAGS, 'contact user fullname'),
                    )
                ),
                'contact users'
            ),
            'enrollmentmethods' => new external_multiple_structure(
                new external_value(PARAM_PLUGIN, 'enrollment method'),
                'enrollment methods list'
            ),
        );

        if (!$onlypublicdata) {
            $extra = array(
                'idnumber' => new external_value(PARAM_RAW, 'Id number', VALUE_OPTIONAL),
                'format' => new external_value(PARAM_PLUGIN, 'Course format: weeks, topics, social, site,..', VALUE_OPTIONAL),
                'showgrades' => new external_value(PARAM_INT, '1 if grades are shown, otherwise 0', VALUE_OPTIONAL),
                'newsitems' => new external_value(PARAM_INT, 'Number of recent items appearing on the course page', VALUE_OPTIONAL),
                'startdate' => new external_value(PARAM_INT, 'Timestamp when the course start', VALUE_OPTIONAL),
                'maxbytes' => new external_value(PARAM_INT, 'Largest size of file that can be uploaded into', VALUE_OPTIONAL),
                'showreports' => new external_value(PARAM_INT, 'Are activity report shown (yes = 1, no =0)', VALUE_OPTIONAL),
                'visible' => new external_value(PARAM_INT, '1: available to student, 0:not available', VALUE_OPTIONAL),
                'groupmode' => new external_value(PARAM_INT, 'no group, separate, visible', VALUE_OPTIONAL),
                'groupmodeforce' => new external_value(PARAM_INT, '1: yes, 0: no', VALUE_OPTIONAL),
                'defaultgroupingid' => new external_value(PARAM_INT, 'default grouping id', VALUE_OPTIONAL),
                'enablecompletion' => new external_value(PARAM_INT, 'Completion enabled? 1: yes 0: no', VALUE_OPTIONAL),
                'completionnotify' => new external_value(PARAM_INT, '1: yes 0: no', VALUE_OPTIONAL),
                'lang' => new external_value(PARAM_SAFEDIR, 'Forced course language', VALUE_OPTIONAL),
                'theme' => new external_value(PARAM_PLUGIN, 'Fame of the forced theme', VALUE_OPTIONAL),
                'sortorder' => new external_value(PARAM_INT, 'Sort order in the category', VALUE_OPTIONAL),
                'marker' => new external_value(PARAM_INT, 'Current course marker', VALUE_OPTIONAL),
                'legacyfiles' => new external_value(PARAM_INT, 'If legacy files are enabled', VALUE_OPTIONAL),
                'calendartype' => new external_value(PARAM_PLUGIN, 'Calendar type', VALUE_OPTIONAL),
                'timecreated' => new external_value(PARAM_INT, 'Time when the course was created', VALUE_OPTIONAL),
                'timemodified' => new external_value(PARAM_INT, 'Last time  the course was updated', VALUE_OPTIONAL),
                'requested' => new external_value(PARAM_INT, 'If is a requested course', VALUE_OPTIONAL),
                'cacherev' => new external_value(PARAM_INT, 'Cache revision number', VALUE_OPTIONAL),
                'filters' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'filter'  => new external_value(PARAM_PLUGIN, 'Filter plugin name'),
                            'localstate' => new external_value(PARAM_INT, 'Filter state: 1 for on, -1 for off, 0 if inherit'),
                            'inheritedstate' => new external_value(PARAM_INT, '1 or 0 to use when localstate is set to inherit'),
                        )
                    ),
                    'Course filters', VALUE_OPTIONAL
                ),
            );
            $coursestructure = array_merge($coursestructure, $extra);
        }
        return new external_single_structure($coursestructure);
    }

    /**
     * Return the course information that is public (visible by every one)
     *
     * @param  course_in_list $course        course in list object
     * @param  stdClass       $coursecontext course context object
     * @return array the course information
     * @since  Moodle 3.2
     */
    protected static function core_course_get_course_public_information(course_in_list $course, $coursecontext) {

        static $categoriescache = array();

        // Category information.
        if (!array_key_exists($course->category, $categoriescache)) {
            $categoriescache[$course->category] = coursecat::get($course->category, IGNORE_MISSING);
        }
        $category = $categoriescache[$course->category];

        // Retrieve course overview used files.
        $files = array();
        foreach ($course->get_course_overviewfiles() as $file) {
            $fileurl = moodle_url::make_webservice_pluginfile_url($file->get_contextid(), $file->get_component(),
                                                                    $file->get_filearea(), null, $file->get_filepath(),
                                                                    $file->get_filename())->out(false);
            $files[] = array(
                'filename' => $file->get_filename(),
                'fileurl' => $fileurl,
                'filesize' => $file->get_filesize(),
                'filepath' => $file->get_filepath(),
                'mimetype' => $file->get_mimetype(),
                'timemodified' => $file->get_timemodified(),
            );
        }

        // Retrieve the course contacts,
        // we need here the users fullname since if we are not enrolled can be difficult to obtain them via other Web Services.
        $coursecontacts = array();
        foreach ($course->get_course_contacts() as $contact) {
             $coursecontacts[] = array(
                'id' => $contact['user']->id,
                'fullname' => $contact['username']
            );
        }

        // Allowed enrolment methods (maybe we can self-enrol).
        $enroltypes = array();
        $instances = enrol_get_instances($course->id, true);
        foreach ($instances as $instance) {
            $enroltypes[] = $instance->enrol;
        }

        // Format summary.
        list($summary, $summaryformat) =
            external_format_text($course->summary, $course->summaryformat, $coursecontext->id, 'course', 'summary', null);

        $displayname = get_course_display_name_for_list($course);
        $coursereturns = array();
        $coursereturns['id']                = $course->id;
        $coursereturns['fullname']          = external_format_string($course->fullname, $coursecontext->id);
        $coursereturns['displayname']       = external_format_string($displayname, $coursecontext->id);
        $coursereturns['shortname']         = external_format_string($course->shortname, $coursecontext->id);
        $coursereturns['categoryid']        = $course->category;
        $coursereturns['categoryname']      = $category == null ? '' : $category->name;
        $coursereturns['summary']           = $summary;
        $coursereturns['summaryformat']     = $summaryformat;
        $coursereturns['summaryfiles']      = local_mobile_get_area_files($coursecontext->id, 'course', 'summary', false, false);
        $coursereturns['overviewfiles']     = $files;
        $coursereturns['contacts']          = $coursecontacts;
        $coursereturns['enrollmentmethods'] = $enroltypes;
        $coursereturns['sortorder'] = $course->sortorder;
        return $coursereturns;
    }


    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.2
     */
    public static function core_course_get_courses_by_field_parameters() {
        return new external_function_parameters(
            array(
                'field' => new external_value(PARAM_ALPHA, 'The field to search can be left empty for all courses or:
                    id: course id
                    ids: comma separated course ids
                    shortname: course short name
                    idnumber: course id number
                    category: category id the course belongs to
                ', VALUE_DEFAULT, ''),
                'value' => new external_value(PARAM_RAW, 'The value to match', VALUE_DEFAULT, '')
            )
        );
    }


    /**
     * Get courses matching a specific field (id/s, shortname, idnumber, category)
     *
     * @param  string $field field name to search, or empty for all courses
     * @param  string $value value to search
     * @return array list of courses and warnings
     * @throws  invalid_parameter_exception
     * @since Moodle 3.2
     */
    public static function core_course_get_courses_by_field($field = '', $value = '') {
        global $DB, $CFG;
        require_once($CFG->libdir . '/coursecatlib.php');
        require_once($CFG->libdir . '/filterlib.php');

        $params = self::validate_parameters(self::core_course_get_courses_by_field_parameters(),
            array(
                'field' => $field,
                'value' => $value,
            )
        );
        $warnings = array();

        if (empty($params['field'])) {
            $courses = $DB->get_records('course', null, 'id ASC');
        } else {
            switch ($params['field']) {
                case 'id':
                case 'category':
                    $value = clean_param($params['value'], PARAM_INT);
                    break;
                case 'ids':
                    $value = clean_param($params['value'], PARAM_SEQUENCE);
                    break;
                case 'shortname':
                    $value = clean_param($params['value'], PARAM_TEXT);
                    break;
                case 'idnumber':
                    $value = clean_param($params['value'], PARAM_RAW);
                    break;
                default:
                    throw new invalid_parameter_exception('Invalid field name');
            }

            if ($params['field'] === 'ids') {
                $courses = $DB->get_records_list('course', 'id', explode(',', $value), 'id ASC');
            } else {
                $courses = $DB->get_records('course', array($params['field'] => $value), 'id ASC');
            }
        }

        $coursesdata = array();
        foreach ($courses as $course) {
            $context = context_course::instance($course->id);
            $canupdatecourse = has_capability('moodle/course:update', $context);
            $canviewhiddencourses = has_capability('moodle/course:viewhiddencourses', $context);

            // Check if the course is visible in the site for the user.
            if (!$course->visible and !$canviewhiddencourses and !$canupdatecourse) {
                continue;
            }
            // Get the public course information, even if we are not enrolled.
            $courseinlist = new course_in_list($course);
            $coursesdata[$course->id] = self::core_course_get_course_public_information($courseinlist, $context);

            // Now, check if we have access to the course.
            try {
                self::validate_context($context);
            } catch (Exception $e) {
                continue;
            }
            // Return information for any user that can access the course.
            $coursefields = array('format', 'showgrades', 'newsitems', 'startdate', 'maxbytes', 'showreports', 'visible',
                'groupmode', 'groupmodeforce', 'defaultgroupingid', 'enablecompletion', 'completionnotify', 'lang', 'theme',
                'sortorder', 'marker');

            // Course filters.
            $coursesdata[$course->id]['filters'] = filter_get_available_in_context($context);

            // Information for managers only.
            if ($canupdatecourse) {
                $managerfields = array('idnumber', 'legacyfiles', 'calendartype', 'timecreated', 'timemodified', 'requested',
                    'cacherev');
                $coursefields = array_merge($coursefields, $managerfields);
            }

            // Populate fields.
            foreach ($coursefields as $field) {
                $coursesdata[$course->id][$field] = $course->{$field};
            }
        }

        return array(
            'courses' => $coursesdata,
            'warnings' => $warnings
        );
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.2
     */
    public static function core_course_get_courses_by_field_returns() {
        // Course structure, including not only public viewable fields.
        return new external_single_structure(
            array(
                'courses' => new external_multiple_structure(self::core_course_get_course_structure(false), 'Course'),
                'warnings' => new external_warnings()
            )
        );
    }
}

