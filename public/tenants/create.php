<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../src/utils/Security.php";
require_once __DIR__ . "/../../src/utils/Logger.php";

// Initialize security
Security::init();
Security::requireLogin();

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST data received: " . print_r($_POST, true));
    error_log("FILES data received: " . print_r($_FILES, true));

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
            $result = $db->query("SELECT COUNT(*) as count FROM tenants WHERE tenant_id = ?", [$tenant_id])->fetch();
            if ($result['count'] > 0) {
                $errors[] = "Tenant ID already exists";
            }
        }
        
        // Check mobile
        if (!empty($mobile)) {
            $result = $db->query("SELECT COUNT(*) as count FROM tenants WHERE mobile = ?", [$mobile])->fetch();
            if ($result['count'] > 0) {
                $errors[] = "Mobile number already registered";
            }
        }
        
        // Check aadhaar
        if (!empty($aadhaar_number)) {
            $result = $db->query("SELECT COUNT(*) as count FROM tenants WHERE aadhaar_number = ?", [$aadhaar_number])->fetch();
            if ($result['count'] > 0) {
                $errors[] = "Aadhaar number already registered";
            }
        }
        
        // Check pancard
        if (!empty($pancard_number)) {
            $result = $db->query("SELECT COUNT(*) as count FROM tenants WHERE pancard_number = ?", [$pancard_number])->fetch();
            if ($result['count'] > 0) {
                $errors[] = "PAN card number already registered";
            }
        }
        
        // Check email
        if (!empty($email)) {
            $result = $db->query("SELECT COUNT(*) as count FROM tenants WHERE email = ?", [$email])->fetch();
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
        // Continue with file upload validation and tenant creation
        // Validate file uploads
        $uploaded_files = [];
        $max_file_size = 5 * 1024 * 1024; // 5MB
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        $base_upload_dir = __DIR__ . "/../uploads/tenant_documents/";

        // Create base upload directory if it doesn't exist
        if (!file_exists($base_upload_dir)) {
            if (!mkdir($base_upload_dir, 0755, true)) {
                $errors[] = "Failed to create upload directory";
            }
        }

        // Process file uploads
        if (!empty($_FILES['documents']['name'][0])) {
            $total_files = count($_FILES['documents']['name']);
            
            if ($total_files > 5) {
                $errors[] = "Maximum 5 documents allowed";
            } else {
                foreach ($_FILES['documents']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['documents']['error'][$key] === UPLOAD_ERR_OK) {
                        $file_name = $_FILES['documents']['name'][$key];
                        $file_type = $_FILES['documents']['type'][$key];
                        $file_size = $_FILES['documents']['size'][$key];
                        
                        // Validate file type
                        if (!in_array($file_type, $allowed_types)) {
                            $errors[] = "Invalid file type for $file_name. Allowed types: PDF, JPEG, PNG, GIF";
                            continue;
                        }
                        
                        // Validate file size
                        if ($file_size > $max_file_size) {
                            $errors[] = "File $file_name is too large. Maximum size is 5MB";
                            continue;
                        }
                        
                        // Generate unique filename
                        $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                        $unique_filename = uniqid() . '_' . time() . '.' . $file_extension;
                        
                        // Store file info for later processing
                        $uploaded_files[] = [
                            'tmp_name' => $tmp_name,
                            'name' => $file_name,
                            'type' => $file_type,
                            'size' => $file_size,
                            'unique_filename' => $unique_filename
                        ];
                    }
                }
            }
        }

        if (empty($errors)) {
            try {
                $db = Database::getInstance();
                
                // Start transaction
                $db->beginTransaction();

                // Insert tenant
                $result = $db->query(
                    "INSERT INTO tenants (tenant_id, name, aadhaar_number, mobile, pancard_number, email, address) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [$tenant_id, $name, $aadhaar_number, $mobile, $pancard_number, $email, $address]
                );

                if ($result) {
                    $tenant_id = $db->lastInsertId();
                    
                    // Create tenant-specific directory
                    $tenant_dir = $base_upload_dir . $tenant_id . '/';
                    if (!file_exists($tenant_dir)) {
                        if (!mkdir($tenant_dir, 0755, true)) {
                            throw new Exception("Failed to create tenant directory");
                        }
                    }

                    // Process uploaded files
                    foreach ($uploaded_files as $file) {
                        $destination = $tenant_dir . $file['unique_filename'];
                        
                        if (move_uploaded_file($file['tmp_name'], $destination)) {
                            // Insert document record
                            $db->query(
                                "INSERT INTO tenant_documents (tenant_id, file_name, file_path, file_type, file_size) 
                                 VALUES (?, ?, ?, ?, ?)",
                                [
                                    $tenant_id,
                                    $file['name'],
                                    $tenant_id . '/' . $file['unique_filename'],
                                    $file['type'],
                                    $file['size']
                                ]
                            );
                        } else {
                            throw new Exception("Failed to move uploaded file: " . $file['name']);
                        }
                    }

                    // Commit transaction
                    $db->commit();
                    
                    Logger::info("New tenant created: " . $name);
                    $_SESSION['success'] = "Tenant created successfully";
                    header("Location: index.php");
                    exit;
                } else {
                    throw new Exception("Failed to create tenant");
                }
            } catch (Exception $e) {
                // Rollback transaction on error
                if (isset($db)) {
                    $db->rollBack();
                }
                
                // Clean up any uploaded files if tenant creation failed
                if (isset($tenant_dir) && file_exists($tenant_dir)) {
                    array_map('unlink', glob("$tenant_dir/*.*"));
                    rmdir($tenant_dir);
                }
                
                Logger::error("Error creating tenant: " . $e->getMessage());
                $errors[] = "Error creating tenant. Please try again.";
            }
        } else {
            $error = implode("<br>", $errors);
            error_log("Validation errors: " . $error);
        }
    }
}

