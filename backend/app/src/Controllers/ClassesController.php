<?php

declare(strict_types=1);

namespace App\Controllers;

use Medoo\Medoo;

class ClassesController
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

    public function list(): void
    {
        if (!$this->requireAuth()) {
            return;
        }

        try {
            $rows = $this->db->select('grades', ['grade_id', 'grade'], [
                'ORDER' => ['grade' => 'ASC'],
            ]);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $rows,
            ]);
        } catch (\Throwable $e) {
            error_log('[ClassesController] list error: ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to load classes']);
        }
    }
}
