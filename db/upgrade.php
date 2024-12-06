<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin upgrade.php
 *
 * @package     format_ludimoodle
 * @copyright   2023 Pimenko <support@pimenko.com><pimenko.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\task\manager;
use format_ludimoodle\local\gameelements\game_element;
use format_ludimoodle\local\gameelements\score;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/format/lib.php');

function xmldb_format_ludimoodle_upgrade($oldversion = 0) {

    global $DB;

    // Automatic 'Purge all caches'....
    if ($oldversion < 2023041801) {
        $cmrecords = $DB->get_records_sql("SELECT cmid
            FROM (
                SELECT cmid FROM {ludimoodle_cm_params}
                UNION
                SELECT cmid FROM {ludimoodle_cm_user}
            ) AS unique_cmid");

        foreach ($cmrecords as $cmrecord) {
            if (!$DB->get_record_sql("SELECT cm.*, md.name as modname
                               FROM {course_modules} cm,
                                    {modules} md
                               WHERE cm.id = $cmrecord->cmid AND
                                     md.id = cm.module")) {
                $DB->delete_records('ludimoodle_cm_params', ['cmid' => $cmrecord->cmid]);
                $DB->delete_records('ludimoodle_cm_user', ['cmid' => $cmrecord->cmid]);
            }
        }
        upgrade_plugin_savepoint(true, 2023041801, 'format', 'ludimoodle');
    }
    if ($oldversion < 2023052602) {
        // Add new value to cm parameters.
        $sql = 'SELECT id, gameelementid, cmid FROM {ludimoodle_cm_params} GROUP BY gameelementid, cmid';
        $cmparams = $DB->get_records_sql($sql);
        foreach ($cmparams as $cmparam) {
            $cm = $DB->get_record('course_modules', ['id' => $cmparam->cmid]);
            $modetype = $DB->get_field('modules', 'name', ['id' => $cm->module]);
            $condition = game_element::get_cm_parameters_default($modetype)['condition'];
            $DB->insert_record('ludimoodle_cm_params', [
                'gameelementid' => $cmparam->gameelementid,
                'cmid' => $cmparam->cmid,
                'name' => 'condition',
                'value' => $condition,
            ]);
        }
        upgrade_plugin_savepoint(true, 2023052602, 'format', 'ludimoodle');
    }
    if ($oldversion < 2023102400) {
        $reqcourses = 'SELECT DISTINCT courseid FROM {ludimoodle_gameelements}';
        $courses = $DB->get_records_sql($reqcourses);

        foreach ($courses as $course) {
            $format = course_get_format($course->courseid);

            // Get the format options.
            $options = $format->get_format_options();

            $manager = new \format_ludimoodle\manager();
            game_element::create_all_for_course($course->courseid);

            // Sync user attribution.
            $manager->sync_user_attribution($course->courseid, $options['assignment'], $options['default_game_element'], false);
        }
        upgrade_plugin_savepoint(true, 2023102400, 'format', 'ludimoodle');
    }
    if ($oldversion < 2023111300) {
        // Update questionnary
        $question = new stdClass();
        $question->id = 29;
        $question->content = 'Cela me rend heureux de pouvoir aider les autres';
        $question->label = 'Philanthropist';
        $DB->update_record('ludimoodle_questions', $question);
        $question->id = 30;
        $question->content = 'J\'apprécie les activités de groupe';
        $question->label = 'Socialiser';
        $DB->update_record('ludimoodle_questions', $question);
        $question->id = 31;
        $question->content = 'Le bien-être des autres m\'est important';
        $question->label = 'Philanthropist';
        $DB->update_record('ludimoodle_questions', $question);
        $question->id = 32;
        $question->content = 'J\'aime faire partie d\'une équipe';
        $question->label = 'Socialiser';
        $DB->update_record('ludimoodle_questions', $question);
        $question->id = 33;
        $question->content = 'J\'aime gérer des tâches difficiles';
        $question->label = 'Achiever';
        $DB->update_record('ludimoodle_questions', $question);
        $question->id = 34;
        $question->content = 'J\'aime sortir victorieux de circonstances difficiles';
        $question->label = 'Achiever';
        $DB->update_record('ludimoodle_questions', $question);
        $question->id = 35;
        $question->content = 'Être indépendant est une chose importante pour moi';
        $question->label = 'Free Spirit';
        $DB->update_record('ludimoodle_questions', $question);
        $question->id = 36;
        $question->content = 'Je n\'aime pas suivre les règles';
        $question->label = 'Disruptor';
        $DB->update_record('ludimoodle_questions', $question);
        $question->id = 37;
        $question->content = 'Si la récompense est suffisante, je ferai des efforts';
        $question->label = 'Player';
        $DB->update_record('ludimoodle_questions', $question);
        $question->id = 38;
        $question->content = 'Il est important pour moi de suivre ma propre voie';
        $question->label = 'Free Spirit';
        $DB->update_record('ludimoodle_questions', $question);
        $question->id = 39;
        $question->content = 'Je me perçois comme étant rebelle';
        $question->label = 'Disruptor';
        $DB->update_record('ludimoodle_questions', $question);
        $question->id = 40;
        $question->content = 'Les récompenses sont un bon moyen de me motiver';
        $question->label = 'Player';
        $DB->update_record('ludimoodle_questions', $question);

        // Delete question with id > 40
        $deletesql = 'DELETE FROM {ludimoodle_questions} WHERE id > 40';
        $DB->execute($deletesql);
    }
    if ($oldversion < 2023112100) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('ludimoodle_log');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('timestamp', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, null, null);
        $table->add_field('user', XMLDB_TYPE_CHAR, '255', null,
            XMLDB_NOTNULL, null, null);
        $table->add_field('gameelement', XMLDB_TYPE_CHAR, '255', null,
            XMLDB_NOTNULL, null, null);
        $table->add_field('action', XMLDB_TYPE_CHAR, '255', null,
            XMLDB_NOTNULL, null, null);
        $table->add_field('info', XMLDB_TYPE_TEXT, null, null,
            null, null, null);
        $table->add_field('info2', XMLDB_TYPE_TEXT, null, null,
            null, null, null);
        $table->add_field('info3', XMLDB_TYPE_TEXT, null, null,
            null, null, null);
        $table->add_field('info4', XMLDB_TYPE_TEXT, null, null,
            null, null, null);
        $table->add_field('info5', XMLDB_TYPE_TEXT, null, null,
            null, null, null);
        $table->add_field('info6', XMLDB_TYPE_TEXT, null, null,
            null, null, null);
        $table->add_field('info7', XMLDB_TYPE_TEXT, null, null,
            null, null, null);

        // Adding keys to table ludimoodle_questions.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        try {
            if (!$dbman->table_exists($table)) {
                $dbman->create_table($table);
            }
        } catch (ddl_exception $e) {
            return false;
        }

        upgrade_plugin_savepoint(true, 2023112100, 'format', 'ludimoodle');
    }
    if ($oldversion < 2024052702) {

        // Update max score because of the new score game element working.
        // Retrieve all score game element.
        $scoregameelements = $DB->get_records('ludimoodle_gameelements', ['type' => 'score']);
        foreach ($scoregameelements as $scoregameelement) {
            // Retrieve all max score parameters.
            $parameters = $DB->get_records('ludimoodle_cm_params',
                ['gameelementid' => $scoregameelement->id, 'name' => 'maxscore']);
            foreach ($parameters as $parameter) {
                $coursemodule = $DB->get_record('course_modules', ['id' => $parameter->cmid]);

                // Retrieve course module type.
                $module = $DB->get_record('modules', ['id' => $coursemodule->module]);

                // And get default parameters.
                // In this function an array with the property max score is updated.
                $defaultparameters = score::get_cm_parameters_default($module->name, $coursemodule->id);
                $parameter->value = $defaultparameters['maxscore'];
                // Update maxscore.
                $DB->update_record('ludimoodle_cm_params', $parameter);
            }
        }
        upgrade_plugin_savepoint(true, 2024052702, 'format', 'ludimoodle');
    }
    if ($oldversion < 2024081900) {
        // Remove all AMS questions from questionnary.
        $DB->delete_records('ludimoodle_questions', ['type' => 'AMS']);
    }

    if ($oldversion < 2024081902) {
        // Set the new setting world to all courses with ludimoodle.
        $courses = $DB->get_records('course', ['format' => 'ludimoodle']);
        foreach ($courses as $course) {
            $format = course_get_format($course->id);
            $data = $format->get_format_options();
            $data['world'] = 'school';
            $format->update_course_format_options($data);
        }
    }

    if ($oldversion < 2024090600) {
        // Remove all course modules without URL from gamelements.
        // Get all courses with ludimoodle format and all course modules.
        $courses = $DB->get_records('course', ['format' => 'ludimoodle']);
        foreach ($courses as $course) {
            $cms = $DB->get_records('course_modules', ['course' => $course->id]);
            foreach ($cms as $cm) {
                $cminfo = get_fast_modinfo($course->id)->get_cm($cm->id);
                // Check if the course module has a URL.
                if (!$cminfo->get_url()) {
                    // Remove all cms without URL.
                    $DB->delete_records('ludimoodle_cm_params', ['cmid' => $cm->id]);
                    $DB->delete_records('ludimoodle_cm_user', ['cmid' => $cm->id]);
                }
            }
        }
    }
    if ($oldversion < 2024101400) {
        // Add new table ludimoodle_bysection.
        $dbman = $DB->get_manager();
        $table = new xmldb_table('ludimoodle_bysection');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, null, null);
        $table->add_field('sectionid', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, null, null);
        $table->add_field('gameelementid', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, null, null);

        // Adding keys to table ludimoodle_bysection.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_key('sectionid', XMLDB_KEY_FOREIGN, ['sectionid'], 'course_sections', ['id']);
        $table->add_key('gameelementid', XMLDB_KEY_FOREIGN, ['gameelementid'], 'ludimoodle_gameelements',
            ['id']);

        // Create table.
        try {
            if (!$dbman->table_exists($table)) {
                $dbman->create_table($table);
            }
        } catch (ddl_exception $e) {
            return false;
        }
        upgrade_plugin_savepoint(true, 2024101400, 'format', 'ludimoodle');
    }
    if ($oldversion < 2024102400) {
        // Replaces questions texts by string identifier in the database.
        $compare = $DB->sql_compare_text('content', 255);
        $sql = 'SELECT * FROM {ludimoodle_questions} WHERE ' . $compare . ' = :content';

        // Question 1.
        $content = 'Cela me rend heureux de pouvoir aider les autres';
        $question = $DB->get_record_sql($sql, ['content' => $content]);
        $question->content = 'questionnaire:question1';
        $DB->update_record('ludimoodle_questions', $question);

        // Question 2.
        $content = 'J\'apprécie les activités de groupe';
        $question = $DB->get_record_sql($sql, ['content' => $content]);
        $question->content = 'questionnaire:question2';
        $DB->update_record('ludimoodle_questions', $question);

        // Question 3.
        $content = 'Le bien-être des autres m\'est important';
        $question = $DB->get_record_sql($sql, ['content' => $content]);
        $question->content = 'questionnaire:question3';
        $DB->update_record('ludimoodle_questions', $question);

        // Question 4.
        $content = 'J\'aime faire partie d\'une équipe';
        $question = $DB->get_record_sql($sql, ['content' => $content]);
        $question->content = 'questionnaire:question4';
        $DB->update_record('ludimoodle_questions', $question);

        // Question 5.
        $content = 'J\'aime gérer des tâches difficiles';
        $question = $DB->get_record_sql($sql, ['content' => $content]);
        $question->content = 'questionnaire:question5';
        $DB->update_record('ludimoodle_questions', $question);

        // Question 6.
        $content = 'J\'aime sortir victorieux de circonstances difficiles';
        $question = $DB->get_record_sql($sql, ['content' => $content]);
        $question->content = 'questionnaire:question6';
        $DB->update_record('ludimoodle_questions', $question);

        // Question 7.
        $content = 'Être indépendant est une chose importante pour moi';
        $question = $DB->get_record_sql($sql, ['content' => $content]);
        $question->content = 'questionnaire:question7';
        $DB->update_record('ludimoodle_questions', $question);

        // Question 8.
        $content = 'Je n\'aime pas suivre les règles';
        $question = $DB->get_record_sql($sql, ['content' => $content]);
        $question->content = 'questionnaire:question8';
        $DB->update_record('ludimoodle_questions', $question);

        // Question 9.
        $content = 'Si la récompense est suffisante, je ferai des efforts';
        $question = $DB->get_record_sql($sql, ['content' => $content]);
        $question->content = 'questionnaire:question9';
        $DB->update_record('ludimoodle_questions', $question);

        // Question 10.
        $content = 'Il est important pour moi de suivre ma propre voie';
        $question = $DB->get_record_sql($sql, ['content' => $content]);
        $question->content = 'questionnaire:question10';
        $DB->update_record('ludimoodle_questions', $question);

        // Question 11.
        $content = 'Je me perçois comme étant rebelle';
        $question = $DB->get_record_sql($sql, ['content' => $content]);
        $question->content = 'questionnaire:question11';
        $DB->update_record('ludimoodle_questions', $question);

        // Question 12.
        $content = 'Les récompenses sont un bon moyen de me motiver';
        $question = $DB->get_record_sql($sql, ['content' => $content]);
        $question->content = 'questionnaire:question12';
        $DB->update_record('ludimoodle_questions', $question);

        upgrade_plugin_savepoint(true, 2024102400, 'format', 'ludimoodle');
    }
    purge_all_caches();
    return true;
}
