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

$string['pluginname'] = 'IOMAD completion tracking';
$string['privacy:metadata'] = 'The \'Local IOMAD completion tracking\' plugin only shows data stored in other locations.';
$string['privacy:metadata:local_iomad_track:id'] = 'Local IOMAD track ID';
$string['privacy:metadata:local_iomad_track:courseid'] = 'Course ID';
$string['privacy:metadata:local_iomad_track:coursename'] = 'Course name.';
$string['privacy:metadata:local_iomad_track:userid'] = 'User ID';
$string['privacy:metadata:local_iomad_track:companyid'] = 'User company ID';
$string['privacy:metadata:local_iomad_track:timecompleted'] = 'Course time completed';
$string['privacy:metadata:local_iomad_track:timeenroled'] = 'Course time enroled';
$string['privacy:metadata:local_iomad_track:timestarted'] = 'Course time started';
$string['privacy:metadata:local_iomad_track:finalscore'] = 'Course final score';
$string['privacy:metadata:local_iomad_track:licenseid'] = 'Licese ID';
$string['privacy:metadata:local_iomad_track:licensename'] = 'License name';
$string['privacy:metadata:local_iomad_track:licenseallocated'] = 'Unix timestamp of time license was allocated';
$string['privacy:metadata:local_iomad_track:modifiedtime'] = 'Record modified time';
$string['privacy:metadata:local_iomad_track'] = 'Local IOMAD track user information';
$string['privacy:metadata:local_iomad_track_certs:id'] = 'Local IOMAD track certificate record ID';
$string['privacy:metadata:local_iomad_track_certs:trackid'] = 'Certificate track ID';
$string['privacy:metadata:local_iomad_track_certs:filename'] = 'Certificate filename';
$string['privacy:metadata:local_iomad_track_certs'] = 'Local iomad track certificate info';
$string['fixtracklicensetask'] = 'IOMAD track fix license tracking details ad-hoc task';
$string['iomad_track:importfrommoodle'] = 'Import completion information from Moodle tables';
$string['importcompletionsfrommoodle'] = 'Import stored completion information from Moodle tables';
$string['importcompletionsfrommoodlefull'] = 'This will run an ad-hoc task to import all of the completion information from Moodle to the IOMAD reporting tables.';
$string['importcompletionsfrommoodlefullwitherrors'] = 'This will run an ad-hoc task to import SOME of the completion information from Moodle to the IOMAD reporting tables. Not all courses have completion enabled or criteria set up and their information will be missed out. If you want to know which courses these are, use the check link on the previous page.';
$string['importmoodlecompletioninformation'] = 'Ad-hoc task to import completion information from Moodle tables';
$string['fixenrolleddatetask'] = 'Ad-hoc task to update the stored completion information to use the enrolment \'timecreated\' timestamp where this is not already set.';
$string['fixcourseclearedtask'] = 'Ad-hoc task to update the \'coursecleared\' field in the stored completion records';
$string['fixtracklicensetask'] = 'Ad-hoc task to fix stored records license information';
$string['importcompletionrecords'] = 'Import completion records';
$string['uploadcompletionresult'] = 'Upload completion file result';
$string['completionimportfromfile'] = 'Completion import from file';
$string['importcompletionsfromfile'] = 'Import completion information from file';
$string['courseswithoutcompletionenabledcouunt'] = 'Number of courses which do not have completion enabled = {$a}';
$string['courseswithoutcompletioncriteriacouunt'] ='Number of courses which have no completion criteria = {$a}';
$string['checkcoursestatusmoodle'] = 'Check course settings for import';
