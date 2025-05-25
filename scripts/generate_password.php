<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/utils/Security.php';

$password = 'admin123';
$hash = Security::hashPassword($password);

echo "Password: " . $password . "\n";
echo "Hash: " . $hash . "\n";

// Update the admin user with the new hash
try {
    $db = Database::getInstance();
    $stmt = $db->query('UPDATE users SET password = ? WHERE username = ?', [$hash, 'admin']);
    echo "Admin user password updated successfully.\n";
} catch (Exception $e) {
    echo "Error updating password: " . $e->getMessage() . "\n";
} 