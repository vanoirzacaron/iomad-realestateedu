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

require_once('../../config.php');
require_once(dirname(__FILE__) . '/../../config.php'); // Creates $PAGE.
require_once(dirname('__FILE__').'/lib.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/user/filters/lib.php');
require_once($CFG->dirroot.'/blocks/iomad_company_admin/lib.php');
require_once($CFG->dirroot.'/course/lib.php');

$companyid    = optional_param('companyid', 0, PARAM_INTEGER);
$coursesearch      = optional_param('coursesearch', '', PARAM_CLEAN);// Search string.
$courseid = optional_param('courseid', 0, PARAM_INTEGER);
$update = optional_param('update', null, PARAM_ALPHA);
$license = optional_param('license', 0, PARAM_INTEGER);
$shared = optional_param('shared', 0, PARAM_INTEGER);
$validfor = optional_param('validfor', 0, PARAM_INTEGER);
$warnnotstarted = optional_param('warnnotstarted', 0, PARAM_INTEGER);
$warnexpire = optional_param('warnexpire', 0, PARAM_INTEGER);
$warncompletion = optional_param('warncompletion', 0, PARAM_INTEGER);
$notifyperiod = optional_param('notifyperiod', 0, PARAM_INTEGER);
$expireafter = optional_param('expireafter', 0, PARAM_INTEGER);
$hasgrade = optional_param('hasgrade', 1, PARAM_INTEGER);
$deleteid = optional_param('deleteid', 0, PARAM_INT);
$hideid = optional_param('hideid', 0, PARAM_INT);
$showid = optional_param('showid', 0, PARAM_INT);
$confirm = optional_param('confirm', null, PARAM_ALPHANUM);
$edit = optional_param('edit', -1, PARAM_BOOL);
$delegateid = optional_param('delegateid', 0, PARAM_INT);
$cloneid = optional_param('cloneid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$params = array();

$params['companyid'] = $companyid;
$params['coursesearch'] = $coursesearch;
if ($courseid) {
    $params['courseid'] = $courseid;
}

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

// Deal with edit buttons.
if ($edit != -1) {
    $USER->editing = $edit;
}

require_login();

$systemcontext = context_system::instance();

// Set the companyid
$mycompanyid = iomad::get_my_companyid($systemcontext);

// Is the users company set and no other company selected?
if (empty($companyid) && !empty($mycompanyid)) {
    $companyid = $mycompanyid;
    $params['companyid'] = $mycompanyid;
}

$companycontext = \core\context\company::instance($mycompanyid);
$company = new company($mycompanyid);

iomad::require_capability('block/iomad_company_admin:viewcourses', $companycontext);

if (iomad::has_capability('block/iomad_company_admin:managecourses', $companycontext)
    || iomad::has_capability('block/iomad_company_admin:manageallcourses', $companycontext)) {
    $canedit = true;
} else {
    $canedit = false;
}

if (iomad::has_capability('block/iomad_company_admin:manageallcourses', $companycontext)) {
    $caneditall = true;
} else {
    $caneditall = false;
}

// Set the url.
$linkurl = new moodle_url('/blocks/iomad_company_admin/iomad_courses_form.php');
$linktext = get_string('iomad_courses_title', 'block_iomad_company_admin');

// Print the page header.
$PAGE->set_context($companycontext);
$PAGE->set_url($linkurl);
$PAGE->set_pagelayout('base');
$PAGE->set_title($linktext);
$PAGE->set_other_editing_capability('local/report_users:redocertificates');
$PAGE->set_other_editing_capability('block/iomad_company_admin:managecourses');
$PAGE->set_other_editing_capability('block/iomad_company_admin:manageallcourses');

// Set the page heading.
$PAGE->set_heading($linktext);

// Non boost theme edit buttons.
if ($canedit && $PAGE->user_allowed_editing()) {
    $buttons = $OUTPUT->edit_button($PAGE->url);
    $PAGE->set_button($buttons);
}

// Delete any valid courses.
if (!empty($deleteid)) {
    if (!$course = $DB->get_record('course', array('id' => $deleteid))) {
        throw new moodle_exception('invalidcourse');
    }
    if (confirm_sesskey() && $confirm == md5($deleteid)) {
        $destroy = optional_param('destroy', 0, PARAM_INT);
        // delete the course and all of the data.
        if (company::delete_course($companyid, $deleteid, $destroy)) {
            redirect($linkurl,
                get_string("deletecourse_successful", 'block_iomad_company_admin'),
                null,
                \core\output\notification::NOTIFY_SUCCESS);

        }
        die;
    } else {
        echo $OUTPUT->header();
        $confirmurl = new moodle_url('iomad_courses_form.php',
                                     array('confirm' => md5($deleteid),
                                           'deleteid' => $deleteid,
                                           'sesskey' => sesskey()
                                           ));
        $continue = new single_button($confirmurl, get_string('continue'), 'post');
        $destroyurl = new moodle_url('iomad_courses_form.php',
                                     array('confirm' => md5($deleteid),
                                           'deleteid' => $deleteid,
                                           'destroy' => true,
                                           'sesskey' => sesskey()
                                           ));
        $destroy = new single_button($destroyurl, get_string('destroy', 'block_iomad_company_admin'), 'post');
        $cancel = new single_button($linkurl, get_string('cancel'), 'post');

        $attributes = [
            'role'=>'alertdialog',
            'aria-labelledby'=>'modal-header',
            'aria-describedby'=>'modal-body',
            'aria-modal'=>'true'
        ];

        // Which message are we showing?
        if (iomad::has_capability('block/iomad_company_admin:destroycourses', $companycontext)) {
            $message = get_string('deleteanddestroycoursesfull', 'block_iomad_company_admin', $course->fullname);
        } else {
            $message = get_string('deletecoursesfull', 'block_iomad_company_admin', $course->fullname);
        }
        $confirmhtml = $OUTPUT->box_start('generalbox modal modal-dialog modal-in-page show', 'notice', $attributes);
        $confirmhtml .= $OUTPUT->box_start('modal-content', 'modal-content');
        $confirmhtml .= $OUTPUT->box_start('modal-header p-x-1', 'modal-header');
        $confirmhtml .= html_writer::tag('h4', get_string('confirm'));
        $confirmhtml .= $OUTPUT->box_end();
        $attributes = [
            'role'=>'alert',
            'data-aria-autofocus'=>'true'
        ];
        $confirmhtml .= $OUTPUT->box_start('modal-body', 'modal-body', $attributes);
        $confirmhtml .= html_writer::tag('p', $message);
        $confirmhtml .= $OUTPUT->box_end();
        $confirmhtml .= $OUTPUT->box_start('modal-footer', 'modal-footer');
        if (iomad::has_capability('block/iomad_company_admin:destroycourses', $companycontext)) {
            $confirmhtml .= html_writer::tag('div', $OUTPUT->render($continue) . $OUTPUT->render($destroy) . $OUTPUT->render($cancel), array('class' => 'buttons'));
        } else {
            $confirmhtml .= html_writer::tag('div', $OUTPUT->render($continue) . $OUTPUT->render($cancel), array('class' => 'buttons'));
        }
        $confirmhtml .= $OUTPUT->box_end();
        $confirmhtml .= $OUTPUT->box_end();
        $confirmhtml .= $OUTPUT->box_end();

        echo $confirmhtml;
        echo $OUTPUT->footer();
        die;
    }
}

// Hide/show courses.
if(!empty($hideid) && iomad::has_capability('block/iomad_company_admin:managecourses', $companycontext)) {    
    if (!$course = $DB->get_record('course', array('id' => $hideid))) {
        throw new moodle_exception('invalidcourse');
    }
    if (confirm_sesskey()) {
        $record = get_course($hideid);
        $course = new core_course_list_element($record);
        course_change_visibility($course->id, false);
    }
}
if(!empty($showid) && iomad::has_capability('block/iomad_company_admin:managecourses', $companycontext)) {
    if (!$course = $DB->get_record('course', array('id' => $showid))) {
        throw new moodle_exception('invalidcourse');
    } 
    if (confirm_sesskey()) {
        $record = get_course($showid);
        $course = new core_course_list_element($record);
        course_change_visibility($course->id, true);
    }
}

// Delegate/remove courses.
if(!empty($delegateid) && iomad::has_capability('block/iomad_company_admin:delegatecourse', $companycontext)) {    
    if (!$course = $DB->get_record('course', ['id' => $delegateid])) {
        throw new moodle_exception('invalidcourse');
    }
    if (confirm_sesskey() && $action == 'add') {
        $company->add_course($course, 0, true);
    } else if (confirm_sesskey() && $action == 'remove') {
        $company->remove_control_of_course($delegateid);
    } 
}
if(!empty($showid) && iomad::has_capability('block/iomad_company_admin:managecourses', $companycontext)) {
    if (!$course = $DB->get_record('course', ['id' => $showid])) {
        throw new moodle_exception('invalidcourse');
    } 
    if (confirm_sesskey()) {
        $record = get_course($showid);
        $course = new core_course_list_element($record);
        course_change_visibility($course->id, true);
    }
}

// Clone courses.
if(!empty($cloneid) && iomad::has_capability('block/iomad_company_admin:createcourse', $companycontext)) {    
    if ((!$clonecourse = $DB->get_record('course', ['id' => $cloneid])) ||
         ! $DB->get_record('company_created_courses', ['companyid' => $companyid, 'courseid' => $cloneid])) {
        throw new moodle_exception('invalidcourse');
    }
    if (!$clonecourse = $DB->get_record('course', ['id' => $cloneid])) { // ||
//        ! $DB->get_record('company_created_courses', ['companyid' => $companyid, 'courseid' => $cloneid])) {
        throw new moodle_exception('invalidcourse');
    }

    // Create the course clone form.
    $cloneparams=$params;
    $cloneparams['cloneid'] = $cloneid;
    $cloneurl = new moodle_url('/blocks/iomad_company_admin/iomad_courses_form.php', $cloneparams);
    $cloneform = new block_iomad_company_admin\forms\course_copy_form($cloneurl, ['course' => $clonecourse]);

    // Do we do the actual work?
    if ($clonedata = $cloneform->get_data()) {
        // Process the form and create the copy task.
        $copydata = \copy_helper::process_formdata($clonedata);
        \copy_helper::create_copy($copydata);
        redirect($linkurl, get_string('successfulcopy', 'backup'), null, \core\output\notification::NOTIFY_SUCCESS);
    } else if ($cloneform->is_cancelled()) {
        redirect($linkurl);
    } else {
        $title = get_string('copycoursetitle', 'backup', $clonecourse->shortname);

        // Display the form.
        echo $OUTPUT->header();
        echo $OUTPUT->heading($title);
        $cloneform->display();
        echo $OUTPUT->footer();
        die;
    }
}

// Deal with any custom field searches.
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
}

