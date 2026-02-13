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
 * Stripe webhook endpoint.
 *
 * @package   local_vendorbilling
 */

define('NO_DEBUG_DISPLAY', true);
require_once(__DIR__ . '/../../config.php');

use local_vendorbilling\stripe\signature;
use local_vendorbilling\stripe\event_processor;
use local_vendorbilling\manager;

$payload = file_get_contents('php://input');
$sigheader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$secret = (string) get_config('local_vendorbilling', 'webhooksecret');

header('Content-Type: text/plain');

if (!signature::verify($payload, $sigheader, $secret)) {
    http_response_code(400);
    echo get_string('error_webhook_signature', 'local_vendorbilling');
    exit;
}

$event = json_decode($payload, true);
if (!is_array($event) || empty($event['id'])) {
    http_response_code(400);
    echo get_string('error_webhook_payload', 'local_vendorbilling');
    exit;
}

$eventid = $event['id'];
$eventtype = $event['type'] ?? 'unknown';
$payloadhash = hash('sha256', $payload);
$now = time();

if ($DB->record_exists('local_vendorbilling_eventlog', ['event_id' => $eventid])) {
    http_response_code(200);
    echo 'Duplicate event ignored.';
    exit;
}

$record = (object) [
    'event_id' => $eventid,
    'event_type' => $eventtype,
    'payload_hash' => $payloadhash,
    'received_at' => $now,
    'processed_at' => null,
    'status' => 'received',
    'error' => null,
];
$record->id = $DB->insert_record('local_vendorbilling_eventlog', $record);

try {
    event_processor::process($event);
    $record->processed_at = time();
    $record->status = 'processed';
    $DB->update_record('local_vendorbilling_eventlog', $record);

    http_response_code(200);
    echo 'OK';
} catch (Throwable $e) {
    $record->processed_at = time();
    $record->status = 'error';
    $record->error = $e->getMessage();
    $DB->update_record('local_vendorbilling_eventlog', $record);

    manager::log('error', 'Webhook processing failed', null, [
        'event_id' => $eventid,
        'error' => $e->getMessage(),
    ]);

    http_response_code(500);
    echo 'Webhook processing error.';
}
