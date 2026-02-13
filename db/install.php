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
 * Install hook.
 *
 * @package   local_vendorbilling
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/accesslib.php');

function xmldb_local_vendorbilling_install() {
    global $DB;
    $systemcontext = context_system::instance();
    $shortname = 'vendoradmin';

    if (!$DB->get_record('role', ['shortname' => $shortname])) {
        $roleid = create_role('Vendor admin', $shortname, 'Vendor organisation administrator');
        update_capabilities('local/vendorbilling');
        if (get_capability_info('local/vendorbilling:vendoradmin')) {
            assign_capability('local/vendorbilling:vendoradmin', CAP_ALLOW, $roleid, $systemcontext->id, true);
        }
    }
}
