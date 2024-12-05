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

require_once('lib.php');

/**
 * Company / User Admin Block
 */
class block_iomad_company_admin extends block_base {

    public function init() {
        $this->title = get_string('blocktitle', 'block_iomad_company_admin');

    }

    public function hide_header() {
        return true;
    }

    /**
     * Iterate through db/iomadmenu.php in plugins
     * NOTE... plugins info is cached, so purge if you change anything
     * directories
     * @return array
     */
    private function get_menu() {
        $menus = [];
        $plugins = get_plugins_with_function('menu', $file = 'db/iomadmenu.php', $include = true);
        unset($plugins['block']['iomad_company_admin']);
        $plugins['block'] = array('iomad_company_admin' => 'block_iomad_company_admin_menu') + $plugins['block'];
        foreach ($plugins as $plugintype) {
            foreach ($plugintype as $plugin => $menufunction) {
                $menus += $menufunction();
            }
        }

        return $menus;
    }

    /**
     * Check company status when accessing this block
     */
    private function check_company_status() {
        global $SESSION, $DB, $USER;

        // Get parameters.
        $edit = optional_param( 'edit', null, PARAM_BOOL );
        $companychange = optional_param( 'companychange', false, PARAM_BOOL );
        $company = optional_param('company', NULL, PARAM_INT);
        $showsuspendedcompanies = optional_param('showsuspendedcompanies', false, PARAM_BOOL);
        $noticeok = optional_param('noticeok', '', PARAM_CLEAN);
        $noticefail = optional_param('noticefail', '', PARAM_CLEAN);

        $SESSION->showsuspendedcompanies = $showsuspendedcompanies;

        $systemcontext = context_system::instance();
        $companycontext = $systemcontext;

        if ($companychange &&
            empty($company) &&
            iomad::has_capability('block/iomad_company_admin:company_add', $companycontext)) {
            // We want to unset the current company.
            $SESSION->currenteditingcompany = 0;
            unset($SESSION->company);
        }

        // Set the session to a user if they are editing a company other than their own.
        if (!empty($company) && ( iomad::has_capability('block/iomad_company_admin:company_add', $companycontext)
            || $DB->get_record('company_users', array('managertype' => 1, 'companyid' => $company, 'userid' => $USER->id)))) {
            $DB->set_field('company_users', 'lastused', time(), ['userid' => $USER->id, 'companyid' => $company]);
            $SESSION->currenteditingcompany = $company;
        }

        // Check if there are any companies.
        if (!$companycount = $DB->count_records('company')) {

            // If not redirect to create form.
            redirect(new moodle_url('/blocks/iomad_company_admin/company_edit_form.php', ['createnew' => 1]));
        }

        // If we don't have one selected pick the first of these.
        if (empty($SESSION->currenteditingcompany) &&
            !iomad::has_capability('block/iomad_company_admin:company_add', $companycontext)) {
            if (iomad::is_company_user()) {
                $company = iomad::companyid();
                $SESSION->currenteditingcompany = $company;
            } else {
                // Otherwise, make the first (or only) company the current one
                $companies = $DB->get_records('company');
                $firstcompany = reset($companies);
                $SESSION->currenteditingcompany = $firstcompany->id;
                $company = $firstcompany->id;
            }
        } else {
            if (!empty($SESSION->currenteditingcompany)) {
              $company = $SESSION->currenteditingcompany;
            } else {
                $company = (object) [];
            }
        }
    }

