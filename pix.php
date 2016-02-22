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
 * An image beacon tracker for emails
 *
 * @package    tool_emailreporting
 * @copyright  2016 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once('locallib.php');

$messageid = optional_param('m', '', PARAM_RAW);

// TODO References header for threaded emails.

if ($messageid) {
    tool_emailreporting_advance_state($messageid, EMAIL_TRACKING_BEACON_SEEN, array(
        'seen' => time(),
        'agentua' => $_SERVER['HTTP_USER_AGENT'],
    ));
} else {
    // Should never happen.
}

// This renders an 1x1 invisible gif.
// See also http://stackoverflow.com/questions/4665960/most-efficient-1x1-gif.
header('Content-Type: image/gif');
echo "\x47\x49\x46\x38\x37\x61\x1\x0\x1\x0\x80\x0\x0\xfc\x6a\x6c\x0\x0\x0\x2c\x0\x0\x0\x0\x1\x0\x1\x0\x0\x2\x2\x44\x1\x0\x3b";

