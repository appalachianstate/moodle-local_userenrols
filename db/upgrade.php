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

    defined('MOODLE_INTERNAL') || die();

    require_once("$CFG->dirroot/lib/ddllib.php");


    /**
     * Handle database updates
     *
     * @param   int         $oldversion     The currently recorded version for this mod/plugin
     * @return  boolean
     * @uses $DB
     */
    function xmldb_local_userenrols_upgrade($oldversion=0) {

        global $DB;


        $dbman = $DB->get_manager();
        $result = true;


        if ($oldversion < 2013052002) {

            try
            {

                // Use a stable to persist metacourse->group prefs
                $table = new xmldb_table('local_userenrols_metagroup');

                $table->add_field('id',     XMLDB_TYPE_INTEGER, 10, XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
                $table->add_field('course', XMLDB_TYPE_INTEGER, 10, XMLDB_UNSIGNED, XMLDB_NOTNULL, false, null);
                $table->add_field('data',   XMLDB_TYPE_TEXT, null, false, XMLDB_NOTNULL, false, null);

                $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
                $table->add_index('course', XMLDB_INDEX_UNIQUE, array('course'));

                if (!$dbman->table_exists($table)) {
                    $dbman->create_table($table);
                }

                upgrade_plugin_savepoint(true, 2013052002, 'local', 'userenrols');

            }
            catch (Exception $exc)
            {
                $result = false;
            }

        }

        return $result;

    } // xmldb_local_userenrols_upgrade
