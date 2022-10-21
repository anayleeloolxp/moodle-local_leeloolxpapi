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

/**
 * Get user course grade.
 *
 * @param array $params params
 * @return array $data data
 */
function get_users_course_grade($params) {

    global $DB;
    global $CFG;

    $functiondataall = $params['functiondata'];

    foreach ($functiondataall as $key => $functiondata) {

        $queryparams = $functiondata;

        if ($CFG->dbtype == 'mysqli') {
            $functype = 'GROUP_CONCAT';
        } else {
            $functype = 'array_agg';
        }

        $query = " SELECT ue.id id,cri.gradepass gradepass,
        ue.timecreated started,
        ROUND((CASE WHEN (g.rawgrademax-g.rawgrademin) > 0
        THEN ((g.finalgrade-g.rawgrademin)/(g.rawgrademax-g.rawgrademin))*100
        ELSE g.finalgrade END), 0) score,
         ROUND((CASE WHEN g.rawgrademax > 0 THEN (g.finalgrade/g.rawgrademax)*100 ELSE g.finalgrade END), 0) grade_real,
         (SELECT COUNT(DISTINCT cmc.id) FROM {course_modules_completion} cmc, {course_modules} cm
         WHERE cm.visible = 1 AND cmc.coursemoduleid = cm.id
         AND cm.completion > 0 AND cm.course = c.id AND cmc.userid = u.id) completed,
         (SELECT ROUND((CASE WHEN (g.rawgrademax-g.rawgrademin) > 0
         THEN ((g.finalgrade-g.rawgrademin)/(g.rawgrademax-g.rawgrademin))*100
         ELSE g.finalgrade END), 0)
         FROM {grade_items} gi, {grade_grades} g
         WHERE gi.itemtype = 'course' AND g.itemid = gi.id
         AND g.finalgrade IS NOT NULL AND gi.courseid=c.id LIMIT 1) average,
         (SELECT $functype(DISTINCT CONCAT(u.firstname,' ',u.lastname))
         FROM {role_assignments} ra
         JOIN {user} u ON ra.userid = u.id
         JOIN {context} ctx ON ctx.id = ra.contextid
         WHERE ctx.instanceid = c.id AND ctx.contextlevel = 50
         ) teacher,
         (SELECT ctx.id
         FROM {context} ctx
         WHERE ctx.instanceid = c.id AND ctx.contextlevel = 50
         ) ctxid
         FROM {user_enrolments} ue
         LEFT JOIN {user} u ON u.id = ue.userid
         LEFT JOIN {enrol} e ON e.id = ue.enrolid
         LEFT JOIN {course} c ON c.id = e.courseid
         LEFT JOIN {course_completions} cc ON cc.course = e.courseid AND cc.userid = ue.userid
         LEFT JOIN {course_completion_criteria} cri ON cri.course = e.courseid AND cri.criteriatype = 6
         LEFT JOIN {grade_items} gi ON gi.courseid = c.id AND gi.itemtype = 'course'
         LEFT JOIN {grade_grades} g ON g.itemid = gi.id AND g.userid =u.id
         WHERE ue.id > 0  AND u.deleted = 0 AND u.suspended = 0 AND u.username <> 'guest'
         AND c.visible = 1 AND ue.status = 0 AND e.status = 0 and u.email = :useremail
         and c.id = :courseid";

        $singledata = $DB->get_record_sql($query, $queryparams);

        $queryletter = "SELECT * from {grade_letters} g
        where g.contextid = ? and g.lowerboundary < ? order by g.lowerboundary desc";

        $queryletterdata = $DB->get_record_sql($queryletter, array($singledata->ctxid, $singledata->score));

        $functiondataall[$key]['values'] = $singledata;

        $functiondataall[$key]['values']->grade_letters = $queryletterdata->letter;
    }

    $return = array();

    $return['data'] = $functiondataall;

    return $return;
}

/**
 * Get user score.
 *
 * @param array $params params
 * @return array $data data
 */
