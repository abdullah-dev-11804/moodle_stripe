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
 * Language strings.
 *
 * @package   local_vendorbilling
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Vendor billing (Stripe)';
$string['vendorbilling:manage'] = 'Manage vendor billing settings and vendors';
$string['vendorbilling:vendoradmin'] = 'Access vendor portal and manage vendor users';

$string['settings_stripeheading'] = 'Stripe settings';
$string['settings_webhooksecret'] = 'Webhook signing secret';
$string['settings_webhooksecret_desc'] = 'The Stripe endpoint secret used to verify Stripe webhook signatures.';
$string['settings_stripe_secretkey'] = 'Stripe secret key';
$string['settings_stripe_secretkey_desc'] = 'Secret API key (sk_live_ / sk_test_) used to create Stripe Customer Portal sessions.';
$string['settings_portal_returnurl'] = 'Portal return URL';
$string['settings_portal_returnurl_desc'] = 'Return URL after Stripe Customer Portal actions. Leave blank to use the vendor portal URL.';
$string['settings_dashboard_url'] = 'Resubscribe dashboard URL';
$string['settings_dashboard_url_desc'] = 'Public URL where vendors can purchase a new subscription (e.g., your Replit payment link page).';
$string['settings_pricemap'] = 'Stripe price map';
$string['settings_pricemap_desc'] = 'JSON object mapping Stripe price IDs to plan metadata. Example: {"price_123": {"plan_code":"STARTER_10","seat_limit":10,"billing":"annual"}}';
$string['settings_vendorrole'] = 'Vendor admin role';
$string['settings_vendorrole_desc'] = 'Role assigned to vendor admins at system context.';

$string['settings_emailheading'] = 'Email templates';
$string['settings_welcome_subject'] = 'Welcome email subject';
$string['settings_welcome_body'] = 'Welcome email body';
$string['settings_welcome_body_desc'] = 'You can use placeholders: {sitename}, {loginurl}, {vendorname}, {email}, {username}, {password}.';
$string['settings_user_welcome_subject'] = 'User welcome email subject';
$string['settings_user_welcome_body'] = 'User welcome email body';
$string['settings_user_welcome_body_desc'] = 'You can use placeholders: {sitename}, {loginurl}, {vendorname}, {email}, {username}, {password}.';
$string['settings_suspension_subject'] = 'Suspension email subject';
$string['settings_suspension_body'] = 'Suspension email body';
$string['settings_suspension_body_desc'] = 'You can use placeholders: {sitename}, {loginurl}, {vendorname}, {email}.';

$string['portal_heading'] = 'Vendor portal';
$string['portal_seatlimit'] = 'Seat limit';
$string['portal_seatsused'] = 'Seats used';
$string['portal_seatsremaining'] = 'Seats remaining';
$string['portal_createuser'] = 'Create user';
$string['portal_bulkupload'] = 'Bulk upload users';
$string['portal_usersuspension'] = 'Your subscription is inactive. Your users are suspended.';
$string['portal_resubscribe'] = 'Resubscribe here';
$string['portal_userlist'] = 'Users';
$string['portal_nousers'] = 'No users have been added yet.';
$string['portal_delete'] = 'Delete';
$string['portal_deleteconfirm'] = 'Delete this user?';
$string['portal_deleted'] = 'User deleted.';
$string['portal_edit'] = 'Edit';
$string['portal_edit_heading'] = 'Edit user';
$string['portal_updated'] = 'User updated.';
$string['portal_suspend'] = 'Suspend';
$string['portal_unsuspend'] = 'Unsuspend';
$string['portal_suspended'] = 'Suspended';
$string['portal_active'] = 'Active';
$string['portal_status'] = 'Status';
$string['portal_unsubscribe'] = 'Unsubscribe';
$string['portal_unsubscribe_confirm'] = 'Continue to Stripe to manage or cancel your subscription?';
$string['portal_unsubscribed'] = 'Subscription canceled. All users are suspended.';
$string['error_invalid_delete'] = 'This user cannot be deleted.';
$string['error_invalid_edit'] = 'This user cannot be edited.';
$string['error_invalid_suspend'] = 'This user cannot be suspended.';
$string['error_stripe_secret'] = 'Stripe secret key is not configured.';
$string['error_stripe_customer'] = 'Stripe customer ID is missing for this vendor.';
$string['error_stripe_portal'] = 'Unable to start Stripe Customer Portal session.';

$string['form_firstname'] = 'First name';
$string['form_lastname'] = 'Last name';
$string['form_email'] = 'Email address';
$string['form_username'] = 'Username';
$string['form_submit'] = 'Create user';
$string['form_csvfile'] = 'CSV file';
$string['form_csvfile_help'] = 'CSV columns: firstname, lastname, email. Username is derived from email.';
$string['form_upload'] = 'Upload';

$string['error_seatlimit'] = 'Seat limit reached. Please upgrade your plan to add more users.';
$string['error_vendor_not_found'] = 'Vendor record not found.';
$string['error_invalid_csv'] = 'CSV file is invalid or empty.';
$string['error_webhook_signature'] = 'Stripe webhook signature verification failed.';
$string['error_webhook_payload'] = 'Stripe webhook payload could not be parsed.';

$string['admin_simulate'] = 'Simulate vendor status';
$string['admin_simulate_desc'] = 'Activate or suspend a vendor for testing.';
$string['admin_simulate_vendor'] = 'Vendor ID';
$string['admin_simulate_status'] = 'Status';
$string['admin_simulate_submit'] = 'Apply';

$string['status_active'] = 'Active';
$string['status_past_due'] = 'Past due';
$string['status_unpaid'] = 'Unpaid';
$string['status_canceled'] = 'Canceled';
$string['status_trialing'] = 'Trialing';
