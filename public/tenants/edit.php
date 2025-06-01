<?php
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../src/utils/Security.php";
require_once __DIR__ . "/../../src/utils/Logger.php";

// Initialize security
Security::init();
Security::requireLogin();

$error = null;
$success = null;

// Get tenant ID from URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: index.php");
    exit;
}

try {
    $db = Database::getInstance();
    $tenant = $db->query("SELECT * FROM tenants WHERE id = ?", [$id])->fetch();
    
    if (!$tenant) {
        header("Location: index.php");
        exit;
    }

    // Get tenant documents
    $stmt = $db->query("SELECT * FROM tenant_documents WHERE tenant_id = ? ORDER BY uploaded_at DESC", [$id]);
    $documents = $stmt->fetchAll();
    
    // Get existing document count
    $existing_doc_count = count($documents);
} catch (Exception $e) {
    Logger::error("Error fetching tenant: " . $e->getMessage());
    $error = "Error loading tenant details. Please try again.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input
    $tenant_id = Security::sanitizeInput($_POST['tenant_id'] ?? '');
    $name = Security::sanitizeInput($_POST['name'] ?? '');
    $aadhaar_number = Security::sanitizeInput($_POST['aadhaar_number'] ?? '');
    $mobile = Security::sanitizeInput($_POST['mobile'] ?? '');
    $email = Security::sanitizeInput($_POST['email'] ?? '');
    $pancard_number = Security::sanitizeInput($_POST['pancard_number'] ?? '');
    $address = Security::sanitizeInput($_POST['address'] ?? '');

    // Validate input
    $errors = [];
    
    // Check for required fields
    if (empty($tenant_id)) {
        $errors[] = "Tenant ID is required";
    }
    
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    
    if (empty($mobile)) {
        $errors[] = "Mobile number is required";
    }
    
    // Validate formats
    if (!empty($aadhaar_number) && !preg_match('/^\d{12}$/', $aadhaar_number)) {
        $errors[] = "Aadhaar number must be 12 digits";
    }
    
    if (!empty($mobile) && !preg_match('/^[0-9]{10}$/', $mobile)) {
        $errors[] = "Mobile number must be 10 digits";
    }
    
    if (!empty($email) && !Security::validateEmail($email)) {
        $errors[] = "Invalid email format";
    }
    
    if (!empty($pancard_number) && !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $pancard_number)) {
        $errors[] = "Invalid PAN card number format";
    }

    // Check for duplicates
    try {
        $db = Database::getInstance();
        
        // Check tenant_id
        if (!empty($tenant_id)) {
            $result = $db->query("SELECT COUNT(*) as count FROM tenants WHERE tenant_id = ? AND id != ?", 
                                [$tenant_id, $id])->fetch();
            if ($result['count'] > 0) {
                $errors[] = "Tenant ID already exists";
            }
        }
        
        // Check mobile
        if (!empty($mobile)) {
            $result = $db->query("SELECT COUNT(*) as count FROM tenants WHERE mobile = ? AND id != ?", 
                                [$mobile, $id])->fetch();
            if ($result['count'] > 0) {
                $errors[] = "Mobile number already registered";
            }
        }
        
        // Check aadhaar
        if (!empty($aadhaar_number)) {
            $result = $db->query("SELECT COUNT(*) as count FROM tenants WHERE aadhaar_number = ? AND id != ?", 
                                [$aadhaar_number, $id])->fetch();
            if ($result['count'] > 0) {
                $errors[] = "Aadhaar number already registered";
            }
        }
        
        // Check pancard
        if (!empty($pancard_number)) {
            $result = $db->query("SELECT COUNT(*) as count FROM tenants WHERE pancard_number = ? AND id != ?", 
                                [$pancard_number, $id])->fetch();
            if ($result['count'] > 0) {
                $errors[] = "PAN card number already registered";
            }
        }
        
        // Check email
        if (!empty($email)) {
            $result = $db->query("SELECT COUNT(*) as count FROM tenants WHERE email = ? AND id != ?", 
                                [$email, $id])->fetch();
            if ($result['count'] > 0) {
                $errors[] = "Email already registered";
            }
        }
    } catch (Exception $e) {
        $errors[] = "Error checking for duplicates: " . $e->getMessage();
    }

    // If there are any validation errors, stop here
    if (!empty($errors)) {
        $error = implode("<br>", $errors);
        error_log("Validation errors: " . $error);
    } else {
        // Handle document deletions
        $documents_to_delete = isset($_POST['delete_documents']) ? $_POST['delete_documents'] : [];
        
        // Validate file uploads
        $uploaded_files = [];
        $max_file_size = 5 * 1024 * 1024; // 5MB
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        $upload_dir = __DIR__ . "/../uploads/tenant_documents/";

        // Create upload directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                $errors[] = "Failed to create upload directory";
            }
        }

        if (isset($_FILES['documents'])) {
            $file_count = count($_FILES['documents']['name']);
            $existing_doc_count = count($documents);
            
            if ($file_count + $existing_doc_count - count($documents_to_delete) > 5) {
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

                // Update tenant
                $result = $db->query(
                    "UPDATE tenants SET 
                        tenant_id = ?, 
                        name = ?, 
                        aadhaar_number = ?, 
                        mobile = ?, 
                        email = ?, 
                        pancard_number = ?, 
                        address = ? 
                    WHERE id = ?",
                    [$tenant_id, $name, $aadhaar_number, $mobile, $email, $pancard_number, $address, $id]
                );

                if ($result) {
                    // Delete selected documents
                    if (!empty($documents_to_delete)) {
                        foreach ($documents_to_delete as $doc_id) {
                            $doc = $db->query("SELECT file_path FROM tenant_documents WHERE id = ? AND tenant_id = ?", 
                                            [$doc_id, $id])->fetch();
                            if ($doc) {
                                $file_path = $upload_dir . $doc['file_path'];
                                if (file_exists($file_path)) {
                                    unlink($file_path);
                                }
                                $db->query("DELETE FROM tenant_documents WHERE id = ?", [$doc_id]);
                            }
                        }
                    }

                    // Insert new document records
                    foreach ($uploaded_files as $file) {
                        $db->query(
                            "INSERT INTO tenant_documents (tenant_id, file_name, file_path, file_type, file_size) 
                             VALUES (?, ?, ?, ?, ?)",
                            [$id, $file['name'], $file['path'], $file['type'], $file['size']]
                        );
                    }

                    // Commit transaction
                    $db->commit();

                    Logger::info("Tenant updated: " . $name);
                    $_SESSION['success'] = "Tenant updated successfully";
                    header("Location: index.php");
                    exit;
                }
            } catch (Exception $e) {
                // Rollback transaction on error
                $db->rollBack();
                Logger::error("Error updating tenant: " . $e->getMessage());
                $error = "Error updating tenant. Please try again.";
            }
        } else {
            $error = implode("<br>", $errors);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Tenant - Shop Rent Management System</title>
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
        .existing-document {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 10px;
        }
        .document-preview {
            position: relative;
            display: inline-block;
            margin: 10px;
        }
        .delete-document {
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
            z-index: 1;
        }
        .delete-document:hover {
            background: #c82333;
        }
        .existing-documents {
            margin-bottom: 20px;
        }
        .document-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .document-item .document-info {
            flex-grow: 1;
        }
        .document-item .document-actions {
            margin-left: 10px;
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
                        <h2 class="card-title">Edit Tenant</h2>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <h5 class="alert-heading">Please fix the following errors:</h5>
                                <ul class="mb-0">
                                    <?php foreach (explode("<br>", $error) as $err): ?>
                                        <li><?php echo htmlspecialchars($err); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success"> <?php echo htmlspecialchars($success); ?> </div>
                        <?php endif; ?>

                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="tenant_id" class="form-label">Tenant ID *</label>
                                <input type="text" class="form-control" id="tenant_id" name="tenant_id" 
                                       value="<?php echo htmlspecialchars($tenant['tenant_id'] ?? ''); ?>" required placeholder="Unique identifier for the tenant">
                                <div class="form-text"></div>
                            </div>

                            <div class="mb-3">
                                <label for="name" class="form-label">Name *</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($tenant['name'] ?? ''); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="aadhaar_number" class="form-label">Aadhaar Number</label>
                                <input type="text" class="form-control" id="aadhaar_number" name="aadhaar_number"
                                       value="<?php echo htmlspecialchars($tenant['aadhaar_number'] ?? ''); ?>"
                                       pattern="\d{12}" maxlength="12" placeholder="12-digit Aadhaar number">
                                <div class="form-text"></div>
                            </div>

                            <div class="mb-3">
                                <label for="mobile" class="form-label">Mobile Number</label>
                                <input type="tel" class="form-control" id="mobile" name="mobile"
                                       value="<?php echo htmlspecialchars($tenant['mobile'] ?? ''); ?>"
                                       pattern="[0-9]{10}" maxlength="10" placeholder="10-digit mobile number">
                                <div class="form-text"></div>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?php echo htmlspecialchars($tenant['email'] ?? ''); ?>">
                                <div class="form-text"></div>
                            </div>

                            <div class="mb-3">
                                <label for="pancard_number" class="form-label">PAN Card Number</label>
                                <input type="text" class="form-control" id="pancard_number" name="pancard_number"
                                       value="<?php echo htmlspecialchars($tenant['pancard_number'] ?? ''); ?>"
                                       pattern="[A-Z]{5}[0-9]{4}[A-Z]{1}" maxlength="10" placeholder="Format: ABCDE1234F">
                                <div class="form-text"></div>
                            </div>

                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($tenant['address'] ?? ''); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Existing Documents</label>
                                <div class="existing-documents">
                                    <?php if ($documents && count($documents) > 0): ?>
                                        <?php foreach ($documents as $doc): ?>
                                            <div class="document-item" data-document-id="<?php echo $doc['id']; ?>">
                                                <div class="document-info">
                                                    <strong><?php echo htmlspecialchars($doc['file_name']); ?></strong>
                                                    <div class="text-muted small">
                                                        Uploaded: <?php echo date('M d, Y H:i', strtotime($doc['uploaded_at'])); ?><br>
                                                        Size: <?php echo number_format($doc['file_size'] / 1024 / 1024, 2); ?> MB
                                                    </div>
                                                </div>
                                                <div class="document-actions">
                                                    <a href="<?php echo BASE_PATH; ?>/uploads/tenant_documents/<?php echo $doc['file_path']; ?>" 
                                                       class="btn btn-sm btn-primary" target="_blank">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-danger" 
                                                            onclick="deleteDocument(<?php echo $doc['id']; ?>, <?php echo $id; ?>)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-muted">No documents uploaded</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="documents" class="form-label">Upload New Documents</label>
                                <input type="file" class="form-control" id="documents" name="documents[]" 
                                       accept=".pdf,.jpg,.jpeg,.png,.gif" multiple>
                                <div class="form-text">
                                    You can upload up to <?php echo 5 - count($documents); ?> more documents (PDF or images). Maximum file size: 5MB each.
                                </div>
                                <div id="file-preview" class="mt-2 d-flex flex-wrap"></div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="index.php" class="btn btn-secondary bi bi-arrow-left"> Back to List</a>
                                <button type="submit" class="btn btn-primary">Update Tenant</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        var TENANT_EDIT_ID = <?php echo (int)$id; ?>;
    </script>
    <script src="<?php echo BASE_PATH; ?>/assets/js/tenant_validation.js"></script>
    <script>
        // File upload preview
        document.getElementById('documents').addEventListener('change', function(e) {
            const preview = document.getElementById('file-preview');
            preview.innerHTML = '';
            
            const existingDocs = document.querySelectorAll('input[name="delete_documents[]"]:checked').length;
            const newFiles = this.files.length;
            const totalDocs = existingDocs + newFiles;
            
            if (totalDocs > 5) {
                alert('You can only have up to 5 documents in total');
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

        // Update file count when checking/unchecking existing documents
        document.querySelectorAll('input[name="delete_documents[]"]').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const fileInput = document.getElementById('documents');
                if (fileInput.files.length > 0) {
                    fileInput.dispatchEvent(new Event('change'));
                }
            });
        });

        // Function to delete existing document
        function deleteDocument(documentId, tenantId) {
            if (confirm('Are you sure you want to delete this document?')) {
                fetch(`delete_document.php?id=${documentId}&tenant_id=${tenantId}`, {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove the document item from the page
                        const documentElement = document.querySelector(`[data-document-id="${documentId}"]`);
                        if (documentElement) {
                            documentElement.remove();
                        }
                        alert('Document deleted successfully');
                        // Reload the page to update the document list
                        location.reload();
                    } else {
                        alert('Error deleting document: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting document');
                });
            }
        }

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