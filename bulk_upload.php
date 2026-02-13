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
 * Vendor bulk upload page.
 *
 * @package   local_vendorbilling
 */

require_once(__DIR__ . '/../../config.php');

use local_vendorbilling\manager;
use local_vendorbilling\form\bulk_upload_form;

require_login();
$context = context_system::instance();
require_capability('local/vendorbilling:vendoradmin', $context);

$vendor = manager::get_vendor_for_user($USER->id);
if (!$vendor) {
    throw new moodle_exception('error_vendor_not_found', 'local_vendorbilling');
}
if (!manager::is_active_status($vendor->status)) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('portal_usersuspension', 'local_vendorbilling'), 'error');
    echo $OUTPUT->continue_button(new moodle_url('/local/vendorbilling/index.php'));
    echo $OUTPUT->footer();
    exit;
}

$PAGE->set_url(new moodle_url('/local/vendorbilling/bulk_upload.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('portal_bulkupload', 'local_vendorbilling'));
$PAGE->set_heading(get_string('portal_bulkupload', 'local_vendorbilling'));

$form = new bulk_upload_form();

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/vendorbilling/index.php'));
}

if ($data = $form->get_data()) {
    $content = $form->get_file_content('csvfile');
    if (empty($content)) {
        echo $OUTPUT->header();
        echo $OUTPUT->notification(get_string('error_invalid_csv', 'local_vendorbilling'), 'error');
        $form->display();
        echo $OUTPUT->footer();
        exit;
    }

    $rows = [];
    $lines = preg_split('/\r\n|\r|\n/', trim($content));
    if (count($lines) < 2) {
        echo $OUTPUT->header();
        echo $OUTPUT->notification(get_string('error_invalid_csv', 'local_vendorbilling'), 'error');
        $form->display();
        echo $OUTPUT->footer();
        exit;
    }

    $headers = str_getcsv(array_shift($lines));
    $map = [];
    foreach ($headers as $index => $header) {
        $key = strtolower(trim($header));
        $map[$key] = $index;
    }
    foreach (['firstname', 'lastname', 'email'] as $required) {
        if (!array_key_exists($required, $map)) {
            echo $OUTPUT->header();
            echo $OUTPUT->notification(get_string('error_invalid_csv', 'local_vendorbilling'), 'error');
            $form->display();
            echo $OUTPUT->footer();
            exit;
        }
    }

    foreach ($lines as $line) {
        if (trim($line) === '') {
            continue;
        }
        $values = str_getcsv($line);
        $rows[] = [
            'firstname' => $values[$map['firstname']] ?? '',
            'lastname' => $values[$map['lastname']] ?? '',
            'email' => $values[$map['email']] ?? '',
            'username' => isset($map['username']) ? ($values[$map['username']] ?? '') : '',
        ];
    }

    if (empty($rows)) {
        echo $OUTPUT->header();
        echo $OUTPUT->notification(get_string('error_invalid_csv', 'local_vendorbilling'), 'error');
        $form->display();
        echo $OUTPUT->footer();
        exit;
    }

    $errors = [];
    try {
        $created = manager::bulk_create_users($vendor, $rows, $errors);
        echo $OUTPUT->header();
        echo $OUTPUT->notification('Created ' . $created . ' users.', 'success');
        foreach ($errors as $error) {
            echo $OUTPUT->notification($error, 'error');
        }
        echo $OUTPUT->continue_button(new moodle_url('/local/vendorbilling/index.php'));
        echo $OUTPUT->footer();
        exit;
    } catch (moodle_exception $e) {
        echo $OUTPUT->header();
        echo $OUTPUT->notification($e->getMessage(), 'error');
        $form->display();
        echo $OUTPUT->footer();
        exit;
    }
}

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
