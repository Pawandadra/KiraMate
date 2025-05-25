<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/utils/Security.php';
Security::init();
Security::requireLogin();

$error = null;
$balances = [];
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT ob.*, s.shop_no, t.name as tenant_name FROM opening_balances ob JOIN shops s ON ob.shop_id = s.id LEFT JOIN tenants t ON ob.tenant_id = t.id ORDER BY ob.financial_year DESC, s.shop_no ASC");
    $balances = $stmt->fetchAll();
} catch (Exception $e) {
    $error = 'Error loading opening balances.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Opening Balances - Shop Rent Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
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
            echo $_SESSION['success'];
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
        <h2 class="mb-0">Opening Balances</h2>
        <a href="create.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Add Opening Balance
        </a>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>Shop No</th>
                                    <th>Tenant</th>
                                    <th>Opening Balance</th>
                                    <th>Financial Year</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($balances)): ?>
                                    <?php foreach ($balances as $ob): ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo htmlspecialchars($ob['shop_no']); ?></td>
                                            <td><?php echo htmlspecialchars($ob['tenant_name'] ?? '-'); ?></td>
                                            <td><?php echo number_format($ob['opening_balance'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($ob['financial_year']); ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="view.php?id=<?php echo $ob['id']; ?>" class="btn btn-info btn-sm" title="View Details">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="delete.php?id=<?php echo $ob['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this opening balance?')" title="Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
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
                emptyTable: "No Opening Balance records found"
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