<?php
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../src/utils/Security.php";

// Initialize security
Security::init();
Security::requireLogin();

// Set content type to JSON
header('Content-Type: application/json');

// Get field and value from request
$field = isset($_GET['field']) ? Security::sanitizeInput($_GET['field']) : '';
$value = isset($_GET['value']) ? Security::sanitizeInput($_GET['value']) : '';
$exclude_id = isset($_GET['exclude_id']) ? (int)$_GET['exclude_id'] : 0;

// Validate field name
$allowed_fields = ['tenant_id', 'aadhaar_number', 'mobile', 'pancard_number', 'email'];
if (!in_array($field, $allowed_fields)) {
    echo json_encode(['error' => 'Invalid field name']);
    exit;
}

try {
    $db = Database::getInstance();
    
    // Check if value exists, excluding current tenant if editing
    $query = "SELECT COUNT(*) as count FROM tenants WHERE $field = ?";
    $params = [$value];
    if ($exclude_id) {
        $query .= " AND id != ?";
        $params[] = $exclude_id;
    }
    $result = $db->query($query, $params)->fetch();
    
    echo json_encode([
        'unique' => $result['count'] === 0,
        'field' => $field,
        'value' => $value
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error']);
} 