$baseurl = new moodle_url(basename(__FILE__), $params);
$returnurl = $baseurl;

$mform = new \local_iomad\forms\course_search_form($baseurl, $params);
$mform->set_data($params);

echo $OUTPUT->header();

// Get the list of companies and display it as a drop down select..
$companyids = company::get_companies_select(false);
if ($caneditall) {
    $companyids = [
            '-1' => get_string('nocompany', 'block_iomad_company_admin'),
            '-2' => get_string('allcourses', 'block_iomad_company_admin')
    ] + $companyids;
}

$companyselect = new single_select($linkurl, 'companyid', $companyids, $companyid);
$companyselect->label = get_string('filtercompany', 'block_iomad_company_admin');
echo html_writer::start_tag('div', array('class' => 'iomadclear controlitems'));
if ($canedit) {
    echo html_writer::tag('div', $OUTPUT->render($companyselect), array('id' => 'iomad_company_selector'));
}
echo html_writer::start_tag('div', array('class' => 'iomadcoursesearchform'));
$mform->display();
echo html_writer::end_tag('div');
echo html_writer::end_tag('div');
echo html_writer::start_tag('div', array('class' => 'iomadclear'));

$table = new \block_iomad_company_admin\tables\iomad_courses_table('iomad_courses_table');

if ($companyid == '-2') {
    $companyid = 0;
}