    public function get_content() {
        global $OUTPUT, $CFG, $SESSION, $USER;

        // Deal with Access approval notifications.
        require_once($CFG->dirroot . '/blocks/iomad_approve_access/lib.php');
        if (iomad_approve_access::has_users() && empty($SESSION->approveaccesswarningshown)) {
           \core\notification::add(get_string('userstoapprove', 'block_iomad_approve_access'), 'info');
           $SESSION->approveaccesswarningshown = true;
        }

        // TODO: Really need a cap check to prevent it being displayed at all.

        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = (object) [];

        // Renderer
        $renderer = $this->page->get_renderer('block_iomad_company_admin');

        // Javascript module
        $this->page->requires->js_call_amd('block_iomad_company_admin/admin', 'init');

        // Get params and session stuff
        $this->check_company_status();

        // Selected tab.
        $showsuspendedcompanies = optional_param('showsuspendedcompanies', false, PARAM_BOOL);

        // Set the current tab to stick.
        if (!empty($selectedtab)) {
            $SESSION->iomad_company_admin_tab = $selectedtab;
        } else if (!empty($SESSION->iomad_company_admin_tab)) {
            $selectedtab = $SESSION->iomad_company_admin_tab;
        } else {
            $selectedtab = 1;
        }

        $systemcontext = context_system::instance();
        $companycontext = $systemcontext;

        if (!empty($companyid)) {
            $companycontext =  \core\context\company::instance($companyid);
        }

        // Build tabs.
        $tabs = [];
        $panes = [];
        $url = $CFG->wwwroot . '/blocks/iomad_company_admin/index.php';
        $selected = true;
        if (iomad::has_capability('block/iomad_company_admin:companymanagement_view', $companycontext)) {
            $tabs[] = [
                'category' => 'CompanyAdmin',
                'icon' => 'fa-building',
                'selected' => $selected,
                'label' => get_string('companymanagement', 'block_iomad_company_admin')
            ];
            $panes[1] = ['category' => 'CompanyAdmin', 'items' => [], 'selected' => $selected];
            $selected = false;
        }
        if (iomad::has_capability('block/iomad_company_admin:usermanagement_view', $companycontext)) {
            $tabs[] = [
                'category' => 'UserAdmin',
                'icon' => 'fa-user',
                'selected' => $selected,
                'label' => get_string('usermanagement', 'block_iomad_company_admin')
            ];
            $panes[2] = ['category' => 'UserAdmin', 'items' => [], 'selected' => $selected];
            $selected = false;
        }
        if (iomad::has_capability('block/iomad_company_admin:coursemanagement_view', $companycontext)) {
            $tabs[] = [
                'category' => 'CourseAdmin',
                'icon' => 'fa-file-text',
                'selected' => $selected,
                'label' => get_string('coursemanagement', 'block_iomad_company_admin')
            ];
            $panes[3] = ['category' => 'CourseAdmin', 'items' => [], 'selected' => $selected];
            $selected = false;
        }
        if (iomad::has_capability('block/iomad_company_admin:licensemanagement_view', $companycontext)) {
            $tabs[] = [
                'category' => 'LicenseAdmin',
                'icon' => 'fa-legal',
                'selected' => $selected,
                'label' => get_string('licensemanagement', 'block_iomad_company_admin')
            ];
            $panes[4] = ['category' => 'LicenseAdmin', 'items' => [], 'selected' => $selected];
            $selected = false;
        }
        if (iomad::has_capability('block/iomad_company_admin:competencymanagement_view', $companycontext)) {
            $tabs[] = [
                'category' => 'CompetencyAdmin',
                'icon' => 'fa-cubes',
                'selected' => $selected,
                'label' => get_string('competencymanagement', 'block_iomad_company_admin')
            ];
            $panes[5] = ['category' => 'CompetencyAdmin', 'items' => [], 'selected' => $selected];
            $selected = false;
        }
        if (iomad::has_capability('block/iomad_commerce:admin_view', $companycontext)) {
            $tabs[] = [
                'category' => 'ECommerceAdmin',
                'icon' => 'fa-truck',
                'selected' => $selected,
                'label' => get_string('blocktitle', 'block_iomad_commerce')
            ];
            $panes[6] = ['category' => 'ECommerceAdmin', 'items' => [], 'selected' => $selected];
            $selected = false;
        }
        if (iomad::has_capability('block/iomad_microlearning:view', $companycontext)) {
            $tabs[] = [
                'category' => 'MicrolearningAdmin',
                'icon' => 'fa-microchip',
                'selected' => false,
                'label' => get_string('threads', 'block_iomad_microlearning')
            ];
            $panes[7] = ['category' => 'MicrolearningAdmin', 'items' => [], 'selected' => $selected];
            $selected = false;
        }
        if (iomad::has_capability('block/iomad_reports:view', $companycontext)) {
            $tabs[] = [
                'category' => 'Reports',
                'icon' => 'fa-bar-chart-o',
                'selected' => $selected,
                'label' => get_string('reports', 'block_iomad_company_admin')
            ];
            $panes[8] = ['category' => 'Reports', 'items' => [], 'selected' => $selected];
            $selected = false;
        }

        // Build content for selected tab (from menu array).
        $menus = $this->get_menu();

        $somethingtodisplay = false;
        foreach ($menus as $key => $menu) {
            $tab = $menu['tab'];

            // If no 'pane' for tab then move on
            if (empty($panes[$tab])) {
                continue;
            }

            // If no capability then move on.
            if (!iomad::has_capability($menu['cap'], $companycontext)) {
                continue;
            }
            $somethingtodisplay = true;

            // Build correct url.
            if (substr($menu['url'], 0, 1) == '/') {
                $url = new moodle_url($menu['url']);
            } else {
                $url = new moodle_url('/blocks/iomad_company_admin/' . $menu['url']);
            }

            // Get topic image icon
            if (!$CFG->iomad_useicons && !empty($menu['icon'])) {
                $icon = $menu['icon'];
            } else if (!empty($menu['icondefault'])) {
                $imgsrc = $OUTPUT->image_url($menu['icondefault'], 'block_iomad_company_admin');
                $icon = '"><img src="'.$imgsrc.'" alt="'.$menu['name'].'" /></br';
            } else {
                $icon = '';
            }

            // Get topic action icon
            if (!$CFG->iomad_useicons && !empty($menu['iconsmall'])) {
                $iconsmall = $menu['iconsmall'];
            } else {
                $iconsmall = '';
            }

            // Get Action description
            if (!empty($menu['name'])) {
                $action = $menu['name'];
            } else {
                $action = '';
            }

            // Construct tabbed entry
            $menu['action'] = $action;
            $menu['iconsmall'] = $iconsmall;
            $menu['icon'] = $icon;
            $menu['url'] = $url;
            $panes[$tab]['items'][] = $menu;
        }

        // If there are no menu items to show this user...
        if (!$somethingtodisplay) {
            $this->content = (object) [];
            $this->content->text = '';
            return $this->content;
        }

        // Remove empty ones.
        $doreset = false;
        $doselected = false;
        foreach ($panes as $paneid => $paneentry) {
            if (empty($paneentry['items'])) {
                unset($panes[$paneid]);
                $doreset = true;
                if ($tabs[$paneid - 1]['selected']) {
                    $doselected = true;
                }
                unset($tabs[$paneid - 1]);
            }
        }

        // Reset the tabs array in case something was removed - as we need to order starting from 0.
        if ($doreset) {
            $tabs = array_values($tabs);
        }

        // Set default selected in case that was removed.
        if ($doselected) {
            $tabs[0]['selected'] = true;
            $panes[array_key_first($panes)]['selected'] =true;
        }

        // Logo.
        $logourl = $renderer->image_url('iomadlogo', 'block_iomad_company_admin');

        // Company selector
        $companyselect = $this->company_selector();

        // Render block.
        $adminblock = new block_iomad_company_admin\output\adminblock($logourl, $companyselect, $tabs, $panes);
        $this->content = (object) [];
        $this->content->text = $renderer->render($adminblock);
        return $this->content;
    }

