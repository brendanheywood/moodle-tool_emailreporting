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
 * Unit tests for email reporting
 *
 * @package    tool_emailreporting
 * @copyright  Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Tests
 *
 * @copyright  Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_emailreporting_locallib_testcase extends advanced_testcase {

    /**
     * Parse email Message ids.
     */
    public function test_messageid_parse() {

        require_once(__DIR__ . '/../locallib.php');

        $parts = tool_emailreporting_parse_messageid('<1234@example.com>');
        $this->assertEquals($parts['path'], '');
        $this->assertEquals($parts['msgid'], '1234');
        $this->assertEquals($parts['domain'], 'example.com');
        $this->assertEquals($parts['wwwroot'], '://example.com');

        $parts = tool_emailreporting_parse_messageid('<1234/some/dir@example.com>');
        $this->assertEquals($parts['path'], '/some/dir');
        $this->assertEquals($parts['msgid'], '1234');
        $this->assertEquals($parts['domain'], 'example.com');
        $this->assertEquals($parts['wwwroot'], '://example.com/some/dir');
    }
}

