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
 * @package   local_report_license_allocations
 * @copyright 2021 Derick Turner
 * @author    Derick Turner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/user/filters/lib.php');
require_once($CFG->dirroot.'/blocks/iomad_company_admin/lib.php');

$firstname       = optional_param('firstname', 0, PARAM_CLEAN);
$lastname      = optional_param('lastname', '', PARAM_CLEAN);
$showsuspended  = optional_param('showsuspended', 0, PARAM_INT);
$downloadformat = optional_param('downloadformat', 'excel', PARAM_ALPHA);
$email  = optional_param('email', 0, PARAM_CLEAN);
$sort         = optional_param('sort', 'lastname', PARAM_ALPHA);
$dir          = optional_param('dir', 'ASC', PARAM_ALPHA);
$page         = optional_param('page', 0, PARAM_INT);
// How many per page.
$perpage      = optional_param('perpage', $CFG->iomad_max_list_users, PARAM_INT);
// Id of user to tweak mnet ACL (requires $access).
$acl          = optional_param('acl', '0', PARAM_INT);
$search      = optional_param('search', '', PARAM_CLEAN);// Search string.
$coursesearch = optional_param('coursesearch', '', PARAM_CLEAN);// Search string.
$departmentid = optional_param('deptid', 0, PARAM_INTEGER);
$courses = optional_param_array('courses', NULL, PARAM_INTEGER);
$licenseid    = optional_param('licenseid', 0, PARAM_INTEGER);
$download  = optional_param('download', false, PARAM_BOOL);
$showtext = optional_param('showtext', false, PARAM_BOOL);
$ifirst = optional_param('firstinitial', '', PARAM_ALPHA);
$ilast = optional_param('lastinitial', '', PARAM_ALPHA);
$showexpiryonly = optional_param('showexpiryonly', get_config('local_report_completion_overview', 'showexpiryonly'), PARAM_BOOL);
$bycourse = optional_param('bycourse', false, PARAM_BOOL);

// Deal with pagination.
if ($perpage == 0) {
    $page = 0;
}

$params = array();

if ($firstname) {
    $params['firstname'] = $firstname;
}
if ($lastname) {
    $params['lastname'] = $lastname;
}
if ($email) {
    $params['email'] = $email;
}
if ($sort) {
    $params['sort'] = $sort;
}
if ($dir) {
    $params['dir'] = $dir;
}
if ($page) {
    $params['page'] = $page;
}
if ($perpage) {
    $params['perpage'] = $perpage;
}
if ($bycourse) {
    $params['bycourse'] = $bycourse;
}
if ($search) {
    $params['search'] = $search;
}
if ($coursesearch) {
    $params['coursesearch'] = $coursesearch;
}
if ($departmentid) {
    $params['deptid'] = $departmentid;
}
$params['showtext'] = $showtext;
if ($courses) {
    foreach ($courses as $a => $b) {
        $params['courses['.$a.']'] = $b;
    }
}
$params['firstinitial'] = $ifirst;
$params['lastinitial'] = $ilast;
$params['showexpiryonly'] = $showexpiryonly;
if ($showsuspended) {
    $params['showsuspended'] = $showsuspended;
}
if ($dir == 'ASC') {
     $reversedir = 'DESC';
} else {
     $reversedir = 'ASC';
}
if ($sort == "name") {
    $sort = 'd.' . $sort;
} else if ($sort == "fullname") {
    $sort = 'c.' . $sort;
} else {
    $sort = 'u.' . $sort;
}

require_login();
// Get course customfields.
$usedfields = [];
$customfields = $DB->get_records_sql("SELECT cff.* FROM
                                      {customfield_field} cff 
                                      JOIN {customfield_category} cfc ON (cff.categoryid = cfc.id)
                                      WHERE cfc.area = 'course'
                                      AND cfc.component = 'core_course'
                                      ORDER BY cfc.sortorder, cff.sortorder");
foreach ($customfields as $customfield) {
    ${'customfield_' . $customfield->shortname} = optional_param('customfield_' . $customfield->shortname, null, PARAM_ALPHANUMEXT);
    if (!empty(${'customfield_' . $customfield->shortname})) {
        $params['customfield_' . $customfield->shortname] = ${'customfield_' . $customfield->shortname};
        $usedfields[$customfield->id] = ${'customfield_' . $customfield->shortname};
    }
}

$systemcontext = context_system::instance();

// Set the companyid
$companyid = iomad::get_my_companyid($systemcontext);
$companycontext = \core\context\company::instance($companyid);
$company = new company($companyid);

iomad::require_capability('local/report_completion_overview:view', $companycontext);

// Get the associated department id.
$parentlevel = company::get_company_parentnode($company->id);
$companydepartment = $parentlevel->id;

// Get the company additional optional user parameter names.
$foundobj = iomad::add_user_filter_params($params, $companyid);
$idlist = $foundobj->idlist;
$foundfields = $foundobj->foundfields;

// all companies?
if ($parentslist = $company->get_parent_companies_recursive()) {
    $companysql = " AND u.id NOT IN (
                    SELECT userid FROM {company_users}
                    WHERE companyid IN (" . implode(',', array_keys($parentslist)) ."))";
} else {
    $companysql = "";
}

