<?php
require_once __DIR__ . '/../../config/database.php';
header('Content-Type: application/json');

$shop_no = $_GET['shop_no'] ?? '';
if (!$shop_no) {
    echo json_encode(['opening_balances' => []]);
    exit;
}
try {
    $db = Database::getInstance();
    $stmt = $db->query('SELECT id FROM shops WHERE shop_no = ?', [$shop_no]);
    $shop = $stmt->fetch();
    if (!$shop) {
        echo json_encode(['opening_balances' => []]);
        exit;
    }
    $shop_id = $shop['id'];
    // Get all unpaid opening balances for this shop
    $stmt = $db->query('SELECT opening_balance, financial_year FROM opening_balances ob WHERE ob.shop_id = ? AND NOT EXISTS (SELECT 1 FROM payments p WHERE p.shop_id = ob.shop_id AND p.ob_financial_year = ob.financial_year)', [$shop_id]);
    $balances = [];
    while ($row = $stmt->fetch()) {
        $balances[] = [
            'opening_balance' => $row['opening_balance'],
            'financial_year' => $row['financial_year']
        ];
    }
    echo json_encode(['opening_balances' => $balances]);
} catch (Exception $e) {
    echo json_encode(['opening_balances' => []]);
} 