// Helper function to get upload error message
function getUploadErrorMessage($error_code) {
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:
            return "The uploaded file exceeds the upload_max_filesize directive in php.ini";
        case UPLOAD_ERR_FORM_SIZE:
            return "The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form";
        case UPLOAD_ERR_PARTIAL:
            return "The uploaded file was only partially uploaded";
        case UPLOAD_ERR_NO_TMP_DIR:
            return "Missing a temporary folder";
        case UPLOAD_ERR_CANT_WRITE:
            return "Failed to write file to disk";
        case UPLOAD_ERR_EXTENSION:
            return "A PHP extension stopped the file upload";
        default:
            return "Unknown upload error";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Tenant - Shop Rent Management System</title>
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
                        <h2 class="card-title">Add New Tenant</h2>
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

                        <form method="POST" action="<?php echo BASE_PATH; ?>/tenants/create.php" enctype="multipart/form-data" id="tenantForm">
                            <div class="mb-3">
                                <label for="tenant_id" class="form-label">Tenant ID *</label>
                                <input type="text" class="form-control" id="tenant_id" name="tenant_id" 
                                       value="<?php echo htmlspecialchars($tenant_id ?? ''); ?>" required placeholder="Unique identifier for the tenant">
                                <div class="form-text"></div>
                            </div>

                            <div class="mb-3">
                                <label for="name" class="form-label">Name *</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($name ?? ''); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="aadhaar_number" class="form-label">Aadhaar Number</label>
                                <input type="text" class="form-control" id="aadhaar_number" name="aadhaar_number"
                                       value="<?php echo htmlspecialchars($aadhaar_number ?? ''); ?>"
                                       pattern="\d{12}" maxlength="12" placeholder="12-digit Aadhaar number">
                                <div class="form-text"></div>
                            </div>

                            <div class="mb-3">
                                <label for="mobile" class="form-label">Mobile Number *</label>
                                <input type="tel" class="form-control" id="mobile" name="mobile"
                                       value="<?php echo htmlspecialchars($mobile ?? ''); ?>"
                                       pattern="[0-9]{10}" maxlength="10" required placeholder="10-digit mobile number">
                                <div class="form-text"></div>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?php echo htmlspecialchars($email ?? ''); ?>">
                                <div class="form-text"></div>
                            </div>

                            <div class="mb-3">
                                <label for="pancard_number" class="form-label">PAN Card Number</label>
                                <input type="text" class="form-control" id="pancard_number" name="pancard_number"
                                       value="<?php echo htmlspecialchars($pancard_number ?? ''); ?>"
                                       pattern="[A-Z]{5}[0-9]{4}[A-Z]{1}" maxlength="10" placeholder="Format: ABCDE1234F">
                                <div class="form-text"></div>
                            </div>

                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($address ?? ''); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="documents" class="form-label">Additional Documents</label>
                                <input type="file" class="form-control" id="documents" name="documents[]" 
                                       accept=".pdf,.jpg,.jpeg,.png,.gif" multiple>
                                <div class="form-text">
                                    You can upload up to 5 documents (PDF or images). Maximum file size: 5MB each.
                                </div>
                                <div id="file-preview" class="mt-2 d-flex flex-wrap"></div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="index.php" class="btn btn-secondary bi bi-arrow-left"> Back to List</a>
                                <button type="submit" class="btn btn-primary">Add Tenant</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        var TENANT_EDIT_ID = null;
    </script>
    <script src="<?php echo BASE_PATH; ?>/assets/js/tenant_validation.js"></script>
    <script>
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