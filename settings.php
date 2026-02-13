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
 * Settings.
 *
 * @package   local_vendorbilling
 */

defined('MOODLE_INTERNAL') || die();

global $CFG, $ADMIN;

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_vendorbilling', get_string('pluginname', 'local_vendorbilling'));

    $settings->add(new admin_setting_heading('local_vendorbilling/stripe',
        get_string('settings_stripeheading', 'local_vendorbilling'), ''));

    $settings->add(new admin_setting_configtext('local_vendorbilling/webhooksecret',
        get_string('settings_webhooksecret', 'local_vendorbilling'),
        get_string('settings_webhooksecret_desc', 'local_vendorbilling'),
        '', PARAM_TEXT));

    $settings->add(new admin_setting_configpasswordunmask('local_vendorbilling/secretkey',
        get_string('settings_stripe_secretkey', 'local_vendorbilling'),
        get_string('settings_stripe_secretkey_desc', 'local_vendorbilling'),
        ''));

    $settings->add(new admin_setting_configtext('local_vendorbilling/portal_returnurl',
        get_string('settings_portal_returnurl', 'local_vendorbilling'),
        get_string('settings_portal_returnurl_desc', 'local_vendorbilling'),
        '', PARAM_URL));

    $settings->add(new admin_setting_configtext('local_vendorbilling/dashboard_url',
        get_string('settings_dashboard_url', 'local_vendorbilling'),
        get_string('settings_dashboard_url_desc', 'local_vendorbilling'),
        '', PARAM_URL));

    $settings->add(new admin_setting_configtextarea('local_vendorbilling/pricemap',
        get_string('settings_pricemap', 'local_vendorbilling'),
        get_string('settings_pricemap_desc', 'local_vendorbilling'),
        "{\"price_123\": {\"plan_code\": \"STARTER_10\", \"seat_limit\": 10, \"billing\": \"annual\"}}",
        PARAM_RAW_TRIMMED, 80, 8));

    require_once($CFG->libdir . '/accesslib.php');
    $systemcontext = context_system::instance();
    $roles = role_get_names($systemcontext);
    $roleoptions = [];
    foreach ($roles as $roleid => $rolename) {
        if (is_object($rolename)) {
            $roleoptions[$roleid] = format_string($rolename->localname ?? $rolename->name ?? '');
        } else {
            $roleoptions[$roleid] = format_string((string) $rolename);
        }
    }
    $settings->add(new admin_setting_configselect('local_vendorbilling/vendorroleid',
        get_string('settings_vendorrole', 'local_vendorbilling'),
        get_string('settings_vendorrole_desc', 'local_vendorbilling'),
        0,
        $roleoptions));

    $settings->add(new admin_setting_heading('local_vendorbilling/email',
        get_string('settings_emailheading', 'local_vendorbilling'), ''));

    $settings->add(new admin_setting_configtext('local_vendorbilling/welcome_subject',
        get_string('settings_welcome_subject', 'local_vendorbilling'), '',
        'Welcome to {sitename}', PARAM_TEXT));

    $settings->add(new admin_setting_configtextarea('local_vendorbilling/welcome_body',
        get_string('settings_welcome_body', 'local_vendorbilling'),
        get_string('settings_welcome_body_desc', 'local_vendorbilling'),
        "Hello {vendorname},\n\nYour vendor admin account is ready.\nUsername: {username}\nPassword: {password}\nLogin: {loginurl}\n\nThanks,\n{sitename}",
        PARAM_RAW_TRIMMED, 80, 10));

    $settings->add(new admin_setting_configtext('local_vendorbilling/user_welcome_subject',
        get_string('settings_user_welcome_subject', 'local_vendorbilling'), '',
        'Welcome to {sitename}', PARAM_TEXT));

    $settings->add(new admin_setting_configtextarea('local_vendorbilling/user_welcome_body',
        get_string('settings_user_welcome_body', 'local_vendorbilling'),
        get_string('settings_user_welcome_body_desc', 'local_vendorbilling'),
        "Hello,\n\nYour account has been created.\nUsername: {username}\nPassword: {password}\nLogin: {loginurl}\n\nThanks,\n{sitename}",
        PARAM_RAW_TRIMMED, 80, 10));

    $settings->add(new admin_setting_configtext('local_vendorbilling/suspension_subject',
        get_string('settings_suspension_subject', 'local_vendorbilling'), '',
        'Your access is suspended', PARAM_TEXT));

    $settings->add(new admin_setting_configtextarea('local_vendorbilling/suspension_body',
        get_string('settings_suspension_body', 'local_vendorbilling'),
        get_string('settings_suspension_body_desc', 'local_vendorbilling'),
        "Hello {vendorname},\n\nYour organisation access has been suspended due to payment status. Please contact support.\n\nThanks,\n{sitename}",
        PARAM_RAW_TRIMMED, 80, 10));

    $ADMIN->add('localplugins', $settings);

    $ADMIN->add('localplugins', new admin_externalpage(
        'local_vendorbilling_simulate',
        get_string('admin_simulate', 'local_vendorbilling'),
        new moodle_url('/local/vendorbilling/admin/simulate.php'),
        'local/vendorbilling:manage'
    ));
}
