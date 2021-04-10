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
 * External Web Service Template
 *
 * @package local_leeloolxpapi
 * @copyright  2020 Leeloo LXP (https://leeloolxp.com)
 * @author Leeloo LXP <info@leeloolxp.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once ($CFG->libdir . "/externallib.php");

class local_leeloolxpapi_external extends external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function course_sync_parameters() {
        return new external_function_parameters(
            array(
                'course_data' => new external_value(PARAM_RAW, 'Course Data', VALUE_DEFAULT, null),
                'categories_data' => new external_value(PARAM_RAW, 'Categories Data', VALUE_DEFAULT, null),
                'grade_data' => new external_value(PARAM_RAW, 'Grade Data', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Sync course from Leeloo to Moodle
     * @return string welcome message
     */
    public static function course_sync($reqcoursedata = '', $reqcategoriesdata = '', $reqgradedata = '') {

        global $DB;
        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(
            self::course_sync_parameters(),
            array(
                'course_data' => $reqcoursedata,
                'categories_data' => $reqcategoriesdata,
                'grade_data' => $reqgradedata,
            )
        );

        $value = (object) json_decode($reqcoursedata, true);

        $value->summaryformat = 0;

        if (!empty($value->description)) {
            $value->summaryformat = 1;
        }

        if (!empty($value->course_image)) {
            $courseimage = $value->course_image;
        } else {
            $courseimage = '';
        }

        if (empty($value->course_id_number)) {
            $value->course_id_number = '';
        }

        $data = [
            'category' => $value->category,
            'sortorder' => 10000,
            'fullname' => $value->project_name,
            'shortname' => $value->project_sort_name,
            'idnumber' => $value->course_id_number,
            'summary' => $value->description,
            'summaryformat' => $value->summaryformat,
            'format' => 'topics',
            'showgrades' => 1,
            'newsitems' => 5,
            'startdate' => strtotime($value->start_date),
            'enddate' => strtotime($value->end_date),
            'relativedatesmode' => 0,
            'marker' => 0,
            'maxbytes' => 0,
            'legacyfiles' => 0,
            'showreports' => 0,
            'visible' => $value->visible,
            'visibleold' => 1,
            'groupmode' => 0,
            'groupmodeforce' => 0,
            'defaultgroupingid' => 0,
            'lang' => '',
            'calendartype' => '',
            'theme' => '',
            'timecreated' => time(),
            'timemodified' => time(),
            'requested' => 0,
            'enablecompletion' => 1,
            'completionnotify' => 0,
            'cacherev' => 1607419438,
        ];

        $data = (object) $data;

        $isinsert = 1;
        if (!empty($value->course_id)) {
            $isinsert = 0;
            $data->id = $value->course_id;
            if (!empty($value->project_sort_name)) {
                $sql = "SELECT * FROM {course} WHERE shortname = ? AND id != ?";
                if ($DB->record_exists_sql($sql, [$value->project_sort_name, $value->course_id])) {
                    return 0;
                }
            }

            if (!empty($value->course_id_number)) {
                $sql = "SELECT * FROM {course} WHERE idnumber = ? AND id != ?";
                if ($DB->record_exists_sql($sql, [$value->course_id_number, $value->course_id])) {
                    return 0;
                }
            }
        } else {
            if (!empty($value->project_sort_name)) {
                if ($DB->record_exists('course', array('shortname' => $value->project_sort_name))) {
                    return 0;
                }
            }

            if (!empty($value->course_id_number)) {
                if ($DB->record_exists('course', array('idnumber' => $value->course_id_number))) {
                    return 0;
                }
            }
        }

        if ($isinsert) {
            $returnid = $DB->insert_record('course', $data);
        } else {
            $DB->update_record('course', $data);
            $returnid = $value->course_id;
        }

        $catreturnid = 0;
        $itemreturnid = 0;
        $categoriesdata = (object) json_decode($reqcategoriesdata, true);
        $gradedata = (object) json_decode($reqgradedata, true);

        // If not empty , then insert category  , no need to check for update.
        if (!empty($categoriesdata) && !empty($categoriesdata->courseid)) { 
            $categoriesdata->path = '/';
            $categoriesdata->courseid = $returnid;
            $catreturnid = $DB->insert_record('grade_categories', $categoriesdata);
            $updatenewdata = ['path' => '/' . $catreturnid . '/' , 'id' => $catreturnid];
            $updatenewdata = (object) $updatenewdata;
            $DB->update_record('grade_categories', $updatenewdata);
        }

        if (!empty($gradedata) && !empty($catreturnid)) {
            $gradedata->iteminstance = $catreturnid;
            $gradedata->courseid = $returnid;
            $itemreturnid = $DB->insert_record('grade_items', $gradedata);
        }

        return $returnid . ',' . $catreturnid . ',' . $itemreturnid;
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function course_sync_returns() {
        return new external_value(PARAM_TEXT, 'Returns id');
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function ar_sync_parameters() {
        return new external_function_parameters(
            array(
                'ar_data' => new external_value(PARAM_RAW, 'A/R Data', VALUE_DEFAULT, null),
                'tags_data' => new external_value(PARAM_RAW, 'Tags Data', VALUE_DEFAULT, null),
                'email' => new external_value(PARAM_RAW, 'Email', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Sync course from Leeloo to Moodle
     * @return string welcome message
     */
    public static function ar_sync($reqardata = '', $reqtagdata = '', $reqemail = '') {

        global $DB;
        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(
            self::ar_sync_parameters(),
            array(
                'ar_data' => $reqardata,
                'tags_data' => $reqtagdata,
                'email' => $reqemail,
            )
        );

        $ardata = (object) json_decode($reqardata, true);

        if (isset($reqemail)) {
            $email = (object) json_decode($reqemail, true);
        }

        $data = [];
        $moddata = [];

        if (isset($ardata->task_name)) {
            $taskname = $ardata->task_name;
            $moddata['name'] = $taskname;
        }

        if (isset($ardata->task_description)) {
            $taskdescription = $ardata->task_description;
            $moddata['intro'] = $taskdescription;
        }

        if (isset($ardata->m_showdescription)) {
            $mshowdescription = $ardata->m_showdescription;
            $data['showdescription'] = $mshowdescription;
        }

        if (isset($ardata->m_idnumber)) {
            $midnumber = $ardata->m_idnumber;
            $data['idnumber'] = $midnumber;
        }

        if (isset($ardata->m_completion)) {
            $mcompletion = $ardata->m_completion;
            $data['completion'] = $mcompletion;
        }

        if (isset($ardata->m_completionexpected)) {
            $mcompletionexpected = $ardata->m_completionexpected;
            if ($mcompletionexpected != 0) {
                $mcompletionexpected = $mcompletionexpected + (6 * 60 * 60);
            }
            $data['completionexpected'] = $mcompletionexpected;
        }

        if (isset($ardata->m_visible)) {
            $mvisible = $ardata->m_visible;
            $data['visible'] = $mvisible;
        }

        if (isset($ardata->m_availability)) {
            $mavailability = $ardata->m_availability;
            $mavailability = str_ireplace('&lt;', '<', $mavailability);
            $mavailability = str_ireplace('&gt;', '>', $mavailability);
            $data['availability'] = $mavailability;
        }

        if (isset($ardata->m_groupmode)) {
            $mgroupmode = $ardata->m_groupmode;
        }

        if (isset($ardata->m_groupingid)) {
            $mgroupingid = $ardata->m_groupingid;
        }

        $activityid = $ardata->activity_id;

        $countupdatescm = count($data);

        $data['id'] = $activityid;

        $data = (object) $data;

        if ($activityid != '') {
            if ($countupdatescm > 0) {
                $DB->update_record('course_modules', $data);
            }

            $ararr = $DB->get_record_sql("SELECT module,instance FROM {course_modules} where id = ?", [$activityid]);
            $module = $ararr->module;
            $modinstance = $ararr->instance;

            $modarr = $DB->get_record_sql("SELECT name FROM {modules} where id = ?", [$module]);
            $modulename = $modarr->name;

            $countupdatesmd = count($moddata);

            $moddata['id'] = $modinstance;

            $moddata = (object) $moddata;

            if ($countupdatesmd > 0) {
                $DB->update_record($modulename, $moddata);
            }

            if (!empty($email)) {
                $userdata = $DB->get_record('user', ['email' => $email->scalar], 'id');
            }

            if (!empty($userdata)) {
                $userid = $userdata->id;
                $tagsreturnarr = [];

                // tags_data
                if (isset($reqtagdata)) {
                    $tagsdataarrobj = (object) json_decode($reqtagdata, true);
                    // echo "<pre>";print_r($tagsdataarrobj);die;

                    if (!empty($tagsdataarrobj)) {
                        foreach ($tagsdataarrobj as $key => $tagsdata) {
                            $istagexist = $DB->get_record('tag', ['name' => $tagsdata['name']], 'id');

                            $leelootagid = $tagsdata['id'];

                            if (empty($istagexist)) {
                                unset($tagsdata['moodleid']);
                                unset($tagsdata['id']);
                                unset($tagsdata['task_id']);

                                $tagsdata['tagcollid'] = 1;
                                $tagsdata['userid'] = $userid;
                                // echo "<pre>";print_r($tagsdata);die;

                                $returnid = $DB->insert_record('tag', $tagsdata);
                                array_push($tagsreturnarr, ['tag_id' => $leelootagid, 'moodleid' => $returnid]);
                            } else {
                                array_push($tagsreturnarr, ['tag_id' => $leelootagid, 'moodleid' => $istagexist->id]);
                            }
                        }
                    }
                }

                // tags_data
                if (!empty($tagsreturnarr)) {
                    $tagidsnotdelete = '';
                    $j = 0;

                    foreach ($tagsreturnarr as $key => $value) {
                        if ($j == 0) {
                            $tagidsnotdelete .= $value['moodleid'];
                        } else {
                            $tagidsnotdelete .= ',' . $value['moodleid'];
                        }
                        $j++;

                        $taginstanceexist = $DB->get_record('tag_instance', ['tagid' => $value['moodleid'], 'itemid' => $activityid], 'id');

                        if (empty($taginstanceexist)) {
                            $contextdata = $DB->get_record('context', ['instanceid' => $activityid], 'id');

                            if (!empty($contextdata)) {
                                $contextid = $contextdata->id;
                            } else {
                                $contextid = 0;
                            }

                            $taginstancedata = [
                                'tagid' => $value['moodleid'],
                                'component' => 'core',
                                'itemtype' => 'course_modules',
                                'itemid' => $activityid,
                                'contextid' => $contextid,
                                'tiuserid' => '0',
                                'ordering' => '1',
                                'timecreated' => strtotime(date('Y-m-d H:i:s')),
                                'timemodified' => strtotime(date('Y-m-d H:i:s')),
                            ];

                            $DB->insert_record('tag_instance', $taginstancedata);
                        }
                    }

                    $sql = "SELECT tagid FROM {tag_instance} WHERE itemid = ?";
                    $tagsfordelete = $DB->get_records_sql($sql, [$activityid]);

                    // $DB->delete_records('tag_instance', ['itemid' => $activityid]);

                    $DB->execute("DELETE FROM {tag_instance} where itemid = ? AND tagid NOT IN (?) ", [$activityid, $tagidsnotdelete]);

                    if (!empty($tagsfordelete)) {
                        $i = 0;

                        foreach ($tagsfordelete as $key => $value) {
                            $sql = "SELECT tagid FROM {tag_instance} WHERE tagid = ?";
                            $istagexistt = $DB->get_record_sql($sql, [$value->tagid]);

                            if (empty($istagexistt)) {
                                $DB->delete_records('tag', ['id' => $value->tagid, 'isstandard' => '0']);
                            }
                        }
                    }
                } else {

                    $sql = "SELECT tagid FROM {tag_instance} WHERE itemid = ?";
                    $tagsfordelete = $DB->get_records_sql($sql, [$activityid]);

                    $DB->delete_records('tag_instance', ['itemid' => $activityid]);

                    if (!empty($tagsfordelete)) {
                        $i = 0;

                        foreach ($tagsfordelete as $key => $value) {
                            $sql = "SELECT tagid FROM {tag_instance} WHERE tagid = ?";
                            $istagexistt = $DB->get_record_sql($sql, [$value->tagid]);

                            if (empty($istagexistt)) {
                                $DB->delete_records('tag', ['id' => $value->tagid, 'isstandard' => '0']);
                            }
                        }
                    }
                }
            } // $userdata end
        }

        if (!empty($tagsreturnarr)) {
            return json_encode($tagsreturnarr);
        }
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function ar_sync_returns() {
        return new external_value(PARAM_TEXT, 'Returns id');
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function standard_tag_sync_parameters() {
        return new external_function_parameters(
            array(
                'standard_tags_data' => new external_value(PARAM_RAW, 'A/R Data', VALUE_DEFAULT, null),
                'email' => new external_value(PARAM_RAW, 'Email', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Sync course from Leeloo to Moodle
     * @return string welcome message
     */
    public static function standard_tag_sync($reqstandardtagdata = '', $reqemail = '') {

        global $DB;
        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(
            self::standard_tag_sync_parameters(),
            array(
                'standard_tags_data' => $reqstandardtagdata,
                'email' => $reqemail,
            )
        );

        if (!empty($reqemail)) {
            $email = (object) json_decode($reqemail, true);
            $userdata = $DB->get_record('user', ['email' => $email->scalar], 'id');
        }

        if (!empty($userdata)) {
            $userid = $userdata->id;

            $tagsreturnarr = [];

            $tagsdataarrobj = (object) json_decode($reqstandardtagdata, true);
            // echo "<pre>";print_r($tagsdataarrobj);die;

            if (!empty($tagsdataarrobj)) {
                foreach ($tagsdataarrobj as $key => $tagsdata) {
                    $istagexist = $DB->get_record('tag', ['name' => $tagsdata['name']], 'id');

                    $leelootagid = $tagsdata['id'];

                    if (empty($istagexist)) {
                        unset($tagsdata['moodleid']);
                        unset($tagsdata['id']);
                        unset($tagsdata['task_id']);

                        $tagsdata['tagcollid'] = 1;
                        $tagsdata['userid'] = $userid;
                        // echo "<pre>";print_r($tagsdata);die;

                        $returnid = $DB->insert_record('tag', $tagsdata);
                        array_push($tagsreturnarr, ['tag_id' => $leelootagid, 'moodleid' => $returnid]);
                    } else {
                        array_push($tagsreturnarr, ['tag_id' => $leelootagid, 'moodleid' => $istagexist->id]);
                    }
                }
            }
        }

        if (!empty($tagsreturnarr)) {
            return json_encode($tagsreturnarr);
        }
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function standard_tag_sync_returns() {
        return new external_value(PARAM_TEXT, 'Returns data');
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function delete_tag_parameters() {
        return new external_function_parameters(
            array(
                'deleted_tag_id' => new external_value(PARAM_RAW, 'Tag data', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Sync course from Leeloo to Moodle
     * @return string welcome message
     */
    public static function delete_tag($reqdeletedtagid = '') {

        global $DB;
        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(
            self::delete_tag_parameters(),
            array(
                'deleted_tag_id' => $reqdeletedtagid,
            )
        );

        $id = json_decode($reqdeletedtagid, true);
        $conditions = array('id' => $id);
        $DB->delete_records('tag', $conditions);
        $conditions = array('tagid' => $id);
        $DB->delete_records('tag_instance', $conditions);
        return '1';
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function delete_tag_returns() {
        return new external_value(PARAM_TEXT, 'Returns data');
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function original_tag_parameters() {
        return new external_function_parameters(
            array(
                'original_tag' => new external_value(PARAM_RAW, 'A/R Data', VALUE_DEFAULT, null),
                'updated_tags_data' => new external_value(PARAM_RAW, 'Tags Data', VALUE_DEFAULT, null),
                'email' => new external_value(PARAM_RAW, 'Email', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Sync course from Leeloo to Moodle
     * @return string welcome message
     */
    public static function original_tag($reqorgtagid = '', $requpdatedtagdata = '', $reqemail = '') {

        global $DB;
        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(
            self::original_tag_parameters(),
            array(
                'original_tag' => $reqorgtagid,
                'updated_tags_data' => $requpdatedtagdata,
                'email' => $reqemail,
            )
        );

        $originaltag = json_decode($reqorgtagid, true);
        $id = $originaltag['id'];

        $istagexist = $DB->get_record('tag', ['id' => $id], 'id');

        if (!empty($istagexist)) {
            $DB->update_record('tag', $originaltag);

            if (!empty($requpdatedtagdata)) {
                $tagsdataarrobj = (object) json_decode($requpdatedtagdata, true);
            }

            if (!empty($reqemail)) {
                $email = (object) json_decode($reqemail, true);
                $userdata = $DB->get_record('user', ['email' => $email->scalar], 'id');
                $userid = $userdata->id;
            }

            $tagsreturnarr = [];
            $tagids = [];

            if (!empty($tagsdataarrobj) && !empty($userid)) {
                foreach ($tagsdataarrobj as $key => $tagsdata) {
                    $istagexist = $DB->get_record('tag', ['name' => $tagsdata['name']], 'id');

                    $leelootagid = $tagsdata['id'];

                    if (empty($istagexist)) {
                        unset($tagsdata['id']);
                        unset($tagsdata['task_id']);

                        $tagsdata['tagcollid'] = 1;
                        $tagsdata['userid'] = $userid;

                        $returnid = $DB->insert_record('tag', $tagsdata);
                        array_push($tagsreturnarr, ['tag_id' => $leelootagid, 'moodleid' => $returnid]);
                    } else {
                        $returnid = $istagexist->id;
                        array_push($tagsreturnarr, ['tag_id' => $leelootagid, 'moodleid' => $istagexist->id]);
                    }
                    array_push($tagids, $returnid);

                    // insert tag instance
                    $taginstanceexistclock = $DB->get_record('tag_instance', ['tagid' => $id, 'itemid' => $returnid], 'id');

                    $taginstanceexistanticlock = $DB->get_record('tag_instance', ['tagid' => $returnid, 'itemid' => $id], 'id');

                    if (empty($taginstanceexistclock) && empty($taginstanceexistanticlock)) {
                        $taginstancedata1 = [
                            'tagid' => $id,
                            'component' => 'core',
                            'itemtype' => 'tag',
                            'itemid' => $returnid,
                            'contextid' => '1',
                            'tiuserid' => '0',
                            'ordering' => '0',
                            'timecreated' => strtotime(date('Y-m-d H:i:s')),
                            'timemodified' => strtotime(date('Y-m-d H:i:s')),
                        ];

                        $taginstancedata2 = [
                            'tagid' => $returnid,
                            'component' => 'core',
                            'itemtype' => 'tag',
                            'itemid' => $id,
                            'contextid' => '1',
                            'tiuserid' => '0',
                            'ordering' => '0',
                            'timecreated' => strtotime(date('Y-m-d H:i:s')),
                            'timemodified' => strtotime(date('Y-m-d H:i:s')),
                        ];

                        $DB->insert_record('tag_instance', $taginstancedata1);
                        $DB->insert_record('tag_instance', $taginstancedata2);
                    }
                }
            } //$tagsdataarrobj end

            if (!empty($tagids)) {
                $tagidsstr = implode(',', $tagids);

                $tagfordelete = $DB->get_records_sql("SELECT tagid FROM {tag_instance} where itemid = ? AND tagid NOT IN (?) ", [$id, $tagidsstr]);
            } else {

                $tagfordelete = $DB->get_records_sql("SELECT tagid FROM {tag_instance} where itemid = ?", [$id]);
            }

            if (!empty($tagfordelete)) {
                foreach ($tagfordelete as $key => $value) {
                    $sql = "SELECT tagid FROM {tag_instance} WHERE itemid = ? or tagid = ?";
                    $tagsfordelete = $DB->get_records_sql($sql, [$value->tagid, $value->tagid]);

                    $DB->execute("DELETE FROM {tag_instance} where itemid = ? AND tagid = ?", [$value->tagid, $id]);
                    $DB->execute("DELETE FROM {tag_instance} where itemid = ? AND tagid = ?", [$id, $value->tagid]);

                    if (!empty($tagsfordelete)) {
                        $i = 0;

                        foreach ($tagsfordelete as $key => $value) {
                            $sql = "SELECT tagid FROM {tag_instance} WHERE tagid = ?";
                            $istagexistt = $DB->get_record_sql($sql, [$value->tagid]);

                            if (empty($istagexistt)) {
                                $DB->delete_records('tag', ['id' => $value->tagid, 'isstandard' => '0']);
                            }
                        }
                    }
                }
            }

            if (!empty($tagsreturnarr)) {
                return json_encode($tagsreturnarr);
            } else {
                return "0";
            }
        }
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function original_tag_returns() {
        return new external_value(PARAM_TEXT, 'Returns id');
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function updated_tag_flag_standard_parameters() {
        return new external_function_parameters(
            array(
                'updated_tag_flag_standard' => new external_value(PARAM_RAW, 'A/R Data', VALUE_DEFAULT, null),
                'email' => new external_value(PARAM_RAW, 'Email', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Sync course from Leeloo to Moodle
     * @return string welcome message
     */
    public static function updated_tag_flag_standard($requpdatedtagflag = '', $reqemail = '') {

        global $DB;
        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(
            self::updated_tag_flag_standard_parameters(),
            array(
                'updated_tag_flag_standard' => $requpdatedtagflag,
                'email' => $reqemail,
            )
        );

        $tagdata = json_decode($requpdatedtagflag, true);
        $id = $tagdata['id'];

        if (!empty($reqemail)) {
            $email = (object) json_decode($reqemail, true);
            $userdata = $DB->get_record('user', ['email' => $email->scalar], 'id');
            $userid = $userdata->id;
        }

        $istagexist = $DB->get_record('tag', ['id' => $id], 'id');

        if (!empty($istagexist) && !empty($userid)) {
            $DB->update_record('tag', $tagdata);
        }
        return '1';
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function updated_tag_flag_standard_returns() {
        return new external_value(PARAM_TEXT, 'Returns id');
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function combine_tags_data_parameters() {
        return new external_function_parameters(
            array(
                'combine_tags_data' => new external_value(PARAM_RAW, 'A/R Data', VALUE_DEFAULT, null),
                'email' => new external_value(PARAM_RAW, 'Email', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Sync course from Leeloo to Moodle
     * @return string welcome message
     */
    public static function combine_tags_data($reqcombtagdata = '', $reqemail = '') {

        global $DB;
        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(
            self::combine_tags_data_parameters(),
            array(
                'combine_tags_data' => $reqcombtagdata,
                'email' => $reqemail,
            )
        );

        $tagdata = json_decode($reqcombtagdata, true);
        $id = $tagdata['updated_id'];
        $deletedids = $tagdata['deleted_ids'];

        if (!empty($reqemail)) {
            $email = (object) json_decode($reqemail, true);
            $userdata = $DB->get_record('user', ['email' => $email->scalar], 'id');
            $userid = $userdata->id;
        }

        $istagexist = $DB->get_record('tag', ['id' => $id], 'id');

        if (!empty($istagexist) && !empty($userid)) {
            $DB->execute("DELETE FROM {tag} where id != ? AND id IN (?) ", [$id, $deletedids]);

            $sql = "SELECT itemid FROM {tag_instance} WHERE tagid IN (?) GROUP BY itemid ";
            $tagsforinsert = $DB->get_records_sql($sql, [$deletedids]);

            if (!empty($tagsforinsert)) {
                foreach ($tagsforinsert as $key => $value) {
                    $taginstanceexistclock = $DB->get_record('tag_instance', ['tagid' => $id, 'itemid' => $value->itemid], 'id');

                    $taginstanceexistanticlock = $DB->get_record('tag_instance', ['tagid' => $value->itemid, 'itemid' => $id], 'id');

                    if (empty($taginstanceexistclock) && empty($taginstanceexistanticlock)) {
                        $taginstancedata1 = [
                            'tagid' => $id,
                            'component' => 'core',
                            'itemtype' => 'tag',
                            'itemid' => $value->itemid,
                            'contextid' => '1',
                            'tiuserid' => '0',
                            'ordering' => '0',
                            'timecreated' => strtotime(date('Y-m-d H:i:s')),
                            'timemodified' => strtotime(date('Y-m-d H:i:s')),
                        ];

                        $taginstancedata2 = [
                            'tagid' => $value->itemid,
                            'component' => 'core',
                            'itemtype' => 'tag',
                            'itemid' => $id,
                            'contextid' => '1',
                            'tiuserid' => '0',
                            'ordering' => '0',
                            'timecreated' => strtotime(date('Y-m-d H:i:s')),
                            'timemodified' => strtotime(date('Y-m-d H:i:s')),
                        ];

                        $DB->insert_record('tag_instance', $taginstancedata1);
                        $DB->insert_record('tag_instance', $taginstancedata2);
                    }
                }
            }
        }
        return '1';
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function combine_tags_data_returns() {
        return new external_value(PARAM_TEXT, 'Returns id');
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function categories_data_delete_parameters() {
        return new external_function_parameters(
            array(
                'categories_data_delete' => new external_value(PARAM_RAW, 'Cat Data', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Sync course from Leeloo to Moodle
     * @return string welcome message
     */
    public static function categories_data_delete($reqcatdatadelete = '') {

        global $DB;
        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(
            self::categories_data_delete_parameters(),
            array(
                'categories_data_delete' => $reqcatdatadelete,
            )
        );

        $id = json_decode($reqcatdatadelete, true);
        $conditions = array('id' => $id);
        $DB->delete_records('course_categories', $conditions);
        return '1';
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function categories_data_delete_returns() {
        return new external_value(PARAM_TEXT, 'Returns true');
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function categories_data_sync_parameters() {
        return new external_function_parameters(
            array(
                'categories_data' => new external_value(PARAM_RAW, 'Cat Data', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Sync course from Leeloo to Moodle
     * @return string welcome message
     */
    public static function categories_data_sync($reqcatsdata = '') {

        global $DB;
        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(
            self::categories_data_sync_parameters(),
            array(
                'categories_data' => $reqcatsdata,
            )
        );

        $value = (object) json_decode($reqcatsdata, true); 
        $isinsert = 1;

        if ($value->is_update) { 
            $returnid = $value->moodle_cat_id;

            $isinsert = 0;
        }
        if ($value->depth != '1') {
            $value->moodle_cat_id = $value->moodle_parent_cat_id;
        }

        $sql = "SELECT * FROM {course_categories} where id = ?";
        $catdetail = $DB->get_record_sql($sql, [$value->moodle_cat_id]);
        $sql = "SELECT * FROM {course_categories} where id = ?";
        $parentcatdetail = $DB->get_record_sql($sql, [$value->moodle_parent_cat_id]);
        // echo "<pre>";print_r($parentcatdetail);

        if (!empty($value->moodle_parent_cat_id) && !empty($parentcatdetail)) {
            // insert/update child cat

            if ($value->depth == 1 || $value->depth == '1') {
                // $value->path = '/' . $catdetail->id;
                $value->parent = '0';
            } else {
                // $value->path = $catdetail->path . '/' . $autoinc->auto_increment; 
                $value->parent = $value->moodle_cat_id;
            }
            if (!empty($returnid)) {
                // $value->id = $autoinc->auto_increment;
                $value->id = 1;
            }
        } else if (!empty($value->moodle_cat_id) && !empty($catdetail)) {
            // update cat

            if ($value->depth == 1 || $value->depth == '1') {
                // $value->path = '/' . $catdetail->id;
                $value->parent = '0';
            } else {
                // $value->path = $catdetail->path . '/' . $autoinc->auto_increment; 
                $value->parent = $value->moodle_cat_id;
            }
            $returnid = $value->id = $value->moodle_cat_id;

            $isinsert = 0;
        } else {
            // insert top cat

            if ($value->depth == 1 || $value->depth == '1') {
                // $value->path = '/' . $autoinc->auto_increment; 
            } else {
                if (!empty($catdetail)) {
                    // $value->path = $catdetail->path . '/' . $autoinc->auto_increment; 
                    $value->parent = $value->moodle_cat_id;
                } else {
                    // $value->path = '/' . $autoinc->auto_increment; 
                    $value->parent = 0;
                }
            }
        }

        $value->sortorder = 10000;
        unset($value->moodle_cat_id);
        unset($value->moodle_parent_cat_id);
        unset($value->is_update);
        $value->path = '';

        if ($isinsert) {
            $returnid = $DB->insert_record('course_categories', $value);            
        } else {
            $DB->update_record('course_categories', $value);
        }

        if (!empty($value->moodle_parent_cat_id) && !empty($parentcatdetail)) {
            // insert/update child cat
            if ($value->depth == 1 || $value->depth == '1') {
                $value->path = '/' . $catdetail->id; 
            } else {
                $value->path = $catdetail->path . '/' . $returnid;  
            } 
        } else if (!empty($value->moodle_cat_id) && !empty($catdetail)) {
            // update cat
            if ($value->depth == 1 || $value->depth == '1') {
                $value->path = '/' . $catdetail->id; 
            } else {
                $value->path = $catdetail->path . '/' . $returnid; 
            } 
        } else {
            // insert top cat
            if ($value->depth == 1 || $value->depth == '1') {
                $value->path = '/' . $returnid; 
            } else {
                if (!empty($catdetail)) {
                    $value->path = $catdetail->path . '/' . $returnid; 
                } else {
                    $value->path = '/' . $returnid; 
                }
            }
        }

        $updatenewdata = ['path' => $value->path , 'id' => $returnid];
        $updatenewdata = (object) $updatenewdata;
        $DB->update_record('course_categories', $updatenewdata);

        return $returnid;
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function categories_data_sync_returns() {
        return new external_value(PARAM_TEXT, 'Returns true');
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_moodle_user_id_parameters() {
        return new external_function_parameters(
            array(
                'get_moodle_user_id' => new external_value(PARAM_RAW, 'User email Data', VALUE_DEFAULT, null),
                'email' => new external_value(PARAM_RAW, 'User email', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Sync course from Leeloo to Moodle
     * @return string welcome message
     */
    public static function get_moodle_user_id($reqmoodleuserid = '', $reqemail = '') {

        global $DB;
        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(
            self::get_moodle_user_id_parameters(),
            array(
                'get_moodle_user_id' => $reqmoodleuserid,
                'email' => $reqemail,
            )
        );

        $email = $reqemail;
        $sql = "SELECT * FROM {user} where email = ?";
        $userdetail = $DB->get_record_sql($sql, [$email]);
        // print_r($userdetail);
        return $userdetail->id;
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_moodle_user_id_returns() {
        return new external_value(PARAM_TEXT, 'Returns userid');
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_moodle_user_parameters() {
        return new external_function_parameters(
            array(
                'userid' => new external_value(PARAM_RAW, 'User id', VALUE_DEFAULT, null),
                'get_moodle_user' => new external_value(PARAM_RAW, 'Cat Data', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Sync course from Leeloo to Moodle
     * @return string welcome message
     */
    public static function get_moodle_user($requserid = '', $reqgetmoodleuser = '') {

        global $DB;
        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(
            self::get_moodle_user_parameters(),
            array(
                'userid' => $requserid,
                'get_moodle_user' => $reqgetmoodleuser,
            )
        );

        $userid = $requserid;
        $sql = "SELECT * FROM {user} where id = ?";
        $userdetail = $DB->get_record_sql($sql, [$userid]);
        // print_r($userdetail);
        return json_encode($userdetail);
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_moodle_user_returns() {
        return new external_value(PARAM_TEXT, 'Returns true');
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function gradeletter1_parameters() {
        return new external_function_parameters(
            array(
                'gradeletter1' => new external_value(PARAM_RAW, 'Cat Data', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Sync course from Leeloo to Moodle
     * @return string welcome message
     */
    public static function gradeletter1($reqgradeletter1 = '') {

        global $DB;
        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(
            self::gradeletter1_parameters(),
            array(
                'gradeletter1' => $reqgradeletter1,
            )
        );

        for ($i = 1; $i <= 11; $i++) {
            $indexl = 'gradeletter' . $i;
            $indexb = 'gradeboundary' . $i;
            $lowerboundary = optional_param($indexb, null, PARAM_RAW);
            $letter = optional_param($indexl, null, PARAM_RAW);
            $DB->execute("update {grade_letters} set lowerboundary = ?, letter = ? where id = ?", [$lowerboundary, $letter, $i]);
        }
        return '1';
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function gradeletter1_returns() {
        return new external_value(PARAM_TEXT, 'Returns true');
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_userid_parameters() {
        return new external_function_parameters(
            array(
                'email' => new external_value(PARAM_RAW, 'Email', VALUE_DEFAULT, null),
                'get_userid' => new external_value(PARAM_RAW, 'Cat Data', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Sync course from Leeloo to Moodle
     * @return string welcome message
     */
    public static function get_userid($reqemail = '', $reqgetuserid = '') {

        global $DB;
        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(
            self::get_userid_parameters(),
            array(
                'email' => $reqemail,
                'get_userid' => $reqgetuserid,
            )
        );

        $email = $reqemail;
        $res = $DB->get_record_sql("SELECT * FROM {user} where email = ?", [$email]);
        if (!empty($res)) {
            return $res->id;
        } else {
            return $res->id;
            0;
        }
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_userid_returns() {
        return new external_value(PARAM_TEXT, 'Returns true');
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function leelo_activity_data_parameters() {
        return new external_function_parameters(
            array(
                'leelo_activity_data' => new external_value(PARAM_RAW, 'Cat Data', VALUE_DEFAULT, null),
                'course_id' => new external_value(PARAM_RAW, 'Cat Data', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Sync course from Leeloo to Moodle
     * @return string welcome message
     */
    public static function leelo_activity_data($reqleelooactdata = '', $reqcourseid = '') {

        global $DB;
        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(
            self::leelo_activity_data_parameters(),
            array(
                'leelo_activity_data' => $reqleelooactdata,
                'course_id' => $reqcourseid,
            )
        );

        $courseid = $reqcourseid;

        $activities = json_decode($reqleelooactdata, true);

        if (!empty($activities)) {
            foreach ($activities as $key => $value) {
                $activityid = $value['activity_id'];

                $startdate = strtotime($value['start_date']);

                $enddate = strtotime($value['end_date']);

                $type = $value['type'];

                $modulerecords = $DB->get_record_sql("SELECT module,instance FROM {course_modules} where id = ?", [$activityid]);

                $moduleid = $modulerecords->module;

                $isntanceid = $modulerecords->instance;

                $modulenames = $DB->get_record_sql("SELECT name FROM {modules} where id = ?", [$moduleid]);

                $modulename = $modulenames->name;

                if ($modulename == 'lesson') {
                    $obj = new stdClass();

                    $obj->id = $isntanceid;

                    $obj->deadline = $enddate;

                    $obj->available = $startdate;

                    $DB->update_record('lesson', $obj);
                } else if ($modulename == 'quiz') {
                    $obj = new stdClass();

                    $obj->id = $isntanceid;

                    $obj->timeopen = $startdate;

                    $obj->timeclose = $enddate;

                    $DB->update_record('quiz', $obj);
                } else if ($modulename == 'assign') {
                    $obj = new stdClass();

                    $obj->id = $isntanceid;

                    $obj->allowsubmissionsfromdate = $startdate;

                    $obj->duedate = $enddate;

                    $DB->update_record('assign', $obj);
                } else if ($modulename == 'chat') {
                    $obj = new stdClass();

                    $obj->id = $isntanceid;

                    $obj->chattime = $startdate;

                    $DB->update_record('chat', $obj);
                } else if ($modulename == 'choice') {
                    $obj = new stdClass();

                    $obj->id = $isntanceid;

                    $obj->timeopen = $startdate;

                    $obj->timeclose = $enddate;

                    $DB->update_record('choice', $obj);
                } else if ($modulename == 'data') {
                    $obj = new stdClass();

                    $obj->id = $isntanceid;

                    $obj->timeavailablefrom = $startdate;

                    $obj->timeavailableto = $enddate;

                    $DB->update_record('data', $obj);
                } else if ($modulename == 'feedback') {
                    $obj = new stdClass();

                    $obj->id = $isntanceid;

                    $obj->timeopen = $startdate;

                    $obj->timeclose = $enddate;

                    $DB->update_record('feedback', $obj);
                } else if ($modulename == 'forum') {
                    $obj = new stdClass();

                    $obj->id = $isntanceid;

                    $obj->duedate = $startdate;

                    $obj->cutoffdate = $enddate;

                    $DB->update_record('forum', $obj);
                } else if ($modulename == 'wespher') {
                    $obj = new stdClass();

                    $obj->id = $isntanceid;

                    $obj->timeopen = $startdate;

                    $DB->update_record('wespher', $obj);
                } else if ($modulename == 'workshop') {
                    $obj = new stdClass();

                    $obj->id = $isntanceid;

                    $obj->submissionstart = $startdate;

                    $obj->submissionend = $enddate;

                    $DB->update_record('workshop', $obj);
                } else if ($modulename == 'scorm') {
                    $obj = new stdClass();

                    $obj->id = $isntanceid;

                    $obj->timeopen = $startdate;

                    $obj->timeclose = $enddate;

                    $DB->update_record('scorm', $obj);
                }
            }
        }

        return "success";
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function leelo_activity_data_returns() {
        return new external_value(PARAM_TEXT, 'Returns true');
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function leelo_data_parameters() {
        return new external_function_parameters(
            array(
                'leelo_data' => new external_value(PARAM_RAW, 'Cat Data', VALUE_DEFAULT, null),
                'course_id' => new external_value(PARAM_RAW, 'Cat Data', VALUE_DEFAULT, null),
                'project_start_date' => new external_value(PARAM_RAW, 'Cat Data', VALUE_DEFAULT, null),
                'project_end_date' => new external_value(PARAM_RAW, 'Cat Data', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Sync course from Leeloo to Moodle
     * @return string welcome message
     */
    public static function leelo_data($reqleeloodata = '', $reqcourseid = '', $reqprojectstartdate = '', $reqprojectenddate = '') {

        global $DB;
        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(
            self::leelo_data_parameters(),
            array(
                'leelo_data' => $reqleeloodata,
                'course_id' => $reqcourseid,
                'project_start_date' => $reqprojectstartdate,
                'project_end_date' => $reqprojectenddate,
            )
        );

        $courseid = $reqcourseid;

        $cobj = new stdClass();

        $cobj->id = $courseid;

        $cobj->startdate = strtotime($reqprojectstartdate);

        $cobj->enddate = strtotime($reqprojectenddate);

        $DB->update_record('course', $cobj);

        $activities = json_decode($reqleeloodata, true);

        if (!empty($activities)) {
            foreach ($activities as $key => $value) {
                $activityid = $value['activity_id'];

                $startdate = strtotime($value['start_date']);

                $enddate = strtotime($value['end_date']);

                $type = $value['type'];

                $modulerecords = $DB->get_record_sql("SELECT module,instance FROM {course_modules} where id = ?", [$activityid]);

                $moduleid = $modulerecords->module;

                $isntanceid = $modulerecords->instance;

                $modulenames = $DB->get_record_sql("SELECT name FROM {modules} where id = ?", [$moduleid]);

                $modulename = $modulenames->name;

                $tbl = $modulename;

                if ($modulename == 'lesson') {
                    $obj = new stdClass();

                    $obj->id = $isntanceid;

                    $obj->deadline = $enddate;

                    $obj->available = $startdate;

                    $DB->update_record('lesson', $obj);
                } else if ($tbl == 'quiz') {
                    $obj = new stdClass();

                    $obj->id = $isntanceid;

                    $obj->timeopen = $startdate;

                    $obj->timeclose = $enddate;

                    $DB->update_record('quiz', $obj);
                } else if ($tbl == 'assign') {
                    $obj = new stdClass();

                    $obj->id = $isntanceid;

                    $obj->allowsubmissionsfromdate = $startdate;

                    $obj->duedate = $enddate;

                    $DB->update_record('assign', $obj);
                } else if ($tbl == 'chat') {
                    $obj = new stdClass();

                    $obj->id = $isntanceid;

                    $obj->chattime = $startdate;

                    $DB->update_record('chat', $obj);
                } else if ($tbl == 'choice') {
                    $obj = new stdClass();

                    $obj->id = $isntanceid;

                    $obj->timeopen = $startdate;

                    $obj->timeclose = $enddate;

                    $DB->update_record('choice', $obj);
                } else if ($tbl == 'data') {
                    $obj = new stdClass();

                    $obj->id = $isntanceid;

                    $obj->timeavailablefrom = $startdate;

                    $obj->timeavailableto = $enddate;

                    $DB->update_record('data', $obj);
                } else if ($tbl == 'feedback') {
                    $obj = new stdClass();

                    $obj->id = $isntanceid;

                    $obj->timeopen = $startdate;

                    $obj->timeclose = $enddate;

                    $DB->update_record('feedback', $obj);
                } else if ($tbl == 'forum') {
                    $obj = new stdClass();

                    $obj->id = $isntanceid;

                    $obj->duedate = $startdate;

                    $obj->cutoffdate = $enddate;

                    $DB->update_record('forum', $obj);
                } else if ($tbl == 'wespher') {
                    $obj = new stdClass();

                    $obj->id = $isntanceid;

                    $obj->timeopen = $startdate;

                    $DB->update_record('wespher', $obj);
                } else if ($tbl == 'workshop') {
                    $obj = new stdClass();

                    $obj->id = $isntanceid;

                    $obj->submissionstart = $startdate;

                    $obj->submissionend = $enddate;

                    $DB->update_record('workshop', $obj);
                } else if ($tbl == 'scorm') {
                    $obj = new stdClass();

                    $obj->id = $isntanceid;

                    $obj->timeopen = $startdate;

                    $obj->timeclose = $enddate;

                    $DB->update_record('scorm', $obj);
                }
            }
        }

        return "success";
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function leelo_data_returns() {
        return new external_value(PARAM_TEXT, 'Returns true');
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function sync_grade_letter_parameters() {
        return new external_function_parameters(
            array(
                'sync_grade_letter' => new external_value(PARAM_RAW, 'Grades Data', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Sync course from Leeloo to Moodle
     * @return string welcome message
     */
    public static function sync_grade_letter($reqlsyncgradeletter = '') {

        global $DB;
        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(
            self::sync_grade_letter_parameters(),
            array(
                'sync_grade_letter' => $reqlsyncgradeletter,
            )
        );

        $response = (object) json_decode($reqlsyncgradeletter, true);
        //echo "<pre>";print_r($response);die;

        $last_inserted_id = 0;
        if (empty($response->contextid)) {
            // courseid
            $response->contextid = 1;
        }

        $override = $response->override;
        unset($response->override);

        if (!empty($response->contextid) && $response->contextid != 1) {
            $contextid = context_course::instance($response->contextid);
            $response->contextid = $contextid->id;

            /*$context_data = $DB->get_record('context', ['contextlevel'=>'50' ,'instanceid' => $response->contextid],'id');
        echo "<pre>";print_r($context_data);die;*/
        }

        $DB->delete_records('grade_letters', ['contextid' => $response->contextid]);

        if (!empty($response)) {
            if ($override != 0) {
                $contextid = $response->contextid;
                unset($response->contextid);

                foreach ($response as $key => $value) {
                    $value['contextid'] = $contextid;

                    $data = (object) $value;

                    if (!empty($last_inserted_id) && $data->lowerboundary == "0.00000") {
                        $data->id = $last_inserted_id;

                        $DB->update_record('grade_letters', $data);
                    } else {

                        if ($data->lowerboundary == "0.00000") {
                            $last_inserted_id = $DB->insert_record('grade_letters', $data);
                        } else {

                            $DB->insert_record('grade_letters', $data);
                        }
                    }
                }
            }
        }

        return $last_inserted_id;
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function sync_grade_letter_returns() {
        return new external_value(PARAM_TEXT, 'Returns data');
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function sync_course_grade_settings_parameters() {
        return new external_function_parameters(
            array(
                'sync_course_grade_settings' => new external_value(PARAM_RAW, 'Grade Settings Data', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Sync course from Leeloo to Moodle
     * @return string welcome message
     */
    public static function sync_course_grade_settings($reqsynccoursegradesettings = '') {

        global $DB;
        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(
            self::sync_course_grade_settings_parameters(),
            array(
                'sync_course_grade_settings' => $reqsynccoursegradesettings,
            )
        );

        $response = (object) json_decode($reqsynccoursegradesettings, true);
        //print_r($response);die;
        $courseid = $response->courseid;
        unset($response->courseid);
        $last_inserted_id = 0;

        if (!empty($response)) {
            $DB->delete_records('grade_settings', ['courseid' => $courseid]);

            foreach ($response as $key => $value) {
                if ($value != '-1') {
                    $insert_data = [
                        'courseid' => $courseid,
                        'name' => $key,
                        'value' => $value,
                    ];
                    $last_inserted_id = $DB->insert_record('grade_settings', $insert_data);
                }
            }
        }

        return $last_inserted_id;
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function sync_course_grade_settings_returns() {
        return new external_value(PARAM_TEXT, 'Returns id');
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function sync_prefrence_grader_report_parameters() {
        return new external_function_parameters(
            array(
                'sync_prefrence_grader_report' => new external_value(PARAM_RAW, 'User Preference Data', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Sync course from Leeloo to Moodle
     * @return string welcome message
     */
    public static function sync_prefrence_grader_report($reqsyncprefrencegraderreport = '') {

        global $DB;
        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(
            self::sync_prefrence_grader_report_parameters(),
            array(
                'sync_prefrence_grader_report' => $reqsyncprefrencegraderreport,
            )
        );

        $response = (object) json_decode($reqsyncprefrencegraderreport, true);
        //echo "<pre>";print_r($response); die;
        $email = $response->email;
        unset($response->email);
        $last_inserted_id = 0;

        $user_data = $DB->get_record('user', ['email' => $email], 'id');

        if (!empty($response) && !empty($user_data)) {
            $user_id = $user_data->id;

            foreach ($response as $key => $value) {
                $DB->delete_records('user_preferences', ['userid' => $user_id, 'name' => $key]);

                if ($value != 'default') {
                    $insert_data = [
                        'userid' => $user_id,
                        'name' => $key,
                        'value' => $value,
                    ];
                    $last_inserted_id = $DB->insert_record('user_preferences', $insert_data);
                }
            }
        }

        return $last_inserted_id;
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function sync_prefrence_grader_report_returns() {
        return new external_value(PARAM_TEXT, 'Returns data');
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function sync_scales_parameters() {
        return new external_function_parameters(
            array(
                'sync_scales' => new external_value(PARAM_RAW, 'Scales Data', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Sync course from Leeloo to Moodle
     * @return string welcome message
     */
    public static function sync_scales($reqsyncscales = '') {

        global $DB;
        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(
            self::sync_scales_parameters(),
            array(
                'sync_scales' => $reqsyncscales,
            )
        );

        $value = (object) json_decode($reqsyncscales, true);

        $return_id = 0;
        $email = $value->email;
        $user_data = $DB->get_record('user', ['email' => $email], 'id');
        if (!empty($user_data)) {
            $user_id = $user_data->id;

            $data = [
                'courseid' => $value->courseid,
                'userid' => $user_id,
                'name' => $value->name,
                'scale' => $value->scale,
                'description' => $value->description,
                'descriptionformat' => 1,
            ];

            $data['timemodified'] = strtotime("now");

            if (!empty($value->moodle_scale_id)) {
                $sql = "SELECT * FROM {scale} where id = '$value->moodle_scale_id'";
                $scale_detail = $DB->get_record_sql($sql);
                if (!empty($scale_detail)) {
                    $data['id'] = $value->moodle_scale_id;
                    $DB->update_record('scale', $data);
                    $return_id = $value->moodle_scale_id;
                } else {
                    $return_id = $DB->insert_record('scale', $data);
                }
            } else {
                $return_id = $DB->insert_record('scale', $data);
            }
        }

        return $return_id;
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function sync_scales_returns() {
        return new external_value(PARAM_TEXT, 'Returns data');
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function categories_data_grades_parameters() {
        return new external_function_parameters(
            array(
                'categories_data' => new external_value(PARAM_RAW, 'Cat Data', VALUE_DEFAULT, null),
                'grade_data' => new external_value(PARAM_RAW, 'Grades Data', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Sync course from Leeloo to Moodle
     * @return string welcome message
     */
    public static function categories_data_grades($reqcategoriesdata = '', $reqgradedata = '') {

        global $DB;
        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(
            self::categories_data_grades_parameters(),
            array(
                'categories_data' => $reqcategoriesdata,
                'grade_data' => $reqgradedata,
            )
        );

        $cat_return_id = 0;
        $item_return_id = 0;
        $categories_data = (object) json_decode($reqcategoriesdata, true);
        $grade_data = (object) json_decode($reqgradedata, true);

        $moodle_parent_id = $categories_data->moodle_parent_id;
        unset($categories_data->moodle_parent_id);

        if (!empty($categories_data) && !empty($moodle_parent_id)) {
            $parent_cat_data = $DB->get_record('grade_categories', ['id' => $moodle_parent_id], '*');

            if (!empty($categories_data->old_cat_id)) {
                unset($categories_data->path);
                unset($categories_data->parent);
                $categories_data->id = $categories_data->old_cat_id;
                unset($categories_data->old_cat_id);

                $DB->update_record('grade_categories', $categories_data);

                $cat_return_id = $categories_data->id;
            } else {
 
                $categories_data->path = '';
                // $parent_cat_data->path . $auto_inc->auto_increment . '/';
                $categories_data->parent = $moodle_parent_id;
                $cat_return_id = $DB->insert_record('grade_categories', $categories_data);

                $updatenewdata = ['path' => $parent_cat_data->path . $cat_return_id . '/' , 'id' => $cat_return_id];
                $updatenewdata = (object) $updatenewdata;
                $DB->update_record('grade_categories', $updatenewdata);

            }
        }

        if (!empty($grade_data)) {
            if (!empty($grade_data->item_moodle_cat_id)) {
                $grade_data->categoryid = $grade_data->item_moodle_cat_id;
            } else {

                unset($grade_data->categoryid);
            }

            unset($grade_data->item_moodle_cat_id);

            if (!empty($grade_data->old_item_id)) {
                //echo "<pre>";print_r($grade_data);die;
                $grade_data->id = $grade_data->old_item_id;

                unset($grade_data->old_item_id);
                unset($grade_data->weightoverride);

                $DB->update_record('grade_items', $grade_data);

                $item_return_id = $grade_data->id;
            } else {

                if (!empty($cat_return_id)) {
                    $grade_data->iteminstance = $cat_return_id;

                    $item_return_id = $DB->insert_record('grade_items', $grade_data);
                } elseif (!empty($grade_data->categoryid)) {

                    unset($grade_data->iteminstance);

                    $item_return_id = $DB->insert_record('grade_items', $grade_data);
                }
            }
        }

        return $cat_return_id . ',' . $item_return_id;
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function categories_data_grades_returns() {
        return new external_value(PARAM_TEXT, 'Returns true');
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function delete_grade_item_parameters() {
        return new external_function_parameters(
            array(
                'delete_grade_item' => new external_value(PARAM_RAW, 'Grade item Data', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Sync course from Leeloo to Moodle
     * @return string welcome message
     */
    public static function delete_grade_item($reqdeletegradeitem = '') {

        global $DB;
        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(
            self::delete_grade_item_parameters(),
            array(
                'delete_grade_item' => $reqdeletegradeitem,
            )
        );

        $response = (object) json_decode($reqdeletegradeitem, true);

        if (!empty($response->id)) {
            $DB->delete_records('grade_items', ['id' => $response->id]);
        }

        return '1';
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function delete_grade_item_returns() {
        return new external_value(PARAM_TEXT, 'Returns true');
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function grade_hidden_data_parameters() {
        return new external_function_parameters(
            array(
                'hidden_data' => new external_value(PARAM_RAW, 'Grade Data', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Sync course from Leeloo to Moodle
     * @return string welcome message
     */
    public static function grade_hidden_data($reqhiddendata = '') {

        global $DB;
        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(
            self::grade_hidden_data_parameters(),
            array(
                'hidden_data' => $reqhiddendata,
            )
        );

        $response = (object) json_decode($reqhiddendata, true);

        if (!empty($response->id) && isset($response->hidden)) {
            $data = [
                'hidden' => $response->hidden,
            ];

            $data = (object) $data;

            $data->id = $response->id;

            if (!empty($response->is_item)) {
                $DB->update_record('grade_items', $data);
            } else {

                $DB->update_record('grade_categories', $data);

                $sql = "UPDATE {grade_items} SET `hidden` = '$response->hidden' WHERE `categoryid` = '$response->id' or  `iteminstance` = '$response->id' ";

                $DB->execute($sql);
            }
        }

        return '1';
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function grade_hidden_data_returns() {
        return new external_value(PARAM_TEXT, 'Returns true');
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function grade_duplicate_data_parameters() {
        return new external_function_parameters(
            array(
                'duplicate_data' => new external_value(PARAM_RAW, 'Grade Data', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Sync course from Leeloo to Moodle
     * @return string welcome message
     */
    public static function grade_duplicate_data($reqduplicatedata = '') {

        global $DB;
        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(
            self::grade_duplicate_data_parameters(),
            array(
                'duplicate_data' => $reqduplicatedata,
            )
        );

        $response = (object) json_decode($reqduplicatedata, true);

        $return_id = 0;

        if (!empty($response->id)) {
            $grade_data = $DB->get_record('grade_items', ['id' => $response->id]);

            unset($grade_data->id);

            $grade_data->itemname = $grade_data->itemname . ' (copy)';

            $return_id = $DB->insert_record('grade_items', $grade_data);
        }

        return $return_id;
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function grade_duplicate_data_returns() {
        return new external_value(PARAM_TEXT, 'Returns id');
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function gradeitem_order_change_data_parameters() {
        return new external_function_parameters(
            array(
                'gradeitem_order_change_data' => new external_value(PARAM_RAW, 'Grade Data', VALUE_DEFAULT, null),
                'category_id' => new external_value(PARAM_RAW, 'Category Data', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Sync course from Leeloo to Moodle
     * @return string welcome message
     */
    public static function gradeitem_order_change_data($reqgradeitemorderchangedata = '', $reqcategoryid = '') {

        global $DB;
        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(
            self::gradeitem_order_change_data_parameters(),
            array(
                'gradeitem_order_change_data' => $reqgradeitemorderchangedata,
                'category_id' => $reqcategoryid,
            )
        );

        $response = (object) json_decode($reqgradeitemorderchangedata, true);
        $cat_id = (object) json_decode($reqcategoryid, true);

        if (!empty($response) && !empty($cat_id->moodle_cat_id)) {
            foreach ($response as $key => $value) {
                //echo " <pre>";print_r($value['moodle_tbl_id']); die;

                $items_data = ['categoryid' => $cat_id->moodle_cat_id];

                $items_data = (object) $items_data;

                $items_data->id = $value['moodle_tbl_id'];

                $DB->update_record('grade_items', $items_data);
            }
        }

        return '1';
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function gradeitem_order_change_data_returns() {
        return new external_value(PARAM_TEXT, 'Returns true');
    }

    function change_cate_order($child_cats, $current_cat, $depth = null) {
        global $DB;

        foreach ($child_cats as $key => $value) {
            $path = str_replace('/' . $current_cat->id, '', $value->path);

            if (empty($depth)) {
                $depth = $current_cat->depth;
                $parent = $current_cat->parent;
            } else {
                $parent = $value->parent;
            }
            $categories_data = [
                'parent' => $parent,
                'depth' => $depth,
                'path' => $path,
            ];
            $categories_data = (object) $categories_data;

            $categories_data->id = $value->id;

            $DB->update_record('grade_categories', $categories_data);

            $child_cat_current = $DB->get_records('grade_categories', ['parent' => $value->id]);

            if (!empty($child_cat_current)) {
                change_cate_order($child_cat_current, $current_cat, $value->depth);
            }
        }
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function delete_grade_category_parameters() {
        return new external_function_parameters(
            array(
                'delete_grade_category' => new external_value(PARAM_RAW, 'Grade Data', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Sync course from Leeloo to Moodle
     * @return string welcome message
     */
    public static function delete_grade_category($reqdeletegradecategory = '') {

        global $DB;
        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(
            self::delete_grade_category_parameters(),
            array(
                'delete_grade_category' => $reqdeletegradecategory,
            )
        );

        $response = (object) json_decode($reqdeletegradecategory, true);

        if (!empty($response->id)) {
            // re-arrenge category parent child relation
            $parent_cat_data = $DB->get_records('grade_categories', ['parent' => $response->id]);

            if (!empty($parent_cat_data)) {
                $current_cat = $DB->get_record('grade_categories', ['id' => $response->id], '*');
                // change_cate_order($parent_cat_data, $current_cat);
            }

            //update parent of grade item
            $sql = "SELECT id FROM {grade_items} WHERE categoryid = '$response->id' ";
            $child_items = $DB->get_records_sql($sql);
            $current_cat = $DB->get_record('grade_categories', ['id' => $response->id], '*');

            if (!empty($child_items)) {
                foreach ($child_items as $key => $value) {
                    $itemms_data = [
                        'categoryid' => $current_cat->parent,
                    ];
                    $itemms_data = (object) $itemms_data;

                    $itemms_data->id = $value->id;

                    $DB->update_record('grade_items', $itemms_data);
                }
            }

            // delete category items
            $sql = "SELECT * FROM {grade_items} WHERE iteminstance = '$response->id' AND itemtype != 'mod' ";

            $result = $DB->get_records_sql($sql);

            foreach ($result as $key => $value) {
                $DB->delete_records('grade_items', ['id' => $value->id]);
            }

            $DB->delete_records('grade_categories', ['id' => $response->id]);
        }

        return '1';
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function delete_grade_category_returns() {
        return new external_value(PARAM_TEXT, 'Returns true');
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function global_grade_user_settings_parameters() {
        return new external_function_parameters(
            array(
                'global_grade_user_settings' => new external_value(PARAM_RAW, 'Grade Data', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Sync course from Leeloo to Moodle
     * @return string welcome message
     */
    public static function global_grade_user_settings($reqglobalgradeusersettings = '') {

        global $DB;
        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(
            self::global_grade_user_settings_parameters(),
            array(
                'global_grade_user_settings' => $reqglobalgradeusersettings,
            )
        );

        $reqglobalgradeusersettings = (object) json_decode($reqglobalgradeusersettings, true);

        $DB->execute("update {config} set value = '".$reqglobalgradeusersettings->showrank."' where name = 'grade_report_user_showrank'");
        $DB->execute("update {config} set value = '".$reqglobalgradeusersettings->showpercentage."' where name = 'grade_report_user_showpercentage'");
        $DB->execute("update {config} set value = '".$reqglobalgradeusersettings->showgrade."' where name = 'grade_report_user_showgrade'");
        $DB->execute("update {config} set value = '".$reqglobalgradeusersettings->showfeedback."' where name = 'grade_report_user_showfeedback'");
        $DB->execute("update {config} set value = '".$reqglobalgradeusersettings->showrange."' where name = 'grade_report_user_showrange'");
        $DB->execute("update {config} set value = '".$reqglobalgradeusersettings->showweight."' where name = 'grade_report_user_showweight'");
        $DB->execute("update {config} set value = '".$reqglobalgradeusersettings->showaverage."' where name = 'grade_report_user_showaverage'");
        $DB->execute("update {config} set value = '".$reqglobalgradeusersettings->showlettergrade."' where name = 'grade_report_user_showlettergrade'");
        $DB->execute("update {config} set value = '".$reqglobalgradeusersettings->rangedecimals."' where name = 'grade_report_user_rangedecimals'");
        $DB->execute("update {config} set value = '".$reqglobalgradeusersettings->showhiddenitems."' where name = 'grade_report_user_showhiddenitems'");
        $DB->execute("update {config} set value = '".$reqglobalgradeusersettings->showtotalsifcontainhidden."' where name = 'grade_report_user_showtotalsifcontainhidden'");
        $DB->execute("update {config} set value = '".$reqglobalgradeusersettings->showcontributiontocoursetotal."' where name = 'grade_report_user_showcontributiontocoursetotal'");

        return '1';
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function global_grade_user_settings_returns() {
        return new external_value(PARAM_TEXT, 'Returns true');
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function global_grade_grader_report_settings_parameters() {
        return new external_function_parameters(
            array(
                'grader_report_settings' => new external_value(PARAM_RAW, 'Grade Data', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Sync course from Leeloo to Moodle
     * @return string welcome message
     */
    public static function global_grade_grader_report_settings($reqgraderreportsettings = '') {

        global $DB;
        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(
            self::global_grade_grader_report_settings_parameters(),
            array(
                'grader_report_settings' => $reqgraderreportsettings,
            )
        );

        $reqgraderreportsettings = (object) json_decode($reqgraderreportsettings, true); 

        $DB->execute("update {config} set value = '".$reqgraderreportsettings->grade_report_studentsperpage."' where name = 'grade_report_studentsperpage'");
        $DB->execute("update {config} set value = '".$reqgraderreportsettings->grade_report_showonlyactiveenrol."' where name = 'grade_report_showonlyactiveenrol'");
        $DB->execute("update {config} set value = '".$reqgraderreportsettings->grade_report_quickgrading."' where name = 'grade_report_quickgrading'");
        $DB->execute("update {config} set value = '".$reqgraderreportsettings->grade_report_showquickfeedback."' where name = 'grade_report_showquickfeedback'");
        $DB->execute("update {config} set value = '".$reqgraderreportsettings->grade_report_meanselection."' where name = 'grade_report_meanselection'");
        $DB->execute("update {config} set value = '".$reqgraderreportsettings->grade_report_enableajax."' where name = 'grade_report_enableajax'");
        $DB->execute("update {config} set value = '".$reqgraderreportsettings->grade_report_showcalculations."' where name = 'grade_report_showcalculations'");
        $DB->execute("update {config} set value = '".$reqgraderreportsettings->grade_report_showeyecons."' where name = 'grade_report_showeyecons'");
        $DB->execute("update {config} set value = '".$reqgraderreportsettings->grade_report_showaverages."' where name = 'grade_report_showaverages'");
        $DB->execute("update {config} set value = '".$reqgraderreportsettings->grade_report_showlocks."' where name = 'grade_report_showlocks'");
        $DB->execute("update {config} set value = '".$reqgraderreportsettings->grade_report_showranges."' where name = 'grade_report_showranges'");
        $DB->execute("update {config} set value = '".$reqgraderreportsettings->grade_report_showanalysisicon."' where name = 'grade_report_showanalysisicon'");
        $DB->execute("update {config} set value = '".$reqgraderreportsettings->grade_report_showuserimage."' where name = 'grade_report_showuserimage'");
        $DB->execute("update {config} set value = '".$reqgraderreportsettings->grade_report_showactivityicons."' where name = 'grade_report_showactivityicons'");
        $DB->execute("update {config} set value = '".$reqgraderreportsettings->grade_report_shownumberofgrades."' where name = 'grade_report_shownumberofgrades'");
        $DB->execute("update {config} set value = '".$reqgraderreportsettings->grade_report_averagesdisplaytype."' where name = 'grade_report_averagesdisplaytype'");
        $DB->execute("update {config} set value = '".$reqgraderreportsettings->grade_report_rangesdisplaytype."' where name = 'grade_report_rangesdisplaytype'");
        $DB->execute("update {config} set value = '".$reqgraderreportsettings->grade_report_averagesdecimalpoints."' where name = 'grade_report_averagesdecimalpoints'");
        $DB->execute("update {config} set value = '".$reqgraderreportsettings->grade_report_rangesdecimalpoints."' where name = 'grade_report_rangesdecimalpoints'");

        return '1';
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function global_grade_grader_report_settings_returns() {
        return new external_value(PARAM_TEXT, 'Returns true');
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function global_scale_delete_parameters() {
        return new external_function_parameters(
            array(
                'scale_delete' => new external_value(PARAM_RAW, 'Grade Data', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Sync course from Leeloo to Moodle
     * @return string welcome message
     */
    public static function global_scale_delete($reqscaledelete = '') {

        global $DB;
        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(
            self::global_scale_delete_parameters(),
            array(
                'scale_delete' => $reqscaledelete,
            )
        );

        $reqscaledelete = (object) json_decode($reqscaledelete, true);

        $DB->delete_records('scale', ['id' => $reqscaledelete->moodle_scale_id]);

        return '1';
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function global_scale_delete_returns() {
        return new external_value(PARAM_TEXT, 'Returns true');
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function global_grade_overview_parameters() {
        return new external_function_parameters(
            array(
                'global_grade_overview' => new external_value(PARAM_RAW, 'Grade Data', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Sync course from Leeloo to Moodle
     * @return string welcome message
     */
    public static function global_grade_overview($reqglobalgradeoverview = '') {

        global $DB;
        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(
            self::global_grade_overview_parameters(),
            array(
                'global_grade_overview' => $reqglobalgradeoverview,
            )
        );

        $reqglobalgradeoverview = (object) json_decode($reqglobalgradeoverview, true); 

        $DB->execute("update {config} set value = '".$reqglobalgradeoverview->showrank."' where name = 'grade_report_overview_showrank'");
        $DB->execute("update {config} set value = '".$reqglobalgradeoverview->showtotalsifcontainhidden."' where name = 'grade_report_overview_showtotalsifcontainhidden'");

        return '1';
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function global_grade_overview_returns() {
        return new external_value(PARAM_TEXT, 'Returns true');
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function global_grade_history_parameters() {
        return new external_function_parameters(
            array(
                'global_grade_history' => new external_value(PARAM_RAW, 'Grade Data', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Sync course from Leeloo to Moodle
     * @return string welcome message
     */
    public static function global_grade_history($reqglobalgradehistory = '') {

        global $DB;
        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(
            self::global_grade_history_parameters(),
            array(
                'global_grade_history' => $reqglobalgradehistory,
            )
        );

        $reqglobalgradehistory = (object) json_decode($reqglobalgradehistory, true); 

        $DB->execute("update {config} set value = '".$reqglobalgradehistory->pages."' where name = 'grade_report_historyperpage'");

        return '1';
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function global_grade_history_returns() {
        return new external_value(PARAM_TEXT, 'Returns true');
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function global_grade_item_settings_parameters() {
        return new external_function_parameters(
            array(
                'grade_item_settings' => new external_value(PARAM_RAW, 'Grade Data', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Sync course from Leeloo to Moodle
     * @return string welcome message
     */
    public static function global_grade_item_settings($reqgradeitemsettings = '') {

        global $DB;
        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(
            self::global_grade_item_settings_parameters(),
            array(
                'grade_item_settings' => $reqgradeitemsettings,
            )
        );

        $reqgradeitemsettings = (object) json_decode($reqgradeitemsettings, true);

        $gradedisplaytype = $reqgradeitemsettings->grade_displaytype;
        $gradedecimalpoints = $reqgradeitemsettings->grade_decimalpoints;
        $gradeitemadvanced = $reqgradeitemsettings->grade_item_advanced;

        $DB->execute("update {config} set value = '".$gradedisplaytype."' where name = 'grade_displaytype'");
        $DB->execute("update {config} set value = '".$gradedecimalpoints."' where name = 'grade_decimalpoints'");
        $DB->execute("update {config} set value = '".$gradeitemadvanced."' where name = 'grade_item_advanced'");

        return '1';
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function global_grade_item_settings_returns() {
        return new external_value(PARAM_TEXT, 'Returns true');
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function global_grade_category_settings_parameters() {
        return new external_function_parameters(
            array(
                'grade_category_settings' => new external_value(PARAM_RAW, 'Grade Data', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Sync course from Leeloo to Moodle
     * @return string welcome message
     */
    public static function global_grade_category_settings($reqgradecategorysettings = '') {

        global $DB;
        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(
            self::global_grade_category_settings_parameters(),
            array(
                'grade_category_settings' => $reqgradecategorysettings,
            )
        );
        $reqgradecategorysettings = (object) json_decode($reqgradecategorysettings, true);
        // return $reqgradecategorysettings;die; 

        $gradehideforcedsettings = $reqgradecategorysettings->grade_hideforcedsettings; 

        $gradeaggregation = $reqgradecategorysettings->grade_aggregation; 
        $gradeaggregationflag = $reqgradecategorysettings->grade_aggregation_flag;
        $gradeaggregationsvisible = $reqgradecategorysettings->grade_aggregations_visible;

        $gradeaggregateonlygraded = $reqgradecategorysettings->grade_aggregateonlygraded; 
        $gradeaggregateonlygradedflag = $reqgradecategorysettings->grade_aggregateonlygraded_flag;

        $gradeaggregateoutcomes = $reqgradecategorysettings->grade_aggregateoutcomes; 
        $gradeaggregateoutcomesflag = $reqgradecategorysettings->grade_aggregateoutcomes_flag;

        $gradekeephigh = $reqgradecategorysettings->grade_keephigh; 
        $gradekeephighflag = $reqgradecategorysettings->grade_keephigh_flag;   

        $gradedroplow = $reqgradecategorysettings->grade_droplow; 
        $gradedroplowflag = $reqgradecategorysettings->grade_droplow_flag;      

        $gradeoverridecat = $reqgradecategorysettings->grade_overridecat;

        $DB->execute("update {config} set value = '".$gradehideforcedsettings."' where name = 'grade_hideforcedsettings'");
        $DB->execute("update {config} set value = '".$gradeaggregation."' where name = 'grade_aggregation'");
        $DB->execute("update {config} set value = '".$gradeaggregationflag."' where name = 'grade_aggregation_flag'");
        $DB->execute("update {config} set value = '".$gradeaggregationsvisible."' where name = 'grade_aggregations_visible'");
        $DB->execute("update {config} set value = '".$gradeaggregateonlygraded."' where name = 'grade_aggregateonlygraded'");
        $DB->execute("update {config} set value = '".$gradeaggregateonlygradedflag."' where name = 'grade_aggregateonlygraded_flag'");
        $DB->execute("update {config} set value = '".$gradeaggregateoutcomes."' where name = 'grade_aggregateoutcomes'");
        $DB->execute("update {config} set value = '".$gradeaggregateoutcomesflag."' where name = 'grade_aggregateoutcomes_flag'");
        $DB->execute("update {config} set value = '".$gradekeephigh."' where name = 'grade_keephigh'");
        $DB->execute("update {config} set value = '".$gradekeephighflag."' where name = 'grade_keephigh_flag'");
        $DB->execute("update {config} set value = '".$gradedroplow."' where name = 'grade_droplow'");
        $DB->execute("update {config} set value = '".$gradedroplowflag."' where name = 'grade_droplow_flag'");
        $DB->execute("update {config} set value = '".$gradeoverridecat."' where name = 'grade_overridecat'");

        return '1';
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function global_grade_category_settings_returns() {
        return new external_value(PARAM_TEXT, 'Returns true');
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function global_grade_settings_parameters() {
        return new external_function_parameters(
            array(
                'global_grade_data' => new external_value(PARAM_RAW, 'Grade Data', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Sync course from Leeloo to Moodle
     * @return string welcome message
     */
    public static function global_grade_settings($reqglobalgradedata = '') {

        global $DB;
        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(
            self::global_grade_settings_parameters(),
            array(
                'global_grade_data' => $reqglobalgradedata,
            )
        );

        $reqglobalgradedata = (object) json_decode($reqglobalgradedata, true); 

        $DB->execute("update {config} set value = '".$reqglobalgradedata->bookmoodlerole."' where name = 'gradebookroles'");
        $DB->execute("update {config} set value = '".$reqglobalgradedata->grade_profilereport."' where name = 'grade_profilereport'");
        $DB->execute("update {config} set value = '".$reqglobalgradedata->grade_aggregationposition."' where name = 'grade_aggregationposition'");
        $DB->execute("update {config} set value = '".$reqglobalgradedata->grade_includescalesinaggregation."' where name = 'grade_includescalesinaggregation'");
        $DB->execute("update {config} set value = '".$reqglobalgradedata->grade_export_displaytype."' where name = 'grade_export_displaytype'");
        $DB->execute("update {config} set value = '".$reqglobalgradedata->grade_export_decimalpoints."' where name = 'grade_export_decimalpoints'");
        $DB->execute("update {config} set value = '".$reqglobalgradedata->grade_navmethod."' where name = 'grade_navmethod'");
        $DB->execute("update {config} set value = '".$reqglobalgradedata->grade_export_userprofilefields."' where name = 'grade_export_userprofilefields'");
        $DB->execute("update {config} set value = '".$reqglobalgradedata->grade_export_customprofilefields."' where name = 'grade_export_customprofilefields'");
        $DB->execute("update {config} set value = '".$reqglobalgradedata->gradeexport."' where name = 'gradeexport'");
        $DB->execute("update {config} set value = '".$reqglobalgradedata->unlimitedgrades."' where name = 'unlimitedgrades'");
        $DB->execute("update {config} set value = '".$reqglobalgradedata->grade_report_showmin."' where name = 'grade_report_showmin'");
        $DB->execute("update {config} set value = '".$reqglobalgradedata->gradepointmax."' where name = 'gradepointmax'");
        $DB->execute("update {config} set value = '".$reqglobalgradedata->gradepointdefault."' where name = 'gradepointdefault'");
        $DB->execute("update {config} set value = '".$reqglobalgradedata->grade_minmaxtouse."' where name = 'grade_minmaxtouse'");
        $DB->execute("update {config} set value = '".$reqglobalgradedata->grade_mygrades_report."' where name = 'grade_mygrades_report'");
        $DB->execute("update {config} set value = '".$reqglobalgradedata->recovergradesdefault."' where name = 'recovergradesdefault'");
        $DB->execute("update {config} set value = '".$reqglobalgradedata->gradereport_mygradeurl."' where name = 'gradereport_mygradeurl'");
        $DB->execute("update {config} set value = '".$reqglobalgradedata->grade_hiddenasdate."' where name = 'grade_hiddenasdate'");
        $DB->execute("update {config} set value = '".$reqglobalgradedata->gradepublishing."' where name = 'gradepublishing'");
        $DB->execute("update {config} set value = '".$reqglobalgradedata->grade_export_exportfeedback."' where name = 'grade_export_exportfeedback'");

        return '1';
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function global_grade_settings_returns() {
        return new external_value(PARAM_TEXT, 'Returns true');
    }
}
