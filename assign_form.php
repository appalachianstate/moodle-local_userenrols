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

    require_once($CFG->libdir.'/formslib.php');



    /**
     * Form definition for the plugin
     *
     */
    class local_userenrols_assign_form extends moodleform {

        /**
         * Define the form's contents
         *
         */
        public function definition()
        {

            // Display a list of the meta enrol plugin instances for this
            // course and for each provide a select (dropdownbox) from which
            // to choose the group to place users associated with that enrol

            $this->_form->addElement('header', 'general', get_string('general', 'form'));
            $this->_form->addElement('selectyesno', local_userenrols_plugin::FORMID_REMOVE_CURRENT, get_string('LBL_REMOVE_CURRENT', local_userenrols_plugin::PLUGIN_NAME));
            $this->_form->setDefault(local_userenrols_plugin::FORMID_REMOVE_CURRENT, '1');
            $this->_form->addHelpButton(local_userenrols_plugin::FORMID_REMOVE_CURRENT, 'LBL_REMOVE_CURRENT', local_userenrols_plugin::PLUGIN_NAME);

            $select_list = array('0' => 'No assignments');
            foreach ($this->_customdata['data']->groups as $group) {
                $select_list["$group->id"] = $group->name;
            }

            foreach($this->_customdata['data']->meta_enrols as $index => $enrol) {

                $this->_form->addElement('header', "metagroup_{$enrol->id}", get_string('LBL_ASSIGN_COURSE', local_userenrols_plugin::PLUGIN_NAME, $enrol->name));
                $element_id = local_userenrols_plugin::FORMID_METAGROUP . "[$enrol->id]";
                $this->_form->addElement('select', $element_id, get_string('LBL_ASSIGN_TO', local_userenrols_plugin::PLUGIN_NAME), $select_list);

                // For this enrol id determine if there is a previous
                // group id selection, and if still valid, use it as
                // the default
                if (   array_key_exists($enrol->id, $this->_customdata['data']->group_prefs)
                    && array_key_exists($this->_customdata['data']->group_prefs[$enrol->id], $select_list)) {
                    $this->_form->setDefault($element_id, $this->_customdata['data']->group_prefs[$enrol->id]);
                } else {
                    $this->_form->setDefault($element_id, '0');
                }

                $this->_form->addHelpButton($element_id, 'LBL_ASSIGN_TO', local_userenrols_plugin::PLUGIN_NAME);

            }

            $this->_form->closeHeaderBefore('error');
            $this->_form->addElement('static', 'error');

            $this->add_action_buttons(true, get_string('LBL_ASSIGN', local_userenrols_plugin::PLUGIN_NAME));

        } // definition



        public function validation($data, $files)
        {

            // Basic validation
            $results = parent::validation($data, $files);
            if (is_array($results) && count($results)) {
                return $results;
            }

            // Verify enrol id and group id values are valid for this course
            $error_element = $this->_form->getElement('error');

            $meta_enrols = $this->_customdata['data']->meta_enrols;
            $groups      = $this->_customdata['data']->groups;
            $metagroup   = $data[local_userenrols_plugin::FORMID_METAGROUP];

            if (!is_array($metagroup)) {
                $results['error'] = get_string('VAL_INVALID_FORM_DATA', local_userenrols_plugin::PLUGIN_NAME);
                return $results;
            }

            foreach ($metagroup as $enrol_id => $group_id) {

                if (   !key_exists($enrol_id, $this->_customdata['data']->meta_enrols)
                    || !empty($group_id) && !key_exists($group_id, $groups)) {

                    $results['error'] = get_string('VAL_INVALID_FORM_DATA', local_userenrols_plugin::PLUGIN_NAME);
                    return $results;
                }

            }

            return null;

        } // validation


    } // class
