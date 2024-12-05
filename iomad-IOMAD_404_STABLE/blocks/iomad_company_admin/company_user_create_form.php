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
 * @package   block_iomad_company_admin
 * @copyright 2021 Derick Turner
 * @author    Derick Turner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Script to let a user create a user within a particular company.
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/user/editlib.php');
require_once('lib.php');

$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);
$companyid = optional_param('companyid', company_user::companyid(), PARAM_INTEGER);
$departmentid = optional_param('deptid', 0, PARAM_INTEGER);
$createdok = optional_param('createdok', 0, PARAM_INTEGER);
$licenseid = optional_param('licenseid', 0, PARAM_INTEGER);
$submitbutton = optional_param('submitbutton', null, PARAM_CLEAN);
$submitandback = optional_param('submitandback', null, PARAM_CLEAN);

require_login();

$systemcontext = context_system::instance();

// Set the companyid
$companyid = iomad::get_my_companyid($systemcontext);
$companycontext = \core\context\company::instance($companyid);
$company = new company($companyid);

iomad::require_capability('block/iomad_company_admin:user_create', $companycontext);

$urlparams = array('companyid' => $companyid);
if ($returnurl) {
    $urlparams['returnurl'] = $returnurl;
}
$companylist = new moodle_url('/blocks/iomad_company_admin/index.php', $urlparams);

// Correct the navbar.
// Set the name for the page.
$linktext = get_string('createuser', 'block_iomad_company_admin');
// Set the url.
$linkurl = new moodle_url('/blocks/iomad_company_admin/company_user_create_form.php');
$dashboardurl = new moodle_url('/blocks/iomad_company_admin/index.php');

// Print the page header.
$PAGE->set_context($companycontext);
$PAGE->set_url($linkurl);
$PAGE->set_pagelayout('base');
$PAGE->set_title($linktext);

// Set the page heading.
$PAGE->set_heading($linktext);
$output = $PAGE->get_renderer('block_iomad_company_admin');

// Javascript for fancy select.
$PAGE->requires->js_call_amd('block_iomad_company_admin/company_user', 'init', []);;
$PAGE->requires->js_call_amd('block_iomad_company_admin/department_select_nosub', 'init', array('deptid', 1, optional_param('deptid', 0, PARAM_INT)));

// Check if the company has gone over the user quota.
if (!$company->check_usercount(1)) {
    $maxusers = $company->get('maxusers');
    throw new moodle_exception('maxuserswarning', 'block_iomad_company_admin', $dashboardurl, $maxusers);
}

$mform = new \block_iomad_company_admin\forms\user_edit_form($PAGE->url, $companyid, $departmentid, $licenseid);
if ($mform->is_cancelled()) {
    redirect($dashboardurl);
    die;
} else if ((!empty($submitbutton) || !empty($submitandback)) && $data = $mform->get_data()) {
    // Trim first and lastnames
    $data->firstname = trim($data->firstname);
    $data->lastname = trim($data->lastname);

    $data->userid = $USER->id;
    if ($companyid > 0) {
        $data->companyid = $companyid;
    }

    // we dont want to pass a department id right now - we assign any later on.
    $departmentid = $data->deptid;
    unset($data->departmentid);
    unset($data->deptid);

    // Company managers can't be added to a specified department.
    if ($data->managertype == 1) {
        $parentdepartment = company::get_company_parentnode($companyid);
        $departmentid = $parentdepartment->id;
    }

    if (!$userid = company_user::create($data, $companyid)) {
        $this->verbose("Error inserting a new user in the database!");
        if (!$this->get('ignore_errors')) {
            die();
        }
    }
    $user = new stdclass();
    $user->id = $userid;
    $data->id = $userid;

    // Save custom profile fields data.
    profile_save_data($data);
    \core\event\user_updated::create_from_userid($userid)->trigger();

    // Process any department moves or promotions.
    company::upsert_company_user($userid, $companyid, $departmentid, $data->managertype, $data->educator, false, true);

    // Enrol the user on the courses.
    if (!empty($data->currentcourses)) {
        $userdata = $DB->get_record('user', array('id' => $userid));
        company_user::enrol($userdata, $data->currentcourses, $companyid, 0, 0, $data->due);
        foreach ($data->currentcourses as $courseid) {
            $course = $DB->get_record('course', array('id' => $courseid));
            EmailTemplate::send('user_added_to_course',
                                ['course' => $course,
                                 'user' => $userdata,
                                 'due' => $data->due]);
        }
    }
    // Assign and licenses.
    if (!empty($licenseid)) {
        $licenserecord = (array) $DB->get_record('companylicense', array('id' => $licenseid));
        if (!empty($licenserecord['program'])) {
            // If so the courses are not passed automatically.
            $data->licensecourses =  $DB->get_records_sql_menu("SELECT c.id, clc.courseid FROM {companylicense_courses} clc
                                                                   JOIN {course} c ON (clc.courseid = c.id
                                                                   AND clc.licenseid = :licenseid)",
                                                                   array('licenseid' => $licenserecord['id']));
        }

        if (!empty($data->licensecourses)) {
            $userdata = $DB->get_record('user', array('id' => $userid));
            $count = $licenserecord['used'];
            $numberoflicenses = $licenserecord['allocation'];
            foreach ($data->licensecourses as $licensecourse) {
                if ($count >= $numberoflicenses) {
                    // Set the used amount.
                    $licenserecord['used'] = $count;
                    $DB->update_record('companylicense', $licenserecord);
                    redirect(new moodle_url("/blocks/iomad_company_admin/company_license_users_form.php",
                                             array('licenseid' => $licenseid, 'error' => 1)));
                }

                $issuedate = time();
                $DB->insert_record('companylicense_users',
                                    array('userid' => $userdata->id,
                                          'licenseid' => $licenseid,
                                          'issuedate' => $issuedate,
                                          'licensecourseid' => $licensecourse));

                // Create an event.
                $eventother = array('licenseid' => $licenseid,
                                    'issuedate' => $issuedate,
                                    'duedate' => $data->due);
                $event = \block_iomad_company_admin\event\user_license_assigned::create(array('context' => context_course::instance($licensecourse),
                                                                                              'objectid' => $licenseid,
                                                                                              'courseid' => $licensecourse,
                                                                                              'userid' => $userdata->id,
                                                                                              'other' => $eventother));
                $event->trigger();
                $count++;
            }
        }
    }

    if (isset($data->submitandback)) {
        redirect($dashboardurl, get_string('usercreated', 'block_iomad_company_admin'), null, \core\output\notification::NOTIFY_SUCCESS);
    } else {
        redirect($linkurl, get_string('usercreated', 'block_iomad_company_admin'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}
echo $output->header();

// Check the department is valid.
if (!empty($departmentid) && !company::check_valid_department($companyid, $departmentid)) {
    throw new moodle_exception('invaliddepartment', 'block_iomad_company_admin');
}

// Check the userid is valid.
if (!empty($userid) && !company::check_valid_user($companyid, $userid, $departmentid)) {
    throw new moodle_exception('invaliduserdepartment', 'block_iomad_company_management');
}

// Display a message if user is created..
if ($createdok) {
    echo html_writer::start_tag('div', array('class' => "alert alert-success"));
    echo get_string('usercreated', 'block_iomad_company_admin');
    echo "</div>";
}
// Display the form.
$mform->display();

echo $output->footer();
