<?php
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../src/utils/Security.php";
require_once __DIR__ . "/../../src/utils/Logger.php";

// Initialize security
Security::init();
Security::requireLogin();

// Log page access
Logger::info("Accessing shops index page");

try {
    $db = Database::getInstance();
    
    // Fetch all shops with tenant information and document count
    $stmt = $db->query("
        SELECT s.*, 
               t.name as tenant_name, 
               t.mobile as tenant_mobile, 
               t.email as tenant_email,
               (SELECT COUNT(*) FROM shop_documents WHERE shop_id = s.id) as document_count
        FROM shops s
        LEFT JOIN tenants t ON s.tenant_id = t.id
        ORDER BY s.shop_no
    ");
    $shops = $stmt->fetchAll();
} catch (Exception $e) {
    Logger::error("Error fetching shops: " . $e->getMessage());
    $error = "Error loading shops. Please try again.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shops - Shop Rent Management System</title>
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
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Shops</h2>
            <a href="create.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Add New Shop
            </a>
        </div>
        <div class="card">
            <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Shop No</th>
                                    <th>Location</th>
                                    <th>Tenant</th>
                                    <th>Contact</th>
                                    <th>Agreement Period</th>
                                    <th>Documents</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($shops)): ?>
                                    <?php foreach ($shops as $shop): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($shop['shop_no']); ?></td>
                                            <td><?php echo htmlspecialchars($shop['location']); ?></td>
                                            <td>
                                                <?php if ($shop['tenant_name']): ?>
                                                    <?php echo htmlspecialchars($shop['tenant_name']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">No tenant assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($shop['tenant_mobile']): ?>
                                                    <?php echo htmlspecialchars($shop['tenant_mobile']); ?>
                                                    <?php if ($shop['tenant_email']): ?>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?php echo htmlspecialchars($shop['tenant_email']); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $start = new DateTime($shop['agreement_start_date']);
                                                $end = new DateTime($shop['agreement_end_date']);
                                                $duration = $start->diff($end);
                                                echo $start->format('M d, Y') . ' to ' . $end->format('M d, Y');
                                                echo '<br><small class="text-muted">';
                                                echo $duration->format('%y years, %m months');
                                                echo '</small>';
                                                ?>
                                            </td>
                                            <td>
                                                <?php if ($shop['document_count'] > 0): ?>
                                                    <span class="badge bg-info">
                                                        <?php echo $shop['document_count']; ?> document(s)
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">No documents</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="view.php?id=<?php echo $shop['id']; ?>" 
                                                    class="btn btn-info btn-sm" title="View">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="edit.php?id=<?php echo $shop['id']; ?>" 
                                                    class="btn btn-primary btn-sm" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="delete.php?id=<?php echo $shop['id']; ?>" 
                                                    class="btn btn-danger btn-sm" 
                                                    onclick="return confirm('Are you sure you want to delete this shop?')" 
                                                    title="Delete">
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
                    emptyTable: "No Shop records found"
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