<?php
require_once __DIR__ . '/../../config/database.php';
header('Content-Type: application/json');

$shop_no = $_GET['shop_no'] ?? '';
if (!$shop_no) {
    echo json_encode(['months' => []]);
    exit;
}
try {
    $db = Database::getInstance();
    // Get shop_id from shop_no
    $stmt = $db->query('SELECT id FROM shops WHERE shop_no = ?', [$shop_no]);
    $shop = $stmt->fetch();
    if (!$shop) {
        echo json_encode(['months' => []]);
        exit;
    }
    $shop_id = $shop['id'];
    // Get pending rents (in rents but not in payments)
    $stmt = $db->query('SELECT rent_year, rent_month FROM rents WHERE shop_id = ? AND NOT EXISTS (SELECT 1 FROM payments WHERE payments.shop_id = rents.shop_id AND payments.rent_year = rents.rent_year AND payments.rent_month = rents.rent_month)', [$shop_id]);
    $months = [];
    while ($row = $stmt->fetch()) {
        $value = sprintf('%04d-%02d', $row['rent_year'], $row['rent_month']);
        $label = date('F Y', strtotime($value . '-01'));
        $months[] = ['value' => $value, 'label' => $label];
    }
    echo json_encode(['months' => $months]);
} catch (Exception $e) {
    echo json_encode(['months' => []]);
} 