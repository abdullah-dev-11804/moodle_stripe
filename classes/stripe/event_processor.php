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
 * Stripe event processor.
 *
 * @package   local_vendorbilling
 */

namespace local_vendorbilling\stripe;

defined('MOODLE_INTERNAL') || die();

use local_vendorbilling\manager;

class event_processor {
    public static function process(array $event): void {
        $type = $event['type'] ?? '';
        $object = $event['data']['object'] ?? [];

        switch ($type) {
            case 'checkout.session.completed':
                self::handle_checkout_session_completed($object);
                break;
            case 'customer.subscription.created':
            case 'customer.subscription.updated':
                self::handle_subscription_updated($object);
                break;
            case 'customer.subscription.deleted':
                self::handle_subscription_deleted($object);
                break;
            case 'invoice.paid':
            case 'invoice.payment_succeeded':
                self::handle_invoice_paid($object);
                break;
            case 'invoice.payment_failed':
                self::handle_invoice_failed($object);
                break;
            default:
                manager::log('info', 'Unhandled event type: ' . $type, null, ['event_type' => $type]);
        }
    }

    private static function handle_checkout_session_completed(array $session): void {
        $customerid = $session['customer'] ?? null;
        $subscriptionid = $session['subscription'] ?? null;
        $email = $session['customer_details']['email'] ?? ($session['customer_email'] ?? null);
        $orgname = $session['customer_details']['name'] ?? ($session['metadata']['org_name'] ?? null);

        $status = ($session['payment_status'] ?? '') === 'paid'
            ? manager::STATUS_ACTIVE
            : manager::STATUS_INCOMPLETE;

        $fields = [
            'org_name' => $orgname ?: 'Vendor',
            'org_email_domain' => self::get_email_domain($email),
            'stripe_customer_id' => $customerid,
            'stripe_subscription_id' => $subscriptionid,
            'status' => $status,
        ];

        $vendor = manager::upsert_vendor($fields);
        manager::ensure_cohort($vendor);

        if (!empty($email)) {
            manager::ensure_vendor_admin($vendor, $email, $orgname);
        }

        if (manager::is_active_status($status)) {
            manager::set_vendor_status($vendor, $status);
        }

        manager::log('info', 'Processed checkout.session.completed', $vendor->id, [
            'customer' => $customerid,
            'subscription' => $subscriptionid,
        ]);
    }

    private static function handle_subscription_updated(array $subscription): void {
        $customerid = $subscription['customer'] ?? null;
        $subscriptionid = $subscription['id'] ?? null;
        $status = $subscription['status'] ?? manager::STATUS_INCOMPLETE;
        $price = $subscription['items']['data'][0]['price'] ?? null;
        $priceid = $price['id'] ?? null;
        $metadata = $price['metadata'] ?? ($subscription['metadata'] ?? null);

        $plan = manager::resolve_plan_from_price($price, $metadata, $priceid);

        $fields = [
            'stripe_customer_id' => $customerid,
            'stripe_subscription_id' => $subscriptionid,
            'stripe_price_id' => $priceid,
            'plan_code' => $plan['plan_code'],
            'seat_limit' => $plan['seat_limit'],
            'status' => $status,
        ];

        $vendor = manager::upsert_vendor($fields);
        manager::ensure_cohort($vendor);
        manager::set_vendor_status($vendor, $status);

        manager::log('info', 'Processed subscription update', $vendor->id, [
            'subscription' => $subscriptionid,
            'status' => $status,
            'price' => $priceid,
        ]);
    }

    private static function handle_subscription_deleted(array $subscription): void {
        $customerid = $subscription['customer'] ?? null;
        $subscriptionid = $subscription['id'] ?? null;

        $vendor = manager::get_vendor_by_stripe($customerid, $subscriptionid);
        if (!$vendor) {
            manager::log('warning', 'Subscription deleted but vendor not found', null, [
                'subscription' => $subscriptionid,
            ]);
            return;
        }

        manager::set_vendor_status($vendor, manager::STATUS_CANCELED);
        manager::log('info', 'Processed subscription deleted', $vendor->id, [
            'subscription' => $subscriptionid,
        ]);
    }

    private static function handle_invoice_paid(array $invoice): void {
        $customerid = $invoice['customer'] ?? null;
        $subscriptionid = $invoice['subscription'] ?? null;

        $vendor = manager::get_vendor_by_stripe($customerid, $subscriptionid);
        if (!$vendor) {
            manager::log('warning', 'Invoice paid but vendor not found', null, [
                'subscription' => $subscriptionid,
            ]);
            return;
        }

        manager::set_vendor_status($vendor, manager::STATUS_ACTIVE);
        manager::log('info', 'Processed invoice paid', $vendor->id, [
            'subscription' => $subscriptionid,
        ]);
    }

    private static function handle_invoice_failed(array $invoice): void {
        $customerid = $invoice['customer'] ?? null;
        $subscriptionid = $invoice['subscription'] ?? null;

        $vendor = manager::get_vendor_by_stripe($customerid, $subscriptionid);
        if (!$vendor) {
            manager::log('warning', 'Invoice failed but vendor not found', null, [
                'subscription' => $subscriptionid,
            ]);
            return;
        }

        $status = $invoice['status'] ?? manager::STATUS_PAST_DUE;
        if (!in_array($status, [manager::STATUS_PAST_DUE, manager::STATUS_UNPAID], true)) {
            $status = manager::STATUS_PAST_DUE;
        }

        manager::set_vendor_status($vendor, $status);
        manager::log('info', 'Processed invoice failed', $vendor->id, [
            'subscription' => $subscriptionid,
            'status' => $status,
        ]);
    }

    private static function get_email_domain(?string $email): ?string {
        if (empty($email) || strpos($email, '@') === false) {
            return null;
        }
        $parts = explode('@', $email);
        return strtolower(end($parts));
    }
}