$companysql = " 1 = 1";
$searchsql = "";
$autoselect = "";
$autofrom = "";
if (!empty($companyid)) {
    if ($companyid == "-1") {
        $companysql = " c.id NOT IN (SELECT courseid FROM {company_course}) ";
    } else {
        $companysql = " (c.id IN (
                          SELECT courseid FROM {company_course}
                          WHERE companyid = :companyid)
                         OR ic.shared = 1) ";
        $autoselect = ", cca.autoenrol AS autoenrol";
        $autofrom = " LEFT JOIN {company_course_autoenrol} cca ON (ic.courseid = cca.courseid AND cca.companyid = " . $companyid . ")";
    }
}

if (!empty($coursesearch)) {
    if (!empty($companysql)) {
        $searchsql = " AND ";
    }
    $searchsql .= $DB->sql_like('c.fullname', ':coursesearch', false, false);
    $params['coursesearch'] = "%" . $params['coursesearch'] ."%";
    $params['coursesearchtext'] = $coursesearch;
}
if (!empty($fieldcourseids)) {
    $searchsql .= " AND c.id IN (" . implode(',', array_keys($fieldcourseids)) . ")";
}

// Set up the SQL for the table.
$selectsql = "ic.id,
              c.id AS courseid,
              c.fullname AS coursename,
              ic.licensed,
              ic.shared,
              ic.validlength,
              ic.warnexpire,
              ic.warncompletion,
              ic.notifyperiod,
              ic.expireafter,
              ic.warnnotstarted,
              ic.hasgrade,
              c.visible,
              '$companyid' AS companyid
              $autoselect";
