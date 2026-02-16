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
 * Core vendor billing manager.
 *
 * @package   local_vendorbilling
 */

namespace local_vendorbilling;

defined('MOODLE_INTERNAL') || die();

class manager {
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAST_DUE = 'past_due';
    public const STATUS_UNPAID = 'unpaid';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_TRIALING = 'trialing';
    public const STATUS_INCOMPLETE = 'incomplete';

    public static function get_vendor_for_user(int $userid): ?\stdClass {
        global $DB;
        return $DB->get_record('local_vendorbilling_vendor', ['vendor_admin_userid' => $userid]) ?: null;
    }

    public static function get_vendor_by_stripe(?string $customerid, ?string $subscriptionid): ?\stdClass {
        global $DB;
        if (!empty($subscriptionid)) {
            $vendor = $DB->get_record('local_vendorbilling_vendor', ['stripe_subscription_id' => $subscriptionid]);
            if ($vendor) {
                return $vendor;
            }
        }
        if (!empty($customerid)) {
            $vendor = $DB->get_record('local_vendorbilling_vendor', ['stripe_customer_id' => $customerid]);
            if ($vendor) {
                return $vendor;
            }
        }
        return null;
    }

    public static function get_vendor_by_email(?string $email): ?\stdClass {
        global $DB;
        if (empty($email)) {
            return null;
        }
        $email = trim(\core_text::strtolower($email));
        return $DB->get_record('local_vendorbilling_vendor', ['vendor_admin_email' => $email]) ?: null;
    }

