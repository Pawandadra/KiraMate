<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/utils/Security.php';
require_once __DIR__ . '/../../src/utils/Logger.php';
Security::init();
Security::requireLogin();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Invalid opening balance ID';
    header('Location: index.php');
    exit;
}

$id = (int)$_GET['id'];
$error = null;

try {
    $db = Database::getInstance();
    $stmt = $db->query('SELECT * FROM opening_balances WHERE id = ?', [$id]);
    $ob = $stmt->fetch();
    if (!$ob) {
        $_SESSION['error'] = 'Opening balance record not found';
        header('Location: index.php');
        exit;
    }
    // Check if opening balance is linked to any payment
    $paymentCheck = $db->query(
        'SELECT COUNT(*) as cnt FROM payments WHERE shop_id = ? AND ob_financial_year = ?',
        [$ob['shop_id'], $ob['financial_year']]
    )->fetch();
    if ($paymentCheck && $paymentCheck['cnt'] > 0) {
        $_SESSION['error'] = 'Cannot delete opening balance: This opening balance is linked to one or more payments.';
        header('Location: index.php');
        exit;
    }
    $db->query('DELETE FROM opening_balances WHERE id = ?', [$id]);
    $_SESSION['success'] = 'Opening balance deleted successfully';
    header('Location: index.php');
    exit;
} catch (Exception $e) {
    Logger::error('Error deleting opening balance: ' . $e->getMessage());
    $_SESSION['error'] = 'An error occurred. Please try again or contact support.';
    header('Location: index.php');
    exit;
} 