<?php

declare(strict_types=1);

namespace App\Controllers;

use Medoo\Medoo;

class TeachersController
{
    private Medoo $db;

    public function __construct(Medoo $db)
    {
        $this->db = $db;
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

    public function subjects(): void
    {
        if (!$this->requireAuth()) {
            return;
        }

        try {
            $rows = $this->db->select('subjects', ['subject_id', 'name'], [
                'ORDER' => ['name' => 'ASC'],
            ]);

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'data' => $rows]);
        } catch (\Throwable $e) {
            error_log('[TeachersController] subjects error: ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to load subjects']);
        }
    }

    public function subjectStats(): void
    {
        if (!$this->requireAuth()) {
            return;
        }

        try {
            // Query using conditional aggregation to count teachers per grade level
            $query = "
                SELECT 
                    s.subject_id,
                    s.name as subject_name,
                    COUNT(DISTINCT CASE 
                        WHEN g.grade IN (1, 2, 3) THEN tsg.teacher_id 
                    END) as level1_count,
                    COUNT(DISTINCT CASE 
                        WHEN g.grade IN (4, 5, 6) THEN tsg.teacher_id 
                    END) as level2_count,
                    COUNT(DISTINCT CASE 
                        WHEN g.grade IN (7, 8, 9) THEN tsg.teacher_id 
                    END) as level3_count
                FROM subjects s
                LEFT JOIN teacher_subject_grades tsg ON s.subject_id = tsg.subject_id
                LEFT JOIN streams st ON tsg.stream_id = st.stream_id
                LEFT JOIN grades g ON st.grade_id = g.grade_id
                GROUP BY s.subject_id, s.name
                ORDER BY s.name ASC
            ";
            $rows = $this->db->query($query)->fetchAll();

            // Organize by level
            $stats = [
                '1' => ['level_name' => 'Grade 1-3', 'subjects' => []],
                '2' => ['level_name' => 'Grade 4-6', 'subjects' => []],
                '3' => ['level_name' => 'Grade 7-9', 'subjects' => []],
            ];

            foreach ($rows as $row) {
                $stats['1']['subjects'][] = [
                    'subject_id' => $row['subject_id'],
                    'subject_name' => $row['subject_name'],
                    'teacher_count' => (int)($row['level1_count'] ?? 0),
                ];
                $stats['2']['subjects'][] = [
                    'subject_id' => $row['subject_id'],
                    'subject_name' => $row['subject_name'],
                    'teacher_count' => (int)($row['level2_count'] ?? 0),
                ];
                $stats['3']['subjects'][] = [
                    'subject_id' => $row['subject_id'],
                    'subject_name' => $row['subject_name'],
                    'teacher_count' => (int)($row['level3_count'] ?? 0),
                ];
            }

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'data' => $stats]);
        } catch (\Throwable $e) {
            error_log('[TeachersController] subjectStats error: ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to load subject statistics']);
        }
    }

    public function saveSubject(): void
    {
        if (!$this->requireAuth()) {
            return;
        }

        $payload = $this->readPayload();
        $name = trim((string)($payload['name'] ?? ''));

        if ($name === '') {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Subject name is required']);
            return;
        }

        try {
            $this->db->insert('subjects', ['name' => $name]);

            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            error_log('[TeachersController] saveSubject error: ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to save subject']);
        }
    }

    public function deleteSubject(): void
    {
        if (!$this->requireAuth()) {
            return;
        }

        $payload = $this->readPayload();
        $subjectId = isset($payload['subject_id']) ? (int)$payload['subject_id'] : 0;

        if ($subjectId <= 0) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid subject id']);
            return;
        }

        try {
            $this->db->delete('subjects', ['subject_id' => $subjectId]);

            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            error_log('[TeachersController] deleteSubject error: ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to delete subject']);
        }
    }

    public function grades(): void
    {
        if (!$this->requireAuth()) {
            return;
        }

        try {
            $rows = $this->db->select('grades', ['grade_id', 'grade'], [
                'ORDER' => ['grade' => 'ASC'],
            ]);

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'data' => $rows]);
        } catch (\Throwable $e) {
            error_log('[TeachersController] grades error: ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to load grades']);
        }
    }

    public function saveGrade(): void
    {
        if (!$this->requireAuth()) {
            return;
        }

        $payload = $this->readPayload();
        $grade = isset($payload['grade']) ? (int)$payload['grade'] : 0;

        if ($grade <= 0) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Grade must be a positive number']);
            return;
        }

        try {
            $this->db->insert('grades', ['grade' => $grade]);
            $gradeId = $this->db->id();

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'data' => ['grade_id' => $gradeId]]);
        } catch (\Throwable $e) {
            error_log('[TeachersController] saveGrade error: ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to save grade']);
        }
    }

    public function deleteGrade(): void
    {
        if (!$this->requireAuth()) {
            return;
        }

        $payload = $this->readPayload();
        $gradeId = isset($payload['grade_id']) ? (int)$payload['grade_id'] : 0;

        if ($gradeId <= 0) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid grade id']);
            return;
        }

        try {
            $this->db->delete('grades', ['grade_id' => $gradeId]);

            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            error_log('[TeachersController] deleteGrade error: ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to delete grade']);
        }
    }

    public function teachers(): void
    {
        if (!$this->requireAuth()) {
            return;
        }

        try {
            $teachers = $this->db->select('teachers', ['teacher_id', 'name', 'is_active'], [
                'ORDER' => ['name' => 'ASC'],
            ]);

            $assignmentRows = $this->db->query(
                'SELECT tsg.teacher_id, s.subject_id, s.name AS subject_name, g.grade_id, g.grade, 
                        st.stream_id, st.name AS stream_name
                 FROM teacher_subject_grades tsg
                 JOIN subjects s ON s.subject_id = tsg.subject_id
                 JOIN grades g ON g.grade_id = tsg.grade_id
                 LEFT JOIN streams st ON st.stream_id = tsg.stream_id
                 ORDER BY s.name, g.grade, st.name'
            );

            $assignments = [];
            if ($assignmentRows) {
                foreach ($assignmentRows->fetchAll() as $row) {
                    $teacherId = (int)$row['teacher_id'];
                    $subjectId = (int)$row['subject_id'];
                    $gradeId = (int)$row['grade_id'];
                    $streamId = isset($row['stream_id']) && $row['stream_id'] ? (int)$row['stream_id'] : null;
                    
                    $assignments[$teacherId][$subjectId]['subject_id'] = $subjectId;
                    $assignments[$teacherId][$subjectId]['subject_name'] = (string)$row['subject_name'];
                    
                    if (!isset($assignments[$teacherId][$subjectId]['grades'][$gradeId])) {
                        $assignments[$teacherId][$subjectId]['grades'][$gradeId] = [
                            'grade_id' => $gradeId,
                            'grade' => (int)$row['grade'],
                            'streams' => [],
                        ];
                    }
                    
                    if ($streamId && !empty($row['stream_name'])) {
                        $assignments[$teacherId][$subjectId]['grades'][$gradeId]['streams'][$streamId] = [
                            'stream_id' => $streamId,
                            'stream_name' => (string)$row['stream_name'],
                        ];
                    }
                }
            }

            $result = [];
            foreach ($teachers as $teacher) {
                $teacherId = (int)$teacher['teacher_id'];
                $subjectGroups = array_values($assignments[$teacherId] ?? []);
                $normalized = [];
                foreach ($subjectGroups as $group) {
                    $grades = [];
                    foreach ($group['grades'] ?? [] as $gradeData) {
                        $grades[] = [
                            'grade_id' => $gradeData['grade_id'],
                            'grade' => $gradeData['grade'],
                            'streams' => array_values($gradeData['streams'] ?? []),
                        ];
                    }
                    $normalized[] = [
                        'subject_id' => $group['subject_id'],
                        'subject_name' => $group['subject_name'],
                        'grades' => $grades,
                    ];
                }

                $result[] = [
                    'teacher_id' => $teacherId,
                    'name' => (string)$teacher['name'],
                    'is_active' => (int)$teacher['is_active'],
                    'assignments' => $normalized,
                ];
            }

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            error_log('[TeachersController] teachers error: ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to load teachers']);
        }
    }

    public function saveTeacher(): void
    {
        if (!$this->requireAuth()) {
            return;
        }

        $payload = $this->readPayload();
        $teacherId = isset($payload['teacher_id']) ? (int)$payload['teacher_id'] : 0;
        $name = trim((string)($payload['name'] ?? ''));
        $isActive = isset($payload['is_active']) ? (int)$payload['is_active'] : 1;
        $assignments = $payload['assignments'] ?? [];

        if ($name === '') {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Teacher name is required']);
            return;
        }

        if (!is_array($assignments)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid assignments data']);
            return;
        }

        $pdo = $this->db->pdo;
        $pdo->beginTransaction();

        try {
            if ($teacherId > 0) {
                $this->db->update('teachers', [
                    'name' => $name,
                    'is_active' => $isActive ? 1 : 0,
                ], ['teacher_id' => $teacherId]);
            } else {
                $this->db->insert('teachers', [
                    'name' => $name,
                    'is_active' => $isActive ? 1 : 0,
                ]);
                $teacherId = (int)$this->db->id();
            }

            $this->db->delete('teacher_subject_grades', ['teacher_id' => $teacherId]);

            // Preload all streams grouped by grade_id for "All" option expansion
            $allStreams = $this->db->select('streams', ['stream_id', 'grade_id']);
            $streamsByGrade = [];
            foreach ($allStreams as $stream) {
                $gid = (int)$stream['grade_id'];
                if (!isset($streamsByGrade[$gid])) {
                    $streamsByGrade[$gid] = [];
                }
                $streamsByGrade[$gid][] = (int)$stream['stream_id'];
            }

            foreach ($assignments as $assignment) {
                $subjectId = isset($assignment['subject_id']) ? (int)$assignment['subject_id'] : 0;
                $grades = $assignment['grades'] ?? [];

                if ($subjectId <= 0 || !is_array($grades)) {
                    continue;
                }

                foreach ($grades as $gradeData) {
                    $gradeId = isset($gradeData['grade_id']) ? (int)$gradeData['grade_id'] : 0;
                    $streamIds = $gradeData['stream_ids'] ?? [];

                    if ($gradeId <= 0) {
                        continue;
                    }

                    if (empty($streamIds)) {
                        // "All" selected - expand to all streams for this grade
                        $streamIds = $streamsByGrade[$gradeId] ?? [];
                    }
                    
                    if (empty($streamIds)) {
                        // No streams exist for this grade - insert without stream as fallback
                        $this->db->insert('teacher_subject_grades', [
                            'teacher_id' => $teacherId,
                            'subject_id' => $subjectId,
                            'grade_id' => $gradeId,
                            'stream_id' => null,
                        ]);
                    } else {
                        // Insert one record per selected stream
                        foreach ($streamIds as $streamId) {
                            $streamId = (int)$streamId;
                            if ($streamId <= 0) {
                                continue;
                            }
                            $this->db->insert('teacher_subject_grades', [
                                'teacher_id' => $teacherId,
                                'subject_id' => $subjectId,
                                'grade_id' => $gradeId,
                                'stream_id' => $streamId,
                            ]);
                        }
                    }
                }
            }

            $pdo->commit();

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'data' => ['teacher_id' => $teacherId]]);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[TeachersController] saveTeacher error: ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to save teacher']);
        }
    }

    public function deleteTeacher(): void
    {
        if (!$this->requireAuth()) {
            return;
        }

        $payload = $this->readPayload();
        $teacherId = isset($payload['teacher_id']) ? (int)$payload['teacher_id'] : 0;

        if ($teacherId <= 0) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid teacher id']);
            return;
        }

        try {
            $this->db->delete('teachers', ['teacher_id' => $teacherId]);

            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            error_log('[TeachersController] deleteTeacher error: ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to delete teacher']);
        }
    }

    // STREAMS MANAGEMENT
    public function streams(): void
    {
        if (!$this->requireAuth()) {
            return;
        }

        try {
            // Join streams with grades to get actual grade number
            $gradeId = isset($_GET['grade_id']) ? (int)$_GET['grade_id'] : 0;
            
            $sql = "SELECT s.stream_id, s.grade_id, s.name, g.grade as grade_number 
                    FROM streams s 
                    LEFT JOIN grades g ON s.grade_id = g.grade_id";
            
            if ($gradeId > 0) {
                $sql .= " WHERE s.grade_id = :grade_id ORDER BY s.name ASC";
                $stmt = $this->db->query($sql, [':grade_id' => $gradeId]);
            } else {
                $sql .= " ORDER BY g.grade ASC, s.name ASC";
                $stmt = $this->db->query($sql);
            }
            
            $rows = $stmt ? $stmt->fetchAll() : [];
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'data' => $rows]);
        } catch (\Throwable $e) {
            error_log('[TeachersController] streams error: ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to load streams']);
        }
    }

    public function saveStream(): void
    {
        if (!$this->requireAuth()) {
            return;
        }
        $payload = $this->readPayload();
        $gradeId = isset($payload['grade_id']) ? (int)$payload['grade_id'] : 0;
        $name = trim((string)($payload['name'] ?? ''));
        if ($gradeId <= 0 || $name === '') {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Grade and stream name required']);
            return;
        }
        try {
            $this->db->insert('streams', [
                'grade_id' => $gradeId,
                'name' => $name,
            ]);
            $streamId = $this->db->id();
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'data' => ['stream_id' => $streamId]]);
        } catch (\Throwable $e) {
            error_log('[TeachersController] saveStream error: ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to save stream']);
        }
    }

    public function deleteStream(): void
    {
        if (!$this->requireAuth()) {
            return;
        }
        $payload = $this->readPayload();
        $streamId = isset($payload['stream_id']) ? (int)$payload['stream_id'] : 0;
        if ($streamId <= 0) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid stream id']);
            return;
        }
        try {
            $this->db->delete('streams', ['stream_id' => $streamId]);
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            error_log('[TeachersController] deleteStream error: ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to delete stream']);
        }
    }
}
