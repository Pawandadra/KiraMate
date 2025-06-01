<?php
session_start();
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../src/utils/Security.php";
require_once __DIR__ . "/../src/utils/Logger.php";

// Initialize security
Security::init();
Security::requireLogin();

// Log page access
Logger::info("Page accessed: " . $_SERVER['REQUEST_URI']);

// Add base path to all asset URLs
$asset_path = BASE_PATH . '/assets';

$error = null;
$summary = [];
try {
    $db = Database::getInstance();
    // Get all shops with tenant info
    $shops = $db->query("SELECT s.id, s.shop_no, t.name as tenant_name FROM shops s LEFT JOIN tenants t ON s.tenant_id = t.id ORDER BY s.shop_no")->fetchAll();
    foreach ($shops as $shop) {
        $shop_id = $shop['id'];
        // Opening balance (sum of all opening balances for this shop)
        $ob = $db->query("SELECT SUM(opening_balance) as ob_sum FROM opening_balances WHERE shop_id = ?", [$shop_id])->fetch();
        $opening_balance = $ob ? (float)$ob['ob_sum'] : 0.0;

        // Check if opening balance is paid
        $ob_status = $db->query(
            "SELECT COUNT(*) as unpaid_count 
             FROM opening_balances ob 
             WHERE ob.shop_id = ? 
             AND NOT EXISTS (
                 SELECT 1 FROM payments p 
                 WHERE p.shop_id = ob.shop_id 
                 AND p.ob_financial_year = ob.financial_year
             )",
            [$shop_id]
        )->fetch();
        $ob_status_text = $ob_status['unpaid_count'] > 0 ? 'Pending' : 'Paid';

        // Paid amount (sum of all rent payments for this shop, excluding opening balance payments)
        $paid = $db->query(
            "SELECT SUM(amount) as paid_sum 
             FROM payments 
             WHERE shop_id = ? 
             AND ob_financial_year IS NULL",
            [$shop_id]
        )->fetch();
        $paid_amount = $paid ? (float)$paid['paid_sum'] : 0.0;

        // Remaining unpaid rents (excluding opening balance)
        $remaining = $db->query(
            "SELECT SUM(r.final_rent) as remain_sum
             FROM rents r
             WHERE r.shop_id = ?
             AND NOT EXISTS (
                 SELECT 1 FROM payments p 
                 WHERE p.shop_id = r.shop_id 
                 AND p.rent_year = r.rent_year 
                 AND p.rent_month = r.rent_month
             )",
            [$shop_id]
        )->fetch();
        $remaining_amount = $remaining ? (float)$remaining['remain_sum'] : 0.0;

        $summary[] = [
            'shop_no' => $shop['shop_no'],
            'tenant_name' => $shop['tenant_name'],
            'opening_balance' => $opening_balance,
            'ob_status' => $ob_status_text,
            'remaining_amount' => $remaining_amount,
            'paid_amount' => $paid_amount
        ];
    }
} catch (Exception $e) {
    $error = 'Error loading summary.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo htmlspecialchars(APP_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?php echo $asset_path; ?>/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
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
<?php include __DIR__ . '/navbar.php'; ?>
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <h1>Welcome to <b>KiraMate</b></h1>
                <h3>A Shop Rent Management System</h3>
                <p>Please select an option from the navigation menu above to manage your shop rentals.</p>
            </div>
        </div>
    </div>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Summary</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#reportModal">
                <i class="bi bi-file-earmark-text"></i> Generate Report
            </button>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Shop No</th>
                                <th>Tenant</th>
                                <th>Opening Balance</th>
                                <th>Remaining Rent</th>
                                <th>Paid Rent</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($summary)): ?>
                                <?php foreach ($summary as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['shop_no']); ?></td>
                                        <td><?php echo htmlspecialchars($row['tenant_name'] ?? '-'); ?></td>
                                        <td>
                                            <?php if ($row['opening_balance'] > 0): ?>
                                                <?php echo number_format($row['opening_balance'], 2); ?> 
                                                <span class="badge <?php echo $row['ob_status'] === 'Paid' ? 'bg-success' : 'bg-warning'; ?>">
                                                    <?php echo $row['ob_status']; ?>
                                                </span>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo number_format($row['remaining_amount'], 2); ?></td>
                                        <td><?php echo number_format($row['paid_amount'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="container mt-4">
        <!-- Report Modal -->
        <div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="get" action="report.php" target="_blank">
                        <div class="modal-header">
                            <h5 class="modal-title" id="reportModalLabel">Generate Report</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="report_type" class="form-label">Report Type</label>
                                <select class="form-select" id="report_type" name="report_type" required>
                                    <option value="">Select report type</option>
                                    <option value="payments">Payments</option>
                                    <option value="rents">Rents</option>
                                    <option value="tenants">Tenants</option>
                                    <option value="shops">Shops</option>
                                    <option value="opening_balances">Opening Balances</option>
                                    <option value="summary">Summary</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="from_date" class="form-label">From Date (optional)</label>
                                <input type="date" class="form-control" id="from_date" name="from_date">
                            </div>
                            <div class="mb-3">
                                <label for="to_date" class="form-label">To Date (optional)</label>
                                <input type="date" class="form-control" id="to_date" name="to_date">
                            </div>
                            <div class="mb-3">
                                <label for="shop_no" class="form-label">Shop No (optional)</label>
                                <input type="text" class="form-control" id="shop_no" name="shop_no">
                            </div>
                            <div class="mb-3">
                                <label for="tenant_name" class="form-label">Tenant Name (optional)</label>
                                <input type="text" class="form-control" id="tenant_name" name="tenant_name">
                            </div>
                            <div class="mb-3" id="status-toggle" style="display:none;">
                                <label class="form-label">Status (optional)</label><br>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="status" id="status_paid" value="paid">
                                    <label class="form-check-label" for="status_paid">Paid</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="status" id="status_pending" value="pending">
                                    <label class="form-check-label" for="status_pending">Pending</label>
                                </div>
                            </div>
                            <div class="mb-3" id="sort-by-group">
                                <label for="sort_by" class="form-label">Sort by</label>
                                <select class="form-select" id="sort_by" name="sort_by">
                                    <option value="">Default</option>
                                </select>
                            </div>
                            <div class="mb-3" id="sort-order-group">
                                <label for="sort_order" class="form-label">Order</label>
                                <select class="form-select" id="sort_order" name="sort_order">
                                    <option value="asc">Ascending</option>
                                    <option value="desc">Descending</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Generate</button>
                        </div>
                    </form>
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
                    emptyTable: "Add records to view Summary"
                }
            });
        }
        // Show/hide status toggle based on report type
        var reportType = document.getElementById('report_type');
        var statusToggle = document.getElementById('status-toggle');
        function updateStatusToggle() {
            if (reportType.value === 'rents' || reportType.value === 'opening_balances') {
                statusToggle.style.display = '';
            } else {
                statusToggle.style.display = 'none';
                // Uncheck radios
                var radios = statusToggle.querySelectorAll('input[type=radio]');
                radios.forEach(function(r) { r.checked = false; });
            }
        }
        reportType.addEventListener('change', updateStatusToggle);
        updateStatusToggle();
        // Add dynamic sort by options
        const sortBySelect = document.getElementById('sort_by');
        const reportTypeSelect = document.getElementById('report_type');
        const sortOptions = {
            payments: [
                { value: 'shop_no', label: 'Shop No' },
                { value: 'tenant_name', label: 'Tenant' },
                { value: 'rent_year', label: 'Rent Year' },
                { value: 'rent_month', label: 'Rent Month' },
                { value: 'amount', label: 'Amount' },
                { value: 'payment_method', label: 'Payment Method' },
                { value: 'payment_date', label: 'Payment Date' }
            ],
            rents: [
                { value: 'shop_no', label: 'Shop No' },
                { value: 'tenant_name', label: 'Tenant' },
                { value: 'rent_year', label: 'Rent Year' },
                { value: 'rent_month', label: 'Rent Month' },
                { value: 'calculated_rent', label: 'Base Rent' },
                { value: 'penalty', label: 'Penalty' },
                { value: 'amount_waved_off', label: 'Waved Off' },
                { value: 'final_rent', label: 'Final Amount' }
            ],
            tenants: [
                { value: 'tenant_id', label: 'Tenant ID' },
                { value: 'name', label: 'Name' },
                { value: 'mobile', label: 'Mobile' },
                { value: 'email', label: 'Email' },
                { value: 'aadhaar_number', label: 'Aadhaar' },
                { value: 'pancard_number', label: 'PAN' }
            ],
            shops: [
                { value: 'shop_no', label: 'Shop No' },
                { value: 'location', label: 'Location' },
                { value: 'tenant_name', label: 'Tenant' },
                { value: 'agreement_start_date', label: 'Agreement Start' },
                { value: 'agreement_end_date', label: 'Agreement End' },
                { value: 'base_rent', label: 'Base Rent' },
                { value: 'rent_increment_percent', label: 'Rent Increment' },
                { value: 'increment_duration_years', label: 'Increment Duration' }
            ],
            opening_balances: [
                { value: 'shop_no', label: 'Shop No' },
                { value: 'tenant_name', label: 'Tenant' },
                { value: 'opening_balance', label: 'Opening Balance' },
                { value: 'financial_year', label: 'Financial Year' }
            ],
            summary: [
                { value: 'shop_no', label: 'Shop No' },
                { value: 'tenant_name', label: 'Tenant' },
                { value: 'opening_balance', label: 'Opening Balance' },
                { value: 'remaining_amount', label: 'Remaining Amount' },
                { value: 'paid_amount', label: 'Paid Amount' }
            ]
        };
        function updateSortByOptions() {
            const type = reportTypeSelect.value;
            sortBySelect.innerHTML = '<option value="">Default</option>';
            if (sortOptions[type]) {
                sortOptions[type].forEach(opt => {
                    const option = document.createElement('option');
                    option.value = opt.value;
                    option.textContent = opt.label;
                    sortBySelect.appendChild(option);
                });
            }
        }
        reportTypeSelect.addEventListener('change', updateSortByOptions);
        updateSortByOptions();
    });
    </script>
</body>
</html> 