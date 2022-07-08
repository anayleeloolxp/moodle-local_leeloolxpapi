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
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . "/externallib.php");

/**
 * External api for leeloo sync
 */
class local_leeloolxpapi_external extends external_api {


    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function course_visibility_parameters() {

        return new external_function_parameters(
            array(
                'course_data' => new external_value(PARAM_RAW, 'Course Data', VALUE_DEFAULT, null),
            )
        );
    }


    /**
     * Sync course from Leeloo to Moodle
     *
     * @param string $reqcoursedata reqcoursedata
     * @return string welcome message
     */
    public static function course_visibility($reqcoursedata = '') {

        global $DB;
        // Parameter validation.
        // REQUIRED.
        $params = self::validate_parameters(
            self::course_sync_parameters(),
            array(
                'course_data' => $reqcoursedata,
            )
        );

        $value = (object) json_decode($reqcoursedata, true);

        $DB->execute("update {" . $value->table . "}
        set visible = '" . $value->visible . "'
        where id =  '" . $value->course_id . "' ");
        return '1';
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function course_visibility_returns() {
        return new external_value(PARAM_TEXT, 'Returns id');
    }

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
     *
     * @param string $reqcoursedata reqcoursedata
     * @param string $reqcategoriesdata reqcategoriesdata
     * @param string $reqgradedata reqgradedata
     *
     * @return string welcome message
     */
    public static function course_sync($reqcoursedata = '', $reqcategoriesdata = '', $reqgradedata = '') {

        global $DB;
        // Parameter validation.
        // REQUIRED.
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
            $updatenewdata = ['path' => '/' . $catreturnid . '/', 'id' => $catreturnid];
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
     *
     * @param string $reqardata reqardata
     * @param string $reqtagdata reqtagdata
     * @param string $reqemail reqemail
     *
     * @return string welcome message
     */
    public static function ar_sync($reqardata = '', $reqtagdata = '', $reqemail = '') {

        global $DB;
        // Parameter validation.
        // REQUIRED.
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

        if (isset($ardata->content)) {
            $content = $ardata->content;
            $moddata['content'] = $content;
        }

        if (isset($ardata->vimeo_video_id)) {
            $vimeovideoid = $ardata->vimeo_video_id;
            $moddata['vimeo_video_id'] = $vimeovideoid;
        }

        if (isset($ardata->quiztype)) {
            $quiztype = $ardata->quiztype;
            $moddata['quiztype'] = $quiztype;
        }

        if (isset($ardata->timeopen)) {
            $timeopen = $ardata->timeopen;
            $moddata['timeopen'] = $timeopen;
        }

        if (isset($ardata->timeclose)) {
            $timeclose = $ardata->timeclose;
            $moddata['timeclose'] = $timeclose;
        }

        /* if (isset($ardata->quiztype)) {
            $quiztype = $ardata->quiztype;
            $moddata['quiztype'] = $quiztype;
        }

        if (isset($ardata->quiztype)) {
            $quiztype = $ardata->quiztype;
            $moddata['quiztype'] = $quiztype;
        }

        if (isset($ardata->quiztype)) {
            $quiztype = $ardata->quiztype;
            $moddata['quiztype'] = $quiztype;
        } */

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
            if ($mcompletion == 2) {
                $data['completionview'] = 1;
            } else {
                $data['completionview'] = 0;
            }
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

                // Var tags_data.
                if (isset($reqtagdata)) {
                    $tagsdataarrobj = (object) json_decode($reqtagdata, true);

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

                                $returnid = $DB->insert_record('tag', $tagsdata);
                                array_push($tagsreturnarr, ['tag_id' => $leelootagid, 'moodleid' => $returnid]);
                            } else {
                                array_push($tagsreturnarr, ['tag_id' => $leelootagid, 'moodleid' => $istagexist->id]);
                            }
                        }
                    }
                }

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

                        $taginstanceexist = $DB->get_record(
                            'tag_instance',
                            ['tagid' => $value['moodleid'], 'itemid' => $activityid],
                            'id'
                        );

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

                    $DB->execute("DELETE FROM {tag_instance} where itemid = ?
                    AND tagid NOT IN (?) ", [$activityid, $tagidsnotdelete]);

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
     *
     * @param string $reqstandardtagdata reqstandardtagdata
     * @param string $reqemail reqemail
     *
     * @return string welcome message
     */
    public static function standard_tag_sync($reqstandardtagdata = '', $reqemail = '') {

        global $DB;
        // Parameter validation.
        // REQUIRED.
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
     *
     * @param string $reqdeletedtagid reqdeletedtagid
     *
     * @return string welcome message
     */
    public static function delete_tag($reqdeletedtagid = '') {

        global $DB;
        // Parameter validation.
        // REQUIRED.
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
     *
     * @param string $reqorgtagid reqorgtagid
     * @param string $requpdatedtagdata requpdatedtagdata
     * @param string $reqemail reqemail
     *
     * @return string welcome message
     */
    public static function original_tag($reqorgtagid = '', $requpdatedtagdata = '', $reqemail = '') {

        global $DB;
        // Parameter validation.
        // REQUIRED.
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

                $tagfordelete = $DB->get_records_sql("SELECT tagid FROM {tag_instance}
                where itemid = ? AND tagid NOT IN (?) ", [$id, $tagidsstr]);
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
     *
     * @param string $requpdatedtagflag requpdatedtagflag
     * @param string $reqemail reqemail
     *
     * @return string welcome message
     */
    public static function updated_tag_flag_standard($requpdatedtagflag = '', $reqemail = '') {

        global $DB;
        // Parameter validation.
        // REQUIRED.
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
     *
     * @param string $reqcombtagdata reqcombtagdata
     * @param string $reqemail reqemail
     *
     * @return string welcome message
     */
    public static function combine_tags_data($reqcombtagdata = '', $reqemail = '') {

        global $DB;
        // Parameter validation.
        // REQUIRED.
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

                    $taginstanceexistanticlock = $DB->get_record(
                        'tag_instance',
                        ['tagid' => $value->itemid, 'itemid' => $id],
                        'id'
                    );

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
     *
     * @param string $reqcatdatadelete reqcatdatadelete
     *
     * @return string welcome message
     */
    public static function categories_data_delete($reqcatdatadelete = '') {

        global $DB;
        // Parameter validation.
        // REQUIRED.
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
     * course_data_move_sync
     */
    public static function course_data_move_sync_parameters() {
        return new external_function_parameters(
            array(
                'course_data' => new external_value(PARAM_RAW, 'Course Data', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Sync course from Leeloo to Moodle
     *
     * @param string $reqcatsdata reqcatsdata
     *
     * @return string welcome message
     */
    public static function course_data_move_sync($reqcatsdata = '') {

        global $DB;
        // Parameter validation.
        // REQUIRED.
        $params = self::validate_parameters(
            self::course_data_move_sync_parameters(),
            array(
                'course_data' => $reqcatsdata,
            )
        );

        $value = (object) json_decode($reqcatsdata, true); // Object.

        $values = '';
        foreach ($value as $catkey => $courseid) {
            $values .= $catkey . ',' . $courseid;

            $sql = "SELECT id FROM {course} where id = ?";
            $coursedetail = $DB->get_record_sql($sql, [$courseid]);

            $sql = "SELECT id FROM {course_categories} where id = ?";
            $catdetail = $DB->get_record_sql($sql, [$catkey]);

            if (!empty($coursedetail) && !empty($catdetail)) {

                $data = new stdClass();
                $data->id = $courseid;
                $data->category = $catkey;
                $DB->update_record('course', $data);
            }
        }

        return 1;
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function course_data_move_sync_returns() {
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
     *
     * @param string $reqcatsdata reqcatsdata
     *
     * @return string welcome message
     */
    public static function categories_data_sync($reqcatsdata = '') {

        global $DB;
        // Parameter validation.
        // REQUIRED.
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

        if (!empty($value->moodle_parent_cat_id) && !empty($parentcatdetail)) {

            if ($value->depth == 1 || $value->depth == '1') {
                $value->parent = '0';
            } else {
                $value->parent = $value->moodle_cat_id;
            }
            if (!empty($returnid)) {
                $value->id = 1;
            }

            if ($isinsert == 0) {
                $value->id = $returnid;
            }
        } else if (!empty($value->moodle_cat_id) && !empty($catdetail)) {

            if ($value->depth == 1 || $value->depth == '1') {
                $value->parent = '0';
            } else {
                $value->parent = $value->moodle_cat_id;
            }
            $returnid = $value->id = $value->moodle_cat_id;

            $isinsert = 0;
        } else {

            if ($value->depth == 1 || $value->depth == '1') {
                $falsevar = 1;
            } else {
                if (!empty($catdetail)) {
                    $value->parent = $value->moodle_cat_id;
                } else {
                    $value->parent = 0;
                }
            }
        }

        $isparentfound = 0;
        if (!empty($value->is_parent)) {
            $isparentfound = $value->is_parent;
            unset($value->is_parent);
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
            // Insert/update child cat.
            if ($value->depth == 1 || $value->depth == '1') {
                $value->path = '/' . $catdetail->id;
            } else {
                $value->path = $catdetail->path . '/' . $returnid;
            }
        } else if (!empty($value->moodle_cat_id) && !empty($catdetail)) {
            // Update cat.
            if ($value->depth == 1 || $value->depth == '1') {
                $value->path = '/' . $catdetail->id;
            } else {
                $value->path = $catdetail->path . '/' . $returnid;
            }
        } else {
            // Insert top cat.
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

        $updatenewdata = ['path' => $value->path, 'id' => $returnid];
        if (!empty($isparentfound)) {
            $updatenewdata['parent'] = $isparentfound;
        }
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
     *
     * @param string $reqmoodleuserid reqmoodleuserid
     * @param string $reqemail reqemail
     *
     * @return string welcome message
     */
    public static function get_moodle_user_id($reqmoodleuserid = '', $reqemail = '') {

        global $DB;
        // Parameter validation.
        // REQUIRED.
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
     *
     * @param string $requserid requserid
     * @param string $reqgetmoodleuser reqgetmoodleuser
     *
     * @return string welcome message
     */
    public static function get_moodle_user($requserid = '', $reqgetmoodleuser = '') {

        global $DB;
        // Parameter validation.
        // REQUIRED.
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
     *
     * @param string $reqgradeletter1 reqgradeletter1
     *
     * @return string welcome message
     */
    public static function gradeletter1($reqgradeletter1 = '') {

        global $DB;
        // Parameter validation.
        // REQUIRED.
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
     *
     * @param string $reqemail reqemail
     * @param string $reqgetuserid reqgetuserid
     *
     * @return string welcome message
     */
    public static function get_userid($reqemail = '', $reqgetuserid = '') {

        global $DB;
        // Parameter validation.
        // REQUIRED.
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
     *
     * @param string $reqleelooactdata reqleelooactdata
     * @param string $reqcourseid reqcourseid
     *
     * @return string welcome message
     */
    public static function leelo_activity_data($reqleelooactdata = '', $reqcourseid = '') {

        global $DB;
        // Parameter validation.
        // REQUIRED.
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
     *
     * @param string $reqleeloodata reqleeloodata
     * @param string $reqcourseid reqcourseid
     * @param string $reqprojectstartdate reqprojectstartdate
     * @param string $reqprojectenddate reqprojectenddate
     *
     * @return string welcome message
     */
    public static function leelo_data($reqleeloodata = '', $reqcourseid = '', $reqprojectstartdate = '', $reqprojectenddate = '') {

        global $DB;
        // Parameter validation.
        // REQUIRED.
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
     *
     * @param string $reqlsyncgradeletter reqlsyncgradeletter
     *
     * @return string welcome message
     */
    public static function sync_grade_letter($reqlsyncgradeletter = '') {

        global $DB;
        // Parameter validation.
        // REQUIRED.
        $params = self::validate_parameters(
            self::sync_grade_letter_parameters(),
            array(
                'sync_grade_letter' => $reqlsyncgradeletter,
            )
        );

        $response = (object) json_decode($reqlsyncgradeletter, true);

        $lastinsertedid = 0;
        if (empty($response->contextid)) {
            $response->contextid = 1;
        }

        $override = $response->override;
        unset($response->override);

        if (!empty($response->contextid) && $response->contextid != 1) {
            $contextid = context_course::instance($response->contextid);
            $response->contextid = $contextid->id;
        }

        $DB->delete_records('grade_letters', ['contextid' => $response->contextid]);

        if (!empty($response)) {
            if ($override != 0) {
                $contextid = $response->contextid;
                unset($response->contextid);

                foreach ($response as $key => $value) {
                    $value['contextid'] = $contextid;

                    $data = (object) $value;

                    if (!empty($lastinsertedid) && $data->lowerboundary == "0.00000") {
                        $data->id = $lastinsertedid;

                        $DB->update_record('grade_letters', $data);
                    } else {

                        if ($data->lowerboundary == "0.00000") {
                            $lastinsertedid = $DB->insert_record('grade_letters', $data);
                        } else {

                            $DB->insert_record('grade_letters', $data);
                        }
                    }
                }
            }
        }

        return $lastinsertedid;
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
     *
     * @param string $reqsynccoursegradesettings reqsynccoursegradesettings
     *
     * @return string welcome message
     */
    public static function sync_course_grade_settings($reqsynccoursegradesettings = '') {

        global $DB;
        // Parameter validation.
        // REQUIRED.
        $params = self::validate_parameters(
            self::sync_course_grade_settings_parameters(),
            array(
                'sync_course_grade_settings' => $reqsynccoursegradesettings,
            )
        );

        $response = (object) json_decode($reqsynccoursegradesettings, true);

        $courseid = $response->courseid;
        unset($response->courseid);
        $lastinsertedid = 0;

        if (!empty($response)) {
            $DB->delete_records('grade_settings', ['courseid' => $courseid]);

            foreach ($response as $key => $value) {
                if ($value != '-1') {
                    $insertdata = [
                        'courseid' => $courseid,
                        'name' => $key,
                        'value' => $value,
                    ];
                    $lastinsertedid = $DB->insert_record('grade_settings', $insertdata);
                }
            }
        }

        return $lastinsertedid;
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
     *
     * @param string $reqsyncprefrencegraderreport reqsyncprefrencegraderreport
     *
     * @return string welcome message
     */
    public static function sync_prefrence_grader_report($reqsyncprefrencegraderreport = '') {

        global $DB;
        // Parameter validation.
        // REQUIRED.
        $params = self::validate_parameters(
            self::sync_prefrence_grader_report_parameters(),
            array(
                'sync_prefrence_grader_report' => $reqsyncprefrencegraderreport,
            )
        );

        $response = (object) json_decode($reqsyncprefrencegraderreport, true);

        $email = $response->email;
        unset($response->email);
        $lastinsertedid = 0;

        $usersdata = $DB->get_record('user', ['email' => $email], 'id');

        if (!empty($response) && !empty($usersdata)) {
            $usersid = $usersdata->id;

            foreach ($response as $key => $value) {
                $DB->delete_records('user_preferences', ['userid' => $usersid, 'name' => $key]);

                if ($value != 'default') {
                    $insertdata = [
                        'userid' => $usersid,
                        'name' => $key,
                        'value' => $value,
                    ];
                    $lastinsertedid = $DB->insert_record('user_preferences', $insertdata);
                }
            }
        }

        return $lastinsertedid;
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
     *
     * @param string $reqsyncscales reqsyncscales
     *
     * @return string welcome message
     */
    public static function sync_scales($reqsyncscales = '') {

        global $DB;
        // Parameter validation.
        // REQUIRED.
        $params = self::validate_parameters(
            self::sync_scales_parameters(),
            array(
                'sync_scales' => $reqsyncscales,
            )
        );

        $value = (object) json_decode($reqsyncscales, true);

        $returnedid = 0;
        $email = $value->email;
        $usersdata = $DB->get_record('user', ['email' => $email], 'id');
        if (!empty($usersdata)) {
            $usersid = $usersdata->id;

            $data = [
                'courseid' => $value->courseid,
                'userid' => $usersid,
                'name' => $value->name,
                'scale' => $value->scale,
                'description' => $value->description,
                'descriptionformat' => 1,
            ];

            $data['timemodified'] = strtotime("now");

            if (!empty($value->moodle_scale_id)) {
                $sql = "SELECT * FROM {scale} where id = ?";
                $scaledetail = $DB->get_record_sql($sql, [$value->moodle_scale_id]);
                if (!empty($scaledetail)) {
                    $data['id'] = $value->moodle_scale_id;
                    $DB->update_record('scale', $data);
                    $returnedid = $value->moodle_scale_id;
                } else {
                    $returnedid = $DB->insert_record('scale', $data);
                }
            } else {
                $returnedid = $DB->insert_record('scale', $data);
            }
        }

        return $returnedid;
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
     *
     * @param string $reqcategoriesdata reqcategoriesdata
     * @param string $reqgradedata reqgradedata
     *
     * @return string welcome message
     */
    public static function categories_data_grades($reqcategoriesdata = '', $reqgradedata = '') {

        global $DB;
        // Parameter validation.
        // REQUIRED.
        $params = self::validate_parameters(
            self::categories_data_grades_parameters(),
            array(
                'categories_data' => $reqcategoriesdata,
                'grade_data' => $reqgradedata,
            )
        );

        $catreturnedid = 0;
        $itemreturnedid = 0;
        $categoriedata = (object) json_decode($reqcategoriesdata, true);
        $gradesdata = (object) json_decode($reqgradedata, true);

        $moodleparentid = $categoriedata->moodle_parent_id;
        unset($categoriedata->moodle_parent_id);

        if (!empty($categoriedata) && !empty($moodleparentid)) {
            $parentcatdata = $DB->get_record('grade_categories', ['id' => $moodleparentid], '*');

            if (!empty($categoriedata->old_cat_id)) {
                unset($categoriedata->path);
                unset($categoriedata->parent);
                $categoriedata->id = $categoriedata->old_cat_id;
                unset($categoriedata->old_cat_id);

                $DB->update_record('grade_categories', $categoriedata);

                $catreturnedid = $categoriedata->id;
            } else {

                $categoriedata->path = '';
                $categoriedata->parent = $moodleparentid;
                $catreturnedid = $DB->insert_record('grade_categories', $categoriedata);

                $updatenewdata = ['path' => $parentcatdata->path . $catreturnedid . '/', 'id' => $catreturnedid];
                $updatenewdata = (object) $updatenewdata;
                $DB->update_record('grade_categories', $updatenewdata);
            }
        }

        if (!empty($gradesdata)) {
            if (!empty($gradesdata->item_moodle_cat_id)) {
                $gradesdata->categoryid = $gradesdata->item_moodle_cat_id;
            } else {

                unset($gradesdata->categoryid);
            }

            unset($gradesdata->item_moodle_cat_id);

            if (!empty($gradesdata->old_item_id)) {
                $gradesdata->id = $gradesdata->old_item_id;

                unset($gradesdata->old_item_id);
                unset($gradesdata->weightoverride);

                $DB->update_record('grade_items', $gradesdata);

                $itemreturnedid = $gradesdata->id;
            } else {

                if (!empty($catreturnedid)) {
                    $gradesdata->iteminstance = $catreturnedid;

                    $itemreturnedid = $DB->insert_record('grade_items', $gradesdata);
                } else if (!empty($gradesdata->categoryid)) {

                    unset($gradesdata->iteminstance);

                    $itemreturnedid = $DB->insert_record('grade_items', $gradesdata);
                }
            }
        }

        return $catreturnedid . ',' . $itemreturnedid;
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
     *
     * @param string $reqdeletegradeitem reqdeletegradeitem
     *
     * @return string welcome message
     */
    public static function delete_grade_item($reqdeletegradeitem = '') {

        global $DB;
        // Parameter validation.
        // REQUIRED.
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
     *
     * @param string $reqhiddendata reqhiddendata
     *
     * @return string welcome message
     */
    public static function grade_hidden_data($reqhiddendata = '') {

        global $DB;
        // Parameter validation.
        // REQUIRED.
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

                $sql = "UPDATE {grade_items} SET hidden = '$response->hidden'
                WHERE categoryid = '$response->id' or  iteminstance = '$response->id' ";

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
     *
     * @param string $reqduplicatedata reqduplicatedata
     *
     * @return string welcome message
     */
    public static function grade_duplicate_data($reqduplicatedata = '') {

        global $DB;
        // Parameter validation.
        // REQUIRED.
        $params = self::validate_parameters(
            self::grade_duplicate_data_parameters(),
            array(
                'duplicate_data' => $reqduplicatedata,
            )
        );

        $response = (object) json_decode($reqduplicatedata, true);

        $returnedid = 0;

        if (!empty($response->id)) {
            $gradesdata = $DB->get_record('grade_items', ['id' => $response->id]);

            unset($gradesdata->id);

            $gradesdata->itemname = $gradesdata->itemname . ' (copy)';

            $returnedid = $DB->insert_record('grade_items', $gradesdata);
        }

        return $returnedid;
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
     *
     * @param string $reqgradeitemorderchangedata reqgradeitemorderchangedata
     * @param string $reqcategoryid reqcategoryid
     *
     * @return string welcome message
     */
    public static function gradeitem_order_change_data($reqgradeitemorderchangedata = '', $reqcategoryid = '') {

        global $DB;
        // Parameter validation.
        // REQUIRED.
        $params = self::validate_parameters(
            self::gradeitem_order_change_data_parameters(),
            array(
                'gradeitem_order_change_data' => $reqgradeitemorderchangedata,
                'category_id' => $reqcategoryid,
            )
        );

        $response = (object) json_decode($reqgradeitemorderchangedata, true);
        $catid = (object) json_decode($reqcategoryid, true);

        if (!empty($response) && !empty($catid->moodle_cat_id)) {
            foreach ($response as $key => $value) {

                $itemsdata = ['categoryid' => $catid->moodle_cat_id];

                $itemsdata = (object) $itemsdata;

                $itemsdata->id = $value['moodle_tbl_id'];

                $DB->update_record('grade_items', $itemsdata);
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

    /**
     * Function to change category order
     *
     * @param stdclass $childcats child_cats
     * @param stdclass $currentcat currentcat
     * @param string $depth depth
     *
     * @return external_description
     */
    public function change_cate_order($childcats, $currentcat, $depth = null) {
        global $DB;

        foreach ($childcats as $key => $value) {
            $path = str_replace('/' . $currentcat->id, '', $value->path);

            if (empty($depth)) {
                $depth = $currentcat->depth;
                $parent = $currentcat->parent;
            } else {
                $parent = $value->parent;
            }
            $categoriedata = [
                'parent' => $parent,
                'depth' => $depth,
                'path' => $path,
            ];
            $categoriedata = (object) $categoriedata;

            $categoriedata->id = $value->id;

            $DB->update_record('grade_categories', $categoriedata);

            $childcatcurrent = $DB->get_records('grade_categories', ['parent' => $value->id]);

            if (!empty($childcatcurrent)) {
                change_cate_order($childcatcurrent, $currentcat, $value->depth);
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
     *
     * @param string $reqdeletegradecategory reqdeletegradecategory
     *
     * @return string welcome message
     */
    public static function delete_grade_category($reqdeletegradecategory = '') {

        global $DB;
        // Parameter validation.
        // REQUIRED.
        $params = self::validate_parameters(
            self::delete_grade_category_parameters(),
            array(
                'delete_grade_category' => $reqdeletegradecategory,
            )
        );

        $response = (object) json_decode($reqdeletegradecategory, true);

        if (!empty($response->id)) {
            $parentcatdata = $DB->get_records('grade_categories', ['parent' => $response->id]);

            if (!empty($parentcatdata)) {
                $currentcat = $DB->get_record('grade_categories', ['id' => $response->id], '*');
            }
            $sql = "SELECT id FROM {grade_items} WHERE categoryid = ?";
            $childitems = $DB->get_records_sql($sql, [$response->id]);
            $currentcat = $DB->get_record('grade_categories', ['id' => $response->id], '*');

            if (!empty($childitems)) {
                foreach ($childitems as $key => $value) {
                    $itemmsdata = [
                        'categoryid' => $currentcat->parent,
                    ];
                    $itemmsdata = (object) $itemmsdata;

                    $itemmsdata->id = $value->id;

                    $DB->update_record('grade_items', $itemmsdata);
                }
            }

            $sql = "SELECT * FROM {grade_items} WHERE iteminstance = ? AND itemtype != 'mod' ";

            $result = $DB->get_records_sql($sql, [$response->id]);

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
     *
     * @param string $reqglograusersettings reqglobalgradeusersettings
     *
     * @return string welcome message
     */
    public static function global_grade_user_settings($reqglograusersettings = '') {

        global $DB;
        // Parameter validation.
        // REQUIRED.
        $params = self::validate_parameters(
            self::global_grade_user_settings_parameters(),
            array(
                'global_grade_user_settings' => $reqglograusersettings,
            )
        );

        $reqglograusersettings = (object) json_decode($reqglograusersettings, true);

        $DB->execute("update {config} set value = '" . $reqglograusersettings->showrank . "'
        where name = 'grade_report_user_showrank'");
        $DB->execute("update {config} set value = '" . $reqglograusersettings->showpercentage . "'
        where name = 'grade_report_user_showpercentage'");
        $DB->execute("update {config} set value = '" . $reqglograusersettings->showgrade . "'
        where name = 'grade_report_user_showgrade'");
        $DB->execute("update {config} set value = '" . $reqglograusersettings->showfeedback . "'
        where name = 'grade_report_user_showfeedback'");
        $DB->execute("update {config} set value = '" . $reqglograusersettings->showrange . "'
        where name = 'grade_report_user_showrange'");
        $DB->execute("update {config} set value = '" . $reqglograusersettings->showweight . "'
        where name = 'grade_report_user_showweight'");
        $DB->execute("update {config} set value = '" . $reqglograusersettings->showaverage . "'
        where name = 'grade_report_user_showaverage'");
        $DB->execute("update {config} set value = '" . $reqglograusersettings->showlettergrade . "'
        where name = 'grade_report_user_showlettergrade'");
        $DB->execute("update {config} set value = '" . $reqglograusersettings->rangedecimals . "'
        where name = 'grade_report_user_rangedecimals'");
        $DB->execute("update {config} set value = '" . $reqglograusersettings->showhiddenitems . "'
        where name = 'grade_report_user_showhiddenitems'");
        $DB->execute("update {config} set value = '" . $reqglograusersettings->showtotalsifcontainhidden . "'
        where name = 'grade_report_user_showtotalsifcontainhidden'");
        $DB->execute("update {config} set value = '" . $reqglograusersettings->showcontributiontocoursetotal . "'
        where name = 'grade_report_user_showcontributiontocoursetotal'");

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
     *
     * @param string $reqgraderreportsettings reqgraderreportsettings
     *
     * @return string welcome message
     */
    public static function global_grade_grader_report_settings($reqgraderreportsettings = '') {

        global $DB;
        // Parameter validation.
        // REQUIRED.
        $params = self::validate_parameters(
            self::global_grade_grader_report_settings_parameters(),
            array(
                'grader_report_settings' => $reqgraderreportsettings,
            )
        );

        $reqgraderreportsettings = (object) json_decode($reqgraderreportsettings, true);

        $DB->execute("update {config} set value = '" . $reqgraderreportsettings->grade_report_studentsperpage . "'
        where name = 'grade_report_studentsperpage'");
        $DB->execute("update {config} set value = '" . $reqgraderreportsettings->grade_report_showonlyactiveenrol . "'
        where name = 'grade_report_showonlyactiveenrol'");
        $DB->execute("update {config} set value = '" . $reqgraderreportsettings->grade_report_quickgrading . "'
        where name = 'grade_report_quickgrading'");
        $DB->execute("update {config} set value = '" . $reqgraderreportsettings->grade_report_showquickfeedback . "'
        where name = 'grade_report_showquickfeedback'");
        $DB->execute("update {config} set value = '" . $reqgraderreportsettings->grade_report_meanselection . "'
        where name = 'grade_report_meanselection'");
        $DB->execute("update {config} set value = '" . $reqgraderreportsettings->grade_report_enableajax . "'
        where name = 'grade_report_enableajax'");
        $DB->execute("update {config} set value = '" . $reqgraderreportsettings->grade_report_showcalculations . "'
        where name = 'grade_report_showcalculations'");
        $DB->execute("update {config} set value = '" . $reqgraderreportsettings->grade_report_showeyecons . "'
        where name = 'grade_report_showeyecons'");
        $DB->execute("update {config} set value = '" . $reqgraderreportsettings->grade_report_showaverages . "'
        where name = 'grade_report_showaverages'");
        $DB->execute("update {config} set value = '" . $reqgraderreportsettings->grade_report_showlocks . "'
        where name = 'grade_report_showlocks'");
        $DB->execute("update {config} set value = '" . $reqgraderreportsettings->grade_report_showranges . "'
        where name = 'grade_report_showranges'");
        $DB->execute("update {config} set value = '" . $reqgraderreportsettings->grade_report_showanalysisicon . "'
        where name = 'grade_report_showanalysisicon'");
        $DB->execute("update {config} set value = '" . $reqgraderreportsettings->grade_report_showuserimage . "'
        where name = 'grade_report_showuserimage'");
        $DB->execute("update {config} set value = '" . $reqgraderreportsettings->grade_report_showactivityicons . "'
        where name = 'grade_report_showactivityicons'");
        $DB->execute("update {config} set value = '" . $reqgraderreportsettings->grade_report_shownumberofgrades . "'
        where name = 'grade_report_shownumberofgrades'");
        $DB->execute("update {config} set value = '" . $reqgraderreportsettings->grade_report_averagesdisplaytype . "'
        where name = 'grade_report_averagesdisplaytype'");
        $DB->execute("update {config} set value = '" . $reqgraderreportsettings->grade_report_rangesdisplaytype . "'
        where name = 'grade_report_rangesdisplaytype'");
        $DB->execute("update {config} set value = '" . $reqgraderreportsettings->grade_report_averagesdecimalpoints . "'
        where name = 'grade_report_averagesdecimalpoints'");
        $DB->execute("update {config} set value = '" . $reqgraderreportsettings->grade_report_rangesdecimalpoints . "'
        where name = 'grade_report_rangesdecimalpoints'");

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
     *
     * @param string $reqscaledelete reqscaledelete
     *
     * @return string welcome message
     */
    public static function global_scale_delete($reqscaledelete = '') {

        global $DB;
        // Parameter validation.
        // REQUIRED.
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
     *
     * @param string $reqglobalgradeoverview reqglobalgradeoverview
     *
     * @return string welcome message
     */
    public static function global_grade_overview($reqglobalgradeoverview = '') {

        global $DB;
        // Parameter validation.
        // REQUIRED.
        $params = self::validate_parameters(
            self::global_grade_overview_parameters(),
            array(
                'global_grade_overview' => $reqglobalgradeoverview,
            )
        );

        $reqglobalgradeoverview = (object) json_decode($reqglobalgradeoverview, true);

        $DB->execute("update {config} set value = '" . $reqglobalgradeoverview->showrank . "'
        where name = 'grade_report_overview_showrank'");
        $DB->execute("update {config} set value = '" . $reqglobalgradeoverview->showtotalsifcontainhidden . "'
        where name = 'grade_report_overview_showtotalsifcontainhidden'");

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
     *
     * @param string $reqglobalgradehistory reqglobalgradehistory
     *
     * @return string welcome message
     */
    public static function global_grade_history($reqglobalgradehistory = '') {

        global $DB;
        // Parameter validation.
        // REQUIRED.
        $params = self::validate_parameters(
            self::global_grade_history_parameters(),
            array(
                'global_grade_history' => $reqglobalgradehistory,
            )
        );

        $reqglobalgradehistory = (object) json_decode($reqglobalgradehistory, true);

        $DB->execute("update {config} set value = '" . $reqglobalgradehistory->pages . "'
        where name = 'grade_report_historyperpage'");

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
     *
     * @param string $reqgradeitemsettings reqgradeitemsettings
     *
     * @return string welcome message
     */
    public static function global_grade_item_settings($reqgradeitemsettings = '') {

        global $DB;
        // Parameter validation.
        // REQUIRED.
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

        $DB->execute("update {config} set value = '" . $gradedisplaytype . "' where name = 'grade_displaytype'");
        $DB->execute("update {config} set value = '" . $gradedecimalpoints . "' where name = 'grade_decimalpoints'");
        $DB->execute("update {config} set value = '" . $gradeitemadvanced . "' where name = 'grade_item_advanced'");

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
     *
     * @param string $reqgradecategorysettings reqgradecategorysettings
     *
     * @return string welcome message
     */
    public static function global_grade_category_settings($reqgradecategorysettings = '') {

        global $DB;
        // Parameter validation.
        // REQUIRED.
        $params = self::validate_parameters(
            self::global_grade_category_settings_parameters(),
            array(
                'grade_category_settings' => $reqgradecategorysettings,
            )
        );
        $reqgradecategorysettings = (object) json_decode($reqgradecategorysettings, true);

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

        $DB->execute("update {config} set value = '" . $gradehideforcedsettings . "'
        where name = 'grade_hideforcedsettings'");
        $DB->execute("update {config} set value = '" . $gradeaggregation . "'
        where name = 'grade_aggregation'");
        $DB->execute("update {config} set value = '" . $gradeaggregationflag . "'
        where name = 'grade_aggregation_flag'");
        $DB->execute("update {config} set value = '" . $gradeaggregationsvisible . "'
        where name = 'grade_aggregations_visible'");
        $DB->execute("update {config} set value = '" . $gradeaggregateonlygraded . "'
        where name = 'grade_aggregateonlygraded'");
        $DB->execute("update {config} set value = '" . $gradeaggregateonlygradedflag . "'
        where name = 'grade_aggregateonlygraded_flag'");
        $DB->execute("update {config} set value = '" . $gradeaggregateoutcomes . "'
        where name = 'grade_aggregateoutcomes'");
        $DB->execute("update {config} set value = '" . $gradeaggregateoutcomesflag . "'
        where name = 'grade_aggregateoutcomes_flag'");
        $DB->execute("update {config} set value = '" . $gradekeephigh . "'
        where name = 'grade_keephigh'");
        $DB->execute("update {config} set value = '" . $gradekeephighflag . "'
        where name = 'grade_keephigh_flag'");
        $DB->execute("update {config} set value = '" . $gradedroplow . "'
        where name = 'grade_droplow'");
        $DB->execute("update {config} set value = '" . $gradedroplowflag . "'
        where name = 'grade_droplow_flag'");
        $DB->execute("update {config} set value = '" . $gradeoverridecat . "'
        where name = 'grade_overridecat'");

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
     *
     * @param string $reqglobalgradedata reqglobalgradedata
     *
     * @return string welcome message
     */
    public static function global_grade_settings($reqglobalgradedata = '') {

        global $DB;
        // Parameter validation.
        // REQUIRED.
        $params = self::validate_parameters(
            self::global_grade_settings_parameters(),
            array(
                'global_grade_data' => $reqglobalgradedata,
            )
        );

        $reqglobalgradedata = (object) json_decode($reqglobalgradedata, true);

        $DB->execute("update {config} set value = '" . $reqglobalgradedata->bookmoodlerole . "'
        where name = 'gradebookroles'");
        $DB->execute("update {config} set value = '" . $reqglobalgradedata->grade_profilereport . "'
        where name = 'grade_profilereport'");
        $DB->execute("update {config} set value = '" . $reqglobalgradedata->grade_aggregationposition . "'
        where name = 'grade_aggregationposition'");
        $DB->execute("update {config} set value = '" . $reqglobalgradedata->grade_includescalesinaggregation . "'
        where name = 'grade_includescalesinaggregation'");
        $DB->execute("update {config} set value = '" . $reqglobalgradedata->grade_export_displaytype . "'
        where name = 'grade_export_displaytype'");
        $DB->execute("update {config} set value = '" . $reqglobalgradedata->grade_export_decimalpoints . "'
        where name = 'grade_export_decimalpoints'");
        $DB->execute("update {config} set value = '" . $reqglobalgradedata->grade_navmethod . "'
        where name = 'grade_navmethod'");
        $DB->execute("update {config} set value = '" . $reqglobalgradedata->grade_export_userprofilefields . "'
        where name = 'grade_export_userprofilefields'");
        $DB->execute("update {config} set value = '" . $reqglobalgradedata->grade_export_customprofilefields . "'
        where name = 'grade_export_customprofilefields'");
        $DB->execute("update {config} set value = '" . $reqglobalgradedata->gradeexport . "'
        where name = 'gradeexport'");
        $DB->execute("update {config} set value = '" . $reqglobalgradedata->unlimitedgrades . "'
        where name = 'unlimitedgrades'");
        $DB->execute("update {config} set value = '" . $reqglobalgradedata->grade_report_showmin . "'
        where name = 'grade_report_showmin'");
        $DB->execute("update {config} set value = '" . $reqglobalgradedata->gradepointmax . "'
        where name = 'gradepointmax'");
        $DB->execute("update {config} set value = '" . $reqglobalgradedata->gradepointdefault . "'
        where name = 'gradepointdefault'");
        $DB->execute("update {config} set value = '" . $reqglobalgradedata->grade_minmaxtouse . "'
        where name = 'grade_minmaxtouse'");
        $DB->execute("update {config} set value = '" . $reqglobalgradedata->grade_mygrades_report . "'
        where name = 'grade_mygrades_report'");
        $DB->execute("update {config} set value = '" . $reqglobalgradedata->recovergradesdefault . "'
        where name = 'recovergradesdefault'");
        $DB->execute("update {config} set value = '" . $reqglobalgradedata->gradereport_mygradeurl . "'
        where name = 'gradereport_mygradeurl'");
        $DB->execute("update {config} set value = '" . $reqglobalgradedata->grade_hiddenasdate . "'
        where name = 'grade_hiddenasdate'");
        $DB->execute("update {config} set value = '" . $reqglobalgradedata->gradepublishing . "'
        where name = 'gradepublishing'");
        $DB->execute("update {config} set value = '" . $reqglobalgradedata->grade_export_exportfeedback . "'
        where name = 'grade_export_exportfeedback'");

        return '1';
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function global_grade_settings_returns() {
        return new external_value(PARAM_TEXT, 'Returns true');
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_analytics_data_parameters() {
        return new external_function_parameters(
            array(
                'params' => new external_value(PARAM_RAW, 'Params', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Analytics data Moodle to Leeloo
     *
     * @param string $reqparams params
     *
     * @return string welcome message
     */
    public static function get_analytics_data($reqparams = '') {

        global $DB, $CFG;
        // Parameter validation.
        // REQUIRED.
        $params = self::validate_parameters(
            self::get_analytics_data_parameters(),
            array(
                'params' => $reqparams,
            )
        );

        $params = json_decode($reqparams, true);

        require_once($CFG->dirroot . '/local/leeloolxpapi/classes/analytics_functions.php');

        $functionname = $params['function'];

        if (function_exists($functionname)) {
            $data = array(
                'responsestring' => "function_available",
                'functioname' => $functionname,
                'response' => $functionname($params)
            );
        } else {
            $data = array(
                'responsestring' => "function_not_available",
                'functioname' => $functionname,
                'response' => ""
            );
        }

        return json_encode($data);
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_analytics_data_returns() {
        return new external_value(PARAM_RAW, 'Analytics data');
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function course_enroll_parameters() {

        return new external_function_parameters(
            array(
                'product_id' => new external_value(PARAM_RAW, 'Product Id', VALUE_DEFAULT, null),
                'username' => new external_value(PARAM_RAW, 'Username', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Enroll course on payment.
     *
     * @param string $reqproductid reqproductid
     * @param string $requsername requsername
     * @return string welcome message
     */
    public static function course_enroll($reqproductid = '', $requsername = '') {

        global $DB;
        // Parameter validation.
        // REQUIRED.
        $params = self::validate_parameters(
            self::course_enroll_parameters(),
            array(
                'product_id' => $reqproductid,
                'username' => $requsername,
            )
        );

        $productid = $reqproductid;
        $username = $requsername;

        $courseidarr = $DB->get_record_sql("SELECT courseid FROM {tool_leeloo_courses_sync} Where productid = ?", [$productid]);
        $courseid = $courseidarr->courseid;

        $useridarr = $DB->get_record_sql("SELECT id FROM {user} Where username = ?", [$username]);
        $userid = $useridarr->id;

        if ($courseid && $userid) {

            $enrolled = 1;

            $dbuser = $DB->get_record('user', array('id' => $userid, 'deleted' => 0), '*', MUST_EXIST);
            $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
            $context = context_course::instance($course->id);
            if (!is_enrolled($context, $dbuser)) {
                $enrol = enrol_get_plugin('manual');
                if ($enrol === null) {
                    $enrolled = 0;
                }
                $instances = enrol_get_instances($course->id, true);
                $manualinstance = null;
                foreach ($instances as $instance) {
                    if ($instance->enrol == 'manual') {
                        $manualinstance = $instance;
                        break;
                    }
                }
                if ($manualinstance == null) {
                    $instanceid = $enrol->add_default_instance($course);
                    if ($instanceid === null) {
                        $instanceid = $enrol->add_instance($course);
                    }
                    $manualinstance = $DB->get_record('enrol', array('id' => $instanceid));
                }

                $contextdata = $DB->get_record('context', array('contextlevel' => 50, 'instanceid' => $course->id));

                if ($contextdata->id) {
                    $DB->execute(
                        "INSERT INTO {role_assignments} (roleid, contextid, userid, modifierid) VALUES (?, ?, ?, ?)",
                        [5, $contextdata->id, $userid, 2]
                    );
                }

                $DB->execute(
                    "INSERT INTO {user_enrolments} (status, enrolid, userid) VALUES (?, ?, ?)",
                    [0, $manualinstance->id, $userid]
                );
            }
        }

        return '1';
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function course_enroll_returns() {
        return new external_value(PARAM_TEXT, 'Returns true');
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function ar_enroll_parameters() {
        return new external_function_parameters(
            array(
                'product_id' => new external_value(PARAM_RAW, 'Product Id', VALUE_DEFAULT, null),
                'username' => new external_value(PARAM_RAW, 'Username', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Enroll AR on payment.
     *
     * @param string $reqproductid reqproductid
     * @param string $requsername requsername
     * @return string welcome message
     */
    public static function ar_enroll($reqproductid = '', $requsername = '') {

        global $DB;
        // Parameter validation.
        // REQUIRED.
        $params = self::validate_parameters(
            self::ar_enroll_parameters(),
            array(
                'product_id' => $reqproductid,
                'username' => $requsername,
            )
        );

        $productid = $reqproductid;
        $username = $requsername;

        $courseidarr = $DB->get_record_sql(
            "SELECT courseid FROM {tool_leeloo_ar_sync} Where productid = ?",
            [$productid]
        );
        $courseid = $courseidarr->courseid;

        $useridarr = $DB->get_record_sql(
            "SELECT id FROM {user} Where username = ?",
            [$username]
        );

        $userid = $useridarr->id;

        if ($courseid && $userid) {
            $DB->execute(
                "INSERT INTO {tool_leeloo_ar_sync_restrict} (arid,userid, productid) VALUES (?, ? , ?)",
                [$courseid, $userid, $productid]
            );
            $enrolled = 1;
        }

        return '1';
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function ar_enroll_returns() {
        return new external_value(PARAM_TEXT, 'Returns true');
    }
}
