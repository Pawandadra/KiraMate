<!-- public/navbar.php -->
<?php
require_once __DIR__ . '/../src/utils/Security.php';
Security::init();

$path = $_SERVER['SCRIPT_NAME'];
$parts = explode('/', trim($path, '/'));
$section = isset($parts[0]) ? $parts[0] : '';

$currentUser = Security::getCurrentUser();
$isAdmin = Security::isAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<style>
.navbar .nav-link.active {
    color: #fff !important;
    font-weight: bold;
    border-bottom: 3px solid #ffc107;
    background: rgba(255,255,255,0.08);
}
.navbar .nav-link {
    color: rgba(255,255,255,.7) !important;
    font-weight: 500;
    border-bottom: 3px solid transparent;
    transition: color 0.2s, border-bottom 0.2s, background 0.2s;
}
.navbar .nav-link:hover {
    color: #fff !important;
    background: rgba(255,255,255,0.04);
}
.navbar .btn-logout {
    color: rgba(255,255,255,.7) !important;
    font-weight: 500;
    transition: color 0.2s;
    text-decoration: none;
    padding: 0.5rem 1rem;
}
.navbar .btn-logout:hover {
    color: #fff !important;
}
.navbar .user-info {
    color: rgba(255,255,255,.7);
    margin-right: 1rem;
    font-weight: 500;
}
.dropdown-menu {
    background-color: #343a40;
    border: 1px solid rgba(255,255,255,.15);
}
.dropdown-item {
    color: rgba(255,255,255,.7) !important;
}
.dropdown-item:hover {
    color: #fff !important;
    background-color: rgba(255,255,255,.1);
}
.dropdown-item.active {
    background-color: rgba(255,255,255,.2);
}
.dropdown-divider {
    border-top-color: rgba(255,255,255,.15);
}
</style>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="<?php echo BASE_PATH; ?>/index.php">KiraMate</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link<?php if($section === 'rents') echo ' active'; ?>" href="<?php echo BASE_PATH; ?>/rents/index.php">Rents</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?php if($section === 'payments') echo ' active'; ?>" href="<?php echo BASE_PATH; ?>/payments/index.php">Payments</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?php if($section === 'shops') echo ' active'; ?>" href="<?php echo BASE_PATH; ?>/shops/index.php">Shops</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?php if($section === 'tenants') echo ' active'; ?>" href="<?php echo BASE_PATH; ?>/tenants/index.php">Tenants</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?php if($section === 'opening_balances') echo ' active'; ?>" href="<?php echo BASE_PATH; ?>/opening_balances/index.php">Opening Balances</a>
                </li>
                <?php if ($isAdmin): ?>
                <li class="nav-item">
                    <a class="nav-link<?php if($section === 'admin') echo ' active'; ?>" href="<?php echo BASE_PATH; ?>/admin/users/index.php">
                        <i class="bi bi-gear"></i> Management
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            <div class="navbar-nav align-items-center">
                <?php if ($currentUser): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($currentUser['username']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?php echo BASE_PATH; ?>/change_password.php"><i class="bi bi-key"></i> Change Password</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_PATH; ?>/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                        </ul>
                    </li>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<script>
function confirmLogout(event) {
    event.preventDefault();
    if (confirm('Are you sure you want to logout?')) {
        window.location.href = '/logout.php';
    }
}
</script>