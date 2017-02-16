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

require_once($CFG->libdir . '/enrollib.php');

function enrol_guest_get_enrol_info($enrolinstance) {
    $enrolplugin = enrol_get_plugin('guest');

    $instanceinfo = new stdClass();
    $instanceinfo->id = $enrolinstance->id;
    $instanceinfo->courseid = $enrolinstance->courseid;
    $instanceinfo->type = $enrolplugin->get_name();
    $instanceinfo->name = $enrolplugin->get_instance_name($enrolinstance);
    $instanceinfo->status = $enrolinstance->status == ENROL_INSTANCE_ENABLED;
    // Specifics enrolment method parameters.
    $instanceinfo->requiredparam = new stdClass();
    $instanceinfo->requiredparam->passwordrequired = !empty($enrolinstance->password);

    // If the plugin is enabled, return the URL for obtaining more information.
    if ($instanceinfo->status) {
        $instanceinfo->wsfunction = 'enrol_guest_get_instance_info';
    }
    return $instanceinfo;
}

require_once("$CFG->dirroot/mod/glossary/lib.php");

if (!file_exists("$CFG->dirroot/mod/glossary/classes/entry_query_builder.php")) {
    /**
     * Entry query builder class.
     *
     * The purpose of this class is to avoid duplicating SQL statements to fetch entries
     * which are very similar with each other. This builder is not meant to be smart, it
     * will not out rule any previously set condition, or join, etc...
     *
     * You should be using this builder just like you would be creating your SQL query. Only
     * some methods are shorthands to avoid logic duplication and common mistakes.
     *
     * @package    mod_glossary
     * @copyright  2015 Frédéric Massart - FMCorz.net
     * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     * @since      Moodle 3.1
     */
    class mod_glossary_entry_query_builder {

        /** Alias for table glossary_alias. */
        const ALIAS_ALIAS = 'ga';
        /** Alias for table glossary_categories. */
        const ALIAS_CATEGORIES = 'gc';
        /** Alias for table glossary_entries_categories. */
        const ALIAS_ENTRIES_CATEGORIES = 'gec';
        /** Alias for table glossary_entries. */
        const ALIAS_ENTRIES = 'ge';
        /** Alias for table user. */
        const ALIAS_USER = 'u';

        /** Include none of the entries to approve. */
        const NON_APPROVED_NONE = 'na_none';
        /** Including all the entries. */
        const NON_APPROVED_ALL = 'na_all';
        /** Including only the entries to be approved. */
        const NON_APPROVED_ONLY = 'na_only';
        /** Including my entries to be approved. */
        const NON_APPROVED_SELF = 'na_self';

        /** @var array Raw SQL statements representing the fields to select. */
        protected $fields = array();
        /** @var array Raw SQL statements representing the JOINs to make. */
        protected $joins = array();
        /** @var string Raw SQL statement representing the FROM clause. */
        protected $from;
        /** @var object The glossary we are fetching from. */
        protected $glossary;
        /** @var int The number of records to fetch from. */
        protected $limitfrom = 0;
        /** @var int The number of records to fetch. */
        protected $limitnum = 0;
        /** @var array List of SQL parameters. */
        protected $params = array();
        /** @var array Raw SQL statements representing the ORDER clause. */
        protected $order = array();
        /** @var array Raw SQL statements representing the WHERE clause. */
        protected $where = array();

        /**
         * Constructor.
         *
         * @param object $glossary The glossary.
         */
        public function __construct($glossary = null) {
            $this->from = sprintf('FROM {glossary_entries} %s', self::ALIAS_ENTRIES);
            if (!empty($glossary)) {
                $this->glossary = $glossary;
                $this->where[] = sprintf('(%s.glossaryid = :gid OR %s.sourceglossaryid = :gid2)',
                    self::ALIAS_ENTRIES, self::ALIAS_ENTRIES);
                $this->params['gid'] = $glossary->id;
                $this->params['gid2'] = $glossary->id;
            }
        }

        /**
         * Add a field to select.
         *
         * @param string $field The field, or *.
         * @param string $table The table name, without the prefix 'glossary_'.
         * @param string $alias An alias for the field.
         */
        public function add_field($field, $table, $alias = null) {
            $field = self::resolve_field($field, $table);
            if (!empty($alias)) {
                $field .= ' AS ' . $alias;
            }
            $this->fields[] = $field;
        }

        /**
         * Adds the user fields.
         *
         * @return void
         */
        public function add_user_fields() {
            $this->fields[] = user_picture::fields('u', null, 'userdataid', 'userdata');
        }

        /**
         * Internal method to build the query.
         *
         * @param bool $count Query to count?
         * @return string The SQL statement.
         */
        protected function build_query($count = false) {
            $sql = 'SELECT ';

            if ($count) {
                $sql .= 'COUNT(\'x\') ';
            } else {
                $sql .= implode(', ', $this->fields) . ' ';
            }

            $sql .= $this->from . ' ';
            $sql .= implode(' ', $this->joins) . ' ';

            if (!empty($this->where)) {
                $sql .= 'WHERE (' . implode(') AND (', $this->where) . ') ';
            }

            if (!$count && !empty($this->order)) {
                $sql .= 'ORDER BY ' . implode(', ', $this->order);
            }

            return $sql;
        }

        /**
         * Count the records.
         *
         * @return int The number of records.
         */
        public function count_records() {
            global $DB;
            return $DB->count_records_sql($this->build_query(true), $this->params);
        }

        /**
         * Filter a field using a letter.
         *
         * @param string $letter     The letter.
         * @param string $finalfield The SQL statement representing the field.
         */
        protected function filter_by_letter($letter, $finalfield) {
            global $DB;

            $letter = core_text::strtoupper($letter);
            $len = core_text::strlen($letter);
            $sql = $DB->sql_substr(sprintf('upper(%s)', $finalfield), 1, $len);

            $this->where[] = "$sql = :letter";
            $this->params['letter'] = $letter;
        }

        /**
         * Filter a field by special characters.
         *
         * @param string $finalfield The SQL statement representing the field.
         */
        protected function filter_by_non_letter($finalfield) {
            global $DB;

            $alphabet = explode(',', get_string('alphabet', 'langconfig'));
            list($nia, $aparams) = $DB->get_in_or_equal($alphabet, SQL_PARAMS_NAMED, 'nonletter', false);

            $sql = $DB->sql_substr(sprintf('upper(%s)', $finalfield), 1, 1);

            $this->where[] = "$sql $nia";
            $this->params = array_merge($this->params, $aparams);
        }

        /**
         * Filter the author by letter.
         *
         * @param string  $letter         The letter.
         * @param bool    $firstnamefirst Whether or not the firstname is first in the author's name.
         */
        public function filter_by_author_letter($letter, $firstnamefirst = false) {
            $field = self::get_fullname_field($firstnamefirst);
            $this->filter_by_letter($letter, $field);
        }

        /**
         * Filter the author by special characters.
         *
         * @param bool $firstnamefirst Whether or not the firstname is first in the author's name.
         */
        public function filter_by_author_non_letter($firstnamefirst = false) {
            $field = self::get_fullname_field($firstnamefirst);
            $this->filter_by_non_letter($field);
        }

        /**
         * Filter the concept by letter.
         *
         * @param string  $letter         The letter.
         */
        public function filter_by_concept_letter($letter) {
            $this->filter_by_letter($letter, self::resolve_field('concept', 'entries'));
        }

        /**
         * Filter the concept by special characters.
         *
         * @return void
         */
        public function filter_by_concept_non_letter() {
            $this->filter_by_non_letter(self::resolve_field('concept', 'entries'));
        }

        /**
         * Filter non approved entries.
         *
         * @param string $constant One of the NON_APPROVED_* constants.
         * @param int    $userid   The user ID when relevant, otherwise current user.
         */
        public function filter_by_non_approved($constant, $userid = null) {
            global $USER;
            if (!$userid) {
                $userid = $USER->id;
            }

            if ($constant === self::NON_APPROVED_ALL) {
                // Nothing to do.

            } else if ($constant === self::NON_APPROVED_SELF) {
                $this->where[] = sprintf('%s != 0 OR %s = :toapproveuserid',
                    self::resolve_field('approved', 'entries'), self::resolve_field('userid', 'entries'));
                $this->params['toapproveuserid'] = $USER->id;

            } else if ($constant === self::NON_APPROVED_NONE) {
                $this->where[] = sprintf('%s != 0', self::resolve_field('approved', 'entries'));

            } else if ($constant === self::NON_APPROVED_ONLY) {
                $this->where[] = sprintf('%s = 0', self::resolve_field('approved', 'entries'));

            } else {
                throw new coding_exception('Invalid constant');
            }
        }

        /**
         * Filter by concept or alias.
         *
         * This requires the alias table to be joined in the query. See {@link self::join_alias()}.
         *
         * @param string $term What the concept or aliases should be.
         */
        public function filter_by_term($term) {
            $this->where[] = sprintf("(%s = :filterterma OR %s = :filtertermb)",
                self::resolve_field('concept', 'entries'),
                self::resolve_field('alias', 'alias'));
            $this->params['filterterma'] = $term;
            $this->params['filtertermb'] = $term;
        }

        /**
         * Convenience method to get get the SQL statement for the full name.
         *
         * @param bool $firstnamefirst Whether or not the firstname is first in the author's name.
         * @return string The SQL statement.
         */
        public static function get_fullname_field($firstnamefirst = false) {
            global $DB;
            if ($firstnamefirst) {
                return $DB->sql_fullname(self::resolve_field('firstname', 'user'), self::resolve_field('lastname', 'user'));
            }
            return $DB->sql_fullname(self::resolve_field('lastname', 'user'), self::resolve_field('firstname', 'user'));
        }

        /**
         * Get the records.
         *
         * @return array
         */
        public function get_records() {
            global $DB;
            return $DB->get_records_sql($this->build_query(), $this->params, $this->limitfrom, $this->limitnum);
        }

        /**
         * Get the recordset.
         *
         * @return moodle_recordset
         */
        public function get_recordset() {
            global $DB;
            return $DB->get_recordset_sql($this->build_query(), $this->params, $this->limitfrom, $this->limitnum);
        }

        /**
         * Retrieve a user object from a record.
         *
         * This comes handy when {@link self::add_user_fields} was used.
         *
         * @param stdClass $record The record.
         * @return stdClass A user object.
         */
        public static function get_user_from_record($record) {
            return user_picture::unalias($record, null, 'userdataid', 'userdata');
        }

        /**
         * Join the alias table.
         *
         * Note that this may cause the same entry to be returned more than once. You might want
         * to add a distinct on the entry id.
         *
         * @return void
         */
        public function join_alias() {
            $this->joins[] = sprintf('LEFT JOIN {glossary_alias} %s ON %s = %s',
                self::ALIAS_ALIAS, self::resolve_field('id', 'entries'), self::resolve_field('entryid', 'alias'));
        }

        /**
         * Join on the category tables.
         *
         * Depending on the category passed the joins will be different. This is due to the display
         * logic that assumes that when displaying all categories the non categorised entries should
         * not be returned, etc...
         *
         * @param int $categoryid The category ID, or GLOSSARY_SHOW_* constant.
         */
        public function join_category($categoryid) {

            if ($categoryid === GLOSSARY_SHOW_ALL_CATEGORIES) {
                $this->joins[] = sprintf('JOIN {glossary_entries_categories} %s ON %s = %s',
                    self::ALIAS_ENTRIES_CATEGORIES, self::resolve_field('id', 'entries'),
                    self::resolve_field('entryid', 'entries_categories'));

                $this->joins[] = sprintf('JOIN {glossary_categories} %s ON %s = %s',
                    self::ALIAS_CATEGORIES, self::resolve_field('id', 'categories'),
                    self::resolve_field('categoryid', 'entries_categories'));

            } else if ($categoryid === GLOSSARY_SHOW_NOT_CATEGORISED) {
                $this->joins[] = sprintf('LEFT JOIN {glossary_entries_categories} %s ON %s = %s',
                    self::ALIAS_ENTRIES_CATEGORIES, self::resolve_field('id', 'entries'),
                    self::resolve_field('entryid', 'entries_categories'));

            } else {
                $this->joins[] = sprintf('JOIN {glossary_entries_categories} %s ON %s = %s AND %s = :joincategoryid',
                    self::ALIAS_ENTRIES_CATEGORIES, self::resolve_field('id', 'entries'),
                    self::resolve_field('entryid', 'entries_categories'),
                    self::resolve_field('categoryid', 'entries_categories'));
                $this->params['joincategoryid'] = $categoryid;

            }
        }

        /**
         * Join the user table.
         *
         * @param bool $strict When strict uses a JOIN rather than a LEFT JOIN.
         */
        public function join_user($strict = false) {
            $join = $strict ? 'JOIN' : 'LEFT JOIN';
            $this->joins[] = sprintf("$join {user} %s ON %s = %s",
                self::ALIAS_USER, self::resolve_field('id', 'user'), self::resolve_field('userid', 'entries'));
        }

        /**
         * Limit the number of records to fetch.
         * @param int $from Fetch from.
         * @param int $num  Number to fetch.
         */
        public function limit($from, $num) {
            $this->limitfrom = $from;
            $this->limitnum = $num;
        }

        /**
         * Normalise a direction.
         *
         * This ensures that the value is either ASC or DESC.
         *
         * @param string $direction The desired direction.
         * @return string ASC or DESC.
         */
        protected function normalize_direction($direction) {
            $direction = core_text::strtoupper($direction);
            if ($direction == 'DESC') {
                return 'DESC';
            }
            return 'ASC';
        }

        /**
         * Order by a field.
         *
         * @param string $field The field, or *.
         * @param string $table The table name, without the prefix 'glossary_'.
         * @param string $direction ASC, or DESC.
         */
        public function order_by($field, $table, $direction = '') {
            $direction = self::normalize_direction($direction);
            $this->order[] = self::resolve_field($field, $table) . ' ' . $direction;
        }

        /**
         * Order by author name.
         *
         * @param bool   $firstnamefirst Whether or not the firstname is first in the author's name.
         * @param string $direction ASC, or DESC.
         */
        public function order_by_author($firstnamefirst = false, $direction = '') {
            $field = self::get_fullname_field($firstnamefirst);
            $direction = self::normalize_direction($direction);
            $this->order[] = $field . ' ' . $direction;
        }

        /**
         * Convenience method to transform a field into SQL statement.
         *
         * @param string $field The field, or *.
         * @param string $table The table name, without the prefix 'glossary_'.
         * @return string SQL statement.
         */
        protected static function resolve_field($field, $table) {
            $prefix = constant(__CLASS__ . '::ALIAS_' . core_text::strtoupper($table));
            return sprintf('%s.%s', $prefix, $field);
        }

        /**
         * Simple where conditions.
         *
         * @param string $field The field, or *.
         * @param string $table The table name, without the prefix 'glossary_'.
         * @param mixed $value The value to be equal to.
         */
        public function where($field, $table, $value) {
            static $i = 0;
            $sql = self::resolve_field($field, $table) . ' ';

            if ($value === null) {
                $sql .= 'IS NULL';

            } else {
                $param = 'where' . $i++;
                $sql .= " = :$param";
                $this->params[$param] = $value;
            }

            $this->where[] = $sql;
        }

    }
}

