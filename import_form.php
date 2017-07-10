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
     * @package     local_userenrols
     */

    require_once($CFG->libdir.'/formslib.php');



    /**
     * Form definition for the plugin
     *
     */
    class local_userenrols_index_form extends moodleform {

        /**
         * Define the form's contents
         * @see moodleform::definition()
         */
        public function definition()
        {

            // Want to know if there are any meta enrol plugin
            // instances in this course.

            $metacourse = $this->_customdata['data']->metacourse;
            $this->_form->addElement('hidden', local_userenrols_plugin::FORMID_METACOURSE, $metacourse ? '1' : '0');
            $this->_form->setType(local_userenrols_plugin::FORMID_METACOURSE, PARAM_INT);

            if ($metacourse) {
                $this->_form->addElement('warning', null, null, get_string('INF_METACOURSE_WARN', local_userenrols_plugin::PLUGIN_NAME));
            }


            $this->_form->addElement('header', 'identity', get_string('LBL_IDENTITY_OPTIONS', local_userenrols_plugin::PLUGIN_NAME));

            // The userid field name drop down list
            $this->_form->addElement('select', local_userenrols_plugin::FORMID_USER_ID_FIELD, get_string('LBL_USER_ID_FIELD', local_userenrols_plugin::PLUGIN_NAME), $this->_customdata['data']->user_id_field_options);
            $this->_form->setDefault(local_userenrols_plugin::FORMID_USER_ID_FIELD, local_userenrols_plugin::DEFAULT_USER_ID_FIELD);
            $this->_form->addHelpButton(local_userenrols_plugin::FORMID_USER_ID_FIELD, 'LBL_USER_ID_FIELD', local_userenrols_plugin::PLUGIN_NAME);


            $this->_form->addElement('header', 'identity', get_string('LBL_ENROLL_OPTIONS', local_userenrols_plugin::PLUGIN_NAME));

            // The role id drop down list. The get_assignable_roles returns an assoc. array
            // with integer keys (role id) and role name values, so it looks like a sparse
            // array. The php array functions tend to reorder the keys to remove the perceived
            // gaps, so have to merge manually with the 0 option.
            $roles = HTML_QuickForm::arrayMerge(array(0 => get_string('LBL_NO_ROLE_ID', local_userenrols_plugin::PLUGIN_NAME)),
                                                get_assignable_roles($this->_customdata['data']->context, ROLENAME_BOTH));
            $this->_form->addElement('select', local_userenrols_plugin::FORMID_ROLE_ID, get_string('LBL_ROLE_ID', local_userenrols_plugin::PLUGIN_NAME), $roles);
            $this->_form->setDefault(local_userenrols_plugin::FORMID_ROLE_ID, $this->_customdata['data']->default_role_id);
            $this->_form->addHelpButton(local_userenrols_plugin::FORMID_ROLE_ID, 'LBL_ROLE_ID', local_userenrols_plugin::PLUGIN_NAME);
            $this->_form->disabledIf(local_userenrols_plugin::FORMID_ROLE_ID, local_userenrols_plugin::FORMID_METACOURSE, 'eq', '1');


            // Conditionally based on user capability
            if ($this->_customdata['data']->canmanagegroups) {

                // Process groups
                $this->_form->addElement('header', 'identity', get_string('LBL_GROUP_OPTIONS', local_userenrols_plugin::PLUGIN_NAME));

                $this->_form->addElement('selectyesno', local_userenrols_plugin::FORMID_GROUP, get_string('LBL_GROUP', local_userenrols_plugin::PLUGIN_NAME));
                $this->_form->setDefault(local_userenrols_plugin::FORMID_GROUP, 0);
                $this->_form->addHelpButton(local_userenrols_plugin::FORMID_GROUP, 'LBL_GROUP', local_userenrols_plugin::PLUGIN_NAME);

                // Group id selection
                $groups = array(0 => get_string('LBL_NO_GROUP_ID', local_userenrols_plugin::PLUGIN_NAME));
                foreach(groups_get_all_groups($this->_customdata['data']->course->id) as $key => $group_record) {
                    $groups[$key] = $group_record->name;
                }
                $this->_form->addElement('select', local_userenrols_plugin::FORMID_GROUP_ID, get_string('LBL_GROUP_ID', local_userenrols_plugin::PLUGIN_NAME), $groups);
                $this->_form->setDefault(local_userenrols_plugin::FORMID_GROUP_ID, 0);
                $this->_form->addHelpButton(local_userenrols_plugin::FORMID_GROUP_ID, 'LBL_GROUP_ID', local_userenrols_plugin::PLUGIN_NAME);
                $this->_form->disabledIf(local_userenrols_plugin::FORMID_GROUP_ID, local_userenrols_plugin::FORMID_GROUP, 'eq', '0');

                // Create new if needed
                $this->_form->addElement('selectyesno', local_userenrols_plugin::FORMID_GROUP_CREATE, get_string('LBL_GROUP_CREATE', local_userenrols_plugin::PLUGIN_NAME));
                $this->_form->setDefault(local_userenrols_plugin::FORMID_GROUP_CREATE, 0);
                $this->_form->addHelpButton(local_userenrols_plugin::FORMID_GROUP_CREATE, 'LBL_GROUP_CREATE', local_userenrols_plugin::PLUGIN_NAME);
                $this->_form->disabledIf(local_userenrols_plugin::FORMID_GROUP_CREATE, local_userenrols_plugin::FORMID_GROUP,    'eq', '0');
                $this->_form->disabledIf(local_userenrols_plugin::FORMID_GROUP_CREATE, local_userenrols_plugin::FORMID_GROUP_ID, 'gt', '0');

            }

            // File picker
            $this->_form->addElement('header', 'identity', get_string('LBL_FILE_OPTIONS', local_userenrols_plugin::PLUGIN_NAME));

            $this->_form->addElement('filepicker', local_userenrols_plugin::FORMID_FILES, null, null, $this->_customdata['options']);
            $this->_form->addHelpButton(local_userenrols_plugin::FORMID_FILES, 'LBL_FILE_OPTIONS', local_userenrols_plugin::PLUGIN_NAME);
            $this->_form->addRule(local_userenrols_plugin::FORMID_FILES, null, 'required', null, 'client');

            $this->add_action_buttons(true, get_string('LBL_IMPORT', local_userenrols_plugin::PLUGIN_NAME));

        } // definition



        /**
         * Validate the submitted form data
         * @see moodleform::validation()
         */
        public function validation($data, $files)
        {
            global $USER;



            $result = array();

            // User record field to match against, has to be
            // one of three defined in the plugin's class
            if (   empty($data[local_userenrols_plugin::FORMID_USER_ID_FIELD])
                || !array_key_exists($data[local_userenrols_plugin::FORMID_USER_ID_FIELD], local_userenrols_plugin::get_user_id_field_options())) {
                $result[local_userenrols_plugin::FORMID_USER_ID_FIELD] = get_string('invaliduserfield', 'error', $data[local_userenrols_plugin::FORMID_USER_ID_FIELD]);
            }

            // Into which role to put the imported users, has
            // to be one valid for the current user.
            $role_id = empty($data[local_userenrols_plugin::FORMID_ROLE_ID])
                     ? 0 : intval($data[local_userenrols_plugin::FORMID_ROLE_ID]);
            if ($role_id > 0 && !array_key_exists($role_id, get_assignable_roles($this->_customdata['data']->context, ROLENAME_BOTH))) {
                $result[local_userenrols_plugin::FORMID_ROLE_ID] = get_string('invalidroleid', 'error');
            }

            // For Yes/No select 1 and 0 only, anything else not valid
            $group_assign = empty($data[local_userenrols_plugin::FORMID_GROUP])
                          ? 0 : intval($data[local_userenrols_plugin::FORMID_GROUP]);
            if ($group_assign < 0 or $group_assign > 1) {
                $result[local_userenrols_plugin::FORMID_GROUP] = get_string('VAL_INVALID_SELECTION', local_userenrols_plugin::PLUGIN_NAME);
            }

            if ($group_assign) {
                $group_id = empty($data[local_userenrols_plugin::FORMID_GROUP_ID])
                          ? 0 : $group_assign ? intval($data[local_userenrols_plugin::FORMID_GROUP_ID]) : 0;
            } else {
                $group_id = 0;
            }
            if ($group_id > 0 && !array_key_exists($group_id, groups_get_all_groups($this->_customdata['data']->course->id))) {
                $group_id = 0;
                $result[local_userenrols_plugin::FORMID_GROUP_ID] = get_string('VAL_INVALID_SELECTION', local_userenrols_plugin::PLUGIN_NAME);
            }

            // For Yes/No select 1 and 0 only, anything else not valid
            if (($group_assign && ($group_id == 0))) {
                $group_create = empty($data[local_userenrols_plugin::FORMID_GROUP_CREATE])
                              ? 0 : $data[local_userenrols_plugin::FORMID_GROUP_CREATE];
            } else {
                $group_create = 0;
            }
            if ($group_create < 0 or $group_create > 1) {
                $result[local_userenrols_plugin::FORMID_GROUPING] = get_string('VAL_INVALID_SELECTION', local_userenrols_plugin::PLUGIN_NAME);
            }

            // File is not in the $files var, rather the itemid is in
            // $data, but we can get to it through file api. At this
            // stage, the file should be in the user's draft area
            $area_files = get_file_storage()->get_area_files(context_user::instance($USER->id)->id, 'user', 'draft', $data[local_userenrols_plugin::FORMID_FILES], false, false);
            $import_file = array_shift($area_files);
            if (null == $import_file) {
                $result[local_userenrols_plugin::FORMID_FILES] = get_string('VAL_NO_FILES', local_userenrols_plugin::PLUGIN_NAME);
            }

            return $result;

        } // validation


    } // class
