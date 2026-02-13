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
 * Stripe signature verification.
 *
 * @package   local_vendorbilling
 */

namespace local_vendorbilling\stripe;

defined('MOODLE_INTERNAL') || die();

class signature {
    public static function verify(string $payload, string $sigheader, string $secret, int $tolerance = 300): bool {
        if ($payload === '' || $sigheader === '' || $secret === '') {
            return false;
        }

        $parts = explode(',', $sigheader);
        $timestamp = null;
        $signatures = [];
        foreach ($parts as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) !== 2) {
                continue;
            }
            if ($pair[0] === 't') {
                $timestamp = (int) $pair[1];
            } else if ($pair[0] === 'v1') {
                $signatures[] = $pair[1];
            }
        }

        if (!$timestamp || empty($signatures)) {
            return false;
        }

        if ($tolerance > 0 && abs(time() - $timestamp) > $tolerance) {
            return false;
        }

        $signedpayload = $timestamp . '.' . $payload;
        $expected = hash_hmac('sha256', $signedpayload, $secret);

        foreach ($signatures as $sig) {
            if (hash_equals($expected, $sig)) {
                return true;
            }
        }

        return false;
    }
}
