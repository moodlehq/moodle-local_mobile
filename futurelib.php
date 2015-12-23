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