// Correct the navbar.
// Set the name for the page.
$linktext = get_string('report_completion_overview_title', 'local_report_completion_overview');

// Set the url.
$linkurl = new moodle_url('/local/report_completion_overview/index.php', $params);

// Print the page header.
$PAGE->set_context($companycontext);
$PAGE->set_url($linkurl);
$PAGE->set_pagelayout('report');
$PAGE->set_title($linktext);

// Set the page heading.
$PAGE->set_heading($linktext);
if (iomad::has_capability('local/report_completion:view', $companycontext)) {
    $switchparams = $params;
    $switchparams['bycourse'] = !$bycourse;
    $switchlink = new moodle_url('/local/report_completion_overview/index.php', $switchparams);
    if ($bycourse) {
        $switchcaption = get_string('byusers', 'local_report_completion_overview');
    } else {
        $switchcaption = get_string('bycourses', 'local_report_completion_overview');
    }
    $buttons = $OUTPUT->single_button($switchlink, $switchcaption, 'get');

    if ($showtext) {
        $displaycaption = get_string('format_image', 'portfolio');
    } else {
        $displaycaption = get_string('typetext', 'grades');
    }
    $textparams = $params;
    $textparams['showtext'] = !$showtext;
    $displaylink = new moodle_url('/local/report_completion_overview/index.php', $textparams);
    $buttons .= $OUTPUT->single_button($displaylink, $displaycaption, 'get');
    if ($showexpiryonly) {
        $displaycaption = get_string('showexpiry', 'local_report_completion_overview');
    } else {
        $displaycaption = get_string('hideexpiry', 'local_report_completion_overview');
    }
    $showexpiryparams = $params;
    $showexpiryparams['showexpiryonly'] = !$showexpiryonly;
    $displaylink = new moodle_url('/local/report_completion_overview/index.php', $showexpiryparams);
    $buttons .= $OUTPUT->single_button($displaylink, $displaycaption, 'get');
    $buttoncaption = get_string('pluginname', 'local_report_completion');
    $buttonlink = new moodle_url($CFG->wwwroot . "/local/report_completion/index.php");
    $buttons .= $OUTPUT->single_button($buttonlink, $buttoncaption, 'get');
    $numberarray = [$CFG->iomad_max_list_users => get_string('defaultrows', 'block_iomad_company_admin'), 10 => 10, 25 => 25, 50 => 50, 0 => get_string('all')];
    $perpageparams = $params;
    unset($perpageparams['page']);
    $perpagelink = new moodle_url('/local/report_completion_overview/index.php', $perpageparams);
    $buttons .= "&nbsp" . $OUTPUT->single_select($perpagelink, 'perpage', $numberarray, $perpage, ['' => 'Number of rows']);
    $PAGE->set_button($buttons);
}
$PAGE->navbar->add($linktext, $linkurl);

// Get the renderer.
$output = $PAGE->get_renderer('block_iomad_company_admin');

// Javascript for fancy select.
// Parameter is name of proper select form element followed by 1=submit its form
$PAGE->requires->js_call_amd('block_iomad_company_admin/department_select', 'init', array('deptid', 1, optional_param('deptid', 0, PARAM_INT)));

// Check the department is valid.
if (!empty($departmentid) && !company::check_valid_department($companyid, $departmentid)) {
    throw new moodle_exception('invaliddepartment', 'block_iomad_company_admin');
}

$baseurl = new moodle_url(basename(__FILE__), $params);
$returnurl = $baseurl;

// Work out where the user sits in the company department tree.
if (\iomad::has_capability('block/iomad_company_admin:edit_all_departments', $companycontext)) {
    $userlevels = array($parentlevel->id => $parentlevel->id);
} else {
    $userlevels = $company->get_userlevel($USER);
}

$userhierarchylevel = key($userlevels);
if ($departmentid == 0 ) {
    $departmentid = $userhierarchylevel;
}

$coursesform = new \local_iomad\forms\course_search_form($linkurl, $params);
// Deal with company courses and search.
$allcompanycourses = $company->get_menu_courses(true, false, false, false, false);
$courselistsql = "";
$coursesearchparams = [];
if (!empty($allcompanycourses)) {
    $courselistsql = " AND ic.courseid IN (" . implode(',', array_keys($allcompanycourses)) . ")";
}
if ($showexpiryonly) {
    $courselistsql = " AND ic.validlength > 0";
}

// Course name search.
if (!empty($coursesearch)) {
    $courselistsql .= " AND " . $DB->sql_like('c.fullname', ':coursename', false, false);
    $coursesearchparams['coursename'] = "%" . $coursesearch . "%";
}

