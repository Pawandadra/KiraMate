<?php
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../src/utils/Security.php";
require_once __DIR__ . "/../../src/utils/Logger.php";

// Initialize security
Security::init();
Security::requireLogin();

// Log page access
Logger::info("Tenants page accessed: " . $_SERVER['REQUEST_URI']);

// Get all tenants
try {
    $db = Database::getInstance();
    $stmt = $db->query("
        SELECT t.*, 
               COUNT(td.id) as document_count,
               GROUP_CONCAT(td.file_name) as document_names
        FROM tenants t
        LEFT JOIN tenant_documents td ON t.id = td.tenant_id
        GROUP BY t.id
        ORDER BY t.name
    ");
    $tenants = $stmt->fetchAll();
} catch (Exception $e) {
    Logger::error("Error fetching tenants: " . $e->getMessage());
    $error = "Error loading tenants. Please try again.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenants - Shop Rent Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .document-badge {
            font-size: 0.8em;
            margin: 2px;
        }
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
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Tenants</h2>
            <a href="create.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Add New Tenant
            </a>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Tenant ID</th>
                                <th>Name</th>
                                <th>Mobile</th>
                                <th>Aadhaar</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($tenants)): ?>
                                <?php foreach ($tenants as $tenant): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($tenant['tenant_id']); ?></td>
                                        <td><?php echo htmlspecialchars($tenant['name']); ?></td>
                                        <td><?php echo htmlspecialchars($tenant['mobile'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($tenant['aadhaar_number'] ?? '-'); ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="view.php?id=<?php echo $tenant['id']; ?>" 
                                                   class="btn btn-info btn-sm" title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="edit.php?id=<?php echo $tenant['id']; ?>" 
                                                   class="btn btn-primary btn-sm" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="delete.php?id=<?php echo $tenant['id']; ?>" 
                                                   class="btn btn-danger btn-sm" 
                                                   onclick="return confirm('Are you sure you want to delete this tenant?')"
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
                    emptyTable: "No Tenant records found"
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