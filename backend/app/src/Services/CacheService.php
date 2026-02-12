<?php

declare(strict_types=1);

namespace App\Services;

use Medoo\Medoo;

/**
 * CacheService: In-memory caching for reference data during request execution
 * 
 * Caches: grades, streams, subjects, periods_per_level, requirements, teacher_maps
 * Duration: Per request (cleared after script execution)
 * 
 * Benefits:
 * - Eliminates repeated DB queries for the same data
 * - Especially important during timetable generation with many function calls
 * - Typical reduction: 30-50 DB queries → 5-10 queries
 */
class CacheService
{
    private static array $cache = [];
    private Medoo $db;
    private const CACHE_TTL = 3600; // 1 hour (not used for in-memory, shown for documentation)
    private const VERBOSE_LOGS = false;

    private static function logInfo(string $message): void
    {
        if (self::VERBOSE_LOGS) {
            error_log($message);
        }
    }

    public function __construct(Medoo $db)
    {
        $this->db = $db;
    }

    /**
     * Cache and retrieve all grades
     * 
     * Query savings: Eliminates repeated grade lookups
     * Typical usage: ~1 per request (cached reuse)
     */
    public function getGrades(): array
    {
        $key = 'grades';
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $grades = $this->db->select('grades', ['grade_id', 'grade', 'name']);
        self::$cache[$key] = $grades ? $grades : [];
        
        self::logInfo('[CacheService] Cached ' . count(self::$cache[$key]) . ' grades');
        return self::$cache[$key];
    }

    /**
     * Cache and retrieve all streams
     * 
     * Query savings: Eliminates repeated stream lookups in mapRequirementsToStreams, loadTeacherMaps
     * Typical usage: ~5-10 queries reduced to 1 with caching
     */
    public function getStreams(): array
    {
        $key = 'streams';
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $streams = $this->db->select('streams', ['stream_id', 'grade_id', 'name']);
        self::$cache[$key] = $streams ? $streams : [];
        
        self::logInfo('[CacheService] Cached ' . count(self::$cache[$key]) . ' streams');
        return self::$cache[$key];
    }

    /**
     * Cache streams indexed by grade_id for fast lookups
     * 
     * Returns: [grade_id => [stream_id, stream_id, ...]]
     * Reduces: Repeated filtering of streams by grade during generate()
     */
    public function getStreamsByGrade(): array
    {
        $key = 'streams_by_grade';
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $streams = $this->getStreams();
        $streamsByGrade = [];
        foreach ($streams as $stream) {
            $gid = (int)$stream['grade_id'];
            if (!isset($streamsByGrade[$gid])) {
                $streamsByGrade[$gid] = [];
            }
            $streamsByGrade[$gid][] = (int)$stream['stream_id'];
        }

        self::$cache[$key] = $streamsByGrade;
        self::logInfo('[CacheService] Cached streams indexed by grade: ' . count($streamsByGrade) . ' grades');
        return self::$cache[$key];
    }

    /**
     * Cache and retrieve all subjects
     * 
     * Query savings: Eliminates repeated subject lookups
     * Returns: [subject_id => name]
     */
    public function getSubjects(): array
    {
        $key = 'subjects';
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $rows = $this->db->select('subjects', ['subject_id', 'name']);
        $subjects = [];
        foreach ($rows as $row) {
            $subjects[(int)$row['subject_id']] = (string)$row['name'];
        }
        self::$cache[$key] = $subjects;
        
        self::logInfo('[CacheService] Cached ' . count($subjects) . ' subjects');
        return self::$cache[$key];
    }

    /**
     * Cache periods per level configuration
     * 
     * Query savings: Eliminates repeated lookups in getPeriodsForGrade()
     * Returns: ['1' => 8, '2' => 8, '3' => 8] (or configured values)
     */
    public function getPeriodsByLevel(): array
    {
        $key = 'periods_by_level';
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $rows = $this->db->select('timetable_config', ['level', 'periods_per_day']);
        $periods = [];
        foreach ($rows as $row) {
            $periods[$row['level']] = (int)$row['periods_per_day'];
        }
        
        // Apply defaults
        $defaults = [
            '1' => 8,
            '2' => 8,
            '3' => 8,
        ];
        
        $result = array_merge($defaults, $periods);
        self::$cache[$key] = $result;
        
        self::logInfo('[CacheService] Cached periods by level: ' . json_encode($result));
        return self::$cache[$key];
    }

