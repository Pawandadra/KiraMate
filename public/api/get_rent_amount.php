<?php
require_once __DIR__ . '/../../config/database.php';
header('Content-Type: application/json');

$shop_no = $_GET['shop_no'] ?? '';
$rent_month = $_GET['rent_month'] ?? '';
if (!$shop_no || !$rent_month) {
    echo json_encode(['amount' => '']);
    exit;
}
try {
    $db = Database::getInstance();
    // Get shop_id from shop_no
    $stmt = $db->query('SELECT id FROM shops WHERE shop_no = ?', [$shop_no]);
    $shop = $stmt->fetch();
    if (!$shop) {
        echo json_encode(['amount' => '']);
        exit;
    }
    $shop_id = $shop['id'];
    $parts = explode('-', $rent_month);
    if (count($parts) !== 2) {
        echo json_encode(['amount' => '']);
        exit;
    }
    $year = (int)$parts[0];
    $month = (int)$parts[1];
    $stmt = $db->query('SELECT final_rent FROM rents WHERE shop_id = ? AND rent_year = ? AND rent_month = ?', [$shop_id, $year, $month]);
    $rent = $stmt->fetch();
    if (!$rent) {
        echo json_encode(['amount' => '']);
        exit;
    }
    echo json_encode(['amount' => $rent['final_rent']]);
} catch (Exception $e) {
    echo json_encode(['amount' => '']);
} 