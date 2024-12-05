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
 * @package   block_iomad_microlearning
 * @copyright 2021 Derick Turner
 * @author    Derick Turner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * As of the implementation of this block and the general navigation code
 * in Moodle 2.0 the body of immediate upgrade work for this block and
 * settings is done in core upgrade {@see lib/db/upgrade.php}
 *
 * There were several reasons that they were put there and not here, both becuase
 * the process for the two blocks was very similar and because the upgrade process
 * was complex due to us wanting to remvoe the outmoded blocks that this
 * block was going to replace.
 *
 * @global moodle_database $DB
 * @param int $oldversion
 * @param object $block
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_block_iomad_microlearning_upgrade($oldversion) {
    global $CFG, $DB;

    $result = true;
    $dbman = $DB->get_manager();

    if ($oldversion < 2019101400) {

        // Define field url to be added to microlearning_nugget.
        $table = new xmldb_table('microlearning_nugget');
        $field = new xmldb_field('url', XMLDB_TYPE_TEXT, null, null, null, null, null, 'cmid');

        // Conditionally launch add field url.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Iomad_microlearning savepoint reached.
        upgrade_block_savepoint(true, 2019101400, 'iomad_microlearning');
    }

    if ($oldversion < 2019120800) {

        // Rename field releaseinterval on table microlearning_thread to releaseinterval.
        $table = new xmldb_table('microlearning_thread');
        $field = new xmldb_field('interval', XMLDB_TYPE_INTEGER, '20', null, null, null, '0', 'timecreated');

        // Conditionally launch add field interval.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Launch rename field releaseinterval.
        $dbman->rename_field($table, $field, 'releaseinterval');

        // Changing precision of field accesskey on table microlearning_thread_user to (240).
        $table = new xmldb_table('microlearning_thread_user');
        $field = new xmldb_field('accesskey', XMLDB_TYPE_CHAR, '240', null, XMLDB_NOTNULL, null, null, 'timecompleted');

        // Launch change of precision for field accesskey.
        $dbman->change_field_precision($table, $field);


        // Iomad_microlearning savepoint reached.
        upgrade_block_savepoint(true, 2019120800, 'iomad_microlearning');
    }

    if ($oldversion < 2021102500) {

        // Define table microlearning_thread_group to be created.
        $table = new xmldb_table('microlearning_thread_group');

        // Adding fields to table microlearning_thread_group.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('threadid', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('companyid', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table microlearning_thread_group.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for microlearning_thread_group.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define field groupid to be added to microlearning_thread_user.
        $table = new xmldb_table('microlearning_thread_user');
        $field = new xmldb_field('groupid', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, '0', 'nuggetid');

        // Conditionally launch add field groupid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Iomad_microlearning savepoint reached.
        upgrade_block_savepoint(true, 2021102500, 'iomad_microlearning');
    }

    if ($oldversion < 2024103000) {

        // Rename field remainder1 on table microlearning_thread to reminder1.
        $table = new xmldb_table('microlearning_thread');
        $field = new xmldb_field('remainder1', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'halt_until_fulfilled');

        // Conditionally launch rename field reminder1.
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'reminder1');
        }

        // Rename field remainder2 on table microlearning_thread to reminder2.
        $table = new xmldb_table('microlearning_thread');
        $field = new xmldb_field('remainder2', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'reminder1');

        // Conditionally launch rename field reminder2.
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'reminder2');
        }

        // Changing type of field send_message on table microlearning_thread to int.
        $table = new xmldb_table('microlearning_thread');
        $field = new xmldb_field('send_message', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null, 'name');

        // Launch change of type for field send_message.
        $dbman->change_field_type($table, $field);

        // Changing type of field send_reminder on table microlearning_thread to int.
        $table = new xmldb_table('microlearning_thread');
        $field = new xmldb_field('send_reminder', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null, 'message_time');

        // Launch change of type for field send_reminder.
        $dbman->change_field_type($table, $field);

        // Changing type of field halt_until_fulfilled on table microlearning_thread to int.
        $table = new xmldb_table('microlearning_thread');
        $field = new xmldb_field('halt_until_fulfilled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null, 'send_reminder');

        // Launch change of type for field halt_until_fulfilled.
        $dbman->change_field_type($table, $field);

        // Changing type of field active on table microlearning_thread to int.
        $table = new xmldb_table('microlearning_thread');
        $field = new xmldb_field('active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null, 'reminder2');

        // Launch change of type for field active.
        $dbman->change_field_type($table, $field);

        // Define field halt_until_fulfilled to be added to microlearning_nugget.
        $table = new xmldb_table('microlearning_nugget');
        $field = new xmldb_field('halt_until_fulfilled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null, 'cmid');

        // Launch change of type for field halt_until_fulfilled.
        $dbman->change_field_type($table, $field);

        // Changing type of field message_delivered on table microlearning_thread_user to int.
        $table = new xmldb_table('microlearning_thread_user');
        $field = new xmldb_field('message_delivered', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null, 'message_time');

        // Launch change of type for field message_delivered.
        $dbman->change_field_type($table, $field);

        // Changing type of field reminder1_delivered on table microlearning_thread_user to int.
        $table = new xmldb_table('microlearning_thread_user');
        $field = new xmldb_field('reminder1_delivered', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null, 'message_delivered');

        // Launch change of type for field reminder1_delivered.
        $dbman->change_field_type($table, $field);

        // Changing type of field reminder2_delivered on table microlearning_thread_user to int.
        $table = new xmldb_table('microlearning_thread_user');
        $field = new xmldb_field('reminder2_delivered', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null, 'reminder1_delivered');

        // Launch change of type for field reminder2_delivered.
        $dbman->change_field_type($table, $field);

        // Iomad_microlearning savepoint reached.
        upgrade_block_savepoint(true, 2024103000, 'iomad_microlearning');
    }

    return $result;
}