if (!function_exists('glossary_view')) {
    /**
     * Notify that the glossary was viewed.
     *
     * This will trigger relevant events and activity completion.
     *
     * @param stdClass $glossary The glossary object.
     * @param stdClass $course   The course object.
     * @param stdClass $cm       The course module object.
     * @param stdClass $context  The context object.
     * @param string   $mode     The mode in which the glossary was viewed.
     * @since Moodle 3.1
     */
    function glossary_view($glossary, $course, $cm, $context, $mode) {

        // Completion trigger.
        $completion = new completion_info($course);
        $completion->set_module_viewed($cm);

        // Trigger the course module viewed event.
        $event = \mod_glossary\event\course_module_viewed::create(array(
            'objectid' => $glossary->id,
            'context' => $context,
            'other' => array('mode' => $mode)
        ));
        $event->add_record_snapshot('course', $course);
        $event->add_record_snapshot('course_modules', $cm);
        $event->add_record_snapshot('glossary', $glossary);
        $event->trigger();
    }
}

if (!function_exists('glossary_entry_view')) {
    /**
     * Notify that a glossary entry was viewed.
     *
     * This will trigger relevant events.
     *
     * @param stdClass $entry    The entry object.
     * @param stdClass $context  The context object.
     * @since Moodle 3.1
     */
    function glossary_entry_view($entry, $context) {

        // Trigger the entry viewed event.
        $event = \mod_glossary\event\entry_viewed::create(array(
            'objectid' => $entry->id,
            'context' => $context
        ));
        $event->add_record_snapshot('glossary_entries', $entry);
        $event->trigger();

    }
}

if (!function_exists('glossary_get_entries_by_letter')) {
    /**
     * Returns the entries of a glossary by letter.
     *
     * @param  object $glossary The glossary.
     * @param  context $context The context of the glossary.
     * @param  string $letter The letter, or ALL, or SPECIAL.
     * @param  int $from Fetch records from.
     * @param  int $limit Number of records to fetch.
     * @param  array $options Accepts:
     *                        - (bool) includenotapproved. When false, includes the non-approved entries created by
     *                          the current user. When true, also includes the ones that the user has the permission to approve.
     * @return array The first element being the recordset, the second the number of entries.
     * @since Moodle 3.1
     */
    function glossary_get_entries_by_letter($glossary, $context, $letter, $from, $limit, $options = array()) {

        $qb = new mod_glossary_entry_query_builder($glossary);
        if ($letter != 'ALL' && $letter != 'SPECIAL' && core_text::strlen($letter)) {
            $qb->filter_by_concept_letter($letter);
        }
        if ($letter == 'SPECIAL') {
            $qb->filter_by_concept_non_letter();
        }

        if (!empty($options['includenotapproved']) && has_capability('mod/glossary:approve', $context)) {
            $qb->filter_by_non_approved(mod_glossary_entry_query_builder::NON_APPROVED_ALL);
        } else {
            $qb->filter_by_non_approved(mod_glossary_entry_query_builder::NON_APPROVED_SELF);
        }

        $qb->add_field('*', 'entries');
        $qb->join_user();
        $qb->add_user_fields();
        $qb->order_by('concept', 'entries');
        $qb->order_by('id', 'entries', 'ASC'); // Sort on ID to avoid random ordering when entries share an ordering value.
        $qb->limit($from, $limit);

        // Fetching the entries.
        $count = $qb->count_records();
        $entries = $qb->get_records();

        return array($entries, $count);
    }
}

if (!function_exists('glossary_get_entries_by_date')) {
    /**
     * Returns the entries of a glossary by date.
     *
     * @param  object $glossary The glossary.
     * @param  context $context The context of the glossary.
     * @param  string $order The mode of ordering: CREATION or UPDATE.
     * @param  string $sort The direction of the ordering: ASC or DESC.
     * @param  int $from Fetch records from.
     * @param  int $limit Number of records to fetch.
     * @param  array $options Accepts:
     *                        - (bool) includenotapproved. When false, includes the non-approved entries created by
     *                          the current user. When true, also includes the ones that the user has the permission to approve.
     * @return array The first element being the recordset, the second the number of entries.
     * @since Moodle 3.1
     */
    function glossary_get_entries_by_date($glossary, $context, $order, $sort, $from, $limit, $options = array()) {

        $qb = new mod_glossary_entry_query_builder($glossary);
        if (!empty($options['includenotapproved']) && has_capability('mod/glossary:approve', $context)) {
            $qb->filter_by_non_approved(mod_glossary_entry_query_builder::NON_APPROVED_ALL);
        } else {
            $qb->filter_by_non_approved(mod_glossary_entry_query_builder::NON_APPROVED_SELF);
        }

        $qb->add_field('*', 'entries');
        $qb->join_user();
        $qb->add_user_fields();
        $qb->limit($from, $limit);

        if ($order == 'CREATION') {
            $qb->order_by('timecreated', 'entries', $sort);
        } else {
            $qb->order_by('timemodified', 'entries', $sort);
        }
        $qb->order_by('id', 'entries', $sort); // Sort on ID to avoid random ordering when entries share an ordering value.

        // Fetching the entries.
        $count = $qb->count_records();
        $entries = $qb->get_records();

        return array($entries, $count);
    }
}

if (!function_exists('glossary_get_entries_by_category')) {
    /**
     * Returns the entries of a glossary by category.
     *
     * @param  object $glossary The glossary.
     * @param  context $context The context of the glossary.
     * @param  int $categoryid The category ID, or GLOSSARY_SHOW_* constant.
     * @param  int $from Fetch records from.
     * @param  int $limit Number of records to fetch.
     * @param  array $options Accepts:
     *                        - (bool) includenotapproved. When false, includes the non-approved entries created by
     *                          the current user. When true, also includes the ones that the user has the permission to approve.
     * @return array The first element being the recordset, the second the number of entries.
     * @since Moodle 3.1
     */
    function glossary_get_entries_by_category($glossary, $context, $categoryid, $from, $limit, $options = array()) {

        $qb = new mod_glossary_entry_query_builder($glossary);
        if (!empty($options['includenotapproved']) && has_capability('mod/glossary:approve', $context)) {
            $qb->filter_by_non_approved(mod_glossary_entry_query_builder::NON_APPROVED_ALL);
        } else {
            $qb->filter_by_non_approved(mod_glossary_entry_query_builder::NON_APPROVED_SELF);
        }

        $qb->join_category($categoryid);
        $qb->join_user();

        // The first field must be the relationship ID when viewing all categories.
        if ($categoryid === GLOSSARY_SHOW_ALL_CATEGORIES) {
            $qb->add_field('id', 'entries_categories', 'cid');
        }

        $qb->add_field('*', 'entries');
        $qb->add_field('categoryid', 'entries_categories');
        $qb->add_user_fields();

        if ($categoryid === GLOSSARY_SHOW_ALL_CATEGORIES) {
            $qb->add_field('name', 'categories', 'categoryname');
            $qb->order_by('name', 'categories');

        } else if ($categoryid === GLOSSARY_SHOW_NOT_CATEGORISED) {
            $qb->where('categoryid', 'entries_categories', null);
        }

        // Sort on additional fields to avoid random ordering when entries share an ordering value.
        $qb->order_by('concept', 'entries');
        $qb->order_by('id', 'entries', 'ASC');
        $qb->limit($from, $limit);

        // Fetching the entries.
        $count = $qb->count_records();
        $entries = $qb->get_records();

        return array($entries, $count);
    }
}

