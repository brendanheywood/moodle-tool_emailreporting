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

define('EMAIL_TRACKING_CONFIG',                  0);
define('EMAIL_TRACKING_QUEUED',                 10);
define('EMAIL_TRACKING_SMTP_FAIL',              40);
define('EMAIL_TRACKING_SMTP_SENT',              50);
define('EMAIL_TRACKING_BOUNCED',                60);
define('EMAIL_TRACKING_BEACON_SEEN_REFERENCED', 70);
define('EMAIL_TRACKING_BEACON_SEEN',            80);
define('EMAIL_TRACKING_CLICKED',                90);

// The idea behind this is that an email travels through a linear state machine.
// At different points a metric may determine that a particular state has been
// acheived, but it will only even advance to a 'higher' state. Higher states
// are not nessesarily 'better', but generally are more 'certain' as to wether
// they are a defintely failure or a definte action.
//
// As a general rule most failure states like 'smtp_failed' would never be
// progressed to a higher level, but some like a soft SMTP fail due to a mail
// box quota being exceeded, or graylisting, may be temporary and later move
// to a higher state.
//
// A large conceptual number of states will be added here, but not all of them
// will have a known way to detect them, or in cases where a metric is known
// it may not have been implemented yet. Some such as DMARC forensic reporting
// do not guarantee that all emails will be reported on. All states are
// purposefully spaced apart so that future intermediate states can be added.
//
$states = array(

    // TODO
    // The email couldn't be sent as something is configured incorrectly.
    // This is critical as all emails are affected.
    EMAIL_TRACKING_CONFIG => array(
        'queued',
        'critical',
    ),

    // Failed_invalid.
    // Failed_bounce_threshold.

    // An email has been sent to an MTA or MSA, we know it has been queued
    // but processing is done asynconously and we may not get an SMTP result
    // unless feedback from the MTA is given back to moodle.
    EMAIL_TRACKING_QUEUED => array(
        'queued',
        'unknown',
    ),

    // Smtp_delayed.

    // TODO
    // We have received defintey feedback that STMP delivery failed, either
    // because Moodle made the SMTP connection itself, or the MTA has been
    // setup to provide feedback to moodle.
    EMAIL_TRACKING_SMTP_FAIL => array(
        'smtpfail',
        'fail',
    ),

    // TODO
    // We have received defintey feedback that STMP delivery worked, either
    // because Moodle made the SMTP connection itself, or the MTA has been
    // setup to provide feedback to moodle.
    EMAIL_TRACKING_SMTP_SENT => array(
        'smtpass',
        'unknown',
    ),

    // TODO
    // The email was sent and a bounce message was returned. This requires
    // moodle bounce processing to be configured.
    EMAIL_TRACKING_BOUNCED => array(
        'bounced',
        'fail',
    ),

    // SPF / dkim /dmarc failure - dmarc forensic report processing via VERP.
    //
    // Delivered into quarantine - no known metric.
    //
    // Inbox but then marked as spam - feedback loop (not metric yet, possibly
    // 3rd party mta tools).

    // TODO
    // If you received a batch of emails which are threaded, such as some forum
    // emails, you may simple click on the most recent, then click on a url in
    // it to view the thread online. Conceptually we can related this group of
    // emails and mark all of them as having been seen.
    EMAIL_TRACKING_BEACON_SEEN_REFERENCED => array(
        'beaconseenreferenced',
        'success',
    ),

    // Triggered when an html email is opened and images are turned on in the email client.
    EMAIL_TRACKING_BEACON_SEEN => array(
        'beaconseen',
        'success',
    ),

    // This is triggered when any link in an html email is clicked. We are
    // generally not too concerned with what the link was, that can be easily
    // tracked in other places, but if images are off in the email client but
    // they still click on a link, then we know the email was opened.
    EMAIL_TRACKING_CLICKED => array(
        'clicked',
        'success',
    ),

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

    // Rewrite html to add click tracking to outgoing links.
    $dom = new DOMDocument;
    $dom->loadHTML($html);
    foreach ($dom->getElementsByTagName('a') as $node) {
        $link = new moodle_url($node->getAttribute('href'));
        $murl = new moodle_url('/admin/tool/emailreporting/click.php', array(
            'msgid' => $parts['msgid'],
            'go' => $link,
        ));

        $node->setAttribute('href', $murl->out(false));

    }
    $html = $dom->saveHtml();

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
    // E mail->Sender ;
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

    // TODO add course and cmid and message system tracking.

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
        }
    }
}