$fromsql = "{iomad_courses} ic JOIN {course} c ON (ic.courseid = c.id) $autofrom ";
$wheresql = "$companysql $searchsql";
$sqlparams = $params;

// Set up the headers for the table.
$tableheaders = [];
$tablecolumns = [];
if (iomad::has_capability('block/iomad_company_admin:company_view_all', $companycontext)) {
    $tableheaders[] = get_string('company', 'block_iomad_company_admin');
    $tablecolumns[] = 'company';
}
$tableheaders = array_merge($tableheaders,
   [get_string('course'),
    get_string('licensed', 'block_iomad_company_admin') . $OUTPUT->help_icon('licensed', 'block_iomad_company_admin'),
    get_string('validfor', 'block_iomad_company_admin') . $OUTPUT->help_icon('validfor', 'block_iomad_company_admin'),
    get_string('expireafter', 'block_iomad_company_admin') . $OUTPUT->help_icon('expireafter', 'block_iomad_company_admin'),
    get_string('warnexpire', 'block_iomad_company_admin') . $OUTPUT->help_icon('warnexpire', 'block_iomad_company_admin'),
    get_string('warnnotstarted', 'block_iomad_company_admin') . $OUTPUT->help_icon('warnnotstarted', 'block_iomad_company_admin'),
    get_string('warncompletion', 'block_iomad_company_admin') . $OUTPUT->help_icon('warncompletion', 'block_iomad_company_admin'),
    get_string('notifyperiod', 'block_iomad_company_admin') . $OUTPUT->help_icon('notifyperiod', 'block_iomad_company_admin'),
    get_string('hasgrade', 'block_iomad_company_admin') . $OUTPUT->help_icon('hasgrade', 'block_iomad_company_admin')]);
$tablecolumns = array_merge($tablecolumns,
                ['coursename',
                 'licensed',
                 'validlength',
                 'expireafter',
                 'warnexpire',
                 'warnnotstarted',
                 'warncompletion',
                 'notifyperiod',
                 'hasgrade']);
if (!empty($companyid) && $companyid != "-1") {
    $tableheaders[] = get_string('autocourses', 'block_iomad_company_admin');
    $tablecolumns[] = 'autoenrol';
}
// Is the user a company manager? If not show course sharing details, otherwise keep these hidden
if (iomad::has_capability('block/iomad_company_admin:company_add', $companycontext)) {
    $tableheaders[] = get_string('shared', 'block_iomad_company_admin')  . $OUTPUT->help_icon('shared', 'block_iomad_company_admin');
    $tablecolumns[] = 'shared';	
}
// If not editing, show course visibility. Otherwise use the actions column
if (empty($USER->editing)){
    $tableheaders[] = get_string('coursevisibility');
    $tablecolumns[] = 'coursevisibility';
}

// Can we manage the courses or just see them?
if ($canedit) {
    // Do we show the action columns?
    if (!empty($USER->editing)) {    
        $tableheaders[] = '';
        $tablecolumns[] = 'actions';
    }
}
$table->set_sql($selectsql, $fromsql, $wheresql, $sqlparams);
$table->define_baseurl($baseurl);
$table->define_columns($tablecolumns);
$table->define_headers($tableheaders);
$table->sort_default_column = 'coursename';
$table->no_sorting('company');
$table->out($CFG->iomad_max_list_courses, true);

echo html_writer::end_tag('div');

echo $OUTPUT->footer();
