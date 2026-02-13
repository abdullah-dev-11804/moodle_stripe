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
 * Admin simulation page.
 *
 * @package   local_vendorbilling
 */

require_once(__DIR__ . '/../../../config.php');

use local_vendorbilling\manager;
use local_vendorbilling\form\simulate_form;

require_login();
$context = context_system::instance();
require_capability('local/vendorbilling:manage', $context);

$PAGE->set_url(new moodle_url('/local/vendorbilling/admin/simulate.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('admin_simulate', 'local_vendorbilling'));
$PAGE->set_heading(get_string('admin_simulate', 'local_vendorbilling'));

$form = new simulate_form();

if ($form->is_cancelled()) {
    redirect(new moodle_url('/admin/settings.php', ['section' => 'local_vendorbilling']));
}

if ($data = $form->get_data()) {
    $vendor = $DB->get_record('local_vendorbilling_vendor', ['id' => (int) $data->vendorid]);
    echo $OUTPUT->header();
    if (!$vendor) {
        echo $OUTPUT->notification(get_string('error_vendor_not_found', 'local_vendorbilling'), 'error');
    } else {
        manager::set_vendor_status($vendor, $data->status);
        echo $OUTPUT->notification('Updated vendor status to ' . $data->status, 'success');
    }
    echo $OUTPUT->continue_button(new moodle_url('/admin/settings.php', ['section' => 'local_vendorbilling']));
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('admin_simulate_desc', 'local_vendorbilling'));
$form->display();
echo $OUTPUT->footer();
