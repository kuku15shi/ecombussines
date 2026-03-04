<?php
require_once __DIR__ . '/config/db.php';
$newHash = password_hash('admin123', PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE admin SET password = ? WHERE email = ?");
if ($stmt->execute([$newHash, 'admin@luxestore.com'])) {
    echo "Admin password updated successfully to 'admin123'\n";
} else {
    echo "Update failed\n";
}