if (!function_exists('glossary_get_entries_by_author')) {
    /**
     * Returns the entries of a glossary by author.
     *
     * @param  object $glossary The glossary.
     * @param  context $context The context of the glossary.
     * @param  string $letter The letter
     * @param  string $field The field to search: FIRSTNAME or LASTNAME.
     * @param  string $sort The sorting: ASC or DESC.
     * @param  int $from Fetch records from.
     * @param  int $limit Number of records to fetch.
     * @param  array $options Accepts:
     *                        - (bool) includenotapproved. When false, includes the non-approved entries created by
     *                          the current user. When true, also includes the ones that the user has the permission to approve.
     * @return array The first element being the recordset, the second the number of entries.
     * @since Moodle 3.1
     */
    function glossary_get_entries_by_author($glossary, $context, $letter, $field, $sort, $from, $limit, $options = array()) {

        $firstnamefirst = $field === 'FIRSTNAME';
        $qb = new mod_glossary_entry_query_builder($glossary);
        if ($letter != 'ALL' && $letter != 'SPECIAL' && core_text::strlen($letter)) {
            $qb->filter_by_author_letter($letter, $firstnamefirst);
        }
        if ($letter == 'SPECIAL') {
            $qb->filter_by_author_non_letter($firstnamefirst);
        }

        if (!empty($options['includenotapproved']) && has_capability('mod/glossary:approve', $context)) {
            $qb->filter_by_non_approved(mod_glossary_entry_query_builder::NON_APPROVED_ALL);
        } else {
            $qb->filter_by_non_approved(mod_glossary_entry_query_builder::NON_APPROVED_SELF);
        }

        $qb->add_field('*', 'entries');
        $qb->join_user(true);
        $qb->add_user_fields();
        $qb->order_by_author($firstnamefirst, $sort);
        $qb->order_by('concept', 'entries');
        $qb->order_by('id', 'entries', 'ASC'); // Sort on ID to avoid random ordering when entries share an ordering value.
        $qb->limit($from, $limit);

        // Fetching the entries.
        $count = $qb->count_records();
        $entries = $qb->get_records();

        return array($entries, $count);
    }
}

if (!function_exists('glossary_get_entries_by_author_id')) {
    /**
     * Returns the entries of a glossary by category.
     *
     * @param  object $glossary The glossary.
     * @param  context $context The context of the glossary.
     * @param  int $authorid The author ID.
     * @param  string $order The mode of ordering: CONCEPT, CREATION or UPDATE.
     * @param  string $sort The direction of the ordering: ASC or DESC.
     * @param  int $from Fetch records from.
     * @param  int $limit Number of records to fetch.
     * @param  array $options Accepts:
     *                        - (bool) includenotapproved. When false, includes the non-approved entries created by
     *                          the current user. When true, also includes the ones that the user has the permission to approve.
     * @return array The first element being the recordset, the second the number of entries.
     * @since Moodle 3.1
     */
    function glossary_get_entries_by_author_id($glossary, $context, $authorid, $order, $sort, $from, $limit, $options = array()) {

        $qb = new mod_glossary_entry_query_builder($glossary);
        if (!empty($options['includenotapproved']) && has_capability('mod/glossary:approve', $context)) {
            $qb->filter_by_non_approved(mod_glossary_entry_query_builder::NON_APPROVED_ALL);
        } else {
            $qb->filter_by_non_approved(mod_glossary_entry_query_builder::NON_APPROVED_SELF);
        }

        $qb->add_field('*', 'entries');
        $qb->join_user(true);
        $qb->add_user_fields();
        $qb->where('id', 'user', $authorid);

        if ($order == 'CREATION') {
            $qb->order_by('timecreated', 'entries', $sort);
        } else if ($order == 'UPDATE') {
            $qb->order_by('timemodified', 'entries', $sort);
        } else {
            $qb->order_by('concept', 'entries', $sort);
        }
        $qb->order_by('id', 'entries', $sort); // Sort on ID to avoid random ordering when entries share an ordering value.

        $qb->limit($from, $limit);

        // Fetching the entries.
        $count = $qb->count_records();
        $entries = $qb->get_records();

        return array($entries, $count);
    }
}

if (!function_exists('glossary_get_authors')) {
    /**
     * Returns the authors in a glossary
     *
     * @param  object $glossary The glossary.
     * @param  context $context The context of the glossary.
     * @param  int $limit Number of records to fetch.
     * @param  int $from Fetch records from.
     * @param  array $options Accepts:
     *                        - (bool) includenotapproved. When false, includes self even if all of their entries require approval.
     *                          When true, also includes authors only having entries pending approval.
     * @return array The first element being the recordset, the second the number of entries.
     * @since Moodle 3.1
     */
    function glossary_get_authors($glossary, $context, $limit, $from, $options = array()) {
        global $DB, $USER;

        $params = array();
        $userfields = user_picture::fields('u', null);

        $approvedsql = '(ge.approved <> 0 OR ge.userid = :myid)';
        $params['myid'] = $USER->id;
        if (!empty($options['includenotapproved']) && has_capability('mod/glossary:approve', $context)) {
            $approvedsql = '1 = 1';
        }

        $sqlselectcount = "SELECT COUNT(DISTINCT(u.id))";
        $sqlselect = "SELECT DISTINCT(u.id) AS userId, $userfields";
        $sql = "  FROM {user} u
                  JOIN {glossary_entries} ge
                    ON ge.userid = u.id
                   AND (ge.glossaryid = :gid1 OR ge.sourceglossaryid = :gid2)
                   AND $approvedsql";
        $ordersql = " ORDER BY u.lastname, u.firstname";

        $params['gid1'] = $glossary->id;
        $params['gid2'] = $glossary->id;

        $count = $DB->count_records_sql($sqlselectcount . $sql, $params);
        $users = $DB->get_recordset_sql($sqlselect . $sql . $ordersql, $params, $from, $limit);

        return array($users, $count);
    }
}

if (!function_exists('glossary_get_categories')) {
    /**
     * Returns the categories of a glossary.
     *
     * @param  object $glossary The glossary.
     * @param  int $from Fetch records from.
     * @param  int $limit Number of records to fetch.
     * @return array The first element being the recordset, the second the number of entries.
     * @since Moodle 3.1
     */
    function glossary_get_categories($glossary, $from, $limit) {
        global $DB;

        $count = $DB->count_records('glossary_categories', array('glossaryid' => $glossary->id));
        $categories = $DB->get_recordset('glossary_categories', array('glossaryid' => $glossary->id), 'name ASC', '*', $from, $limit);

        return array($categories, $count);
    }
}

if (!function_exists('glossary_get_search_terms_sql')) {
    /**
     * Get the SQL where clause for searching terms.
     *
     * Note that this does not handle invalid or too short terms.
     *
     * @param array   $terms      Array of terms.
     * @param bool    $fullsearch Whether or not full search should be enabled.
     * @return array The first element being the where clause, the second array of parameters.
     * @since Moodle 3.1
     */
    function glossary_get_search_terms_sql(array $terms, $fullsearch = true) {
        global $DB;
        static $i = 0;

        if ($DB->sql_regex_supported()) {
            $regexp = $DB->sql_regex(true);
            $notregexp = $DB->sql_regex(false);
        }

        $params = array();
        $conditions = array();

        foreach ($terms as $searchterm) {
            $i++;

            $not = false; // Initially we aren't going to perform NOT LIKE searches, only MSSQL and Oracle
                          // will use it to simulate the "-" operator with LIKE clause.

            if (empty($fullsearch)) {
                // With fullsearch disabled, look only within concepts and aliases.
                $concat = $DB->sql_concat('ge.concept', "' '", "COALESCE(al.alias, :emptychar{$i})");
            } else {
                // With fullsearch enabled, look also within definitions.
                $concat = $DB->sql_concat('ge.concept', "' '", 'ge.definition', "' '", "COALESCE(al.alias, :emptychar{$i})");
            }
            $params['emptychar' . $i] = '';

            // Under Oracle and MSSQL, trim the + and - operators and perform simpler LIKE (or NOT LIKE) queries.
            if (!$DB->sql_regex_supported()) {
                if (substr($searchterm, 0, 1) === '-') {
                    $not = true;
                }
                $searchterm = trim($searchterm, '+-');
            }

            if (substr($searchterm, 0, 1) === '+') {
                $searchterm = trim($searchterm, '+-');
                $conditions[] = "$concat $regexp :searchterm{$i}";
                $params['searchterm' . $i] = '(^|[^a-zA-Z0-9])' . preg_quote($searchterm, '|') . '([^a-zA-Z0-9]|$)';

            } else if (substr($searchterm, 0, 1) === "-") {
                $searchterm = trim($searchterm, '+-');
                $conditions[] = "$concat $notregexp :searchterm{$i}";
                $params['searchterm' . $i] = '(^|[^a-zA-Z0-9])' . preg_quote($searchterm, '|') . '([^a-zA-Z0-9]|$)';

            } else {
                $conditions[] = $DB->sql_like($concat, ":searchterm{$i}", false, true, $not);
                $params['searchterm' . $i] = '%' . $DB->sql_like_escape($searchterm) . '%';
            }
        }

        // When there are no conditions we add a negative one to ensure that we don't return anything.
        if (empty($conditions)) {
            $conditions[] = '1 = 2';
        }

        $where = implode(' AND ', $conditions);
        return array($where, $params);
    }
}

