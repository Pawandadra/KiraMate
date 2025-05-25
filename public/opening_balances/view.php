<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/utils/Security.php';
Security::init();
Security::requireLogin();

$error = null;
$ob = null;

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT ob.*, s.shop_no, t.name as tenant_name, t.email as tenant_email FROM opening_balances ob JOIN shops s ON ob.shop_id = s.id LEFT JOIN tenants t ON ob.tenant_id = t.id WHERE ob.id = ?", [$_GET['id']]);
    $ob = $stmt->fetch();
    if (!$ob) {
        header('Location: index.php');
        exit;
    }
} catch (Exception $e) {
    $error = 'Error loading opening balance details.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Opening Balance - Shop Rent Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
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
                    <h2 class="card-title">Opening Balance Details</h2>
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
                                <td><?php echo htmlspecialchars($ob['shop_no']); ?></td>
                            </tr>
                            <tr>
                                <th>Tenant Name</th>
                                <td><?php echo htmlspecialchars($ob['tenant_name'] ?? '-'); ?></td>
                            </tr>
                            <tr>
                                <th>Tenant Email</th>
                                <td><?php echo htmlspecialchars($ob['tenant_email'] ?? '-'); ?></td>
                            </tr>
                            <tr>
                                <th>Opening Balance</th>
                                <td><?php echo number_format($ob['opening_balance'], 2); ?></td>
                            </tr>
                            <tr>
                                <th>Financial Year</th>
                                <td><?php echo htmlspecialchars($ob['financial_year']); ?></td>
                            </tr>
                        </table>
                        <div class="d-flex justify-content-between">
                            <a href="index.php" class="btn btn-secondary bi bi-arrow-left"> Back to List</a>
                            <a href="delete.php?id=<?php echo $ob['id']; ?>" class="btn btn-danger bi bi-trash" onclick="return confirm('Are you sure you want to delete this opening balance?')"> Delete</a>
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