<?php
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../src/utils/Security.php";

Security::init();
Security::requireLogin();

$error = null;
$payment = null;

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

try {
    $db = Database::getInstance();
    $stmt = $db->query(
        "SELECT p.*, s.shop_no, t.name as tenant_name, r.rent_year, r.rent_month FROM payments p JOIN shops s ON p.shop_id = s.id LEFT JOIN tenants t ON s.tenant_id = t.id LEFT JOIN rents r ON p.shop_id = r.shop_id AND p.rent_year = r.rent_year AND p.rent_month = r.rent_month WHERE p.id = ?",
        [$_GET['id']]
    );
    $payment = $stmt->fetch();
    if (!$payment) {
        header("Location: index.php");
        exit;
    }
} catch (Exception $e) {
    $error = "Error loading payment details.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Payment - Shop Rent Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/common.css" rel="stylesheet">
    <style>
    .table, .table th, .table td {
        font-size: 1rem;
    }
    .btn, .btn-sm {
        font-size: 0.95rem;
    }
    .bi {
        font-size: 1.1em;
        vertical-align: -0.125em;
    }
    </style>
</head>
<body>
<?php include __DIR__ . '/../navbar.php'; ?>
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Payment Details</h2>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php else: ?>
                            <table class="table table-bordered">
                                <tr>
                                    <th>Shop No</th>
                                    <td><?php echo htmlspecialchars($payment['shop_no']); ?></td>
                                </tr>
                                <tr>
                                    <th>Tenant</th>
                                    <td><?php echo htmlspecialchars($payment['tenant_name'] ?? '-'); ?></td>
                                </tr>
                                <tr>
                                    <th>Rent Month</th>
                                    <td>
                                        <?php
                                        if (!empty($payment['ob_financial_year'])) {
                                            echo 'Opening Balance (FY ' . htmlspecialchars($payment['ob_financial_year']) . ')';
                                        } else {
                                            echo date('F Y', strtotime($payment['rent_year'] . '-' . $payment['rent_month'] . '-01'));
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Amount</th>
                                    <td><?php echo number_format($payment['amount'], 2); ?></td>
                                </tr>
                                <tr>
                                    <th>Payment Method</th>
                                    <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                </tr>
                                <tr>
                                    <th>Payment Date</th>
                                    <td><?php echo date('d M Y', strtotime($payment['payment_date'])); ?></td>
                                </tr>
                                <tr>
                                    <th>Remark</th>
                                    <td><?php echo htmlspecialchars($payment['notes'] ?? '-'); ?></td>
                                </tr>
                            </table>
                            <div class="d-flex justify-content-between">
                                <a href="index.php" class="btn btn-secondary bi bi-arrow-left"> Back to List</a>
                                <div>
                                    <a href="receipt.php?id=<?php echo $payment['id']; ?>" class="btn btn-success bi bi-printer" target="_blank"> Receipt</a>
                                    <a href="delete.php?id=<?php echo $payment['id']; ?>" class="btn btn-danger bi bi-trash" onclick="return confirm('Are you sure you want to delete this payment?')"> Delete</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                document.querySelectorAll('.alert-success, .alert-danger').forEach(function(alert) {
                    alert.classList.add('fade');
                    setTimeout(function() { alert.style.display = 'none'; }, 500);
                });
            }, 5000);
        });
    </script>
</body>
</html> 