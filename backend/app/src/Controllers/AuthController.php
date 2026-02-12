<?php

declare(strict_types=1);

namespace App\Controllers;

use Medoo\Medoo;

class AuthController
{
    private Medoo $db;

    public function __construct(Medoo $db)
    {
        $this->db = $db;
    }

    public function login(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            return;
        }

        $this->startSession();

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $username = isset($_POST['username']) ? trim((string)$_POST['username']) : '';
        $password = isset($_POST['password']) ? (string)$_POST['password'] : '';

        if ($username === '' || $password === '') {
            $this->clearSession();
            error_log('[AUTH] Empty username or password');
            header('Location: ' . $this->basePath() . '/Pages/login.html?error=1');
            return;
        }

        $admin = $this->db->get('admin_users', ['id', 'username', 'password_hash', 'is_active'], ['username' => $username]);

        $hasAdmin = (bool)$admin;
        $validPassword = $hasAdmin && !empty($admin['is_active'])
            && isset($admin['password_hash'])
            && password_verify($password, $admin['password_hash']);
        if (!$validPassword) {
            $this->clearSession();
            error_log('[AUTH] Invalid credentials for username=' . $username . ' adminFound=' . ($hasAdmin ? '1' : '0'));
            header('Location: ' . $this->basePath() . '/Pages/login.html?error=1');
            return;
        }

        $this->db->update('admin_users', [
            'last_login_at' => date('Y-m-d H:i:s'),
        ], ['id' => $admin['id']]);

        session_regenerate_id(true);
        $_SESSION['loggedin'] = true;
        $_SESSION['id'] = $admin['id'];
        $_SESSION['username'] = $admin['username'];
        error_log('[AUTH] Login success username=' . $admin['username']);

        header('Location: ' . $this->basePath() . '/Pages/settings/timetable.html');
    }

    public function check(): void
    {
        $this->startSession();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        header('Content-Type: application/json');
        echo json_encode([
            'authenticated' => isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true,
            'username' => $_SESSION['username'] ?? null,
        ]);
    }

    public function logout(): void
    {
        $this->startSession();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $this->clearSession();
        header('Location: ' . $this->basePath() . '/Pages/login.html');
    }

    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_set_cookie_params([
            'path' => $this->basePath() ?: '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function basePath(): string
    {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $marker = '/backend/public/index.php';
        if ($scriptName !== '' && strlen($scriptName) >= strlen($marker)) {
            if (substr($scriptName, -strlen($marker)) === $marker) {
                $base = substr($scriptName, 0, -strlen($marker));
                return $base !== '' ? $base : '';
            }
        }

        $dir = dirname($scriptName);
        return $dir === '/' ? '' : $dir;
    }

    private function clearSession(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }

}
