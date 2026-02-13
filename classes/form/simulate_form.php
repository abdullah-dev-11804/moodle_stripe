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
 * Simulation form.
 *
 * @package   local_vendorbilling
 */

namespace local_vendorbilling\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class simulate_form extends \moodleform {
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('text', 'vendorid', get_string('admin_simulate_vendor', 'local_vendorbilling'));
        $mform->setType('vendorid', PARAM_INT);
        $mform->addRule('vendorid', null, 'required', null, 'client');

        $options = [
            'active' => get_string('status_active', 'local_vendorbilling'),
            'past_due' => get_string('status_past_due', 'local_vendorbilling'),
            'unpaid' => get_string('status_unpaid', 'local_vendorbilling'),
            'canceled' => get_string('status_canceled', 'local_vendorbilling'),
            'trialing' => get_string('status_trialing', 'local_vendorbilling'),
        ];
        $mform->addElement('select', 'status', get_string('admin_simulate_status', 'local_vendorbilling'), $options);
        $mform->setDefault('status', 'active');

        $this->add_action_buttons(true, get_string('admin_simulate_submit', 'local_vendorbilling'));
    }
}
