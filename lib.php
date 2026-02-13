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
 * Local plugin library.
 *
 * @package   local_vendorbilling
 */

defined('MOODLE_INTERNAL') || die();

function local_vendorbilling_extend_navigation(global_navigation $navigation) {
    if (!isloggedin() || isguestuser()) {
        return;
    }
    global $USER;
    error_log('vendorbilling: extend_navigation for user ' . $USER->id);
    $context = context_system::instance();
    if (has_capability('local/vendorbilling:vendoradmin', $context)) {
        global $PAGE;
        $currenturl = $PAGE->url;
        if ($currenturl && $currenturl->compare(new moodle_url('/my/'), URL_MATCH_BASE)) {
            $fromlogin = optional_param('redirect', 0, PARAM_INT) == 0;
            if ($fromlogin) {
                global $SESSION;
                if (!isset($SESSION) || !is_object($SESSION)) {
                    $SESSION = new stdClass();
                }
                if (empty($SESSION->vendorbilling_landing_done)) {
                    $SESSION->vendorbilling_landing_done = true;
                    redirect(new moodle_url('/local/vendorbilling/index.php'));
                }
            }
        }

        error_log('vendorbilling: user has vendoradmin capability (main nav)');
        $url = new moodle_url('/local/vendorbilling/index.php');

        $node = $navigation->add(
            get_string('portal_heading', 'local_vendorbilling'),
            $url,
            navigation_node::TYPE_CUSTOM,
            null,
            'vendorbilling'
        );
        $node->showinflatnavigation = true;
        $node->showinsecondarynavigation = true;
        if (method_exists($node, 'set_show_in_primary_navigation')) {
            $node->set_show_in_primary_navigation(true);
        } else if (property_exists($node, 'showinprimarynavigation')) {
            $node->showinprimarynavigation = true;
        }
        error_log('vendorbilling: navigation node added to main nav');
    } else {
        error_log('vendorbilling: user lacks vendoradmin capability (main nav)');
    }
}

function local_vendorbilling_extend_navigation_user_settings(
    navigation_node $parentnode,
    stdClass $user,
    context_user $context,
    stdClass $course,
    context_course $coursecontext
): void {
    if (!isloggedin() || isguestuser()) {
        return;
    }

    $systemcontext = context_system::instance();
    if (!has_capability('local/vendorbilling:vendoradmin', $systemcontext)) {
        error_log('vendorbilling: user lacks vendoradmin capability (user settings nav)');
        return;
    }
    error_log('vendorbilling: user has vendoradmin capability (user settings nav)');

    $parentnode->add(
        get_string('portal_heading', 'local_vendorbilling'),
        new moodle_url('/local/vendorbilling/index.php'),
        navigation_node::TYPE_SETTING,
        null,
        'vendorbilling'
    );
    error_log('vendorbilling: navigation node added to user settings nav');
}
