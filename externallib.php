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
            'format' => 'flexsections',
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

        $DB->execute("INSERT INTO {tool_leeloolxp_sync}

        ( courseid, sectionid, activityid, enabled, teamnio_task_id, is_quiz)

        VALUES ( ?, ?, ?, '1', ?,'0')", [$returnid, '0', '0', '0']);

        $catreturnid = 0;
        $itemreturnid = 0;
        $categoriesdata = (object) json_decode($reqcategoriesdata, true);
        $gradedata = (object) json_decode($reqgradedata, true);

        // If not empty , then insert category  , no need to check for update.
        if (!empty($categoriesdata) && $isinsert) {
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
            $gradedata->categoryid = $catreturnid;
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

        if (isset($ardata->ispremium)) {
            $ispremium = $ardata->ispremium;
            $moddata['ispremium'] = $ispremium;
        }

        if (isset($ardata->instructor)) {
            $instructor = $ardata->instructor;
            $moddata['instructor'] = $instructor;
        }

        if (isset($ardata->isfree)) {
            $isfree = $ardata->isfree;
            $moddata['isfree'] = $isfree;
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

        if (isset($ardata->m_completionview)) {
            $mcompletionview = $ardata->m_completionview;
            $data['completionview'] = $mcompletionview;
        } else {
            $mcompletionview = 0;
        }

        if (isset($ardata->m_completiongradeitemnumber)) {
            $completiongradeitemnumber = $ardata->m_completiongradeitemnumber;
            $data['completiongradeitemnumber'] = $completiongradeitemnumber;
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
            $data['groupmode'] = $mgroupmode;
        }

        if (isset($ardata->m_groupingid)) {
            $mgroupingid = $ardata->m_groupingid;
            $data['groupingid'] = $mgroupingid;
        }

        $activityid = $ardata->activity_id;

        $action = $ardata->action;

        $countupdatescm = count($data);

        $data['id'] = $activityid;

        $data = (object) $data;

        if ($activityid != '') {

            if ($action == 'edit') {
                if ($countupdatescm > 0) {
                    $DB->update_record('course_modules', $data);
                }

                $ararr = $DB->get_record_sql("SELECT module,instance FROM {course_modules} where id = ?", [$activityid]);
                $module = $ararr->module;
                $modinstance = $ararr->instance;

                if (isset($ardata->gradepass)) {
                    $gradepass = $ardata->gradepass;
                    $DB->execute(
                        "update {grade_items} set gradepass = ? WHERE iteminstance = ? AND itemmodule = ?",
                        [$gradepass, $modinstance, 'quiz']
                    );
                }

                $modarr = $DB->get_record_sql("SELECT name FROM {modules} where id = ?", [$module]);
                $modulename = $modarr->name;

                if (isset($ardata->completionattemptsexhausted) && $modulename == 'quiz') {
                    $mcompletionattemptsexhausted = $ardata->completionattemptsexhausted;
                    $moddata['completionattemptsexhausted'] = $mcompletionattemptsexhausted;
                }
                if (isset($ardata->completionpass) && $modulename == 'quiz') {
                    $completionpass = $ardata->completionpass;
                    $moddata['completionpass'] = $completionpass;
                }

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
            } else if ($action == 'delete') {
                $data = array();
                $data['id'] = $activityid;
                $data['deletioninprogress'] = 1;

                $data = (object) $data;

                $DB->update_record('course_modules', $data);
            } else if ($action == 'add') {
                $courseid = $ardata->course_id;
                $artype = $ardata->artype;
                $arname = $ardata->arname;
                $section = $ardata->sectionid;

                $modulesdata = $DB->get_record('modules', ['name' => $artype]);
                if ($modulesdata->id) {

                    if ($artype == 'page') {
                        $dp = 'a:3:{s:12:"printheading";s:1:"1";s:10:"printintro";s:1:"0";s:17:"printlastmodified";s:1:"1";}';
                        $sectiondata = array();
                        $sectiondata['course'] = $courseid;
                        $sectiondata['name'] = $arname;
                        $sectiondata['intro'] = '';
                        $sectiondata['introformat'] = 1;
                        $sectiondata['content'] = $content;
                        $sectiondata['contentformat'] = 1;
                        $sectiondata['legacyfiles'] = 0;
                        $sectiondata['legacyfileslast'] = null;
                        $sectiondata['display'] = 5;
                        $sectiondata['displayoptions'] = $dp;
                        $sectiondata['revision'] = 1;
                        $sectiondata['timemodified'] = time();

                        $sectiondata = (object) $sectiondata;

                        $instance = $DB->insert_record('page', $sectiondata);
                    } else if ($artype == 'leeloolxpvimeo') {
                        $dp = 'a:3:{s:12:"printheading";s:1:"1";s:10:"printintro";s:1:"0";s:17:"printlastmodified";s:1:"1";}';
                        $sectiondata = array();
                        $sectiondata['course'] = $courseid;
                        $sectiondata['name'] = $arname;
                        $sectiondata['intro'] = '';
                        $sectiondata['introformat'] = 1;
                        $sectiondata['vimeo_video_id'] = $ardata->vimeo_video_id;
                        $sectiondata['ispremium'] = $ardata->ispremium;
                        $sectiondata['isfree'] = $ardata->isfree;
                        $sectiondata['instructor'] = $ardata->instructor;
                        $sectiondata['vimeo_token'] = '';
                        $sectiondata['width'] = 640;
                        $sectiondata['height'] = 320;
                        $sectiondata['border'] = 0;
                        $sectiondata['allow'] = '';
                        $sectiondata['content'] = $content;
                        $sectiondata['contentformat'] = 1;
                        $sectiondata['legacyfiles'] = 0;
                        $sectiondata['legacyfileslast'] = null;
                        $sectiondata['display'] = 5;
                        $sectiondata['displayoptions'] = $dp;
                        $sectiondata['revision'] = 1;
                        $sectiondata['timemodified'] = time();

                        $sectiondata = (object) $sectiondata;

                        $instance = $DB->insert_record('leeloolxpvimeo', $sectiondata);
                    } else if ($artype == 'quiz') {
                        $sectiondata = array();
                        $sectiondata['course'] = $courseid;
                        $sectiondata['name'] = $arname;
                        $sectiondata['intro'] = '';
                        $sectiondata['introformat'] = 1;
                        $sectiondata['timeopen'] = 0;
                        $sectiondata['timeclose'] = 0;
                        $sectiondata['timelimit'] = 0;
                        $sectiondata['overduehandling'] = 'autosubmit';
                        $sectiondata['graceperiod'] = 0;
                        $sectiondata['preferredbehaviour'] = 'deferredfeedback';
                        $sectiondata['canredoquestions'] = 0;
                        $sectiondata['attempts'] = 0;
                        $sectiondata['attemptonlast'] = 0;
                        $sectiondata['grademethod'] = 1;
                        $sectiondata['decimalpoints'] = 2;
                        $sectiondata['questiondecimalpoints'] = '-1';
                        $sectiondata['reviewattempt'] = 69888;
                        $sectiondata['reviewcorrectness'] = 4352;
                        $sectiondata['reviewmarks'] = 4352;
                        $sectiondata['reviewspecificfeedback'] = 4352;
                        $sectiondata['reviewgeneralfeedback'] = 4352;
                        $sectiondata['reviewrightanswer'] = 4352;
                        $sectiondata['reviewoverallfeedback'] = 4352;
                        $sectiondata['questionsperpage'] = 1;
                        $sectiondata['navmethod'] = 'free';
                        $sectiondata['shuffleanswers'] = 1;
                        $sectiondata['sumgrades'] = '0.00000';
                        $sectiondata['grade'] = '0.00000';
                        $sectiondata['timecreated'] = time();
                        $sectiondata['timemodified'] = time();
                        $sectiondata['password'] = '';
                        $sectiondata['subnet'] = '';
                        $sectiondata['browsersecurity'] = '-';
                        $sectiondata['delay1'] = 0;
                        $sectiondata['delay2'] = 0;
                        $sectiondata['showuserpicture'] = 0;
                        $sectiondata['showblocks'] = 0;
                        $sectiondata['completionattemptsexhausted'] = 0;
                        $sectiondata['completionpass'] = 0;
                        $sectiondata['allowofflineattempts'] = 0;
                        $sectiondata['quiztype'] = $ardata->quiztype;

                        $sectiondata = (object) $sectiondata;

                        $instance = $DB->insert_record('quiz', $sectiondata);

                        $sectiondata = array();
                        $sectiondata['quizid'] = $instance;
                        $sectiondata['firstslot'] = 1;
                        $sectiondata['heading'] = '';
                        $sectiondata['shufflequestions'] = 0;

                        $sectiondata = (object) $sectiondata;
                        $DB->insert_record('quiz_sections', $sectiondata);

                        $gradecategory = $DB->get_record('grade_categories', ['courseid' => $courseid], 'id');

                        if (!empty($gradecategory)) {
                            $gradecategoryid = $gradecategory->id;
                        } else {
                            $gradecategoryid = '1';
                        }

                        $sectiondata = array();
                        $sectiondata['courseid'] = $courseid;
                        $sectiondata['categoryid'] = $gradecategoryid;
                        $sectiondata['itemname'] = $arname;
                        $sectiondata['itemtype'] = 'mod';
                        $sectiondata['itemmodule'] = 'quiz';
                        $sectiondata['iteminstance'] = $instance;
                        $sectiondata['itemnumber'] = 0;
                        $sectiondata['iteminfo'] = null;
                        $sectiondata['idnumber'] = null;
                        $sectiondata['calculation'] = null;
                        $sectiondata['gradetype'] = 1;
                        $sectiondata['grademax'] = '10.00000';
                        $sectiondata['grademin'] = '0.00000';
                        $sectiondata['scaleid'] = null;
                        $sectiondata['outcomeid'] = null;
                        $sectiondata['gradepass'] = '0.00000';
                        $sectiondata['multfactor'] = '1.00000';
                        $sectiondata['plusfactor'] = '0.00000';
                        $sectiondata['aggregationcoef'] = '0.00000';
                        $sectiondata['aggregationcoef2'] = '0.01538';
                        $sectiondata['sortorder'] = 1;
                        $sectiondata['display'] = 0;
                        $sectiondata['decimals'] = null;
                        $sectiondata['hidden'] = 0;
                        $sectiondata['locked'] = 0;
                        $sectiondata['locktime'] = 0;
                        $sectiondata['needsupdate'] = 0;
                        $sectiondata['weightoverride'] = 0;
                        $sectiondata['timecreated'] = time();
                        $sectiondata['timemodified'] = time();

                        $sectiondata = (object) $sectiondata;
                        $DB->insert_record('grade_items', $sectiondata);
                    }

                    if ($instance) {
                        $sectiondata = array();
                        $sectiondata['course'] = $courseid;
                        $sectiondata['module'] = $modulesdata->id;
                        $sectiondata['instance'] = $instance;
                        $sectiondata['section'] = $section;
                        $sectiondata['idnumber'] = '';
                        $sectiondata['added'] = time();
                        $sectiondata['score'] = 0;
                        $sectiondata['indent'] = 0;
                        $sectiondata['visible'] = 1;
                        $sectiondata['visibleoncoursepage'] = 1;
                        $sectiondata['visibleold'] = 1;
                        $sectiondata['groupmode'] = 0;
                        $sectiondata['groupingid'] = 0;
                        $sectiondata['completion'] = 0;
                        $sectiondata['completiongradeitemnumber'] = null;
                        $sectiondata['completionexpected'] = 0;
                        $sectiondata['showdescription'] = 0;
                        $sectiondata['deletioninprogress'] = 0;
                        $sectiondata['availability'] = null;
                        $sectiondata['completionview'] = $mcompletionview;

                        $sectiondata = (object) $sectiondata;

                        $coursemodulesinstance = $DB->insert_record('course_modules', $sectiondata);

                        $sectiondata = $DB->get_record('course_sections', ['id' => $section], 'sequence');

                        $sectionsequence = $sectiondata->sequence;

                        if ($sectionsequence != '') {
                            $newsectionsequence = $sectionsequence . ',' . $coursemodulesinstance;
                        } else {
                            $newsectionsequence = $coursemodulesinstance;
                        }

                        $data = array();
                        $data['id'] = $section;
                        $data['sequence'] = $newsectionsequence;

                        $data = (object) $data;

                        $DB->update_record('course_sections', $data);

                        return $coursemodulesinstance . '--' . $instance;
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
        $sql = "SELECT sortorder FROM {course_categories} ORDER BY sortorder DESC ";
        $catorderdata = $DB->get_record_sql($sql);
        if (!empty($catorderdata)) {
            $catorderdata->sortorder = (int)$catorderdata->sortorder;
            $value->sortorder = $catorderdata->sortorder + 10000;
        } else {
            $value->sortorder = 10000;
        }
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

        $reqemail = json_decode($reqemail, true);
        $email = base64_decode($reqemail[1], true);
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
                    $gradesdata->categoryid = $gradesdata->iteminstance = $catreturnedid;

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

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function section_sync_parameters() {
        return new external_function_parameters(
            array(
                'section_data' => new external_value(PARAM_RAW, 'Section Data', VALUE_DEFAULT, null),
                'email' => new external_value(PARAM_RAW, 'Email', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Sync course section from Leeloo to Moodle
     *
     * @param string $reqardata reqardata
     * @param string $reqemail reqemail
     *
     * @return string welcome message
     */
    public static function section_sync($reqardata = '', $reqemail = '') {

        global $DB;
        // Parameter validation.
        // REQUIRED.
        $params = self::validate_parameters(
            self::section_sync_parameters(),
            array(
                'section_data' => $reqardata,
                'email' => $reqemail,
            )
        );

        $ardata = (object) json_decode($reqardata, true);

        if (isset($reqemail)) {
            $email = (object) json_decode($reqemail, true);
        }

        if (isset($ardata->action)) {
            $courseid = $ardata->course_id;
            $action = $ardata->action;
            $sectionname = $ardata->sectionname;
            $parentsectionid = $ardata->parentsectionid;
            if ($action == 'addsection') {
                $coursedata = $DB->get_record('course', ['id' => $courseid]);

                $sectionlast = $DB->get_record_sql(
                    "SELECT max(section) as lastsection FROM {course_sections} WHERE course = ?",
                    [$courseid]
                );

                $lastsection = $sectionlast->lastsection;
                $newsection = $lastsection + 1;

                $sectiondata = array();
                $sectiondata['course'] = $courseid;
                $sectiondata['section'] = $newsection;
                $sectiondata['name'] = $sectionname;
                $sectiondata['summaryformat'] = 1;
                $sectiondata['sequence'] = '';
                $sectiondata['visible'] = 1;
                $sectiondata['availability'] = null;
                $sectiondata['timemodified'] = time();

                $sectiondata = (object) $sectiondata;

                $sectionid = $DB->insert_record('course_sections', $sectiondata);

                if ($coursedata->format == 'flexsections') {

                    $courseformatdata = array();
                    $courseformatdata['courseid'] = $courseid;
                    $courseformatdata['format'] = 'flexsections';
                    $courseformatdata['sectionid'] = $sectionid;
                    $courseformatdata['name'] = 'collapsed';
                    $courseformatdata['value'] = 0;

                    $courseformatdata = (object) $courseformatdata;

                    $DB->insert_record('course_format_options', $courseformatdata);

                    $sectioniddata = $DB->get_record('course_sections', ['id' => $parentsectionid]);

                    if ($sectioniddata->section) {
                        $sectionparent = $sectioniddata->section;
                    } else {
                        $sectionparent = 0;
                    }

                    $courseformatdata = array();
                    $courseformatdata['courseid'] = $courseid;
                    $courseformatdata['format'] = 'flexsections';
                    $courseformatdata['sectionid'] = $sectionid;
                    $courseformatdata['name'] = 'parent';
                    $courseformatdata['value'] = $sectionparent;

                    $courseformatdata = (object) $courseformatdata;

                    $DB->insert_record('course_format_options', $courseformatdata);

                    $courseformatdata = array();
                    $courseformatdata['courseid'] = $courseid;
                    $courseformatdata['format'] = 'flexsections';
                    $courseformatdata['sectionid'] = $sectionid;
                    $courseformatdata['name'] = 'visibleold';
                    $courseformatdata['value'] = 1;

                    $courseformatdata = (object) $courseformatdata;

                    $DB->insert_record('course_format_options', $courseformatdata);
                }

                return $sectionid;
            } else if ($action == 'delete') {
                $sectionid = $ardata->section_id;

                $DB->delete_records('course_sections', ['id' => $sectionid]);
                $DB->delete_records('course_format_options', ['sectionid' => $sectionid]);
                return 1;
            } else if ($action == 'editsection') {
                $sectionid = $ardata->section_id;
                $sectionname = $ardata->sectionname;
                $updatenewdata = ['name' => $sectionname, 'id' => $sectionid];
                $updatenewdata = (object) $updatenewdata;
                $DB->update_record('course_sections', $updatenewdata);
                return 1;
            } else if ($action == 'ordersection') {
                $courseid = $ardata->course_id;
                $order = $ardata->order;

                $allsectiondata = $DB->get_records('course_sections', ['course' => $courseid]);

                if (!empty($allsectiondata)) {
                    $time = time();
                    $count = 1;
                    foreach ($allsectiondata as $key => $section) {
                        $updatesectionorder = ['section' => $time + $count, 'id' => $section->id];
                        $updatesectionorder = (object) $updatesectionorder;
                        $DB->update_record('course_sections', $updatesectionorder);
                        $count++;
                    }
                }

                if (!empty($order)) {
                    foreach ($order as $key => $section) {

                        $checksectiondata = $DB->get_record('course_sections', ['course' => $courseid, 'section' => $key]);

                        if (!$checksectiondata) {
                            $updatesectionorder = ['section' => $key, 'id' => $section];
                            $updatesectionorder = (object) $updatesectionorder;
                            $DB->update_record('course_sections', $updatesectionorder);
                        }
                    }
                }

                return 1;
            } else if ($action == 'parentflexsection') {
                $courseid = $ardata->course_id;
                $parentsdata = $ardata->parent;
                $orderdata = $ardata->order;

                foreach ($parentsdata as $thissection => $parent) {
                    $flexparentsectionid = $DB->get_record('course_sections', ['id' => $parent]);

                    $flexparentsection = $flexparentsectionid->section;
                    if (!$flexparentsection) {
                        $flexparentsection = 0;
                    }

                    $DB->execute(
                        "update {course_format_options} set value = ? WHERE sectionid = ? AND name = ?",
                        [$flexparentsection, $thissection, 'parent']
                    );
                }

                return 1;
            } else if ($action == 'syncflexorder') {
                $courseid = $ardata->course_id;
                $orderdata = $ardata->order;

                $allsectiondata = $DB->get_records('course_sections', ['course' => $courseid]);

                if (!empty($allsectiondata)) {
                    $time = time();
                    $count = 1;
                    foreach ($allsectiondata as $key => $section) {

                        $coursesectionold = $DB->get_record('course_sections', ['id' => $section->id]);
                        $coursesectionoldsection = $coursesectionold->section;

                        $DB->execute(
                            "update {course_sections} set section = ? WHERE id = ?",
                            [$time + $count, $section->id]
                        );

                        $DB->execute(
                            "update {course_format_options} set value = ? WHERE courseid = ? AND name = ? AND value = ?",
                            [$time + $count, $courseid, 'parent', $coursesectionoldsection]
                        );

                        $count++;
                    }
                }

                if (!empty($orderdata)) {
                    foreach ($orderdata as $key => $section) {

                        $coursesectionold = $DB->get_record('course_sections', ['id' => $section]);
                        $coursesectionoldsection = $coursesectionold->section;

                        $DB->execute(
                            "update {course_sections} set section = ? WHERE id = ?",
                            [$key, $section]
                        );

                        $DB->execute(
                            "update {course_format_options} set value = ? WHERE courseid = ? AND name = ? AND value = ?",
                            [$key, $courseid, 'parent', $coursesectionoldsection]
                        );
                    }
                }

                return 1;
            } else if ($action == 'editcourse') {
                $id = $ardata->course_id;
                if (!empty($ardata->idnumber)) {
                    $idnumber = $ardata->idnumber;
                    $updatenewdata = ['idnumber' => $idnumber, 'id' => $id];
                    $updatenewdata = (object) $updatenewdata;
                    $DB->update_record('course', $updatenewdata);
                }
                if (!empty($ardata->startdate)) {
                    $updatenewdata = [];
                    $startdate = $ardata->startdate;
                    $updatenewdata = ['startdate' => $startdate, 'id' => $id];
                    $updatenewdata = (object) $updatenewdata;
                    $DB->update_record('course', $updatenewdata);
                }
                if (!empty($ardata->enddate)) {
                    $updatenewdata = [];
                    $enddate = $ardata->enddate;
                    $updatenewdata = ['enddate' => $enddate, 'id' => $id];
                    $updatenewdata = (object) $updatenewdata;
                    $DB->update_record('course', $updatenewdata);
                }
                if (isset($ardata->visible)) {
                    $updatenewdata = [];
                    $visible = $ardata->visible;
                    $updatenewdata = ['visible' => $visible, 'id' => $id];
                    $updatenewdata = (object) $updatenewdata;
                    $DB->update_record('course', $updatenewdata);
                }
                if (!empty($ardata->fullname)) {
                    $updatenewdata = [];
                    $fullname = $ardata->fullname;
                    $updatenewdata = ['fullname' => $fullname, 'id' => $id];
                    $updatenewdata = (object) $updatenewdata;
                    $DB->update_record('course', $updatenewdata);
                }
                if (!empty($ardata->summary)) {
                    $updatenewdata = [];
                    $summary = $ardata->summary;
                    $updatenewdata = ['summary' => $summary, 'id' => $id];
                    $updatenewdata = (object) $updatenewdata;
                    $DB->update_record('course', $updatenewdata);
                }
                if (isset($ardata->sectionvisible)) {
                    $sectionvisible = $ardata->sectionvisible;
                    $sectionid = $ardata->sectionid;
                    $coursesections = $DB->get_record('course_sections', ['id' => $sectionid], 'section');
                    if (!empty($coursesections)) {
                        $sectionval = $coursesections->section;
                        $DB->execute("update {course_sections} set visible = ? where id = ? ", [$sectionvisible, $sectionid]);
                        $coursesectionskills = $DB->get_records_sql("SELECT sectionid FROM {course_format_options} where courseid = '$id' AND name = 'parent' AND value = '$sectionval' ");
                        if (!empty($coursesectionskills)) {
                            foreach ($coursesectionskills as $keycoursesectionskills => $valuecoursesectionskills) {
                                $coursesectionsnew = $DB->get_record('course_sections', ['id' => $valuecoursesectionskills->sectionid], 'section');
                                $sectionvalnew = $coursesectionsnew->section;
                                $DB->execute("update {course_sections} set visible = ? where id = ? ", [$sectionvisible, $valuecoursesectionskills->sectionid]);
                                $coursesectionsunits = $DB->get_records('course_format_options', ['courseid' => $id, 'name' => 'parent', 'value' => $sectionvalnew]);
                                if (!empty($coursesectionsunits)) {
                                    foreach ($coursesectionsunits as $keycoursesectionunits => $valuecoursesectionunits) {
                                        $DB->execute("update {course_sections} set visible = ? where id = ? ", [$sectionvisible, $valuecoursesectionunits->sectionid]);
                                    }
                                }
                            }
                        }
                    }
                }


                return 1;
            } else if ($action == 'updateAccessCompletion') {
                $videodataupdate = array();
                $quizdataupdate = array();

                if (isset($ardata->v_completion)) {
                    $completion = $ardata->v_completion;
                    $videodataupdate['completion'] = $completion;
                }
                if (isset($ardata->v_completionview)) {
                    $mcompletionview = $ardata->v_completionview;
                    $videodataupdate['completionview'] = $mcompletionview;
                }
                if (isset($ardata->v_completiongradeitemnumber)) {
                    $completiongradeitemnumber = $ardata->v_completiongradeitemnumber;
                    $videodataupdate['completiongradeitemnumber'] = $completiongradeitemnumber;
                }


                if (isset($ardata->q_completion)) {
                    $completion = $ardata->q_completion;
                    $quizdataupdate['completion'] = $completion;
                }
                if (isset($ardata->q_completiongradeitemnumber)) {
                    $completiongradeitemnumber = $ardata->q_completiongradeitemnumber;
                    $quizdataupdate['completiongradeitemnumber'] = $completiongradeitemnumber;
                }

                $modulesdataquiz = $DB->get_record('modules', ['name' => 'quiz']);
                $quizmoduleid = $modulesdataquiz->id;
                $modulesdatavimeovideo = $DB->get_record('modules', ['name' => 'leeloolxpvimeo']);
                $vimeovideomoduleid = $modulesdatavimeovideo->id;

                $quizzesdata = $DB->get_records('course_modules', ['course' => $courseid, 'module' => $quizmoduleid]);
                $videosdata = $DB->get_records('course_modules', ['course' => $courseid, 'module' => $vimeovideomoduleid]);


                if (isset($ardata->v_availability)) {
                    $mavailability = $ardata->v_availability;
                    $mavailability = str_ireplace('&lt;', '<', $mavailability);
                    $mavailability = str_ireplace('&gt;', '>', $mavailability);
                    $videodataupdate['availability'] = $mavailability;
                }

                if (isset($ardata->q_availability)) {
                    $mavailability = $ardata->q_availability;
                    $mavailability = str_ireplace('&lt;', '<', $mavailability);
                    $mavailability = str_ireplace('&gt;', '>', $mavailability);
                    $quizdataupdate['availability'] = $mavailability;
                }

                if (isset($ardata->is_delete)) {
                    $videodataupdate['availability'] = '';
                    $videodataupdate['completion'] = 0;
                    $videodataupdate['completionview'] = 0;
                    $videodataupdate['completiongradeitemnumber'] = '';
                    $quizdataupdate['availability'] = '';
                    $quizdataupdate['completion'] = 0;
                    $quizdataupdate['completionview'] = 0;
                    $quizdataupdate['completiongradeitemnumber'] = '';
                }

                if (!empty($videosdata)) {
                    $videodataupdate = (object) $videodataupdate;
                    foreach ($videosdata as $keytemppp => $vdata) {
                        $videodataupdate->id = $vdata->id;
                        $DB->update_record('course_modules', $videodataupdate);
                    }
                }

                if (!empty($quizzesdata)) {
                    $quizdataupdate = (object) $quizdataupdate;
                    foreach ($quizzesdata as $keytemppp => $qdataa) {
                        $quizdataupdate->id = $qdataa->id;
                        $DB->update_record('course_modules', $quizdataupdate);

                        $moddata = [];
                        if (isset($ardata->q_completionattemptsexhausted)) {
                            $mcompletionattemptsexhausted = $ardata->q_completionattemptsexhausted;
                            $moddata['completionattemptsexhausted'] = $mcompletionattemptsexhausted;
                        }
                        if (isset($ardata->q_completionpass)) {
                            $completionpass = $ardata->q_completionpass;
                            $moddata['completionpass'] = $completionpass;
                        }

                        if (isset($ardata->is_delete)) {
                            $moddata['completionattemptsexhausted'] = 0;
                            $moddata['completionpass'] = 0;
                        }

                        $countupdatesmd = count($moddata);

                        $moddata['id'] = $qdataa->instance;

                        $moddata = (object) $moddata;

                        if ($countupdatesmd > 0) {
                            $DB->update_record('quiz', $moddata);
                        }
                    }
                }
            }
        }
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function section_sync_returns() {
        return new external_value(PARAM_TEXT, 'Returns id');
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function questionsync_parameters() {
        return new external_function_parameters(
            array(
                'questions_data' => new external_value(PARAM_RAW, 'Question Data', VALUE_DEFAULT, null),
                'email' => new external_value(PARAM_RAW, 'Email', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Questions sync.
     *
     * @param string $reqquestiondata reqquestiondata
     * @param string $reqemail reqemail
     * @return string welcome message
     */
    public static function questionsync($reqquestiondata = '', $reqemail = '') {

        global $DB;
        // Parameter validation.
        // REQUIRED.
        $params = self::validate_parameters(
            self::questionsync_parameters(),
            array(
                'questions_data' => $reqquestiondata,
                'email' => $reqemail,
            )
        );

        $questiondata = (object) json_decode($reqquestiondata, true);

        $userid = '2';
        if (isset($reqemail)) {
            $email = (object) json_decode($reqemail, true);
            $userdata = $DB->get_record('user', ['email' => $email->scalar], 'id');
            if (!empty($userdata)) {
                $userid = $userdata->id;
            }
        }

        $questionidsarr = [];
        $answeridsarr = [];

        $action = $questiondata->action;

        if ($action == 'add') {

            if (!empty($questiondata->activity_data)) {

                foreach ($questiondata->activity_data as $keyactivity => $valueactivity) {

                    $activityid = $valueactivity['activity_id'];

                    if (!empty($activityid) && !empty($questiondata->questions_data[$keyactivity])) {

                        $modulesdata = $DB->get_record('course_modules', ['id' => $activityid]);

                        if (!empty($modulesdata)) {

                            $tempdata = $DB->get_record_sql(
                                "SELECT shortname,category FROM {course} where id = ? ",
                                [$questiondata->course_id]
                            );
                            $contextdata = $DB->get_record('context', ['instanceid' => $questiondata->course_id, 'contextlevel' => '50']);

                            if (empty($contextdata)) {

                                $coursecatid = $tempdata->category;
                                $contextdata2 = $DB->get_record(
                                    'context',
                                    ['instanceid' => $coursecatid, 'depth' => '2', 'contextlevel' => '40'],
                                    'id'
                                );
                                $pathcontext = '/1/' . $contextdata2->id;
                                $catnewdata = array();
                                $catnewdata['contextlevel'] = '50';
                                $catnewdata['instanceid'] = $questiondata->course_id;
                                $catnewdata['path'] = $pathcontext;
                                $catnewdata['locked'] = 0;
                                $catnewdata['depth'] = '3';
                                $catnewdata = (object) $catnewdata;
                                $lastiddtemp = $DB->insert_record('context', $catnewdata);

                                $catnewdata = array();
                                $catnewdata['path'] = $pathcontext . '/' . $lastiddtemp;
                                $catnewdata = (object) $catnewdata;
                                $catnewdata->id = $lastiddtemp;
                                $DB->update_record('context', $catnewdata);
                                $contextdata = $DB->get_record(
                                    'context',
                                    ['instanceid' => $questiondata->course_id, 'depth' => '3']
                                );
                            }

                            if (!empty($contextdata)) {

                                $qcdata = $DB->get_record_sql(
                                    "SELECT id FROM {question_categories} where contextid = '$contextdata->id' ORDER BY id DESC"
                                );

                                if (empty($qcdata)) {
                                    $infotext = "The default category for questions shared in context '$tempdata->shortname'. ";

                                    $catnewdata = array();
                                    $catnewdata['name'] = 'top';
                                    $catnewdata['contextid'] = $contextdata->id;
                                    $catnewdata['info'] = $infotext;
                                    $catnewdata['infoformat'] = 0;
                                    $catnewdata['stamp'] = $_SERVER['HTTP_HOST'] . '+' . rand() . '+' . substr(md5(microtime()), rand(0, 26), 6);
                                    $catnewdata['parent'] = 0;
                                    $catnewdata['sortorder'] = 0;
                                    $catnewdata['idnumber'] = null;
                                    $catnewdata = (object) $catnewdata;
                                    $lastidd = $DB->insert_record('question_categories', $catnewdata);

                                    $catnewdata2 = array();
                                    $catnewdata2['name'] = 'Default for ' . $tempdata->shortname;
                                    $catnewdata2['contextid'] = $contextdata->id;
                                    $catnewdata2['info'] = $infotext;
                                    $catnewdata2['infoformat'] = 0;
                                    $catnewdata2['stamp'] = $_SERVER['HTTP_HOST'] . '+' . rand() . '+' . substr(md5(microtime()), rand(0, 26), 6);
                                    $catnewdata2['parent'] = $lastidd;
                                    $catnewdata2['sortorder'] = 0;
                                    $catnewdata2['idnumber'] = null;
                                    $catnewdata2 = (object) $catnewdata2;

                                    $DB->insert_record('question_categories', $catnewdata2);

                                    $qcdata = $DB->get_record_sql(
                                        "SELECT id FROM {question_categories} where contextid = ? ORDER BY id DESC",
                                        [$contextdata->id]
                                    );
                                }

                                if (!empty($qcdata)) {

                                    $quizdataa = $DB->get_record(
                                        'quiz',
                                        [
                                            'course' => $questiondata->course_id,
                                            'name' => $valueactivity['task_name'], 'quiztype' => $valueactivity['quiztype']
                                        ]
                                    );

                                    if (!empty($quizdataa)) {

                                        $quizid = $modulesdata->instance;
                                        $questionsdata = $questiondata->questions_data[$keyactivity];
                                        $questionsid = $questiondata->questionsid;

                                        foreach ($questionsdata as $keyss => $valuess) {

                                            $sectiondata = array();
                                            $sectiondata['penalty'] = '0.3333333';
                                            if ($valuess['qtype'] == 'truefalse') {
                                                $sectiondata['penalty'] = '1.0000000';
                                            }

                                            if (!empty($valuess['question_category_id'])) {
                                                $sectiondata['category'] = $valuess['question_category_id'];
                                            } else {
                                                $sectiondata['category'] = $qcdata->id;
                                            }
                                            $sectiondata['parent'] = 0;
                                            $sectiondata['name'] = $valuess['name'];
                                            $sectiondata['questiontext'] = $valuess['questiontext'];
                                            $sectiondata['questiontextformat'] = 1;
                                            $sectiondata['generalfeedback'] = $valuess['generalfeedback'];
                                            $sectiondata['generalfeedbackformat'] = 1;
                                            $sectiondata['qtype'] = $valuess['qtype'];
                                            $sectiondata['length'] = '1';
                                            $sectiondata['stamp'] = '1';
                                            $sectiondata['version'] = '1';
                                            $sectiondata['hidden'] = '0';
                                            $sectiondata['timecreated'] = time();
                                            $sectiondata['timemodified'] = time();
                                            $sectiondata['createdby'] = $userid;
                                            $sectiondata['modifiedby'] = $userid;
                                            $sectiondata['idnumber'] = null;

                                            $sectiondata = (object) $sectiondata;

                                            if (empty($valuess['mid'])) {

                                                $mqid = $DB->insert_record('question', $sectiondata);
                                                if ($valuess['qtype'] == 'shortanswer') {
                                                    $tempdata = [];
                                                    $tempdata['usecase'] = 0;
                                                    $tempdata['questionid'] = $mqid;
                                                    $DB->insert_record('qtype_shortanswer_options', $tempdata);
                                                } else if ($valuess['qtype'] == 'multichoice') {
                                                    $tempdata = [];

                                                    $tempdata['questionid'] = $mqid;
                                                    $tempdata['layout'] = 0;
                                                    $tempdata['single'] = 1;
                                                    $tempdata['shuffleanswers'] = 1;
                                                    $tempdata['correctfeedback'] = 'Your answer is correct.';
                                                    $tempdata['correctfeedbackformat'] = 1;
                                                    $tempdata['partiallycorrectfeedback'] = 'Your answer is partially correct.';
                                                    $tempdata['partiallycorrectfeedbackformat'] = 1;
                                                    $tempdata['incorrectfeedback'] = 'Your answer is incorrect.';
                                                    $tempdata['incorrectfeedbackformat'] = 1;
                                                    $tempdata['answernumbering'] = 'abc';
                                                    $tempdata['shownumcorrect'] = 1;
                                                    $tempdata['showstandardinstruction'] = 1;
                                                    $DB->insert_record('qtype_multichoice_options', $tempdata);
                                                }

                                                $leelooids = explode(',', $valuess['id']);

                                                $questionidsarr[] = [$leelooids[0] => $mqid];

                                                $quizslotsdata = $DB->get_record_sql(
                                                    "SELECT * FROM {quiz_slots} where quizid = ? ORDER BY id DESC",
                                                    [$quizid]
                                                );
                                                $slott = 1;
                                                if (!empty($quizslotsdata)) {
                                                    $slott = $quizslotsdata->page + 1;
                                                }
                                                $answersdata = array();
                                                $answersdata['slot'] = $slott;
                                                $answersdata['quizid'] = $quizid;
                                                $answersdata['page'] = $slott;
                                                $answersdata['requireprevious'] = 0;
                                                $answersdata['questionid'] = $mqid;
                                                $answersdata['questioncategoryid'] = null;
                                                $answersdata['includingsubcategories'] = null;
                                                $answersdata['maxmark'] = '1.0000000';
                                                $DB->insert_record('quiz_slots', $answersdata);

                                                if (!empty($valuess['video_id'])) {
                                                    $sectiondata->name = $valuess['name'] . ' - vimeo';
                                                    $sectiondata->questiontext = $valuess['description'];
                                                    $sectiondata->parent = 0;
                                                    $sectiondata->qtype = 'description';
                                                    $lasttidd = $DB->insert_record('question', $sectiondata);
                                                    $questionidsarr[] = [$leelooids[1] => $lasttidd];
                                                    $slott++;

                                                    $answersdata = array();
                                                    $answersdata['slot'] = $slott;
                                                    $answersdata['quizid'] = $quizid;
                                                    $answersdata['page'] = $slott;
                                                    $answersdata['requireprevious'] = 0;
                                                    $answersdata['questionid'] = $lasttidd;
                                                    $answersdata['questioncategoryid'] = null;
                                                    $answersdata['includingsubcategories'] = null;
                                                    $answersdata['maxmark'] = '1.0000000';
                                                    $DB->insert_record('quiz_slots', $answersdata);

                                                    $extradatainsert = array();
                                                    $extradatainsert['vimeoid'] = $valuess['video_id'];
                                                    $extradatainsert['questionid'] = $lasttidd;
                                                    $extradatainsert['difficulty'] = '1';
                                                    $DB->insert_record('local_leeloolxptrivias_qd', $extradatainsert);
                                                }
                                            } else {

                                                $leelooids = explode(',', $valuess['mid']);
                                                $mqid = $sectiondata->id = $leelooids[0];
                                                $DB->update_record('question', $sectiondata);

                                                if (!empty($valuess['video_id'])) {

                                                    $tempid = $leelooids[1];
                                                    $sectiondata->name = $valuess['name'] . ' - vimeo';
                                                    $sectiondata->questiontext = $valuess['description'];
                                                    $sectiondata->parent = 0;
                                                    $sectiondata->qtype = 'description';
                                                    $sectiondata->id = $tempid;
                                                    $lasttidd = $DB->update_record('question', $sectiondata);

                                                    $tempdata = $DB->get_record_sql(
                                                        "SELECT id FROM {local_leeloolxptrivias_qd} where questionid = ? ",
                                                        [$tempid]
                                                    );
                                                    $extradatainsert = array();
                                                    $extradatainsert['vimeoid'] = $valuess['video_id'];
                                                    $extradatainsert = (object) $extradatainsert;
                                                    $extradatainsert->id = $tempdata->id;
                                                    $DB->update_record('local_leeloolxptrivias_qd', $extradatainsert);
                                                }
                                            }

                                            $quizslotsdatatotal = $DB->get_record_sql(
                                                "SELECT sum(maxmark) as total FROM {quiz_slots} where quizid = ? ",
                                                [$quizid]
                                            );

                                            if (!empty($quizslotsdatatotal)) {
                                                $sumgrades = $quizslotsdatatotal->total;
                                                $DB->execute("update {quiz} set sumgrades = ? where id = ? ", [$sumgrades, $quizid]);
                                            }

                                            if (!empty($valuess['answers'])) {

                                                $trueid = 0;
                                                $falseid = 0;

                                                foreach ($valuess['answers'] as $keyanswers => $valueanswers) {

                                                    $answersdata = array();
                                                    $answersdata['question'] = $mqid;
                                                    $answersdata['answer'] = $valueanswers['answer'];
                                                    $answersdata['answerformat'] = '1';
                                                    $answersdata['fraction'] = $valueanswers['fraction'];
                                                    $answersdata['feedback'] = $valueanswers['feedback'];
                                                    $answersdata['feedbackformat'] = '1';
                                                    $answersdata = (object) $answersdata;
                                                    if (empty($valueanswers['mid'])) {
                                                        $lastidanswer = $maid = $DB->insert_record(
                                                            'question_answers',
                                                            $answersdata
                                                        );
                                                        $answeridsarr[] = [$valueanswers['id'] => $maid];
                                                    } else {
                                                        $lastidanswer = $answersdata->id = $valueanswers['mid'];
                                                        $DB->update_record('question_answers', $answersdata);
                                                    }

                                                    if ($valueanswers['answer'] == 'True') {
                                                        $trueid = $lastidanswer;
                                                    }

                                                    if ($valueanswers['answer'] == 'False') {
                                                        $falseid = $lastidanswer;
                                                    }
                                                }

                                                if (!empty($falseid) && !empty($trueid) && empty($valuess['mid'])) {
                                                    $answersdata = array();
                                                    $answersdata['question'] = $mqid;
                                                    $answersdata['trueanswer'] = $trueid;
                                                    $answersdata['falseanswer'] = $falseid;
                                                    $DB->insert_record('question_truefalse', $answersdata);
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } else {
            $questionid = $questiondata->questionid;

            $questionidarr = explode(',', $questionid);

            if (!empty($questionidarr)) {

                foreach ($questionidarr as $key => $questionid) {
                    if (!empty($questionid)) {
                        $DB->delete_records('quiz_slots', ['questionid' => $questionid]);
                        $DB->delete_records('question', ['id' => $questionid]);
                        $DB->delete_records('question', ['parent' => $questionid]);
                        $DB->delete_records('qtype_shortanswer_options', ['questionid' => $questionid]);
                        $DB->delete_records('question_answers', ['question' => $questionid]);
                        $DB->delete_records('question_truefalse', ['question' => $questionid]);
                        $DB->delete_records('local_leeloolxptrivias_qd', ['questionid' => $questionid]);
                    }
                }
            }

            return 1;
        }
        $returndataarr = [
            'questionidsarr' => $questionidsarr,
            'answeridsarr' => $answeridsarr,
        ];
        return json_encode($returndataarr);
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function questionsync_returns() {
        return new external_value(PARAM_TEXT, 'Returns true');
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function structurecreator_parameters() {
        return new external_function_parameters(
            array(
                'structure_data' => new external_value(PARAM_RAW, 'Structure Data', VALUE_DEFAULT, null),
                'email' => new external_value(PARAM_RAW, 'Email', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Check and pdate course sections.
     *
     * @param string $courseid courseid
     * @param string $section section
     */
    public static function check_update_course_section($courseid, $section) {
        global $DB;
        // Check if course-section exist
        $csexist = $DB->get_record('course_sections', ['course' => $courseid, 'section' => $section]);
        if (!empty($csexist)) {
            // Update all entries by 1 after and including the record we found
            $updatedata = $DB->get_records_sql("select id from {course_sections} WHERE section >= ? and course = ?  ORDER BY section DESC ", [$section, $courseid]);
            foreach ($updatedata as $key => $value) {
                $DB->execute(
                    "update {course_sections} set section = section + 1 WHERE id = ? ",
                    [$value->id]
                );
            }
        }
    }

    /**
     * Save ar data.
     *
     * @param string $sectionid sectionid
     * @param string $valueskills valueskills
     * @param string $leelooidstring leelooidstring
     * @param string $arcount arcount
     * @param string $startdate startdate
     * @param string $enddate enddate
     * @param string $courseid courseid
     * @param string $quiztype quiztype
     * @return array $returnarr returnarr
     */
    public static function save_ar_data($sectionid, $valueskills, $leelooidstring, $arcount, $startdate, $enddate, $courseid, $quiztype, $userid) {

        global $DB;

        $leelooidarr = explode(',', $leelooidstring);
        $returnarr = [];
        $artype = 'quiz';
        $arname = $valueskills;
        $section = $sectionid;

        for ($i = 0; $i < $arcount; $i++) {

            if (!empty($leelooidarr[$i])) {

                $data = [];
                $moddata = [];
                $moddata['name'] = $valueskills;
                $moddata['intro'] = '';
                $moddata['content'] = '';
                $moddata['vimeo_video_id'] = '';
                $moddata['ispremium'] = 2;
                $moddata['isfree'] = 2;
                $moddata['instructor'] = 0;
                $moddata['quiztype'] = $quiztype;
                $moddata['timeopen'] = '';
                $moddata['timeclose'] = '';
                $data['showdescription'] = 0;
                $data['idnumber'] = '';
                $data['completion'] = '';
                $mcompletionview = $data['completionview'] = 0;
                $data['completiongradeitemnumber'] = 0;
                $mcompletionexpected = $data['completionexpected'] = 0;
                $data['visible'] = 0;
                $data['availability'] = '';
                $data['groupmode'] = 0;
                $data['groupingid'] = 0;

                $modulesdata = $DB->get_record('modules', ['name' => $artype]);

                if ($modulesdata->id) {

                    $sectiondata = array();
                    $sectiondata['course'] = $courseid;
                    $sectiondata['name'] = $arname;
                    $sectiondata['intro'] = '';
                    $sectiondata['introformat'] = 1;
                    $sectiondata['timeopen'] = strtotime($startdate);
                    $sectiondata['timeclose'] = strtotime($enddate);
                    $sectiondata['timelimit'] = 0;
                    $sectiondata['overduehandling'] = 'autosubmit';
                    $sectiondata['graceperiod'] = 0;
                    $sectiondata['preferredbehaviour'] = 'deferredfeedback';
                    $sectiondata['canredoquestions'] = 0;
                    $sectiondata['attempts'] = 0;
                    $sectiondata['attemptonlast'] = 0;
                    $sectiondata['grademethod'] = 1;
                    $sectiondata['decimalpoints'] = 2;
                    $sectiondata['questiondecimalpoints'] = '-1';
                    $sectiondata['reviewattempt'] = 69888;
                    $sectiondata['reviewcorrectness'] = 4352;
                    $sectiondata['reviewmarks'] = 4352;
                    $sectiondata['reviewspecificfeedback'] = 4352;
                    $sectiondata['reviewgeneralfeedback'] = 4352;
                    $sectiondata['reviewrightanswer'] = 4352;
                    $sectiondata['reviewoverallfeedback'] = 4352;
                    $sectiondata['questionsperpage'] = 1;
                    $sectiondata['navmethod'] = 'free';
                    $sectiondata['shuffleanswers'] = 1;
                    $sectiondata['sumgrades'] = '0.00000';
                    $sectiondata['grade'] = '0.00000';
                    $sectiondata['timecreated'] = time();
                    $sectiondata['timemodified'] = time();
                    $sectiondata['password'] = '';
                    $sectiondata['subnet'] = '';
                    $sectiondata['browsersecurity'] = '-';
                    $sectiondata['delay1'] = 0;
                    $sectiondata['delay2'] = 0;
                    $sectiondata['showuserpicture'] = 0;
                    $sectiondata['showblocks'] = 0;
                    $sectiondata['completionattemptsexhausted'] = 0;
                    $sectiondata['completionpass'] = 0;
                    $sectiondata['allowofflineattempts'] = 0;
                    $sectiondata['quiztype'] = $quiztype;

                    $sectiondata = (object) $sectiondata;

                    $instance = $DB->insert_record('quiz', $sectiondata);

                    $sectiondata = array();
                    $sectiondata['quizid'] = $instance;
                    $sectiondata['firstslot'] = 1;
                    $sectiondata['heading'] = '';
                    $sectiondata['shufflequestions'] = 0;

                    $sectiondata = (object) $sectiondata;
                    $DB->insert_record('quiz_sections', $sectiondata);

                    $gradecategory = $DB->get_record('grade_categories', ['courseid' => $courseid], 'id');

                    if (!empty($gradecategory)) {
                        $gradecategoryid = $gradecategory->id;
                    } else {
                        $gradecategoryid = '1';
                    }

                    $sectiondata = array();
                    $sectiondata['courseid'] = $courseid;
                    $sectiondata['categoryid'] = $gradecategoryid;
                    $sectiondata['itemname'] = $arname;
                    $sectiondata['itemtype'] = 'mod';
                    $sectiondata['itemmodule'] = 'quiz';
                    $sectiondata['iteminstance'] = $instance;
                    $sectiondata['itemnumber'] = 0;
                    $sectiondata['iteminfo'] = null;
                    $sectiondata['idnumber'] = null;
                    $sectiondata['calculation'] = null;
                    $sectiondata['gradetype'] = 1;
                    $sectiondata['grademax'] = '10.00000';
                    $sectiondata['grademin'] = '0.00000';
                    $sectiondata['scaleid'] = null;
                    $sectiondata['outcomeid'] = null;
                    $sectiondata['gradepass'] = '0.00000';
                    $sectiondata['multfactor'] = '1.00000';
                    $sectiondata['plusfactor'] = '0.00000';
                    $sectiondata['aggregationcoef'] = '0.00000';
                    $sectiondata['aggregationcoef2'] = '0.01538';
                    $sectiondata['sortorder'] = 1;
                    $sectiondata['display'] = 0;
                    $sectiondata['decimals'] = null;
                    $sectiondata['hidden'] = 0;
                    $sectiondata['locked'] = 0;
                    $sectiondata['locktime'] = 0;
                    $sectiondata['needsupdate'] = 0;
                    $sectiondata['weightoverride'] = 0;
                    $sectiondata['timecreated'] = time();
                    $sectiondata['timemodified'] = time();

                    $sectiondata = (object) $sectiondata;
                    $itemidlast = $DB->insert_record('grade_items', $sectiondata);

                    $gradegradedata = array();
                    $gradegradedata['itemid'] = $itemidlast;
                    $gradegradedata['userid'] = $userid;
                    $gradegradedata['rawgrademax'] = '10.00000';
                    $gradegradedata['rawgrademin'] = '0.00000';
                    $gradegradedata['hidden'] = 0;
                    $gradegradedata['locked'] = 0;
                    $gradegradedata['locktime'] = 0;
                    $gradegradedata['exported'] = 0;
                    $gradegradedata['overridden'] = 0;
                    $gradegradedata['excluded'] = 0;
                    $gradegradedata['feedback'] = '';
                    $gradegradedata['feedbackformat'] = 0;
                    $gradegradedata['information'] = '';
                    $gradegradedata['informationformat'] = 0;
                    $gradegradedata['aggregationstatus'] = 'novalue';
                    $gradegradedata['aggregationweight'] = '0.00000';

                    $gradegradedata = (object) $gradegradedata;
                    $DB->insert_record('grade_grades', $gradegradedata);

                    if ($instance) {
                        $sectiondata = array();
                        $sectiondata['course'] = $courseid;
                        $sectiondata['module'] = $modulesdata->id;
                        $sectiondata['instance'] = $instance;
                        $sectiondata['section'] = $section;
                        $sectiondata['idnumber'] = '';
                        $sectiondata['added'] = time();
                        $sectiondata['score'] = 0;
                        $sectiondata['indent'] = 0;
                        $sectiondata['visible'] = 1;
                        $sectiondata['visibleoncoursepage'] = 1;
                        $sectiondata['visibleold'] = 1;
                        $sectiondata['groupmode'] = 0;
                        $sectiondata['groupingid'] = 0;
                        $sectiondata['completion'] = 0;
                        $sectiondata['completiongradeitemnumber'] = null;
                        $sectiondata['completionexpected'] = 0;
                        $sectiondata['showdescription'] = 0;
                        $sectiondata['deletioninprogress'] = 0;
                        $sectiondata['availability'] = null;
                        $sectiondata['completionview'] = $mcompletionview;

                        $sectiondata = (object) $sectiondata;

                        $coursemodulesinstance = $DB->insert_record('course_modules', $sectiondata);

                        $sectiondata = $DB->get_record('course_sections', ['id' => $section], 'sequence');

                        $sectionsequence = $sectiondata->sequence;

                        if ($sectionsequence != '') {
                            $newsectionsequence = $sectionsequence . ',' . $coursemodulesinstance;
                        } else {
                            $newsectionsequence = $coursemodulesinstance;
                        }

                        $data = array();
                        $data['id'] = $section;
                        $data['sequence'] = $newsectionsequence;

                        $data = (object) $data;

                        $DB->update_record('course_sections', $data);

                        $DB->execute("INSERT INTO {tool_leeloolxp_sync}

                        ( courseid, sectionid, activityid, enabled, teamnio_task_id, is_quiz)

                        VALUES ( ?, ?, ?, '1', ?,'0')", [$courseid, $sectionid, $coursemodulesinstance, $leelooidarr[$i]]);

                        $returnarr[] = [$leelooidarr[$i] => $coursemodulesinstance];
                    }
                }
            }
        }
        return $returnarr;
    }

    /**
     * Structure Create.
     *
     * @param string $reqstructuredata reqstructuredata
     * @param string $reqemail reqemail
     * @return string welcome message
     */
    public static function structurecreator($reqstructuredata = '', $reqemail = '') {

        global $DB;
        // Parameter validation.
        // REQUIRED.
        $params = self::validate_parameters(
            self::structurecreator_parameters(),
            array(
                'structure_data' => $reqstructuredata,
                'email' => $reqemail,
            )
        );

        $structuredata = (object) json_decode($reqstructuredata, true);

        $sql = "SELECT id FROM {user} WHERE id != ? ORDER BY id ASC";
        $userdata = $DB->get_record_sql(
            $sql,
            ['1']
        );
        $userid = $userdata->id;
        if (isset($structuredata->email)) {
            $email = (object) json_decode($structuredata->email, true);
            $userdata = $DB->get_record('user', ['email' => $email->scalar], 'id');
            if (!empty($userdata)) {
                $userid = $userdata->id;
            }
        }

        $courseid = $structuredata->courseid;
        $action = $structuredata->action;

        if ($action == 'addstructure') {

            // Check if course entry exist , if not then insert one.
            $coursexist = $DB->get_record('course_sections', ['course' => $courseid]);

            $gradecategory = $DB->get_record('grade_categories', ['courseid' => $courseid], 'id');


            $gradecategoryinsertdata = [];
            $gradeiteminsertdata = [];
            if (empty($gradecategory)) {
                $categoriesdata = [];
                $categoriesdata = (object) $categoriesdata;
                $categoriesdata->path = '/';
                $categoriesdata->courseid = $courseid;
                $categoriesdata->depth = 0;
                $categoriesdata->fullname = '?';
                $categoriesdata->timecreated = time();
                $categoriesdata->timemodified = time();
                $catreturnid = $DB->insert_record('grade_categories', $categoriesdata);
                $updatenewdata = ['path' => '/' . $catreturnid . '/', 'id' => $catreturnid];
                $updatenewdata = (object) $updatenewdata;
                $DB->update_record('grade_categories', $updatenewdata);
                $categoriesdata->path = '/' . $catreturnid . '/';
                $categoriesdata->id = $catreturnid;
                $gradecategoryinsertdata = $categoriesdata;

                $sectiondata = array();
                $sectiondata['courseid'] = $courseid;
                $sectiondata['categoryid'] = $catreturnid;
                $sectiondata['itemtype'] = 'course';
                $sectiondata['iteminstance'] = $catreturnid;
                $sectiondata['itemnumber'] = 0;
                $sectiondata['iteminfo'] = null;
                $sectiondata['idnumber'] = null;
                $sectiondata['calculation'] = null;
                $sectiondata['gradetype'] = 1;
                $sectiondata['grademax'] = '100.00000';
                $sectiondata['grademin'] = '0.00000';
                $sectiondata['scaleid'] = null;
                $sectiondata['outcomeid'] = null;
                $sectiondata['gradepass'] = '0.00000';
                $sectiondata['multfactor'] = '1.00000';
                $sectiondata['plusfactor'] = '0.00000';
                $sectiondata['aggregationcoef'] = '0.00000';
                $sectiondata['aggregationcoef2'] = '0.01538';
                $sectiondata['sortorder'] = 1;
                $sectiondata['display'] = 0;
                $sectiondata['decimals'] = null;
                $sectiondata['hidden'] = 0;
                $sectiondata['locked'] = 0;
                $sectiondata['locktime'] = 0;
                $sectiondata['needsupdate'] = 0;
                $sectiondata['weightoverride'] = 0;
                $sectiondata['timecreated'] = time();
                $sectiondata['timemodified'] = time();

                $sectiondata = (object) $sectiondata;
                $tempinsertedid = $DB->insert_record('grade_items', $sectiondata);
                $sectiondata->id = $tempinsertedid;
                $gradeiteminsertdata = $sectiondata;
            }

            if (empty($coursexist)) {

                $tempdata = array();
                $tempdata['course'] = $courseid;
                $tempdata['section'] = '0';
                $tempdata['name'] = null;
                $tempdata['summaryformat'] = 1;
                $tempdata['sequence'] = '';
                $tempdata['visible'] = 1;
                $tempdata['availability'] = null;
                $tempdata['timemodified'] = time();

                $tempdata = (object) $tempdata;

                $DB->insert_record('course_sections', $tempdata);
                $sectionorder = 0;
            } else {
                $coursexist = $DB->get_record('course_sections', ['course' => $courseid, 'section' => '0']);
                if (!empty($coursexist)) {
                    $sectionorder = 0;
                } else {
                    $sectionorder = -1;
                }
            }

            $skillsets = $structuredata->skillsets;
            $skillsetsleelooid = $structuredata->skillsetsid;
            $skillsetmoodleid = $structuredata->skillsetmoodleid;
            $skills = $structuredata->skills;
            $skillsleelooid = $structuredata->skillsid;
            $skillmoodleid = $structuredata->skillmoodleid;
            $units = $structuredata->units;
            $unitsleelooid = $structuredata->unitsid;
            $unitmoodleid = $structuredata->unitmoodleid;

            $returnidsarray = [];
            $returnarids = [];

            if (!empty($skillsets)) {

                foreach ($skillsets as $keyss => $valuess) {
                    if (!empty($valuess)) {
                        $sectiondata = array();

                        $sectiondata['course'] = $courseid;
                        $sectiondata['name'] = $valuess;
                        $sectiondata['summaryformat'] = 1;
                        $sectiondata['visible'] = 1;
                        $sectiondata['availability'] = null;
                        $sectiondata['timemodified'] = time();

                        $sectionorder++;
                        $sectionorderskillset = $sectionorder;
                        $sectiondata['section'] = $sectionorder;
                        self::check_update_course_section($courseid, $sectionorder);

                        if (empty($skillsetmoodleid[$keyss])) {

                            $sectiondata['sequence'] = '';
                            $sectiondata = (object) $sectiondata;

                            $sectionid = $DB->insert_record('course_sections', $sectiondata);
                            $returnidsarray[] = [$skillsetsleelooid[$keyss] => $sectionid];
                        } else {
                            $sectiondata = (object) $sectiondata;
                            $sectiondata->id = $skillsetmoodleid[$keyss];
                            $sectionid = $DB->update_record('course_sections', $sectiondata);
                        }

                        if (!empty($skills[$keyss])) {
                            foreach ($skills[$keyss] as $keyskills => $valueskills) {
                                if (!empty($valueskills)) {

                                    $sectiondata = array();

                                    $sectiondata['course'] = $courseid;
                                    $sectiondata['name'] = $valueskills;
                                    $sectiondata['summaryformat'] = 1;
                                    $sectiondata['visible'] = 1;
                                    $sectiondata['availability'] = null;
                                    $sectiondata['timemodified'] = time();

                                    $sectionorder++;
                                    $sectionorderskill = $sectionorder;
                                    $sectiondata['section'] = $sectionorder;
                                    self::check_update_course_section($courseid, $sectionorder);

                                    if (empty($skillmoodleid[$keyss][$keyskills])) {
                                        $sectiondata['sequence'] = '';

                                        $sectiondata = (object) $sectiondata;

                                        $lastid = $DB->insert_record('course_sections', $sectiondata);
                                        $returnidsarray[] = [$skillsleelooid[$keyss][$keyskills] => $lastid];

                                        $tempobject = new stdClass();
                                        $tempobject->courseid = $courseid;
                                        $tempobject->format = 'flexsections';
                                        $tempobject->sectionid = $lastid;
                                        $tempobject->name = 'collapsed';
                                        $tempobject->value = 0;
                                        $DB->insert_record('course_format_options', $tempobject);

                                        $tempobject = new stdClass();
                                        $tempobject->courseid = $courseid;
                                        $tempobject->format = 'flexsections';
                                        $tempobject->sectionid = $lastid;
                                        $tempobject->name = 'parent';
                                        $tempobject->value = $sectionorderskillset;
                                        $DB->insert_record('course_format_options', $tempobject);

                                        $tempobject = new stdClass();
                                        $tempobject->courseid = $courseid;
                                        $tempobject->format = 'flexsections';
                                        $tempobject->sectionid = $lastid;
                                        $tempobject->name = 'visibleold';
                                        $tempobject->value = 1;
                                        $DB->insert_record('course_format_options', $tempobject);
                                    } else {

                                        // Update AR names also if Skills name has changed.
                                        $tempdata = $DB->get_record(
                                            'course_sections',
                                            ['id' => $skillmoodleid[$keyss][$keyskills]]
                                        );

                                        if ($tempdata->name != $valueskills) {
                                            $quizdataarr = $DB->get_records(
                                                'quiz',
                                                ['course' => $courseid, 'name' => $tempdata->name]
                                            );
                                            if (!empty($quizdataarr)) {
                                                foreach ($quizdataarr as $keytemppp => $quizdataa) {
                                                    $tempdataobj = array();
                                                    $tempdataobj['name'] = $valueskills;
                                                    $tempdataobj['id'] = $quizdataa->id;
                                                    $tempdataobj = (object) $tempdataobj;
                                                    $DB->update_record('quiz', $tempdataobj);

                                                    $gradedataa = $DB->get_record(
                                                        'grade_items',
                                                        [
                                                            'courseid' => $courseid,
                                                            'itemname' => $tempdata->name,
                                                            'iteminstance' => $quizdataa->id
                                                        ]
                                                    );

                                                    if (!empty($gradedataa)) {
                                                        $tempdataobj = array();
                                                        $tempdataobj['itemname'] = $valueskills;
                                                        $tempdataobj['id'] = $gradedataa->id;
                                                        $tempdataobj = (object) $tempdataobj;
                                                        $DB->update_record('grade_items', $tempdataobj);
                                                    }
                                                }
                                            }
                                        }

                                        $sectiondata = (object) $sectiondata;
                                        $lastid = $sectiondata->id = $skillmoodleid[$keyss][$keyskills];
                                        $DB->update_record('course_sections', $sectiondata);

                                        // Update format table also
                                        $DB->execute(
                                            "update {course_format_options} set value = ? WHERE courseid = ? AND name = ? AND sectionid = ?  AND format = ? ",
                                            [$sectionorderskillset, $courseid, 'parent', $lastid, 'flexsections']
                                        );
                                    }

                                    // Add multiple quizzes.
                                    if (
                                        !empty($structuredata->skill_quiz_numbers)
                                    ) {
                                        foreach ($structuredata->skill_quiz_numbers as $keyquiznumbers => $valuequiznumbers) {
                                            $returnarr = self::save_ar_data(
                                                $lastid,
                                                $valueskills,
                                                $structuredata->skillstaskid[$keyss][$keyskills][$structuredata->skill_quiz_types[$keyquiznumbers]],
                                                $valuequiznumbers,
                                                $structuredata->skill_quiz_start_date[$keyquiznumbers],
                                                $structuredata->skill_quiz_end_date[$keyquiznumbers],
                                                $courseid,
                                                $structuredata->skill_quiz_types[$keyquiznumbers],
                                                $userid
                                            );
                                            $returnarids[] = $returnarr;
                                        }
                                    }

                                    // Add/Edit AR duels for skills.
                                    if (
                                        !empty($structuredata->duels_per_skill)
                                        &&
                                        !empty($structuredata->skillstaskid[$keyss][$keyskills]['duels'])
                                    ) {
                                        $returnarr = self::save_ar_data(
                                            $lastid,
                                            $valueskills,
                                            $structuredata->skillstaskid[$keyss][$keyskills]['duels'],
                                            $structuredata->duels_per_skill,
                                            $structuredata->duels_per_skill_startdate,
                                            $structuredata->duels_per_skill_enddate,
                                            $courseid,
                                            'regularduel',
                                            $userid
                                        );
                                        $returnarids[] = $returnarr;
                                    }

                                    // Add/Edit AR duels for situation.
                                    if (
                                        !empty($structuredata->situations_per_skill)
                                        &&
                                        !empty($structuredata->skillstaskid[$keyss][$keyskills]['situation'])
                                    ) {
                                        $returnarr = self::save_ar_data(
                                            $lastid,
                                            $valueskills,
                                            $structuredata->skillstaskid[$keyss][$keyskills]['situation'],
                                            $structuredata->situations_per_skill,
                                            $structuredata->situations_per_skill_startdate,
                                            $structuredata->situations_per_skill_enddate,
                                            $courseid,
                                            'situation',
                                            $userid
                                        );
                                        $returnarids[] = $returnarr;
                                    }

                                    // Add/Edit AR duels for case.
                                    if (
                                        !empty($structuredata->cases_per_skill)
                                        &&
                                        !empty($structuredata->skillstaskid[$keyss][$keyskills]['case'])
                                    ) {
                                        $returnarr = self::save_ar_data(
                                            $lastid,
                                            $valueskills,
                                            $structuredata->skillstaskid[$keyss][$keyskills]['case'],
                                            $structuredata->cases_per_skill,
                                            $structuredata->cases_per_skill_startdate,
                                            $structuredata->cases_per_skill_enddate,
                                            $courseid,
                                            'case',
                                            $userid
                                        );
                                        $returnarids[] = $returnarr;
                                    }

                                    // Add/Edit AR duels for quest.
                                    if (
                                        !empty($structuredata->quests_per_skill)
                                        &&
                                        !empty($structuredata->skillstaskid[$keyss][$keyskills]['quest'])
                                    ) {
                                        $returnarr = self::save_ar_data(
                                            $lastid,
                                            $valueskills,
                                            $structuredata->skillstaskid[$keyss][$keyskills]['quest'],
                                            $structuredata->quests_per_skill,
                                            $structuredata->quests_per_skill_startdate,
                                            $structuredata->quests_per_skill_enddate,
                                            $courseid,
                                            'quest',
                                            $userid
                                        );
                                        $returnarids[] = $returnarr;
                                    }

                                    $sectionid = $lastid;
                                    if (!empty($units[$keyss][$keyskills])) {
                                        foreach ($units[$keyss][$keyskills] as $keyunits => $valueunits) {
                                            if (!empty($valueunits)) {

                                                $sectiondata = array();

                                                $sectiondata['course'] = $courseid;
                                                $sectiondata['name'] = $valueunits;
                                                $sectiondata['summaryformat'] = 1;
                                                $sectiondata['visible'] = 1;
                                                $sectiondata['availability'] = null;
                                                $sectiondata['timemodified'] = time();
                                                $sectionorder++;
                                                $sectiondata['section'] = $sectionorder;
                                                self::check_update_course_section($courseid, $sectionorder);

                                                if (empty($unitmoodleid[$keyss][$keyskills][$keyunits])) {
                                                    $sectiondata['sequence'] = '';

                                                    $sectiondata = (object) $sectiondata;

                                                    $lastid = $DB->insert_record('course_sections', $sectiondata);
                                                    $returnidsarray[] = [$unitsleelooid[$keyss][$keyskills][$keyunits] => $lastid];

                                                    $tempobject = new stdClass();
                                                    $tempobject->courseid = $courseid;
                                                    $tempobject->format = 'flexsections';
                                                    $tempobject->sectionid = $lastid;
                                                    $tempobject->name = 'collapsed';
                                                    $tempobject->value = 0;
                                                    $DB->insert_record('course_format_options', $tempobject);

                                                    $tempobject = new stdClass();
                                                    $tempobject->courseid = $courseid;
                                                    $tempobject->format = 'flexsections';
                                                    $tempobject->sectionid = $lastid;
                                                    $tempobject->name = 'parent';
                                                    $tempobject->value = $sectionorderskill;
                                                    $DB->insert_record('course_format_options', $tempobject);

                                                    $tempobject = new stdClass();
                                                    $tempobject->courseid = $courseid;
                                                    $tempobject->format = 'flexsections';
                                                    $tempobject->sectionid = $lastid;
                                                    $tempobject->name = 'visibleold';
                                                    $tempobject->value = 1;
                                                    $DB->insert_record('course_format_options', $tempobject);
                                                } else {

                                                    // Update AR names also if Skills name has changed.
                                                    $tempdata = $DB->get_record(
                                                        'course_sections',
                                                        ['id' => $unitmoodleid[$keyss][$keyskills][$keyunits]]
                                                    );
                                                    if ($tempdata->name != $valueunits) {
                                                        $quizdataarr = $DB->get_records(
                                                            'quiz',
                                                            ['course' => $courseid, 'name' => $tempdata->name]
                                                        );
                                                        if (!empty($quizdataarr)) {
                                                            foreach ($quizdataarr as $keytemppp => $quizdataa) {
                                                                $tempdataobj = array();
                                                                $tempdataobj['name'] = $valueunits;
                                                                $tempdataobj['id'] = $quizdataa->id;
                                                                $tempdataobj = (object) $tempdataobj;
                                                                $DB->update_record('quiz', $tempdataobj);

                                                                $gradedataa = $DB->get_record(
                                                                    'grade_items',
                                                                    [
                                                                        'courseid' => $courseid,
                                                                        'itemname' => $tempdata->name,
                                                                        'iteminstance' => $quizdataa->id
                                                                    ]
                                                                );

                                                                if (!empty($gradedataa)) {
                                                                    $tempdataobj = array();
                                                                    $tempdataobj['itemname'] = $valueunits;
                                                                    $tempdataobj['id'] = $gradedataa->id;
                                                                    $tempdataobj = (object) $tempdataobj;
                                                                    $DB->update_record('grade_items', $tempdataobj);
                                                                }
                                                            }
                                                        }
                                                    }

                                                    $sectiondata = (object) $sectiondata;
                                                    $lastid = $sectiondata->id = $unitmoodleid[$keyss][$keyskills][$keyunits];
                                                    $DB->update_record('course_sections', $sectiondata);

                                                    // Update format table also

                                                    $DB->execute(
                                                        "update {course_format_options} set value = ? WHERE courseid = ? AND name = ? AND sectionid = ? AND format = ? ",
                                                        [$sectionorderskill, $courseid, 'parent', $lastid, 'flexsections']
                                                    );
                                                }

                                                // Add/Edit AR for Unit.
                                                if (
                                                    !empty($structuredata->discover_per_unit)
                                                    &&
                                                    !empty($structuredata->unitstaskid[$keyss][$keyskills][$keyunits]['discover'])
                                                ) {
                                                    $returnarr = self::save_ar_data(
                                                        $lastid,
                                                        $valueunits,
                                                        $structuredata->unitstaskid[$keyss][$keyskills][$keyunits]['discover'],
                                                        $structuredata->discover_per_unit,
                                                        $structuredata->discover_per_unit_startdate,
                                                        $structuredata->discover_per_unit_enddate,
                                                        $courseid,
                                                        'discover',
                                                        $userid
                                                    );
                                                    $returnarids[] = $returnarr;
                                                }

                                                // Add/Edit AR for Unit.
                                                if (
                                                    !empty($structuredata->remember_per_unit)
                                                    &&
                                                    !empty($structuredata->unitstaskid[$keyss][$keyskills][$keyunits]['remember'])
                                                ) {
                                                    $returnarr = self::save_ar_data(
                                                        $lastid,
                                                        $valueunits,
                                                        $structuredata->unitstaskid[$keyss][$keyskills][$keyunits]['remember'],
                                                        $structuredata->remember_per_unit,
                                                        $structuredata->remember_per_unit_startdate,
                                                        $structuredata->remember_per_unit_enddate,
                                                        $courseid,
                                                        'remember',
                                                        $userid
                                                    );
                                                    $returnarids[] = $returnarr;
                                                }

                                                // Add/Edit AR for Unit.
                                                if (
                                                    !empty($structuredata->understand_per_unit)
                                                    &&
                                                    !empty($structuredata->unitstaskid[$keyss][$keyskills][$keyunits]['understand'])
                                                ) {
                                                    $returnarr = self::save_ar_data(
                                                        $lastid,
                                                        $valueunits,
                                                        $structuredata->unitstaskid[$keyss][$keyskills][$keyunits]['understand'],
                                                        $structuredata->understand_per_unit,
                                                        $structuredata->understand_per_unit_startdate,
                                                        $structuredata->understand_per_unit_enddate,
                                                        $courseid,
                                                        'understand',
                                                        $userid
                                                    );
                                                    $returnarids[] = $returnarr;
                                                }

                                                // Add multiple quizzes.
                                                if (
                                                    !empty($structuredata->unit_quiz_numbers)
                                                ) {
                                                    foreach ($structuredata->unit_quiz_numbers as $keyquiznumbers => $valuequiznumbers) {
                                                        $returnarr = self::save_ar_data(
                                                            $lastid,
                                                            $valueunits,
                                                            $structuredata->unitstaskid[$keyss][$keyskills][$keyunits][$structuredata->unit_quiz_types[$keyquiznumbers]],
                                                            $valuequiznumbers,
                                                            $structuredata->unit_quiz_start_date[$keyquiznumbers],
                                                            $structuredata->unit_quiz_end_date[$keyquiznumbers],
                                                            $courseid,
                                                            $structuredata->unit_quiz_types[$keyquiznumbers],
                                                            $userid
                                                        );
                                                        $returnarids[] = $returnarr;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            $returndataarr = [
                'projectids' => $returnidsarray,
                'taskids' => $returnarids,
                'gradecategoryinsertdata' => $gradecategoryinsertdata,
                'gradeiteminsertdata' => $gradeiteminsertdata,
            ];
            return json_encode($returndataarr);
        } else if ($action == 'delete') {

            $sectionidstring = $structuredata->sectionidstring;
            $quiztypestring = $structuredata->quiztypestring;
            $courseid = $structuredata->courseid;
            $sectionidsarr = explode(',', $sectionidstring);
            $quiztypesarr = explode(',', $quiztypestring);

            if (!empty($sectionidsarr)) {

                foreach ($sectionidsarr as $key => $sectionid) {

                    $sectiondataaa = $DB->get_record('course_sections', ['id' => $sectionid]);
                    $DB->delete_records('course_sections', ['id' => $sectionid, 'course' => $courseid]);
                    $DB->delete_records('course_format_options', ['sectionid' => $sectionid, 'courseid' => $courseid]);

                    if (!empty($quiztypesarr)) {

                        foreach ($quiztypesarr as $keyqt => $valueqt) {
                            $quizdataa = $DB->get_record(
                                'quiz',
                                ['course' => $courseid, 'name' => $sectiondataaa->name, 'quiztype' => $valueqt]
                            );

                            if (!empty($quizdataa)) {
                                $instance = $quizdataa->id;
                                $modulesdata = $DB->get_record('modules', ['name' => 'quiz']);
                                $coursemodulesdata = $DB->get_records(
                                    'course_modules',
                                    [
                                        'section' => $sectionid,
                                        'course' => $courseid,
                                        'instance' => $instance,
                                        'module' => $modulesdata->id
                                    ],
                                    'id'
                                );

                                if (!empty($coursemodulesdata)) {
                                    foreach ($coursemodulesdata as $keymodule => $valuemodule) {
                                        $data = array();
                                        $data['id'] = $valuemodule->id;
                                        $data['deletioninprogress'] = 1;

                                        $data = (object) $data;

                                        $DB->update_record('course_modules', $data);
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $questionid = $structuredata->questionid;

            $questionidarr = explode(',', $questionid);

            if (!empty($questionidarr)) {

                foreach ($questionidarr as $key => $questionid) {
                    if (!empty($questionid)) {
                        $DB->delete_records('quiz_slots', ['questionid' => $questionid]);
                        $DB->delete_records('question', ['id' => $questionid]);
                        $DB->delete_records('question', ['parent' => $questionid]);
                        $DB->delete_records('qtype_shortanswer_options', ['questionid' => $questionid]);
                        $DB->delete_records('question_answers', ['question' => $questionid]);
                        $DB->delete_records('question_truefalse', ['question' => $questionid]);
                        $DB->delete_records('local_leeloolxptrivias_qd', ['questionid' => $questionid]);
                    }
                }
            }
            return 1;
        }
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function structurecreator_returns() {
        return new external_value(PARAM_TEXT, 'Returns true');
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function checkintegration_parameters() {
        return new external_function_parameters(
            array(
                'checkmodule' => new external_value(PARAM_RAW, 'Check Module', VALUE_DEFAULT, null),
                'leeloo_token' => new external_value(PARAM_RAW, 'leeloo_token', VALUE_DEFAULT, null),
                'moodle_content_token' => new external_value(PARAM_RAW, 'moodle_content_token', VALUE_DEFAULT, null),
                'payments_licensekey' => new external_value(PARAM_RAW, 'payments_licensekey', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Check Module.
     *
     * @param string $reqcheckmodule checkmodule
     * @param string $reqleelootoken leeloo_token
     * @param string $reqmoodlecontenttoken moodle_content_token
     * @param string $reqpaymentslicensekey payments_licensekey
     * @return string welcome message
     */
    public static function checkintegration(
        $reqcheckmodule = '',
        $reqleelootoken = '',
        $reqmoodlecontenttoken = '',
        $reqpaymentslicensekey = ''
    ) {

        global $DB, $CFG;
        // Parameter validation.
        // REQUIRED.
        $params = self::validate_parameters(
            self::checkintegration_parameters(),
            array(
                'checkmodule' => $reqcheckmodule,
                'leeloo_token' => $reqleelootoken,
                'moodle_content_token' => $reqmoodlecontenttoken,
                'payments_licensekey' => $reqpaymentslicensekey,
            )
        );

        $modulesdata = $DB->get_record(
            'config_plugins',
            [
                'plugin' => $reqcheckmodule,
                'name' => 'version'
            ]
        );

        if ($modulesdata) {
            $status = 'installed';

            $enabled = 2;
            $tokenmatched = 2;
            $leelooapitokenmatched = 2;

            if (
                $reqcheckmodule == 'local_leeloolxpapi'
            ) {
                $leelooapitoken = $DB->get_record('config_plugins', ['plugin' => $reqcheckmodule, 'name' => 'leelooapitoken']);
                if ($leelooapitoken->value == $reqleelootoken) {
                    $leelooapitokenmatched = 1;
                } else {
                    $leelooapitokenmatched = 0;
                }
            }

            if (
                $reqcheckmodule == 'local_leeloolxpcontentapi'
            ) {
                $conetnttokenactive = $DB->get_record_sql(
                    'SELECT
                        t.token
                    FROM {external_tokens} t
                        left join {external_services} s on t.externalserviceid = s.id
                    WHERE s.component = "local_leeloolxpcontentapi"'
                );
                if ($conetnttokenactive->token == $reqmoodlecontenttoken) {
                    $tokenmatched = 1;
                } else {
                    $tokenmatched = 0;
                }
            }

            get_enabled_auth_plugins(true);
            if (empty($CFG->auth)) {
                $authsenabled = array();
            } else {
                $authsenabled = explode(',', $CFG->auth);
            }

            if (
                $reqcheckmodule == 'auth_leeloolxp_tracking_sso' ||
                $reqcheckmodule == 'auth_leeloo_pay_sso'
            ) {
                if (in_array(str_replace('auth_', '', $reqcheckmodule), $authsenabled)) {
                    $enabled = 1;
                } else {
                    $enabled = 0;
                }
            }

            if (
                $reqcheckmodule == 'filter_leeloolxp'
            ) {
                $filteractive = $DB->get_record('filter_active', ['filter' => 'leeloolxp', 'active' => '1']);
                if ($filteractive) {
                    $enabled = 1;
                } else {
                    $enabled = 0;
                }
            }

            if (
                $reqcheckmodule == 'local_leeloolxpsocial'
            ) {
                $filteractive = $DB->get_record('config_plugins', ['plugin' => $reqcheckmodule, 'name' => 'addsocialpage']);
                if ($filteractive->value == 1) {
                    $enabled = 1;
                } else {
                    $enabled = 0;
                }
            }

            if (
                $reqcheckmodule == 'local_leeloolxpsrm'
            ) {
                $filteractive = $DB->get_record('config_plugins', ['plugin' => $reqcheckmodule, 'name' => 'addsrmpage']);
                if ($filteractive->value == 1) {
                    $enabled = 1;
                } else {
                    $enabled = 0;
                }
            }

            if (
                $reqcheckmodule == 'local_leeloolxp_web_tat'
            ) {
                $filteractive = $DB->get_record('config_plugins', ['plugin' => $reqcheckmodule, 'name' => 'leeloolxp_web_tatenabled']);
                if ($filteractive->value == 1) {
                    $enabled = 1;
                } else {
                    $enabled = 0;
                }
            }

            if (
                $reqcheckmodule == 'local_leeloolxp_lct'
            ) {
                $filteractive = $DB->get_record('config_plugins', ['plugin' => $reqcheckmodule, 'name' => 'certitrackerenable']);
                if ($filteractive->value == 1) {
                    $enabled = 1;
                } else {
                    $enabled = 0;
                }
            }

            if (
                $reqcheckmodule == 'local_leeloolxp_web_login_tracking'
            ) {
                $filteractive = $DB->get_record('config_plugins', ['plugin' => $reqcheckmodule, 'name' => 'web_loginlogout']);
                if ($filteractive->value == 1) {
                    $enabled = 1;
                } else {
                    $enabled = 0;
                }
            }

            $vendorkeycheck = $DB->get_record_sql(
                "SELECT value FROM {config_plugins} where plugin = ? and name = ?",
                [$reqcheckmodule, 'vendorkey']
            );

            if ($vendorkeycheck) {
                if ($vendorkeycheck->value == $reqpaymentslicensekey) {
                    $vendortrue = 1;
                } else {
                    $vendortrue = 0;
                }
            } else {
                $vendortrue = 2; // Not needed.
            }

            $licekeycheck = $DB->get_record_sql(
                "SELECT value FROM {config_plugins} where plugin = ? and name LIKE '%license%'",
                [$reqcheckmodule]
            );

            if ($licekeycheck) {
                if ($licekeycheck->value == $reqpaymentslicensekey) {
                    $licetrue = 1;
                } else {
                    $licetrue = 0;
                }
            } else {
                $licetrue = 2; // Not needed.
            }
        } else {
            $status = 'notinstalled';
            $vendortrue = 2; // Not needed.
            $licetrue = 2; // Not needed.
            $enabled = 2; // Not needed.
            $tokenmatched = 2; // Not needed.
            $leelooapitokenmatched = 2; // Not needed.
        }

        $responsearr = array();

        $responsearr['status'] = $status;
        $responsearr['vendorkeycheck'] = $vendortrue;
        $responsearr['licekeycheck'] = $licetrue;
        $responsearr['enabled'] = $enabled;
        $responsearr['tokenmatched'] = $tokenmatched;
        $responsearr['leelooapitokenmatched'] = $leelooapitokenmatched;

        return json_encode($responsearr);
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function checkintegration_returns() {
        return new external_value(PARAM_TEXT, 'Returns true');
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function quizzes_settings_parameters() {
        return new external_function_parameters(
            array(
                'req_data' => new external_value(PARAM_RAW, 'Structure Data', VALUE_DEFAULT, null),
                'email' => new external_value(PARAM_RAW, 'Email', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Structure Create.
     *
     * @param string $reqstructuredata reqstructuredata
     * @param string $reqemail reqemail
     * @return string welcome message
     */
    public static function quizzes_settings($reqstructuredata = '', $reqemail = '') {

        global $DB;
        // Parameter validation.
        // REQUIRED.
        $params = self::validate_parameters(
            self::quizzes_settings_parameters(),
            array(
                'req_data' => $reqstructuredata,
                'email' => $reqemail,
            )
        );

        $req_data = (object) json_decode($reqstructuredata, true);



        if (isset($reqemail)) {
            $email = (object) json_decode($reqemail, true);
        }
        $course_id = $req_data->course_id;
        $quiztypesstr = $req_data->quiz_types_str;
        $timeopen = $req_data->timeopen;
        $timeclose = $req_data->timeclose;
        $timelimit = $req_data->timelimit;
        $overduehandling = $req_data->overduehandling;
        $attempts = $req_data->attempts;
        $grademethod = $req_data->grademethod;
        $showblocks = $req_data->showblocks;
        $preferredbehaviour = $req_data->preferredbehaviour;
        $shuffleanswers = $req_data->shuffleanswers;
        $canredoquestions = $req_data->canredoquestions;
        $attemptonlast = $req_data->attemptonlast;
        $grade = $req_data->grade;
        $shufflequestions = $req_data->shufflequestions;
        $forcerepair = $req_data->forcerepair;
        $reviewattempt = $req_data->reviewattempt;
        $reviewcorrectness = $req_data->reviewcorrectness;
        $reviewmarks = $req_data->reviewmarks;
        $reviewspecificfeedback = $req_data->reviewspecificfeedback;
        $reviewgeneralfeedback = $req_data->reviewgeneralfeedback;
        $reviewrightanswer = $req_data->reviewrightanswer;
        $reviewoverallfeedback = $req_data->reviewoverallfeedback;

        $DB->execute("update {quiz} set timeopen = ?, timeclose = ?, timelimit = ? , overduehandling = ?, attempts = ?, grademethod = ? , showblocks = ?, preferredbehaviour = ?, shuffleanswers = ?, canredoquestions = ?, grade = ?, attemptonlast = ? ,reviewattempt = ?,reviewcorrectness = ?,reviewmarks = ?,reviewspecificfeedback = ?,reviewgeneralfeedback = ?,reviewrightanswer = ?,reviewoverallfeedback = ? where course = ? AND quiztype IN ($quiztypesstr) ", [$timeopen, $timeclose, $timelimit, $overduehandling, $attempts, $grademethod, $showblocks, $preferredbehaviour, $shuffleanswers, $canredoquestions, $grade, $attemptonlast, $reviewattempt, $reviewcorrectness, $reviewmarks, $reviewspecificfeedback, $reviewgeneralfeedback, $reviewrightanswer, $reviewoverallfeedback, $course_id]);
        $quizzesdata = $DB->get_records_sql("SELECT id,quiztype FROM {quiz} where course = ? AND quiztype IN ($quiztypesstr) ORDER BY `quiztype` ASC ", [$course_id]);

        $quizseqforid = 1;
        $lastquiztype = '';
        $lastquiztypeseq = 1;
        foreach ($quizzesdata as $key => $value) {

            $groupmode = $req_data->groupmode;
            $groupingid = $req_data->groupingid;
            $idnumberstr = $idnumber = $req_data->idnumber;
            $gradepass = $req_data->gradepass;
            $instance = $value->id;
            $quiztype = $value->quiztype;
            $module = '17';
            if ($lastquiztype != $quiztype) {
                $lastquiztypeseq = 1;
                $lastquiztype = $quiztype;
            }

            if (strpos($idnumber, '$') !== false) {
                $idnumberarr = explode('_', $idnumber);
                $idnumberstr = '01';

                if (strpos($idnumber, '$ID') !== false) {
                    $courseidnumber = $req_data->course_id_number;
                    $courseidnumber = trim($courseidnumber);
                    $idnumberstr = $courseidnumber . '_01';
                }
                if (strpos($idnumber, '$SECTION') !== false) {
                    $tempdata1 = $DB->get_record_sql("SELECT section  FROM {course_modules} where course = ? AND instance = ? AND module = '17' ", [$course_id, $instance]);
                    $section = $tempdata1->section;
                    $tempdata2 = $DB->get_record_sql("SELECT value FROM {course_format_options} where sectionid = ? AND name = 'parent' AND courseid = '$course_id' ", [$section]);
                    $parentt = $tempdata2->value;
                    $tempdatanew = $DB->get_records_sql("SELECT sectionid FROM {course_format_options} where name = 'parent' AND courseid = '$course_id' AND value = '$parentt' ORDER BY id ASC ");
                    if (!empty($tempdatanew)) {
                        $orderunit = 1;
                        foreach ($tempdatanew as $keytemp => $valuetemp) {
                            if ($valuetemp->sectionid == $section) {
                                break;
                            }
                            $orderunit++;
                        }


                        $datausedonlyonce = $DB->get_record_sql("SELECT id FROM {course_sections} where course = ? AND section = ? ", [$course_id, $parentt]);
                        $section = $datausedonlyonce->id;
                        $tempdata2 = $DB->get_record_sql("SELECT value FROM {course_format_options} where sectionid = ? AND name = 'parent' AND courseid = '$course_id' ", [$section]);
                        $parentt = $tempdata2->value;
                        $tempdatanew = $DB->get_records_sql("SELECT sectionid FROM {course_format_options} where name = 'parent' AND courseid = '$course_id' AND value = '$parentt' ORDER BY id ASC ");
                        if (!empty($tempdatanew)) {
                            $orderskill = 1;
                            foreach ($tempdatanew as $keytemp => $valuetemp) {
                                if ($valuetemp->sectionid == $section) {
                                    break;
                                }
                                $orderskill++;
                            }
                            $orderskill = sprintf("%02d", $orderskill);
                            $idnumberstr .= $orderskill;
                        }
                        $orderunit = sprintf("%02d", $orderunit);
                        $idnumberstr .= $orderunit;
                    }
                }
                if (strpos($idnumber, '$TYPE') !== false) {
                    $quiztypechar = strtoupper($quiztype[0]);
                    $idnumberstr .= '_' . $quiztypechar;
                }

                if (strpos($idnumber, '**') !== false) {
                    $idnumberstr .= $lastquiztypeseq;
                    $lastquiztypeseq++;
                } elseif (strpos($idnumber, '*') !== false) {
                    $idnumberstr .= $quizseqforid;
                    $quizseqforid++;
                }
            }

            $DB->execute("update {course_modules} set groupmode = ?, groupingid = ?, idnumber = ? where course = ? AND instance = ? AND module = '17' ", [$groupmode, $groupingid, $idnumberstr, $course_id, $instance]);
            $DB->execute("update {quiz_sections} set shufflequestions = ? where quizid = ? ", [$shufflequestions, $value->id]);
            $DB->execute("update {grade_items} set gradepass = ? where iteminstance = ? AND courseid = ? AND itemmodule = 'quiz' ", [$gradepass, $value->id, $course_id]);

            if (!empty($forcerepair)) {
                $quizslotsdatatotal = $DB->get_record_sql(
                    "SELECT sum(maxmark) as total FROM {quiz_slots} where quizid = ? ",
                    [$value->id]
                );
                if (!empty($quizslotsdatatotal) && !empty($quizslotsdatatotal->total)) {
                    $sumgrades = $quizslotsdatatotal->total;
                    $DB->execute("update {quiz} set sumgrades = ? where id = ? ", [$sumgrades, $value->id]);
                }
            }

            // Update Question data
            $difficulty = $req_data->difficulty;
            $shuffleanswers = $req_data->shuffleanswers;
            $answernumbering = $req_data->answernumbering;
            $choice1correct = $req_data->choice_1_correct;
            $defaultmark = $req_data->default_mark;
            $quesslotsdata = $DB->get_records_sql("SELECT questionid FROM {quiz_slots} where quizid = ? ", [$value->id]);
            if (!empty($quesslotsdata)) {
                foreach ($quesslotsdata as $keyqsd => $valueqsd) {
                    $DB->execute("update {question} set defaultmark = ? where id = ? ", [$defaultmark, $valueqsd->questionid]);

                    $difficultyexist = $DB->get_record_sql(
                        "SELECT id FROM {local_leeloolxptrivias_qd} where questionid = ?",
                        [$valueqsd->questionid]
                    );
                    if (!empty($difficultyexist)) {
                        $DB->execute("update {local_leeloolxptrivias_qd} set difficulty = ? where questionid = ? ", [$difficulty, $valueqsd->questionid]);
                    } else {
                        $extradatainsert = array();
                        $extradatainsert['vimeoid'] = '0';
                        $extradatainsert['questionid'] = $valueqsd->questionid;
                        $extradatainsert['difficulty'] = $difficulty;
                        $DB->insert_record('local_leeloolxptrivias_qd', $extradatainsert);
                    }
                    $DB->execute("update {qtype_multichoice_options} set shuffleanswers = ? ,answernumbering = ? where questionid = ? ", [$shuffleanswers, $answernumbering, $valueqsd->questionid]);

                    if (!empty($choice1correct)) {
                        $answerexist = $DB->get_record_sql(
                            "SELECT id FROM {question_answers} where question = ? ORDER BY id ASC ",
                            [$valueqsd->questionid]
                        );
                        if (!empty($answerexist)) {
                            $DB->execute("update {question_answers} set fraction = '1.0000000' where id = ? ", [$answerexist->id]);
                        }
                    }
                }
            }
        }

        // Create/Update quiz cat hierarchy
        $coursequestioncategories = [];
        if (!empty($req_data->create_update_cat)) {
            $catsleeloodata = json_decode($req_data->catsleeloodata);
            $structuredata = $DB->get_records_sql("SELECT * FROM {course_sections} where course = ? AND section != '0' ORDER BY section ASC ", [$course_id]);
            $contextdata = $DB->get_record(
                'context',
                ['instanceid' => $course_id, 'depth' => '3']
            );
            $paretcategory = $DB->get_record_sql(
                "SELECT * FROM {question_categories} where contextid = ? and parent = '0' ORDER BY id DESC",
                [$contextdata->id]
            );

            $lastidd = $lastskillid = $lastskillsetid = $paretcategory->id;



            foreach ($structuredata as $keystr => $valuestr) {

                $skillsetexist = $DB->get_record('course_format_options', ['courseid' => $course_id, 'sectionid' => $valuestr->id]);
                $catnewdata = array();

                if (!empty($skillsetexist)) { // Skill/Unit

                    $tempdata = $DB->get_record_sql("SELECT value FROM {course_format_options} where sectionid = ? AND name = 'parent' ", [$valuestr->id]);
                    $tempdata2 = $DB->get_record_sql("SELECT id FROM {course_sections} where course = ? AND section = ? ", [$course_id, $tempdata->value]);
                    $skillsetexistcheck = $DB->get_record_sql("SELECT id FROM {course_format_options} where sectionid = ? ", [$tempdata2->id]);
                    if (!empty($skillsetexistcheck)) { // Unit cat
                        $catnewdata['name'] = $valuestr->name;
                        $catnewdata['contextid'] = $paretcategory->contextid;
                        $catnewdata['info'] = '';
                        $catnewdata['infoformat'] = 1;
                        $catnewdata['stamp'] = $_SERVER['HTTP_HOST'] . '+' . rand() . '+' . substr(md5(microtime()), rand(0, 26), 6);
                        $catnewdata['parent'] = $lastskillid;
                        $catnewdata['sortorder'] = 999;
                        $catnewdata['idnumber'] = null;
                        $catnewdata = (object) $catnewdata;


                        $keycheck = array_search($valuestr->id, array_column($catsleeloodata, 'sectionid'));

                        if (is_int($keycheck)) { // Update
                            $lastidd = $catnewdata->id = $catsleeloodata[$keycheck]->moodle_id;
                            $DB->update_record('question_categories', $catnewdata);
                        } else { // Insert
                            $lastidd = $DB->insert_record('question_categories', $catnewdata);
                            $lastcategory = $DB->get_record_sql("SELECT * FROM {question_categories} where id = '$lastidd' ");
                            $lastcategory->sectionid = $valuestr->id;
                            $coursequestioncategories[] = $lastcategory;
                        }

                        $quiztypeexist = $DB->get_record_sql(
                            "SELECT
                                cm.id,quiz.id as qid
                            FROM {course_modules} cm
                                left join {quiz} quiz on cm.instance = quiz.id
                            WHERE cm.course = '$course_id'
                            AND cm.module = '17' AND cm.section = '$valuestr->id'
                            AND quiz.quiztype IN ($quiztypesstr) "
                        );
                        if (!empty($quiztypeexist)) {
                            $quiztypesarr = explode(',', $quiztypesstr);
                            foreach ($quiztypesarr as $keyquiztype => $valuequiztype) {
                                $valuequiztype = str_replace("'", "", $valuequiztype);
                                $quiztypeexistsingle = $DB->get_record_sql(
                                    "SELECT
                                        cm.id,quiz.id as qid
                                    FROM {course_modules} cm
                                        left join {quiz} quiz on cm.instance = quiz.id
                                    WHERE cm.course = '$course_id'
                                    AND cm.module = '17' AND cm.section = '$valuestr->id'
                                    AND quiz.quiztype = '$valuequiztype' "
                                );
                                if (!empty($quiztypeexistsingle)) {
                                    $catnewdata = array();
                                    $catnewdata['name'] = $valuequiztype;
                                    $catnewdata['contextid'] = $paretcategory->contextid;
                                    $catnewdata['info'] = '';
                                    $catnewdata['infoformat'] = 1;
                                    $catnewdata['stamp'] = $_SERVER['HTTP_HOST'] . '+' . rand() . '+' . substr(md5(microtime()), rand(0, 26), 6);
                                    $catnewdata['parent'] = $lastidd;
                                    $catnewdata['sortorder'] = 999;
                                    $catnewdata['idnumber'] = null;
                                    $catnewdata = (object) $catnewdata;

                                    $recordexist = $DB->get_record_sql("SELECT id,name FROM {question_categories} where contextid = '$paretcategory->contextid' and parent = '$lastidd' and name = '$valuequiztype' ");

                                    if (empty($recordexist)) {
                                        $lastquizcatid = $DB->insert_record('question_categories', $catnewdata);
                                        $lastcategory = $DB->get_record_sql("SELECT * FROM {question_categories} where id = '$lastquizcatid' ");
                                        $lastcategory->sectionid = '';
                                        $coursequestioncategories[] = $lastcategory;
                                    } else {
                                        $lastquizcatid = $recordexist->id;
                                    }

                                    $allquiztypequestions = $DB->get_records_sql(
                                        "SELECT
                                        ques.id as quesid
                                    FROM {course_modules} cm
                                        join {quiz} quiz on cm.instance = quiz.id
                                        join {quiz_slots} qs on quiz.id = qs.quizid
                                        join {question} ques on qs.questionid = ques.id
                                    WHERE cm.course = '$course_id'
                                    AND cm.module = '17' AND cm.section = '$valuestr->id'
                                    AND quiz.quiztype = '$valuequiztype' "
                                    );

                                    if (!empty($allquiztypequestions)) {
                                        foreach ($allquiztypequestions as $keyaqt => $valueaqt) {
                                            $tempdataupdate = new stdClass();
                                            $tempdataupdate->category = $lastquizcatid;
                                            $tempdataupdate->id = $valueaqt->quesid;
                                            $DB->update_record('question', $tempdataupdate);
                                        }
                                    }
                                }
                            }
                        }
                    } else { // Skills cat
                        $catnewdata['name'] = $valuestr->name;
                        $catnewdata['contextid'] = $paretcategory->contextid;
                        $catnewdata['info'] = '';
                        $catnewdata['infoformat'] = 1;
                        $catnewdata['stamp'] = $_SERVER['HTTP_HOST'] . '+' . rand() . '+' . substr(md5(microtime()), rand(0, 26), 6);
                        $catnewdata['parent'] = $lastskillsetid;
                        $catnewdata['sortorder'] = 999;
                        $catnewdata['idnumber'] = null;
                        $catnewdata = (object) $catnewdata;

                        $keycheck = array_search($valuestr->id, array_column($catsleeloodata, 'sectionid'));

                        if (is_int($keycheck)) { // Update
                            $lastidd = $lastskillid = $catnewdata->id = $catsleeloodata[$keycheck]->moodle_id;
                            $DB->update_record('question_categories', $catnewdata);
                        } else { // Insert
                            $lastidd = $lastskillid = $DB->insert_record('question_categories', $catnewdata);
                            $lastcategory = $DB->get_record_sql("SELECT * FROM {question_categories} where id = '$lastidd' ");
                            $lastcategory->sectionid = $valuestr->id;
                            $coursequestioncategories[] = $lastcategory;
                        }

                        $quiztypeexist = $DB->get_record_sql(
                            "SELECT
                                cm.id
                            FROM {course_modules} cm
                                left join {quiz} quiz on cm.instance = quiz.id
                            WHERE cm.course = '$course_id'
                            AND cm.module = '17' AND cm.section = '$valuestr->id'
                            AND quiz.quiztype IN ($quiztypesstr) "
                        );
                        if (!empty($quiztypeexist)) {
                            $quiz_types_arr = explode(',', $quiztypesstr);
                            foreach ($quiz_types_arr as $keyquiztype => $valuequiztype) {
                                $valuequiztype = str_replace("'", "", $valuequiztype);
                                $quiztypeexistsingle = $DB->get_record_sql(
                                    "SELECT
                                        cm.id,quiz.id as qid
                                    FROM {course_modules} cm
                                        left join {quiz} quiz on cm.instance = quiz.id
                                    WHERE cm.course = '$course_id'
                                    AND cm.module = '17' AND cm.section = '$valuestr->id'
                                    AND quiz.quiztype = '$valuequiztype' "
                                );
                                if (!empty($quiztypeexistsingle)) {
                                    $catnewdata = array();
                                    $catnewdata['name'] = $valuequiztype;
                                    $catnewdata['contextid'] = $paretcategory->contextid;
                                    $catnewdata['info'] = '';
                                    $catnewdata['infoformat'] = 1;
                                    $catnewdata['stamp'] = $_SERVER['HTTP_HOST'] . '+' . rand() . '+' . substr(md5(microtime()), rand(0, 26), 6);
                                    $catnewdata['parent'] = $lastidd;
                                    $catnewdata['sortorder'] = 999;
                                    $catnewdata['idnumber'] = null;
                                    $catnewdata = (object) $catnewdata;

                                    $recordexist = $DB->get_record_sql("SELECT id,name FROM {question_categories} where contextid = '$paretcategory->contextid' and parent = '$lastidd' and name = '$valuequiztype' ");

                                    if (empty($recordexist)) {
                                        $lastquizcatid = $DB->insert_record('question_categories', $catnewdata);
                                        $lastcategory = $DB->get_record_sql("SELECT * FROM {question_categories} where id = '$lastquizcatid' ");
                                        $lastcategory->sectionid = '';
                                        $coursequestioncategories[] = $lastcategory;
                                    } else {
                                        $lastquizcatid = $recordexist->id;
                                    }

                                    $allquiztypequestions = $DB->get_records_sql(
                                        "SELECT
                                        ques.id as quesid
                                    FROM {course_modules} cm
                                        join {quiz} quiz on cm.instance = quiz.id
                                        join {quiz_slots} qs on quiz.id = qs.quizid
                                        join {question} ques on qs.questionid = ques.id
                                    WHERE cm.course = '$course_id'
                                    AND cm.module = '17' AND cm.section = '$valuestr->id'
                                    AND quiz.quiztype IN ($quiztypesstr) "
                                    );

                                    if (!empty($allquiztypequestions)) {
                                        foreach ($allquiztypequestions as $keyaqt => $valueaqt) {
                                            $tempdataupdate = new stdClass();
                                            $tempdataupdate->category = $lastquizcatid;
                                            $tempdataupdate->id = $valueaqt->quesid;
                                            $DB->update_record('question', $tempdataupdate);
                                        }
                                    }
                                }
                            }
                        }
                    }
                } else { // Skillset

                    $catnewdata['name'] = $valuestr->name;
                    $catnewdata['contextid'] = $paretcategory->contextid;
                    $catnewdata['info'] = '';
                    $catnewdata['infoformat'] = 1;
                    $catnewdata['stamp'] = $_SERVER['HTTP_HOST'] . '+' . rand() . '+' . substr(md5(microtime()), rand(0, 26), 6);
                    $catnewdata['parent'] = $paretcategory->id;
                    $catnewdata['sortorder'] = 999;
                    $catnewdata['idnumber'] = null;
                    $catnewdata = (object) $catnewdata;


                    $keycheck = array_search($valuestr->id, array_column($catsleeloodata, 'sectionid'));

                    if (is_int($keycheck)) { // Update
                        $lastidd = $lastskillsetid = $catnewdata->id = $catsleeloodata[$keycheck]->moodle_id;
                        $DB->update_record('question_categories', $catnewdata);
                    } else { // Insert
                        $lastidd = $lastskillsetid = $DB->insert_record('question_categories', $catnewdata);
                        $lastcategory = $DB->get_record_sql("SELECT * FROM {question_categories} where id = '$lastidd' ");
                        $lastcategory->sectionid = $valuestr->id;
                        $coursequestioncategories[] = $lastcategory;
                    }
                }
            }
        }

        $categoriesarr = [];
        // Add quiz to its own category
        if (!empty($req_data->cats_arr)) {
            $categoriesarr = json_decode($req_data->cats_arr);
            if (!empty($categoriesarr)) {
                $sql = "SELECT id,path FROM {grade_categories} WHERE courseid = '$course_id' ORDER BY id ASC ";
                $parentcatdata = $DB->get_record_sql($sql);
                foreach ($categoriesarr as $keycat => $valuecat) {
                    $gradecatdata = new stdClass();
                    $gradecatdata->courseid = $course_id;
                    $gradecatdata->parent = $parentcatdata->id;
                    $gradecatdata->depth = '2';
                    $gradecatdata->path = '';
                    $gradecatdata->fullname = $valuecat->fullname;
                    $gradecatdata->aggregation = '13';
                    $gradecatdata->keephigh = '0';
                    $gradecatdata->droplow = '0';
                    $gradecatdata->aggregateonlygraded = '1';
                    $gradecatdata->aggregateoutcomes = '0';
                    $gradecatdata->timecreated = strtotime(date('Y/m/d h:i', time()));
                    $gradecatdata->timemodified = strtotime(date('Y/m/d h:i', time()));
                    $gradecatdata->hidden = '0';

                    $catreturnedid = $DB->insert_record('grade_categories', $gradecatdata);
                    $valuecat->moodle_cat_id = $catreturnedid;

                    $updatenewdata = ['path' => $parentcatdata->path . $catreturnedid . '/', 'id' => $catreturnedid];
                    $updatenewdata = (object) $updatenewdata;
                    $DB->update_record('grade_categories', $updatenewdata);

                    $gradeitems = $DB->get_records_sql(
                        "SELECT
                            gi.id
                        FROM {grade_items} gi
                            left join {quiz} quiz on gi.iteminstance = quiz.id
                        WHERE quiz.course = '$course_id'
                        AND quiz.quiztype = '$valuecat->fullname' "
                    );
                    if (!empty($gradeitems)) {
                        foreach ($gradeitems as $keygi => $valuegi) {
                            $gradeitemdata = new stdClass();
                            $gradeitemdata->categoryid = $catreturnedid;
                            $gradeitemdata->id = $valuegi->id;
                            $DB->update_record('grade_items', $gradeitemdata);
                        }
                    }
                }
            }
        }




        $returndata = [
            'cat_ids_arr' => json_encode($categoriesarr),
            'question_cats' => json_encode($coursequestioncategories)
        ];

        return json_encode($returndata);
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function quizzes_settings_returns() {
        return new external_value(PARAM_TEXT, 'Returns true');
    }
}
