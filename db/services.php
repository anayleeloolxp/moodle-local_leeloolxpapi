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
 * External functions and service definitions.
 *
 * @package local_leeloolxpapi
 * @copyright  2020 Leeloo LXP (https://leeloolxp.com)
 * @author Leeloo LXP <info@leeloolxp.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// We defined the web service functions to install.
$functions = array(
    'local_leeloolxpapi_course_sync' => array(
        'classname' => 'local_leeloolxpapi_external',
        'methodname' => 'course_sync',
        'classpath' => 'local/leeloolxpapi/externallib.php',
        'description' => 'Sync course from Leeloo to Moodle.',
        'type' => 'write',
    ),
    'local_leeloolxpapi_ar_sync' => array(
        'classname' => 'local_leeloolxpapi_external',
        'methodname' => 'ar_sync',
        'classpath' => 'local/leeloolxpapi/externallib.php',
        'description' => 'Sync A/R from Leeloo to Moodle.',
        'type' => 'write',
    ),
    'local_leeloolxpapi_standard_tag_sync' => array(
        'classname' => 'local_leeloolxpapi_external',
        'methodname' => 'standard_tag_sync',
        'classpath' => 'local/leeloolxpapi/externallib.php',
        'description' => 'Sync Standard Tags from Leeloo to Moodle.',
        'type' => 'write',
    ),
    'local_leeloolxpapi_delete_tag' => array(
        'classname' => 'local_leeloolxpapi_external',
        'methodname' => 'delete_tag',
        'classpath' => 'local/leeloolxpapi/externallib.php',
        'description' => 'Delete Tags from Leeloo to Moodle.',
        'type' => 'write',
    ),

    'local_leeloolxpapi_original_tag' => array(
        'classname' => 'local_leeloolxpapi_external',
        'methodname' => 'original_tag',
        'classpath' => 'local/leeloolxpapi/externallib.php',
        'description' => 'Original Tags from Leeloo to Moodle.',
        'type' => 'write',
    ),

    'local_leeloolxpapi_updated_tag_flag_standard' => array(
        'classname' => 'local_leeloolxpapi_external',
        'methodname' => 'updated_tag_flag_standard',
        'classpath' => 'local/leeloolxpapi/externallib.php',
        'description' => 'Update Tag flags from Leeloo to Moodle.',
        'type' => 'write',
    ),

    'local_leeloolxpapi_combine_tags_data' => array(
        'classname' => 'local_leeloolxpapi_external',
        'methodname' => 'combine_tags_data',
        'classpath' => 'local/leeloolxpapi/externallib.php',
        'description' => 'Combine Tags from Leeloo to Moodle.',
        'type' => 'write',
    ),

    'local_leeloolxpapi_categories_data_delete' => array(
        'classname' => 'local_leeloolxpapi_external',
        'methodname' => 'categories_data_delete',
        'classpath' => 'local/leeloolxpapi/externallib.php',
        'description' => 'Delete category from leeloo to moodle.',
        'type' => 'write',
    ),

    'local_leeloolxpapi_categories_data_sync' => array(
        'classname' => 'local_leeloolxpapi_external',
        'methodname' => 'categories_data_sync',
        'classpath' => 'local/leeloolxpapi/externallib.php',
        'description' => 'Sync categories from leeloo to moodle.',
        'type' => 'write',
    ),

    'local_leeloolxpapi_get_moodle_user_id' => array(
        'classname' => 'local_leeloolxpapi_external',
        'methodname' => 'get_moodle_user_id',
        'classpath' => 'local/leeloolxpapi/externallib.php',
        'description' => 'Get Moodle user id.',
        'type' => 'read',
    ),

    'local_leeloolxpapi_get_moodle_user' => array(
        'classname' => 'local_leeloolxpapi_external',
        'methodname' => 'get_moodle_user',
        'classpath' => 'local/leeloolxpapi/externallib.php',
        'description' => 'Get Moodle user.',
        'type' => 'read',
    ),

    'local_leeloolxpapi_gradeletter1' => array(
        'classname' => 'local_leeloolxpapi_external',
        'methodname' => 'gradeletter1',
        'classpath' => 'local/leeloolxpapi/externallib.php',
        'description' => 'Get Grade Letter.',
        'type' => 'write',
    ),

    'local_leeloolxpapi_get_userid' => array(
        'classname' => 'local_leeloolxpapi_external',
        'methodname' => 'get_userid',
        'classpath' => 'local/leeloolxpapi/externallib.php',
        'description' => 'Get user id.',
        'type' => 'read',
    ),

    'local_leeloolxpapi_leelo_activity_data' => array(
        'classname' => 'local_leeloolxpapi_external',
        'methodname' => 'leelo_activity_data',
        'classpath' => 'local/leeloolxpapi/externallib.php',
        'description' => 'Sync leeloo activity data.',
        'type' => 'write',
    ),

    'local_leeloolxpapi_leelo_data' => array(
        'classname' => 'local_leeloolxpapi_external',
        'methodname' => 'leelo_data',
        'classpath' => 'local/leeloolxpapi/externallib.php',
        'description' => 'Sync leeloo data.',
        'type' => 'write',
    ),

    // grades sync

    'local_leeloolxpapi_sync_grade_letter' => array(
        'classname' => 'local_leeloolxpapi_external',
        'methodname' => 'sync_grade_letter',
        'classpath' => 'local/leeloolxpapi/externallib.php',
        'description' => 'Sync Grade Letters.',
        'type' => 'write',
    ),

    'local_leeloolxpapi_sync_course_grade_settings' => array(
        'classname' => 'local_leeloolxpapi_external',
        'methodname' => 'sync_course_grade_settings',
        'classpath' => 'local/leeloolxpapi/externallib.php',
        'description' => 'Sync Course Grade Settings.',
        'type' => 'write',
    ),

    'local_leeloolxpapi_sync_prefrence_grader_report' => array(
        'classname' => 'local_leeloolxpapi_external',
        'methodname' => 'sync_prefrence_grader_report',
        'classpath' => 'local/leeloolxpapi/externallib.php',
        'description' => 'Sync User Preference Grade report.',
        'type' => 'write',
    ),

    'local_leeloolxpapi_sync_scales' => array(
        'classname' => 'local_leeloolxpapi_external',
        'methodname' => 'sync_scales',
        'classpath' => 'local/leeloolxpapi/externallib.php',
        'description' => 'Sync scales from leeloo to moodle.',
        'type' => 'write',
    ),

    'local_leeloolxpapi_categories_data_grades' => array(
        'classname' => 'local_leeloolxpapi_external',
        'methodname' => 'categories_data_grades',
        'classpath' => 'local/leeloolxpapi/externallib.php',
        'description' => 'Sync Grade items and Category.',
        'type' => 'write',
    ),

    'local_leeloolxpapi_delete_grade_item' => array(
        'classname' => 'local_leeloolxpapi_external',
        'methodname' => 'delete_grade_item',
        'classpath' => 'local/leeloolxpapi/externallib.php',
        'description' => 'Delete grade item.',
        'type' => 'write',
    ),

    'local_leeloolxpapi_grade_hidden_data' => array(
        'classname' => 'local_leeloolxpapi_external',
        'methodname' => 'grade_hidden_data',
        'classpath' => 'local/leeloolxpapi/externallib.php',
        'description' => 'Hide/Show grade item and category.',
        'type' => 'write',
    ),

    'local_leeloolxpapi_grade_duplicate_data' => array(
        'classname' => 'local_leeloolxpapi_external',
        'methodname' => 'grade_duplicate_data',
        'classpath' => 'local/leeloolxpapi/externallib.php',
        'description' => 'Duplicate grade item.',
        'type' => 'write',
    ),

    'local_leeloolxpapi_gradeitem_order_change_data' => array(
        'classname' => 'local_leeloolxpapi_external',
        'methodname' => 'gradeitem_order_change_data',
        'classpath' => 'local/leeloolxpapi/externallib.php',
        'description' => 'Order grade item.',
        'type' => 'write',
    ),

    'local_leeloolxpapi_delete_grade_category' => array(
        'classname' => 'local_leeloolxpapi_external',
        'methodname' => 'delete_grade_category',
        'classpath' => 'local/leeloolxpapi/externallib.php',
        'description' => 'Delete grade category.',
        'type' => 'write',
    ),
);

// We define the services to install as pre-build services. A pre-build service is not editable by administrator.
$services = array(
    'Leeloo LXP API' => array(
        'functions' => array(
            'local_leeloolxpapi_course_sync',
            'local_leeloolxpapi_ar_sync',
            'local_leeloolxpapi_standard_tag_sync',
            'local_leeloolxpapi_delete_tag',
            'local_leeloolxpapi_original_tag',
            'local_leeloolxpapi_updated_tag_flag_standard',
            'local_leeloolxpapi_combine_tags_data',
            'local_leeloolxpapi_categories_data_delete',
            'local_leeloolxpapi_categories_data_sync',
            'local_leeloolxpapi_get_moodle_user_id',
            'local_leeloolxpapi_get_moodle_user',
            'local_leeloolxpapi_gradeletter1',
            'local_leeloolxpapi_get_userid',
            'local_leeloolxpapi_leelo_activity_data',
            'local_leeloolxpapi_leelo_data',
            'local_leeloolxpapi_sync_grade_letter',
            'local_leeloolxpapi_sync_course_grade_settings',
            'local_leeloolxpapi_sync_prefrence_grader_report',
            'local_leeloolxpapi_sync_scales',
            'local_leeloolxpapi_categories_data_grades',
            'local_leeloolxpapi_delete_grade_item',
            'local_leeloolxpapi_grade_hidden_data',
            'local_leeloolxpapi_grade_duplicate_data',
            'local_leeloolxpapi_gradeitem_order_change_data',
            'local_leeloolxpapi_delete_grade_category',

        ),
        'restrictedusers' => 0,
        'enabled' => 1,
    ),
);
