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

function local_vendorbilling_can_see_portal(): bool {
    if (!isloggedin() || isguestuser()) {
        return false;
    }
    global $USER;

    // Only show if this user is mapped to a vendor in this plugin.
    $vendor = \local_vendorbilling\manager::get_vendor_for_user((int)$USER->id);
    return !empty($vendor);
}


/**
 * Add Vendor Portal to global/flat navigation.
 */
function local_vendorbilling_extend_navigation(global_navigation $navigation) {
    if (!local_vendorbilling_can_see_portal()) {
        return;
    }

    global $PAGE;

    // Optional: redirect vendor admins from Dashboard after login to the portal.
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

    // These control flat nav / secondary nav / (some themes) primary.
    $node->showinflatnavigation = true;
    $node->showinsecondarynavigation = true;

    // Some Moodle versions/themes support this:
    if (method_exists($node, 'set_show_in_primary_navigation')) {
        $node->set_show_in_primary_navigation(true);
    } else if (property_exists($node, 'showinprimarynavigation')) {
        $node->showinprimarynavigation = true;
    }
}

/**
 * Add Vendor Portal into the top Primary navigation (Moodle 4.x).
 * This is the key fix when extend_navigation() doesn't show in the top bar.
 */
function local_vendorbilling_extend_primary_navigation(\core\navigation\output\primary $primarynav): void {
    if (!local_vendorbilling_can_see_portal()) {
        return;
    }

    $primarynav->add(
        get_string('portal_heading', 'local_vendorbilling'),
        new moodle_url('/local/vendorbilling/index.php'),
        null,
        null,
        'vendorbilling'
    );
}

/**
 * Add to user settings navigation area (optional).
 */
function local_vendorbilling_extend_navigation_user_settings(
    navigation_node $parentnode,
    stdClass $user,
    context_user $context,
    stdClass $course,
    context_course $coursecontext
): void {
    if (!local_vendorbilling_can_see_portal()) {
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
 * Fallback injector for themes that don't show custom nodes in primary nav.
 *
 * @return string
 */
function local_vendorbilling_before_standard_top_of_body_html(): string {
    if (!isloggedin() || isguestuser()) {
        return '';
    }

   if (!local_vendorbilling_can_see_portal()) {
    return '';
}

    $url = (new moodle_url('/local/vendorbilling/index.php'))->out(false);
    $label = get_string('portal_heading', 'local_vendorbilling');

    $jsurl = json_encode($url);
    $jslabel = json_encode($label);

    return '<script>(function(){' .
        'function addLink(){try{' .
            'var href=' . $jsurl . ';' .
            'var label=' . $jslabel . ';' .

            // Prefer Moodle 4 "moremenu" primary navigation.
            'var ul = document.querySelector("nav.moremenu ul.nav, nav.moremenu ul.more-nav, nav.moremenu .nav");' .

            // Fallbacks for other themes/structures.
            'if(!ul){ ul = document.querySelector("header .primary-navigation ul.nav, header .primary-navigation ul, .primary-navigation ul.nav, .primary-navigation ul"); }' .
            'if(!ul){ ul = document.querySelector("#nav-drawer nav ul.list-unstyled, #nav-drawer ul.list-unstyled, #nav-drawer ul"); }' .
            'if(!ul){ return; }' .

            // Already exists?
            'if(ul.querySelector(\'a[href="\'+href+\'"]\')){ return; }' .

            // Create li/a with Bootstrap nav classes to avoid "dot" bullets.
            'var li=document.createElement("li");' .
            'li.className="nav-item";' .
            'var a=document.createElement("a");' .
            'a.className="nav-link";' .
            'a.href=href;' .
            'a.textContent=label;' .

            // Mark active.
            'try{' .
                'var targetPath=(new URL(href,window.location.origin)).pathname.replace(/\\/+$/,"");' .
                'var currentPath=window.location.pathname.replace(/\\/+$/,"");' .
                'if(targetPath===currentPath){ a.classList.add("active"); }' .
            '}catch(e){}' .

            'li.appendChild(a);' .

            // If UL is a list-group (drawer), nav-item/nav-link look odd; adjust.
            'if(ul.classList && ul.classList.contains("list-unstyled")){' .
                'li.className="";' .
                'a.className="list-group-item list-group-item-action";' .
            '}' .

            'ul.appendChild(li);' .
        '}catch(e){}}' .

        // Run now + after DOM ready (themes differ).
        'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",addLink);}else{addLink();}' .
    '})();</script>';
}