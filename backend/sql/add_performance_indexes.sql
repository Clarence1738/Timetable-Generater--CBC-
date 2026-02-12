-- Backend Performance Optimization: Database Indexes
-- Run this script to add indexes for frequently queried fields
-- These indexes will significantly improve query performance
-- Using IF NOT EXISTS to safely handle existing indexes

-- Indexes for timetable_entries (most frequently queried table)
-- Used in: view(), teacherView(), generate() for looking up entries by class_id, day, period
ALTER TABLE `timetable_entries` 
ADD INDEX IF NOT EXISTS `idx_class_id` (`class_id`),
ADD INDEX IF NOT EXISTS `idx_day_period` (`day`, `period`),
ADD INDEX IF NOT EXISTS `idx_examiner_id` (`examiner_id`),
ADD INDEX IF NOT EXISTS `idx_class_day_period` (`class_id`, `day`, `period`);

-- Indexes for teacher_subject_grades (heavily used in generate())
-- Used for: finding teachers by stream, subject, grade
ALTER TABLE `teacher_subject_grades`
ADD INDEX IF NOT EXISTS `idx_teacher_id` (`teacher_id`),
ADD INDEX IF NOT EXISTS `idx_subject_id` (`subject_id`),
ADD INDEX IF NOT EXISTS `idx_grade_id` (`grade_id`),
ADD INDEX IF NOT EXISTS `idx_stream_id` (`stream_id`),
ADD INDEX IF NOT EXISTS `idx_teacher_subject_grade_stream` (`teacher_id`, `subject_id`, `grade_id`, `stream_id`);

-- Indexes for streams (used in generate() to load streams and expand assignments)
-- Used for: looking up streams by grade
ALTER TABLE `streams`
ADD INDEX IF NOT EXISTS `idx_grade_id` (`grade_id`),
ADD INDEX IF NOT EXISTS `idx_grade_stream` (`grade_id`, `stream_id`);

-- Index for grade_subject_requirements (used in generate())
-- Used for: loading requirements by grade
ALTER TABLE `grade_subject_requirements`
ADD INDEX IF NOT EXISTS `idx_grade` (`grade`);

-- Index for timetable_config (used frequently in getPeriodsForGrade, getAllPeriodsByLevel)
-- Used for: looking up periods configuration
ALTER TABLE `timetable_config`
ADD INDEX IF NOT EXISTS `idx_level` (`level`);

-- Optional: Composite primary key if not already present
-- ALTER TABLE `timetable_entries` ADD PRIMARY KEY (`id`);
-- ALTER TABLE `teacher_subject_grades` ADD PRIMARY KEY (`id`);
