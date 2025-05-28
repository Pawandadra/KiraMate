<?php
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../src/utils/Security.php";
require_once __DIR__ . "/../../src/utils/Logger.php";

// Initialize security
Security::init();
Security::requireLogin();

// Log page access
Logger::info("Accessing shop view page");

$error = null;
$shop = null;
$documents = [];

// Get shop ID from URL
$shop_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($shop_id <= 0) {
    $error = "Invalid shop ID";
} else {
    try {
        $db = Database::getInstance();
        
        // Get shop details with tenant information
        $stmt = $db->query(
            "SELECT s.*, t.name as tenant_name, t.mobile as tenant_mobile, t.email as tenant_email 
             FROM shops s 
             LEFT JOIN tenants t ON s.tenant_id = t.id 
             WHERE s.id = ?",
            [$shop_id]
        );
        $shop = $stmt->fetch();

        if (!$shop) {
            $error = "Shop not found";
        } else {
            // Get shop documents
            $stmt = $db->query(
                "SELECT * FROM shop_documents WHERE shop_id = ? ORDER BY created_at DESC",
                [$shop_id]
            );
            $documents = $stmt->fetchAll();
        }
    } catch (Exception $e) {
        Logger::error("Error fetching shop details: " . $e->getMessage());
        $error = "Error loading shop details. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Shop - Shop Rent Management System</title>
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
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php else: ?>
            <div class="row">
                <div class="col-md-8 offset-md-2">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h2 class="card-title mb-0">Shop Details</h2>
                            <div>
                                <a href="edit.php?id=<?php echo $shop_id; ?>" class="btn btn-primary">
                                    <i class="bi bi-pencil"></i> Edit
                                </a>
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> Back to List
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h5>Basic Information</h5>
                                    <table class="table table-borderless">
                                        <tr>
                                            <th>Shop Number:</th>
                                            <td><?php echo htmlspecialchars($shop['shop_no']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Location:</th>
                                            <td><?php echo htmlspecialchars($shop['location']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Tenant:</th>
                                            <td>
                                                <?php if ($shop['tenant_name']): ?>
                                                    <?php echo htmlspecialchars($shop['tenant_name']); ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        Mobile: <?php echo htmlspecialchars($shop['tenant_mobile']); ?><br>
                                                        Email: <?php echo htmlspecialchars($shop['tenant_email']); ?>
                                                    </small>
                                                <?php else: ?>
                                                    <span class="text-muted">No tenant assigned</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <h5>Agreement Details</h5>
                                    <table class="table table-borderless">
                                        <tr>
                                            <th>Start Date:</th>
                                            <td><?php echo date('d M Y', strtotime($shop['agreement_start_date'])); ?></td>
                                        </tr>
                                        <tr>
                                            <th>End Date:</th>
                                            <td><?php echo date('d M Y', strtotime($shop['agreement_end_date'])); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Duration:</th>
                                            <td>
                                                <?php
                                                $start = new DateTime($shop['agreement_start_date']);
                                                $end = new DateTime($shop['agreement_end_date']);
                                                $interval = $start->diff($end);
                                                echo $interval->format('%y years, %m months');
                                                ?>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-12">
                                    <h5>Rent Information</h5>
                                    <table class="table table-borderless">
                                        <tr>
                                            <th>Base Rent:</th>
                                            <td>₹<?php echo number_format($shop['base_rent'], 2); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Rent Increment:</th>
                                            <td><?php echo number_format($shop['rent_increment_percent'], 2); ?>%</td>
                                        </tr>
                                        <tr>
                                            <th>Increment Duration:</th>
                                            <td><?php echo $shop['increment_duration_years']; ?> years</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12">
                                    <h5>Rent Agreement Documents</h5>
                                    <?php if (empty($documents)): ?>
                                        <p class="text-muted">No documents uploaded</p>
                                    <?php else: ?>
                                        <div class="row">
                                            <?php foreach ($documents as $doc): ?>
                                                <div class="col-md-4 mb-3">
                                                    <div class="card">
                                                        <div class="card-body">
                                                            <?php if (strpos($doc['file_type'], 'image/') === 0): ?>
                                                                <img src="/uploads/shop_documents/<?php echo htmlspecialchars($doc['file_path']); ?>" 
                                                                     class="img-fluid mb-2" alt="Document preview">
                                                            <?php else: ?>
                                                                <i class="bi bi-file-pdf fs-1 text-danger"></i>
                                                            <?php endif; ?>
                                                            <p class="mb-1"><?php echo htmlspecialchars($doc['file_name']); ?></p>
                                                            <small class="text-muted">
                                                                <?php echo number_format($doc['file_size'] / 1024, 2); ?> KB
                                                            </small>
                                                            <div class="mt-2">
                                                                <a href="/uploads/shop_documents/<?php echo htmlspecialchars($doc['file_path']); ?>" 
                                                                   class="btn btn-sm btn-primary" target="_blank">
                                                                    <i class="bi bi-eye"></i> View
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
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