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
 * Plugin administration pages are defined here.
 *
 * @package     local_leeloolxpapi
 * @category    admin
 * @copyright   2020 Leeloo LXP <info@leeloolxp.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once(dirname(dirname(__DIR__)) . '/config.php');

/**
 * Function to get Leeloo Install
 *
 * @return string leeloo url
 */
function local_leeloolxpapi_get_leelooinstall() {

    global $SESSION;

    if (isset($SESSION->apileelooinstall)) {
        return $SESSION->apileelooinstall;
    }

    global $CFG;
    require_once($CFG->dirroot . '/lib/filelib.php');

    $configweblogintrack = get_config('local_leeloolxpapi');
    $liacnsekey = $configweblogintrack->gradelicensekey;
    $postdata = array('license_key' => $liacnsekey);
    $url = 'https://leeloolxp.com/api_moodle.php/?action=page_info';
    $curl = new curl;
    $options = array(
        'CURLOPT_RETURNTRANSFER' => true,
        'CURLOPT_HEADER' => false,
        'CURLOPT_POST' => count($postdata),
    );

    if (!$output = $curl->post($url, $postdata, $options)) {
        $leelooinstallurl = 'no';
        $SESSION->apileelooinstall = $leelooinstallurl;
    }

    $infoteamnio = json_decode($output);
    if ($infoteamnio->status != 'false') {
        $leelooinstallurl = $infoteamnio->data->install_url;
        $SESSION->apileelooinstall = $leelooinstallurl;
    } else {
        $leelooinstallurl = 'no';
        $SESSION->apileelooinstall = $leelooinstallurl;
    }

    $allroles = get_all_roles();
    $roles = json_encode($allroles);

    $postdata = '&roles=' . $roles;
    $url = $leelooinstallurl . '/admin/sync_moodle_course/sync_moodle_roles/';
    $curl = new curl;
    $options = array(
        'CURLOPT_RETURNTRANSFER' => true,
        'CURLOPT_HEADER' => false,
        'CURLOPT_POST' => 1,
    );

    $output = $curl->post($url, $postdata, $options);

    return $leelooinstallurl;
}

/**
 * Plugin to sync user's tracking on activity to LeelooLXP account of the Moodle Admin
 */
function local_leeloolxpapi_before_footer() {

    $teamniourl = local_leeloolxpapi_get_leelooinstall();

    if ($teamniourl == 'no') {
        return true;
    }

    global $USER;
    global $PAGE;
    global $CFG;
    global $DB;
    $useremail = $USER->email;

    $PAGE->requires->jquery();
    if (
        $PAGE->pagetype == 'admin-setting-gradessettings' ||
        $PAGE->pagetype == 'admin-setting-gradecategorysettings' ||
        $PAGE->pagetype == 'admin-setting-gradeitemsettings' ||
        $PAGE->pagetype == 'admin-grade-edit-scale-edit' ||
        $PAGE->pagetype == 'grade-edit-scale-edit' ||
        $PAGE->pagetype == 'admin-grade-edit-letter-index' ||
        $PAGE->pagetype == 'grade-edit-letter-index' ||
        $PAGE->pagetype == 'admin-setting-gradereportgrader' ||
        $PAGE->pagetype == 'admin-setting-gradereporthistory' ||
        $PAGE->pagetype == 'admin-setting-gradereportoverview' ||
        $PAGE->pagetype == 'admin-setting-gradereportuser' ||
        $PAGE->pagetype == 'grade-edit-settings-index' ||
        $PAGE->pagetype == 'grade-report-grader-preferences' ||
        $PAGE->pagetype == 'admin-grade-edit-scale-index' ||
        $PAGE->pagetype == 'grade-edit-scale-index' ||
        $PAGE->pagetype == 'grade-edit-tree-index' ||
        $PAGE->pagetype == 'grade-edit-tree-category' ||
        $PAGE->pagetype == 'mod-workshop-submission' ||
        $PAGE->pagetype == 'course-togglecompletion'
    ) {
        if ($CFG->dbtype == 'mysqli') {
            $tablecat = $CFG->prefix . 'scale';
            $sql = " SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$CFG->dbname' AND TABLE_NAME = '$tablecat' ";
            $autoinc = $DB->get_record_sql($sql);
            $autoincrement = $autoinc->auto_increment;

            $tablecat = $CFG->prefix . 'course_completions';
            $sql = " SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$CFG->dbname' AND TABLE_NAME = '$tablecat' ";
            $autoinc = $DB->get_record_sql($sql);
            $autoincrementcoursecompletions = $autoinc->auto_increment;
        } else {
            if (
                $PAGE->pagetype == 'course-togglecompletion'
            ) {
                $autoincrement = 55290;

                $tablecat = $CFG->prefix . 'course_completions';
                $sql = "SELECT nextval(pg_get_serial_sequence('$tablecat', 'id')) AS auto_increment;";
                $autoinc = $DB->get_record_sql($sql);
                $autoincrementcoursecompletionsless = $autoinc->auto_increment;

                $autoincrementcoursecompletions = $autoincrementcoursecompletionsless + 1;
            } else if (
                $PAGE->pagetype == 'admin-grade-edit-scale-edit' ||
                $PAGE->pagetype == 'grade-edit-scale-edit'
            ) {
                $tablecat = $CFG->prefix . 'scale';
                $sql = "SELECT nextval(pg_get_serial_sequence('$tablecat', 'id')) AS auto_increment;";
                $autoinc = $DB->get_record_sql($sql);
                $autoincrementless = $autoinc->auto_increment;
                $autoincrement = $autoincrementless + 1;

                $autoincrementcoursecompletions = 55290;
            } else {
                $autoincrement = 55290;
                $autoincrementcoursecompletions = 55290;
            }
        }

        $modulerecords = $DB->get_record_sql("SELECT MAX(id) as max_id FROM {scale}");
        if (!empty($modulerecords)) {
            $scalemaxid = $modulerecords->max_id;
        } else {
            $scalemaxid = 0;
        }

        $workshopgardearsyncid = 0;

        if ($PAGE->pagetype == 'mod-workshop-submission') {
            $idid = $_REQUEST['id'];

            $maindata = $DB->get_record_sql("SELECT wg.id FROM {workshop_grades}

            wg JOIN {workshop_assessments} wa  ON wa.id = wg.assessmentid

            WHERE wa.submissionid = ?", [$idid]);

            if (!empty($maindata) && !empty($maindata->id)) {
                $workshopgardearsyncid = $maindata->id;
            }
        }

        $PAGE->requires->jquery();

        $PAGE->requires->js(new moodle_url('/local/leeloolxpapi/js/gradesync.js'));

        echo '<input type="hidden" id="local_leeloolxpapi_workshopgardearsyncid" value="' . base64_encode($workshopgardearsyncid) . '"/>';
        echo '<input type="hidden" id="local_leeloolxpapi_teamniourl" value="' . base64_encode($teamniourl) . '"/>';
        echo '<input type="hidden" id="local_leeloolxpapi_email" value="' . base64_encode($useremail) . '"/>';
        echo '<input type="hidden" id="local_leeloolxpapi_auto_increment_course_completions" value="' . base64_encode($autoincrementcoursecompletions) . '"/>';
        echo '<input type="hidden" id="local_leeloolxpapi_scale_max_id" value="' . base64_encode($scalemaxid) . '"/>';
        echo '<input type="hidden" id="local_leeloolxpapi_auto_increment" value="' . base64_encode($autoincrement) . '"/>';
        echo '<input type="hidden" id="local_leeloolxpapi_course_id" value="' . base64_encode($PAGE->course->id) . '"/>';
    }
}