    public static function upsert_vendor(array $fields): \stdClass {
        global $DB;
        $now = time();

        $vendor = self::get_vendor_by_stripe(
            $fields['stripe_customer_id'] ?? null,
            $fields['stripe_subscription_id'] ?? null
        );
        if (!$vendor && !empty($fields['vendor_admin_email'])) {
            $vendor = self::get_vendor_by_email($fields['vendor_admin_email']);
        }

        if ($vendor) {
            foreach ($fields as $key => $value) {
                if ($key === 'vendor_admin_email' && !empty($value)) {
                    $value = trim(\core_text::strtolower($value));
                }
                $vendor->$key = $value;
            }
            $vendor->updated_at = $now;
            $DB->update_record('local_vendorbilling_vendor', $vendor);
            return $vendor;
        }

        $record = (object) array_merge([
            'org_name' => $fields['org_name'] ?? 'Vendor',
            'org_email_domain' => $fields['org_email_domain'] ?? null,
            'stripe_customer_id' => $fields['stripe_customer_id'] ?? null,
            'stripe_subscription_id' => $fields['stripe_subscription_id'] ?? null,
            'stripe_price_id' => $fields['stripe_price_id'] ?? null,
            'plan_code' => $fields['plan_code'] ?? null,
            'seat_limit' => $fields['seat_limit'] ?? 0,
            'status' => $fields['status'] ?? self::STATUS_INCOMPLETE,
            'vendor_admin_userid' => $fields['vendor_admin_userid'] ?? null,
            'vendor_admin_email' => $fields['vendor_admin_email'] ?? null,
            'cohortid' => $fields['cohortid'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $fields);

        if (!empty($record->vendor_admin_email)) {
            $record->vendor_admin_email = trim(\core_text::strtolower($record->vendor_admin_email));
        }

        $record->id = $DB->insert_record('local_vendorbilling_vendor', $record);
        return $record;
    }

    public static function ensure_cohort(\stdClass $vendor): \stdClass {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/cohort/lib.php');

        if (!empty($vendor->cohortid)) {
            $cohort = $DB->get_record('cohort', ['id' => $vendor->cohortid]);
            if ($cohort) {
                return $cohort;
            }
        }

        $cohort = new \stdClass();
        $cohort->name = 'Vendor - ' . ($vendor->org_name ?: 'Vendor') . ' (#' . $vendor->id . ')';
        $cohort->idnumber = 'vendor_' . $vendor->id;
        $cohort->description = 'Vendor cohort for ' . $vendor->org_name;
        $cohort->contextid = \context_system::instance()->id;
        $cohort->visible = 1;
        $cohort->component = 'local_vendorbilling';
        $cohort->timecreated = time();
        $cohort->timemodified = time();
        $cohort->id = cohort_add_cohort($cohort);

        $vendor->cohortid = $cohort->id;
        $vendor->updated_at = time();
        $DB->update_record('local_vendorbilling_vendor', $vendor);

        return $cohort;
    }

    public static function ensure_vendor_admin(\stdClass $vendor, string $email, ?string $fullname = null): \stdClass {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');
        require_once($CFG->libdir . '/moodlelib.php');

        $created = false;
        $user = $DB->get_record('user', ['email' => $email, 'deleted' => 0]);
        if (!$user) {
            $names = self::split_name($fullname ?: $email);
            $user = new \stdClass();
            $user->username = trim(\core_text::strtolower($email));
            $user->auth = 'manual';
            $user->confirmed = 1;
            $user->policyagreed = 0;
            $user->firstname = $names['firstname'];
            $user->lastname = $names['lastname'];
            $user->email = $email;
            $user->lang = current_language();
            $user->mnethostid = $CFG->mnet_localhost_id;
            $user->suspended = 0;
            $user->timecreated = time();
            $user->timemodified = time();
            $plainpassword = generate_password();
            $user->password = $plainpassword;
            $user->forcepasswordchange = 1;

            $userid = user_create_user($user, false, false);
            $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
            update_internal_user_password($user, $plainpassword);
            $created = true;
            self::log('info', 'Created vendor admin user', $vendor->id ?? null, [
                'userid' => $user->id,
                'email' => $user->email,
            ]);
        } else {
            self::log('info', 'Found existing vendor admin user', $vendor->id ?? null, [
                'userid' => $user->id,
                'email' => $user->email,
            ]);
        }

        if (empty($vendor->vendor_admin_userid)) {
            $vendor->vendor_admin_userid = $user->id;
            $vendor->updated_at = time();
            $DB->update_record('local_vendorbilling_vendor', $vendor);
        }
        if (empty($vendor->vendor_admin_email) && !empty($user->email)) {
            $vendor->vendor_admin_email = trim(\core_text::strtolower($user->email));
            $vendor->updated_at = time();
            $DB->update_record('local_vendorbilling_vendor', $vendor);
        }

        self::assign_vendor_role($user->id);
        $cohort = self::ensure_cohort($vendor);
        self::add_user_to_cohort($cohort->id, $user->id);

        if ($created && self::is_active_status($vendor->status ?? '')) {
            self::log('info', 'Sending welcome email (new vendor admin)', $vendor->id ?? null, [
                'email' => $user->email,
            ]);
            self::send_vendor_admin_welcome_email($user, $vendor, $plainpassword ?? '');
        }

        return $user;
    }

    public static function assign_vendor_role(int $userid): void {
        global $DB;
        $roleid = (int) get_config('local_vendorbilling', 'vendorroleid');
        if ($roleid <= 0) {
            $role = get_role_by_shortname('vendoradmin');
            if ($role) {
                $roleid = (int) $role->id;
            }
        }
        if ($roleid > 0) {
            $context = \context_system::instance();
            if (!$DB->record_exists('role_assignments', ['roleid' => $roleid, 'userid' => $userid, 'contextid' => $context->id])) {
                role_assign($roleid, $userid, $context->id);
            }
        }
    }

    public static function add_user_to_cohort(int $cohortid, int $userid): void {
        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');
        if (!cohort_is_member($cohortid, $userid)) {
            cohort_add_member($cohortid, $userid);
        }
    }

    public static function get_seat_usage(\stdClass $vendor): int {
        global $DB;
        if (empty($vendor->cohortid)) {
            return 0;
        }
        $params = [
            'cohortid' => $vendor->cohortid,
            'adminid' => (int) $vendor->vendor_admin_userid,
        ];
        $sql = "SELECT COUNT(u.id)
                  FROM {user} u
                  JOIN {cohort_members} cm ON cm.userid = u.id
                 WHERE cm.cohortid = :cohortid
                   AND u.deleted = 0
                   AND u.suspended = 0
                   AND u.id <> :adminid";
        return (int) $DB->count_records_sql($sql, $params);
    }

    public static function get_seat_remaining(\stdClass $vendor): int {
        $used = self::get_seat_usage($vendor);
        $limit = (int) ($vendor->seat_limit ?? 0);
        return max(0, $limit - $used);
    }

    public static function enforce_seat_limit(\stdClass $vendor): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');
        require_once($CFG->libdir . '/moodlelib.php');

        if (!self::is_active_status($vendor->status ?? '')) {
            return;
        }
        if (empty($vendor->cohortid)) {
            return;
        }

        $limit = (int) ($vendor->seat_limit ?? 0);
        if ($limit <= 0) {
            return;
        }

        $params = [
            'cohortid' => $vendor->cohortid,
            'adminid' => (int) $vendor->vendor_admin_userid,
        ];

        $active = $DB->get_records_sql(
            "SELECT u.id, u.timecreated
               FROM {user} u
               JOIN {cohort_members} cm ON cm.userid = u.id
              WHERE cm.cohortid = :cohortid
                AND u.deleted = 0
                AND u.suspended = 0
                AND u.id <> :adminid
           ORDER BY u.timecreated DESC",
            $params
        );

        $activecount = count($active);
        if ($activecount > $limit) {
            foreach ($active as $user) {
                if ($activecount <= $limit) {
                    break;
                }
                $record = $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
                $record->suspended = 1;
                $record->timemodified = time();
                user_update_user($record, false, false);
                $activecount--;
            }
        } else if ($activecount < $limit) {
            $remaining = $limit - $activecount;
            $suspended = $DB->get_records_sql(
                "SELECT u.id, u.timecreated
                   FROM {user} u
                   JOIN {cohort_members} cm ON cm.userid = u.id
                  WHERE cm.cohortid = :cohortid
                    AND u.deleted = 0
                    AND u.suspended = 1
                    AND u.id <> :adminid
               ORDER BY u.timecreated ASC",
                $params
            );
            foreach ($suspended as $user) {
                if ($remaining <= 0) {
                    break;
                }
                $record = $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
                $record->suspended = 0;
                $record->timemodified = time();
                user_update_user($record, false, false);
                $remaining--;
            }
        }
    }

    public static function get_vendor_users(\stdClass $vendor): array {
        global $DB;
        if (empty($vendor->cohortid)) {
            return [];
        }
        $params = [
            'cohortid' => $vendor->cohortid,
            'adminid' => (int) $vendor->vendor_admin_userid,
        ];
        $sql = "SELECT u.id, u.firstname, u.lastname, u.email, u.suspended
                  FROM {user} u
                  JOIN {cohort_members} cm ON cm.userid = u.id
                 WHERE cm.cohortid = :cohortid
                   AND u.deleted = 0
                   AND u.id <> :adminid
              ORDER BY u.lastname, u.firstname, u.id";
        return $DB->get_records_sql($sql, $params);
    }

    public static function get_vendor_user(\stdClass $vendor, int $userid): ?\stdClass {
        global $DB;
        if (empty($vendor->cohortid)) {
            return null;
        }
        if ((int) $userid === (int) $vendor->vendor_admin_userid) {
            return null;
        }
        $sql = "SELECT u.*
                  FROM {user} u
                  JOIN {cohort_members} cm ON cm.userid = u.id
                 WHERE cm.cohortid = :cohortid
                   AND u.id = :userid
                   AND u.deleted = 0";
        $params = [
            'cohortid' => $vendor->cohortid,
            'userid' => $userid,
        ];
        return $DB->get_record_sql($sql, $params) ?: null;
    }

    public static function update_vendor_user(\stdClass $vendor, int $userid, array $data): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');

        if (!self::is_active_status($vendor->status ?? '')) {
            throw new \moodle_exception('portal_usersuspension', 'local_vendorbilling');
        }

        $user = self::get_vendor_user($vendor, $userid);
        if (!$user) {
            throw new \moodle_exception('error_invalid_edit', 'local_vendorbilling');
        }

        $firstname = trim($data['firstname'] ?? '');
        $lastname = trim($data['lastname'] ?? '');
        $email = trim($data['email'] ?? '');
        if ($firstname === '' || $lastname === '' || $email === '') {
            throw new \moodle_exception('error_invalid_edit', 'local_vendorbilling');
        }

        if ($email !== $user->email && $DB->record_exists('user', ['email' => $email, 'deleted' => 0])) {
            throw new \moodle_exception('emailexists');
        }

        $username = trim(\core_text::strtolower($email));
        if ($username !== $user->username && $DB->record_exists('user', ['username' => $username])) {
            throw new \moodle_exception('usernameexists');
        }

        $user->firstname = $firstname;
        $user->lastname = $lastname;
        $user->email = $email;
        $user->username = $username;
        $user->timemodified = time();

        user_update_user($user, false, false);

        self::log('info', 'Updated vendor user', $vendor->id ?? null, [
            'userid' => $user->id,
            'email' => $user->email,
        ]);
    }

