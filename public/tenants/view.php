<?php
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../src/utils/Security.php";
require_once __DIR__ . "/../../src/utils/Logger.php";

// Initialize security
Security::init();
Security::requireLogin();

$error = null;
$tenant = null;
$documents = null;

// Get tenant ID from URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: index.php");
    exit;
}

try {
    $db = Database::getInstance();
    
    // Get tenant details
    $tenant = $db->query("SELECT * FROM tenants WHERE id = ?", [$id])->fetch();
    
    if (!$tenant) {
        header("Location: index.php");
        exit;
    }

    // Get tenant documents
    $stmt = $db->query("SELECT * FROM tenant_documents WHERE tenant_id = ? ORDER BY uploaded_at DESC", [$id]);
    $documents = $stmt->fetchAll();
    
} catch (Exception $e) {
    Logger::error("Error fetching tenant details: " . $e->getMessage());
    $error = "Error loading tenant details. Please try again.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Details - Shop Rent Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .document-preview {
            max-width: 200px;
            max-height: 200px;
            margin: 10px;
        }
        .document-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .document-card {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 15px;
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
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2 class="card-title mb-0">Tenant Details</h2>
                        <div>
                            <a href="edit.php?id=<?php echo $tenant['id']; ?>" class="btn btn-primary">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                            <a href="index.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Back to List
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"> <?php echo htmlspecialchars($error); ?> </div>
                        <?php endif; ?>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5>Basic Information</h5>
                                <table class="table">
                                    <tr>
                                        <th>Tenant ID:</th>
                                        <td><?php echo htmlspecialchars($tenant['tenant_id']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Name:</th>
                                        <td><?php echo htmlspecialchars($tenant['name']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Mobile:</th>
                                        <td><?php echo htmlspecialchars($tenant['mobile'] ?? '-'); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Email:</th>
                                        <td><?php echo htmlspecialchars($tenant['email'] ?? '-'); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h5>Additional Information</h5>
                                <table class="table">
                                    <tr>
                                        <th>Aadhaar Number:</th>
                                        <td><?php echo htmlspecialchars($tenant['aadhaar_number'] ?? '-'); ?></td>
                                    </tr>
                                    <tr>
                                        <th>PAN Card:</th>
                                        <td><?php echo htmlspecialchars($tenant['pancard_number'] ?? '-'); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Address:</th>
                                        <td><?php echo nl2br(htmlspecialchars($tenant['address'] ?? '-')); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <div class="mt-4">
                            <h5>Documents</h5>
                            <?php if ($documents && count($documents) > 0): ?>
                                <div class="row">
                                    <?php foreach ($documents as $doc): ?>
                                        <div class="col-md-4">
                                            <div class="document-card">
                                                <h6><?php echo htmlspecialchars($doc['file_name']); ?></h6>
                                                <p class="text-muted small">
                                                    Uploaded: <?php echo date('M d, Y H:i', strtotime($doc['uploaded_at'])); ?><br>
                                                    Size: <?php echo number_format($doc['file_size'] / 1024 / 1024, 2); ?> MB
                                                </p>
                                                <?php if (strpos($doc['file_type'], 'image/') === 0): ?>
                                                    <img src="<?php echo BASE_PATH; ?>/uploads/tenant_documents/<?php echo $doc['file_path']; ?>" 
                                                         class="document-preview img-thumbnail">
                                                <?php else: ?>
                                                    <div class="text-center">
                                                        <i class="bi bi-file-pdf fs-1"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="mt-2">
                                                    <a href="<?php echo BASE_PATH; ?>/uploads/tenant_documents/<?php echo $doc['file_path']; ?>" 
                                                       class="btn btn-sm btn-primary" target="_blank">
                                                        <i class="bi bi-eye"></i> View
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No documents uploaded</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
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