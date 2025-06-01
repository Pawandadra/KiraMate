<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/utils/Security.php';
require_once __DIR__ . '/../src/utils/Logger.php';

// Initialize security
Security::init();

// Check if already logged in
if (Security::isAuthenticated()) {
    header('Location: ' . BASE_PATH . '/index.php');
    exit;
}

$error = '';
$success = $_GET['success'] ?? '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        try {
            $db = Database::getInstance();
            $stmt = $db->query('SELECT * FROM users WHERE username = ?', [$username]);
            $user = $stmt->fetch();

            if ($user && Security::verifyPassword($password, $user['password'])) {
                if (!$user['is_active']) {
                    $error = 'Your account is inactive. Please contact administrator.';
                } else {
                    Security::login($user['id'], $user);
                    Logger::info("User logged in: {$user['username']}");
                    header('Location: ' . BASE_PATH . '/index.php');
                    exit;
                }
            } else {
                $error = 'Invalid username or password.';
                Logger::warning("Failed login attempt for username: $username");
            }
        } catch (Exception $e) {
            $error = 'An error occurred. Please try again later.';
            Logger::error('Login error: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo htmlspecialchars(APP_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            max-width: 400px;
            width: 100%;
            padding: 20px;
        }
        .login-card {
            background: white;
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 420px;
            max-width: 100%;
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header img {
            max-width: 150px;
            margin-bottom: 20px;
        }
        .form-control {
            padding: 12px;
            font-size: 16px;
        }
        .btn-login {
            padding: 12px;
            font-size: 16px;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <div class="login-card">
            <div class="login-header">
                <img src="<?php echo BASE_PATH; ?>/assets/images/default_logo.png" alt="Logo" onerror="this.src='<?php echo BASE_PATH; ?>/assets/images/default-logo.png'">
                <h2><?php echo htmlspecialchars(APP_NAME); ?></h2>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Login</button>
                </div>
            </form>
            <div class="mt-3 text-center text-muted small">
                <p>Please contact administrator for password recovery.</p>
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