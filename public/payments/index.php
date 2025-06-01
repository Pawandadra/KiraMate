<?php
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../src/utils/Security.php";
require_once __DIR__ . "/../../src/utils/Logger.php";

Security::init();
Security::requireLogin();
Logger::info("Payments page accessed.");

$error = null;
$success = null;
$payments = [];

try {
    $db = Database::getInstance();
    $stmt = $db->query(
        "SELECT p.*, s.shop_no, t.name as tenant_name, r.rent_year, r.rent_month FROM payments p JOIN shops s ON p.shop_id = s.id LEFT JOIN tenants t ON s.tenant_id = t.id LEFT JOIN rents r ON p.shop_id = r.shop_id AND p.rent_year = r.rent_year AND p.rent_month = r.rent_month ORDER BY p.created_at DESC"
    );
    $payments = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "Error loading payments.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - Shop Rent Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href<?php echo BASE_PATH; ?>assets/css/common.css" rel="stylesheet">
    <style>
    table.dataTable td.dataTables_empty {
        text-align: center;
        font-style: italic;
    }
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
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php 
                echo htmlspecialchars($_SESSION['success']);
                unset($_SESSION['success']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php 
                echo htmlspecialchars($_SESSION['error']);
                unset($_SESSION['error']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Payments</h2>
            <a href="create.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Add Payment Entry
            </a>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Shop No</th>
                                <th>Tenant</th>
                                <th>Rent Month</th>
                                <th>Amount</th>
                                <th>Payment Method</th>
                                <th>Payment Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($payments)): ?>
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($payment['shop_no']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['tenant_name'] ?? '-'); ?></td>
                                        <td>
                                            <?php
                                            if (!empty($payment['ob_financial_year'])) {
                                                echo 'Opening Balance (FY ' . htmlspecialchars($payment['ob_financial_year']) . ')';
                                            } else {
                                                echo date('F Y', strtotime($payment['rent_year'] . '-' . $payment['rent_month'] . '-01'));
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo number_format($payment['amount'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                        <td><?php echo date('d M Y', strtotime($payment['payment_date'])); ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="view.php?id=<?php echo $payment['id']; ?>" class="btn btn-info btn-sm" title="View"><i class="bi bi-eye"></i></a>
                                                <a href="receipt.php?id=<?php echo $payment['id']; ?>" class="btn btn-success btn-sm" title="Generate Receipt" target="_blank"><i class="bi bi-printer"></i></a>
                                                <a href="delete.php?id=<?php echo $payment['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this payment?')" title="Delete"><i class="bi bi-trash"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var table = document.querySelector('table.table');
        if (table) {
            $(table).DataTable({
                paging: false,
                info: false,
                searching: false,
                language: {
                    emptyTable: "No Payment records found"
                }
            });
        }
        // Auto-hide alerts after 5 seconds
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