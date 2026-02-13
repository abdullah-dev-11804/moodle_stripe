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
 * Vendor portal dashboard.
 *
 * @package   local_vendorbilling
 */

require_once(__DIR__ . '/../../config.php');

use local_vendorbilling\manager;

require_login();
$context = context_system::instance();
require_capability('local/vendorbilling:vendoradmin', $context);

$vendor = manager::get_vendor_for_user($USER->id);
if (!$vendor) {
    throw new moodle_exception('error_vendor_not_found', 'local_vendorbilling');
}

$action = optional_param('action', '', PARAM_ALPHANUMEXT);
$userid = optional_param('userid', 0, PARAM_INT);
$suspend = optional_param('suspend', 0, PARAM_INT);

$PAGE->set_url(new moodle_url('/local/vendorbilling/index.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('portal_heading', 'local_vendorbilling'));
$PAGE->set_heading(get_string('portal_heading', 'local_vendorbilling'));

$seatsused = manager::get_seat_usage($vendor);
$seatsremaining = manager::get_seat_remaining($vendor);
$seatlimit = (int) ($vendor->seat_limit ?? 0);
$statusactive = manager::is_active_status($vendor->status);
$dashboardurl = (string) get_config('local_vendorbilling', 'dashboard_url');

if ($action === 'delete' && $userid) {
    require_sesskey();
    try {
        manager::delete_vendor_user($vendor, $userid);
        redirect(new moodle_url('/local/vendorbilling/index.php'),
            get_string('portal_deleted', 'local_vendorbilling'),
            null,
            \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        echo $OUTPUT->header();
        echo $OUTPUT->notification($e->getMessage(), 'error');
        echo $OUTPUT->continue_button(new moodle_url('/local/vendorbilling/index.php'));
        echo $OUTPUT->footer();
        exit;
    }
}
if ($action === 'unsubscribe') {
    require_sesskey();
    try {
        $portalurl = manager::create_portal_session($vendor);
        manager::log('info', 'Redirecting vendor to Stripe portal', $vendor->id ?? null, [
            'vendor_admin_userid' => $vendor->vendor_admin_userid ?? null,
        ]);
        redirect($portalurl);
    } catch (moodle_exception $e) {
        echo $OUTPUT->header();
        echo $OUTPUT->notification($e->getMessage(), 'error');
        echo $OUTPUT->continue_button(new moodle_url('/local/vendorbilling/index.php'));
        echo $OUTPUT->footer();
        exit;
    }
}
if ($action === 'suspend' && $userid) {
    require_sesskey();
    try {
        manager::set_vendor_user_suspension($vendor, $userid, (bool) $suspend);
        redirect(new moodle_url('/local/vendorbilling/index.php'),
            $suspend ? get_string('portal_suspended', 'local_vendorbilling') : get_string('portal_updated', 'local_vendorbilling'),
            null,
            \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        echo $OUTPUT->header();
        echo $OUTPUT->notification($e->getMessage(), 'error');
        echo $OUTPUT->continue_button(new moodle_url('/local/vendorbilling/index.php'));
        echo $OUTPUT->footer();
        exit;
    }
}

echo $OUTPUT->header();

echo html_writer::tag('style', '
.vb-portal { font-family: "Source Sans 3", "Segoe UI", sans-serif; }
.vb-hero { background: linear-gradient(135deg, #0b3b3b, #0f5a5a); color: #fff; border-radius: 16px; padding: 24px; display: flex; flex-wrap: wrap; gap: 16px; align-items: center; justify-content: space-between; }
.vb-hero h2 { margin: 0 0 6px; font-family: "Fraunces", "Georgia", serif; font-size: 28px; }
.vb-hero p { margin: 0; opacity: 0.9; }
.vb-status { display: inline-flex; align-items: center; gap: 8px; padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; background: rgba(255,255,255,0.15); }
.vb-actions { display: flex; flex-wrap: wrap; gap: 10px; }
.vb-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 16px; border-radius: 10px; text-decoration: none; font-weight: 600; border: 1px solid transparent; }
.vb-btn-primary { background: #f4d35e; color: #1b1b1b; }
.vb-btn-secondary { background: #ffffff; color: #0b3b3b; }
.vb-btn-outline { background: transparent; color: #ffffff; border-color: rgba(255,255,255,0.5); }
.vb-btn-danger { background: #f25c54; color: #ffffff; }
.vb-btn-disabled { pointer-events: none; opacity: 0.5; }
.vb-grid { display: grid; gap: 16px; margin-top: 16px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
.vb-card { background: #ffffff; border-radius: 14px; padding: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
.vb-card h3 { margin: 0 0 10px; font-size: 16px; }
.vb-metric { font-size: 26px; font-weight: 700; margin: 0; }
.vb-muted { color: #5b6670; font-size: 13px; margin: 6px 0 0; }
.vb-progress { height: 8px; background: #e5eef0; border-radius: 999px; overflow: hidden; margin-top: 8px; }
.vb-progress span { display: block; height: 100%; background: #0f5a5a; width: 0%; }
.vb-banner { margin-top: 16px; padding: 14px 16px; border-radius: 12px; background: #fff4e5; border: 1px solid #f8d39b; color: #6b4d16; }
.vb-banner a { color: inherit; font-weight: 700; }
.vb-users { margin-top: 20px; }
.vb-table td, .vb-table th { padding: 10px 8px; }
.vb-table .actions { white-space: nowrap; }
', ['class' => 'vb-style']);

echo html_writer::start_tag('div', ['class' => 'vb-portal']);

$statusmap = [
    manager::STATUS_PAST_DUE => get_string('status_past_due', 'local_vendorbilling'),
    manager::STATUS_UNPAID => get_string('status_unpaid', 'local_vendorbilling'),
    manager::STATUS_CANCELED => get_string('status_canceled', 'local_vendorbilling'),
    manager::STATUS_TRIALING => get_string('status_trialing', 'local_vendorbilling'),
];
$statuslabel = $statusactive ? get_string('status_active', 'local_vendorbilling') : ($statusmap[$vendor->status] ?? format_string($vendor->status));

echo html_writer::start_tag('div', ['class' => 'vb-hero']);
echo html_writer::start_tag('div');
echo html_writer::tag('h2', get_string('portal_heading', 'local_vendorbilling') . ' — ' . format_string($vendor->org_name));
echo html_writer::tag('p', get_string('portal_seatlimit', 'local_vendorbilling') . ': ' . $seatlimit);
echo html_writer::tag('div', $statuslabel, ['class' => 'vb-status']);
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'vb-actions']);
$createurl = new moodle_url('/local/vendorbilling/create_user.php');
$bulkuploadurl = new moodle_url('/local/vendorbilling/bulk_upload.php');
$createattrs = ['class' => 'vb-btn vb-btn-primary' . ($statusactive ? '' : ' vb-btn-disabled')];
$bulkattrs = ['class' => 'vb-btn vb-btn-secondary' . ($statusactive ? '' : ' vb-btn-disabled')];
echo html_writer::link($createurl, get_string('portal_createuser', 'local_vendorbilling'), $createattrs);
echo html_writer::link($bulkuploadurl, get_string('portal_bulkupload', 'local_vendorbilling'), $bulkattrs);

$unsuburl = new moodle_url('/local/vendorbilling/index.php', [
    'action' => 'unsubscribe',
    'sesskey' => sesskey(),
]);
$unsubattrs = [
    'class' => 'vb-btn vb-btn-outline',
    'onclick' => "return confirm('" . get_string('portal_unsubscribe_confirm', 'local_vendorbilling') . "');",
];
echo html_writer::link($unsuburl, get_string('portal_unsubscribe', 'local_vendorbilling'), $unsubattrs);
echo html_writer::end_tag('div');
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'vb-grid']);
echo html_writer::start_tag('div', ['class' => 'vb-card']);
echo html_writer::tag('h3', get_string('portal_seatsused', 'local_vendorbilling'));
echo html_writer::tag('p', (string) $seatsused, ['class' => 'vb-metric']);
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'vb-card']);
echo html_writer::tag('h3', get_string('portal_seatsremaining', 'local_vendorbilling'));
echo html_writer::tag('p', (string) $seatsremaining, ['class' => 'vb-metric']);
$progress = $seatlimit > 0 ? min(100, round(($seatsused / $seatlimit) * 100)) : 0;
echo html_writer::tag('div', html_writer::tag('span', '', ['style' => 'width:' . $progress . '%']), ['class' => 'vb-progress']);
echo html_writer::tag('p', $progress . '% used', ['class' => 'vb-muted']);
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'vb-card']);
echo html_writer::tag('h3', get_string('portal_seatlimit', 'local_vendorbilling'));
echo html_writer::tag('p', (string) $seatlimit, ['class' => 'vb-metric']);
echo html_writer::end_tag('div');
echo html_writer::end_tag('div');

if (!$statusactive) {
    $message = get_string('portal_usersuspension', 'local_vendorbilling');
    if ($dashboardurl !== '') {
        $message .= ' ' . html_writer::link($dashboardurl, get_string('portal_resubscribe', 'local_vendorbilling'));
    }
    echo html_writer::tag('div', $message, ['class' => 'vb-banner']);
}

$users = manager::get_vendor_users($vendor);
echo html_writer::tag('h3', get_string('portal_userlist', 'local_vendorbilling'), ['class' => 'vb-users']);
if (empty($users)) {
    echo $OUTPUT->notification(get_string('portal_nousers', 'local_vendorbilling'), 'info');
} else {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable vb-table';
    $table->head = [
        get_string('form_firstname', 'local_vendorbilling'),
        get_string('form_lastname', 'local_vendorbilling'),
        get_string('form_email', 'local_vendorbilling'),
        get_string('portal_status', 'local_vendorbilling'),
        '',
    ];
    foreach ($users as $user) {
        $editurl = new moodle_url('/local/vendorbilling/edit_user.php', [
            'userid' => $user->id,
        ]);
        $edit = html_writer::link($editurl, get_string('portal_edit', 'local_vendorbilling'));

        $suspendurl = new moodle_url('/local/vendorbilling/index.php', [
            'action' => 'suspend',
            'userid' => $user->id,
            'suspend' => $user->suspended ? 0 : 1,
            'sesskey' => sesskey(),
        ]);
        $suspendlabel = $user->suspended
            ? get_string('portal_unsuspend', 'local_vendorbilling')
            : get_string('portal_suspend', 'local_vendorbilling');
        $suspendlink = html_writer::link($suspendurl, $suspendlabel);

        $deleteurl = new moodle_url('/local/vendorbilling/index.php', [
            'action' => 'delete',
            'userid' => $user->id,
            'sesskey' => sesskey(),
        ]);
        $delete = html_writer::link($deleteurl, get_string('portal_delete', 'local_vendorbilling'), [
            'onclick' => "return confirm('" . get_string('portal_deleteconfirm', 'local_vendorbilling') . "');",
        ]);
        $table->data[] = [
            format_string($user->firstname),
            format_string($user->lastname),
            s($user->email),
            $user->suspended ? get_string('portal_suspended', 'local_vendorbilling') : get_string('portal_active', 'local_vendorbilling'),
            html_writer::tag('span', $edit . ' | ' . $suspendlink . ' | ' . $delete, ['class' => 'actions']),
        ];
    }
    echo html_writer::table($table);
}

echo html_writer::end_tag('div');

echo $OUTPUT->footer();
