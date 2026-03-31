<?php
require_once __DIR__ . '/config/db.php';

$username = 'boss';
$password = 'kingboy@mizree1';
$hash = password_hash($password, PASSWORD_BCRYPT);

try {
    // Check if the boss user already exists
    $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    
    if ($stmt->fetch()) {
        // Update password if user exists
        $update = $pdo->prepare("UPDATE admin_users SET password = ? WHERE username = ?");
        $update->execute([$hash, $username]);
        echo "SUCCESS: Updated password for admin user 'boss'.\n";
    } else {
        // Create user if they don't exist
        $insert = $pdo->prepare("INSERT INTO admin_users (username, password, role) VALUES (?, ?, 'superadmin')");
        $insert->execute([$username, $hash]);
        echo "SUCCESS: Created new admin user 'boss' with password '$password'.\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
