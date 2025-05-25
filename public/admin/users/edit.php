<?php
require_once __DIR__ . "/../../../config/database.php";
require_once __DIR__ . "/../../../src/utils/Security.php";
require_once __DIR__ . "/../../../src/utils/Logger.php";

// Initialize security
Security::init();
Security::requireLogin();

// Check if user is admin
if (!Security::isAdmin()) {
    $_SESSION['error'] = "Access denied. Admin privileges required.";
    header("Location: /index.php");
    exit;
}

$error = '';
$success = '';
$user = null;

// Get user ID from URL
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$user_id) {
    $_SESSION['error'] = "Invalid user ID";
    header("Location: index.php");
    exit;
}

try {
    $db = Database::getInstance();
    
    // Get user details
    $stmt = $db->query("SELECT * FROM users WHERE id = ?", [$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $_SESSION['error'] = "User not found";
        header("Location: index.php");
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $user = [
            'id' => $user_id,
            'username' => $_POST['username'] ?? '',
            'email' => $_POST['email'] ?? '',
            'role' => $_POST['role'] ?? 'user',
            'is_active' => isset($_POST['is_active'])
        ];
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validate input
        if (empty($user['username'])) {
            $error = 'Username is required.';
        } elseif (empty($user['email'])) {
            $error = 'Email is required.';
        } elseif (!filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format.';
        } elseif (!empty($password) && strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif (!empty($password) && $password !== $confirm_password) {
            $error = 'Passwords do not match.';
        }

        if (empty($error)) {
            // Check if username or email already exists (excluding current user)
            $stmt = $db->query(
                'SELECT COUNT(*) as count FROM users WHERE (username = ? OR email = ?) AND id != ?',
                [$user['username'], $user['email'], $user_id]
            );
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                $error = 'Username or email already exists.';
            } else {
                // Update user
                if (!empty($password)) {
                    // Update with new password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->query(
                        'UPDATE users SET username = ?, email = ?, password = ?, role = ?, is_active = ? WHERE id = ?',
                        [
                            $user['username'],
                            $user['email'],
                            $hashed_password,
                            $user['role'],
                            $user['is_active'] ? 1 : 0,
                            $user_id
                        ]
                    );
                } else {
                    // Update without changing password
                    $stmt = $db->query(
                        'UPDATE users SET username = ?, email = ?, role = ?, is_active = ? WHERE id = ?',
                        [
                            $user['username'],
                            $user['email'],
                            $user['role'],
                            $user['is_active'] ? 1 : 0,
                            $user_id
                        ]
                    );
                }
                
                $success = 'User updated successfully.';
                Logger::info("User updated: {$user['username']}");
            }
        }
    }
} catch (Exception $e) {
    $error = 'An error occurred while updating the user.';
    Logger::error('Error updating user: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - Shop Rent Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/common.css" rel="stylesheet">
</head>
<body>
<?php include __DIR__ . '/../../navbar.php'; ?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Edit User</h2>
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

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required 
                                   value="<?php echo htmlspecialchars($user['username']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required 
                                   value="<?php echo htmlspecialchars($user['email']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="password" name="password" minlength="8">
                            <div class="form-text">Leave blank to keep current password. Must be at least 8 characters if changed.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8">
                        </div>
                        
                        <div class="mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-control" id="role" name="role" required>
                                <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                                <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" 
                                       <?php echo $user['is_active'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="index.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Back to List
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Update User
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Auto-hide alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(function(alert) {
                alert.classList.add('fade');
                setTimeout(function() { alert.style.display = 'none'; }, 500);
            });
        }, 5000);
    });
</script>
</body>
</html> 