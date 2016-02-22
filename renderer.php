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
 * Renderers
 *
 * @package    tool_emailreporting
 * @copyright  2016 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Class tool_emailreporting_renderer
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright Catalyst
 */
class tool_emailreporting_renderer extends plugin_renderer_base {

    /**
     * Renderer for a state flow bar
     *
     * @param directory_user $user
     * @return html string
     */
    public function render_states($states, $width = '100%', $height = '40px') {

        require_once(__DIR__ . '/locallib.php');

        global $email_states;

        $out = sprintf('<div class="statebar" style="width:%s; height:%s; line-height: %s;">',
            $width,
            $height,
            $height
        );

        usort($states, function($a, $b) {
            return $a->state - $b->state;
        });

        $total = 0;
        foreach ($states as $state) {
            $total += $state->count;
        }

        foreach ($states as $state) {
            $out .= sprintf ('<span style="width: %.1f%%" class="%s" title="%s %d / %d">%.1f%% %s</span>',
                100 * $state->count / $total,
                $email_states[$state->state][0],
                get_string($email_states[$state->state][0], 'tool_emailreporting'),
                $state->count,
                $total,
                100 * $state->count / $total,
                get_string($email_states[$state->state][0], 'tool_emailreporting')
            );
        }

        $out .= '</out>';
        return $out;
    }
}

