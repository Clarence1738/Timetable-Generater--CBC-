<?php

use FastRoute\RouteCollector;

return function (RouteCollector $r): void {
    $r->addRoute('POST', '/auth/login', 'AuthController@login');
    $r->addRoute('GET', '/auth/check', 'AuthController@check');
    $r->addRoute('GET', '/auth/logout', 'AuthController@logout');

    $r->addRoute('GET', '/settings/classes', 'ClassesController@list');

    $r->addRoute('GET', '/settings/subjects', 'TeachersController@subjects');
    $r->addRoute('POST', '/settings/subjects', 'TeachersController@saveSubject');
    $r->addRoute('POST', '/settings/subjects/delete', 'TeachersController@deleteSubject');
    $r->addRoute('GET', '/settings/subject-stats', 'TeachersController@subjectStats');

    $r->addRoute('GET', '/settings/grades', 'TeachersController@grades');
    $r->addRoute('POST', '/settings/grades', 'TeachersController@saveGrade');
    $r->addRoute('POST', '/settings/grades/delete', 'TeachersController@deleteGrade');

    // Stream management
    $r->addRoute('GET', '/settings/streams', 'TeachersController@streams');
    $r->addRoute('POST', '/settings/streams', 'TeachersController@saveStream');
    $r->addRoute('POST', '/settings/streams/delete', 'TeachersController@deleteStream');

    $r->addRoute('GET', '/settings/teachers', 'TeachersController@teachers');
    $r->addRoute('POST', '/settings/teachers', 'TeachersController@saveTeacher');
    $r->addRoute('POST', '/settings/teachers/delete', 'TeachersController@deleteTeacher');

    $r->addRoute('GET', '/timetable/config', 'TimetableController@config');
    $r->addRoute('POST', '/timetable/config', 'TimetableController@saveConfig');
    $r->addRoute('GET', '/timetable/requirements', 'TimetableController@requirements');
    $r->addRoute('POST', '/timetable/requirements', 'TimetableController@saveRequirements');
    $r->addRoute('POST', '/timetable/generate', 'TimetableController@generate');
    $r->addRoute('GET', '/timetable/view', 'TimetableController@view');
    $r->addRoute('GET', '/timetable/teacher-view', 'TimetableController@teacherView');
};