// Deal with any custom course field searches.
$fieldcourseids = [];
if (!empty($usedfields)) {
    $foundfields = [];
    foreach ($usedfields as $fieldid => $fieldsearchvalue) {
        if ($customfields[$fieldid]->type == 'text' || $customfields[$fieldid]->type == 'text' ) {
            $fieldsql = "fieldid = :fieldid AND " . $DB->sql_like('value', ':fieldsearchvalue');
            $fieldsearchvalue = '%' . $fieldsearchvalue . '%';
        } else {
            $fieldsql = "value = :fieldsearchvalue AND fieldid = :fieldid";
        }
        $foundfields[] = $DB->get_records_sql("SELECT instanceid FROM {customfield_data} WHERE $fieldsql", ['fieldsearchvalue' => $fieldsearchvalue, 'fieldid' => $fieldid]);
    }

    // Sort the keys to be unique.
    $fieldcourseids = array_pop($foundfields);
    if (!empty($foundfields)) {
        foreach ($foundfields as $foundfield) {
            $fieldcourseids = array_intersect_key($fieldcourseids, $foundfield);
            if (empty($fieldcourseids)) {
                break;
            }
        }
    }
    if (empty($fieldcourseids)) {
        $fieldcourseids[0] = "We didn't find any courses";
    }
    $courselistsql .= " AND c.id IN (" . join(',', array_keys($fieldcourseids)) . ")"; 
}

