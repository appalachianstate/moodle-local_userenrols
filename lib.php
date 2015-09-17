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

    require_once("$CFG->dirroot/lib/accesslib.php");
    require_once("$CFG->dirroot/lib/enrollib.php");
    require_once("$CFG->dirroot/lib/grouplib.php");
    require_once("$CFG->dirroot/lib/navigationlib.php");
    require_once("$CFG->dirroot/group/lib.php");


    /**
     * Hook to insert a link in global navigation menu block
     * @param global_navigation $navigation
     */
    /*
    function local_userenrols_extends_navigation(global_navigation $navigation)
    {
    }
    */


    /**
     * Hook to insert a link in settings navigation menu block
     *
     * @param settings_navigation $navigation
     * @param course_context      $context
     * @return void
     */
    function local_userenrols_extend_settings_navigation(settings_navigation $navigation, $context)
    {

        // If not in a course context, then leave
        if ($context == null || $context->contextlevel != CONTEXT_COURSE) {
            return;
        }

        // When on front page there is 'frontpagesettings' node, other
        // courses will have 'courseadmin' node
        if (null == ($courseadmin_node = $navigation->get('courseadmin'))) {
            // Keeps us off the front page
            return;
        }
        if (null == ($useradmin_node = $courseadmin_node->get('users'))) {
            return;
        }

        // Add our links
        
        $config = get_config('local_userenrols');
        if (!empty($config->csvenrol)){
            $useradmin_node->add(
                get_string('IMPORT_MENU_LONG', local_userenrols_plugin::PLUGIN_NAME),
                local_userenrols_plugin::get_plugin_url('import', $context->instanceid),
                navigation_node::TYPE_SETTING,
                get_string('IMPORT_MENU_SHORT', local_userenrols_plugin::PLUGIN_NAME),
                null, new pix_icon('i/import', 'import'));            
        }
        
        if (!empty($config->csvunenrol)){       
            $useradmin_node->add(
                get_string('UNENROLL_MENU_LONG', local_userenrols_plugin::PLUGIN_NAME),
                local_userenrols_plugin::get_plugin_url('unenroll', $context->instanceid),
                navigation_node::TYPE_SETTING,
                get_string('UNENROLL_MENU_SHORT', local_userenrols_plugin::PLUGIN_NAME),
                null, new pix_icon('i/export', 'export'));
        }

        if (!empty($config->metaassign)){ 
            $useradmin_node->add(
                get_string('ASSIGN_MENU_LONG', local_userenrols_plugin::PLUGIN_NAME),
                local_userenrols_plugin::get_plugin_url('assign', $context->instanceid),
                navigation_node::TYPE_SETTING,
                get_string('ASSIGN_MENU_SHORT', local_userenrols_plugin::PLUGIN_NAME),
                null, new pix_icon('t/move', 'assign'));
        }

    }



    class local_userenrols_plugin
    {

        /*
         * Class constants
         */

        const PLUGIN_NAME                 = 'local_userenrols';
        const PLUGIN_PATH                 = 'local/userenrols';
        const PLUGIN_FILEAREA             = 'uploads';

        const REQUIRED_CAP                = 'moodle/course:managegroups';

        const PARAM_COURSE_ID             = 'id';

        const MAXFILESIZE                 = 51200;

        const FORMID_ROLE_ID              = 'role_id';
        const FORMID_USER_ID_FIELD        = 'user_id';
        const FORMID_GROUP                = 'group';
        const FORMID_GROUP_ID             = 'group_id';
        const FORMID_GROUP_CREATE         = 'group_create';
        const FORMID_FILES                = 'filepicker';
        const FORMID_METACOURSE           = 'metacourse';
        const FORMID_GROUPMODE            = 'groupmode';
        const FORMID_METAGROUP            = 'metagroup';
        const FORMID_REMOVE_CURRENT       = 'remove';

        const DEFAULT_USER_ID_FIELD       = 'username';



        /*
         * Member vars
         */

        /**
         * @access private
         * @staticvar array
         */
        private static $user_id_field_options    = null;



        /*
         * Methods
         */

        /**
         * Return a url for this plugin's interfaces
         *
         * @access public
         * @static
         * @param  int          $courseid        Optional id for course
         * @return moodle_url
         */
        public static function get_plugin_url($action, $courseid = 0)
        {
            global $CFG;



            return new moodle_url("$CFG->wwwroot/" . self::PLUGIN_PATH . "/{$action}.php", $courseid ? array(self::PARAM_COURSE_ID => $courseid) : null);

        }



        /**
         * Return list of valid options for user record field matching
         *
         * @access public
         * @static
         * @return array
         */
        public static function get_user_id_field_options()
        {

            if (self::$user_id_field_options == null) {
                self::$user_id_field_options = array(
                    'username' => get_string('username'),
                    'email'    => get_string('email'),
                    'idnumber' => get_string('idnumber')
                );
            }

            return self::$user_id_field_options;

        }



        /**
         * Make a role assignment in the specified course using the specified role
         * id for the user whose id information is passed in the line data.
         *
         * @access public
         * @static
         * @param stdClass      $course           Course in which to make the role assignment
         * @param stdClass      $enrol_instance   Enrol instance to use for adding users to course
         * @param string        $ident_field      The field (column) name in Moodle user rec against which to query using the imported data
         * @param int           $role_id          Id of the role to use in the role assignment
         * @param boolean       $group_assign     Whether or not to assign users to groups
         * @param int           $group_id         Id of group to assign to, 0 indicates use group name from import file
         * @param boolean       $group_create     Whether or not to create new groups if needed
         * @param stored_file   $import_file      File in local repository from which to get enrollment and group data
         * @return string                         String message with results
         *
         * @uses $DB
         */
        public static function import_file(stdClass $course, stdClass $enrol_instance, $ident_field, $role_id, $group_assign, $group_id, $group_create, stored_file $import_file)
        {
            global $DB;



            // Default return value
            $result = '';

            // Need one of these in the loop
            $course_context = context_course::instance($course->id);

            // Choose the regex pattern based on the $ident_field
            switch($ident_field)
            {
                case 'email':
                    $regex_pattern = '/^"?\s*([a-z0-9][\w.%-]*@[a-z0-9][a-z0-9.-]{0,61}[a-z0-9]\.[a-z]{2,6})\s*"?(?:\s*[;,\t]\s*"?\s*([a-z0-9][\w\' .,&-]*))?\s*"?$/Ui';
                    break;
                case 'idnumber':
                    $regex_pattern = '/^"?\s*(\d{1,32})\s*"?(?:\s*[;,\t]\s*"?\s*([a-z0-9][\w\' .,&-]*))?\s*"?$/Ui';
                    break;
                default:
                    $regex_pattern = '/^"?\s*([a-z0-9][\w@.-]*)\s*"?(?:\s*[;,\t]\s*"?\s*([a-z0-9][\w\' .,&-]*))?\s*"?$/Ui';
                    break;
            }

            // If doing group assignments, want to know the valid
            // groups for the course
            $selected_group = null;
            if ($group_assign) {

                if (false === ($existing_groups = groups_get_all_groups($course->id))) {
                    $existing_groups = array();
                }

                if ($group_id > 0) {
                    if (array_key_exists($group_id, $existing_groups)) {
                        $selected_group = $existing_groups[$group_id];
                    } else {
                        // Error condition
                        return sprintf(get_string('ERR_INVALID_GROUP_ID', self::PLUGIN_NAME), $group_id);
                    }
                }

            }

            // Iterate the list of active enrol plugins looking for
            // the meta course plugin
            $metacourse = false;
            $enrols_enabled = enrol_get_instances($course->id, true);
            foreach($enrols_enabled as $enrol) {
                if ($enrol->enrol == 'meta') {
                    $metacourse = true;
                    break;
                }
            }

            // Get an instance of the enrol_manual_plugin (not to be confused
            // with the enrol_instance arg)
            $manual_enrol_plugin = enrol_get_plugin('manual');

            $user_rec     =
            $new_group    =
            $new_grouping = null;

            // Open and fetch the file contents
            $fh = $import_file->get_content_file_handle();
            $line_num = 0;
            while (false !== ($line = fgets($fh))) {
                $line_num++;

                // Clean these up for each iteration
                unset($user_rec, $new_group, $new_grouping);

                if (!($line = trim($line))) continue;

                // Parse the line, from which we may get one or two
                // matches since the group name is an optional item
                // on a line by line basis
                if (!preg_match($regex_pattern, $line, $matches)) {
                    $result .= sprintf(get_string('ERR_PATTERN_MATCH', self::PLUGIN_NAME), $line_num, $line);
                    continue;
                }

                $ident_value    = $matches[1];
                $group_name     = isset($matches[2]) ? $matches[2] : '';

                // User must already exist, we import enrollments
                // into courses, not users into the system. Exclude
                // records marked as deleted. Because idnumber is
                // not enforced unique, possible multiple records
                // returned when using that identifying field, so
                // use ->get_records method to make that detection
                // and inform user
                $user_rec_array = $DB->get_records('user', array($ident_field => addslashes($ident_value), 'deleted' => 0));
                // Should have one and only one record, otherwise
                // report it and move on to the next
                $user_rec_count = count($user_rec_array);
                if ($user_rec_count == 0) {
                    // No record found
                    $result .= sprintf(get_string('ERR_USERID_INVALID', self::PLUGIN_NAME), $line_num, $ident_value);
                    continue;
                } elseif ($user_rec_count > 1) {
                    // Too many records
                    $result .= sprintf(get_string('ERR_USER_MULTIPLE_RECS', self::PLUGIN_NAME), $line_num, $ident_value);
                    continue;
                }

                $user_rec = array_shift($user_rec_array);

                // Fetch all the role assignments this user might have for this course's context
                $roles = get_user_roles($course_context, $user_rec->id, false);
                // If a user has a role in this course, then we leave it alone and move on
                // to the group assignment if there is one. If they have no role, then we
                // should go ahead and add one, as long as it is not a metacourse.
                if (!$roles && $role_id > 0) {
                    if ($metacourse) {
                        $result .= sprintf(get_string('ERR_ENROLL_META', self::PLUGIN_NAME), $line_num, $ident_value);
                    } else {
                        try {
                            $manual_enrol_plugin->enrol_user($enrol_instance, $user_rec->id, $role_id);
                        }
                        catch (Exception $exc) {
                            $result .= sprintf(get_string('ERR_ENROLL_FAILED', self::PLUGIN_NAME), $line_num, $ident_value);
                            $result .= $exc->getMessage();
                            continue;
                        }
                    }
                }

                // If no group assignments, or group is from file, but no
                // group found, next line
                if (!$group_assign ||($group_id == 0 && empty($group_name))) continue;

                // If no group pre-selected, see if group from import already
                // created for that course
                $assign_group_id = 0;
                $assign_group_name = '';
                if ($selected_group != null) {

                    $assign_group_id   = $selected_group->id;
                    $assign_group_name = $selected_group->name;

                } else {

                    foreach($existing_groups as $existing_group) {
                        if ($existing_group->name != $group_name)
                            continue;
                        $assign_group_id   = $existing_group->id;
                        $assign_group_name = $existing_group->name;
                        break;
                    }

                    // No group by that name
                    if ($assign_group_id == 0) {

                        // Can not create one, next line
                        if (!$group_create) continue;

                        // Make a new group for this course
                        $new_group = new stdClass();
                        $new_group->name = addslashes($group_name);
                        $new_group->courseid = $course->id;
                        if (false === ($assign_group_id = groups_create_group($new_group))) {
                            $result .= sprintf(get_string('ERR_CREATE_GROUP', self::PLUGIN_NAME), $line_num, $group_name);
                            continue;
                        } else {
                            // Add the new group to our list for the benefit of
                            // the next contestant. Strip the slashes off the
                            // name since we do a name comparison earlier when
                            // trying to find the group in our local cache and
                            // an escaped semi-colon will cause the test to fail.
                            $new_group->name   =
                            $assign_group_name = stripslashes($new_group->name);
                            $new_group->id = $assign_group_id;
                            $existing_groups[] = $new_group;
                        }

                    } // if ($assign_group_id == 0)

                }

                // Put the user in the group if not aleady in it
                if (   !groups_is_member($assign_group_id, $user_rec->id)
                    && !groups_add_member($assign_group_id, $user_rec->id)) {
                    $result .= sprintf(get_string('ERR_GROUP_MEMBER', self::PLUGIN_NAME), $line_num, $ident_value, $assign_group_name);
                    continue;
                }

                // Any other work...

            } // while fgets

            fclose($fh);

            return (empty($result)) ? get_string('INF_IMPORT_SUCCESS', self::PLUGIN_NAME) : $result;

        } // import_file


        /**
         * Remove all role assignments in the specified course for the specified account
         * id for the user whose id information is passed in the line data.
         *
         * @access public
         * @static
         * @param stdClass      $course           Course in which to make the role assignment
         * @param stdClass      $enrol_instance   Enrol instance to use for adding users to course
         * @param string        $ident_field      The field (column) name in Moodle user rec against which to query using the imported data
         * @param stored_file   $import_file      File in local repository from which to get enrollment and group data
         * @return string                         String message with results
         *
         * @uses $DB
         */
        public static function unenroll_file(stdClass $course, stdClass $enrol_instance, $ident_field, stored_file $import_file)
        {
            global $DB;



            // Default return value
            $result = '';

            // Need one of these in the loop
            $course_context = context_course::instance($course->id);

            // Choose the regex pattern based on the $ident_field
            switch($ident_field)
            {
                case 'email':
                    $regex_pattern = '/^"?\s*([a-z0-9][\w.%-]*@[a-z0-9][a-z0-9.-]{0,61}[a-z0-9]\.[a-z]{2,6})\s*"?(?:\s*[;,\t]\s*"?\s*([a-z0-9][\w\' .,&-]*))?\s*"?$/Ui';
                    break;
                case 'idnumber':
                    $regex_pattern = '/^"?\s*(\d{1,32})\s*"?(?:\s*[;,\t]\s*"?\s*([a-z0-9][\w\' .,&-]*))?\s*"?$/Ui';
                    break;
                default:
                    $regex_pattern = '/^"?\s*([a-z0-9][\w@.-]*)\s*"?(?:\s*[;,\t]\s*"?\s*([a-z0-9][\w\' .,&-]*))?\s*"?$/Ui';
                    break;
            }

            // Get an instance of the enrol_manual_plugin (not to be confused
            // with the enrol_instance arg)
            $manual_enrol_plugin = enrol_get_plugin('manual');

            $user_rec     = null;

            // Open and fetch the file contents
            $fh = $import_file->get_content_file_handle();
            $line_num = 0;
            while (false !== ($line = fgets($fh))) {
                $line_num++;

                // Clean these up for each iteration
                unset($user_rec);

                if (!($line = trim($line))) continue;

                // Parse the line, from which we may get one or two
                // matches since the group name is an optional item
                // on a line by line basis
                if (!preg_match($regex_pattern, $line, $matches)) {
                    $result .= sprintf(get_string('ERR_PATTERN_MATCH', self::PLUGIN_NAME), $line_num, $line);
                    continue;
                }

                $ident_value    = $matches[1];

                // User must already exist, we import enrollments
                // into courses, not users into the system. Exclude
                // records marked as deleted. Because idnumber is
                // not enforced unique, possible multiple records
                // returned when using that identifying field, so
                // use ->get_records method to make that detection
                // and inform user
                $user_rec_array = $DB->get_records('user', array($ident_field => addslashes($ident_value), 'deleted' => 0));
                // Should have one and only one record, otherwise
                // report it and move on to the next
                $user_rec_count = count($user_rec_array);
                if ($user_rec_count == 0) {
                    // No record found
                    $result .= sprintf(get_string('ERR_USERID_INVALID', self::PLUGIN_NAME), $line_num, $ident_value);
                    continue;
                } elseif ($user_rec_count > 1) {
                    // Too many records
                    $result .= sprintf(get_string('ERR_USER_MULTIPLE_RECS', self::PLUGIN_NAME), $line_num, $ident_value);
                    continue;
                }

                $user_rec = array_shift($user_rec_array);

                // Fetch all the role assignments this user might have for this course's context
                $roles = get_user_roles($course_context, $user_rec->id, false);
                // If a user does not have a role in this course, then we leave it alone. If they have a role, then we
                // should go ahead and remove it, as long as it is not a metacourse.
                if ($roles) {
                    foreach ($roles as $role) {
                        if($role->component == "enrol_meta"){
                            $result .= sprintf(get_string('ERR_UNENROLL_META', self::PLUGIN_NAME), $line_num, $ident_value);
                        }else{
                            try {
                                $manual_enrol_plugin->unenrol_user($enrol_instance, $user_rec->id, $role->roleid);
                            }
                            catch (Exception $exc) {
                                $result .= sprintf(get_string('ERR_UNENROLL_FAILED', self::PLUGIN_NAME), $line_num, $ident_value);
                                $result .= $exc->getMessage();
                                continue;
                            }                               
                        }
                    }
                }
            } // while fgets

            fclose($fh);

            return (empty($result)) ? get_string('INF_UNENROLL_SUCCESS', self::PLUGIN_NAME) : $result;

        } // unenroll_file


        /**
         * Assign people to groups based on their metacourse enrollments
         *
         * @access public
         * @static
         * @param stdClass $course            The course in which group assignments are made
         * @param array    $group_selections  Assoc. array ($enrol_id => $group_id)
         * @param boolean  $remove_current    Remove users' current group assignments
         * @return void
         *
         * @uses $DB
         */
        public static function metagroup_assign(stdClass $course, $group_selections, $remove_current = false)
        {
            global $DB;



            $result = '';
            $course_groups = groups_get_all_groups($course->id);

            foreach($group_selections as $enrol_id => $assign_group_id) {

                // No assignment select for this enrol instance
                if (empty($assign_group_id)) {
                    continue;
                }

                // Get users association with this enrol instance
                if (false === ($userids = $DB->get_records('user_enrolments', array('enrolid' => $enrol_id), 'userid', 'userid'))) {
                    continue;
                }

                // Iterate the user id list for this enrol instance
                foreach($userids as $userid => $record_object) {

                    // Remove user from existing groups if needed
                    if ($remove_current) {
                        foreach ($course_groups as $course_group) {
                            if ($course_group->id != $assign_group_id) {
                                groups_remove_member($course_group->id, $userid);
                            }
                        }
                    }

                    // Got an existing group's id
                    groups_add_member($assign_group_id, $userid);

                } // foreach($userids...

            } // foreach($group_selections...

            self::set_group_prefs($course->id, $group_selections);

        } // metagroup_assign



        /**
         * Fetch meta course group selections
         *
         * @access public
         * @static
         * @param int      $course_id         The course in which group assignments are made
         * @return array
         *
         * @uses $DB
         */
        public static function get_group_prefs($course_id)
        {
            global $DB;



            $record_object = $DB->get_record('local_userenrols_metagroup', array('course' => $course_id), 'course, data');
            if (false === $record_object) {

                self::set_group_prefs($course_id);
                return array();

            } else {

                return (array)unserialize($record_object->data);

            }

        } // get_group_prefs



        /**
         * Fetch meta course group selections
         *
         * @access public
         * @static
         * @param int      $course_id         The course in which group assignments are made
         * @param array    $prefs             Array of prefs, group id values keyed by enrol id
         * @return void
         *
         * @uses $DB
         */
        public static function set_group_prefs($course_id, $prefs = array())
        {
            global $DB;


            try {

                $DB->delete_records('local_userenrols_metagroup', array('course' => $course_id));

                $record_object = new stdClass();
                $record_object->course = $course_id;
                $record_object->data   = serialize((array)$prefs);

                $DB->insert_record('local_userenrols_metagroup', $record_object, false);

            }
            catch (Exception $exc) {
                // Squelch the exception
            }

        } // get_group_prefs


    } // class
