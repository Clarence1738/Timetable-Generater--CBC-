<?php

declare(strict_types=1);

namespace App\Controllers;

use Medoo\Medoo;
use App\Services\CacheService;

class TimetableController
{
    private Medoo $db;
    private CacheService $cache;

    private const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    private const DEFAULT_PERIODS = 8;
    private const MAX_ATTEMPTS = 80;

    public function __construct(Medoo $db)
    {
        $this->db = $db;
        $this->cache = new CacheService($db);
    }

    private function requireAuth(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            return false;
        }

        return true;
    }

    private function readPayload(): array
    {
        $payload = $_POST;
        if (empty($payload)) {
            $raw = file_get_contents('php://input');
            $payload = json_decode($raw ?: '', true) ?: [];
        }

        return is_array($payload) ? $payload : [];
    }

    public function config(): void
    {
        if (!$this->requireAuth()) {
            return;
        }

        try {
            // Load periods for each grade level (stored as JSON or separate rows)
            $rows = $this->db->select('timetable_config', ['id', 'periods_per_day', 'level']);
            
            $levels = [];
            foreach ($rows as $row) {
                $level = $row['level'] ?? '1';
                $levels[$level] = (int)$row['periods_per_day'];
            }
            
            // Default to 8 if not set
            if (empty($levels)) {
                $levels = ['1' => 8, '2' => 8, '3' => 8];
            }

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => [
                    'days' => self::DAYS,
                    'levels' => $levels,
                ],
            ]);
        } catch (\Throwable $e) {
            error_log('[TimetableController] config error: ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to load timetable config']);
        }
    }

    public function saveConfig(): void
    {
        if (!$this->requireAuth()) {
            return;
        }

        $payload = $this->readPayload();
        $levels = $payload['levels'] ?? [];
        
        // Validate
        if (!is_array($levels) || empty($levels)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid configuration data']);
            return;
        }
        
        foreach ($levels as $level => $periods) {
            $periods = (int)$periods;
            if ($periods <= 0 || $periods > 20) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Invalid periods per day for level ' . $level]);
                return;
            }
        }

        try {
            // Save/update each level
            foreach ($levels as $level => $periods) {
                $existing = $this->db->get('timetable_config', ['id'], ['level' => (string)$level]);
                if ($existing) {
                    $this->db->update('timetable_config', [
                        'periods_per_day' => (int)$periods,
                    ], ['level' => (string)$level]);
                } else {
                    $this->db->insert('timetable_config', [
                        'periods_per_day' => (int)$periods,
                        'level' => (string)$level,
                    ]);
                }
            }

            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            error_log('[TimetableController] saveConfig error: ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to save config']);
        }
    }

    public function requirements(): void
    {
        if (!$this->requireAuth()) {
            return;
        }

        $grade = isset($_GET['grade']) ? (int)$_GET['grade'] : 0;
        if ($grade <= 0) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid grade']);
            return;
        }

        try {
            $sql = "SELECT s.subject_id, s.name, COALESCE(r.lessons_per_week, 0) AS lessons_per_week
                FROM subjects s
                LEFT JOIN grade_subject_requirements r
                    ON r.subject_id = s.subject_id AND r.grade = :grade
                ORDER BY s.name";
            $stmt = $this->db->query($sql, [':grade' => $grade]);
            $rows = $stmt ? $stmt->fetchAll() : [];

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $rows,
            ]);
        } catch (\Throwable $e) {
            error_log('[TimetableController] requirements error: ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to load requirements']);
        }
    }

    public function saveRequirements(): void
    {
        if (!$this->requireAuth()) {
            return;
        }

        $payload = $this->readPayload();
        $grade = isset($payload['grade']) ? (int)$payload['grade'] : 0;
        $requirements = $payload['requirements'] ?? [];

        if ($grade <= 0 || !is_array($requirements)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid requirements data']);
            return;
        }

        try {
            $this->db->delete('grade_subject_requirements', ['grade' => $grade]);

            foreach ($requirements as $req) {
                $subjectId = isset($req['subject_id']) ? (int)$req['subject_id'] : 0;
                $lessons = isset($req['lessons_per_week']) ? (int)$req['lessons_per_week'] : 0;
                if ($subjectId <= 0 || $lessons <= 0) {
                    continue;
                }

                $this->db->insert('grade_subject_requirements', [
                    'grade' => $grade,
                    'subject_id' => $subjectId,
                    'lessons_per_week' => $lessons,
                ]);
            }

            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            error_log('[TimetableController] saveRequirements error: ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to save requirements']);
        }
    }

    public function generate(): void
    {
        if (!$this->requireAuth()) {
            return;
        }

        try {
            $days = self::DAYS;
            $periodsByLevel = $this->getAllPeriodsByLevel();
            
            // Load streams with grade numbers
            $sql = "SELECT s.stream_id, s.grade_id, s.name, g.grade as grade_number 
                    FROM streams s 
                    LEFT JOIN grades g ON s.grade_id = g.grade_id 
                    ORDER BY g.grade ASC, s.name ASC";
            $stmt = $this->db->query($sql);
            $streams = $stmt ? $stmt->fetchAll() : [];

            if (!$streams) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'No streams found']);
                return;
            }

            $requirementsByGrade = $this->loadRequirementsByGrade();
            
            // Validate that all grades have requirements (requirements are keyed by grade_id)
            $gradesInStreams = array_unique(array_column($streams, 'grade_id'));
            $gradeIdToNumber = [];
            foreach ($streams as $s) {
                $gradeIdToNumber[(int)$s['grade_id']] = $s['grade_number'] ?? $s['grade_id'];
            }
            
            foreach ($gradesInStreams as $gradeId) {
                if (empty($requirementsByGrade[$gradeId])) {
                    $gradeNum = $gradeIdToNumber[$gradeId] ?? $gradeId;
                    http_response_code(400);
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'message' => 'Missing requirements for Grade ' . $gradeNum . '. Please set up subject requirements first.',
                    ]);
                    return;
                }
            }

            $teacherMaps = $this->loadTeacherMaps();
            $subjectNames = $this->loadSubjects();

            $requirementsByClass = $this->mapRequirementsToStreams($streams, $requirementsByGrade);
            $classSubjectTeacher = $this->assignTeachers($streams, $requirementsByClass, $teacherMaps, $subjectNames);

            $attempt = 0;
            $entries = [];
            $classSchedules = [];
            $teacherSchedule = [];
            $classOrder = $streams;
            $generationSucceeded = false;
            $lastFailure = 'Failed to generate a valid timetable. Try adjusting requirements or periods.';

            while ($attempt < self::MAX_ATTEMPTS) {
                $attempt++;
                $entries = [];
                $classSchedules = [];
                $teacherSchedule = [];
                shuffle($classOrder);

                $success = true;
                foreach ($classOrder as $stream) {
                    $streamId = (int)$stream['stream_id'];
                    $gradeNumber = (int)($stream['grade_number'] ?? 1);
                    $streamName = 'Grade ' . $gradeNumber . ' ' . $stream['name'];
                    
                    // Get periods for this grade level
                    $periods = $this->getPeriodsForGrade($gradeNumber);
                    
                    $result = $this->buildClassSchedule(
                        $streamId,
                        $streamName,
                        $requirementsByClass[$streamId],
                        $classSubjectTeacher[$streamId] ?? [],
                        $days,
                        $periods,
                        $teacherSchedule,
                        $subjectNames
                    );

                    if (!$result['success']) {
                        $success = false;
                        $lastFailure = $result['message'] ?? $lastFailure;
                        break;
                    }

                    $classSchedules[$streamId] = $result['grid'];
                    $entries = array_merge($entries, $result['entries']);
                    $teacherSchedule = $result['teacherSchedule'];
                }

                if ($success) {
                    $generationSucceeded = true;
                    break;
                }
            }

            if (!$generationSucceeded) {
                http_response_code(409);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => $lastFailure,
                ]);
                return;
            }

            $streamIds = array_column($streams, 'stream_id');
            $this->db->delete('timetable_entries', ['class_id' => $streamIds]);
            foreach ($entries as $entry) {
                $this->db->insert('timetable_entries', $entry);
            }

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => [
                    'attempts' => $attempt,
                    'entries' => count($entries),
                ],
            ]);
        } catch (\Throwable $e) {
            error_log('[TimetableController] generate error: ' . $e->getMessage());
            error_log('[TimetableController] Stack trace: ' . $e->getTraceAsString());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to generate timetable: ' . $e->getMessage()]);
        }
    }

    public function view(): void
    {
        if (!$this->requireAuth()) {
            return;
        }

        $streamId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
        if ($streamId <= 0) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid stream id']);
            return;
        }

        try {
            // Get stream with grade number
            $sql = "SELECT s.stream_id, s.grade_id, s.name, g.grade as grade_number 
                    FROM streams s 
                    LEFT JOIN grades g ON s.grade_id = g.grade_id 
                    WHERE s.stream_id = :stream_id";
            $stmt = $this->db->query($sql, [':stream_id' => $streamId]);
            $stream = $stmt ? $stmt->fetch() : null;
            
            if (!$stream) {
                http_response_code(404);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Stream not found']);
                return;
            }
            
            // Get periods for this grade level
            $gradeNumber = (int)($stream['grade_number'] ?? 1);
            $periods = $this->getPeriodsForGrade($gradeNumber);

            $sql = "SELECT te.day, te.period, te.room, s.name AS subject, t.name AS examiner
                FROM timetable_entries te
                LEFT JOIN subjects s ON te.subject_id = s.subject_id
                LEFT JOIN teachers t ON te.examiner_id = t.teacher_id
                WHERE te.class_id = :stream_id";
            $stmt = $this->db->query($sql, [':stream_id' => $streamId]);
            $rows = $stmt ? $stmt->fetchAll() : [];

            $dayIndex = array_flip(self::DAYS);
            usort($rows, function ($a, $b) use ($dayIndex) {
                $dayA = $dayIndex[$a['day']] ?? 0;
                $dayB = $dayIndex[$b['day']] ?? 0;
                if ($dayA === $dayB) {
                    return ((int)$a['period']) <=> ((int)$b['period']);
                }
                return $dayA <=> $dayB;
            });

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => [
                    'class' => $stream,
                    'days' => self::DAYS,
                    'periods_per_day' => $periods,
                    'entries' => $rows,
                ],
            ]);
        } catch (\Throwable $e) {
            error_log('[TimetableController] view error: ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to load timetable']);
        }
    }

    public function teacherView(): void
    {
        if (!$this->requireAuth()) {
            return;
        }

        $teacherId = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : 0;
        if ($teacherId <= 0) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid teacher id']);
            return;
        }

        try {
            $teacher = $this->db->get('teachers', ['teacher_id', 'name'], ['teacher_id' => $teacherId]);
            if (!$teacher) {
                http_response_code(404);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Teacher not found']);
                return;
            }

            $sql = "SELECT te.day, te.period, te.room, s.name AS subject, st.name AS stream_name, g.grade as grade_number
                FROM timetable_entries te
                LEFT JOIN subjects s ON te.subject_id = s.subject_id
                LEFT JOIN streams st ON te.class_id = st.stream_id
                LEFT JOIN grades g ON st.grade_id = g.grade_id
                WHERE te.examiner_id = :teacher_id";
            $stmt = $this->db->query($sql, [':teacher_id' => $teacherId]);
            $rows = $stmt ? $stmt->fetchAll() : [];

            $dayIndex = array_flip(self::DAYS);
            usort($rows, function ($a, $b) use ($dayIndex) {
                $dayA = $dayIndex[$a['day']] ?? 0;
                $dayB = $dayIndex[$b['day']] ?? 0;
                if ($dayA === $dayB) {
                    return ((int)$a['period']) <=> ((int)$b['period']);
                }
                return $dayA <=> $dayB;
            });

            $maxPeriods = 0;
            foreach ($rows as $row) {
                $gradeNumber = (int)($row['grade_number'] ?? 0);
                if ($gradeNumber <= 0) {
                    $gradeNumber = 1;
                }
                $maxPeriods = max($maxPeriods, $this->getPeriodsForGrade($gradeNumber));
            }

            if ($maxPeriods <= 0) {
                $maxPeriods = self::DEFAULT_PERIODS;
            }

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => [
                    'teacher' => $teacher,
                    'days' => self::DAYS,
                    'periods_per_day' => $maxPeriods,
                    'entries' => $rows,
                ],
            ]);
        } catch (\Throwable $e) {
            error_log('[TimetableController] teacherView error: ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to load teacher timetable']);
        }
    }

    private function getPeriodsForGrade(int $gradeNumber): int
    {
        // Determine the level based on grade number
        // Level 1 = Grade 1-3, Level 2 = Grade 4-6, Level 3 = Grade 7-9
        if ($gradeNumber >= 1 && $gradeNumber <= 3) {
            $level = '1';
        } elseif ($gradeNumber >= 4 && $gradeNumber <= 6) {
            $level = '2';
        } else {
            $level = '3';
        }
        
        // Use cached periods by level
        $periodsByLevel = $this->cache->getPeriodsByLevel();
        $periods = $periodsByLevel[$level] ?? self::DEFAULT_PERIODS;
        return $periods > 0 ? $periods : self::DEFAULT_PERIODS;
    }
    
    private function getAllPeriodsByLevel(): array
    {
        // Use cached periods by level
        $periodsByLevel = $this->cache->getPeriodsByLevel();
        return [
            '1' => $periodsByLevel['1'] ?? self::DEFAULT_PERIODS,
            '2' => $periodsByLevel['2'] ?? self::DEFAULT_PERIODS,
            '3' => $periodsByLevel['3'] ?? self::DEFAULT_PERIODS,
        ];
    }

    private function loadRequirementsByGrade(): array
    {
        // Use cached requirements
        return $this->cache->getRequirementsByGrade();
    }

    private function mapRequirementsToClasses(array $classes, array $requirementsByGrade): array
    {
        $requirementsByClass = [];
        foreach ($classes as $class) {
            $gradeId = (int)$class['grade_id'];
            // Use gradeId instead of grade value since requirements are keyed by grade_id
            $requirementsByClass[$gradeId] = $requirementsByGrade[$gradeId] ?? [];
        }

        return $requirementsByClass;
    }

    private function mapRequirementsToStreams(array $streams, array $requirementsByGrade): array
    {
        $requirementsByStream = [];
        foreach ($streams as $stream) {
            $streamId = (int)$stream['stream_id'];
            $gradeId = (int)$stream['grade_id'];
            // Each stream gets the requirements of its grade
            $requirementsByStream[$streamId] = $requirementsByGrade[$gradeId] ?? [];
        }

        return $requirementsByStream;
    }

    private function loadSubjects(): array
    {
        // Use cached subjects
        return $this->cache->getSubjects();
    }

    private function loadTeacherMaps(): array
    {
        // Use cached teacher maps (this is the biggest optimization!)
        return $this->cache->getTeacherMaps();
    }

    private function assignTeachers(array $classes, array $requirementsByClass, array $teacherMaps, array $subjectNames): array
    {
        $streamSubjectTeachers = $teacherMaps['streamSubjectTeachers'];
        $load = [];
        $assignment = [];

        foreach ($classes as $class) {
            // Use stream_id for streams
            $streamId = (int)($class['stream_id'] ?? $class['grade_id']);
            $assignment[$streamId] = [];
            
            // Check if requirements exist for this stream
            if (!isset($requirementsByClass[$streamId])) {
                continue;
            }
            
            foreach ($requirementsByClass[$streamId] as $req) {
                $subjectId = (int)$req['subject_id'];
                
                // Get teachers assigned to this subject FOR THIS SPECIFIC STREAM
                $candidates = $streamSubjectTeachers[$streamId][$subjectId] ?? [];
                
                if (!$candidates) {
                    $gradeNum = $class['grade_number'] ?? $class['grade_id'];
                    $streamName = $class['name'] ?? '';
                    throw new \RuntimeException('No teacher assigned for ' . ($subjectNames[$subjectId] ?? 'Unknown') . ' in Grade ' . $gradeNum . ' ' . $streamName);
                }

                $best = null;
                $bestLoad = null;
                foreach ($candidates as $teacherId) {
                    $current = $load[$teacherId] ?? 0;
                    if ($best === null || $current < $bestLoad) {
                        $best = $teacherId;
                        $bestLoad = $current;
                    }
                }

                $assignment[$streamId][$subjectId] = $best;
                $load[$best] = ($load[$best] ?? 0) + (int)$req['lessons_per_week'];
            }
        }

        return $assignment;
    }

    private function buildClassSchedule(
        int $classId,
        string $className,
        array $requirements,
        array $classSubjectTeacher,
        array $days,
        int $periods,
        array $teacherSchedule,
        array $subjectNames = []
    ): array {
        $totalSlots = count($days) * $periods;
        $lessonCount = 0;
        foreach ($requirements as $req) {
            $lessonCount += (int)$req['lessons_per_week'];
        }

        if ($lessonCount > $totalSlots) {
            return [
                'success' => false,
                'message' => 'Too many lessons for class ' . $className,
            ];
        }

        $grid = [];
        foreach ($days as $day) {
            for ($period = 1; $period <= $periods; $period++) {
                $grid[$day][$period] = null;
            }
        }

        $occurrences = [];
        foreach ($requirements as $req) {
            $subjectId = (int)$req['subject_id'];
            $lessons = (int)$req['lessons_per_week'];
            for ($i = 0; $i < $lessons; $i++) {
                $occurrences[] = $subjectId;
            }
        }
        shuffle($occurrences);

        foreach ($occurrences as $subjectId) {
            $teacherId = $classSubjectTeacher[$subjectId] ?? null;
            if (!$teacherId) {
                return [
                    'success' => false,
                    'message' => 'Missing teacher for subject',
                ];
            }

            $slot = $this->findSlot($grid, $teacherSchedule, $teacherId, $subjectId, $days, $periods, true, 2);
            if ($slot === null) {
                $slot = $this->findSlot($grid, $teacherSchedule, $teacherId, $subjectId, $days, $periods, false, 3);
            }

            if ($slot === null) {
                $subjectName = $subjectNames[$subjectId] ?? "Subject #$subjectId";
                return [
                    'success' => false,
                    'message' => "Failed to place $subjectName in $className - teacher conflict or no available slots",
                ];
            }

            $day = $slot['day'];
            $period = $slot['period'];
            $grid[$day][$period] = [
                'subject_id' => $subjectId,
                'examiner_id' => $teacherId,
            ];
            $teacherSchedule[$teacherId][$day][$period] = true;
        }

        $entries = [];
        foreach ($grid as $day => $periodsMap) {
            foreach ($periodsMap as $period => $cell) {
                if (!$cell) {
                    continue;
                }
                $entries[] = [
                    'class_id' => $classId,
                    'day' => $day,
                    'period' => $period,
                    'subject_id' => $cell['subject_id'],
                    'examiner_id' => $cell['examiner_id'],
                    'room' => $className,
                ];
            }
        }

        return [
            'success' => true,
            'grid' => $grid,
            'entries' => $entries,
            'teacherSchedule' => $teacherSchedule,
        ];
    }

    private function findSlot(
        array $grid,
        array $teacherSchedule,
        int $teacherId,
        int $subjectId,
        array $days,
        int $periods,
        bool $avoidAdjacent,
        int $maxPerDay
    ): ?array {
        $slots = [];
        foreach ($days as $day) {
            for ($period = 1; $period <= $periods; $period++) {
                if ($grid[$day][$period] !== null) {
                    continue;
                }
                $slots[] = ['day' => $day, 'period' => $period];
            }
        }

        $dayLoad = [];
        foreach ($days as $day) {
            $dayLoad[$day] = 0;
            foreach ($grid[$day] as $cell) {
                if ($cell !== null) {
                    $dayLoad[$day]++;
                }
            }
        }

        foreach ($slots as &$slot) {
            $day = $slot['day'];
            $slot['subjectCount'] = $this->countSubjectInDay($grid, $day, $subjectId);
            $slot['dayLoad'] = $dayLoad[$day] ?? 0;
            $slot['rand'] = mt_rand(0, 1000000);
        }
        unset($slot);

        usort($slots, function ($a, $b) {
            if ($a['subjectCount'] !== $b['subjectCount']) {
                return $a['subjectCount'] <=> $b['subjectCount'];
            }
            if ($a['dayLoad'] !== $b['dayLoad']) {
                return $a['dayLoad'] <=> $b['dayLoad'];
            }
            return $a['rand'] <=> $b['rand'];
        });

        foreach ($slots as $slot) {
            $day = $slot['day'];
            $period = $slot['period'];
            if (!empty($teacherSchedule[$teacherId][$day][$period])) {
                continue;
            }

            if ($this->countSubjectInDay($grid, $day, $subjectId) >= $maxPerDay) {
                continue;
            }

            if ($avoidAdjacent) {
                $prev = $grid[$day][$period - 1] ?? null;
                $next = $grid[$day][$period + 1] ?? null;
                if (($prev && $prev['subject_id'] === $subjectId) || ($next && $next['subject_id'] === $subjectId)) {
                    continue;
                }
            }

            return $slot;
        }

        return null;
    }

    private function countSubjectInDay(array $grid, string $day, int $subjectId): int
    {
        $count = 0;
        foreach ($grid[$day] ?? [] as $cell) {
            if ($cell && (int)$cell['subject_id'] === $subjectId) {
                $count++;
            }
        }
        return $count;
    }
}