function get_users_score($params) {

    global $DB;

    $functiondataall = $params['functiondata'];

    foreach ($functiondataall as $key => $functiondata) {

        $queryparams = $functiondata;

        $query = "SELECT ROUND(AVG(CASE WHEN (g.rawgrademax-g.rawgrademin) > 0
        THEN ((g.finalgrade-g.rawgrademin)/(g.rawgrademax-g.rawgrademin))*100 ELSE g.finalgrade END)) grade,
        COUNT(DISTINCT e.courseid) courses, COUNT(DISTINCT cc.course) {completed_courses}
        FROM {user_enrolments} ue LEFT JOIN {enrol} e ON e.id = ue.enrolid
        LEFT JOIN {course_completions} cc ON cc.timecompleted > 0
        AND cc.course = e.courseid AND cc.userid = ue.userid
        LEFT JOIN {grade_items} gi ON gi.itemtype = 'course' AND gi.courseid = e.courseid
        LEFT JOIN {grade_grades} g ON g.userid = ue.userid AND g.itemid = gi.id
        AND g.finalgrade IS NOT NULL
        left join {user} u on u.id = ue.userid
        where u.email = :useremail
        GROUP BY ue.userid";

        $singledata = $DB->get_record_sql($query, $queryparams);

        $functiondataall[$key]['values'] = $singledata;
    }

    $return = array();

    $return['data'] = $functiondataall;

    return $return;
}

/**
 * Get user proflie.
 *
 * @param array $params params
 * @return array $data data
 */
function get_users_profile($params) {

    global $DB;

    $functiondataall = $params['functiondata'];

    foreach ($functiondataall as $key => $functiondata) {

        $queryparams = $functiondata;

        $query = "SELECT u.id, u.firstname, u.lastname, u.middlename, u.email, u.idnumber, u.username,
        u.phone1, u.phone2, u.institution, u.department, u.address, u.city, u.country,
        u.auth, u.confirmed, u.suspended, u.deleted, u.timecreated, u.timemodified,
        u.firstaccess, u.lastaccess, u.lastlogin, u.currentlogin, u.lastip
        FROM {user} u WHERE u.email = :useremail";

        $singledata = $DB->get_record_sql($query, $queryparams);

        $functiondataall[$key]['values'] = $singledata;
    }

    $return = array();

    $return['data'] = $functiondataall;

    return $return;
}

/**
 * Get total questions of quiz.
 *
 * @param array $params params
 * @return array $data data
 */
function get_total_questions_quiz($params) {

    global $DB;

    $functiondataall = $params['functiondata'];

    foreach ($functiondataall as $key => $functiondata) {

        $queryparams = $functiondata;

        $query = "SELECT instance FROM {course_modules} u WHERE id = :activityid AND course = :courseid ";

        $activityiddata = $DB->get_record_sql($query, $queryparams);

        $singledata = '';

        if (!empty($activityiddata->instance)) {

            $args = ['activityid' => $activityiddata->instance];

            $query = "SELECT MAX(ql.questions) questions FROM {quiz} q
            JOIN {course} c ON c.id = q.course  LEFT JOIN {quiz_attempts} qa ON qa.quiz = q.id
            LEFT JOIN (SELECT quizid, count(*) questions
            FROM {quiz_slots} GROUP BY quizid) ql ON ql.quizid = q.id
            LEFT JOIN {modules} m ON m.name = 'quiz'
            LEFT JOIN {course_modules} cm ON cm.module = m.id AND cm.course = c.id
            AND cm.instance = q.id  WHERE q.id = :activityid
            AND c.visible = 1 GROUP BY q.id, c.id ";

            $singledata = $DB->get_record_sql($query, $args);
        }

        $functiondataall[$key]['values'] = $singledata;
    }

    $return = array();

    $return['data'] = $functiondataall;

    return $return;
}

/**
 * Get quiz close date.
 *
 * @param array $params params
 * @return array $data data
 */
function get_quiz_close_date($params) {

    global $DB;
    global $CFG;
    $functiondataall = $params['functiondata'];

    if ($CFG->dbtype == 'mysqli') {
        $functype = 'GROUP_CONCAT';
    } else {
        $functype = 'array_agg';
    }

    foreach ($functiondataall as $key => $functiondata) {
        $queryparams = $functiondata;

        $query = "SELECT q.id,q.timeclose, $functype(DISTINCT t.rawname) tags,
        $functype(DISTINCT t_c.rawname ) tags_course from {course_modules} cm
        left JOIN {quiz} q ON q.id = cm.instance left JOIN {course} c ON c.id = cm.course
        LEFT JOIN {tag_instance} ti ON ti.itemtype = 'course_modules' AND ti.itemid = cm.id
        LEFT JOIN {tag} t ON t.id = ti.tagid
        LEFT JOIN {tag_instance} ti_c ON ti_c.itemtype = 'course'
        AND ti_c.itemid = cm.course LEFT JOIN {tag} t_c ON t_c.id = ti_c.tagid
        WHERE cm.id = :activityid group by q.id ";

        $singledata = $DB->get_record_sql($query, $queryparams);

        $functiondataall[$key]['values'] = $singledata;
    }

    $return = array();

    $return['data'] = $functiondataall;

    return $return;
}

