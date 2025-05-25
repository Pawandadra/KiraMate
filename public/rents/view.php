<?php
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../src/utils/Security.php";
require_once __DIR__ . "/../../src/utils/Logger.php";

// Initialize security
Security::init();
Security::requireLogin();

$error = null;
$rent = null;

// Get rent ID from URL
$rent_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$rent_id) {
    $_SESSION['error'] = "Invalid rent record ID";
    header("Location: index.php");
    exit;
}

try {
    $db = Database::getInstance();
    
    // Get rent record with shop and tenant information
    $stmt = $db->query(
        "SELECT r.*, s.shop_no, s.base_rent, s.rent_increment_percent, s.increment_duration_years, 
                s.agreement_start_date, t.name as tenant_name, t.email as tenant_email 
         FROM rents r 
         JOIN shops s ON r.shop_id = s.id 
         LEFT JOIN tenants t ON s.tenant_id = t.id 
         WHERE r.id = ?",
        [$rent_id]
    );
    $rent = $stmt->fetch();

    if (!$rent) {
        $_SESSION['error'] = "Rent record not found";
        header("Location: index.php");
        exit;
    }

    // Format the rent month for display
    $rent_month = DateTime::createFromFormat('Y-m', sprintf("%04d-%02d", $rent['rent_year'], $rent['rent_month']));
    $formatted_month = $rent_month->format('F Y');

} catch (Exception $e) {
    Logger::error("Error fetching rent record: " . $e->getMessage());
    $error = "Error loading rent record. Please try again.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Rent - Shop Rent Management System</title>
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
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2 class="card-title mb-0">Rent Details</h2>
                        <div>
                            <a href="edit.php?id=<?php echo $rent_id; ?>" class="btn btn-primary">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                            <a href="index.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Back to List
                            </a>
                            <a href="receipt.php?id=<?php echo $rent_id; ?>" class="btn btn-success" target="_blank">
                                <i class="bi bi-printer"></i> Generate Receipt
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <div class="col-md-6">
                                    <h4 class="mb-3">Shop Information</h4>
                                    <table class="table table-bordered">
                                        <tr>
                                            <th style="width: 40%">Shop Number</th>
                                            <td><?php echo htmlspecialchars($rent['shop_no']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Base Rent</th>
                                            <td>₹<?php echo number_format($rent['base_rent'], 2); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Rent Increment</th>
                                            <td><?php echo $rent['rent_increment_percent']; ?>%</td>
                                        </tr>
                                        <tr>
                                            <th>Increment Duration</th>
                                            <td><?php echo $rent['increment_duration_years']; ?> years</td>
                                        </tr>
                                        <tr>
                                            <th>Agreement Start Date</th>
                                            <td><?php echo date('d M Y', strtotime($rent['agreement_start_date'])); ?></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <h4 class="mb-3">Tenant Information</h4>
                                    <table class="table table-bordered">
                                        <tr>
                                            <th style="width: 40%">Tenant Name</th>
                                            <td><?php echo htmlspecialchars($rent['tenant_name'] ?? 'Not Assigned'); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Email</th>
                                            <td><?php echo htmlspecialchars($rent['tenant_email'] ?? 'Not Available'); ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <div class="row mt-4">
                                <div class="col-12">
                                    <h4 class="mb-3">Rent Information</h4>
                                    <table class="table table-bordered">
                                        <tr>
                                            <th style="width: 40%">Rent Month</th>
                                            <td><?php echo $formatted_month; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Rent Amount</th>
                                            <td>₹<?php echo number_format($rent['calculated_rent'], 2); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Penalty</th>
                                            <td>₹<?php echo number_format($rent['penalty'], 2); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Amount Waved Off</th>
                                            <td>₹<?php echo number_format($rent['amount_waved_off'], 2); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Final Rent</th>
                                            <td>₹<?php echo number_format($rent['final_rent'], 2); ?></td>
                                        </tr>
                                        <?php if (!empty($rent['remarks'])): ?>
                                        <tr>
                                            <th>Remarks</th>
                                            <td><?php echo nl2br(htmlspecialchars($rent['remarks'])); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
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