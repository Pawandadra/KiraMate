<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../src/utils/Security.php";
require_once __DIR__ . "/../src/utils/Logger.php";

// Get success message if any
$success_message = $_GET['msg'] ?? null;

// Log the logout
if (isset($_SESSION['user_id'])) {
    Logger::info("User {$_SESSION['user_id']} logged out.");
}

// Clear all session data
session_unset();
session_destroy();

// Redirect to login page with success message if any
$redirect_url = BASE_PATH . "/login.php";
if ($success_message) {
    $redirect_url .= "?success=" . urlencode($success_message);
}
header("Location: " . $redirect_url);
exit; 