/**
 * Get total questions attempted.
 *
 * @param array $params params
 * @return array $data data
 */
function get_total_questions_attempted($params) {

    global $DB;

    $functiondataall = $params['functiondata'];

    foreach ($functiondataall as $key => $functiondata) {

        $queryparams = $functiondata;

        $paramarr = $queryparams;

        if (!empty($queryparams['useremail'])) {

            $query = "SELECT id FROM {user} WHERE email = :useremail ";

            $userdata = $DB->get_record_sql($query, $queryparams);

            if (!empty($userdata->id)) {

                $paramarr['uid'] = $userdata->id;

                $query = "SELECT COUNT(DISTINCT(qa.id)) attempted FROM {quiz_attempts} qa WHERE userid = ' $userdata->id'   ";

                $singledata = $DB->get_record_sql($query);
            }
        } else {

            $query = "SELECT COUNT(DISTINCT(qa.id)) attempted
            FROM {quiz_attempts} qa, {quiz} q, {course} c
            WHERE qa.quiz = q.id AND c.id = q.course  AND c.visible = 1  AND c.id = :courseid  ";

            $singledata = $DB->get_record_sql($query, $paramarr);
        }

        $functiondataall[$key]['values'] = $singledata;
    }

    $return = array();

    $return['data'] = $functiondataall;

    return $return;
}

/**
 * Get total number of attempts.
 *
 * @param array $params params
 * @return array $data data
 */
function get_number_of_attempts($params) {

    global $DB;

    $functiondataall = $params['functiondata'];

    foreach ($functiondataall as $key => $functiondata) {

        $queryparams = $functiondata;

        $userquery1 = "";

        $userquery2 = "";

        if (!empty($queryparams['useremail'])) {

            $query = "SELECT id FROM {user} WHERE email = :useremail ";

            $userdata = $DB->get_record_sql($query, $queryparams);

            if (!empty($userdata->id)) {

                $userquery1 = " AND sst.userid = '$userdata->id' ";

                $userquery2 = " and ue.userid = '$userdata->id' ";
            }
        }

        $activityid = $queryparams['activityid'];

        // Check for scorm.

        $query = "SELECT id FROM {course_modules} WHERE id = '$activityid' AND module = '19' ";

        $scormdata = $DB->get_record_sql($query);

        if (!empty($scormdata)) {

            $query = "SELECT count(sst.id)  attempted FROM {course_modules} cm
            JOIN {scorm_scoes_track} sst ON sst.scormid=cm.instance
            WHERE cm.id = '$activityid' AND module = '19' $userquery1 ";

            $scormdata = $DB->get_record_sql($query);
        } else {

            $paramarr['activityid'] = $queryparams['activityid'];

            $query = " SELECT CASE WHEN m.name = 'quiz' THEN
            (CASE WHEN qat.num_of_attempts IS NULL THEN 0 ELSE qat.num_of_attempts END)
            WHEN m.name = 'assign'
            THEN (CASE WHEN asbm.num_of_attempts IS NULL THEN 0 ELSE asbm.num_of_attempts END)
            WHEN m.name = 'h5pactivity'
            THEN (CASE WHEN h5patt.num_of_attempts IS NULL THEN 0 ELSE h5patt.num_of_attempts END)
            ELSE 0 END attempted
            FROM (SELECT MIN(ue1.id) id, ue1.userid, e1.courseid, MIN(ue1.status) enrol_status,
            MIN(ue1.timeend) timeend FROM {user_enrolments} ue1
            JOIN {enrol} e1 ON e1.id = ue1.enrolid
            GROUP BY ue1.userid, e1.courseid ) ue JOIN {course} c ON c.id = ue.courseid
            JOIN {course_modules} cm ON cm.course = c.id JOIN {modules} m ON m.id = cm.module
            LEFT JOIN ( SELECT qa.quiz, qa.userid, MAX(qa.attempt) num_of_attempts,
            MIN(qa.timemodified) first_completed_date FROM {quiz_attempts} qa
            JOIN {quiz} q ON q.id=qa.quiz
            GROUP BY qa.quiz, qa.userid ) qat ON qat.userid = ue.userid
            AND m.name = 'quiz' AND qat.quiz = cm.instance
            LEFT JOIN ( SELECT asbm1.assignment, asbm1.userid, COUNT(*) num_of_attempts,
            MIN(asbm1.timemodified) first_completed_date
            FROM {assign_submission} asbm1 JOIN {assign} a ON a.id=asbm1.assignment
            GROUP BY asbm1.assignment, asbm1.userid ) asbm ON asbm.userid = ue.userid
            AND m.name = 'assign' AND asbm.assignment = cm.instance
            LEFT JOIN (SELECT h5pa.userid, h5pa.h5pactivityid, COUNT(*) num_of_attempts
            FROM {h5pactivity_attempts} h5pa
            JOIN {h5pactivity} h5p ON h5p.id = h5pa.h5pactivityid
            GROUP BY h5pa.userid, h5pa.h5pactivityid ) h5patt ON h5patt.userid = ue.userid
            AND m.name = 'h5pactivity' AND h5patt.h5pactivityid = cm.instance
            where cm.id = :activityid  $userquery2 ";
        }

        $singledata = $DB->get_record_sql($query, $paramarr);

        $functiondataall[$key]['values'] = $singledata;
    }

    $return = array();

    $return['data'] = $functiondataall;

    return $return;
}

