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
 * @package   local_iomad_signup
 * @copyright 2021 Derick Turner
 * @author    Derick Turner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/login/lib.php');
$wantedcompanyid = optional_param('id', 0, PARAM_INT);
$wantedcompanyshort = optional_param('code', '', PARAM_CLEAN);

// This page should no longer be used. Grab company parameters and redirect to core login page.
$redirecturl = new moodle_url($CFG->wwwroot . '/login/index.php', ['id' => $wantedcompanyid, 'code' => $wantedcompanyshort]);
redirect($redirecturl);

die;