if (!function_exists('glossary_get_entries_by_search')) {

    /**
     * Returns the entries of a glossary by search.
     *
     * @param  object $glossary The glossary.
     * @param  context $context The context of the glossary.
     * @param  string $query The search query.
     * @param  bool $fullsearch Whether or not full search is required.
     * @param  string $order The mode of ordering: CONCEPT, CREATION or UPDATE.
     * @param  string $sort The direction of the ordering: ASC or DESC.
     * @param  int $from Fetch records from.
     * @param  int $limit Number of records to fetch.
     * @param  array $options Accepts:
     *                        - (bool) includenotapproved. When false, includes the non-approved entries created by
     *                          the current user. When true, also includes the ones that the user has the permission to approve.
     * @return array The first element being the recordset, the second the number of entries.
     * @since Moodle 3.1
     */
    function glossary_get_entries_by_search($glossary, $context, $query, $fullsearch, $order, $sort, $from, $limit,
                                            $options = array()) {
        global $DB, $USER;

        // Remove too little terms.
        $terms = explode(' ', $query);
        foreach ($terms as $key => $term) {
            if (strlen(trim($term, '+-')) < 2) {
                unset($terms[$key]);
            }
        }

        list($searchcond, $params) = glossary_get_search_terms_sql($terms, $fullsearch);

        $userfields = user_picture::fields('u', null, 'userdataid', 'userdata');

        // Need one inner view here to avoid distinct + text.
        $sqlwrapheader = 'SELECT ge.*, ge.concept AS glossarypivot, ' . $userfields . '
                            FROM {glossary_entries} ge
                            LEFT JOIN {user} u ON u.id = ge.userid
                            JOIN ( ';
        $sqlwrapfooter = ' ) gei ON (ge.id = gei.id)';
        $sqlselect  = "SELECT DISTINCT ge.id";
        $sqlfrom    = "FROM {glossary_entries} ge
                       LEFT JOIN {glossary_alias} al ON al.entryid = ge.id";

        if (!empty($options['includenotapproved']) && has_capability('mod/glossary:approve', $context)) {
            $approvedsql = '';
        } else {
            $approvedsql = 'AND (ge.approved <> 0 OR ge.userid = :myid)';
            $params['myid'] = $USER->id;
        }

        if ($order == 'CREATION') {
            $sqlorderby = "ORDER BY ge.timecreated $sort";
        } else if ($order == 'UPDATE') {
            $sqlorderby = "ORDER BY ge.timemodified $sort";
        } else {
            $sqlorderby = "ORDER BY ge.concept $sort";
        }
        $sqlorderby .= " , ge.id ASC"; // Sort on ID to avoid random ordering when entries share an ordering value.

        $sqlwhere = "WHERE ($searchcond) $approvedsql";

        // Fetching the entries.
        $count = $DB->count_records_sql("SELECT COUNT(DISTINCT(ge.id)) $sqlfrom $sqlwhere", $params);

        $query = "$sqlwrapheader $sqlselect $sqlfrom $sqlwhere $sqlwrapfooter $sqlorderby";
        $entries = $DB->get_recordset_sql($query, $params, $from, $limit);

        return array($entries, $count);
    }
}

if (!function_exists('glossary_get_entries_by_term')) {
    /**
     * Returns the entries of a glossary by term.
     *
     * @param  object $glossary The glossary.
     * @param  context $context The context of the glossary.
     * @param  string $term The term we are searching for, a concept or alias.
     * @param  int $from Fetch records from.
     * @param  int $limit Number of records to fetch.
     * @param  array $options Accepts:
     *                        - (bool) includenotapproved. When false, includes the non-approved entries created by
     *                          the current user. When true, also includes the ones that the user has the permission to approve.
     * @return array The first element being the recordset, the second the number of entries.
     * @since Moodle 3.1
     */
    function glossary_get_entries_by_term($glossary, $context, $term, $from, $limit, $options = array()) {

        // Build the query.
        $qb = new mod_glossary_entry_query_builder($glossary);
        if (!empty($options['includenotapproved']) && has_capability('mod/glossary:approve', $context)) {
            $qb->filter_by_non_approved(mod_glossary_entry_query_builder::NON_APPROVED_ALL);
        } else {
            $qb->filter_by_non_approved(mod_glossary_entry_query_builder::NON_APPROVED_SELF);
        }

        $qb->add_field('*', 'entries');
        $qb->join_alias();
        $qb->join_user();
        $qb->add_user_fields();
        $qb->filter_by_term($term);

        $qb->order_by('concept', 'entries');
        $qb->order_by('id', 'entries');     // Sort on ID to avoid random ordering when entries share an ordering value.
        $qb->limit($from, $limit);

        // Fetching the entries.
        $count = $qb->count_records();
        $entries = $qb->get_records();

        return array($entries, $count);
    }
}

if (!function_exists('glossary_get_entries_to_approve')) {
    /**
     * Returns the entries to be approved.
     *
     * @param  object $glossary The glossary.
     * @param  context $context The context of the glossary.
     * @param  string $letter The letter, or ALL, or SPECIAL.
     * @param  string $order The mode of ordering: CONCEPT, CREATION or UPDATE.
     * @param  string $sort The direction of the ordering: ASC or DESC.
     * @param  int $from Fetch records from.
     * @param  int $limit Number of records to fetch.
     * @return array The first element being the recordset, the second the number of entries.
     * @since Moodle 3.1
     */
    function glossary_get_entries_to_approve($glossary, $context, $letter, $order, $sort, $from, $limit) {

        $qb = new mod_glossary_entry_query_builder($glossary);
        if ($letter != 'ALL' && $letter != 'SPECIAL' && core_text::strlen($letter)) {
            $qb->filter_by_concept_letter($letter);
        }
        if ($letter == 'SPECIAL') {
            $qb->filter_by_concept_non_letter();
        }

        $qb->add_field('*', 'entries');
        $qb->join_user();
        $qb->add_user_fields();
        $qb->filter_by_non_approved(mod_glossary_entry_query_builder::NON_APPROVED_ONLY);
        if ($order == 'CREATION') {
            $qb->order_by('timecreated', 'entries', $sort);
        } else if ($order == 'UPDATE') {
            $qb->order_by('timemodified', 'entries', $sort);
        } else {
            $qb->order_by('concept', 'entries', $sort);
        }
        $qb->order_by('id', 'entries', $sort); // Sort on ID to avoid random ordering when entries share an ordering value.
        $qb->limit($from, $limit);

        // Fetching the entries.
        $count = $qb->count_records();
        $entries = $qb->get_records();

        return array($entries, $count);
    }
}

if (!function_exists('glossary_get_entry_by_id')) {
    /**
     * Fetch an entry.
     *
     * @param  int $id The entry ID.
     * @return object|false The entry, or false when not found.
     * @since Moodle 3.1
     */
    function glossary_get_entry_by_id($id) {

        // Build the query.
        $qb = new mod_glossary_entry_query_builder();
        $qb->add_field('*', 'entries');
        $qb->join_user();
        $qb->add_user_fields();
        $qb->where('id', 'entries', $id);

        // Fetching the entries.
        $entries = $qb->get_records();
        if (empty($entries)) {
            return false;
        }
        return array_pop($entries);
    }
}

require_once("$CFG->dirroot/mod/wiki/lib.php");
require_once("$CFG->dirroot/mod/wiki/locallib.php");

if (!function_exists('wiki_can_create_pages')) {
    /**
     * Check if the user can create pages in a certain wiki.
     * @param context $context Wiki's context.
     * @param integer|stdClass $user A user id or object. By default (null) checks the permissions of the current user.
     * @return bool True if user can create pages, false otherwise.
     * @since Moodle 3.1
     */
    function wiki_can_create_pages($context, $user = null) {
        return has_capability('mod/wiki:createpage', $context, $user);
    }
}

if (!function_exists('wiki_view')) {
    /**
     * Mark the activity completed (if required) and trigger the course_module_viewed event.
     *
     * @param  stdClass $wiki       Wiki object.
     * @param  stdClass $course     Course object.
     * @param  stdClass $cm         Course module object.
     * @param  stdClass $context    Context object.
     * @since Moodle 3.1
     */
    function wiki_view($wiki, $course, $cm, $context) {
        // Trigger course_module_viewed event.
        $params = array(
            'context' => $context,
            'objectid' => $wiki->id
        );
        $event = \mod_wiki\event\course_module_viewed::create($params);
        $event->add_record_snapshot('course_modules', $cm);
        $event->add_record_snapshot('course', $course);
        $event->add_record_snapshot('wiki', $wiki);
        $event->trigger();
        // Completion.
        $completion = new completion_info($course);
        $completion->set_module_viewed($cm);
    }
}

if (!function_exists('wiki_page_view')) {
    /**
     * Mark the activity completed (if required) and trigger the page_viewed event.
     *
     * @param  stdClass $wiki       Wiki object.
     * @param  stdClass $page       Page object.
     * @param  stdClass $course     Course object.
     * @param  stdClass $cm         Course module object.
     * @param  stdClass $context    Context object.
     * @param  int $uid             Optional User ID.
     * @param  array $other         Optional Other params: title, wiki ID, group ID, groupanduser, prettyview.
     * @param  stdClass $subwiki    Optional Subwiki.
     * @since Moodle 3.1
     */
    function wiki_page_view($wiki, $page, $course, $cm, $context, $uid = null, $other = null, $subwiki = null) {
        // Trigger course_module_viewed event.
        $params = array(
            'context' => $context,
            'objectid' => $page->id
        );
        if ($uid != null) {
            $params['relateduserid'] = $uid;
        }
        if ($other != null) {
            $params['other'] = $other;
        }
        $event = \mod_wiki\event\page_viewed::create($params);
        $event->add_record_snapshot('wiki_pages', $page);
        $event->add_record_snapshot('course_modules', $cm);
        $event->add_record_snapshot('course', $course);
        $event->add_record_snapshot('wiki', $wiki);
        if ($subwiki != null) {
            $event->add_record_snapshot('wiki_subwikis', $subwiki);
        }
        $event->trigger();
        // Completion.
        $completion = new completion_info($course);
        $completion->set_module_viewed($cm);
    }
}

if (!function_exists('wiki_get_possible_subwiki_by_group')) {
    /**
     * Get a sub wiki instance by wiki id, group id and user id.
     * If the wiki doesn't exist in DB it will return an isntance with id -1.
     *
     * @param int $wikiid  Wiki ID.
     * @param int $groupid Group ID.
     * @param int $userid  User ID.
     * @return object      Subwiki instance.
     * @since Moodle 3.1
     */
    function wiki_get_possible_subwiki_by_group($wikiid, $groupid, $userid = 0) {
        if (!$subwiki = wiki_get_subwiki_by_group($wikiid, $groupid, $userid)) {
            $subwiki = new stdClass();
            $subwiki->id = -1;
            $subwiki->wikiid = $wikiid;
            $subwiki->groupid = $groupid;
            $subwiki->userid = $userid;
        }
        return $subwiki;
    }
}

if (!function_exists('wiki_get_visible_subwikis')) {
    /**
     * Get all the possible subwikis visible to the user in a wiki.
     * It will return all the subwikis that can be created in a wiki, even if they don't exist in DB yet.
     *
     * @param  stdClass $wiki          Wiki to get the subwikis from.
     * @param  cm_info|stdClass $cm    Optional. The course module object.
     * @param  context_module $context Optional. Context of wiki module.
     * @return array                   List of subwikis.
     * @since Moodle 3.1
     */
    function wiki_get_visible_subwikis($wiki, $cm = null, $context = null) {
        global $USER;
        $subwikis = array();
        if (empty($wiki) or !is_object($wiki)) {
            // Wiki not valid.
            return $subwikis;
        }
        if (empty($cm)) {
            $cm = get_coursemodule_from_instance('wiki', $wiki->id);
        }
        if (empty($context)) {
            $context = context_module::instance($cm->id);
        }
        if (!has_capability('mod/wiki:viewpage', $context)) {
            return $subwikis;
        }
        $manage = has_capability('mod/wiki:managewiki', $context);
        if (!$groupmode = groups_get_activity_groupmode($cm)) {
            // No groups.
            if ($wiki->wikimode == 'collaborative') {
                // Only 1 subwiki.
                $subwikis[] = wiki_get_possible_subwiki_by_group($wiki->id, 0, 0);
            } else if ($wiki->wikimode == 'individual') {
                // There's 1 subwiki per user.
                if ($manage) {
                    // User can view all subwikis.
                    $users = get_enrolled_users($context);
                    foreach ($users as $user) {
                        $subwikis[] = wiki_get_possible_subwiki_by_group($wiki->id, 0, $user->id);
                    }
                } else {
                    // User can only see his subwiki.
                    $subwikis[] = wiki_get_possible_subwiki_by_group($wiki->id, 0, $USER->id);
                }
            }
        } else {
            if ($wiki->wikimode == 'collaborative') {
                // 1 subwiki per group.
                $aag = has_capability('moodle/site:accessallgroups', $context);
                if ($aag || $groupmode == VISIBLEGROUPS) {
                    // User can see all groups.
                    $allowedgroups = groups_get_all_groups($cm->course, 0, $cm->groupingid);
                    $allparticipants = new stdClass();
                    $allparticipants->id = 0;
                    array_unshift($allowedgroups, $allparticipants); // Add all participants.
                } else {
                    // User can only see the groups he belongs to.
                    $allowedgroups = groups_get_all_groups($cm->course, $USER->id, $cm->groupingid);
                }
                foreach ($allowedgroups as $group) {
                    $subwikis[] = wiki_get_possible_subwiki_by_group($wiki->id, $group->id, 0);
                }
            } else if ($wiki->wikimode == 'individual') {
                // 1 subwiki per user and group.
                if ($manage || $groupmode == VISIBLEGROUPS) {
                    // User can view all subwikis.
                    $users = get_enrolled_users($context);
                    foreach ($users as $user) {
                        // Get all the groups this user belongs to.
                        $groups = groups_get_all_groups($cm->course, $user->id);
                        if (!empty($groups)) {
                            foreach ($groups as $group) {
                                $subwikis[] = wiki_get_possible_subwiki_by_group($wiki->id, $group->id, $user->id);
                            }
                        } else {
                            // User doesn't belong to any group, add it to group 0.
                            $subwikis[] = wiki_get_possible_subwiki_by_group($wiki->id, 0, $user->id);
                        }
                    }
                } else {
                    // The user can only see the subwikis of the groups he belongs.
                    $allowedgroups = groups_get_all_groups($cm->course, $USER->id, $cm->groupingid);
                    foreach ($allowedgroups as $group) {
                        $users = groups_get_members($group->id);
                        foreach ($users as $user) {
                            $subwikis[] = wiki_get_possible_subwiki_by_group($wiki->id, $group->id, $user->id);
                        }
                    }
                }
            }
        }
        return $subwikis;
    }
}

/**
 * Get pages list in wiki
 * @param int $swid sub wiki id
 * @param string $sort How to sort the pages. By default, title ASC.
 */
function local_mobile_wiki_get_page_list($swid, $sort = 'title ASC') {
    global $DB;
    $records = $DB->get_records('wiki_pages', array('subwikiid' => $swid), $sort);
    return $records;
}

function local_mobile_external_format_text($text, $textformat, $contextid, $component, $filearea, $itemid, $options = null) {
    global $CFG;
    // Get settings (singleton).
    $settings = external_settings::get_instance();
    if ($settings->get_fileurl()) {
        require_once($CFG->libdir . "/filelib.php");
        $text = file_rewrite_pluginfile_urls($text, $settings->get_file(), $contextid, $component, $filearea, $itemid);
    }
    if (!$settings->get_raw()) {
        $options = (array)$options;
        $options['filter'] = isset($options['filter']) ? $options['filter'] : $settings->get_filter();
        $options['para'] = isset($options['para']) ? $options['para'] : false;
        $options['context'] = context::instance_by_id($contextid);
        $options['allowid'] = isset($options['allowid']) ? $options['allowid'] : true;
        $text = format_text($text, $textformat, $options);
        $textformat = FORMAT_HTML; // Once converted to html (from markdown, plain... lets inform consumer this is already HTML).
    }
    return array($text, $textformat);
}


require_once("$CFG->dirroot/mod/quiz/locallib.php");

if (!function_exists('quiz_view')) {
    /**
     * Mark the activity completed (if required) and trigger the course_module_viewed event.
     *
     * @param  stdClass $quiz       quiz object
     * @param  stdClass $course     course object
     * @param  stdClass $cm         course module object
     * @param  stdClass $context    context object
     * @since Moodle 3.1
     */
    function quiz_view($quiz, $course, $cm, $context) {

        $params = array(
            'objectid' => $quiz->id,
            'context' => $context
        );

        $event = \mod_quiz\event\course_module_viewed::create($params);
        $event->add_record_snapshot('quiz', $quiz);
        $event->trigger();

        // Completion.
        $completion = new completion_info($course);
        $completion->set_module_viewed($cm);
    }

}

if (!function_exists('quiz_validate_new_attempt')) {
    /**
     * Validate permissions for creating a new attempt and start a new preview attempt if required.
     *
     * @param  quiz $quizobj quiz object
     * @param  quiz_access_manager $accessmanager quiz access manager
     * @param  bool $forcenew whether was required to start a new preview attempt
     * @param  int $page page to jump to in the attempt
     * @param  bool $redirect whether to redirect or throw exceptions (for web or ws usage)
     * @return array an array containing the attempt information, access error messages and the page to jump to in the attempt
     * @throws moodle_quiz_exception
     * @since Moodle 3.1
     */
    function quiz_validate_new_attempt(quiz $quizobj, quiz_access_manager $accessmanager, $forcenew, $page, $redirect) {
        global $DB, $USER;
        $timenow = time();

        if ($quizobj->is_preview_user() && $forcenew) {
            $accessmanager->current_attempt_finished();
        }

        // Check capabilities.
        if (!$quizobj->is_preview_user()) {
            $quizobj->require_capability('mod/quiz:attempt');
        }

        // Check to see if a new preview was requested.
        if ($quizobj->is_preview_user() && $forcenew) {
            // To force the creation of a new preview, we mark the current attempt (if any)
            // as finished. It will then automatically be deleted below.
            $DB->set_field('quiz_attempts', 'state', quiz_attempt::FINISHED,
                    array('quiz' => $quizobj->get_quizid(), 'userid' => $USER->id));
        }

        // Look for an existing attempt.
        $attempts = quiz_get_user_attempts($quizobj->get_quizid(), $USER->id, 'all', true);
        $lastattempt = end($attempts);

        $attemptnumber = null;
        // If an in-progress attempt exists, check password then redirect to it.
        if ($lastattempt && ($lastattempt->state == quiz_attempt::IN_PROGRESS ||
                $lastattempt->state == quiz_attempt::OVERDUE)) {
            $currentattemptid = $lastattempt->id;
            $messages = $accessmanager->prevent_access();

            // If the attempt is now overdue, deal with that.
            $quizobj->create_attempt_object($lastattempt)->handle_if_time_expired($timenow, true);

            // And, if the attempt is now no longer in progress, redirect to the appropriate place.
            if ($lastattempt->state == quiz_attempt::ABANDONED || $lastattempt->state == quiz_attempt::FINISHED) {
                if ($redirect) {
                    redirect($quizobj->review_url($lastattempt->id));
                } else {
                    throw new moodle_quiz_exception($quizobj, 'attemptalreadyclosed');
                }
            }

            // If the page number was not explicitly in the URL, go to the current page.
            if ($page == -1) {
                $page = $lastattempt->currentpage;
            }

        } else {
            while ($lastattempt && $lastattempt->preview) {
                $lastattempt = array_pop($attempts);
            }

            // Get number for the next or unfinished attempt.
            if ($lastattempt) {
                $attemptnumber = $lastattempt->attempt + 1;
            } else {
                $lastattempt = false;
                $attemptnumber = 1;
            }
            $currentattemptid = null;

            $messages = $accessmanager->prevent_access() +
                $accessmanager->prevent_new_attempt(count($attempts), $lastattempt);

            if ($page == -1) {
                $page = 0;
            }
        }
        return array($currentattemptid, $attemptnumber, $lastattempt, $messages, $page);
    }

}

if (!function_exists('quiz_prepare_and_start_new_attempt')) {
    /**
     * Prepare and start a new attempt deleting the previous preview attempts.
     *
     * @param  quiz $quizobj quiz object
     * @param  int $attemptnumber the attempt number
     * @param  object $lastattempt last attempt object
     * @return object the new attempt
     * @since  Moodle 3.1
     */
    function quiz_prepare_and_start_new_attempt(quiz $quizobj, $attemptnumber, $lastattempt) {
        global $DB, $USER;

        // Delete any previous preview attempts belonging to this user.
        quiz_delete_previews($quizobj->get_quiz(), $USER->id);

        $quba = question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);

        // Create the new attempt and initialize the question sessions
        $timenow = time(); // Update time now, in case the server is running really slowly.
        $attempt = quiz_create_attempt($quizobj, $attemptnumber, $lastattempt, $timenow, $quizobj->is_preview_user());

        if (!($quizobj->get_quiz()->attemptonlast && $lastattempt)) {
            $attempt = quiz_start_new_attempt($quizobj, $quba, $attempt, $attemptnumber, $timenow);
        } else {
            $attempt = quiz_start_attempt_built_on_last($quba, $attempt, $lastattempt);
        }

        $transaction = $DB->start_delegated_transaction();

        $attempt = quiz_attempt_save_started($quizobj, $quba, $attempt);

        $transaction->allow_commit();

        return $attempt;
    }
}

