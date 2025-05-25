<?php
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../src/utils/Security.php";
require_once __DIR__ . "/../../src/utils/Logger.php";

// Initialize security
Security::init();
Security::requireLogin();

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid shop ID";
    header("Location: index.php");
    exit;
}

$id = (int)$_GET['id'];

try {
    $db = Database::getInstance();
    
    // Check if shop is linked to any rents, payments, or opening balances
    $rentCheck = $db->query("SELECT COUNT(*) as cnt FROM rents WHERE shop_id = ?", [$id])->fetch();
    $paymentCheck = $db->query("SELECT COUNT(*) as cnt FROM payments WHERE shop_id = ?", [$id])->fetch();
    $obCheck = $db->query("SELECT COUNT(*) as cnt FROM opening_balances WHERE shop_id = ?", [$id])->fetch();
    if (($rentCheck && $rentCheck['cnt'] > 0) || ($paymentCheck && $paymentCheck['cnt'] > 0) || ($obCheck && $obCheck['cnt'] > 0)) {
        $_SESSION['error'] = htmlspecialchars("Cannot delete shop: This shop is linked to one or more rent, payment, or opening balance records.");
        header("Location: index.php");
        exit;
    }

    // Start transaction
    $db->beginTransaction();

    // Get shop documents
    $stmt = $db->query("SELECT file_path FROM shop_documents WHERE shop_id = ?", [$id]);
    $documents = $stmt->fetchAll();

    // Delete documents from storage
    $upload_dir = __DIR__ . "/../uploads/shop_documents/";
    foreach ($documents as $doc) {
        $file_path = $upload_dir . $doc['file_path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }

    // Delete document records
    $db->query("DELETE FROM shop_documents WHERE shop_id = ?", [$id]);

    // Delete shop
    $result = $db->query("DELETE FROM shops WHERE id = ?", [$id]);

    if ($result) {
        // Commit transaction
        $db->commit();
        
        Logger::info("Shop deleted: ID " . $id);
        $_SESSION['success'] = htmlspecialchars("Shop deleted successfully");
    } else {
        throw new Exception("Failed to delete shop");
    }
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($db)) {
        $db->rollBack();
    }
    
    Logger::error("Error deleting shop: " . $e->getMessage());
    $_SESSION['error'] = htmlspecialchars("An error occurred. Please try again or contact support.");
}

header("Location: index.php");
exit; 