/**
 * Get first filename.
 *
 * @param array $params params
 * @return array $data data
 */
function get_file_name_first_submission($params) {

    global $DB;

    $functiondataall = $params['functiondata'];

    foreach ($functiondataall as $key => $functiondata) {

        $queryparams = $functiondata;

        $query = "SELECT id FROM {user} WHERE email = :useremail ";

        $userdata = $DB->get_record_sql($query, $queryparams);

        if (!empty($userdata->id)) {

            $paramarr['activityid'] = $queryparams['activityid'];

            $query = "SELECT filename FROM {context} con JOIN {files} f ON f.contextid = con.id
            WHERE con.instanceid = :activityid AND f.userid = ' $userdata->id'
            AND f.component='assignsubmission_file' AND f.filearea='submission_files'
            ORDER BY f.id ASC  ";

            $singledata = $DB->get_record_sql($query, $paramarr);
        }

        $functiondataall[$key]['values'] = $singledata;
    }

    $return = array();

    $return['data'] = $functiondataall;

    return $return;
}

/**
 * Get last file name.
 *
 * @param array $params params
 * @return array $data data
 */
function get_file_name_last_submission($params) {

    global $DB;

    $functiondataall = $params['functiondata'];

    foreach ($functiondataall as $key => $functiondata) {

        $queryparams = $functiondata;

        $query = "SELECT id FROM {user} WHERE email = :useremail ";

        $userdata = $DB->get_record_sql($query, $queryparams);

        if (!empty($userdata->id)) {

            $paramarr['activityid'] = $queryparams['activityid'];

            $query = "SELECT filename FROM {context} con JOIN {files} f ON f.contextid = con.id
            WHERE con.instanceid = :activityid AND f.userid = ' $userdata->id'
            AND f.component='assignsubmission_file' AND f.filearea='submission_files'
            ORDER BY f.id DESC  ";

            $singledata = $DB->get_record_sql($query, $paramarr);
        }

        $functiondataall[$key]['values'] = $singledata;
    }

    $return = array();

    $return['data'] = $functiondataall;

    return $return;
}

/**
 * Get user moodle idnumber.
 *
 * @param array $params params
 * @return array $data data
 */
function get_user_moodle_idnumber($params) {

    global $DB;

    $functiondataall = $params['functiondata'];

    foreach ($functiondataall as $key => $functiondata) {

        $queryparams = $functiondata;

        $query = "SELECT idnumber FROM {user} WHERE email = :useremail ";

        $singledata = $DB->get_record_sql($query, $queryparams);

        $functiondataall[$key]['values'] = $singledata;
    }

    $return = array();

    $return['data'] = $functiondataall;

    return $return;
}

/**
 * Get wiki activity percent.
 *
 * @param array $params params
 * @return array $data data
 */