    public static function set_vendor_user_suspension(\stdClass $vendor, int $userid, bool $suspend): void {
        global $CFG;
        require_once($CFG->dirroot . '/user/lib.php');

        if (!self::is_active_status($vendor->status ?? '')) {
            throw new \moodle_exception('portal_usersuspension', 'local_vendorbilling');
        }

        $user = self::get_vendor_user($vendor, $userid);
        if (!$user) {
            throw new \moodle_exception('error_invalid_suspend', 'local_vendorbilling');
        }

        $user->suspended = $suspend ? 1 : 0;
        $user->timemodified = time();
        user_update_user($user, false, false);

        self::log('info', $suspend ? 'Suspended vendor user' : 'Unsuspended vendor user', $vendor->id ?? null, [
            'userid' => $user->id,
            'email' => $user->email ?? '',
            'suspended' => (bool) $suspend,
        ]);
    }

    public static function delete_vendor_user(\stdClass $vendor, int $userid): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');

        if (!self::is_active_status($vendor->status ?? '')) {
            throw new \moodle_exception('portal_usersuspension', 'local_vendorbilling');
        }

        if ((int) $userid === (int) $vendor->vendor_admin_userid) {
            throw new \moodle_exception('error_invalid_delete', 'local_vendorbilling');
        }

