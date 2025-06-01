<?php
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../src/utils/Security.php";
require_once __DIR__ . "/../../src/utils/Logger.php";

// Initialize security
Security::init();
Security::requireLogin();

// Check if user is admin, if not redirect to home page
if (!Security::isAdmin()) {
    header("Location: " . BASE_PATH . "/index.php");
    exit;
}

$error = null;
$success = null;

try {
    $db = Database::getInstance();
    
    // Get current settings
    $settings = $db->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $db->beginTransaction();
        
        try {
            // Handle file upload
            $logo_path = $settings['company_logo'] ?? 'default_logo.png';
            if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . "/../uploads/";
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_extension = strtolower(pathinfo($_FILES['company_logo']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (!in_array($file_extension, $allowed_extensions)) {
                    throw new Exception("Invalid file type. Only JPG, PNG and GIF files are allowed.");
                }
                
                $new_filename = 'company_logo_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $upload_path)) {
                    // Delete old logo if it exists and is not the default
                    if ($logo_path !== 'default_logo.png' && file_exists($upload_dir . $logo_path)) {
                        unlink($upload_dir . $logo_path);
                    }
                    $logo_path = $new_filename;
                }
            }
            
            // Update settings
            $db->query(
                "UPDATE system_settings SET setting_value = ? WHERE setting_key = 'company_name'",
                [$_POST['company_name']]
            );
            
            $db->query(
                "UPDATE system_settings SET setting_value = ? WHERE setting_key = 'company_address'",
                [$_POST['company_address']]
            );
            
            $db->query(
                "UPDATE system_settings SET setting_value = ? WHERE setting_key = 'company_logo'",
                [$logo_path]
            );
            
            $db->commit();
            
            Logger::info("System settings updated");
            $success = "Settings updated successfully";
            
            // Refresh settings
            $settings = $db->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
} catch (Exception $e) {
    Logger::error("Error updating system settings: " . $e->getMessage());
    $error = "Error updating settings: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - <?php echo htmlspecialchars(APP_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .preview-logo {
            max-width: 200px;
            max-height: 100px;
            margin: 10px 0;
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
                    <h2 class="card-title">System Settings</h2>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <form method="post" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="company_name" class="form-label">Company Name</label>
                            <input type="text" class="form-control" id="company_name" name="company_name" 
                                   value="<?php echo htmlspecialchars($settings['company_name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="company_address" class="form-label">Company Address</label>
                            <textarea class="form-control" id="company_address" name="company_address" 
                                      rows="3" required><?php echo htmlspecialchars($settings['company_address'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="company_logo" class="form-label">Company Logo</label>
                            <?php if (!empty($settings['company_logo'])): ?>
                                <div>
                                    <img src="<?php echo $settings['company_logo'] === 'default_logo.png' 
                                        ? BASE_PATH . '/assets/images/default_logo.png'
                                        : BASE_PATH . '/uploads/' . htmlspecialchars($settings['company_logo']); ?>" 
                                         class="preview-logo" alt="Current Logo">
                                </div>
                            <?php endif; ?>
                            <input type="file" class="form-control" id="company_logo" name="company_logo" 
                                   accept="image/jpeg,image/png,image/gif">
                            <div class="form-text">Upload a new logo (JPG, PNG or GIF). Leave empty to keep current logo.</div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="<?php echo BASE_PATH; ?>/admin/users" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Back to Users
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 