function get_wiki_activity_percent($params) {

    global $DB;

    $functiondataall = $params['functiondata'];

    foreach ($functiondataall as $key => $functiondata) {

        $queryparams = $functiondata;

        $query = "SELECT  w.id, w.name wiki_name, cm.id,
                ROUND((COUNT( DISTINCT CASE WHEN log.action='created' AND log.target='comment' THEN log.id
                ELSE NULL END)*100)/COUNT(DISTINCT log.id),2) percent_comment,
                ROUND((COUNT( DISTINCT CASE WHEN log.action='viewed'
                THEN log.id ELSE NULL END)*100)/COUNT(DISTINCT log.id),2) percent_viewed,
                ROUND((COUNT( DISTINCT CASE WHEN (log.action='created' AND log.target<>'comment')
                OR log.action='updated' OR log.action='deleted'
                THEN log.id ELSE NULL END)*100)/COUNT(DISTINCT log.id),2) percent_edited
                FROM {wiki} w
                LEFT JOIN {course} c ON w.course=c.id
                LEFT JOIN {context} con ON con.contextlevel = 50 AND con.instanceid = c.id
                JOIN {modules} m ON m.name='wiki'
                LEFT JOIN {course_modules} cm ON cm.course=w.course AND cm.instance=w.id AND cm.module=m.id
                LEFT JOIN {logstore_standard_log} log ON log.courseid=w.course
                AND log.component='mod_wiki' AND log.contextinstanceid=cm.id
                WHERE c.id>1
                GROUP BY w.id,c.id,cm.id having cm.id = :activityid  ";

        $singledata = $DB->get_record_sql($query, $queryparams);

        $functiondataall[$key]['values'] = $singledata;
    }

    $return = array();

    $return['data'] = $functiondataall;

    return $return;
}

/**
 * Get completion status.
 *
 * @param array $params params
 * @return array $data data
 */
function get_completion_status($params) {

    global $DB;
    global $CFG;
    $functiondataall = $params['functiondata'];

    if ($CFG->dbtype == 'mysqli') {
        $functype = 'GROUP_CONCAT';
        $timefunc = "TIME_TO_SEC(CASE WHEN sst.element = 'cmi.core.total_time' THEN sst.value ELSE null END)";
    } else {
        $functype = 'array_agg';
        $timefunc = "EXTRACT(EPOCH FROM CASE WHEN sst.element = 'cmi.core.total_time' THEN sst.value::time ELSE null END)";
    }

    foreach ($functiondataall as $key => $functiondata) {

        $queryparams = $functiondata;

        $query = "SELECT id FROM {user} WHERE email = :useremail ";

        $userdata = $DB->get_record_sql($query, $queryparams);

        if (!empty($userdata->id)) {

            $paramarr['activityid'] = $queryparams['activityid'];

            $paramarr['uid'] = $userdata->id;

            $query = "SELECT CONCAT(s.id, '_', u.id, '_', c.id) uniquecol,

                         t.*,

                         u.id user_id,

                         cm.id cm_id

                FROM {scorm} s

                JOIN {course} c ON c.id = s.course

                JOIN {modules} m ON m.name = 'scorm'

                JOIN {course_modules} cm ON cm.module = m.id AND cm.instance = s.id

                JOIN (SELECT e.courseid, ue1.userid, MIN(ue1.status) status, MIN(ue1.timeend) timeend

                                FROM {enrol} e

                                JOIN {user_enrolments} ue1 ON ue1.enrolid = e.id

                        GROUP BY e.courseid, ue1.userid

                         ) ue ON ue.courseid = c.id

                JOIN {user} u ON u.id = ue.userid

                    LEFT JOIN (SELECT MIN(sst.id) id,

                                                 sst.userid,

                                                 sst.scormid,

                                                 MAX(sst.timemodified) timemodified,

                                                 MIN(CASE WHEN sst.element = 'x.start.time' THEN sst.value ELSE null END) starttime,

                                                 SUM( $timefunc) duration,

                                                 $functype(CASE WHEN sst.attempt = 1
                                                 AND sst.element IN ('cmi.completion_status', 'cmi.core.lesson_status')
                                                 AND sst.value IN ('completed', 'passed')
                                                 THEN 'completed' ELSE '' END) first_completion_status,

                                                 $functype(CASE WHEN sst.attempt = la.last_attempt_number AND

                                         sst.element IN ('cmi.completion_status', 'cmi.core.lesson_status') AND

                                         sst.value IN ('completed', 'passed')

                                THEN 'completed'

                                ELSE ''

                    END) current_completion_status,

                                     COUNT(DISTINCT(sst.attempt)) attempts

                            FROM {scorm_scoes_track} sst

                            JOIN (SELECT userid, scormid, MAX(attempt) last_attempt_number

                                            FROM {scorm_scoes_track}

                                    GROUP BY userid, scormid

                                     ) la ON la.userid = sst.userid AND la.scormid = sst.scormid

                         WHERE sst.id > 0

                    GROUP BY sst.userid, sst.scormid

                     ) t ON t.scormid = s.id AND t.userid = u.id

                    WHERE u.id = :uid and cm.id = :activityid  ";

            $singledata = $DB->get_record_sql($query, $paramarr);
        }

        $functiondataall[$key]['values'] = $singledata;
    }

    $return = array();

    $return['data'] = $functiondataall;

    return $return;
}

