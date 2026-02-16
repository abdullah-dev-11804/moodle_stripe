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
 * Upgrade steps for local_vendorbilling.
 *
 * @package   local_vendorbilling
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/accesslib.php');

function xmldb_local_vendorbilling_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();
    $systemcontext = context_system::instance();
    $shortname = 'vendoradmin';

    if ($oldversion < 2026021001) {
        if (!$DB->get_record('role', ['shortname' => $shortname])) {
            $roleid = create_role('Vendor admin', $shortname, 'Vendor organisation administrator');
            update_capabilities('local/vendorbilling');
            if (get_capability_info('local/vendorbilling:vendoradmin')) {
                assign_capability('local/vendorbilling:vendoradmin', CAP_ALLOW, $roleid, $systemcontext->id, true);
            }
        }
        upgrade_plugin_savepoint(true, 2026021001, 'local', 'vendorbilling');
    }

    if ($oldversion < 2026021003) {
        if (!$DB->get_record('role', ['shortname' => $shortname])) {
            $roleid = create_role('Vendor admin', $shortname, 'Vendor organisation administrator');
            update_capabilities('local/vendorbilling');
            if (get_capability_info('local/vendorbilling:vendoradmin')) {
                assign_capability('local/vendorbilling:vendoradmin', CAP_ALLOW, $roleid, $systemcontext->id, true);
            }
        }
        upgrade_plugin_savepoint(true, 2026021003, 'local', 'vendorbilling');
    }

    if ($oldversion < 2026021104) {
        $table = new xmldb_table('local_vendorbilling_vendor');
        $field = new xmldb_field('vendor_admin_email', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'vendor_admin_userid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $index = new xmldb_index('vendor_admin_email_idx', XMLDB_INDEX_NOTUNIQUE, ['vendor_admin_email']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $vendors = $DB->get_records_select('local_vendorbilling_vendor',
            "vendor_admin_userid IS NOT NULL AND (vendor_admin_email IS NULL OR vendor_admin_email = '')");
        foreach ($vendors as $vendor) {
            $email = $DB->get_field('user', 'email', ['id' => $vendor->vendor_admin_userid]);
            if (!empty($email)) {
                $vendor->vendor_admin_email = trim(\core_text::strtolower($email));
                $vendor->updated_at = time();
                $DB->update_record('local_vendorbilling_vendor', $vendor);
            }
        }

        upgrade_plugin_savepoint(true, 2026021104, 'local', 'vendorbilling');
    }

    return true;
}