if (!function_exists('quiz_feedback_record_for_grade')) {
    /**
     * Get the feedback object for this grade on this quiz.
     *
     * @param float $grade a grade on this quiz.
     * @param object $quiz the quiz settings.
     * @return false|stdClass the record object or false if there is not feedback for the given grade
     * @since  Moodle 3.1
     */
    function quiz_feedback_record_for_grade($grade, $quiz) {
        global $DB;

        // With CBM etc, it is possible to get -ve grades, which would then not match
        // any feedback. Therefore, we replace -ve grades with 0.
        $grade = max($grade, 0);

        $feedback = $DB->get_record_select('quiz_feedback',
                'quizid = ? AND mingrade <= ? AND ? < maxgrade', array($quiz->id, $grade, $grade));

        return $feedback;
    }
}

class local_mobile_quiz extends quiz {

    /**
     * Static function to create a new quiz object for a specific user.
     *
     * @param int $quizid the the quiz id.
     * @param int $userid the the userid.
     * @return quiz the new quiz object
     */
    public static function create($quizid, $userid = null) {
        global $DB;

        $quiz = local_mobile_quiz_access_manager::load_quiz_and_settings($quizid);
        $course = $DB->get_record('course', array('id' => $quiz->course), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);

        // Update quiz with override information.
        if ($userid) {
            $quiz = quiz_update_effective_access($quiz, $userid);
        }

        return new local_mobile_quiz($quiz, $cm, $course);
    }

    /**
     * Return quiz_access_manager and instance of the quiz_access_manager class
     * for this quiz at this time.
     * @param int $timenow the current time as a unix timestamp.
     * @return quiz_access_manager and instance of the quiz_access_manager class
     *      for this quiz at this time.
     */
    public function get_access_manager($timenow) {
        if (is_null($this->accessmanager)) {
            $this->accessmanager = new local_mobile_quiz_access_manager($this, $timenow,
                    has_capability('mod/quiz:ignoretimelimits', $this->context, null, false));
        }
        return $this->accessmanager;
    }

    /**
     * Return all the question types used in this quiz.
     *
     * @param  boolean $includepotential if the quiz include random questions, setting this flag to true will make the function to
     * return all the possible question types in the random questions category
     * @return array a sorted array including the different question types
     * @since  Moodle 3.1
     */
    public function get_all_question_types_used($includepotential = false) {
        $questiontypes = array();
        // To control if we need to look in categories for questions.
        $qcategories = array();

        // We must be careful with random questions, if we find a random question we must assume that the quiz may content
        // any of the questions in the referenced category (or subcategories).
        foreach ($this->get_questions() as $questiondata) {
            if ($questiondata->qtype == 'random' and $includepotential) {
                $includesubcategories = (bool) $questiondata->questiontext;
                if (!isset($qcategories[$questiondata->category])) {
                    $qcategories[$questiondata->category] = false;
                }
                if ($includesubcategories) {
                    $qcategories[$questiondata->category] = true;
                }
            } else {
                if (!in_array($questiondata->qtype, $questiontypes)) {
                    $questiontypes[] = $questiondata->qtype;
                }
            }
        }

        if (!empty($qcategories)) {
            // We have to look for all the question types in these categories.
            $categoriestolook = array();
            foreach ($qcategories as $cat => $includesubcats) {
                if ($includesubcats) {
                    $categoriestolook = array_merge($categoriestolook, question_categorylist($cat));
                } else {
                    $categoriestolook[] = $cat;
                }
            }
            $questiontypesincategories = local_mobile_question_bank::get_all_question_types_in_categories($categoriestolook);
            $questiontypes = array_merge($questiontypes, $questiontypesincategories);
        }
        $questiontypes = array_unique($questiontypes);
        sort($questiontypes);

        return $questiontypes;
    }
}

class local_mobile_quiz_attempt extends quiz_attempt {

