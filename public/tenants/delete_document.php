<?php
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../src/utils/Security.php";
require_once __DIR__ . "/../../src/utils/Logger.php";

// Initialize security
Security::init();
Security::requireLogin();

// Set content type to JSON
header('Content-Type: application/json');

// Get document ID and tenant ID from request
$document_id = isset($_POST['id']) ? (int)$_POST['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
$tenant_id = isset($_POST['tenant_id']) ? (int)$_POST['tenant_id'] : (isset($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : 0);

if (!$document_id || !$tenant_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid document or tenant ID']);
    exit;
}

try {
    $db = Database::getInstance();
    
    // Start transaction
    $db->beginTransaction();

    // Get document information
    $stmt = $db->query(
        "SELECT file_path FROM tenant_documents WHERE id = ? AND tenant_id = ?",
        [$document_id, $tenant_id]
    );
    $document = $stmt->fetch();

    if (!$document) {
        throw new Exception("Document not found");
    }

    // Delete file from storage
    $file_path = __DIR__ . "/../uploads/tenant_documents/" . $document['file_path'];
    if (file_exists($file_path)) {
        unlink($file_path);
    }

    // Delete document record from database
    $db->query(
        "DELETE FROM tenant_documents WHERE id = ? AND tenant_id = ?",
        [$document_id, $tenant_id]
    );

    // Commit transaction
    $db->commit();
    
    Logger::info("Document deleted: ID " . $document_id);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($db)) {
        $db->rollBack();
    }
    
    Logger::error("Error deleting document: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 