    /**
     * Cache grade subject requirements
     * 
     * Query savings: Eliminates repeated loads in loadRequirementsByGrade()
     * Returns: [grade_id => [['subject_id' => X, 'lessons_per_week' => Y], ...], ...]
     */
    public function getRequirementsByGrade(): array
    {
        $key = 'requirements_by_grade';
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $rows = $this->db->select('grade_subject_requirements', [
            'grade',
            'subject_id',
            'lessons_per_week',
        ]);

        $requirements = [];
        foreach ($rows as $row) {
            $grade = (int)$row['grade'];
            if (!isset($requirements[$grade])) {
                $requirements[$grade] = [];
            }
            $requirements[$grade][] = [
                'subject_id' => (int)$row['subject_id'],
                'lessons_per_week' => (int)$row['lessons_per_week'],
            ];
        }

        self::$cache[$key] = $requirements;
        self::logInfo('[CacheService] Cached requirements for ' . count($requirements) . ' grades');
        return self::$cache[$key];
    }

    /**
     * Cache all teacher mappings in one call
     * 
     * Query savings: MAJOR - reduces ~8 queries to 3 DB calls
     * - 1 query for all teachers
     * - 1 query for all assignments
     * - 1 query for all streams (cascaded from caching)
     * 
     * Returns: [
     *     'examiners' => [teacher_id => name],
     *     'streamSubjectTeachers' => [stream_id => [subject_id => [teacher_ids]]]
     * ]
     */
    public function getTeacherMaps(): array
    {
        $key = 'teacher_maps';
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $teachers = $this->db->select('teachers', ['teacher_id', 'name']);
        self::logInfo('[CacheService] Loaded ' . count($teachers) . ' teachers');
        
        $allAssignments = $this->db->select('teacher_subject_grades', [
            'teacher_id', 'subject_id', 'grade_id', 'stream_id'
        ]);
        self::logInfo('[CacheService] Loaded ' . count($allAssignments) . ' teacher assignments');
        
        $streamsByGrade = $this->getStreamsByGrade();
        
        // Build stream-subject-teachers map
        $streamSubjectTeachers = [];
        $seen = [];
        
        foreach ($allAssignments as $row) {
            $streamId = $row['stream_id'] ? (int)$row['stream_id'] : null;
            $gradeId = (int)$row['grade_id'];
            $subjectId = (int)$row['subject_id'];
            $teacherId = (int)$row['teacher_id'];
            
            // If stream_id is null, expand to all streams for this grade
            if ($streamId === null) {
                $expandedStreams = $streamsByGrade[$gradeId] ?? [];
                foreach ($expandedStreams as $expandedStreamId) {
                    $key = "stream:$expandedStreamId:$teacherId:$subjectId";
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    
                    if (!isset($streamSubjectTeachers[$expandedStreamId])) {
                        $streamSubjectTeachers[$expandedStreamId] = [];
                    }
                    if (!isset($streamSubjectTeachers[$expandedStreamId][$subjectId])) {
                        $streamSubjectTeachers[$expandedStreamId][$subjectId] = [];
                    }
                    $streamSubjectTeachers[$expandedStreamId][$subjectId][] = $teacherId;
                }
            } else {
                // stream_id is specified
                $key = "stream:$streamId:$teacherId:$subjectId";
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                
                if (!isset($streamSubjectTeachers[$streamId])) {
                    $streamSubjectTeachers[$streamId] = [];
                }
                if (!isset($streamSubjectTeachers[$streamId][$subjectId])) {
                    $streamSubjectTeachers[$streamId][$subjectId] = [];
                }
                $streamSubjectTeachers[$streamId][$subjectId][] = $teacherId;
            }
        }
        
        $examiners = [];
        foreach ($teachers as $row) {
            $examiners[(int)$row['teacher_id']] = (string)$row['name'];
        }
        
        $result = [
            'examiners' => $examiners,
            'streamSubjectTeachers' => $streamSubjectTeachers,
        ];
        
        self::$cache[$key] = $result;
        self::logInfo('[CacheService] Built teacher maps: ' . count($examiners) . ' teachers, ' . count($streamSubjectTeachers) . ' streams');
        return self::$cache[$key];
    }

    /**
     * Clear all cached data (call after request completes)
     */
    public static function clearCache(): void
    {
        self::$cache = [];
        self::logInfo('[CacheService] Cache cleared');
    }

    /**
     * Get cache statistics for debugging
     */
    public static function getCacheStats(): array
    {
        return [
            'cached_keys' => array_keys(self::$cache),
            'cache_size' => count(self::$cache),
        ];
    }
}