    /**
     * Constructor assuming we already have the necessary data loaded.
     *
     * @param object $attempt the row of the quiz_attempts table.
     * @param object $quiz the quiz object for this attempt and user.
     * @param object $cm the course_module object for this quiz.
     * @param object $course the row from the course table for the course we belong to.
     * @param bool $loadquestions (optional) if true, the default, load all the details
     *      of the state of each question. Else just set up the basic details of the attempt.
     */
    public function __construct($attempt, $quiz, $cm, $course, $loadquestions = true) {
        global $DB;

        $this->attempt = $attempt;
        $this->quizobj = new local_mobile_quiz($quiz, $cm, $course);

        if (!$loadquestions) {
            return;
        }

        $this->quba = question_engine::load_questions_usage_by_activity($this->attempt->uniqueid);
        $this->slots = $DB->get_records('quiz_slots',
                array('quizid' => $this->get_quizid()), 'slot',
                'slot, requireprevious, questionid');
        $this->sections = array_values($DB->get_records('quiz_sections',
                array('quizid' => $this->get_quizid()), 'firstslot'));

        $this->link_sections_and_slots();
        $this->determine_layout();
        $this->number_questions();
    }

    /**
     * Static function to create a new quiz_attempt object given an attemptid.
     *
     * @param int $attemptid the attempt id.
     * @return quiz_attempt the new quiz_attempt object
     */
    public static function create($attemptid) {
        return self::create_helper(array('id' => $attemptid));
    }

    /**
     * Used by {create()} and {create_from_usage_id()}.
     * @param array $conditions passed to $DB->get_record('quiz_attempts', $conditions).
     */
    protected static function create_helper($conditions) {
        global $DB;

        $attempt = $DB->get_record('quiz_attempts', $conditions, '*', MUST_EXIST);
        $quiz = quiz_access_manager::load_quiz_and_settings($attempt->quiz);
        $course = $DB->get_record('course', array('id' => $quiz->course), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);

        // Update quiz with override information.
        $quiz = quiz_update_effective_access($quiz, $attempt->userid);
        return new local_mobile_quiz_attempt($attempt, $quiz, $cm, $course);
    }

    /**
     * Return the question type name for a given slot within the current attempt.
     *
     * @param int $slot the number used to identify this question within this attempt.
     * @return string the question type name
     * @since  Moodle 3.1
     */
    public function get_question_type_name($slot) {
        return $this->quba->get_question($slot)->get_type_name();
    }

    /**
     * Process responses during an attempt at a quiz.
     *
     * @param  int $timenow time when the processing started
     * @param  bool $finishattempt whether to finish the attempt or not
     * @param  bool $timeup true if form was submitted by timer
     * @param  int $thispage current page number
     * @return string the attempt state once the data has been processed
     * @since  Moodle 3.1
     * @throws  moodle_exception
     */
    public function process_attempt($timenow, $finishattempt, $timeup, $thispage) {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        // If there is only a very small amount of time left, there is no point trying
        // to show the student another page of the quiz. Just finish now.
        $graceperiodmin = null;
        $accessmanager = $this->get_access_manager($timenow);
        $timeclose = $accessmanager->get_end_time($this->get_attempt());

        // Don't enforce timeclose for previews.
        if ($this->is_preview()) {
            $timeclose = false;
        }
        $toolate = false;
        if ($timeclose !== false && $timenow > $timeclose - QUIZ_MIN_TIME_TO_CONTINUE) {
            $timeup = true;
            $graceperiodmin = get_config('quiz', 'graceperiodmin');
            if ($timenow > $timeclose + $graceperiodmin) {
                $toolate = true;
            }
        }

        // If time is running out, trigger the appropriate action.
        $becomingoverdue = false;
        $becomingabandoned = false;
        if ($timeup) {
            if ($this->get_quiz()->overduehandling == 'graceperiod') {
                if (is_null($graceperiodmin)) {
                    $graceperiodmin = get_config('quiz', 'graceperiodmin');
                }
                if ($timenow > $timeclose + $this->get_quiz()->graceperiod + $graceperiodmin) {
                    // Grace period has run out.
                    $finishattempt = true;
                    $becomingabandoned = true;
                } else {
                    $becomingoverdue = true;
                }
            } else {
                $finishattempt = true;
            }
        }

        // Don't log - we will end with a redirect to a page that is logged.

        if (!$finishattempt) {
            // Just process the responses for this page and go to the next page.
            if (!$toolate) {
                try {
                    $this->process_submitted_actions($timenow, $becomingoverdue);

                } catch (question_out_of_sequence_exception $e) {
                    throw new moodle_exception('submissionoutofsequencefriendlymessage', 'question',
                            $this->attempt_url(null, $thispage));

                } catch (Exception $e) {
                    // This sucks, if we display our own custom error message, there is no way
                    // to display the original stack trace.
                    $debuginfo = '';
                    if (!empty($e->debuginfo)) {
                        $debuginfo = $e->debuginfo;
                    }
                    throw new moodle_exception('errorprocessingresponses', 'question',
                            $this->attempt_url(null, $thispage), $e->getMessage(), $debuginfo);
                }

                if (!$becomingoverdue) {
                    foreach ($this->get_slots() as $slot) {
                        if (optional_param('redoslot' . $slot, false, PARAM_BOOL)) {
                            $this->process_redo_question($slot, $timenow);
                        }
                    }
                }

            } else {
                // The student is too late.
                $this->process_going_overdue($timenow, true);
            }

            $transaction->allow_commit();

            return $becomingoverdue ? self::OVERDUE : self::IN_PROGRESS;
        }

        // Update the quiz attempt record.
        try {
            if ($becomingabandoned) {
                $this->process_abandon($timenow, true);
            } else {
                $this->process_finish($timenow, !$toolate);
            }

        } catch (question_out_of_sequence_exception $e) {
            throw new moodle_exception('submissionoutofsequencefriendlymessage', 'question',
                    $this->attempt_url(null, $thispage));

        } catch (Exception $e) {
            // This sucks, if we display our own custom error message, there is no way
            // to display the original stack trace.
            $debuginfo = '';
            if (!empty($e->debuginfo)) {
                $debuginfo = $e->debuginfo;
            }
            throw new moodle_exception('errorprocessingresponses', 'question',
                    $this->attempt_url(null, $thispage), $e->getMessage(), $debuginfo);
        }

        // Send the user to the review page.
        $transaction->allow_commit();

        return $becomingabandoned ? self::ABANDONED : self::FINISHED;
    }

    /**
     * Check a page access to see if is an out of sequence access.
     *
     * @param  int $page page number
     * @return boolean false is is an out of sequence access, true otherwise.
     * @since Moodle 3.1
     */
    public function check_page_access($page) {
        global $DB;

        if ($this->get_currentpage() != $page) {
            if ($this->get_navigation_method() == QUIZ_NAVMETHOD_SEQ && $this->get_currentpage() > $page) {
                return false;
            }
        }
        return true;
    }

    /**
     * Update attempt page.
     *
     * @param  int $page page number
     * @return boolean true if everything was ok, false otherwise (out of sequence access).
     * @since Moodle 3.1
     */
    public function set_currentpage($page) {
        global $DB;

        if ($this->check_page_access($page)) {
            $DB->set_field('quiz_attempts', 'currentpage', $page, array('id' => $this->get_attemptid()));
            return true;
        }
        return false;
    }

    /**
     * Trigger the attempt_viewed event.
     *
     * @since Moodle 3.1
     */
    public function fire_attempt_viewed_event() {
        $params = array(
            'objectid' => $this->get_attemptid(),
            'relateduserid' => $this->get_userid(),
            'courseid' => $this->get_courseid(),
            'context' => context_module::instance($this->get_cmid()),
            'other' => array(
                'quizid' => $this->get_quizid()
            )
        );
        $event = \mod_quiz\event\attempt_viewed::create($params);
        $event->add_record_snapshot('quiz_attempts', $this->get_attempt());
        $event->trigger();
    }

    /**
     * Trigger the attempt_summary_viewed event.
     *
     * @since Moodle 3.1
     */
    public function fire_attempt_summary_viewed_event() {

        $params = array(
            'objectid' => $this->get_attemptid(),
            'relateduserid' => $this->get_userid(),
            'courseid' => $this->get_courseid(),
            'context' => context_module::instance($this->get_cmid()),
            'other' => array(
                'quizid' => $this->get_quizid()
            )
        );
        $event = \mod_quiz\event\attempt_summary_viewed::create($params);
        $event->add_record_snapshot('quiz_attempts', $this->get_attempt());
        $event->trigger();
    }

    /**
     * Trigger the attempt_reviewed event.
     *
     * @since Moodle 3.1
     */
    public function fire_attempt_reviewed_event() {

        $params = array(
            'objectid' => $this->get_attemptid(),
            'relateduserid' => $this->get_userid(),
            'courseid' => $this->get_courseid(),
            'context' => context_module::instance($this->get_cmid()),
            'other' => array(
                'quizid' => $this->get_quizid()
            )
        );
        $event = \mod_quiz\event\attempt_reviewed::create($params);
        $event->add_record_snapshot('quiz_attempts', $this->get_attempt());
        $event->trigger();
    }

    /**
     * Update the timemodifiedoffline field in the quiz_attempts table.
     * This function should be used only when web services are being used.
     *
     * @param int $time time stamp
     * @return boolean false if the field is not updated becase web services aren't being used.
     * @since Moodle 3.1
     */
    public function set_offline_modified_time($time) {
        global $DB;

        // Update the timemodifiedoffline field only if web services are being used.
        if (WS_SERVER) {
            $dbman = $DB->get_manager();
            $attemptstable = new xmldb_table('quizaccess_offlineattempts_a');
            if ($dbman->table_exists($attemptstable)) {
                $attemptid = $this->get_attemptid();
                if ($attempt = $DB->get_record('quizaccess_offlineattempts_a', array('attemptid' => $attemptid))) {
                    $attempt->timemodifiedoffline = $time;
                    $DB->update_record('quizaccess_offlineattempts_a', $attempt);
                } else {
                    $attempt = new stdClass;
                    $attempt->attemptid = $attemptid;
                    $attempt->timemodifiedoffline = $time;
                    $DB->insert_record('quizaccess_offlineattempts_a', $attempt);
                }
            }
        }
        return false;
    }
}

class local_mobile_quiz_access_manager extends quiz_access_manager {

    /**
     * Run the preflight checks using the given data in all the rules supporting them.
     *
     * @param array $data passed data for validation
     * @param array $files un-used, Moodle seems to not support it anymore
     * @param int|null $attemptid the id of the current attempt, if there is one,
     *      otherwise null.
     * @return array of errors, empty array means no erros
     * @since  Moodle 3.1
     */
    public function validate_preflight_check($data, $files, $attemptid) {
        $errors = array();
        foreach ($this->rules as $rule) {
            if ($rule->is_preflight_check_required($attemptid)) {
                $errors = $rule->validate_preflight_check($data, $files, $errors, $attemptid);
            }
        }
        return $errors;
    }
}

class local_mobile_question_bank extends question_bank {
    /**
     * Return a list of the different question types present in the given categories.
     *
     * @param  array $categories a list of category ids
     * @return array the list of question types in the categories
     * @since  Moodle 3.1
     */
    public static function get_all_question_types_in_categories($categories) {
        global $DB;

        list($categorysql, $params) = $DB->get_in_or_equal($categories);
        $sql = "SELECT DISTINCT q.qtype
                FROM {question} q
                WHERE q.category $categorysql";

        $qtypes = $DB->get_fieldset_sql($sql, $params);
        return $qtypes;
    }
}

function local_mobile_mod_quiz_add_timemodifiedoffline($attempt) {
    global $DB;

    $dbman = $DB->get_manager();
    $attemptstable = new xmldb_table('quizaccess_offlineattempts_a');
    if (!$dbman->table_exists($attemptstable)) {
        return $attempt;
    }

    if ($timemodifiedoffline = $DB->get_field('quizaccess_offlineattempts_a', 'timemodifiedoffline', array('attemptid' => $attempt->id))) {
        $attempt->timemodifiedoffline = $timemodifiedoffline;
    }

    return $attempt;
}

