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
