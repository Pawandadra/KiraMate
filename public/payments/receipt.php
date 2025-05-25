<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . "/../../src/utils/Security.php";

Security::init();
Security::requireLogin();
$error = null;
$payment = null;

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('Invalid payment ID.');
}

try {
    $db = Database::getInstance();
    $stmt = $db->query(
        "SELECT p.*, s.shop_no, t.name as tenant_name, r.rent_year, r.rent_month FROM payments p JOIN shops s ON p.shop_id = s.id LEFT JOIN tenants t ON s.tenant_id = t.id LEFT JOIN rents r ON p.shop_id = r.shop_id AND p.rent_year = r.rent_year AND p.rent_month = r.rent_month WHERE p.id = ?",
        [$_GET['id']]
    );
    $payment = $stmt->fetch();
    if (!$payment) {
        $error = 'Payment record not found.';
    }
    $shop_no = $payment['shop_no'] ?? '';
    $tenant_name = $payment['tenant_name'] ?? '';
    $rent_amount = $payment['amount'];
    $payment_date = $payment['payment_date'];
    $payment_method = $payment['payment_method'];
    $rent_month = DateTime::createFromFormat('Y-m', $payment['rent_year'] . '-' . $payment['rent_month']);
    $rent_of_display = $rent_month ? $rent_month->format('M-Y') : '';
} catch (Exception $e) {
    $error = 'Error loading payment details.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Receipt</title>
    <link rel="stylesheet" href="/assets/css/receipt.css">
</head>
<body>
    <div class="a4-container">
        <?php include __DIR__ . '/../letterhead.php'; ?>
        <div class="receipt-title">RENT PAYMENT RECEIPT</div>
        <div class="receipt-date">Generated on: <?php echo date('d-m-Y'); ?></div>
        <table class="receipt-table">
            <tr>
                <td class="bold">Shop no :</td>
                <td><?php echo htmlspecialchars($shop_no); ?></td>
                <td class="bold">Tenant name :</td>
                <td><?php echo htmlspecialchars($tenant_name); ?></td>
            </tr>
            <tr>
                <td class="bold">Rent of :</td>
                <td>
                    <?php
                    if (!empty($payment['ob_financial_year'])) {
                        echo 'Opening Balance (FY ' . htmlspecialchars($payment['ob_financial_year']) . ')';
                    } else {
                        echo date('M-Y', strtotime($payment['rent_year'] . '-' . $payment['rent_month'] . '-01'));
                    }
                    ?>
                </td>
                <td class="bold">Payment date :</td>
                <td><?php echo date('d-m-Y', strtotime($payment['payment_date'])); ?></td>
            </tr>
            <tr>
                <td class="bold">Payment method :</td>
                <td><?php echo htmlspecialchars($payment_method); ?></td>
                <td class="bold">Amount :</td>
                <td><?php echo number_format($rent_amount); ?></td>
            </tr>
        </table>
        <div class="footer">
            <div class="signature-section">
                <div class="signature-text">Authorized Sign</div>
            </div>
            <div class="center">------------------------------------------------ Thanks for your payment ------------------------------------------------</div>
        </div>
        <div class="no-print" style="text-align:center; margin: 30px 0;">
            <button onclick="window.print()" style="padding: 10px 28px; font-size: 1em; background: #4a5568; color: white; border: none; border-radius: 4px; cursor: pointer; transition: background 0.2s ease;">Print Receipt</button>
        </div>
    </div>
</body>
</html>

<style>
    .signature-section {
        margin-bottom: 20px;
        text-align: right;
        padding-right: 50px;
    }
    
    .signature-text {
        font-size: 14px;
    }
    
    @media print {
        
        .signature-section {
            margin-bottom: 20px;
        }
    }
</style> 