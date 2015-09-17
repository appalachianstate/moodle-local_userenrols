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
     *  local_userenrols
     *
     *  This plugin will import user enrollments and group assignments
     *  from a delimited text file. It does not create new user accounts
     *  in Moodle, it will only enroll existing users in a course.
     *
     * @author      Fred Woolard <woolardfa@appstate.edu>
     * @copyright   (c) 2013 Appalachian State Universtiy, Boone, NC
     * @license     GNU General Public License version 3
     * @package     local
     * @subpackage  userenrols
    */

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {

    $settings = new admin_settingpage('local_userenrols', get_string('pluginname', 'local_userenrols'));
    $ADMIN->add('localplugins', $settings);
        
    $settings->add(new admin_setting_configcheckbox('local_userenrols/csvenrol',
        get_string('IMPORT_MENU_LONG', 'local_userenrols'), get_string('ENABLE_IMPORT_MENU', 'local_userenrols'), 1));

    $settings->add(new admin_setting_configcheckbox('local_userenrols/csvunenrol',
        get_string('UNENROLL_MENU_LONG', 'local_userenrols'), get_string('ENABLE_UNENROLL_MENU', 'local_userenrols'), 1));

    $settings->add(new admin_setting_configcheckbox('local_userenrols/metaassign',
        get_string('ASSIGN_MENU_LONG', 'local_userenrols'), get_string('ENABLE_ASSIGN_MENU', 'local_userenrols'), 1));

}
