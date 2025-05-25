<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/utils/Security.php';
Security::init();
Security::requireLogin();

$error = null;
$success = null;
$shops = [];

try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT s.*, t.name as tenant_name, t.email as tenant_email FROM shops s LEFT JOIN tenants t ON s.tenant_id = t.id ORDER BY s.shop_no");
    $shops = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "Error loading shops.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shop_no = $_POST['shop_no'] ?? '';
    $opening_balance = $_POST['opening_balance'] ?? '';
    $financial_year = $_POST['financial_year'] ?? '';
    $errors = [];
    if (empty($shop_no)) $errors[] = "Shop number is required.";
    if ($opening_balance === '' || !is_numeric($opening_balance)) $errors[] = "Valid opening balance is required.";
    if (empty($financial_year)) $errors[] = "Financial year is required.";
    // Validate financial year format and difference
    if (!preg_match('/^(\d{4}) to (\d{4})$/', $financial_year, $matches)) {
        $errors[] = "Financial year must be in format YYYY to YYYY (e.g., 2024 to 2025).";
    } else {
        $startYear = (int)$matches[1];
        $endYear = (int)$matches[2];
        if ($endYear - $startYear !== 1) {
            $errors[] = "Financial year must be for one year only (e.g., 2024 to 2025).";
        }
    }
    // Find shop_id and tenant_id from shop_no
    $shop_id = null;
    $tenant_id = null;
    foreach ($shops as $shop) {
        if ($shop['shop_no'] == $shop_no) {
            $shop_id = $shop['id'];
            $tenant_id = $shop['tenant_id'];
            break;
        }
    }
    if (!$shop_id) $errors[] = "Invalid shop number.";
    // Check for duplicate entry
    if ($shop_id && !empty($financial_year) && empty($errors)) {
        $db = Database::getInstance();
        $stmt = $db->query("SELECT COUNT(*) as cnt FROM opening_balances WHERE shop_id = ? AND financial_year = ?", [$shop_id, $financial_year]);
        $row = $stmt->fetch();
        if ($row && $row['cnt'] > 0) {
            $errors[] = "An opening balance for this shop and financial year already exists.";
        }
    }
    if (empty($errors)) {
        try {
            $db = Database::getInstance();
            $db->query(
                "INSERT INTO opening_balances (shop_id, tenant_id, opening_balance, financial_year, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())",
                [$shop_id, $tenant_id, $opening_balance, $financial_year]
            );
            header("Location: index.php?success=1");
            exit;
        } catch (Exception $e) {
            $error = "Error creating opening balance: " . $e->getMessage();
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
    <title>Add Opening Balance - Shop Rent Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
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
                    <h2 class="card-title">Add Opening Balance</h2>
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
                            <label for="opening_balance" class="form-label">Opening Balance *</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="opening_balance" name="opening_balance" value="<?php echo htmlspecialchars($_POST['opening_balance'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="financial_year" class="form-label">Financial Year *</label>
                            <input type="text" class="form-control" id="financial_year" name="financial_year" placeholder="e.g. 2024 to 2025" value="<?php echo htmlspecialchars($_POST['financial_year'] ?? ''); ?>" required>
                        </div>
                        <div class="d-flex justify-content-between">
                            <a href="index.php" class="btn btn-secondary bi bi-arrow-left"> Back to List</a>
                            <button type="submit" class="btn btn-success">Add Opening Balance</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
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
    });
</script>
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