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
 * Version details.
 *
 * @package    tool_emailreporting
 * @copyright  2016 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once('locallib.php');

require_login();
admin_externalpage_setup('toolemailreporting');

$courseid   = optional_param('cid',  '', PARAM_INT);
$cmid       = optional_param('cmid', '', PARAM_INT);
$fromdomain = optional_param('fd',   '', PARAM_RAW);
$todomain   = optional_param('td',   '', PARAM_RAW);
$agent      = optional_param('a',    '', PARAM_RAW);

$PAGE->requires->css('/admin/tool/emailreporting/styles.css');
echo $OUTPUT->header();

$sql = "
    SELECT l.state,
           count(l.state)
      FROM {tool_emailreporting_log} l
  GROUP BY l.state
";

$states = $DB->get_records_sql($sql);
$output = $PAGE->get_renderer('tool_emailreporting');
echo "<div class='tool_emailreporting'>";
echo $output->render_states($states);
echo "</div>";
echo $OUTPUT->footer();

