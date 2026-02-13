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
 * Vendor edit user page.
 *
 * @package   local_vendorbilling
 */

require_once(__DIR__ . '/../../config.php');

use local_vendorbilling\manager;
use local_vendorbilling\form\edit_user_form;

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

$userid = required_param('userid', PARAM_INT);
$user = manager::get_vendor_user($vendor, $userid);
if (!$user) {
    throw new moodle_exception('error_invalid_edit', 'local_vendorbilling');
}

$PAGE->set_url(new moodle_url('/local/vendorbilling/edit_user.php', ['userid' => $userid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('portal_edit_heading', 'local_vendorbilling'));
$PAGE->set_heading(get_string('portal_edit_heading', 'local_vendorbilling'));

$form = new edit_user_form();
$form->set_data($user);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/vendorbilling/index.php'));
}

if ($data = $form->get_data()) {
    try {
        manager::update_vendor_user($vendor, $userid, (array) $data);
        redirect(new moodle_url('/local/vendorbilling/index.php'),
            get_string('portal_updated', 'local_vendorbilling'),
            null, \core\output\notification::NOTIFY_SUCCESS);
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
