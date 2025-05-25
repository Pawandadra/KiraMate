<?php
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../src/utils/Security.php";

// Initialize security
Security::init();
Security::requireLogin();

header('Content-Type: application/json');

$shop_no = Security::sanitizeInput($_GET['shop_no'] ?? '');
$exclude_id = isset($_GET['exclude_id']) ? (int)$_GET['exclude_id'] : 0;

if (empty($shop_no)) {
    echo json_encode(['exists' => false]);
    exit;
}

try {
    $db = Database::getInstance();
    $query = "SELECT COUNT(*) as count FROM shops WHERE shop_no = ?";
    $params = [$shop_no];
    if ($exclude_id) {
        $query .= " AND id != ?";
        $params[] = $exclude_id;
    }
    $stmt = $db->query($query, $params);
    $result = $stmt->fetch();
    
    echo json_encode(['exists' => $result['count'] > 0]);
} catch (Exception $e) {
    echo json_encode(['exists' => false]);
} 