if (empty($courses)) {
    $courses = $DB->get_records_sql("SELECT ic.courseid, c.fullname FROM {iomad_courses} ic
                                                JOIN {course} c ON (ic.courseid = c.id)
                                                WHERE 1=1 $courselistsql
                                                ORDER BY c.fullname", $coursesearchparams);
}

$expirecourses = $courses;

// Get courses where we don't show the grade.
$gradelesscourses = $DB->get_records_sql("SELECT courseid FROM {iomad_courses} WHERE hasgrade = 0");

// Setup the user search form.
$searchinfo = iomad::get_user_sqlsearch($params, $idlist, $sort, $dir, $departmentid, true, true);

if (!$download) {
    echo $output->header();
    // Display the search form and department picker.

    // Display the license selector and other control forms.
    if (!empty($companyid)) {

        // Display the tree selector thing.
        echo $output->display_tree_selector($company, $parentlevel, $baseurl, $params, $departmentid);

        echo html_writer::start_tag('div', ['id' => 'completion_overview_forms',
                                            'class' => 'report_completion_overview_forms',
                                            'style' => 'display: inline-flex;']);
        // Set up the filter form.
        $options = $params;
        $options['companyid'] = $companyid;
        $mform = new \local_iomad\forms\user_search_form(null, $options);
        $mform->set_data(array('departmentid' => $departmentid));

        $mform->set_data($options);
        $mform->get_data();

        // Display the user filter form.
        echo html_writer::start_tag('div', array('class' => 'iomadusersearchform'));
        $mform->display();
        echo html_writer::end_tag('div');

        // Display the course filter form.
        echo html_writer::start_tag('div', array('class' => 'iomadcoursesearchform'));
        $coursesform->display();
        echo html_writer::end_tag('div');
        echo html_writer::end_tag('div');
    }
}

// Sort out downloadind
if ($download) {
    $page = 0;
    $perpage = 0;
}

$stredit   = get_string('edit');
$returnurl = $CFG->wwwroot."/local/report_completion_overview/index.php";

// Do we have any additional reporting fields?
$extrafields = array();
if (!empty($CFG->iomad_report_fields)) {
    $companyrec = $DB->get_record('company', array('id' => $companyid));
    foreach (explode(',', $CFG->iomad_report_fields) as $extrafield) {
        $extrafields[$extrafield] = new stdclass();
        $extrafields[$extrafield]->name = $extrafield;
        if (strpos($extrafield, 'profile_field') !== false) {
            // Its an optional profile field.
            $profilefield = $DB->get_record('user_info_field', array('shortname' => str_replace('profile_field_', '', $extrafield)));
            if ($profilefield->categoryid == $companyrec->profileid ||
                !$DB->get_record('company', array('profileid' => $profilefield->categoryid))) {
                $extrafields[$extrafield]->title = $profilefield->name;
                $extrafields[$extrafield]->fieldid = $profilefield->id;
            } else {
                unset($extrafields[$extrafield]);
            }
        } else {
            $extrafields[$extrafield]->title = get_string($extrafield);
        }
    }
}

// Deal with where we are on the department tree.
$currentdepartment = company::get_departmentbyid($departmentid);
$showdepartments = company::get_subdepartments_list($currentdepartment);
$showdepartments[$departmentid] = $departmentid;
$departmentsql = " AND d.id IN (" . implode(',', array_keys($showdepartments)) . ")";
$coursesql = " AND lit.courseid IN (" . implode (',', array_keys($courses)) . ")";

//Set up the SQL to get the users.
$selectsql = "DISTINCT u.*";
$fromsql = " {user} u JOIN {company_users} cu ON (u.id = cu.userid) JOIN {department} d ON (cu.departmentid = d.id and cu.companyid = d.company)";

// Set up the headers for the form.
$sortparams = $params;
if (!$bycourse) {
    $sortparams['sort'] = 'firstname';
} else {
    $sortparams['sort'] = 'fullname';
}
if ($sort == 'c.fullnamename') {
    $sortparams['dir'] = $reversedir;
} else {
    $sortparams['dir'] = $dir;
}
$coursenamesort = new moodle_url($baseurl, $sortparams);
if ($sort == 'u.firstname') {
    $sortparams['dir'] = $reversedir;
} else {
    $sortparams['dir'] = $dir;
}
$firstnamesort = new moodle_url($baseurl, $sortparams);
$sortparams = $params;
$sortparams['sort'] = 'lastname';
if ($sort == 'u.lastname') {
    $sortparams['dir'] = $reversedir;
} else {
    $sortparams['dir'] = $dir;
}
$lastnamesort = new moodle_url($baseurl, $sortparams);
$sortparams = $params;
$sortparams['sort'] = 'email';
if ($sort == 'u.email') {
    $sortparams['dir'] = $reversedir;
} else {
    $sortparams['dir'] = $dir;
}
$emailsort = new moodle_url($baseurl, $sortparams);
$sortparams = $params;
$sortparams['sort'] = 'name';
if ($sort == 'd.name') {
    $sortparams['dir'] = $reversedir;
} else {
    $sortparams['dir'] = $dir;
}
$departmentsort = new moodle_url($baseurl, $sortparams);

if (!$download) {
    if (!$bycourse) {
        $headers = [html_writer::tag('a', get_string('firstname'), ['href' => $firstnamesort]) . '&nbsp/&nbsp' . html_writer::tag('a', get_string('lastname'), ['href' => $lastnamesort]),
                    get_string('department', 'block_iomad_company_admin'),
                    html_writer::tag('a', get_string('email'), ['href' => $emailsort])];
    } else {
        $headers = [html_writer::tag('a', get_string('course'), ['href' => $coursenamesort])];
    }
} else {
    if (!$bycourse) {
        $headers = [get_string('fullname'),
                    get_string('department', 'block_iomad_company_admin'),
                    get_string('email')];
    } else {
        $headers = [get_string('course')];
    }
}

if (!$bycourse) {
    $columns = ['fullname',
                'department',
                'email'];
} else {
    $columns = ['course'];
}

if (!$bycourse) {
    foreach ($courses as $courseid => $junk) {
        if (empty($allcompanycourses[$courseid])) {
            continue;
        }
        if (!$download) {
            $headers[] = html_writer::tag('a', $allcompanycourses[$courseid], ['href' => new moodle_url($CFG->wwwroot . '/local/report_completion/index.php', ['courseid' => $courseid])]);
            $columns[] = "c" . $courseid . "coursename";
        } else {
            $headers[] = get_string('coursestatus', 'local_report_completion_overview', $allcompanycourses[$courseid]);
            $columns[] = "c" . $courseid . "coursestatus";
            $headers[] = get_string('coursecompletion', 'local_report_completion_overview', $allcompanycourses[$courseid]);
            $columns[] = "c" . $courseid . "coursecompletion";
            $headers[] = get_string('courseexpiry', 'local_report_completion_overview', $allcompanycourses[$courseid]);
            $columns[] = "c" . $courseid . "courseexpiry";
        }
    }
}

$sqlparams = array('companyid' => $companyid) + $searchinfo->searchparams;

// Deal with optional report fields.
if (!empty($extrafields)) {
    foreach ($extrafields as $extrafield) {
        $headers[] = $extrafield->title;
        $columns[] = $extrafield->name;
        if (!empty($extrafield->fieldid)) {
            // Its a profile field.
            // Skip it this time as these may not have data.
        } else {
            $selectsql .= ", u." . $extrafield->name;
        }
    }
    foreach ($extrafields as $extrafield) {
        if (!empty($extrafield->fieldid)) {
            // Its a profile field.
            $selectsql .= ", P" . $extrafield->fieldid . ".data AS " . $extrafield->name;
            $fromsql .= " LEFT JOIN {user_info_data} P" . $extrafield->fieldid . " ON (u.id = P" . $extrafield->fieldid . ".userid AND P".$extrafield->fieldid . ".fieldid = :p" . $extrafield->fieldid . "fieldid )";
            $sqlparams["p".$extrafield->fieldid."fieldid"] = $extrafield->fieldid;
        }
    }
}
// Deal with initial sort.
$ifirstsort = "";
$ilastsort = "";
if (!empty($ifirst)) {
    $ifirstsort = " AND " . $DB->sql_like('u.firstname', ':ifirst', false, false);
    $sqlparams['ifirst'] = $ifirst . "%";
}
if (!empty($ilast)) {
    $ilastsort = " AND " . $DB->sql_like('u.lastname', ':ilast', false, false);
    $sqlparams['ilast'] = $ilast . "%";
}
$usersort = $sort;
if ($bycourse) {
    $usersort = "u.lastname";
}
$wheresql = $searchinfo->sqlsearch . " AND cu.companyid = :companyid $departmentsql $companysql $ifirstsort $ilastsort ORDER BY $usersort $dir";
$countwheresql = $searchinfo->sqlsearch . " AND cu.companyid = :companyid $departmentsql $companysql $ifirstsort $ilastsort";
$countsql = "SELECT COUNT(u.id) FROM $fromsql WHERE $countwheresql";

// Get the users.
$userlist = $DB->get_records_sql("SELECT $selectsql FROM $fromsql WHERE $wheresql", $sqlparams, $page*$perpage, $perpage);
$usercount = $DB->count_records_sql($countsql, $sqlparams);

// Populate all of the course data
$coursedetailsql = "SELECT lit.*
                    FROM {local_iomad_track} lit
                    WHERE lit.userid = :userid
                    AND lit.courseid = :courseid
                    AND lit.id = (
                      SELECT MAX(id)
                      FROM {local_iomad_track}
                      WHERE userid = lit.userid
                      AND courseid = lit.courseid)";
if (!$bycourse) {
    foreach ($userlist as $userid => $user) {
        $usercourses = [];
        foreach ($courses as $courseid => $junk) {
            if (empty($allcompanycourses[$courseid])) {
                continue;
            }
            if ($comprecord = $DB->get_record_sql($coursedetailsql, ['userid' => $userid, 'courseid' => $courseid])) {
                $comprecord->indate = false;
                $comprecord->outdate = false;
                $comprecord->lastcompleted = null;
                $comprecord->timeexpired = null;
                // Do we have an in-date record?
                if ($indate = $DB->get_records_sql("SELECT * FROM {local_iomad_track}
                                                    WHERE userid = :userid
                                                    AND courseid = :courseid
                                                    AND timecompleted > :time
                                                    ORDER BY id DESC",
                                                   ['userid' => $userid,
                                                   'courseid' => $courseid,
                                                   'time' => time()], 0, 1)) {
                    $comprecord->indate = $indaterec->timeexpires;
                    $indaterec = reset($indate);
                    $comprecord->lastcompleted = $indaterec->timecompleted;
                    $comprecord->timeexpires = $indaterec->timeexpires;
                // Do we have an out-date record?
                } else if ($outdate = $DB->get_records_sql("SELECT * FROM {local_iomad_track}
                                                            WHERE userid = :userid
                                                            AND courseid = :courseid
                                                            AND timecompleted > 0
                                                            ORDER BY id DESC",
                                                           ['userid' => $userid,
                                                            'courseid' => $courseid], 0, 1)) {
                    $comprecord->outdate = true;
                    $outdaterec = reset($outdate);
                    $comprecord->lastcompleted = $outdaterec->timecompleted;
                    $comprecord->timeexpired = $outdaterec->timeexpires;
                }
                $usercourses[$courseid] = $comprecord;
            } else {
                $usercourses[$courseid] = (object) ['coursename' => $allcompanycourses[$courseid],
                                                    'courseid' => $courseid,
                                                    'timestarted' => null,
                                                    'timeenrolled' => null,
                                                    'timecompleted' => null,
                                                    'timeexpires' => null,
                                                    'finalscore' => 0,
                                                    'indate' => false,
                                                    'outdate' => false,
                                                    'userid' => $userid];
            }
        }
        $userlist[$userid]->coursedetails = $usercourses;
    }
} else {
    foreach ($courses as $courseid => $junk) {
        $courseusers = [];
        foreach ($userlist as $userid => $user) {
            if ($comprecord = $DB->get_record_sql($coursedetailsql, ['userid' => $userid, 'courseid' => $courseid])) {
                $comprecord->indate = false;
                $comprecord->outdate = false;
                $comprecord->lastcompleted = null;
                $comprecord->timeexpired = null;
                // Do we have an in-date record?
                if ($indate = $DB->get_records_sql("SELECT * FROM {local_iomad_track}
                                                    WHERE userid = :userid
                                                    AND courseid = :courseid
                                                    AND timecompleted > :time
                                                    ORDER BY id DESC",
                                                   ['userid' => $userid,
                                                   'courseid' => $courseid,
                                                   'time' => time()], 0, 1)) {
                    $comprecord->indate = $indaterec->timeexpires;
                    $indaterec = reset($indate);
                    $comprecord->lastcompleted = $indaterec->timecompleted;
                    $comprecord->timeexpires = $indaterec->timeexpires;
                // Do we have an out-date record?
                } else if ($outdate = $DB->get_records_sql("SELECT * FROM {local_iomad_track}
                                                            WHERE userid = :userid
                                                            AND courseid = :courseid
                                                            AND timecompleted > 0
                                                            ORDER BY id DESC",
                                                           ['userid' => $userid,
                                                            'courseid' => $courseid], 0, 1)) {
                    $comprecord->outdate = true;
                    $outdaterec = reset($outdate);
                    $comprecord->lastcompleted = $outdaterec->timecompleted;
                    $comprecord->timeexpired = $outdaterec->timeexpires;
                }
                $coursesusers[$userid] = $comprecord;
            } else {
                $coursesusers[$userid] = (object) ['coursename' => $allcompanycourses[$courseid],
                                                    'courseid' => $courseid,
                                                    'timestarted' => null,
                                                    'timeenrolled' => null,
                                                    'timecompleted' => null,
                                                    'timeexpires' => null,
                                                    'finalscore' => 0,
                                                    'indate' => false,
                                                    'outdate' => false,
                                                    'userid' => $userid];
            }
        }
        $courses[$courseid]->userdetails = $coursesusers;
    }
}

if (!$download) {
    $pagingurl = new moodle_url($baseurl, $params);
    echo $OUTPUT->initials_bar($ifirst, 'firstinitial', get_string('firstname'), 'firstinitial', $pagingurl);
    echo $OUTPUT->initials_bar($ilast, 'lastinitial', get_string('lastname'), 'lastinitial', $pagingurl);
    $downloadparams = $params;
    $downloadparams['download'] = true;
    echo html_writer::start_tag("div", ['class' => 'displayflex']);
    echo $OUTPUT->download_dataformat_selector(get_string('downloadas', 'table'), $baseurl, 'downloadformat', $downloadparams);
    echo html_writer::end_tag("div");
    if ($perpage != 0) {
        echo $OUTPUT->paging_bar($usercount, $page, $perpage, $pagingurl);
    }
}

// Are we showing all detail or not?
$showfulldetails = get_config('local_report_completion_overview', 'showfulldetail');

// Set up the table. 
$table = new html_table();

// Class is different depending on which way around we are looking at this.
if (!$bycourse) {
    $table->attributes = ['class' => 'generaltable overviewbyuser'];
}
$table->head = $headers;

if (!$bycourse) {
    foreach ($userlist as $user) {
        if (!$download) {
            $row = [html_writer::tag("a", fullname($user), ['href' => new moodle_url($CFG->wwwroot . '/local/report_users/userdisplay.php', ['userid' => $user->id])])];
        } else {
            $row = [fullname($user)];
        }
        $userdepartments = $DB->get_records_sql("SELECT d.name
                                                 FROM {department} d
                                                 JOIN {company_users} cu
                                                 ON d.id = cu.departmentid
                                                 WHERE cu.userid = :userid
                                                 AND cu.companyid = :companyid",
                                                 ['userid' => $user->id, 'companyid' => $companyid]);
        $departmentinfo = "";
        $count = count($userdepartments);
        $current = 1;
        if ($count > 5 && !$download) {
            $departmentinfo .= "<details><summary>" . get_string('show') . "</summary>";
        }
        $first = true;
        foreach ($userdepartments as $userdepartment) {
            $departmentinfo .= format_string($userdepartment->name);
            if ($current < $count) {
                if (!$download) {
                    $departmentinfo .= ",<br>";
                } else {
                    $departmentinfo .= ",\n";
                }
            }
            $current++;
        }

        if ($count > 5) {
            $departmentinfo .= "</details>";
        }
        $row[] = $departmentinfo;
        $row[] = $user->email;

        $runtime = time();
        foreach ($user->coursedetails as $usercourse) {
            $coursesummary = [];
            if (empty($usercourse->timeenrolled)) {
                $coursesummary['enrolled'] = get_string('never');
            } else {
                $coursesummary['enrolled'] = date($CFG->iomad_date_format, $usercourse->timeenrolled);
            }
            if (empty($usercourse->timestarted)) {
                $coursesummary['timestarted'] = get_string('never');
            } else {
                $coursesummary['timestarted'] = date($CFG->iomad_date_format, $usercourse->timestarted);
            }
            if (empty($usercourse->timecompleted)) {
                $coursesummary['timecompleted'] = get_string('never');
            } else {
                $coursesummary['timecompleted'] = date($CFG->iomad_date_format, $usercourse->timecompleted);
            }
            if (empty($usercourse->lastcompleted)) {
                $coursesummary['lastcompleted'] = get_string('never');
            } else {
                $coursesummary['lastcompleted'] = date($CFG->iomad_date_format, $usercourse->lastcompleted);
            }
            if (empty($usercourse->timeexpires)) {
                $coursesummary['timeexpires'] = '';
            } else {
                $coursesummary['timeexpires'] = date($CFG->iomad_date_format, $usercourse->timeexpires);
            }
            if (empty($usercourse->timeexpired)) {
                $coursesummary['timeexpired'] = '';
            } else {
                $coursesummary['timeexpired'] = date($CFG->iomad_date_format, $usercourse->timeexpired);
            }
            $coursesummary['finalscore'] = $usercourse->finalscore;

            // Make the extra info.
            if (!$showfulldetails) {
                if (empty($coursesummary['timeexpired'])) {
                    $rowtext = get_string('coursesummary_partial', 'local_report_completion_overview', (object) $coursesummary);
                } else {
                    if ($usercourse->timeexpired > $runtime) {
                        $rowtext = get_string('coursesummary_partial_extra_indate', 'local_report_completion_overview', (object) $coursesummary);
                    } else {
                        $rowtext = get_string('coursesummary_partial_extra_outdate', 'local_report_completion_overview', (object) $coursesummary);
                    }
                }
            } else {
                if (!empty($expirecourses[$usercourse->courseid]) && empty($gradelesscourses[$usercourse->courseid])) {
                    if (empty($coursesummary['timeexpired'])) {
                        $rowtext = get_string('coursesummary', 'local_report_completion_overview', (object) $coursesummary);
                    } else {
                    if ($usercourse->timeexpired > $runtime) {
                            $rowtext = get_string('coursesummary_extra_indate', 'local_report_completion_overview', (object) $coursesummary);
                        } else {
                            $rowtext = get_string('coursesummary_extra_outdate', 'local_report_completion_overview', (object) $coursesummary);
                        }
                    }
                } else if (empty($expirecourses[$usercourse->courseid]) && empty($gradelesscourses[$usercourse->courseid])) {
                    $rowtext = get_string('coursesummary_noexpiry', 'local_report_completion_overview', (object) $coursesummary);
                } else if (!empty($expirecourses[$usercourse->courseid]) && !empty($gradelesscourses[$usercourse->courseid])) {
                    $rowtext = get_string('coursesummary_nograde', 'local_report_completion_overview', (object) $coursesummary);
                } else if (empty($expirecourses[$usercourse->courseid]) && !empty($gradelesscourses[$usercourse->courseid])) {
                    $rowtext = get_string('coursesummary_nograde_noexpiry', 'local_report_completion_overview', (object) $coursesummary);
                }
            }

            // Set up the cell classes.
            if (empty($expirecourses[$usercourse->courseid])) {
                $rowclass = "ignored";
                $statustext = "";
            } else {
                if (empty($usercourse->timeenrolled)) {
                    $rowclass = "notenrolled";
                    if ($usercourse->indate) {
                        if ($usercourse->indate > $runtime) {
                            $rowclass .= "-indate";
                        } else {
                            $rowclass .= "-expiring";
                        }
                    }
                    if ($usercourse->outdate) {
                        $rowclass .= "-outdate";
                    }
                }
                if (!empty($usercourse->timeenrolled) && empty($usercourse->timecompleted)) {
                    $rowclass = "notcompleted";
                    if ($usercourse->indate) {
                        if ($usercourse->indate > $runtime) {
                            $rowclass .= "-indate";
                        } else {
                            $rowclass .= "-expiring";
                        }
                    }
                    if ($usercourse->outdate) {
                        $rowclass .= "-outdate";
                    }
                }
                if (!empty($usercourse->timeenrolled) && !empty($usercourse->timecompleted) && $usercourse->timeexpires > $runtime) {
                    $rowclass = "indate";
                }
                if (!empty($usercourse->timeenrolled) && !empty($usercourse->timecompleted) && $usercourse->timeexpires < $runtime + get_config('local_report_completion_overview', 'warningduration')) {
                    $rowclass = "expiring";
                }
                if (!empty($usercourse->timeenrolled) && !empty($usercourse->timecompleted) && $usercourse->timeexpires < $runtime) {
                    if (empty($usercourse->timeexpires)) {
                        $rowclass = "indate";
                    } else {
                        $rowclass = "expired";
                    } 
                }
                $statustext = get_string($rowclass, 'local_report_completion_overview');
            }
    
            if ($download) {
                $row[] = $statustext;
                $row[] = $coursesummary['timecompleted'];
                $row[] = $coursesummary['timeexpires'];
            } else if (!$showtext) {
                $row[] = "<div class='completion_overview_icon' title='$rowtext'><span class='dot $rowclass'></span></div>";
            } else { 
                $row[] = "<span>" . nl2br($rowtext) . "</span>";
            } 
        }
        $table->data[] = $row;
    }
} else {
    // Doing this by course instead.
    foreach ($userlist as $user) {
        if (!$download) {
            $headers[] = html_writer::tag("a", fullname($user), ['href' => new moodle_url($CFG->wwwroot . '/local/report_users/userdisplay.php', ['userid' => $user->id])]);
            $columns[] = "u" . $user->id;
        } else {
            $headers[] = fullname($user);
            $columns[] = "u" . $user->id;
        }
    }

    $table->head = $headers;
            
    foreach ($courses as $course) {
        $runtime = time();
        if (!$download) {
            $row = [html_writer::tag("a", $course->fullname, ['href' => new moodle_url($CFG->wwwroot . '/local/report_completion/index.php', ['courseid' => $course->courseid])])];
        } else {
            $row = [$course->fullname];
        }
        foreach ($course->userdetails as $usercourse) {
            $coursesummary = [];
            if (empty($usercourse->timeenrolled)) {
                $coursesummary['enrolled'] = get_string('never');
            } else {
                $coursesummary['enrolled'] = date($CFG->iomad_date_format, $usercourse->timeenrolled);
            }
            if (empty($usercourse->timestarted)) {
                $coursesummary['timestarted'] = get_string('never');
            } else {
                $coursesummary['timestarted'] = date($CFG->iomad_date_format, $usercourse->timestarted);
            }
            if (empty($usercourse->timecompleted)) {
                $coursesummary['timecompleted'] = get_string('never');
            } else {
                $coursesummary['timecompleted'] = date($CFG->iomad_date_format, $usercourse->timecompleted);
            }
            if (empty($usercourse->lastcompleted)) {
                $coursesummary['lastcompleted'] = get_string('never');
            } else {
                $coursesummary['lastcompleted'] = date($CFG->iomad_date_format, $usercourse->lastcompleted);
            }
            if (empty($usercourse->timeexpires)) {
                $coursesummary['timeexpires'] = '';
            } else {
                $coursesummary['timeexpires'] = date($CFG->iomad_date_format, $usercourse->timeexpires);
            }
            if (empty($usercourse->timeexpired)) {
                $coursesummary['timeexpired'] = '';
            } else {
                $coursesummary['timeexpired'] = date($CFG->iomad_date_format, $usercourse->timeexpired);
            }
            $coursesummary['finalscore'] = $usercourse->finalscore;

            // Make the extra info.
            if (!$showfulldetails) {
                if (empty($coursesummary['timeexpired'])) {
                    $rowtext = get_string('coursesummary_partial', 'local_report_completion_overview', (object) $coursesummary);
                } else {
                    if ($usercourse->timeexpired > $runtime) {
                        $rowtext = get_string('coursesummary_partial_extra_indate', 'local_report_completion_overview', (object) $coursesummary);
                    } else {
                        $rowtext = get_string('coursesummary_partial_extra_outdate', 'local_report_completion_overview', (object) $coursesummary);
                    }
                }
            } else {
                if (!empty($expirecourses[$usercourse->courseid]) && empty($gradelesscourses[$usercourse->courseid])) {
                    if (empty($coursesummary['timeexpired'])) {
                        $rowtext = get_string('coursesummary', 'local_report_completion_overview', (object) $coursesummary);
                    } else {
                    if ($usercourse->timeexpired > $runtime) {
                            $rowtext = get_string('coursesummary_extra_indate', 'local_report_completion_overview', (object) $coursesummary);
                        } else {
                            $rowtext = get_string('coursesummary_extra_outdate', 'local_report_completion_overview', (object) $coursesummary);
                        }
                    }
                } else if (empty($expirecourses[$usercourse->courseid]) && empty($gradelesscourses[$usercourse->courseid])) {
                    $rowtext = get_string('coursesummary_noexpiry', 'local_report_completion_overview', (object) $coursesummary);
                } else if (!empty($expirecourses[$usercourse->courseid]) && !empty($gradelesscourses[$usercourse->courseid])) {
                    $rowtext = get_string('coursesummary_nograde', 'local_report_completion_overview', (object) $coursesummary);
                } else if (empty($expirecourses[$usercourse->courseid]) && !empty($gradelesscourses[$usercourse->courseid])) {
                    $rowtext = get_string('coursesummary_nograde_noexpiry', 'local_report_completion_overview', (object) $coursesummary);
                }
            }

            // Set up the cell classes.
            if (empty($expirecourses[$usercourse->courseid])) {
                $rowclass = "ignored";
                $statustext = "";
            } else {
                if (empty($usercourse->timeenrolled)) {
                    $rowclass = "notenrolled";
                    if ($usercourse->indate) {
                        if ($usercourse->indate > $runtime) {
                            $rowclass .= "-indate";
                        } else {
                            $rowclass .= "-expiring";
                        }
                    }
                    if ($usercourse->outdate) {
                        $rowclass .= "-outdate";
                    }
                }
                if (!empty($usercourse->timeenrolled) && empty($usercourse->timecompleted)) {
                    $rowclass = "notcompleted";
                    if ($usercourse->indate) {
                        if ($usercourse->indate > $runtime) {
                            $rowclass .= "-indate";
                        } else {
                            $rowclass .= "-expiring";
                        }
                    }
                    if ($usercourse->outdate) {
                        $rowclass .= "-outdate";
                    }
                }
                if (!empty($usercourse->timeenrolled) && !empty($usercourse->timecompleted) && $usercourse->timeexpires > $runtime) {
                    $rowclass = "indate";
                }
                if (!empty($usercourse->timeenrolled) && !empty($usercourse->timecompleted) && $usercourse->timeexpires < $runtime + get_config('local_report_completion_overview', 'warningduration')) {
                    $rowclass = "expiring";
                }
                if (!empty($usercourse->timeenrolled) && !empty($usercourse->timecompleted) && $usercourse->timeexpires < $runtime) {
                    if (empty($usercourse->timeexpires)) {
                        $rowclass = "indate";
                    } else {
                        $rowclass = "expired";
                    } 
                }
                $statustext = get_string($rowclass, 'local_report_completion_overview');
            }
    
            if ($download) {
                $row[] = $statustext;
                $row[] = $coursesummary['timecompleted'];
                $row[] = $coursesummary['timeexpires'];
            } else if (!$showtext) {
                $row[] = "<div class='completion_overview_icon' title='$rowtext'><span class='dot $rowclass'></span></div>";
            } else { 
                $row[] = "<span>" . nl2br($rowtext) . "</span>";
            } 
        }
        $table->data[] = $row;
    }
}
if (!$download) {
    echo html_writer::table($table);
    echo $output->footer();
} else {
    // Get the download helper.
    if (ob_get_length()) {
        throw new coding_exception("Output can not be buffered before instantiating table_dataformat_export_format");
    }

    $classname = 'dataformat_' . $downloadformat . '\writer';
    if (!class_exists($classname)) {
        throw new coding_exception("Unable to locate dataformat/$dataformat/classes/writer.php");
    }
    $dataformat = new $classname;

    // The dataformat export time to first byte could take a while to generate...
    set_time_limit(0);

    // Close the session so that the users other tabs in the same session are not blocked.
    \core\session\manager::write_close();

    //$dataformat->set_filename($filename);
    $dataformat->set_filename("test");
    $dataformat->send_http_headers();
    $dataformat->set_sheettitle("Testsheet");
    $dataformat->start_output();
    $dataformat->start_sheet($headers);

    $rownum = 1;
    // Output the rows
    foreach ($table->data as $row) {
        $dataformat->write_record($row, $rownum++);
    }
    $dataformat->close_sheet($headers);
    $dataformat->close_output();
    die;
}
