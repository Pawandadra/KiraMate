<?php
require_once __DIR__ . "/../../../config/database.php";
require_once __DIR__ . "/../../../src/utils/Security.php";
require_once __DIR__ . "/../../../src/utils/Logger.php";

// Initialize security
Security::init();
Security::requireLogin();

// Check if user is admin
if (!Security::isAdmin()) {
    $_SESSION['error'] = "Access denied. Admin privileges required.";
    header("Location: /index.php");
    exit;
}

// Get user ID from URL
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$user_id) {
    $_SESSION['error'] = "Invalid user ID";
    header("Location: index.php");
    exit;
}

// Prevent self-deletion
if ($user_id === $_SESSION['user_id']) {
    $_SESSION['error'] = "You cannot delete your own account";
    header("Location: index.php");
    exit;
}

try {
    $db = Database::getInstance();
    
    // Get user details for logging
    $stmt = $db->query("SELECT username FROM users WHERE id = ?", [$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $_SESSION['error'] = "User not found";
        header("Location: index.php");
        exit;
    }
    
    // Delete the user
    $result = $db->query("DELETE FROM users WHERE id = ?", [$user_id]);
    
    if ($result) {
        Logger::info("User deleted: {$user['username']}");
        $_SESSION['success'] = "User deleted successfully";
    } else {
        throw new Exception("Failed to delete user");
    }
} catch (Exception $e) {
    Logger::error("Error deleting user: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while deleting the user";
}

header("Location: index.php");
exit; 