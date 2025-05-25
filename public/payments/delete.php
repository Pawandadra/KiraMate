<?php
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../src/utils/Security.php";
require_once __DIR__ . "/../../src/utils/Logger.php";

Security::init();
Security::requireLogin();

$payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$payment_id) {
    $_SESSION['error'] = "Invalid payment record ID";
    header("Location: index.php");
    exit;
}

try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT id FROM payments WHERE id = ?", [$payment_id]);
    if ($stmt->rowCount() === 0) {
        $_SESSION['error'] = "Payment record not found";
        header("Location: index.php");
        exit;
    }
    $db->query("DELETE FROM payments WHERE id = ?", [$payment_id]);
    $_SESSION['success'] = "Payment record deleted successfully";
} catch (Exception $e) {
    Logger::error("Error deleting payment record: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred. Please try again or contact support.";
}
header("Location: index.php");
exit; 