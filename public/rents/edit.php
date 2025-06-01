<?php
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../src/utils/Security.php";
require_once __DIR__ . "/../../src/utils/Logger.php";

// Initialize security
Security::init();
Security::requireLogin();

$error = null;
$success = null;
$rent = null;
$shops = [];

// Get rent ID from URL
$rent_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$rent_id) {
    $_SESSION['error'] = "Invalid rent record ID";
    header("Location: index.php");
    exit;
}

try {
    $db = Database::getInstance();
    
    // Get rent record
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

    // Get all shops for dropdown
    $stmt = $db->query(
        "SELECT s.*, t.name as tenant_name, t.email as tenant_email 
         FROM shops s 
         LEFT JOIN tenants t ON s.tenant_id = t.id 
         ORDER BY s.shop_no"
    );
    $shops = $stmt->fetchAll();
} catch (Exception $e) {
    Logger::error("Error fetching rent record: " . $e->getMessage());
    $error = "Error loading rent record. Please try again.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input
    $shop_id = Security::sanitizeInput($_POST['shop_id'] ?? '');
    $rent_of_month = Security::sanitizeInput($_POST['rent_of_month'] ?? '');
    $calculated_rent = Security::sanitizeInput($_POST['calculated_rent'] ?? '0');
    $penalty = Security::sanitizeInput($_POST['penalty'] ?? '0');
    $amount_waved_off = Security::sanitizeInput($_POST['amount_waved_off'] ?? '0');
    $final_rent = Security::sanitizeInput($_POST['final_rent'] ?? '0');
    $remarks = Security::sanitizeInput($_POST['remarks'] ?? '');

    // Validate input
    $errors = [];
    
    if (empty($shop_id)) {
        $errors[] = "Shop is required";
    }
    
    if (empty($rent_of_month)) {
        $errors[] = "Rent month is required";
    }

    if (empty($errors)) {
        try {
            $db = Database::getInstance();
            
            // Parse the month-year value
            $rent_date = new DateTime($rent_of_month . '-01');
            $rent_year = $rent_date->format('Y');
            $rent_month = $rent_date->format('m');
            
            // Start transaction
            $db->beginTransaction();

            // Check if rent record already exists for this shop and month (excluding current record)
            $check_stmt = $db->query(
                "SELECT id FROM rents WHERE shop_id = ? AND rent_year = ? AND rent_month = ? AND id != ?",
                [$shop_id, $rent_year, $rent_month, $rent_id]
            );
            
            if ($check_stmt->rowCount() > 0) {
                throw new Exception("Rent record already exists for this shop and month");
            }

            // Update rent record
            $result = $db->query(
                "UPDATE rents SET 
                    shop_id = ?, 
                    rent_year = ?, 
                    rent_month = ?, 
                    calculated_rent = ?, 
                    penalty = ?, 
                    amount_waved_off = ?, 
                    final_rent = ?, 
                    remarks = ? 
                WHERE id = ?",
                [$shop_id, $rent_year, $rent_month, $calculated_rent, $penalty, $amount_waved_off, $final_rent, $remarks, $rent_id]
            );

            if ($result) {
                // Commit transaction
                $db->commit();

                Logger::info("Rent record updated: " . $rent_id);
                $_SESSION['success'] = "Rent record updated successfully";
                header("Location: index.php");
                exit;
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            if (isset($db)) {
                $db->rollBack();
            }
            
            Logger::error("Error updating rent record: " . $e->getMessage());
            $error = "Error updating rent record: " . $e->getMessage();
        }
    } else {
        $error = implode("<br>", $errors);
    }
}

// Format the rent month for the input field
$rent_month = sprintf("%04d-%02d", $rent['rent_year'], $rent['rent_month']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Rent - Shop Rent Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?php echo BASE_PATH; ?>/assets/css/common.css" rel="stylesheet">
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
                        <h2 class="card-title">Edit Rent Record</h2>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($success); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" id="rentForm">
                            <div class="mb-3">
                                <label for="shop_id" class="form-label">Shop Number *</label>
                                <select class="form-select" id="shop_id" name="shop_id" required>
                                    <option value="">Select Shop</option>
                                    <?php foreach ($shops as $shop): ?>
                                        <option value="<?php echo $shop['id']; ?>" 
                                                data-base-rent="<?php echo $shop['base_rent']; ?>"
                                                data-increment-percent="<?php echo $shop['rent_increment_percent']; ?>"
                                                data-increment-duration="<?php echo $shop['increment_duration_years']; ?>"
                                                data-agreement-start="<?php echo $shop['agreement_start_date']; ?>"
                                                data-tenant-name="<?php echo htmlspecialchars($shop['tenant_name'] ?? ''); ?>"
                                                data-tenant-email="<?php echo htmlspecialchars($shop['tenant_email'] ?? ''); ?>"
                                                <?php echo $shop['id'] == $rent['shop_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($shop['shop_no']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Tenant Information</label>
                                <div id="tenant-info" class="form-text">
                                    <?php if ($rent['tenant_name']): ?>
                                        Tenant Name: <?php echo htmlspecialchars($rent['tenant_name']); ?><br>
                                        Email: <?php echo htmlspecialchars($rent['tenant_email'] ?? ''); ?>
                                    <?php else: ?>
                                        Select a shop to view tenant information
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="rent_of_month" class="form-label">Rent month *</label>
                                <input type="month" class="form-control" id="rent_of_month" name="rent_of_month" 
                                       value="<?php echo $rent_month; ?>" required onchange="calculateRent()">
                            </div>

                            <div class="mb-3">
                                <label for="calculated_rent" class="form-label">Rent Amount</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="calculated_rent" 
                                       name="calculated_rent" value="<?php echo $rent['calculated_rent']; ?>" readonly>
                            </div>

                            <div class="mb-3">
                                <label for="penalty" class="form-label">Penalty</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="penalty" name="penalty" 
                                       value="<?php echo $rent['penalty']; ?>" onchange="calculateFinalRent()">
                            </div>

                            <div class="mb-3">
                                <label for="amount_waved_off" class="form-label">Amount Waved Off</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="amount_waved_off" 
                                       name="amount_waved_off" value="<?php echo $rent['amount_waved_off']; ?>" onchange="calculateFinalRent()">
                            </div>

                            <div class="mb-3">
                                <label for="remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="remarks" name="remarks" rows="3"><?php echo htmlspecialchars($rent['remarks'] ?? ''); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="final_rent" class="form-label">Final Rent *</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="final_rent" 
                                       name="final_rent" value="<?php echo $rent['final_rent']; ?>" readonly>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="index.php" class="btn btn-secondary bi bi-arrow-left"> Back to List</a>
                                <button type="submit" class="btn btn-primary">Update Rent</button>
                            </div>
                        </form>
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

        // Update tenant info when shop is selected
        document.getElementById('shop_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const tenantInfo = document.getElementById('tenant-info');
            
            if (this.value) {
                const tenantName = selectedOption.dataset.tenantName;
                const tenantEmail = selectedOption.dataset.tenantEmail;
                tenantInfo.innerHTML = `
                    Tenant Name: ${tenantName}<br>
                    Email: ${tenantEmail}
                `;
                calculateRent();
            } else {
                tenantInfo.innerHTML = 'Select a shop to view tenant information';
            }
        });

        // Calculate rent based on increment formula
        function calculateRent() {
            const shopSelect = document.getElementById('shop_id');
            const selectedOption = shopSelect.options[shopSelect.selectedIndex];
            const calculatedRentInput = document.getElementById('calculated_rent');
            const rentOfMonth = document.getElementById('rent_of_month').value;
            
            if (!shopSelect.value || !rentOfMonth) {
                calculatedRentInput.value = '0';
                return;
            }

            const baseRent = parseFloat(selectedOption.dataset.baseRent) || 0;
            const rentIncrementPercentage = parseFloat(selectedOption.dataset.incrementPercent) || 0;
            const rentAgreementStartDate = selectedOption.dataset.agreementStart;
            const durationAfterRentIncrement = parseInt(selectedOption.dataset.incrementDuration) || 0;

            if (!baseRent || !rentIncrementPercentage || !rentAgreementStartDate || !durationAfterRentIncrement) {
                calculatedRentInput.value = '0';
                return;
            }

            const startDate = new Date(rentAgreementStartDate);
            const selectedDate = new Date(rentOfMonth + '-01'); // Use first day of selected month

            // Calculate the total months passed from the start date to the selected date
            const totalMonthsPassed = (selectedDate.getFullYear() - startDate.getFullYear()) * 12 + 
                                    (selectedDate.getMonth() - startDate.getMonth());

            let rentAfterIncrement = baseRent;

            // Calculate increments applied in periods based on duration after rent increment
            if (totalMonthsPassed > 0) {
                const periodsPassed = Math.floor(totalMonthsPassed / (durationAfterRentIncrement * 12));

                for (let i = 0; i < periodsPassed; i++) {
                    rentAfterIncrement += baseRent * rentIncrementPercentage / 100;
                }
            }

            calculatedRentInput.value = rentAfterIncrement.toFixed(2);
            calculateFinalRent();
        }

        // Calculate final rent including penalty and waved off amount
        function calculateFinalRent() {
            const calculatedRent = parseFloat(document.getElementById('calculated_rent').value) || 0;
            const penalty = parseFloat(document.getElementById('penalty').value) || 0;
            const amountWavedOff = parseFloat(document.getElementById('amount_waved_off').value) || 0;

            const finalRent = calculatedRent + penalty - amountWavedOff;
            document.getElementById('final_rent').value = finalRent.toFixed(2);
        }

        // Calculate initial values when page loads
        document.addEventListener('DOMContentLoaded', function() {
            calculateRent();
        });
    </script>
</body>
</html> 