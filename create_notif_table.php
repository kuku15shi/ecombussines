<?php
require_once __DIR__ . '/config/db.php';
$sql = "CREATE TABLE IF NOT EXISTS site_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200),
    message TEXT,
    image_url VARCHAR(500),
    target_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$pdo->exec($sql);
echo "Table created successfully!";
?>
