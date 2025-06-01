<?php
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../src/utils/Security.php";

Security::init();
Security::requireLogin();

$rent_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$rent_id) {
    die("Invalid rent record ID");
}

try {
    $db = Database::getInstance();
    $stmt = $db->query(
        "SELECT r.*, s.shop_no, t.name as tenant_name, t.email as tenant_email
         FROM rents r
         JOIN shops s ON r.shop_id = s.id
         LEFT JOIN tenants t ON s.tenant_id = t.id
         WHERE r.id = ?",
        [$rent_id]
    );
    $rent = $stmt->fetch();
    if (!$rent) {
        die("Rent record not found");
    }
    $rent_amount = $rent['final_rent'];
    $penalty = $rent['penalty'];
    $waved_off = $rent['amount_waved_off'];
    $tenant_name = $rent['tenant_name'] ?? '';
    $shop_no = $rent['shop_no'] ?? '';
    $date_today = date('d-m-Y');
    $rent_month = DateTime::createFromFormat('Y-m', $rent['rent_year'] . '-' . $rent['rent_month']);
    $rent_of_display = $rent_month ? $rent_month->format('M-Y') : '';
} catch (Exception $e) {
    die("Error generating receipt: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Rent Receipt</title>
    <link href="<?php echo BASE_PATH; ?>/assets/css/receipt.css" rel="stylesheet">
</head>
<body>
    <div class="a4-container">
        <?php include __DIR__ . '/../letterhead.php'; ?>
        <div class="receipt-title">RENT RECEIPT</div>
        <div class="receipt-date">Generated on: <?php echo $date_today; ?></div>
        <table class="receipt-table">
            <tr>
                <td class="bold">Shop no :</td>
                <td><?php echo htmlspecialchars($shop_no); ?></td>
                <td class="bold">Tenant name :</td>
                <td><?php echo htmlspecialchars($tenant_name); ?></td>
            </tr>
            <tr>
                <td class="bold">Rent of :</td>
                <td><?php echo htmlspecialchars($rent_of_display); ?></td>
                <td class="bold">Penalty :</td>
                <td><?php echo number_format($penalty, 2); ?></td>
            </tr>
            <tr>
                <td class="bold">Waved off :</td>
                <td><?php echo number_format($waved_off, 2); ?></td>
                <td class="bold">Rent amount :</td>
                <td><?php echo number_format($rent_amount, 2); ?></td>
            </tr>
        </table>
        <div class="footer">
            <div class="center">Note: This is not a bill. This is only a summary receipt.</div>
            <div class="center">------------------------------------------------------Thanks------------------------------------------------------</div>
        </div>
        <div class="no-print" style="text-align:center; margin: 30px 0;">
            <button onclick="window.print()" style="padding: 10px 28px; font-size: 1em; background: #4a5568; color: white; border: none; border-radius: 4px; cursor: pointer; transition: background 0.2s ease;">Print Receipt</button>
        </div>
    </div>
</body>
</html> 