require_once("$CFG->dirroot/mod/assign/locallib.php");

class local_mobile_assign extends assign {

    /** @var stdClass the assignment record that contains the global settings for this assign instance */
    private $instance;

    /** @var stdClass the grade_item record for this assign instance's primary grade item. */
    private $gradeitem;

    /** @var context the context of the course module for this assign instance
     *               (or just the course if we are creating a new one)
     */
    private $context;

    /** @var stdClass the course this assign instance belongs to */
    private $course;

    /** @var stdClass the admin config for all assign instances  */
    private $adminconfig;

    /** @var assign_renderer the custom renderer for this module */
    private $output;

    /** @var cm_info the course module for this assign instance */
    private $coursemodule;

    /** @var array cache for things like the coursemodule name or the scale menu -
     *             only lives for a single request.
     */
    private $cache;

    /** @var array list of the installed submission plugins */
    private $submissionplugins;

    /** @var array list of the installed feedback plugins */
    private $feedbackplugins;

    /** @var string action to be used to return to this page
     *              (without repeating any form submissions etc).
     */
    private $returnaction = 'view';

    /** @var array params to be used to return to this page */
    private $returnparams = array();

    /** @var string modulename prevents excessive calls to get_string */
    private static $modulename = null;

    /** @var string modulenameplural prevents excessive calls to get_string */
    private static $modulenameplural = null;

    /** @var array of marking workflow states for the current user */
    private $markingworkflowstates = null;

    /** @var bool whether to exclude users with inactive enrolment */
    private $showonlyactiveenrol = null;

    /** @var string A key used to identify userlists created by this object. */
    private $useridlistid = null;

    /** @var array cached list of participants for this assignment. The cache key will be group, showactive and the context id */
    private $participants = array();

    /** @var array cached list of user groups when team submissions are enabled. The cache key will be the user. */
    private $usersubmissiongroups = array();

    /** @var array cached list of user groups. The cache key will be the user. */
    private $usergroups = array();

    /** @var array cached list of IDs of users who share group membership with the user. The cache key will be the user. */
    private $sharedgroupmembers = array();

    public function __construct($coursemodulecontext, $coursemodule, $course) {
        global $SESSION;

        $this->context = $coursemodulecontext;
        $this->course = $course;

        // Ensure that $this->coursemodule is a cm_info object (or null).
        $this->coursemodule = cm_info::create($coursemodule);

        // Temporary cache only lives for a single request - used to reduce db lookups.
        $this->cache = array();

        $this->submissionplugins = $this->load_plugins('assignsubmission');
        $this->feedbackplugins = $this->load_plugins('assignfeedback');

        // Extra entropy is required for uniqid() to work on cygwin.
        $this->useridlistid = clean_param(uniqid('', true), PARAM_ALPHANUM);

        if (!isset($SESSION->mod_assign_useridlist)) {
            $SESSION->mod_assign_useridlist = [];
        }
        parent::__construct($coursemodulecontext, $coursemodule, $course);
    }

    /**
     * Creates an assign_submission_status renderable.
     *
     * @param stdClass $user the user to get the report for
     * @param bool $showlinks return plain text or links to the profile
     * @return assign_submission_status renderable object
     */
    public function get_assign_submission_status_renderable($user, $showlinks) {
        global $PAGE;

        $instance = $this->get_instance();
        $flags = $this->get_user_flags($user->id, false);
        $submission = $this->get_user_submission($user->id, false);

        $teamsubmission = null;
        $submissiongroup = null;
        $notsubmitted = array();
        if ($instance->teamsubmission) {
            $teamsubmission = $this->get_group_submission($user->id, 0, false);
            $submissiongroup = $this->get_submission_group($user->id);
            $groupid = 0;
            if ($submissiongroup) {
                $groupid = $submissiongroup->id;
            }
            $notsubmitted = $this->get_submission_group_members_who_have_not_submitted($groupid, false);
        }

        $showedit = $showlinks &&
                    ($this->is_any_submission_plugin_enabled()) &&
                    $this->can_edit_submission($user->id);

        $gradelocked = ($flags && $flags->locked) || $this->grading_disabled($user->id, false);

        // Grading criteria preview.
        $gradingmanager = get_grading_manager($this->context, 'mod_assign', 'submissions');
        $gradingcontrollerpreview = '';
        if ($gradingmethod = $gradingmanager->get_active_method()) {
            $controller = $gradingmanager->get_controller($gradingmethod);
            if ($controller->is_form_defined()) {
                $gradingcontrollerpreview = $controller->render_preview($PAGE);
            }
        }

        $showsubmit = ($showlinks && $this->submissions_open($user->id));
        $showsubmit = ($showsubmit && $this->show_submit_button($submission, $teamsubmission, $user->id));

        $extensionduedate = null;
        if ($flags) {
            $extensionduedate = $flags->extensionduedate;
        }
        $viewfullnames = has_capability('moodle/site:viewfullnames', $this->get_course_context());

        $gradingstatus = $this->get_grading_status($user->id);
        $usergroups = $this->get_all_groups($user->id);
        $submissionstatus = new assign_submission_status($instance->allowsubmissionsfromdate,
                                                          $instance->alwaysshowdescription,
                                                          $submission,
                                                          $instance->teamsubmission,
                                                          $teamsubmission,
                                                          $submissiongroup,
                                                          $notsubmitted,
                                                          $this->is_any_submission_plugin_enabled(),
                                                          $gradelocked,
                                                          $this->is_graded($user->id),
                                                          $instance->duedate,
                                                          $instance->cutoffdate,
                                                          $this->get_submission_plugins(),
                                                          $this->get_return_action(),
                                                          $this->get_return_params(),
                                                          $this->get_course_module()->id,
                                                          $this->get_course()->id,
                                                          assign_submission_status::STUDENT_VIEW,
                                                          $showedit,
                                                          $showsubmit,
                                                          $viewfullnames,
                                                          $extensionduedate,
                                                          $this->get_context(),
                                                          $this->is_blind_marking(),
                                                          $gradingcontrollerpreview,
                                                          $instance->attemptreopenmethod,
                                                          $instance->maxattempts,
                                                          $gradingstatus,
                                                          $instance->preventsubmissionnotingroup,
                                                          $usergroups);
        return $submissionstatus;
    }


    /**
     * Creates an assign_feedback_status renderable.
     *
     * @param stdClass $user the user to get the report for
     * @return assign_feedback_status renderable object
     */
    public function get_assign_feedback_status_renderable($user) {
        global $CFG, $DB, $PAGE;

        require_once($CFG->libdir.'/gradelib.php');
        require_once($CFG->dirroot.'/grade/grading/lib.php');

        $instance = $this->get_instance();
        $grade = $this->get_user_grade($user->id, false);
        $gradingstatus = $this->get_grading_status($user->id);

        $gradinginfo = grade_get_grades($this->get_course()->id,
                                    'mod',
                                    'assign',
                                    $instance->id,
                                    $user->id);

        $gradingitem = null;
        $gradebookgrade = null;
        if (isset($gradinginfo->items[0])) {
            $gradingitem = $gradinginfo->items[0];
            $gradebookgrade = $gradingitem->grades[$user->id];
        }

        // Check to see if all feedback plugins are empty.
        $emptyplugins = true;
        if ($grade) {
            foreach ($this->get_feedback_plugins() as $plugin) {
                if ($plugin->is_visible() && $plugin->is_enabled()) {
                    if (!$plugin->is_empty($grade)) {
                        $emptyplugins = false;
                    }
                }
            }
        }

        if ($this->get_instance()->markingworkflow && $gradingstatus != ASSIGN_MARKING_WORKFLOW_STATE_RELEASED) {
            $emptyplugins = true; // Don't show feedback plugins until released either.
        }

        $cangrade = has_capability('mod/assign:grade', $this->get_context());
        // If there is a visible grade, show the summary.
        if ((!is_null($gradebookgrade->grade) || !$emptyplugins)
                && ($cangrade || !$gradebookgrade->hidden)) {

            $gradefordisplay = null;
            $gradeddate = null;
            $grader = null;
            $gradingmanager = get_grading_manager($this->get_context(), 'mod_assign', 'submissions');

            // Only show the grade if it is not hidden in gradebook.
            if (!is_null($gradebookgrade->grade) && ($cangrade || !$gradebookgrade->hidden)) {
                if ($controller = $gradingmanager->get_active_controller()) {
                    $menu = make_grades_menu($this->get_instance()->grade);
                    $controller->set_grade_range($menu, $this->get_instance()->grade > 0);
                    $gradefordisplay = $controller->render_grade($PAGE,
                                                                 $grade->id,
                                                                 $gradingitem,
                                                                 $gradebookgrade->str_long_grade,
                                                                 $cangrade);
                } else {
                    $gradefordisplay = $this->display_grade($gradebookgrade->grade, false);
                }
                $gradeddate = $gradebookgrade->dategraded;
                if (isset($grade->grader)) {
                    $grader = $DB->get_record('user', array('id' => $grade->grader));
                }
            }

            $feedbackstatus = new assign_feedback_status($gradefordisplay,
                                                  $gradeddate,
                                                  $grader,
                                                  $this->get_feedback_plugins(),
                                                  $grade,
                                                  $this->get_course_module()->id,
                                                  $this->get_return_action(),
                                                  $this->get_return_params());
            return $feedbackstatus;
        }
        return;
    }

    /**
     * Creates an assign_attempt_history renderable.
     *
     * @param stdClass $user the user to get the report for
     * @return assign_attempt_history renderable object
     */
    public function get_assign_attempt_history_renderable($user) {

        $allsubmissions = $this->get_all_submissions($user->id);
        $allgrades = $this->get_all_grades($user->id);

        $history = new assign_attempt_history($allsubmissions,
                                              $allgrades,
                                              $this->get_submission_plugins(),
                                              $this->get_feedback_plugins(),
                                              $this->get_course_module()->id,
                                              $this->get_return_action(),
                                              $this->get_return_params(),
                                              false,
                                              0,
                                              0);
        return $history;
    }

    /**
     * Creates an assign_grading_summary renderable.
     *
     * @return assign_grading_summary renderable object
     */
    public function get_assign_grading_summary_renderable() {

        $instance = $this->get_instance();

        $draft = ASSIGN_SUBMISSION_STATUS_DRAFT;
        $submitted = ASSIGN_SUBMISSION_STATUS_SUBMITTED;

        $activitygroup = groups_get_activity_group($this->get_course_module());

        if ($instance->teamsubmission) {
            $defaultteammembers = $this->get_submission_group_members(0, true);
            $warnofungroupedusers = (count($defaultteammembers) > 0 && $instance->preventsubmissionnotingroup);

            $summary = new assign_grading_summary($this->count_teams($activitygroup),
                                                  $instance->submissiondrafts,
                                                  $this->count_submissions_with_status($draft),
                                                  $this->is_any_submission_plugin_enabled(),
                                                  $this->count_submissions_with_status($submitted),
                                                  $instance->cutoffdate,
                                                  $instance->duedate,
                                                  $this->get_course_module()->id,
                                                  $this->count_submissions_need_grading(),
                                                  $instance->teamsubmission,
                                                  $warnofungroupedusers);
        } else {
            // The active group has already been updated in groups_print_activity_menu().
            $countparticipants = $this->count_participants($activitygroup);
            $summary = new assign_grading_summary($countparticipants,
                                                  $instance->submissiondrafts,
                                                  $this->count_submissions_with_status($draft),
                                                  $this->is_any_submission_plugin_enabled(),
                                                  $this->count_submissions_with_status($submitted),
                                                  $instance->cutoffdate,
                                                  $instance->duedate,
                                                  $this->get_course_module()->id,
                                                  $this->count_submissions_need_grading(),
                                                  $instance->teamsubmission,
                                                  false);

        }

        return $summary;
    }
}