    public function company_selector() {
        global $USER, $CFG, $DB, $OUTPUT, $SESSION;

        $selector = (object) [];

        // Only display if you have the correct capability, or you are not in more than one company.
        // Just display name of current company if no choice.
        if (!iomad::has_capability('block/iomad_company_admin:company_view_all', context_system::instance())) {
            if ($DB->count_records_sql("SELECT COUNT(DISTINCT companyid) FROM {company_users} WHERE userid = :userid", ['userid' => $USER->id]) <= 1 ) {
                $companyrecords = $DB->get_records('company_users', array('userid' => $USER->id));
                $companyuser = array_pop($companyrecords);
                $company = $DB->get_record('company', array('id' => $companyuser->companyid), '*', MUST_EXIST);
                $selector->companyname = $company->name;
                $selector->onecompany = true;
                return $selector;
            }
        }

        // Possibly more than one company
        $selector->onecompany = false;

        $content = '';

        if (!isloggedin()) {
            return;
        }

        //  Check users session and profile settings to get the current editing company.
        if (!empty($SESSION->currenteditingcompany)) {
            $selectedcompany = $SESSION->currenteditingcompany;
        } else if ($usercompany = company::by_userid($USER->id)) {
            $selectedcompany = $usercompany->id;
        } else {
            $selectedcompany = "";
        }

        //  Check users session current show suspended setting.
        if (!empty($SESSION->showsuspendedcompanies)) {
            $showsuspendedcompanies = $SESSION->showsuspendedcompanies;
        } else {
            $showsuspendedcompanies = false;
        }

        // Get the company name if set.
        if (!empty($selectedcompany)) {
            $companyname = company::get_companyname_byid($selectedcompany);
        } else {
            $companyname = "";
        }

        // Get a list of companies.
        $companylist = company::get_companies_select($showsuspendedcompanies);
        $select = new \block_iomad_company_admin\forms\iomad_company_select_form(new moodle_url($CFG->wwwroot .'/blocks/iomad_company_admin/index.php'), $companylist, $selectedcompany);
        $select->set_data(array('company' => $selectedcompany, 'showsuspendedcompanies' => $showsuspendedcompanies));
        $selector->selectform = $select->render();
        if (!$showsuspendedcompanies) {
            $selector->suspended = $OUTPUT->single_button(new moodle_url($CFG->wwwroot .'/blocks/iomad_company_admin/index.php',
                                               array('showsuspendedcompanies' => true)),
                                               get_string("show_suspended_companies", 'block_iomad_company_admin'));
        } else {
            $selector->suspended = $OUTPUT->single_button(new moodle_url($CFG->wwwroot .'/blocks/iomad_company_admin/index.php',
                                               array('showsuspendedcompanies' => false)),
                                               get_string("hide_suspended_companies", 'block_iomad_company_admin'));
        }

        return $selector;
    }

    /**
     * Do any additional initialization you may need at the time a new block instance is created
     * @return boolean
     */
    function instance_create() {
        global $DB;

        // Bodge? Modify our own instance to make the default region the
        // content area, not the side bar.
        $instance = $this->instance;
        $instance->defaultregion = 'content';
        $instance->defaultweight = -10;
        $DB->update_record('block_instances', $instance);

        return true;
    }
}