        if (empty($vendor->cohortid)) {
            throw new \moodle_exception('error_vendor_not_found', 'local_vendorbilling');
        }

        $ismember = $DB->record_exists('cohort_members', [
            'cohortid' => $vendor->cohortid,
            'userid' => $userid,
        ]);
        if (!$ismember) {
            throw new \moodle_exception('error_invalid_delete', 'local_vendorbilling');
        }

        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);
        user_delete_user($user);

        self::log('info', 'Deleted vendor user', $vendor->id ?? null, [
            'userid' => $userid,
            'email' => $user->email ?? '',
        ]);
    }

    public static function create_portal_session(\stdClass $vendor): string {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $secret = (string) get_config('local_vendorbilling', 'secretkey');
        if ($secret === '') {
            throw new \moodle_exception('error_stripe_secret', 'local_vendorbilling');
        }

        if (empty($vendor->stripe_customer_id)) {
            throw new \moodle_exception('error_stripe_customer', 'local_vendorbilling');
        }

        $returnurl = (string) get_config('local_vendorbilling', 'portal_returnurl');
        if ($returnurl === '') {
            $returnurl = (new \moodle_url('/local/vendorbilling/index.php'))->out(false);
        }

        $endpoint = 'https://api.stripe.com/v1/billing_portal/sessions';
        $body = http_build_query([
            'customer' => $vendor->stripe_customer_id,
            'return_url' => $returnurl,
        ], '', '&');

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $secret,
                'Content-Type: application/x-www-form-urlencoded',
                'Content-Length: ' . strlen($body),
            ],
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($ch);
        $curlerror = curl_error($ch);
        $httpcode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response ?? '', true);
        if ($response === false || $httpcode >= 400 || !is_array($data) || empty($data['url'])) {
            self::log('error', 'Stripe portal session failed', $vendor->id ?? null, [
                'response' => $response,
                'curlerror' => $curlerror,
                'httpcode' => $httpcode,
            ]);
            throw new \moodle_exception('error_stripe_portal', 'local_vendorbilling');
        }

        self::log('info', 'Stripe portal session created', $vendor->id ?? null, [
            'customer' => $vendor->stripe_customer_id,
        ]);

        return $data['url'];
    }

    public static function create_vendor_user(\stdClass $vendor, array $data): \stdClass {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');

        if (self::get_seat_remaining($vendor) <= 0) {
            throw new \moodle_exception('error_seatlimit', 'local_vendorbilling');
        }

        $email = trim($data['email'] ?? '');
        if (empty($email)) {
            throw new \moodle_exception('missingemail');
        }

        if ($DB->record_exists('user', ['email' => $email, 'deleted' => 0])) {
            throw new \moodle_exception('emailexists');
        }

        $firstname = trim($data['firstname'] ?? '');
        $lastname = trim($data['lastname'] ?? '');
        if ($firstname === '') {
            $firstname = $email;
        }
        if ($lastname === '') {
            $lastname = 'User';
        }

        $username = trim(\core_text::strtolower($email));
        if ($DB->record_exists('user', ['username' => $username])) {
            throw new \moodle_exception('usernameexists');
        }

        $user = new \stdClass();
        $user->username = $username;
        $user->auth = 'manual';
        $user->confirmed = 1;
        $user->policyagreed = 0;
        $user->firstname = $firstname;
        $user->lastname = $lastname;
        $user->email = $email;
        $user->lang = current_language();
        $user->mnethostid = $CFG->mnet_localhost_id;
        $user->suspended = 0;
        $user->timecreated = time();
        $user->timemodified = time();
        $plainpassword = generate_password();
        $user->password = $plainpassword;
        $user->forcepasswordchange = 1;

        $userid = user_create_user($user, false, false);
        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        update_internal_user_password($user, $plainpassword);

        $cohort = self::ensure_cohort($vendor);
        self::add_user_to_cohort($cohort->id, $user->id);

        self::send_user_welcome_email($user, $vendor, $plainpassword);

        return $user;
    }

    public static function bulk_create_users(\stdClass $vendor, array $rows, array &$errors): int {
        $errors = [];
        $created = 0;

        $remaining = self::get_seat_remaining($vendor);
        if (count($rows) > $remaining) {
            throw new \moodle_exception('error_seatlimit', 'local_vendorbilling');
        }

        foreach ($rows as $index => $row) {
            try {
                self::create_vendor_user($vendor, $row);
                $created++;
            } catch (\moodle_exception $ex) {
                $errors[] = 'Row ' . ($index + 1) . ': ' . $ex->getMessage();
            }
        }

        return $created;
    }

    public static function set_vendor_status(\stdClass $vendor, string $status): void {
        global $DB;
        $wasactive = self::is_active_status($vendor->status ?? '');
        $vendor->status = $status;
        $vendor->updated_at = time();
        $DB->update_record('local_vendorbilling_vendor', $vendor);

        $isactive = self::is_active_status($status);
        if ($isactive) {
            self::unsuspend_vendor_users($vendor);
            self::enforce_seat_limit($vendor);
        } else {
            self::suspend_vendor_users($vendor);
            if ($wasactive && !empty($vendor->vendor_admin_userid)) {
                $admin = $DB->get_record('user', ['id' => $vendor->vendor_admin_userid], '*', MUST_EXIST);
                self::send_suspension_email($admin, $vendor);
            }
        }
    }

    public static function is_active_status(string $status): bool {
        return in_array($status, [self::STATUS_ACTIVE, self::STATUS_TRIALING], true);
    }

    public static function suspend_vendor_users(\stdClass $vendor): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');
        if (empty($vendor->cohortid)) {
            return;
        }

        $userids = $DB->get_fieldset_select('cohort_members', 'userid', 'cohortid = :cohortid', ['cohortid' => $vendor->cohortid]);
        if (empty($userids)) {
            return;
        }

        foreach ($userids as $userid) {
            if (!empty($vendor->vendor_admin_userid) && (int) $userid === (int) $vendor->vendor_admin_userid) {
                continue;
            }
            $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
            if ((int) $user->suspended === 0) {
                $user->suspended = 1;
                $user->timemodified = time();
                user_update_user($user, false, false);
            }
        }
    }

    public static function unsuspend_vendor_users(\stdClass $vendor): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');
        if (empty($vendor->cohortid)) {
            return;
        }

        $userids = $DB->get_fieldset_select('cohort_members', 'userid', 'cohortid = :cohortid', ['cohortid' => $vendor->cohortid]);
        if (!empty($userids)) {
            foreach ($userids as $userid) {
                if (!empty($vendor->vendor_admin_userid) && (int) $userid === (int) $vendor->vendor_admin_userid) {
                    continue;
                }
                $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
                if ((int) $user->suspended === 1) {
                    $user->suspended = 0;
                    $user->timemodified = time();
                    user_update_user($user, false, false);
                }
            }
        }

        if (!empty($vendor->vendor_admin_userid)) {
            // Vendor admin is intentionally not suspended.
        }
    }

    public static function send_vendor_admin_welcome_email(\stdClass $user, \stdClass $vendor, string $password): void {
        $subject = get_config('local_vendorbilling', 'welcome_subject');
        $body = get_config('local_vendorbilling', 'welcome_body');
        self::send_templated_email($user, $vendor, $subject, $body, [
            '{password}' => $password,
            '{username}' => $user->username ?? '',
        ]);
    }

    public static function send_user_welcome_email(\stdClass $user, \stdClass $vendor, string $password): void {
        $subject = get_config('local_vendorbilling', 'user_welcome_subject');
        $body = get_config('local_vendorbilling', 'user_welcome_body');
        self::send_templated_email($user, $vendor, $subject, $body, [
            '{password}' => $password,
            '{username}' => $user->username ?? '',
        ]);
    }

    public static function send_suspension_email(\stdClass $user, \stdClass $vendor): void {
        $subject = get_config('local_vendorbilling', 'suspension_subject');
        $body = get_config('local_vendorbilling', 'suspension_body');
        self::send_templated_email($user, $vendor, $subject, $body);
    }

    public static function send_templated_email(\stdClass $user, \stdClass $vendor, string $subject, string $body, array $extra = []): void {
        global $CFG;
        if ($subject === '' || $body === '') {
            self::log('warning', 'Email template empty, skipping send', $vendor->id ?? null, [
                'email' => $user->email ?? '',
            ]);
            return;
        }
        $placeholders = [
            '{sitename}' => format_string($CFG->sitename),
            '{loginurl}' => (new \moodle_url('/login/index.php'))->out(false),
            '{vendorname}' => $vendor->org_name ?? 'Vendor',
            '{email}' => $user->email ?? '',
        ];
        if (!empty($extra)) {
            $placeholders = array_merge($placeholders, $extra);
        }
        $subject = strtr($subject, $placeholders);
        $body = strtr($body, $placeholders);
        $result = email_to_user($user, \core_user::get_support_user(), $subject, $body);
        self::log($result ? 'info' : 'error', 'Email send attempt', $vendor->id ?? null, [
            'email' => $user->email ?? '',
            'subject' => $subject,
            'result' => (bool) $result,
        ]);
    }

    public static function get_pricemap(): array {
        $raw = get_config('local_vendorbilling', 'pricemap');
        if (empty($raw)) {
            return [];
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return [];
        }
        return $data;
    }

    public static function resolve_plan_from_price(?array $price, ?array $metadata, ?string $priceid): array {
        $plan = [
            'plan_code' => null,
            'seat_limit' => 0,
            'billing' => null,
        ];

        $meta = $metadata ?? [];
        if (isset($price['metadata']) && is_array($price['metadata'])) {
            $meta = array_merge($meta, $price['metadata']);
        }

        if (!empty($meta['plan_code'])) {
            $plan['plan_code'] = $meta['plan_code'];
        }
        if (!empty($meta['seat_limit'])) {
            $plan['seat_limit'] = (int) $meta['seat_limit'];
        }
        if (!empty($meta['billing'])) {
            $plan['billing'] = $meta['billing'];
        }

        if ((!$plan['plan_code'] || !$plan['seat_limit']) && $priceid) {
            $map = self::get_pricemap();
            if (isset($map[$priceid])) {
                $mapped = $map[$priceid];
                $plan['plan_code'] = $plan['plan_code'] ?: ($mapped['plan_code'] ?? null);
                $plan['seat_limit'] = $plan['seat_limit'] ?: (int) ($mapped['seat_limit'] ?? 0);
                $plan['billing'] = $plan['billing'] ?: ($mapped['billing'] ?? null);
            }
        }

        return $plan;
    }

    public static function log(string $level, string $message, ?int $vendorid = null, ?array $data = null): void {
        global $DB;
        $record = (object) [
            'vendorid' => $vendorid,
            'level' => $level,
            'message' => $message,
            'data' => $data ? json_encode($data) : null,
            'created_at' => time(),
        ];
        $DB->insert_record('local_vendorbilling_log', $record);
    }

    public static function clean_username(string $email): string {
        $username = trim(\core_text::strtolower($email));
        $username = preg_replace('/[^a-z0-9_\-\.\@]/', '', $username);
        if ($username === '') {
            $username = 'user' . random_int(1000, 9999);
        }
        return $username;
    }

    public static function split_name(string $name): array {
        $name = trim($name);
        if ($name === '') {
            return ['firstname' => 'Vendor', 'lastname' => 'Admin'];
        }
        $parts = preg_split('/\s+/', $name);
        $firstname = array_shift($parts);
        $lastname = count($parts) ? implode(' ', $parts) : 'Admin';
        return ['firstname' => $firstname, 'lastname' => $lastname];
    }
}
