<?php
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../src/utils/Security.php";
require_once __DIR__ . "/../../src/utils/Logger.php";

// Initialize security
Security::init();
Security::requireLogin();

// Log page access
Logger::info("Rents page accessed");

try {
    $db = Database::getInstance();
    $stmt = $db->query(
        "SELECT r.*, s.shop_no, t.name as tenant_name 
         FROM rents r 
         JOIN shops s ON r.shop_id = s.id 
         LEFT JOIN tenants t ON s.tenant_id = t.id 
         ORDER BY r.rent_year DESC, r.rent_month DESC, s.shop_no"
    );
    $rents = $stmt->fetchAll();
} catch (Exception $e) {
    Logger::error("Error fetching rents: " . $e->getMessage());
    $error = "Error loading rents. Please try again.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rents - Shop Rent Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?php echo BASE_PATH; ?>/assets/css/common.css" rel="stylesheet">
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Rent Records</h2>
            <a href="create.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Add New Rent
            </a>
        </div>

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

        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Shop No</th>
                                    <th>Tenant</th>
                                    <th>Rent Month</th>
                                    <th>Rent Amount</th>
                                    <th>Penalty</th>
                                    <th>Waved Off</th>
                                    <th>Final Amount</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($rents)): ?>
                                    <?php foreach ($rents as $rent): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($rent['shop_no']); ?></td>
                                            <td><?php echo htmlspecialchars($rent['tenant_name'] ?? 'N/A'); ?></td>
                                            <td>
                                                <?php 
                                                $date = DateTime::createFromFormat('Y-m', $rent['rent_year'] . '-' . $rent['rent_month']);
                                                echo $date ? $date->format('F Y') : 'Invalid Date';
                                                ?>
                                            </td>
                                            <td>₹<?php echo number_format($rent['calculated_rent'], 2); ?></td>
                                            <td>₹<?php echo number_format($rent['penalty'], 2); ?></td>
                                            <td>₹<?php echo number_format($rent['amount_waved_off'], 2); ?></td>
                                            <td>₹<?php echo number_format($rent['final_rent'], 2); ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="view.php?id=<?php echo $rent['id']; ?>" class="btn btn-info btn-sm">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="edit.php?id=<?php echo $rent['id']; ?>" class="btn btn-primary btn-sm">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="delete.php?id=<?php echo $rent['id']; ?>" class="btn btn-danger btn-sm" 
                                                       onclick="return confirm('Are you sure you want to delete this rent record?');">
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
        <?php endif; ?>
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
                    emptyTable: "No Rent records found"
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