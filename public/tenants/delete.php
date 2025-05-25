<?php
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../src/utils/Security.php";
require_once __DIR__ . "/../../src/utils/Logger.php";

// Initialize security
Security::init();
Security::requireLogin();

// Get tenant ID from URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: index.php");
    exit;
}

try {
    $db = Database::getInstance();
    
    // Check if tenant is linked to any shop
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM shops WHERE tenant_id = ?", [$id]);
    $row = $stmt->fetch();
    if ($row && $row['cnt'] > 0) {
        $_SESSION['error'] = "Cannot delete tenant: This tenant is linked to one or more shops.";
        header("Location: index.php");
        exit;
    }

    // Start transaction
    $db->beginTransaction();

    // Get tenant documents
    $stmt = $db->query("SELECT file_path FROM tenant_documents WHERE tenant_id = ?", [$id]);
    $documents = $stmt->fetchAll();

    // Delete tenant documents from storage
    $upload_dir = __DIR__ . "/../uploads/tenant_documents/";
    foreach ($documents as $doc) {
        $file_path = $upload_dir . $doc['file_path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }

    // Delete tenant documents from database
    $db->query("DELETE FROM tenant_documents WHERE tenant_id = ?", [$id]);

    // Delete tenant
    $result = $db->query("DELETE FROM tenants WHERE id = ?", [$id]);

    if ($result) {
        // Commit transaction
        $db->commit();
        Logger::info("Tenant deleted: ID " . $id);
        $_SESSION['success'] = "Tenant deleted successfully";
    } else {
        // Rollback transaction
        $db->rollBack();
        $_SESSION['error'] = "An error occurred. Please try again or contact support.";
    }
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($db)) {
        $db->rollBack();
    }
    Logger::error("Error deleting tenant: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred. Please try again or contact support.";
}

header("Location: index.php");
exit;

?>

<?php if ($error): ?>
    <div class="alert alert-danger"> <?php echo htmlspecialchars($error); ?> </div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"> <?php echo htmlspecialchars($success); ?> </div>
<?php endif; ?>

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