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
    require_once('./unenroll_form.php');



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

    $page_head_title = get_string('LBL_UNENROLL_TITLE', local_userenrols_plugin::PLUGIN_NAME) . ' : ' . $COURSE->shortname;

    $PAGE->set_title($page_head_title);
    $PAGE->set_heading($page_head_title);
    $PAGE->set_pagelayout('incourse');
    $PAGE->set_url(local_userenrols_plugin::get_plugin_url('unenroll', $COURSE->id));
    $PAGE->set_cacheable(false);

    // Fix up the form. Have not determined yet whether this is a
    // GET or POST, but the form will be used in either case.

    // Fix up our customdata object to pass to the form constructor
    $data                   = new stdClass();
    $data->course           = $COURSE;
    $data->context          = $PAGE->context;
    $data->user_id_field_options
                            = local_userenrols_plugin::get_user_id_field_options();
    $data->metacourse       = false;
    $data->default_role_id  = 0;

    // Iterate the list of active enrol plugins looking for
    // the meta course plugin
    reset($enrols_enabled);
    foreach($enrols_enabled as $enrol) {
        if ($enrol->enrol == 'meta') {
            $data->metacourse = true;
            $data->default_role_id = 0;
            break;
        }
    }

    // Set some options for the filepicker
    $file_picker_options = array(
		'accepted_types' => array('.csv','.txt'),
        'maxbytes'       => local_userenrols_plugin::MAXFILESIZE);

    $formdata = null;
    $mform    = new local_userenrols_index_form(local_userenrols_plugin::get_plugin_url('unenroll', $COURSE->id)->out(), array('data' => $data, 'options' => $file_picker_options));

    if ($mform->is_cancelled()) {

        // POST request, but cancel button clicked, or formdata not
        // valid. Either event, clear out draft file area to remove
        // unused uploads, then send back to course view
        get_file_storage()->delete_area_files($user_context->id, 'user', 'draft', file_get_submitted_draft_itemid(local_userenrols_plugin::FORMID_FILES));
        redirect($course_url);

    } elseif (!$mform->is_submitted() || null == ($formdata = $mform->get_data())) {

        // GET request, or POST request where data did not
        // pass validation, either case display the form
        echo $OUTPUT->header();
        echo $OUTPUT->heading_with_help(get_string('LBL_UNENROLL_TITLE', local_userenrols_plugin::PLUGIN_NAME), 'HELP_PAGE_UNENROLL', local_userenrols_plugin::PLUGIN_NAME);

        // Display the form with a filepicker
        echo $OUTPUT->container_start();
        $mform->display();
        echo $OUTPUT->container_end();

        echo $OUTPUT->footer();

    } else {

        // POST request, submit button clicked and formdata
        // passed validation, first check session spoofing
        require_sesskey();

        // Collect the input
        $user_id_field     = $formdata->{local_userenrols_plugin::FORMID_USER_ID_FIELD};

        // Leave the file in the user's draft area since we
        // will not plan to keep it after processing
        $area_files = get_file_storage()->get_area_files($user_context->id, 'user', 'draft', $formdata->{local_userenrols_plugin::FORMID_FILES}, null, false);
        $result = local_userenrols_plugin::unenroll_file($COURSE, $user_id_field, array_shift($area_files));

        // Clean up the file area
        get_file_storage()->delete_area_files($user_context->id, 'user', 'draft', $formdata->{local_userenrols_plugin::FORMID_FILES});

        echo $OUTPUT->header();
        echo $OUTPUT->heading_with_help(get_string('LBL_UNENROLL_TITLE', local_userenrols_plugin::PLUGIN_NAME), 'HELP_PAGE_UNENROLL', local_userenrols_plugin::PLUGIN_NAME);

        // Output the processing result
        echo $OUTPUT->box(nl2br($result));
        echo $OUTPUT->continue_button($groups_url);

        echo $OUTPUT->footer();

    }