require_once("$CFG->dirroot/course/lib.php");

if (!function_exists('course_get_user_navigation_options')) {
    /**
     * Return an object with the list of navigation options in a course that are avaialable or not for the current user.
     * This function also handles the frontpage course.
     *
     * @param  stdClass $context context object (it can be a course context or the system context for frontpage settings)
     * @param  stdClass $course  the course where the settings are being rendered
     * @return stdClass          the navigation options in a course and their availability status
     * @since  Moodle 3.2
     */
    function course_get_user_navigation_options($context, $course = null) {
        global $CFG;

        $isloggedin = isloggedin();
        $isguestuser = isguestuser();
        $isfrontpage = $context->contextlevel == CONTEXT_SYSTEM;

        if ($isfrontpage) {
            $sitecontext = $context;
        } else {
            $sitecontext = context_system::instance();
        }

        $options = new stdClass;
        $options->blogs = !empty($CFG->enableblogs) &&
                            ($CFG->bloglevel == BLOG_GLOBAL_LEVEL ||
                            ($CFG->bloglevel == BLOG_SITE_LEVEL and ($isloggedin and !$isguestuser)))
                            && has_capability('moodle/blog:view', $sitecontext);

        $options->notes = !empty($CFG->enablenotes) && has_any_capability(array('moodle/notes:manage', 'moodle/notes:view'), $context);

        // Frontpage settings?
        if ($isfrontpage) {
            if ($course->id == SITEID) {
                $options->participants = has_capability('moodle/site:viewparticipants', $sitecontext);
            } else {
                $options->participants = has_capability('moodle/course:viewparticipants', context_course::instance($course->id));
            }

            $options->badges = !empty($CFG->enablebadges) && has_capability('moodle/badges:viewbadges', $sitecontext);
            $options->tags = !empty($CFG->usetags) && $isloggedin;
            $options->search = !empty($CFG->enableglobalsearch) && has_capability('moodle/search:query', $sitecontext);
            $options->calendar = $isloggedin;
        } else {
            $options->participants = has_capability('moodle/course:viewparticipants', $context);
            $options->badges = !empty($CFG->enablebadges) && !empty($CFG->badges_allowcoursebadges) &&
                                has_capability('moodle/badges:viewbadges', $context);
            // Add view grade report is permitted.
            $grades = false;

            if (has_capability('moodle/grade:viewall', $context)) {
                $grades = true;
            } else if (!empty($course->showgrades)) {
                $reports = core_component::get_plugin_list('gradereport');
                if (is_array($reports) && count($reports) > 0) {  // Get all installed reports.
                    arsort($reports);   // User is last, we want to test it first.
                    foreach ($reports as $plugin => $plugindir) {
                        if (has_capability('gradereport/'.$plugin.':view', $context)) {
                            // Stop when the first visible plugin is found.
                            $grades = true;
                            break;
                        }
                    }
                }
            }
            $options->grades = $grades;
        }

        return $options;
    }
}

if (!function_exists('course_get_user_administration_options')) {
    /**
     * Return an object with the list of administration options in a course that are available or not for the current user.
     * This function also handles the frontpage settings.
     *
     * @param  stdClass $course  course object (for frontpage it should be a clone of $SITE)
     * @param  stdClass $context context object (course context)
     * @return stdClass          the administration options in a course and their availability status
     * @since  Moodle 3.2
     */
    function course_get_user_administration_options($course, $context) {
        global $CFG;
        $isfrontpage = $course->id == SITEID;

        $options = new stdClass;
        $options->update = has_capability('moodle/course:update', $context);
        $options->filters = has_capability('moodle/filter:manage', $context) &&
                            count(filter_get_available_in_context($context)) > 0;
        $options->reports = has_capability('moodle/site:viewreports', $context);
        $options->backup = has_capability('moodle/backup:backupcourse', $context);
        $options->restore = has_capability('moodle/restore:restorecourse', $context);
        $options->files = $course->legacyfiles == 2 and has_capability('moodle/course:managefiles', $context);

        if (!$isfrontpage) {
            $options->tags = has_capability('moodle/course:tag', $context);
            $options->gradebook = has_capability('moodle/grade:manage', $context);
            $options->outcomes = !empty($CFG->enableoutcomes) && has_capability('moodle/course:update', $context);
            $options->badges = !empty($CFG->enablebadges);
            $options->import = has_capability('moodle/restore:restoretargetimport', $context);
            $options->publish = has_capability('moodle/course:publish', $context);
            $options->reset = has_capability('moodle/course:reset', $context);
            $options->roles = has_capability('moodle/role:switchroles', $context);
        } else {
            // Set default options to false.
            $listofoptions = array('tags', 'gradebook', 'outcomes', 'badges', 'import', 'publish', 'reset', 'roles', 'grades');

            foreach ($listofoptions as $option) {
                $options->$option = false;
            }
        }

        return $options;
    }
}

require_once("$CFG->dirroot/user/lib.php");

/**
 * Updates the provided users profile picture based upon the expected fields returned from the edit or edit_advanced forms.
 *
 * @param stdClass $usernew An object that contains some information about the user being updated
 * @param array $filemanageroptions
 * @return bool True if the user was updated, false if it stayed the same.
 */
function local_mobile_core_user_update_picture(stdClass $usernew, $filemanageroptions = array()) {
    global $CFG, $DB;
    require_once("$CFG->libdir/gdlib.php");

    $context = context_user::instance($usernew->id, MUST_EXIST);
    $user = core_user::get_user($usernew->id, 'id, picture', MUST_EXIST);

    $newpicture = $user->picture;
    // Get file_storage to process files.
    $fs = get_file_storage();
    if (!empty($usernew->deletepicture)) {
        // The user has chosen to delete the selected users picture.
        $fs->delete_area_files($context->id, 'user', 'icon'); // Drop all images in area.
        $newpicture = 0;

    } else {
        // Save newly uploaded file, this will avoid context mismatch for newly created users.
        file_save_draft_area_files($usernew->imagefile, $context->id, 'user', 'newicon', 0, $filemanageroptions);
        if (($iconfiles = $fs->get_area_files($context->id, 'user', 'newicon')) && count($iconfiles) == 2) {
            // Get file which was uploaded in draft area.
            foreach ($iconfiles as $file) {
                if (!$file->is_directory()) {
                    break;
                }
            }
            // Copy file to temporary location and the send it for processing icon.
            if ($iconfile = $file->copy_content_to_temp()) {
                // There is a new image that has been uploaded.
                // Process the new image and set the user to make use of it.
                // NOTE: Uploaded images always take over Gravatar.
                $newpicture = (int)process_new_icon($context, 'user', 'icon', 0, $iconfile);
                // Delete temporary file.
                @unlink($iconfile);
                // Remove uploaded file.
                $fs->delete_area_files($context->id, 'user', 'newicon');
            } else {
                // Something went wrong while creating temp file.
                // Remove uploaded file.
                $fs->delete_area_files($context->id, 'user', 'newicon');
                return false;
            }
        }
    }

    if ($newpicture != $user->picture) {
        $DB->set_field('user', 'picture', $newpicture, array('id' => $user->id));
        return true;
    } else {
        return false;
    }
}

if (!class_exists('external_files')) {
    require_once("$CFG->libdir/externallib.php");

    /**
     * External structure representing a set of files.
     *
     * @package    core_webservice
     * @copyright  2016 Juan Leyva
     * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     * @since      Moodle 3.2
     */
    class external_files extends external_multiple_structure {

        /**
         * Constructor
         * @param string $desc Description for the multiple structure.
         * @param int $required The type of value (VALUE_REQUIRED OR VALUE_OPTIONAL).
         */
        public function __construct($desc = 'List of files.', $required = VALUE_REQUIRED) {

            parent::__construct(
                new external_single_structure(
                    array(
                        'filename' => new external_value(PARAM_FILE, 'File name.', VALUE_OPTIONAL),
                        'filepath' => new external_value(PARAM_PATH, 'File path.', VALUE_OPTIONAL),
                        'filesize' => new external_value(PARAM_INT, 'File size.', VALUE_OPTIONAL),
                        'fileurl' => new external_value(PARAM_URL, 'Downloadable file url.', VALUE_OPTIONAL),
                        'timemodified' => new external_value(PARAM_INT, 'Time modified.', VALUE_OPTIONAL),
                        'mimetype' => new external_value(PARAM_RAW, 'File mime type.', VALUE_OPTIONAL),
                    ),
                    'File.'
                ),
                $desc,
                $required
            );
        }

        /**
         * Return the properties ready to be used by an exporter.
         *
         * @return array properties
         * @since  Moodle 3.3
         */
        public static function get_properties_for_exporter() {
            return [
                'filename' => array(
                    'type' => PARAM_FILE,
                    'description' => 'File name.',
                    'optional' => true,
                    'null' => NULL_NOT_ALLOWED,
                ),
                'filepath' => array(
                    'type' => PARAM_PATH,
                    'description' => 'File path.',
                    'optional' => true,
                    'null' => NULL_NOT_ALLOWED,
                ),
                'filesize' => array(
                    'type' => PARAM_INT,
                    'description' => 'File size.',
                    'optional' => true,
                    'null' => NULL_NOT_ALLOWED,
                ),
                'fileurl' => array(
                    'type' => PARAM_URL,
                    'description' => 'Downloadable file url.',
                    'optional' => true,
                    'null' => NULL_NOT_ALLOWED,
                ),
                'timemodified' => array(
                    'type' => PARAM_INT,
                    'description' => 'Time modified.',
                    'optional' => true,
                    'null' => NULL_NOT_ALLOWED,
                ),
                'mimetype' => array(
                    'type' => PARAM_RAW,
                    'description' => 'File mime type.',
                    'optional' => true,
                    'null' => NULL_NOT_ALLOWED,
                ),
            ];
        }
    }

}

/**
 * Returns all area files (optionally limited by itemid).
 *
 * @param int $contextid context ID
 * @param string $component component
 * @param string $filearea file area
 * @param int $itemid item ID or all files if not specified
 * @param bool $useitemidinurl wether to use the item id in the file URL (modules intro don't use it)
 * @return array of files, compatible with the external_files structure.
 * @since Moodle 3.2
 */
function local_mobile_get_area_files($contextid, $component, $filearea, $itemid = false, $useitemidinurl = true) {
    $files = array();
    $fs = get_file_storage();

    if ($areafiles = $fs->get_area_files($contextid, $component, $filearea, $itemid, 'itemid, filepath, filename', false)) {
        foreach ($areafiles as $areafile) {
            $file = array();
            $file['filename'] = $areafile->get_filename();
            $file['filepath'] = $areafile->get_filepath();
            $file['mimetype'] = $areafile->get_mimetype();
            $file['filesize'] = $areafile->get_filesize();
            $file['timemodified'] = $areafile->get_timemodified();
            $fileitemid = $useitemidinurl ? $areafile->get_itemid() : null;
            $file['fileurl'] = moodle_url::make_webservice_pluginfile_url($contextid, $component, $filearea,
                                $fileitemid, $areafile->get_filepath(), $areafile->get_filename())->out(false);
            $files[] = $file;
        }
    }
    return $files;
}