/**
 * Get user activity feedback.
 *
 * @param array $params params
 * @return array $data data
 */
function get_user_activitiy_feedback($params) {

    global $DB;

    $functiondataall = $params['functiondata'];

    foreach ($functiondataall as $key => $functiondata) {

        $queryparams = $functiondata;

        $query = "SELECT assignc.commenttext filename
        from {course_modules} cm
        left JOIN {assignfeedback_comments} assignc ON assignc.assignment = cm.instance
        WHERE cm.id = :activityid AND course = :courseid AND module = '1' ";
        $singledata1 = $DB->get_record_sql($query, $queryparams);

        $query = "SELECT qf.feedbacktext filename
        from {course_modules} cm left JOIN {quiz_feedback} qf ON qf.quizid = cm.instance
        WHERE cm.id = :activityid AND course = :courseid AND module = '17'
        AND qf.feedbacktext is not null AND qf.feedbacktext != '' ";
        $singledata2 = $DB->get_record_sql($query, $queryparams);

        if (!empty($singledata2)) {
            $singledata = array_merge($singledata1, $singledata2);
        }
        $functiondataall[$key]['values'] = $singledata;
    }
    $return = array();

    $return['data'] = $functiondataall;

    return $return;
}

/**
 * Get all data in sql.
 *
 * @param array $params params
 * @return array $data data
 */
function get_all_data($params) {

    global $DB, $CFG;

    $mainquery = $params['functiondata'];

    if (strpos($mainquery['sql_query'], 'AS INT') !== false) {
        if ($CFG->dbtype != 'mysqli') {
            $mainquery['sql_query'] = str_replace('AS UNSIGNED', 'AS INT', $mainquery['sql_query']);
        }
    }

    if (strpos($mainquery['sql_query'], 'GROUP_CONCAT') !== false) {
        if ($CFG->dbtype != 'mysqli') {
            $mainquery['sql_query'] = str_replace('GROUP_CONCAT', 'array_agg', $mainquery['sql_query']);
        }
    }

    if (strpos($mainquery['sql_query'], 'RAND') !== false) {
        if ($CFG->dbtype != 'mysqli') {
            $mainquery['sql_query'] = str_replace('RAND', 'random', $mainquery['sql_query']);
        }
    }

    if ($CFG->dbtype != 'mysqli') {

        $query = $mainquery['sql_query'] . " OFFSET " . $mainquery['start'] . ' limit ' . $mainquery['end'];

        $singledata = $DB->get_records_sql($query);

        $query = $mainquery['sql_query'] . " OFFSET " . $mainquery['start_count'] . ' limit ' . $mainquery['end'];

        $singledata2 = $DB->get_records_sql($query);
    } else {

        $query = $mainquery['sql_query'] . " limit " . $mainquery['start'] . ',' . $mainquery['end'];

        $singledata = $DB->get_records_sql($query);

        $query = $mainquery['sql_query'] . " limit " . $mainquery['start_count'] . ',' . $mainquery['end'];

        $singledata2 = $DB->get_records_sql($query);
    }

    $functiondataall['']['values'] = $singledata;

    $functiondataall['']['count'] = $singledata2;

    $return = array();

    $return['data'] = $functiondataall;

    return $return;
}


/**
 * Get custom fields
 *
 * @param array $params params
 * @return array $data data
 */
function get_custom_fields($params) {

    global $DB;
    if ($params['functiondata'] == 'userfield') {
        $query = "SELECT * from {user_info_field} ";
        $singledata1 = $DB->get_records_sql($query);
    } else {
        $query = "SELECT * from {customfield_field} ";
        $singledata1 = $DB->get_records_sql($query);
    }


    $return = array();

    $return['data'] = $singledata1;

    return $return;
}