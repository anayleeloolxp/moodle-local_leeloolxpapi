require(["jquery"], function($) {

    $(document).ready(function() {

        var workshopgardearsyncid = window.atob($('#local_leeloolxpapi_workshopgardearsyncid').val());
        var teamniourl = window.atob($('#local_leeloolxpapi_teamniourl').val()); 
        var email = $('#local_leeloolxpapi_email').val(); 
        var auto_increment_course_completions = window.atob($('#local_leeloolxpapi_auto_increment_course_completions').val()); 
        var scale_max_id = window.atob($('#local_leeloolxpapi_scale_max_id').val()); 
        var auto_increment = window.atob($('#local_leeloolxpapi_auto_increment').val()); 
        var course_id = window.atob($('#local_leeloolxpapi_course_id').val()); 

        if (workshopgardearsyncid != 0) {
            // delete  workshop ar grades
            $("#page-mod-workshop-submission .btn-primary").click(function() {

                var datas = {};

                var dataArray = $('form').serialize();

                $.ajax({

                    async: false,

                    url: teamniourl+"/admin/sync_moodle_course/delete_workshop_grade/?" + dataArray + '&id=' + workshopgardearsyncid,

                    type: "post",

                    data: {},

                    success: function(tdata) {}

                });
            
            });
        }
        
        // auto_increment_course_completions
        $("#page-course-togglecompletion .btn-primary").click(function(e) {

            // e.preventDefault();
            var datas = {};

            var dataArray = $('form').serialize() + '&email=' + email + '&id=' + auto_increment_course_completions;

            $.ajax({

                async: false,

                url: teamniourl+"/admin/sync_moodle_course/insert_update_course_completion/?" + dataArray,

                type: "post",

                data: {},

                success: function(tdata) {}

            });

        });

        $("#page-grade-edit-tree-category .btn-primary").click(function() {

            var datas = {};

            var dataArray = $('form').serialize();

            $.ajax({

                async: false,

                url: teamniourl+"/admin/sync_moodle_course/add_grade_category/?" + dataArray,

                type: "post",

                data: {},

                success: function(tdata) {
                    /*console.log(tdata);alert(tdata)*/ }

            });

        });

        $("#page-grade-edit-tree-index .btn-primary").click(function() {

            var datas = {};

            var dataArray = $('form').serialize();

            $.ajax({

                async: false,

                url: teamniourl+"/admin/sync_moodle_course/delete_grade_items/?" + dataArray,

                type: "post",

                data: {},

                success: function(tdata) {}

            });

        });


        $("#page-admin-setting-gradessettings .btn-primary").click(function() {

            console.log('gradessettings');
            var datas = {};

            var dataArray = $('form').serialize();

            $.ajax({

                async: false,

                url: teamniourl+"/admin/sync_moodle_course/sync_grades/?" + dataArray,

                type: "post",

                data: {},

                success: function(tdata) {}

            });
        });

        $("#page-admin-setting-gradecategorysettings .btn-primary").click(function() {

            var datas = {};

            var dataArray = $('form').serialize();

            $.ajax({

                async: false,

                url: teamniourl+"/admin/sync_moodle_course/gradecategorysettings/?" + dataArray,

                type: "post",

                data: {},

                success: function(tdata) {

                }

            });
        });
        $("#page-admin-setting-gradeitemsettings .btn-primary").click(function() {

            var datas = {};

            var dataArray = $('form').serialize();

            $.ajax({

                async: false,

                url: teamniourl+"/admin/sync_moodle_course/gradeitemsettings/?" + dataArray,

                type: "post",

                data: {},

                success: function(tdata) {

                }

            });
        });

        $("#page-admin-grade-edit-scale-edit .btn-primary").click(function() {
            //alert();
            var datas = {};

            var dataArray = $('form').serialize() + '&email=' + email;
            $.ajax({
                async: false,
                url: teamniourl+"/admin/sync_moodle_course/scale/?" + dataArray + '&max_id=' + scale_max_id,
                type: "post",
                data: {
                    auto_increment: auto_increment
                },
                success: function(tdata) {
                    //alert(tdata);
                }

            });

        });

        $("#page-grade-edit-scale-edit .btn-primary").click(function() {
            var datas = {};

            var dataArray = $('form').serialize() + '&email=' + email;
            $.ajax({
                async: false,
                url: teamniourl+"/admin/sync_moodle_course/scale/?" + dataArray + '&max_id=' + scale_max_id,
                type: "post",
                data: {
                    auto_increment: auto_increment
                },
                success: function(tdata) {
                    //alert(tdata);
                }

            });
        });
        $("#page-admin-grade-edit-letter-index .btn-primary").click(function() {
            var datas = {};
            var dataArray = $('form').serialize();
            $.ajax({

                async: false,

                url: teamniourl+"/admin/sync_moodle_course/gradeeditletter/?" + dataArray,

                type: "post",

                data: {},

                success: function(tdata) {

                    console.log(tdata)
                    //alert(tdata)

                }

            });
        });

        $("#page-grade-edit-letter-index .btn-primary").click(function() {
            var datas = {};
            var dataArray = $('form').serialize() + '&course_id=' + course_id;
            $.ajax({

                async: false,

                url: teamniourl+"/admin/sync_moodle_course/gradeeditletter/?" + dataArray,

                type: "post",

                data: {},

                success: function(tdata) {

                    console.log(tdata)

                }

            });
        });


        $("#page-admin-setting-gradereportgrader .btn-primary").click(function() {
            var datas = {};
            var dataArray = $('form').serialize();
            $.ajax({

                async: false,
                url: teamniourl+"/admin/sync_moodle_course/gradereportgrader/?" + dataArray,

                type: "post",

                data: {},

                success: function(tdata) {

                }

            });
        });

        $("#page-admin-setting-gradereporthistory .btn-primary").click(function() {
            var datas = {};
            var dataArray = $('form').serialize();
            $.ajax({

                async: false,

                url: teamniourl+"/admin/sync_moodle_course/gradereporthistory/?" + dataArray,

                type: "post",

                data: {},

                success: function(tdata) {

                }

            });
        });
        $("#page-admin-setting-gradereportoverview .btn-primary").click(function() {
            var datas = {};
            var dataArray = $('form').serialize();
            $.ajax({

                async: false,

                url: teamniourl+"/admin/sync_moodle_course/gradereportoverview/?" + dataArray,

                type: "post",

                data: {},

                success: function(tdata) {

                }

            });
        });

        $("#page-admin-setting-gradereportuser .btn-primary").click(function() {
            var datas = {};
            var dataArray = $('form').serialize();
            $.ajax({

                async: false,

                url: teamniourl+"/admin/sync_moodle_course/gradereportuser/?" + dataArray,

                type: "post",

                data: {},

                success: function(tdata) {

                }
            });

        });


        $("#page-grade-edit-settings-index .btn-primary").click(function() {
            var datas = {};
            var dataArray = $('form').serialize();
            $.ajax({

                async: false,

                url: teamniourl+"/admin/sync_moodle_course/course_grade_setting/?" + dataArray,

                type: "post",

                data: {},

                success: function(tdata) {

                }
            });

        });

        $("#page-grade-report-grader-preferences .btn-primary").click(function() {
            var datas = {};
            var dataArray = $('form').serialize() + '&email=' + email;
            $.ajax({

                async: false,

                url: teamniourl+"/admin/sync_moodle_course/grade_report_preferences/?" + dataArray,

                type: "post",

                data: {},

                success: function(tdata) {

                }
            });

        });

        //delete scale from moodle also
        $("#page-admin-grade-edit-scale-index .btn-primary ,#page-grade-edit-scale-index .btn-primary ").click(function() {

            var dataArray = $('form').serialize();

            $.ajax({

                async: false,

                url: teamniourl+"/admin/sync_moodle_course/delete_global_scale/?" + dataArray,

                type: "post",

                data: {},

                success: function(tdata) {

                }
            });

        });
    });
});
