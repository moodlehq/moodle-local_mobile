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

        $params = self::mod_wiki_validate_parameters(self::view_page_parameters(),
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

}