<?php
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../src/utils/Security.php";
require_once __DIR__ . "/../../src/utils/Logger.php";

// Initialize security
Security::init();
Security::requireLogin();

$error = null;
$success = null;

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid shop ID";
    header("Location: index.php");
    exit;
}

$id = (int)$_GET['id'];

// Get shop details
try {
    $db = Database::getInstance();
    $stmt = $db->query("
        SELECT s.*, t.name as tenant_name, t.mobile as tenant_mobile, t.email as tenant_email
        FROM shops s
        LEFT JOIN tenants t ON s.tenant_id = t.id
        WHERE s.id = ?
    ", [$id]);
    $shop = $stmt->fetch();

    if (!$shop) {
        $_SESSION['error'] = "Shop not found";
        header("Location: index.php");
        exit;
    }

    // Get shop documents
    $stmt = $db->query("SELECT * FROM shop_documents WHERE shop_id = ? ORDER BY created_at DESC", [$id]);
    $documents = $stmt->fetchAll();

    // Get all tenants for dropdown
    $stmt = $db->query("SELECT id, tenant_id, name, mobile, email FROM tenants ORDER BY name");
    $tenants = $stmt->fetchAll();
} catch (Exception $e) {
    Logger::error("Error fetching shop details: " . $e->getMessage());
    $error = "Error loading shop details. Please try again.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input
    $shop_no = Security::sanitizeInput($_POST['shop_no'] ?? '');
    $location = Security::sanitizeInput($_POST['location'] ?? '');
    $tenant_id = Security::sanitizeInput($_POST['tenant_id'] ?? '');
    $agreement_start_date = Security::sanitizeInput($_POST['agreement_start_date'] ?? '');
    $agreement_end_date = Security::sanitizeInput($_POST['agreement_end_date'] ?? '');

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
        $existing_docs = count($documents);
        
        if ($file_count + $existing_docs > 5) {
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
                $file_path = $upload_dir . $new_file_name;

                if (move_uploaded_file($_FILES['documents']['tmp_name'][$i], $file_path)) {
                    $uploaded_files[] = [
                        'name' => $file_name,
                        'path' => $new_file_name,
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

            // Update shop
            $result = $db->query(
                "UPDATE shops SET 
                    shop_no = ?, 
                    location = ?, 
                    tenant_id = ?, 
                    agreement_start_date = ?, 
                    agreement_end_date = ? 
                WHERE id = ?",
                [$shop_no, $location, $tenant_id, $agreement_start_date, $agreement_end_date, $id]
            );

            if ($result) {
                // Insert new document records
                foreach ($uploaded_files as $file) {
                    $db->query(
                        "INSERT INTO shop_documents (shop_id, file_name, file_path, file_type, file_size) 
                         VALUES (?, ?, ?, ?, ?)",
                        [$id, $file['name'], $file['path'], $file['type'], $file['size']]
                    );
                }

                // Commit transaction
                $db->commit();

                Logger::info("Shop updated: " . $shop_no);
                $_SESSION['success'] = "Shop updated successfully";
                header("Location: index.php");
                exit;
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            if (isset($db)) {
                $db->rollBack();
            }
            
            Logger::error("Error updating shop: " . $e->getMessage());
            $error = "Error updating shop. Please try again.";
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
    <title>Edit Shop - Shop Rent Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .file-preview {
            max-width: 150px;
            max-height: 150px;
            margin: 5px;
            position: relative;
        }
        .file-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .file-preview .file-info {
            font-size: 0.8em;
            color: #666;
        }
        .remove-file {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            text-align: center;
            line-height: 20px;
            cursor: pointer;
            font-size: 12px;
        }
        .remove-file:hover {
            background: #c82333;
        }
        .document-item {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 10px;
            position: relative;
        }
        .document-actions {
            position: absolute;
            top: 10px;
            right: 10px;
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
                        <h2 class="card-title">Edit Shop</h2>
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

                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="shop_no" class="form-label">Shop Number *</label>
                                <input type="text" class="form-control" id="shop_no" name="shop_no" 
                                       value="<?php echo htmlspecialchars($shop['shop_no']); ?>" required placeholder="Unique identifier for the shop">
                                <div class="form-text"></div>
                            </div>

                            <div class="mb-3">
                                <label for="location" class="form-label">Location *</label>
                                <input type="text" class="form-control" id="location" name="location" 
                                       value="<?php echo htmlspecialchars($shop['location']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="tenant_id" class="form-label">Tenant *</label>
                                <select class="form-select" id="tenant_id" name="tenant_id" required>
                                    <option value="">Select Tenant</option>
                                    <?php foreach ($tenants as $tenant): ?>
                                        <option value="<?php echo $tenant['id']; ?>" 
                                                <?php echo $tenant['id'] == $shop['tenant_id'] ? 'selected' : ''; ?>
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
                                    <?php if ($shop['tenant_mobile']): ?>
                                        Mobile: <?php echo htmlspecialchars($shop['tenant_mobile']); ?>
                                        <?php if ($shop['tenant_email']): ?>
                                            <br>
                                            Email: <?php echo htmlspecialchars($shop['tenant_email']); ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        Select a tenant to view their contact information
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="agreement_start_date" class="form-label">Agreement Start Date *</label>
                                <input type="date" class="form-control" id="agreement_start_date" 
                                       name="agreement_start_date" 
                                       value="<?php echo $shop['agreement_start_date']; ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="agreement_end_date" class="form-label">Agreement End Date *</label>
                                <input type="date" class="form-control" id="agreement_end_date" 
                                       name="agreement_end_date" 
                                       value="<?php echo $shop['agreement_end_date']; ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Agreement Duration</label>
                                <div id="agreement-duration" class="form-text">
                                    <?php
                                    $start = new DateTime($shop['agreement_start_date']);
                                    $end = new DateTime($shop['agreement_end_date']);
                                    $duration = $start->diff($end);
                                    echo $duration->format('%y years, %m months');
                                    ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Existing Rent Agreement(s)</label>
                                <?php if ($documents): ?>
                                    <div class="row">
                                        <?php foreach ($documents as $doc): ?>
                                            <div class="col-md-4 mb-3">
                                                <div class="document-item">
                                                    <div class="document-actions">
                                                        <a href="<?php echo BASE_PATH; ?>/uploads/shop_documents/<?php echo $doc['file_path']; ?>" 
                                                           class="btn btn-sm btn-info" target="_blank" title="View">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-sm btn-danger" 
                                                                onclick="deleteDocument(<?php echo $doc['id']; ?>, <?php echo $id; ?>)" 
                                                                title="Delete">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                    <div class="file-info">
                                                        <?php echo htmlspecialchars($doc['file_name']); ?>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?php echo date('M d, Y', strtotime($doc['created_at'])); ?>
                                                            (<?php echo number_format($doc['file_size'] / 1024 / 1024, 2); ?> MB)
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No documents uploaded</p>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label for="documents" class="form-label">Upload New Agreement</label>
                                <input type="file" class="form-control" id="documents" name="documents[]" 
                                       accept=".pdf,.jpg,.jpeg,.png,.gif" multiple>
                                <div class="form-text">
                                    You can upload up to <?php echo 5 - count($documents); ?> more documents (PDF or images). 
                                    Maximum file size: 5MB each.
                                </div>
                                <div id="file-preview" class="mt-2 d-flex flex-wrap"></div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="index.php" class="btn btn-secondary bi bi-arrow-left"> Back to List</a>
                                <button type="submit" class="btn btn-primary">Update Shop</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        var SHOP_EDIT_ID = <?php echo (int)$id; ?>;
    </script>
    <script src="<?php echo BASE_PATH; ?>/assets/js/shop_validation.js"></script>
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
            
            const maxFiles = 5 - <?php echo count($documents); ?>;
            if (this.files.length > maxFiles) {
                alert(`You can only upload up to ${maxFiles} more files`);
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

        // Delete document
        function deleteDocument(documentId, shopId) {
            if (confirm('Are you sure you want to delete this document?')) {
                fetch(`/shops/delete_document.php?id=${documentId}&shop_id=${shopId}`, {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error deleting document: ' + data.error);
                    }
                })
                .catch(error => {
                    alert('Error deleting document: ' + error);
                });
            }
        }

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