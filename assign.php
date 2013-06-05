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

    require_once('../../config.php');
    require_once('./lib.php');
    require_once('./assign_form.php');



    // Fetch the course id from query string
    $course_id = required_param(local_userenrols_plugin::PARAM_COURSE_ID, PARAM_INT);

    // No anonymous access for this page, and this will
    // handle bogus course id values as well
    require_login($course_id);
    // $PAGE, $USER, $COURSE, and other globals now set
    // up, check the capabilities
    require_capability(local_userenrols_plugin::REQUIRED_CAP, $PAGE->context);

    $user_context = context_user::instance($USER->id);

    // Want this for subsequent print_error() calls
    $course_url = new moodle_url("{$CFG->wwwroot}/course/view.php", array('id' => $COURSE->id));
    $groups_url = new moodle_url("{$CFG->wwwroot}/group/index.php", array('id' => $COURSE->id));

    $page_head_title = get_string('LBL_ASSIGN_TITLE', local_userenrols_plugin::PLUGIN_NAME) . ' : ' . $COURSE->shortname;

    $PAGE->set_title($page_head_title);
    $PAGE->set_heading($page_head_title);
    $PAGE->set_pagelayout('incourse');
    $PAGE->set_url(local_userenrols_plugin::get_plugin_url('assign', $COURSE->id));
    $PAGE->set_cacheable(false);


    // Iterate the list of active enrol plugins looking for
    // the meta plugin, deal breaker if not found
    $meta_enrols = array();
    foreach(enrol_get_instances($COURSE->id, true) as $enrol) {
        if ($enrol->enrol == 'meta') {
            if (empty($enrol->name)) {
                // No custom name, so fetch the linked course
                // to get its name
                if (false == ($linked_course = $DB->get_record('course', array('id' => $enrol->customint1), 'shortname'))) {
                    continue;
                }
                $enrol->name = $linked_course->shortname;
            }
            $meta_enrols["$enrol->id"] = $enrol;
        }
    }
    // Deal breaker
    if (!$meta_enrols) {
        print_error('ERR_NO_META_ENROL', local_userenrols_plugin::PLUGIN_NAME, $course_url);
    }

    // Fix up the form. Have not determined yet whether this is a
    // GET or POST, but the form will be used in either case.

    // Fix up our customdata object to pass to the form constructor
    $data                   = new stdClass();
    $data->course           = $COURSE;
    $data->context          = $PAGE->context;
    $data->meta_enrols      = $meta_enrols;
    $data->groups           = groups_get_all_groups($course_id);
    $data->group_prefs      = local_userenrols_plugin::get_group_prefs($COURSE->id);

    $formdata = null;
    $mform    = new local_userenrols_assign_form(local_userenrols_plugin::get_plugin_url('assign', $COURSE->id)->out(), array('data' => $data));

    if ($mform->is_cancelled()) {

        // POST request, but cancel button clicked
        redirect($course_url);

    } elseif (!$mform->is_submitted() || null == ($formdata = $mform->get_data())) {

        // GET request, or POST request where data did not
        // pass validation, either case display the form
        echo $OUTPUT->header();
        echo $OUTPUT->heading_with_help(get_string('LBL_ASSIGN_TITLE', local_userenrols_plugin::PLUGIN_NAME), 'HELP_PAGE_ASSIGN', local_userenrols_plugin::PLUGIN_NAME);

        $mform->display();

        echo $OUTPUT->footer();

    } else {

        // POST request, submit button clicked and formdata
        // passed validation, first check session spoofing
        require_sesskey();

        // Collect the input
        $metagroup         = $formdata->{local_userenrols_plugin::FORMID_METAGROUP};
        $remove_current    = intval($formdata->{local_userenrols_plugin::FORMID_REMOVE_CURRENT});

        local_userenrols_plugin::metagroup_assign($COURSE, $metagroup, $remove_current);

        echo $OUTPUT->header();
        echo $OUTPUT->heading_with_help(get_string('LBL_ASSIGN_TITLE', local_userenrols_plugin::PLUGIN_NAME), 'HELP_PAGE_ASSIGN', local_userenrols_plugin::PLUGIN_NAME);

        // Output the processing result
        echo $OUTPUT->notification(get_string('INF_IMPORT_SUCCESS', local_userenrols_plugin::PLUGIN_NAME), 'notifysuccess');
        echo $OUTPUT->continue_button($groups_url);

        echo $OUTPUT->footer();

    }
