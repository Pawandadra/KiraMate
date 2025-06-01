<?php
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../src/utils/Security.php";
require_once __DIR__ . "/../../src/utils/Logger.php";

// Initialize security
Security::init();
Security::requireLogin();

$error = null;
$success = null;

// Get all tenants for dropdown
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT id, tenant_id, name, mobile, email FROM tenants ORDER BY name");
    $tenants = $stmt->fetchAll();
} catch (Exception $e) {
    Logger::error("Error fetching tenants: " . $e->getMessage());
    $error = "Error loading tenants. Please try again.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input
    $shop_no = Security::sanitizeInput($_POST['shop_no'] ?? '');
    $location = Security::sanitizeInput($_POST['location'] ?? '');
    $tenant_id = Security::sanitizeInput($_POST['tenant_id'] ?? '');
    $agreement_start_date = Security::sanitizeInput($_POST['agreement_start_date'] ?? '');
    $agreement_end_date = Security::sanitizeInput($_POST['agreement_end_date'] ?? '');
    $base_rent = Security::sanitizeInput($_POST['base_rent'] ?? '');
    $rent_increment_percent = Security::sanitizeInput($_POST['rent_increment_percent'] ?? '');
    $increment_duration_years = Security::sanitizeInput($_POST['increment_duration_years'] ?? '');

    // Validate input
    $errors = [];
    
    if (empty($shop_no)) {
        $errors[] = "Shop number is required";
    }
    
    if (empty($location)) {
        $errors[] = "Location is required";
    }
    
    if (empty($tenant_id)) {
        $errors[] = "Tenant is required";
    }
    
    if (empty($agreement_start_date)) {
        $errors[] = "Agreement start date is required";
    }
    
    if (empty($agreement_end_date)) {
        $errors[] = "Agreement end date is required";
    }
    
    if (!empty($agreement_start_date) && !empty($agreement_end_date)) {
        $start = new DateTime($agreement_start_date);
        $end = new DateTime($agreement_end_date);
        
        if ($end <= $start) {
            $errors[] = "Agreement end date must be after start date";
        }
    }

    if (empty($base_rent) || !is_numeric($base_rent) || $base_rent <= 0) {
        $errors[] = "Base rent is required and must be a positive number";
    }
    if (empty($rent_increment_percent) || !is_numeric($rent_increment_percent) || $rent_increment_percent < 0) {
        $errors[] = "Rent increment %age is required and must be a non-negative number";
    }
    if (empty($increment_duration_years) || !is_numeric($increment_duration_years) || $increment_duration_years <= 0 || intval($increment_duration_years) != $increment_duration_years) {
        $errors[] = "Duration (after rent increment) is required and must be a positive integer (years)";
    }

    // Validate file uploads
    $uploaded_files = [];
    $max_file_size = 5 * 1024 * 1024; // 5MB
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    $upload_dir = __DIR__ . "/../uploads/shop_documents/";

    // Create upload directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            $errors[] = "Failed to create upload directory";
        }
    }

    if (isset($_FILES['documents'])) {
        $file_count = count($_FILES['documents']['name']);
        
        if ($file_count > 5) {
            $errors[] = "Maximum 5 documents can be uploaded";
        }

        for ($i = 0; $i < $file_count; $i++) {
            if ($_FILES['documents']['error'][$i] === UPLOAD_ERR_OK) {
                $file_type = $_FILES['documents']['type'][$i];
                $file_size = $_FILES['documents']['size'][$i];
                $file_name = $_FILES['documents']['name'][$i];

                if (!in_array($file_type, $allowed_types)) {
                    $errors[] = "Invalid file type for $file_name. Only PDF and images are allowed.";
                    continue;
                }

                if ($file_size > $max_file_size) {
                    $errors[] = "File $file_name is too large. Maximum size is 5MB.";
                    continue;
                }

                $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                $new_file_name = uniqid() . '_' . time() . '.' . $file_extension;
                // Temporarily store in main upload dir, will move after shop is created
                $file_path = $upload_dir . $new_file_name;

                if (move_uploaded_file($_FILES['documents']['tmp_name'][$i], $file_path)) {
                    $uploaded_files[] = [
                        'name' => $file_name,
                        'path' => $new_file_name, // Will update after shop_id is known
                        'type' => $file_type,
                        'size' => $file_size
                    ];
                } else {
                    $errors[] = "Failed to upload $file_name";
                }
            }
        }
    }

    if (empty($errors)) {
        try {
            $db = Database::getInstance();
            
            // Start transaction
            $db->beginTransaction();

            // Insert shop
            $result = $db->query(
                "INSERT INTO shops (shop_no, location, tenant_id, agreement_start_date, agreement_end_date, base_rent, rent_increment_percent, increment_duration_years) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$shop_no, $location, $tenant_id, $agreement_start_date, $agreement_end_date, $base_rent, $rent_increment_percent, $increment_duration_years]
            );

            if ($result) {
                $shop_id = $db->lastInsertId();

                // Create shop-specific directory
                $shop_dir = $upload_dir . $shop_id . '/';
                if (!file_exists($shop_dir)) {
                    mkdir($shop_dir, 0755, true);
                }

                // Move uploaded files to shop-specific directory and update DB
                foreach ($uploaded_files as $file) {
                    $old_path = $upload_dir . $file['path'];
                    $new_path = $shop_dir . $file['path'];
                    if (rename($old_path, $new_path)) {
                        $db->query(
                            "INSERT INTO shop_documents (shop_id, file_name, file_path, file_type, file_size) 
                             VALUES (?, ?, ?, ?, ?)",
                            [$shop_id, $file['name'], $shop_id . '/' . $file['path'], $file['type'], $file['size']]
                        );
                    }
                }

                // Commit transaction
                $db->commit();

                Logger::info("New shop created: " . $shop_no);
                $_SESSION['success'] = "Shop created successfully";
                header("Location: index.php");
                exit;
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            if (isset($db)) {
                $db->rollBack();
            }
            
            Logger::error("Error creating shop: " . $e->getMessage());
            $error = "Error creating shop. Please try again.";
        }
    } else {
        $error = implode("<br>", $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Shop - Shop Rent Management System</title>
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
                    <div class="card-header">
                        <h2 class="card-title">Add New Shop</h2>
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

                        <form method="POST" action="<?php echo BASE_PATH; ?>/shops/create.php" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="shop_no" class="form-label">Shop Number *</label>
                                <input type="text" class="form-control" id="shop_no" name="shop_no" 
                                       value="<?php echo htmlspecialchars($shop_no ?? ''); ?>" required placeholder="Unique identifier for the shop">
                                <div class="form-text"></div>
                            </div>

                            <div class="mb-3">
                                <label for="location" class="form-label">Location *</label>
                                <input type="text" class="form-control" id="location" name="location" 
                                       value="<?php echo htmlspecialchars($location ?? ''); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="tenant_id" class="form-label">Tenant *</label>
                                <select class="form-select" id="tenant_id" name="tenant_id" required>
                                    <option value="">Select Tenant</option>
                                    <?php foreach ($tenants as $tenant): ?>
                                        <option value="<?php echo $tenant['id']; ?>" 
                                                data-mobile="<?php echo htmlspecialchars($tenant['mobile']); ?>"
                                                data-email="<?php echo htmlspecialchars($tenant['email']); ?>">
                                            <?php echo htmlspecialchars($tenant['tenant_id'] . ' - ' . $tenant['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Tenant Contact Information</label>
                                <div id="tenant-info" class="form-text">
                                    Select a tenant to view their contact information
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="agreement_start_date" class="form-label">Agreement Start Date *</label>
                                <input type="date" class="form-control" id="agreement_start_date" 
                                       name="agreement_start_date" required>
                            </div>

                            <div class="mb-3">
                                <label for="agreement_end_date" class="form-label">Agreement End Date *</label>
                                <input type="date" class="form-control" id="agreement_end_date" 
                                       name="agreement_end_date" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Agreement Duration</label>
                                <div id="agreement-duration" class="form-text">
                                    Select start and end dates to calculate duration
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="base_rent" class="form-label">Base Rent *</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="base_rent" name="base_rent" value="<?php echo htmlspecialchars($base_rent ?? ''); ?>" required>
                                <div class="form-text">Enter the base rent amount</div>
                            </div>
                            <div class="mb-3">
                                <label for="rent_increment_percent" class="form-label">Rent Increment %age *</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="rent_increment_percent" name="rent_increment_percent" value="<?php echo htmlspecialchars($rent_increment_percent ?? ''); ?>" required>
                                <div class="form-text">Enter the rent increment percentage (e.g., 5 for 5%)</div>
                            </div>
                            <div class="mb-3">
                                <label for="increment_duration_years" class="form-label">Duration (after rent increment) (in years) *</label>
                                <input type="number" min="1" step="1" class="form-control" id="increment_duration_years" name="increment_duration_years" value="<?php echo htmlspecialchars($increment_duration_years ?? ''); ?>" required>
                                <div class="form-text">Enter the duration in years after which rent is incremented</div>
                            </div>

                            <div class="mb-3">
                                <label for="documents" class="form-label">Rent Agreement</label>
                                <input type="file" class="form-control" id="documents" name="documents[]" 
                                       accept=".pdf,.jpg,.jpeg,.png,.gif" multiple>
                                <div class="form-text">
                                    You can upload up to 5 documents (PDF or images). Maximum file size: 5MB each.
                                </div>
                                <div id="file-preview" class="mt-2 d-flex flex-wrap"></div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="index.php" class="btn btn-secondary bi bi-arrow-left"> Back to List</a>
                                <button type="submit" class="btn btn-primary">Add Shop</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        var SHOP_EDIT_ID = null;
    </script>
    <script src="/assets/js/shop_validation.js"></script>
    <script>
        // Update tenant info when tenant is selected
        document.getElementById('tenant_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const tenantInfo = document.getElementById('tenant-info');
            
            if (this.value) {
                const mobile = selectedOption.dataset.mobile;
                const email = selectedOption.dataset.email;
                tenantInfo.innerHTML = `
                    Mobile: ${mobile}<br>
                    Email: ${email}
                `;
            } else {
                tenantInfo.innerHTML = 'Select a tenant to view their contact information';
            }
        });

        // Calculate agreement duration
        function calculateDuration() {
            const startDate = document.getElementById('agreement_start_date').value;
            const endDate = document.getElementById('agreement_end_date').value;
            const durationDiv = document.getElementById('agreement-duration');
            
            if (startDate && endDate) {
                const start = new Date(startDate);
                const end = new Date(endDate);
                
                if (end <= start) {
                    durationDiv.innerHTML = '<span class="text-danger">End date must be after start date</span>';
                    return;
                }
                
                const diff = end - start;
                const years = Math.floor(diff / (1000 * 60 * 60 * 24 * 365));
                const months = Math.floor((diff % (1000 * 60 * 60 * 24 * 365)) / (1000 * 60 * 60 * 24 * 30));
                
                let duration = '';
                if (years > 0) {
                    duration += years + ' year' + (years > 1 ? 's' : '');
                }
                if (months > 0) {
                    if (duration) duration += ', ';
                    duration += months + ' month' + (months > 1 ? 's' : '');
                }
                
                durationDiv.innerHTML = duration;
            } else {
                durationDiv.innerHTML = 'Select start and end dates to calculate duration';
            }
        }

        document.getElementById('agreement_start_date').addEventListener('change', calculateDuration);
        document.getElementById('agreement_end_date').addEventListener('change', calculateDuration);

        // File upload preview
        document.getElementById('documents').addEventListener('change', function(e) {
            const preview = document.getElementById('file-preview');
            preview.innerHTML = '';
            
            if (this.files.length > 5) {
                alert('You can only upload up to 5 files');
                this.value = '';
                return;
            }

            for (let i = 0; i < this.files.length; i++) {
                const file = this.files[i];
                if (file.size > 5 * 1024 * 1024) {
                    alert(`File ${file.name} is too large. Maximum size is 5MB.`);
                    continue;
                }

                const div = document.createElement('div');
                div.className = 'file-preview border rounded p-2 m-1';
                
                if (file.type.startsWith('image/')) {
                    const img = document.createElement('img');
                    img.src = URL.createObjectURL(file);
                    div.appendChild(img);
                } else {
                    const icon = document.createElement('i');
                    icon.className = 'bi bi-file-pdf fs-1';
                    div.appendChild(icon);
                }

                const info = document.createElement('div');
                info.className = 'file-info';
                info.textContent = `${file.name} (${(file.size / 1024 / 1024).toFixed(2)}MB)`;
                div.appendChild(info);

                // Add remove button
                const removeBtn = document.createElement('div');
                removeBtn.className = 'remove-file';
                removeBtn.innerHTML = '×';
                removeBtn.onclick = function() {
                    div.remove();
                    // Create a new FileList without the removed file
                    const dt = new DataTransfer();
                    for (let j = 0; j < this.files.length; j++) {
                        if (j !== i) {
                            dt.items.add(this.files[j]);
                        }
                    }
                    this.files = dt.files;
                }.bind(this);
                div.appendChild(removeBtn);

                preview.appendChild(div);
            }
        });

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