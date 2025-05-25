<?php
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../src/utils/Security.php";

Security::init();
Security::requireLogin();

$error = null;
$success = null;
$shops = [];

try {
    $db = Database::getInstance();
    $stmt = $db->query(
        "SELECT s.*, t.name as tenant_name, t.email as tenant_email FROM shops s LEFT JOIN tenants t ON s.tenant_id = t.id ORDER BY s.shop_no"
    );
    $shops = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "Error loading shops.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shop_no = $_POST['shop_no'] ?? '';
    $rent_month = $_POST['rent_month'] ?? '';
    $amount = $_POST['amount'] ?? '';
    $payment_date = $_POST['payment_date'] ?? '';
    $payment_method = $_POST['payment_method'] ?? '';
    $remark = $_POST['remark'] ?? '';

    $errors = [];
    if (empty($shop_no)) $errors[] = "Shop number is required.";
    if (empty($rent_month)) $errors[] = "Rent month is required.";
    if (empty($amount) || !is_numeric($amount)) $errors[] = "Valid rent amount is required.";
    if (empty($payment_date)) $errors[] = "Payment date is required.";
    if (empty($payment_method)) $errors[] = "Payment method is required.";

    // Find shop_id from shop_no
    $shop_id = null;
    foreach ($shops as $shop) {
        if ($shop['shop_no'] == $shop_no) {
            $shop_id = $shop['id'];
            break;
        }
    }
    if (!$shop_id) $errors[] = "Invalid shop number.";

    if (empty($errors)) {
        try {
            $db = Database::getInstance();
            $rent_year = NULL;
            $rent_month_val = NULL;
            $ob_fin_year = NULL;
            if (strpos($rent_month, 'OB-') === 0) {
                // Opening Balance payment
                $ob_fin_year = substr($rent_month, 3); // e.g. "2024to2025"
            } else {
                $yearMonth = explode('-', $rent_month);
                $rent_year = (int)$yearMonth[0];
                $rent_month_val = (int)$yearMonth[1];
            }
            $db->query(
                "INSERT INTO payments (shop_id, amount, payment_date, payment_method, notes, created_at, updated_at, rent_year, rent_month, ob_financial_year) VALUES (?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, ?)",
                [
                    $shop_id,
                    $amount,
                    $payment_date,
                    $payment_method,
                    $remark,
                    $rent_year,
                    $rent_month_val,
                    $ob_fin_year
                ]
            );
            $success = "Payment record created successfully.";
            header("Location: index.php?success=1");
            exit;
        } catch (Exception $e) {
            $error = "Error creating payment record: " . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make Payment - Shop Rent Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/common.css" rel="stylesheet">
    <style>
        .autocomplete-suggestions {
            border: 1px solid #ccc;
            background: #fff;
            position: absolute;
            z-index: 1000;
            max-height: 200px;
            overflow-y: auto;
            width: 100%;
        }
        .autocomplete-suggestion {
            padding: 8px 12px;
            cursor: pointer;
        }
        .autocomplete-suggestion:hover {
            background: #f0f0f0;
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
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Make Payment</h2>
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
                        <form method="POST" action="" autocomplete="off">
                            <div class="mb-3 position-relative">
                                <label for="shop_no" class="form-label">Shop Number *</label>
                                <input type="text" class="form-control" id="shop_no" name="shop_no" required value="<?php echo htmlspecialchars($_POST['shop_no'] ?? ''); ?>" autocomplete="off">
                                <div id="shopNoSuggestions" class="autocomplete-suggestions"></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Tenant Details</label>
                                <div id="tenant-info" class="form-text">
                                    <?php if (!empty($_POST['shop_no'])): 
                                        foreach ($shops as $shop) {
                                            if ($shop['shop_no'] == $_POST['shop_no']) {
                                                echo 'Name: ' . htmlspecialchars($shop['tenant_name']) . '<br>Email: ' . htmlspecialchars($shop['tenant_email']);
                                            }
                                        }
                                    else:
                                        echo 'Select a shop to view tenant details.';
                                    endif; ?>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="rent_month" class="form-label">Rent Month *</label>
                                <select class="form-select" id="rent_month" name="rent_month" required>
                                    <option value="">Select Rent Month</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="amount" class="form-label">Rent Amount *</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="amount" name="amount" value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>" required readonly>
                            </div>
                            <div class="mb-3">
                                <label for="payment_date" class="form-label">Payment Date *</label>
                                <input type="date" class="form-control" id="payment_date" name="payment_date" value="<?php echo htmlspecialchars($_POST['payment_date'] ?? date('Y-m-d')); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="payment_method" class="form-label">Payment Method *</label>
                                <select class="form-select" id="payment_method" name="payment_method" required>
                                    <option value="">Select Method</option>
                                    <?php $methods = ['Cash', 'Cheque', 'Bank Transfer', 'UPI', 'Other'];
                                    foreach ($methods as $method): ?>
                                        <option value="<?php echo $method; ?>" <?php if (isset($_POST['payment_method']) && $_POST['payment_method'] == $method) echo 'selected'; ?>><?php echo $method; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="remark" class="form-label">Remark</label>
                                <textarea class="form-control" id="remark" name="remark" rows="2"><?php echo htmlspecialchars($_POST['remark'] ?? ''); ?></textarea>
                            </div>
                            <div class="d-flex justify-content-between">
                                <a href="index.php" class="btn btn-secondary bi bi-arrow-left"> Back to List</a>
                                <button type="submit" class="btn btn-success">Make Payment</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
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

        // Prepare shop data for JS
        const shops = <?php echo json_encode($shops); ?>;
        // Autocomplete for shop_no
        const shopNoInput = document.getElementById('shop_no');
        const suggestionsBox = document.getElementById('shopNoSuggestions');
        shopNoInput.addEventListener('input', function() {
            const val = this.value.trim();
            suggestionsBox.innerHTML = '';
            if (!val) return;
            const matches = shops.filter(shop => shop.shop_no.toLowerCase().includes(val.toLowerCase()));
            matches.slice(0, 8).forEach(shop => {
                const div = document.createElement('div');
                div.className = 'autocomplete-suggestion';
                div.textContent = shop.shop_no + ' - ' + (shop.tenant_name || '');
                div.onclick = function() {
                    shopNoInput.value = shop.shop_no;
                    suggestionsBox.innerHTML = '';
                    updateTenantInfo(shop.shop_no);
                    fetchPendingRentMonths();
                };
                suggestionsBox.appendChild(div);
            });
        });
        document.addEventListener('click', function(e) {
            if (e.target !== shopNoInput) suggestionsBox.innerHTML = '';
        });
        // Update tenant info
        function updateTenantInfo(shopNo) {
            const infoDiv = document.getElementById('tenant-info');
            const shop = shops.find(s => s.shop_no == shopNo);
            if (shop) {
                infoDiv.innerHTML = 'Name: ' + (shop.tenant_name || '-') + '<br>Email: ' + (shop.tenant_email || '-');
            } else {
                infoDiv.innerHTML = 'Select a shop to view tenant details.';
            }
        }
        shopNoInput.addEventListener('change', function() {
            updateTenantInfo(this.value);
            fetchPendingRentMonths();
        });
        // Fetch pending rent months from API
        async function fetchPendingRentMonths() {
            const shopNo = shopNoInput.value;
            const rentMonthSelect = document.getElementById('rent_month');
            rentMonthSelect.innerHTML = '<option value="">Select Rent Month</option>';
            document.getElementById('amount').value = '';
            if (!shopNo) return;
            try {
                // Fetch pending rent months
                const response = await fetch(`/api/get_pending_rent_months.php?shop_no=${encodeURIComponent(shopNo)}`);
                const data = await response.json();
                // Fetch all unpaid opening balances for this shop
                const obResponse = await fetch(`/api/get_opening_balance.php?shop_no=${encodeURIComponent(shopNo)}`);
                const obData = await obResponse.json();
                if (obData && Array.isArray(obData.opening_balances)) {
                    obData.opening_balances.forEach(ob => {
                        const option = document.createElement('option');
                        option.value = 'OB-' + ob.financial_year;
                        option.textContent = 'Opening Balance (FY ' + ob.financial_year + ')';
                        option.setAttribute('data-ob-amount', ob.opening_balance);
                        rentMonthSelect.appendChild(option);
                    });
                }
                if (data && Array.isArray(data.months)) {
                    data.months.forEach(monthObj => {
                        const option = document.createElement('option');
                        option.value = monthObj.value;
                        option.textContent = monthObj.label;
                        rentMonthSelect.appendChild(option);
                    });
                }
            } catch (e) {}
        }
        // Fetch rent amount from rents table or opening balance
        async function fetchRentAmount() {
            const shopNo = shopNoInput.value;
            const rentMonth = document.getElementById('rent_month').value;
            const rentMonthSelect = document.getElementById('rent_month');
            if (!shopNo || !rentMonth) return;
            if (rentMonth.startsWith('OB-')) {
                // Opening balance
                // Find the selected option and get its data-ob-amount
                const selectedOption = rentMonthSelect.options[rentMonthSelect.selectedIndex];
                const obAmount = selectedOption ? selectedOption.getAttribute('data-ob-amount') : '';
                document.getElementById('amount').value = obAmount || '';
            } else {
                // Normal rent month
                try {
                    const response = await fetch(`/api/get_rent_amount.php?shop_no=${encodeURIComponent(shopNo)}&rent_month=${encodeURIComponent(rentMonth)}`);
                    const data = await response.json();
                    document.getElementById('amount').value = data.amount || '';
                } catch (e) {
                    document.getElementById('amount').value = '';
                }
            }
        }
        document.getElementById('rent_month').addEventListener('change', fetchRentAmount);
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 