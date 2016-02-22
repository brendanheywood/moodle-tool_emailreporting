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
 * Locallib.
 *
 * @package    tool_emailreporting
 * @copyright  2016 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


define('EMAIL_TRACKING_QUEUED',                 10);
define('EMAIL_TRACKING_SMTP_FAIL',              40);
define('EMAIL_TRACKING_BEACON_SEEN_REFERENCED', 70);
define('EMAIL_TRACKING_BEACON_SEEN',            80);



// The idea behind this is that an email travels through a linear state machine.
// At different points a metric may determine that a particular state has been
// acheived, but it will only even advance to a 'higher' state. Higher states
// are not nessesarily 'better', but generally are more 'certain' as to wether
// they are a defintely failure or a definte action.
//
// As a general rule some states like 'smtp_failed' would never be progressed
// to a higher level.
//
$stats = array(

    // failed_invalid
    // failed_config
    //
    // failed_bounce_threshold
    //
    // ....?
    //
    // queued
    EMAIL_TRACKING_QUEUED => array(
        'queued', // lang pack
        'unknown', // color
    ),
    //
    // smtp_delayed
    //
    // smtp_sent <- log process or smtp result
    //
    // smtp_failed
    EMAIL_TRACKING_SMTP_FAIL => array(
        'smtpfail', // lang pack
        'fail', // color
    ),
    //
    // bounced - bounce processing - incoming / VERP
    //
    // spf / dkim /dmarc failure - dmarc forensic report processing via VERP
    //
    // delivered into quarantine - no known metric
    //
    // inbox but then marked as spam - feedback loop (not metric yet, possibly 3rd party mta tools)
    //

    // html_opened_related - if you have 10 emails in a thread an open the last one, flag the others in the thread
    //                       as having a 'related' email opened
    EMAIL_TRACKING_BEACON_SEEN_REFERENCED => array(
        'beaconseenreferenced',
        'success',
    ),

    EMAIL_TRACKING_BEACON_SEEN => array(
        'beaconseen',
        'success',
    ),
    //
    // html_opened
    //
    // html_clicked
    //
    // moodle_seen <- url? maybe too hard, means we have to parse out a url and store, which url if many? what if no url?

);


/**
 * Given the html email body rewrite any links and add a tracking beacon
 *
 * The $mail object may not have actually been sent. If it will
 * be then the email html body will be rewritten to add open
 * and click tracking links.
 *
 * @param string $messageid a MessageId even if not sent
 * @param string $html the email body
 * @return string the rewritten email body
 */
function tool_emailreporting_rewrite_email($messageid, $html) {

    $parts = tool_emailreporting_parse_messageid($messageid);

    $dom = new DOMDocument;
    $dom->loadHTML($html);
    foreach ($dom->getElementsByTagName('a') as $node) {
        $link = new moodle_url($node->getAttribute('href'));
        $link->params(array('msgid' => $parts['msgid']));
        $node->setAttribute('href', $link->out(false));

    }
    $html = $dom->saveHtml();
    // Rewrite html to add outgoing links
    // e($html);

    // Add the tracking beacon.
    $beacon = new moodle_url('/admin/tool/emailreporting/pix.php', array('m' => $parts['msgid']));
    $html .= "<img width=0 height=0 src='$beacon' />";

    return $html;
}

/**
 * Parse a MessageID into component parts
 *
 * @param string $messageid a MessageId even if not sent
 * @return array of parsed message id components
 */
function tool_emailreporting_parse_messageid($messageid) {

    preg_match('/^<?([^\/+]*)(\/.*?)?@(.*)>$/', $messageid, $match);

    $parts = array(
        'msgid' => $match[1],
        'path' => $match[2],
        'domain' => $match[3],
        'wwwroot' => '://' . $match[3] . $match[2],
    );

    return $parts;
}

/**
 * Create a new email tracking record
 *
 * @param integer $state a state
 * @param phpmailer $mail a mail object
 */
function tool_emailreporting_set_state($state, $mail) {

    global $DB;
    // e($mail->Sender);
    // TODO should we log who the email is from, or the sender, if different?

    preg_match('/^(.*)@(.*)$/', $mail->getToAddresses()[0][0], $to);
    preg_match('/^(.*)@(.*)$/', $mail->From, $from);

    $parts = tool_emailreporting_parse_messageid($mail->MessageID);

    $record = (object) array(
        'state' => $state,
        'created' => time(),
        'lastmod' => time(),
        'tolocal' => $to[1],
        'todomain' => $to[2],
        'fromlocal' => $from[1],
        'fromdomain' => $from[2],
        'msgid' => $parts['msgid'],
        'subject' => $mail->Subject,
        'html' => empty($mail->AltBody) ? 0 : 1,
    );

        // <FIELD NAME="courseid" TYPE="int" LENGTH="10" NOTNULL="false" SEQUENCE="false"/>
        // <FIELD NAME="cmid" TYPE="int" LENGTH="10" NOTNULL="false" SEQUENCE="false"/>
        // <FIELD NAME="system" TYPE="text" NOTNULL="false" SEQUENCE="false" COMMENT="Moodle message system or file path"/>

    // e($record);

    $DB->insert_record('tool_emailreporting_log', $record);

}

/**
 * Advance an email state to a higher level
 *
 * @param string $messageid a MessageId local part
 * @param integer $state a state
 * @param arrary $update other fields to set for the email record
 */
function tool_emailreporting_advance_state($messageid, $state, $update = null) {

    global $DB;

    $record = $DB->get_record('tool_emailreporting_log', array('msgid' => $messageid));

    if ($record) {

        if ($state >= $record->state) {
            if ($update) {
                $record = (object)array_merge((array)$record, $update);
            }
            // We only ever advance the email state, can't go back!
            $record->state = $state;
            $record->lastmod = time();
            $DB->update_record('tool_emailreporting_log', $record);
        } else {
            // If the state doesn't advance then do nothing.
        }

    } else {
        // Should never happen.
    }
}


