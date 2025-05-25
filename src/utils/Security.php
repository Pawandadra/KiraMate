<?php
require_once __DIR__ . '/../../config/config.php';

class Security {
    public static function init() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    public static function generateCSRFToken() {
        return $_SESSION['csrf_token'];
    }

    public static function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    }

    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    public static function isAuthenticated() {
        return isset($_SESSION['user_id']) && isset($_SESSION['last_activity']);
    }

    public static function checkSessionTimeout($timeout = 1800) { // 30 minutes
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
            self::logout();
            return false;
        }
        $_SESSION['last_activity'] = time();
        return true;
    }

    public static function requireLogin() {
        if (!self::isAuthenticated() || !self::checkSessionTimeout()) {
            header('Location: /login.php');
            exit;
        }
    }

    public static function requireRole($role) {
        self::requireLogin();
        if (!isset($_SESSION['user_data']['role']) || $_SESSION['user_data']['role'] !== $role) {
            header('Location: /index.php');
            exit;
        }
    }

    public static function login($userId, $userData) {
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_data'] = $userData;
        $_SESSION['last_activity'] = time();
    }

    public static function logout() {
        session_destroy();
    }

    public static function getCurrentUserId() {
        return $_SESSION['user_id'] ?? null;
    }

    public static function getCurrentUser() {
        return $_SESSION['user_data'] ?? null;
    }

    public static function isAdmin() {
        $user = self::getCurrentUser();
        return $user && isset($user['role']) && $user['role'] === 'admin';
    }
}

// Initialize security
Security::init(); 