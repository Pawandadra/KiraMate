<?php
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../src/utils/Security.php";
require_once __DIR__ . "/../../src/utils/Logger.php";

// Initialize security
Security::init();
Security::requireLogin();

// Get document and shop IDs
$document_id = isset($_POST['id']) ? (int)$_POST['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
$shop_id = isset($_POST['shop_id']) ? (int)$_POST['shop_id'] : (isset($_GET['shop_id']) ? (int)$_GET['shop_id'] : 0);

if (!$document_id || !$shop_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid document or shop ID']);
    exit;
}

try {
    $db = Database::getInstance();
    
    // Start transaction
    $db->beginTransaction();

    // Get document details
    $stmt = $db->query("SELECT * FROM shop_documents WHERE id = ? AND shop_id = ?", [$document_id, $shop_id]);
    $document = $stmt->fetch();

    if (!$document) {
        throw new Exception("Document not found");
    }

    // Delete file from storage
    $file_path = __DIR__ . "/../uploads/shop_documents/" . $document['file_path'];
    if (file_exists($file_path)) {
        unlink($file_path);
    }

    // Delete document record
    $result = $db->query("DELETE FROM shop_documents WHERE id = ? AND shop_id = ?", [$document_id, $shop_id]);

    if ($result) {
        // Commit transaction
        $db->commit();
        
        Logger::info("Shop document deleted: ID " . $document_id);
        echo json_encode(['success' => true]);
    } else {
        throw new Exception("Failed to delete document");
    }
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($db)) {
        $db->rollBack();
    }
    
    Logger::error("Error deleting shop document: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 