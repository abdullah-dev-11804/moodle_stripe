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
        return;
    }

    $parentnode->add(
        get_string('portal_heading', 'local_vendorbilling'),
        new moodle_url('/local/vendorbilling/index.php'),
        navigation_node::TYPE_SETTING,
        null,
        'vendorbilling'
    );
}

/**
 * Fallback for Boost-style primary nav rendering where custom nodes may be hidden.
 *
 * @return string
 */
function local_vendorbilling_before_standard_top_of_body_html(): string {
    if (!isloggedin() || isguestuser()) {
        return '';
    }

    $systemcontext = context_system::instance();
    if (!has_capability('local/vendorbilling:vendoradmin', $systemcontext)) {
        return '';
    }

    $url = (new moodle_url('/local/vendorbilling/index.php'))->out(false);
    $label = get_string('portal_heading', 'local_vendorbilling');
    $jsurl = json_encode($url);
    $jslabel = json_encode($label);

    return '<script>(function(){document.addEventListener("DOMContentLoaded",function(){try{'
        . 'var href=' . $jsurl . ';'
        . 'var label=' . $jslabel . ';'
        . 'var nav=document.querySelector("header .primary-navigation .navigation, .primary-navigation .navigation, header .primary-navigation ul");'
        . 'if(!nav){return;}'
        . 'if(nav.querySelector(\'a[href="\'+href+\'"]\')){return;}'
        . 'var item=document.createElement("li");'
        . 'item.className="nav-item";'
        . 'var link=document.createElement("a");'
        . 'link.className="nav-link";'
        . 'link.href=href;'
        . 'link.textContent=label;'
        . 'try{var targetPath=(new URL(href,window.location.origin)).pathname.replace(/\\/+$/,"");'
        . 'var currentPath=window.location.pathname.replace(/\\/+$/,"");'
        . 'if(targetPath===currentPath){link.classList.add("active");}}catch(e){}'
        . 'item.appendChild(link);'
        . 'nav.appendChild(item);'
        . '}catch(e){}});})();</script>';
}
