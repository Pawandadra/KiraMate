<?php
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../src/utils/Security.php";
require_once __DIR__ . "/../../src/utils/Logger.php";

// Initialize security
Security::init();
Security::requireLogin();

// Log the start of the deletion process
Logger::info("Delete process started");

// Get rent ID from URL
$rent_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$rent_id) {
    $_SESSION['error'] = "Invalid rent record ID";
    header("Location: index.php");
    exit;
}

try {
    $db = Database::getInstance();
    
    // Check if rent record exists
    $check_stmt = $db->query(
        "SELECT id, shop_id, rent_year, rent_month FROM rents WHERE id = ?",
        [$rent_id]
    );
    $rent = $check_stmt->fetch();
    if (!$rent) {
        $_SESSION['error'] = "Rent record not found";
        header("Location: index.php");
        exit;
    }

    // Check if rent is linked to any payment
    $paymentCheck = $db->query(
        "SELECT COUNT(*) as cnt FROM payments WHERE shop_id = ? AND rent_year = ? AND rent_month = ?",
        [$rent['shop_id'], $rent['rent_year'], $rent['rent_month']]
    )->fetch();
    if ($paymentCheck && $paymentCheck['cnt'] > 0) {
        $_SESSION['error'] = "Cannot delete rent record: This rent is linked to one or more payments.";
        header("Location: index.php");
        exit;
    }

    // Delete the rent record
    $result = $db->query(
        "DELETE FROM rents WHERE id = ?",
        [$rent_id]
    );

    if ($result) {
        Logger::info("Rent record deleted: " . $rent_id);
        $_SESSION['success'] = "Rent record deleted successfully";
    } else {
        throw new Exception("Failed to delete rent record");
    }
} catch (Exception $e) {
    Logger::error("Error deleting rent record: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred. Please try again or contact support.";
}

// Log the end of the deletion process
Logger::info("Delete process completed");

// Redirect back to index page
header("Location: index.php");
exit; 